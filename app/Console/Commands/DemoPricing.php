<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;

/**
 * Put a price on a few titles so the checkout has something to sell.
 *
 * Only while the demonstration gateway is running. Every book in the
 * catalogue is a Project Gutenberg file: their licence takes a fifth of gross
 * profits on anything carrying their trademark, which a simulated gateway
 * never generates, and which a real one would. So this refuses to run unless
 * `store.demo_payments` is on, and `--clear` is the documented way back to a
 * catalogue that is entirely free before live keys go in.
 */
class DemoPricing extends Command
{
    protected $signature = 'store:demo-pricing
        {--books=3 : How many titles to price}
        {--paise=19900 : The price to set, in paise}
        {--clear : Put every priced title back to free}';

    protected $description = 'Price a few titles for the demonstration checkout';

    public function handle(): int
    {
        if (! config('store.demo_payments')) {
            $this->error('STORE_DEMO_PAYMENTS is off.');
            $this->line('This prices public-domain books, which is only defensible while no money can change hands.');
            $this->line('Price real titles in the admin instead.');

            return self::FAILURE;
        }

        return $this->option('clear') ? $this->clear() : $this->price();
    }

    private function clear(): int
    {
        $priced = Book::query()->where('price_paise', '>', 0)->get();

        if ($priced->isEmpty()) {
            $this->info('Every title is already free.');

            return self::SUCCESS;
        }

        foreach ($priced as $book) {
            $book->forceFill(['price_paise' => 0])->save();
            $this->line("  free again: {$book->title}");
        }

        $this->info($priced->count().' title(s) back to free.');

        return self::SUCCESS;
    }

    private function price(): int
    {
        $wanted = max(1, (int) $this->option('books'));
        $paise = max(100, (int) $this->option('paise'));

        $already = Book::query()->where('price_paise', '>', 0)->count();

        if ($already >= $wanted) {
            $this->info("{$already} title(s) already priced. Nothing to do — use --clear to undo.");

            return self::SUCCESS;
        }

        // The best-known titles, because the point is a checkout somebody
        // will actually click through, and a book nobody recognises makes a
        // worse demonstration than one they do. A cover matters for the same
        // reason: the cart and the order both show one.
        $books = Book::query()
            ->where('price_paise', 0)
            ->where('is_published', true)
            ->whereNotNull('cover_image_path')
            ->whereNotNull('sample_path')
            ->orderByDesc('page_count')
            ->limit($wanted - $already)
            ->get();

        if ($books->isEmpty()) {
            $this->warn('No published book with a cover and a sample to price.');

            return self::SUCCESS;
        }

        foreach ($books as $book) {
            $book->forceFill(['price_paise' => $paise])->save();
            $this->line('  priced '.number_format($paise / 100, 2).': '.$book->title);
        }

        $this->info($books->count().' title(s) priced.');
        $this->comment('Run this with --clear before real payment keys go in.');

        return self::SUCCESS;
    }
}
