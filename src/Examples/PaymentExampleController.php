<?php

namespace DanOgbo\AutoPay\Examples;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use DanOgbo\AutoPay\Facades\AutoPay;
use DanOgbo\AutoPay\Exceptions\AutoPayException;

/**
 * Example controller showing how to use the AutoPay package
 * Copy this to your application's Controllers directory and customize as needed
 */
class PaymentExampleController extends Controller
{
    /**
     * Process bulk salary payment
     */
    public function processSalary(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'batch_no' => 'required|string',
            'batch_name' => 'required|string',
            'month' => 'required|string',
            'year' => 'required|string|digits:4',
            'source_account' => 'required|string',
            'narration' => 'required|string|max:90',
        ]);

        try {
            // Prepare batch data
            $batchData = [
                'batch_no' => $validated['batch_no'],
                'batch_name' => $validated['batch_name'],
                'month' => $validated['month'],
                'year' => $validated['year'],
            ];

            // Get payment records from your database
            // This is just an example - customize based on your data structure
            $payments = $this->getPaymentsFromDatabase($validated['batch_no']);

            // Process the payment
            $result = AutoPay::processBulkPayment(
                $batchData,
                $payments,
                $validated['source_account'],
                $validated['narration']
            );

            // Handle success
            if ($result->isSuccessful()) {
                // Send notification email
                $this->sendSuccessNotification($result);

                return redirect()
                    ->back()
                    ->with('success', sprintf(
                        'Payment processed successfully. Batch: %s, Amount: ₦%s',
                        $result->getBatchName(),
                        number_format($result->getMetadata()['total_amount'], 2)
                    ));
            }

            // Handle failure
            return redirect()
                ->back()
                ->with('error', 'Payment failed: ' . $result->getError());
        } catch (AutoPayException $e) {
            // Log the error
            Log::error('AutoPay payment failed', [
                'batch_no' => $validated['batch_no'],
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'An error occurred while processing payment. Please try again.');
        }
    }

    /**
     * Get payments from database
     *
     * @param string $batchNo
     * @return array
     */
    protected function getPaymentsFromDatabase(string $batchNo): array
    {
        // Example: Fetch from your monthly_pay_register or equivalent table
        // Replace this with your actual database query

        /*
        $employees = \DB::table('monthly_pay_register')
            ->where('batch_no', $batchNo)
            ->where('locked', 0)
            ->whereNotNull('account_number')
            ->where('account_number', '<>', '')
            ->get();

        return $employees->map(function ($emp) {
            return [
                'beneficiary_id' => $emp->employee_no,
                'beneficiary_name' => $emp->full_name,
                'account_number' => $emp->account_number,
                'bank_code' => $emp->bank_code,
                'account_type' => $emp->account_type ?? 'SAVINGS',
                'amount' => $emp->monthly_pay,
            ];
        })->toArray();
        */

        // For demonstration purposes, return sample data
        return [
            [
                'beneficiary_id' => 'BEN001',
                'beneficiary_name' => 'John Doe',
                'account_number' => '0123456789',
                'bank_code' => '058',
                'account_type' => 'SAVINGS',
                'amount' => 150000.00,
            ],
            [
                'beneficiary_id' => 'BEN002',
                'beneficiary_name' => 'Jane Smith',
                'account_number' => '0987654321',
                'bank_code' => '011',
                'account_type' => 'CURRENT',
                'amount' => 200000.00,
            ],
        ];
    }

    /**
     * Send success notification email
     *
     * @param \DanOgbo\AutoPay\DTOs\PaymentResult $result
     * @return void
     */
    protected function sendSuccessNotification($result): void
    {
        $metadata = $result->getMetadata();

        // Example email sending
        /*
        \Mail::send('emails.payment-success', [
            'batch_name' => $result->getBatchName(),
            'month' => $metadata['month'],
            'year' => $metadata['year'],
            'total_amount' => $metadata['total_amount'],
            'total_employees' => $metadata['total_employees'],
        ], function ($message) use ($metadata) {
            $message->to(['finance@example.com'])
                ->subject('Payment Processed: ' . $metadata['month'] . ' ' . $metadata['year']);
        });
        */
    }
}
