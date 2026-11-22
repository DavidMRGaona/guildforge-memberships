<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The instant a membership became expired, by hand or by the daily command (like cancelled_at for a
 * cancellation): years as a member (activity contract) stop at that day or at the end of the
 * period, whichever is first. Null, as in every row that exists, means it expired at its end.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships_memberships', function (Blueprint $table): void {
            $table->timestamp('expired_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('memberships_memberships', function (Blueprint $table): void {
            $table->dropColumn('expired_at');
        });
    }
};
