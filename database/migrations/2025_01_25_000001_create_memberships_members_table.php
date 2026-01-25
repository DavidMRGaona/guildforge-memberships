<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('member_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->date('birth_date')->nullable();
            $table->text('address')->nullable();
            $table->string('member_type'); // regular, student, senior, honorary, founder
            $table->string('status'); // active, inactive, suspended, expelled
            $table->uuid('user_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamp('joined_at');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('member_type');

            // Foreign keys
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships_members');
    }
};
