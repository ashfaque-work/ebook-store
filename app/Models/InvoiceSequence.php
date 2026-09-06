<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gapless, per-financial-year invoice numbering.
 *
 * Indian tax rules expect a sequential series that does not skip numbers, so
 * this is a counter row taken under a row lock rather than MAX(...) + 1, which
 * races the moment two checkouts overlap.
 */
class InvoiceSequence extends Model
{
    protected $fillable = ['financial_year', 'last_number'];

    /**
     * Reserve and return the next invoice number, e.g. "INV/2026-27/000123".
     *
     * Must be called inside a transaction so the lock is held until the order
     * that uses the number is committed.
     */
    public static function next(?Carbon $on = null): string
    {
        $year = self::financialYear($on ?? now());

        $sequence = static::query()->lockForUpdate()->firstOrCreate(
            ['financial_year' => $year],
            ['last_number' => 0],
        );

        $number = $sequence->last_number + 1;
        $sequence->update(['last_number' => $number]);

        return sprintf('INV/%s/%06d', $year, $number);
    }

    /**
     * The Indian financial year runs 1 April to 31 March, so a purchase in
     * March 2027 belongs to 2026-27, not 2027-28.
     */
    public static function financialYear(Carbon $date): string
    {
        $startYear = $date->month >= 4 ? $date->year : $date->year - 1;

        return $startYear.'-'.substr((string) ($startYear + 1), -2);
    }

    /** Convenience for reporting; not used by the numbering itself. */
    public static function currentCount(): int
    {
        return (int) DB::table('invoice_sequences')
            ->where('financial_year', self::financialYear(now()))
            ->value('last_number');
    }
}
