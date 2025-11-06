<?php

namespace DanOgbo\AutoPay;

use DanOgbo\AutoPay\Services\AutoPayXmlProcessor;

class AutoPayManager
{
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Process bulk payment via AutoPay XML
     *
     * @param array $batchData
     * @param array $payments
     * @param string $sourceAccount
     * @param string $narration
     * @return \DanOgbo\AutoPay\DTOs\PaymentResult
     */
    public function processBulkPayment(
        array $batchData,
        array $payments,
        string $sourceAccount,
        string $narration
    ) {
        $processor = new AutoPayXmlProcessor();

        return $processor->process($batchData, $payments, $sourceAccount, $narration);
    }

    /**
     * Get a payment processor instance
     *
     * @return \DanOgbo\AutoPay\Services\AutoPayXmlProcessor
     */
    public function processor()
    {
        return new AutoPayXmlProcessor();
    }
}
