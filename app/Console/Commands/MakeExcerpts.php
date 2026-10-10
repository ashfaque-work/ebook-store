<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Support\Epub\TextExtractor;
use App\Support\OpeningLines;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Give every book its opening lines.
 *
 * The home page is built to lead with prose rather than a strapline, and the
 * book page shows the writing under the blurb. Both fall back to the
 * description when `excerpt` is empty — and it was empty on all fifty-four
 * books, so a feature designed to show the writing had been showing a
 * machine-written summary since the day it shipped. Nothing ever wrote to the
 * column.
 *
 * The source is the book's own sample, which `store:make-samples` has already
 * anchored to the first chapter heading. So the first words of the sample are
 * the first words of the book, past the licence, the preface and the list of
 * illustrations.
 */
class MakeExcerpts extends Command
{
    protected $signature = 'store:make-excerpts
        {--force : Replace excerpts that already exist}
        {--words=58 : Roughly how many words to keep}';

    protected $description = 'Pull each book\'s opening lines out of its sample';

    public function handle(): int
    {
        $words = max(20, (int) $this->option('words'));

        $books = Book::query()
            ->whereNotNull('sample_path')
            ->when(! $this->option('force'), fn ($q) => $q->where(fn ($w) => $w->whereNull('excerpt')->orWhere('excerpt', '')))
            ->orderBy('id')
            ->get();

        if ($books->isEmpty()) {
            $this->info('Every book with a sample already has an excerpt.');

            return self::SUCCESS;
        }

        $this->info("Reading {$books->count()} sample(s).");

        $done = 0;
        $skipped = 0;

        foreach ($books as $book) {
            $opening = $this->openingOf($book, $words);

            if ($opening === null) {
                $skipped++;
                $this->warn("  no usable opening: {$book->title}");

                continue;
            }

            $book->forceFill(['excerpt' => $opening])->save();
            $done++;
            $this->line('  '.Str::limit($opening, 72));
        }

        $this->newLine();
        $this->info("{$done} excerpt(s) written.");

        if ($skipped > 0) {
            $this->warn("  {$skipped} skipped.");
        }

        return self::SUCCESS;
    }

    private function openingOf(Book $book, int $words): ?string
    {
        try {
            $bytes = Storage::disk(Book::fileDisk())->get($book->getRawOriginal('sample_path'));
        } catch (Throwable) {
            return null;
        }

        if (! $bytes) {
            return null;
        }

        $passages = TextExtractor::passages($bytes, $words * 2);

        // Two passes. A preface and a translator's note are real writing and
        // pass every prose test, so they win on a first-match-wins scan —
        // and a window onto "a few words about Dostoevsky himself may help
        // the English reader" sells nobody a novel. So the book proper is
        // asked first, and the front matter only answers if nothing else
        // will.
        foreach ([true, false] as $skipFrontMatter) {
            foreach ($passages as $passage) {
                if ($skipFrontMatter && self::isFrontMatter($passage['heading'] ?? null)) {
                    continue;
                }

                $opening = OpeningLines::from($passage['content'], $words);

                if ($opening !== null) {
                    return $opening;
                }
            }
        }

        return null;
    }

    private static function isFrontMatter(?string $heading): bool
    {
        return $heading !== null
            && preg_match('/\b(preface|introduction|foreword|translator|contents|note|dedication|acknowledg)/iu', $heading) === 1;
    }
}
