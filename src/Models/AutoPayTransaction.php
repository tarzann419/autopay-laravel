<?php

namespace YourVendor\AutoPay\Models;

use Illuminate\Database\Eloquent\Model;

class AutoPayTransaction extends Model
{
    protected $fillable = [
        'batch_no',
        'batch_name',
        'month',
        'year',
        'source_account',
        'transaction_session_id',
        'response_code',
        'status',
        'total_amount',
        'total_count',
        'narration',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'total_count' => 'integer',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('autopay.tables.transactions', 'autopay_transactions'));
    }

    /**
     * Scope to filter by batch number
     */
    public function scopeByBatchNo($query, string $batchNo)
    {
        return $query->where('batch_no', $batchNo);
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter successful transactions
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'SUCCESS');
    }

    /**
     * Scope to filter failed transactions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'FAILED');
    }

    /**
     * Check if transaction was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'SUCCESS';
    }

    /**
     * Check if transaction failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'FAILED';
    }
}
