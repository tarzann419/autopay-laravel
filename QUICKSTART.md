# AutoPay Laravel - Quick Start Guide

This guide will help you get started with the AutoPay Laravel package in just a few minutes.

## Step 1: Install the Package

```bash
composer require tarzann419/autopay-laravel
```

## Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=autopay-config
php artisan vendor:publish --tag=autopay-migrations
php artisan migrate
```

## Step 3: Configure Your .env

Add your Interswitch credentials:

```env
AUTOPAY_CLIENT_ID=your_client_id_here
AUTOPAY_CLIENT_SECRET=your_client_secret_here
AUTOPAY_TERMINAL_ID=XXXXXXXX
AUTOPAY_SWITCHING_CHARGE=0
```

## Step 4: Use in Your Controller

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DanOgbo\AutoPay\Facades\AutoPay;
use DanOgbo\AutoPay\Exceptions\AutoPayException;

class PaymentController extends Controller
{
    public function process(Request $request)
    {
        // Your batch information
        $batchData = [
            'batch_no' => 'BATCH001',
            'batch_name' => 'January Salary',
            'month' => 'January',
            'year' => '2025',
        ];

        // Your payments (from database or wherever)
        $payments = [
            [
                'beneficiary_id' => 'BEN001',
                'beneficiary_name' => 'John Doe',
                'account_number' => '0123456789',
                'bank_code' => '058', // GTBank
                'account_type' => 'SAVINGS',
                'amount' => 150000.00,
            ],
            // ... more payments
        ];

        $sourceAccount = '1234567890'; // Your source account
        $narration = 'January 2025 Salary';

        try {
            $result = AutoPay::processBulkPayment(
                $batchData,
                $payments,
                $sourceAccount,
                $narration
            );

            if ($result->isSuccessful()) {
                return back()->with('success', 'Payment processed!');
            } else {
                return back()->with('error', $result->getError());
            }
        } catch (AutoPayException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
```

## That's It!

You're now ready to process bulk payments through Interswitch AutoPay.

## Common Bank Codes

| Bank          | Code |
| ------------- | ---- |
| Access Bank   | 044  |
| Citibank      | 023  |
| Ecobank       | 050  |
| FCMB          | 214  |
| Fidelity Bank | 070  |
| First Bank    | 011  |
| GTBank        | 058  |
| Heritage Bank | 030  |
| Keystone Bank | 082  |
| Polaris Bank  | 076  |
| Stanbic IBTC  | 221  |
| Sterling Bank | 232  |
| UBA           | 033  |
| Union Bank    | 032  |
| Unity Bank    | 215  |
| Wema Bank     | 035  |
| Zenith Bank   | 057  |

## Next Steps

-   Read the [full documentation](README.md)
-   Check the [example controller](src/Examples/PaymentExampleController.php)
-   Review error handling strategies
-   Set up email notifications

## Need Help?

-   Check the logs: `storage/logs/laravel.log`
-   Review the configuration: `config/autopay.php`
-   Open an issue on GitHub
