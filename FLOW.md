# AutoPay Package - User Flow

## How It Works for End Users

```
┌─────────────────────────────────────────────────────────────┐
│                    YOUR LARAVEL APP                         │
└─────────────────────────────────────────────────────────────┘
                            │
                            │ 1. Call AutoPay::processBulkPayment()
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                   AutoPay Package                           │
│  ┌───────────────────────────────────────────────────────┐  │
│  │  Step 1: Authenticate with Interswitch               │  │
│  │  - Send credentials                                   │  │
│  │  - Receive security token                            │  │
│  └───────────────────────────────────────────────────────┘  │
│                            │                                 │
│                            ▼                                 │
│  ┌───────────────────────────────────────────────────────┐  │
│  │  Step 2: Build XML Payload                           │  │
│  │  - Process each payment                              │  │
│  │  - Deduct switching charges                          │  │
│  │  - Generate MAC hash                                 │  │
│  │  - Create SOAP envelope                              │  │
│  └───────────────────────────────────────────────────────┘  │
│                            │                                 │
│                            ▼                                 │
│  ┌───────────────────────────────────────────────────────┐  │
│  │  Step 3: Send to Interswitch                         │  │
│  │  - Make SOAP request                                 │  │
│  │  - Receive response                                  │  │
│  └───────────────────────────────────────────────────────┘  │
│                            │                                 │
│                            ▼                                 │
│  ┌───────────────────────────────────────────────────────┐  │
│  │  Step 4: Parse Response                              │  │
│  │  - Extract response code                             │  │
│  │  - Check success (90000)                             │  │
│  └───────────────────────────────────────────────────────┘  │
│                            │                                 │
│                            ▼                                 │
│  ┌───────────────────────────────────────────────────────┐  │
│  │  Step 5: Save to Database                            │  │
│  │  - Store transaction record                          │  │
│  │  - Log metadata                                      │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            │
                            │ 2. Return PaymentResult object
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                    YOUR LARAVEL APP                         │
│  ┌───────────────────────────────────────────────────────┐  │
│  │  Handle Result                                        │  │
│  │  - Check if successful                                │  │
│  │  - Display message to user                            │  │
│  │  - Send email notifications                           │  │
│  │  - Update your database                               │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

## Simple Code Flow

### 1. User Makes Request

```php
// Your controller receives payment request
public function processPayment(Request $request) { ... }
```

### 2. Prepare Data

```php
$batchData = ['batch_no' => '...', 'batch_name' => '...', ...];
$payments = [['beneficiary_id' => '...', 'amount' => 150000, ...]];
```

### 3. Call Package

```php
$result = AutoPay::processBulkPayment(
    $batchData,
    $payments,
    $sourceAccount,
    $narration
);
```

### 4. Handle Response

```php
if ($result->isSuccessful()) {
    // Payment succeeded
    return back()->with('success', 'Payment processed!');
} else {
    // Payment failed
    return back()->with('error', $result->getError());
}
```

## What Happens Behind the Scenes

1. **Authentication**: Package authenticates with Interswitch using your credentials
2. **XML Building**: Converts your payment array into Interswitch XML format
3. **Charge Calculation**: Automatically deducts switching charges from each payment
4. **MAC Generation**: Creates security hash required by Interswitch
5. **SOAP Request**: Sends XML payload to Interswitch
6. **Response Parsing**: Extracts result from XML response
7. **Database Logging**: Saves transaction to `autopay_transactions` table
8. **Result Object**: Returns user-friendly result object

## Payment Flow Example

```
Input Payment:
├─ Beneficiary: John Doe
├─ Amount: ₦150,000
├─ Account: 0123456789
└─ Bank: GTBank (058)

↓ Package Processing ↓

1. Deduct charge: ₦150,000 - ₦0 = ₦150,000
2. Convert to kobo: ₦150,000 × 100 = 15,000,000 kobo
3. Build XML for this payment
4. Add to batch

↓ Send to Interswitch ↓

Response: Code 90000 (Success)

↓ Save Transaction ↓

Database Record:
├─ Batch: JAN2025_SALARY_abc123
├─ Status: SUCCESS
├─ Amount: ₦150,000
└─ Response Code: 90000
```

## Error Handling Flow

```
Try:
    └─ Call AutoPay::processBulkPayment()
        ├─ Success? Return PaymentResult (success=true)
        └─ Failed? Return PaymentResult (success=false)
Catch AutoPayException:
    └─ Handle exception (log, notify, etc.)
```

## Database Schema

After installation, you'll have this table:

```
autopay_transactions
├─ id (primary key)
├─ batch_no (indexed)
├─ batch_name
├─ month
├─ year
├─ source_account
├─ transaction_session_id
├─ response_code
├─ status (indexed: SUCCESS/FAILED/PENDING)
├─ total_amount
├─ total_count
├─ narration
├─ created_at
└─ updated_at
```

Query examples:

```php
// Get all successful payments
AutoPayTransaction::successful()->get();

// Find by batch number
AutoPayTransaction::byBatchNo('BATCH001')->first();

// Check if payment succeeded
$transaction->isSuccessful(); // true/false
```
