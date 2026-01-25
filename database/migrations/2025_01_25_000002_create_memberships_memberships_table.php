<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships_memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('member_id');
            $table->string('period_type'); // calendar_year, academic_year, rolling
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status'); // pending, active, expired, cancelled
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('member_id');
            $table->index('status');
            $table->index('end_date');
            $table->index(['member_id', 'status']);

            // Foreign keys
            $table->foreign('member_id')
                ->references('id')
                ->on('memberships_members')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships_memberships');
    }
};
