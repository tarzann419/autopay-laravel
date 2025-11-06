<?php

namespace YourVendor\AutoPay\DTOs;

class PaymentResult
{
    public function __construct(
        public bool $success,
        public string $responseCode,
        public string $message,
        public string $batchName,
        public string $transactionId,
        public array $metadata = []
    ) {}

    /**
     * Check if payment was successful
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * Check if payment failed
     */
    public function isFailed(): bool
    {
        return !$this->success;
    }

    /**
     * Get error message
     */
    public function getError(): string
    {
        return $this->message;
    }

    /**
     * Get response code
     */
    public function getResponseCode(): string
    {
        return $this->responseCode;
    }

    /**
     * Get batch name
     */
    public function getBatchName(): string
    {
        return $this->batchName;
    }

    /**
     * Get transaction ID
     */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * Get metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'response_code' => $this->responseCode,
            'message' => $this->message,
            'batch_name' => $this->batchName,
            'transaction_id' => $this->transactionId,
            'metadata' => $this->metadata,
        ];
    }
}
