<?php

namespace YourVendor\AutoPay\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use YourVendor\AutoPay\DTOs\PaymentResult;
use YourVendor\AutoPay\Exceptions\AutoPayException;
use YourVendor\AutoPay\Models\AutoPayTransaction;

class AutoPayXmlProcessor
{
    protected $authUrl;
    protected $requestUrl;
    protected $clientId;
    protected $clientSecret;
    protected $terminalId;
    protected $switchingCharge;

    public function __construct()
    {
        $this->authUrl = config('autopay.auth_url');
        $this->requestUrl = config('autopay.request_url');
        $this->clientId = config('autopay.client_id');
        $this->clientSecret = config('autopay.client_secret');
        $this->terminalId = config('autopay.terminal_id');
        $this->switchingCharge = config('autopay.switching_charge', 0);
    }

    /**
     * Process bulk payment
     *
     * @param array $batchData ['batch_no', 'batch_name', 'month', 'year']
     * @param array $payments Array of payment records
     * @param string $sourceAccount
     * @param string $narration
     * @return PaymentResult
     * @throws AutoPayException
     */
    public function process(
        array $batchData,
        array $payments,
        string $sourceAccount,
        string $narration
    ): PaymentResult {
        try {
            // Step 1: Authenticate with Interswitch
            $authResponse = $this->authenticate();

            if ($authResponse['http_status'] !== 200) {
                throw new AutoPayException('Authentication failed with Interswitch');
            }

            $securityToken = $this->extractSecurityToken($authResponse['response']);

            if (strlen($securityToken) < 50) {
                throw new AutoPayException('Invalid Security Token from Payment Switch');
            }

            // Step 2: Build XML payload
            $batchName = $this->generateBatchName($batchData['batch_name'], $batchData['month']);
            $xmlPayload = $this->buildXmlPayload(
                $batchData,
                $payments,
                $sourceAccount,
                $narration,
                $batchName
            );

            if ($xmlPayload === false) {
                throw new AutoPayException('Processing charge misconfigured');
            }

            // Step 3: Make payment request
            $paymentResponse = $this->makePaymentRequest($securityToken, $xmlPayload['xml']);

            if ($paymentResponse['http_status'] !== 200) {
                throw new AutoPayException('Payment request failed');
            }

            // Step 4: Parse response
            $result = $this->parsePaymentResponse($paymentResponse['response']);

            // Step 5: Save transaction record
            $this->saveTransaction($batchData, $batchName, $result, $xmlPayload['metadata']);

            return new PaymentResult(
                success: $result['success'],
                responseCode: $result['response_code'],
                message: $result['message'],
                batchName: $batchName,
                transactionId: $batchData['batch_no'],
                metadata: $xmlPayload['metadata']
            );
        } catch (\Exception $e) {
            $this->logError($e);
            throw new AutoPayException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Authenticate with Interswitch
     */
    protected function authenticate(): array
    {
        $content = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:aut="http://techquest.interswitchng.com/authenticate/">
            <soap:Header/>
            <soap:Body>
                <aut:AuthenticationRequest>
                    <aut:Service>authenticate</aut:Service>
                    <aut:Username>' . $this->clientId . '</aut:Username>
                    <aut:Password>' . $this->clientSecret . '</aut:Password>
                </aut:AuthenticationRequest>
            </soap:Body>
        </soap:Envelope>';

        $headers = [
            "Content-type: text/xml;charset=\"utf-8\"",
            "Accept: text/xml",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "Content-length: " . strlen($content),
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->authUrl);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_VERBOSE, '1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->log('Authentication request', ['http_status' => $http_status]);

        return ['http_status' => $http_status, 'response' => $result];
    }

    /**
     * Make payment request to Interswitch
     */
    protected function makePaymentRequest(string $securityToken, string $xmlPayload): array
    {
        $headers = [
            "Content-type: text/xml;charset=\"utf-16\"",
            "SecurityToken: " . $securityToken,
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "Content-length: " . strlen($xmlPayload),
            "Connection: keep-alive",
            "Expect:",
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->requestUrl);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_VERBOSE, '1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlPayload);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->log('Payment request', ['http_status' => $http_status]);

        return ['http_status' => $http_status, 'response' => $result];
    }

    /**
     * Extract security token from authentication response
     */
    protected function extractSecurityToken(string $xmlResponse): string
    {
        $result = xml2array(str_replace("</ ", "</", $xmlResponse));
        return $result['ns3:Envelope']['ns3:Body']['AuthenticationResponse']['Token'] ?? '';
    }

    /**
     * Build XML payload for payment
     */
    protected function buildXmlPayload(
        array $batchData,
        array $payments,
        string $sourceAccount,
        string $narration,
        string $batchName
    ): array|false {
        $paymentsBatchXml = "";
        $amount = 0;
        $allBeneficiaryCode = "";
        $totalEmployees = 0;

        // Process each payment
        foreach ($payments as $payment) {
            // Apply switching charge
            $netAmount = $payment['amount'] - $this->switchingCharge;

            if ($netAmount <= 0) {
                continue; // Skip if net amount is zero or negative
            }

            // Convert to kobo (multiply by 100)
            $amountInKobo = round($netAmount, 2) * 100;

            $bankCode = $payment['bank_code'] ?? '0';
            $accountType = $this->getAccountTypeCode($payment['account_type'] ?? 'SAVINGS');

            $paymentRef = uniqid();
            $employeeNo = $payment['employee_no'] ?? $paymentRef;
            $beneficiaryName = preg_replace("/[^a-zA-Z\s]/", " ", $payment['beneficiary_name']);

            $paymentsBatchXml .= '<aut:Payment>
                <aut:PaymentRef>' . $paymentRef . '</aut:PaymentRef>
                <aut:PaymentType>DC</aut:PaymentType>
                <aut:BeneficiaryCode>' . $employeeNo . '</aut:BeneficiaryCode>
                <aut:Narration>' . htmlspecialchars($narration) . '</aut:Narration>
                <aut:Amount>' . $amountInKobo . '</aut:Amount>
                <aut:CurrencyCode>NGN</aut:CurrencyCode>
                <aut:BeneficiaryName>' . $beneficiaryName . '</aut:BeneficiaryName>
                <aut:AccountNumber>' . $payment['account_number'] . '</aut:AccountNumber>
                <aut:AccountType>' . $accountType . '</aut:AccountType>
                <aut:BankCBNCode>' . $bankCode . '</aut:BankCBNCode>
            </aut:Payment>';

            $amount += $amountInKobo;
            $allBeneficiaryCode .= $employeeNo;
            $totalEmployees++;
        }

        if ($totalEmployees === 0) {
            return false;
        }

        // Calculate MAC
        $macAmount = $amount / 2; // Divide by 2 as per ISW requirement
        $macString = $this->terminalId . $allBeneficiaryCode . $macAmount;
        $mac = hash('sha512', $macString);

        // Build request details
        $requestDetails = '<aut:RequestDetails>
            <aut:TerminalId>' . $this->terminalId . '</aut:TerminalId>
            <aut:BatchName>' . $batchName . '</aut:BatchName>
            <aut:IsBulkRemittance>Y</aut:IsBulkRemittance>
            <aut:SourceAccount>' . $sourceAccount . '</aut:SourceAccount>
            <aut:IsOffline>N</aut:IsOffline>
            <aut:IsConsolidated>Y</aut:IsConsolidated>
            <aut:MAC>' . $mac . '</aut:MAC>
            <aut:PaymentsBatch>
                ' . $paymentsBatchXml . '
            </aut:PaymentsBatch>
        </aut:RequestDetails>';

        // Build SOAP envelope
        $content = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:aut="http://techquest.interswitchng.com/autopay/">
            <soapenv:Header/>
            <soapenv:Body>
                <aut:UploadPaymentBatch>
                    <aut:UploadPaymentBatchRequest>
                        <aut:Service>autopayupload</aut:Service>
                        <aut:xmlParams>' . $requestDetails . '</aut:xmlParams>
                    </aut:UploadPaymentBatchRequest>
                </aut:UploadPaymentBatch>
            </soapenv:Body>
        </soapenv:Envelope>';

        $metadata = [
            'batch_name' => $batchName,
            'batch_no' => $batchData['batch_no'],
            'month' => $batchData['month'],
            'year' => $batchData['year'],
            'total_amount' => $amount / 100, // Convert back to Naira
            'total_employees' => $totalEmployees,
            'source_account' => $sourceAccount,
            'narration' => $narration,
        ];

        return [
            'xml' => $content,
            'metadata' => $metadata
        ];
    }

    /**
     * Parse payment response
     */
    protected function parsePaymentResponse(string $xmlResponse): array
    {
        $result = xml2array(str_replace("</ ", "</", $xmlResponse));

        if (!isset($result['ns2:Envelope'])) {
            return [
                'success' => false,
                'response_code' => 'E00',
                'message' => 'Invalid response XML from payment switch'
            ];
        }

        $responseData = $result['ns2:Envelope']['ns2:Body']['UploadPaymentBatchResponse']['UploadPaymentBatchResponseResult'] ?? null;

        if (!isset($responseData['ResponseCode'])) {
            return [
                'success' => false,
                'response_code' => 'E00',
                'message' => 'Could not fetch ResponseCode from payment switch'
            ];
        }

        $responseCode = $responseData['ResponseCode'];
        $responseMessage = $responseData['ResponseMessage'] ?? 'Unknown response';

        if ($responseCode === 'E21') {
            return [
                'success' => false,
                'response_code' => $responseCode,
                'message' => 'Error connecting to payment switch. Try again'
            ];
        }

        if ($responseCode === '90000') {
            return [
                'success' => true,
                'response_code' => $responseCode,
                'message' => 'Payment processed successfully'
            ];
        }

        return [
            'success' => false,
            'response_code' => $responseCode,
            'message' => "Payment failed: {$responseMessage}"
        ];
    }

    /**
     * Save transaction to database
     */
    protected function saveTransaction(array $batchData, string $batchName, array $result, array $metadata): void
    {
        AutoPayTransaction::create([
            'batch_no' => $batchData['batch_no'],
            'batch_name' => $batchName,
            'month' => $batchData['month'],
            'year' => $batchData['year'],
            'source_account' => $metadata['source_account'],
            'transaction_session_id' => $batchName,
            'response_code' => $result['response_code'],
            'status' => $result['success'] ? 'SUCCESS' : 'FAILED',
            'total_amount' => $metadata['total_amount'],
            'total_count' => $metadata['total_employees'],
            'narration' => $metadata['narration'],
        ]);
    }

    /**
     * Generate batch name
     */
    protected function generateBatchName(string $batchName, string $month): string
    {
        return preg_replace("/[^a-zA-Z0-9]/", "_", $batchName) . '_' . $month . '_' . uniqid();
    }

    /**
     * Get account type code
     */
    protected function getAccountTypeCode(string $accountType): int
    {
        return match (strtoupper($accountType)) {
            'SAVINGS' => 10,
            'CURRENT' => 20,
            default => 10,
        };
    }

    /**
     * Log message
     */
    protected function log(string $message, array $context = []): void
    {
        if (config('autopay.logging.enabled')) {
            Log::channel(config('autopay.logging.channel'))->info($message, $context);
        }
    }

    /**
     * Log error
     */
    protected function logError(\Exception $e): void
    {
        if (config('autopay.logging.enabled')) {
            Log::channel(config('autopay.logging.channel'))->error('AutoPay Error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
