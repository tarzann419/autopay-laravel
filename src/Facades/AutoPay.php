<?php

namespace YourVendor\AutoPay\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \YourVendor\AutoPay\DTOs\PaymentResult processBulkPayment(array $batchData, array $payments, string $sourceAccount, string $narration)
 * @method static \YourVendor\AutoPay\Services\AutoPayXmlProcessor processor()
 *
 * @see \YourVendor\AutoPay\AutoPayManager
 */
class AutoPay extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'autopay';
    }
}
