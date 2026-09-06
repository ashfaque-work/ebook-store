<?php

use App\Models\Book;
use App\Models\InvoiceSequence;
use App\Models\Order;
use App\Models\User;
use App\Services\Tax\TaxCalculator;
use Illuminate\Support\Carbon;

test('no tax is charged while GST is disabled', function () {
    config()->set('store.gst_enabled', false);

    $user = User::factory()->create();
    $book = Book::factory()->create(['price_paise' => 29900]);

    $this->actingAs($user)->withSession(['cart' => [$book->id]])->post('/checkout');

    $order = Order::sole();

    // An unregistered seller must not charge tax or break out a tax line.
    expect($order->tax_paise)->toBe(0)
        ->and($order->subtotal_paise)->toBe(29900)
        ->and($order->total_paise)->toBe(29900)
        ->and($order->tax_type)->toBeNull();
});

test('enabling GST does not change what the customer pays', function () {
    config()->set('store.gst_enabled', true);

    $user = User::factory()->create();
    $book = Book::factory()->create(['price_paise' => 29900, 'tax_rate' => 18.0]);

    $this->actingAs($user)->withSession(['cart' => [$book->id]])->post('/checkout');

    $order = Order::sole();

    // Prices are tax-inclusive, so the tax comes out of the price rather than
    // being added to it.
    expect($order->total_paise)->toBe(29900)
        ->and($order->subtotal_paise)->toBe(25339)
        ->and($order->tax_paise)->toBe(4561);
});

test('the split always reconciles exactly', function (int $gross, float $rate) {
    config()->set('store.gst_enabled', true);

    $split = (new TaxCalculator)->split($gross, $rate);

    // Deriving tax by subtraction is what guarantees this; rounding both parts
    // independently would drift by a paisa on many amounts.
    expect($split['subtotal_paise'] + $split['tax_paise'])->toBe($gross);
})->with([
    [29900, 18.0], [9900, 5.0], [1, 18.0], [33333, 18.0], [49999, 5.0], [100, 12.0],
]);

test('a zero rate leaves the amount untouched', function () {
    config()->set('store.gst_enabled', true);

    $split = (new TaxCalculator)->split(9900, 0.0);

    expect($split['tax_paise'])->toBe(0)
        ->and($split['subtotal_paise'])->toBe(9900);
});

test('place of supply decides which tax applies', function () {
    config()->set('store.gst_enabled', true);
    config()->set('store.state_code', '27');   // Maharashtra

    $tax = new TaxCalculator;

    expect($tax->typeFor('27'))->toBe(Order::TAX_CGST_SGST)   // same state
        ->and($tax->typeFor('29'))->toBe(Order::TAX_IGST)     // another state
        ->and($tax->typeFor(null))->toBe(Order::TAX_CGST_SGST); // unknown: conservative
});

test('a paid order is given an invoice number', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create();

    $this->actingAs($user)->withSession(['cart' => [$book->id]])->post('/checkout');

    expect(Order::sole()->invoice_number)->toMatch('#^INV/\d{4}-\d{2}/\d{6}$#');
});

test('invoice numbers run in sequence without gaps', function () {
    $numbers = collect(range(1, 3))->map(function () {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)->withSession(['cart' => [$book->id]])->post('/checkout');

        return Order::where('user_id', $user->id)->sole()->invoice_number;
    });

    expect($numbers->all())->toBe([
        'INV/'.InvoiceSequence::financialYear(now()).'/000001',
        'INV/'.InvoiceSequence::financialYear(now()).'/000002',
        'INV/'.InvoiceSequence::financialYear(now()).'/000003',
    ]);
});

test('the financial year runs April to March', function () {
    // A March purchase belongs to the year that began the previous April.
    expect(InvoiceSequence::financialYear(Carbon::parse('2027-03-31')))->toBe('2026-27')
        ->and(InvoiceSequence::financialYear(Carbon::parse('2027-04-01')))->toBe('2027-28')
        ->and(InvoiceSequence::financialYear(Carbon::parse('2026-12-25')))->toBe('2026-27');
});

test('the sequence restarts each financial year', function () {
    Carbon::setTestNow('2027-03-30');
    expect(InvoiceSequence::next())->toBe('INV/2026-27/000001');

    Carbon::setTestNow('2027-04-02');
    expect(InvoiceSequence::next())->toBe('INV/2027-28/000001');

    Carbon::setTestNow();
});

test('fulfilling an order twice does not burn a second invoice number', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create();

    $this->actingAs($user)->withSession(['cart' => [$book->id]])->post('/checkout');

    $order = Order::sole();
    $first = $order->invoice_number;

    // The webhook and the browser callback both call FulfilOrder; the second
    // one must change nothing at all.
    expect(app(App\Services\Checkout\FulfilOrder::class)($order))->toBeFalse()
        ->and($order->fresh()->invoice_number)->toBe($first)
        ->and(InvoiceSequence::currentCount())->toBe(1);
});
