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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique();
            $table->foreignId('order_id')->constrained('orders')->onDelete('restrict');
            $table->enum('payment_method', ['cash', 'card', 'qris']);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2);
            $table->decimal('change_amount', 15, 2);
            $table->enum('status', ['success', 'failed', 'refunded'])->default('success');
            $table->foreignId('processed_by')->constrained('users');
            $table->timestamps();

            $table->index('order_id', 'idx_transactions_order');
            $table->index('created_at', 'idx_transactions_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
