<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = config('autopay.tables.transactions', 'autopay_transactions');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('batch_no')->index();
            $table->string('batch_name');
            $table->string('month');
            $table->string('year');
            $table->string('source_account');
            $table->string('transaction_session_id')->nullable();
            $table->string('response_code')->nullable();
            $table->string('status')->default('PENDING')->index();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->integer('total_count')->default(0);
            $table->text('narration')->nullable();
            $table->timestamps();

            $table->index(['batch_no', 'month', 'year']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('autopay.tables.transactions', 'autopay_transactions');
        Schema::dropIfExists($tableName);
    }
};
