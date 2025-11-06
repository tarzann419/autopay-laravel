# AutoPay Laravel Package

A Laravel package for processing bulk payments through Interswitch AutoPay XML API.

## Features

-   ✅ Simple, fluent API for bulk payment processing
-   ✅ Automatic Interswitch authentication
-   ✅ XML payload generation and parsing
-   ✅ Transaction logging to database
-   ✅ Configurable switching charges
-   ✅ Comprehensive error handling
-   ✅ PSR-4 compliant

## Requirements

-   PHP ^8.0
-   Laravel ^9.0|^10.0|^11.0
-   ext-curl
-   ext-xml

## Installation

Install the package via Composer:

```bash
composer require yourvendor/autopay-laravel
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=autopay-config
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag=autopay-migrations
php artisan migrate
```

## Configuration

Add your Interswitch credentials to your `.env` file:

```env
AUTOPAY_CLIENT_ID=your_client_id
AUTOPAY_CLIENT_SECRET=your_client_secret
AUTOPAY_TERMINAL_ID=3PSA0001
AUTOPAY_SWITCHING_CHARGE=0
```

## Usage

### Basic Example

```php
use YourVendor\AutoPay\Facades\AutoPay;

$batchData = [
    'batch_no' => 'BATCH001',
    'batch_name' => 'January 2025 Salary',
    'month' => 'January',
    'year' => '2025',
];

$payments = [
    [
        'employee_no' => 'EMP001',
        'beneficiary_name' => 'John Doe',
        'account_number' => '0123456789',
        'bank_code' => '058',
        'account_type' => 'SAVINGS',
        'amount' => 150000.00,
    ],
    [
        'employee_no' => 'EMP002',
        'beneficiary_name' => 'Jane Smith',
        'account_number' => '0987654321',
        'bank_code' => '011',
        'account_type' => 'CURRENT',
        'amount' => 200000.00,
    ],
    // ... more payments
];

$sourceAccount = '1234567890';
$narration = 'January 2025 Salary Payment';

$result = AutoPay::processBulkPayment(
    $batchData,
    $payments,
    $sourceAccount,
    $narration
);

if ($result->isSuccessful()) {
    echo "Payment processed successfully!";
    echo "Batch Name: " . $result->getBatchName();
    echo "Transaction ID: " . $result->getTransactionId();
} else {
    echo "Payment failed: " . $result->getError();
}
```

### Using in a Controller

```php
namespace App\Http\Controllers;

use YourVendor\AutoPay\Facades\AutoPay;
use YourVendor\AutoPay\Exceptions\AutoPayException;
use Illuminate\Http\Request;

class SalaryController extends Controller
{
    public function processSalary(Request $request)
    {
        $validated = $request->validate([
            'batch_no' => 'required|string',
            'batch_name' => 'required|string',
            'month' => 'required|string',
            'year' => 'required|string',
            'source_account' => 'required|string',
            'narration' => 'required|string',
        ]);

        // Get employees from your database
        $employees = $this->getEmployeesForPayment($validated['batch_no']);

        // Transform to payment format
        $payments = $employees->map(function ($employee) {
            return [
                'employee_no' => $employee->employee_no,
                'beneficiary_name' => $employee->full_name,
                'account_number' => $employee->account_number,
                'bank_code' => $employee->bank_code,
                'account_type' => $employee->account_type,
                'amount' => $employee->monthly_pay,
            ];
        })->toArray();

        try {
            $result = AutoPay::processBulkPayment(
                [
                    'batch_no' => $validated['batch_no'],
                    'batch_name' => $validated['batch_name'],
                    'month' => $validated['month'],
                    'year' => $validated['year'],
                ],
                $payments,
                $validated['source_account'],
                $validated['narration']
            );

            if ($result->isSuccessful()) {
                return redirect()->back()->with('success', 'Payment processed successfully');
            } else {
                return redirect()->back()->with('error', $result->getError());
            }

        } catch (AutoPayException $e) {
            return redirect()->back()->with('error', 'Payment failed: ' . $e->getMessage());
        }
    }
}
```

### Using the Processor Directly

```php
use YourVendor\AutoPay\Services\AutoPayXmlProcessor;

$processor = new AutoPayXmlProcessor();

$result = $processor->process(
    $batchData,
    $payments,
    $sourceAccount,
    $narration
);
```

### Query Transactions

```php
use YourVendor\AutoPay\Models\AutoPayTransaction;

// Get all transactions
$transactions = AutoPayTransaction::latest()->get();

// Get successful transactions
$successful = AutoPayTransaction::successful()->get();

// Get failed transactions
$failed = AutoPayTransaction::failed()->get();

// Get by batch number
$batch = AutoPayTransaction::byBatchNo('BATCH001')->first();

// Check status
if ($batch->isSuccessful()) {
    echo "Payment was successful";
}
```

## Payment Data Structure

Each payment in the `$payments` array should have:

| Field              | Type   | Required | Description                            |
| ------------------ | ------ | -------- | -------------------------------------- |
| `employee_no`      | string | Yes      | Employee/Beneficiary reference number  |
| `beneficiary_name` | string | Yes      | Name of the beneficiary                |
| `account_number`   | string | Yes      | Bank account number                    |
| `bank_code`        | string | Yes      | Bank CBN code (e.g., '058' for GTBank) |
| `account_type`     | string | Yes      | 'SAVINGS' or 'CURRENT'                 |
| `amount`           | float  | Yes      | Payment amount in Naira                |

## Response Object

The `PaymentResult` object provides:

```php
$result->isSuccessful()      // bool: Check if payment succeeded
$result->isFailed()          // bool: Check if payment failed
$result->getResponseCode()   // string: Interswitch response code
$result->getError()          // string: Error message if failed
$result->getBatchName()      // string: Generated batch name
$result->getTransactionId()  // string: Transaction ID
$result->getMetadata()       // array: Payment metadata
$result->toArray()           // array: Convert to array
```

## Configuration Options

The `config/autopay.php` file allows you to customize:

```php
return [
    // Interswitch credentials
    'client_id' => env('AUTOPAY_CLIENT_ID'),
    'client_secret' => env('AUTOPAY_CLIENT_SECRET'),
    'terminal_id' => env('AUTOPAY_TERMINAL_ID', '3PSA0001'),

    // API endpoints
    'auth_url' => env('AUTOPAY_AUTH_URL', 'https://saturn.interswitchng.com/v2/authenticate?wsdl'),
    'request_url' => env('AUTOPAY_REQUEST_URL', 'https://saturn.interswitchng.com/v3/autopayservice?wsdl'),

    // Processing charge per transaction (in Naira)
    'switching_charge' => env('AUTOPAY_SWITCHING_CHARGE', 0),

    // Table names
    'tables' => [
        'transactions' => 'autopay_transactions',
    ],

    // Logging
    'logging' => [
        'enabled' => env('AUTOPAY_LOGGING', true),
        'channel' => env('AUTOPAY_LOG_CHANNEL', 'stack'),
    ],
];
```

## Error Handling

The package throws `AutoPayException` for errors:

```php
use YourVendor\AutoPay\Exceptions\AutoPayException;

try {
    $result = AutoPay::processBulkPayment(...);
} catch (AutoPayException $e) {
    Log::error('AutoPay Error: ' . $e->getMessage());
    // Handle the error
}
```

## Logging

All API requests and responses are logged when logging is enabled:

```env
AUTOPAY_LOGGING=true
AUTOPAY_LOG_CHANNEL=stack
```

Check your logs at `storage/logs/laravel.log`

## Testing

```bash
composer test
```

## Security

-   Never commit your `.env` file
-   Keep your Interswitch credentials secure
-   Use environment variables for sensitive data
-   Enable logging only in development/staging

## Support

For issues or questions, please open an issue on GitHub.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

-   [Your Name](https://github.com/yourusername)
-   [All Contributors](../../contributors)

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes.
