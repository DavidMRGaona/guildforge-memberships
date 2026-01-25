<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships_fee_structures', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('member_type'); // regular, student, senior, honorary, founder
            $table->string('period_type'); // calendar_year, academic_year, rolling
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('EUR');
            $table->json('proration_rules')->nullable();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            // Indexes
            $table->index(['member_type', 'period_type']);
            $table->index('valid_from');
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships_fee_structures');
    }
};
