<?php

namespace DanOgbo\AutoPay\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \DanOgbo\AutoPay\DTOs\PaymentResult processBulkPayment(array $batchData, array $payments, string $sourceAccount, string $narration)
 * @method static \DanOgbo\AutoPay\Services\AutoPayXmlProcessor processor()
 *
 * @see \DanOgbo\AutoPay\AutoPayManager
 */
class AutoPay extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'autopay';
    }
}
