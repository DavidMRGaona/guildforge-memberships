<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships_fees', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('membership_id');
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('EUR');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable(); // cash, bank_transfer, card, other
            $table->string('transaction_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('membership_id');
            $table->index('due_date');
            $table->index('paid_at');

            // Foreign keys
            $table->foreign('membership_id')
                ->references('id')
                ->on('memberships_memberships')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships_fees');
    }
};
