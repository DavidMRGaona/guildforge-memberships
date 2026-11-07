<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One default fee structure per member type and period type. Where several are marked, the one
 * that starts last keeps the mark (the current price, when old ones were closed as the guide
 * says), and the others lose it before the index goes in.
 */
return new class extends Migration
{
    private const string TABLE = 'memberships_fee_structures';

    private const string INDEX = 'memberships_fee_structures_one_default';

    public function up(): void
    {
        $kept = [];
        $defaults = DB::table(self::TABLE)
            ->where('is_default', true)
            ->orderByDesc('valid_from')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'member_type', 'period_type']);

        foreach ($defaults as $default) {
            $key = $default->member_type.'|'.$default->period_type;

            if (isset($kept[$key])) {
                DB::table(self::TABLE)->where('id', $default->id)->update(['is_default' => false]);

                continue;
            }

            $kept[$key] = true;
        }

        // Partial indexes are not in the schema builder; both drivers take this SQL
        DB::statement(
            'CREATE UNIQUE INDEX '.self::INDEX.' '
            .'ON '.self::TABLE.' (member_type, period_type) WHERE is_default = true'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::INDEX);
    }
};
