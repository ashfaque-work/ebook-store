<?php

namespace App\Services\Tax;

use App\Models\Order;

/**
 * GST on a digital sale.
 *
 * Prices are stored and shown **inclusive** of tax, which is what Indian
 * consumer pricing expects: the number on the book page is the number the
 * customer pays. Tax is therefore extracted from the price rather than added
 * to it, and enabling GST never changes anyone's total.
 *
 * This is not tax advice. Confirm rates, thresholds and place-of-supply rules
 * with a CA before you switch `store.gst_enabled` on.
 */
class TaxCalculator
{
    public function enabled(): bool
    {
        return (bool) config('store.gst_enabled');
    }

    /**
     * Split a tax-inclusive amount into net and tax.
     *
     * @return array{subtotal_paise: int, tax_paise: int, tax_rate: float}
     */
    public function split(int $grossPaise, ?float $rate = null): array
    {
        if (! $this->enabled()) {
            return ['subtotal_paise' => $grossPaise, 'tax_paise' => 0, 'tax_rate' => 0.0];
        }

        $rate ??= (float) config('store.default_tax_rate');

        if ($rate <= 0) {
            return ['subtotal_paise' => $grossPaise, 'tax_paise' => 0, 'tax_rate' => 0.0];
        }

        // net = gross / (1 + rate/100), rounded to the paisa. Deriving tax by
        // subtraction guarantees net + tax == gross exactly, which rounding the
        // two independently does not.
        $subtotal = (int) round($grossPaise / (1 + $rate / 100));

        return [
            'subtotal_paise' => $subtotal,
            'tax_paise' => $grossPaise - $subtotal,
            'tax_rate' => $rate,
        ];
    }

    /**
     * Which tax applies, given where the buyer is.
     *
     * Same state as us: CGST + SGST, split evenly. Different state: IGST at the
     * full rate. Unknown: treat as intra-state, the conservative default.
     */
    public function typeFor(?string $buyerStateCode): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $sellerState = (string) config('store.state_code');

        if ($buyerStateCode === null || $sellerState === '' || $buyerStateCode === $sellerState) {
            return Order::TAX_CGST_SGST;
        }

        return Order::TAX_IGST;
    }

    /**
     * Roll item-level splits up into the order totals.
     *
     * @param  array<int, array{subtotal_paise: int, tax_paise: int}>  $lines
     * @return array{subtotal_paise: int, tax_paise: int, total_paise: int}
     */
    public function totals(array $lines): array
    {
        $subtotal = array_sum(array_column($lines, 'subtotal_paise'));
        $tax = array_sum(array_column($lines, 'tax_paise'));

        return [
            'subtotal_paise' => $subtotal,
            'tax_paise' => $tax,
            'total_paise' => $subtotal + $tax,
        ];
    }
}
