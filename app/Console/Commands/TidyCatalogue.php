<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Models\Book;
use App\Support\CatalogueText;
use Illuminate\Console\Command;

/**
 * Make imported metadata read like a shop rather than a card catalogue.
 *
 * The importer takes what Project Gutenberg has, which is a library record:
 * accurate, and written for a librarian. This fixes the parts that are
 * unambiguous — see App\Support\CatalogueText — and reports the ones that
 * need somebody to decide, rather than guessing at a book's real title.
 */
class TidyCatalogue extends Command
{
    protected $signature = 'store:tidy-catalogue
        {--dry-run : Show what would change and write nothing}';

    protected $description = 'Clean up titles, author names and blurbs imported from a library catalogue';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $changes = [...$this->bookChanges(), ...$this->authorChanges()];

        if ($changes === []) {
            $this->info('Nothing to tidy.');
        } else {
            $this->table(['What', 'Before', 'After'], array_map(
                fn ($change) => [$change['what'], self::shorten($change['before']), self::shorten($change['after'])],
                $changes,
            ));

            if ($dryRun) {
                $this->comment(count($changes).' change(s) — nothing written. Drop --dry-run to apply.');
            } else {
                foreach ($changes as $change) {
                    $change['model']->forceFill([$change['field'] => $change['after']])->save();
                }

                $this->info(count($changes).' change(s) applied.');
            }
        }

        $this->reportJudgementCalls();

        return self::SUCCESS;
    }

    /**
     * @return list<array{model: Book, field: string, what: string, before: string, after: string}>
     */
    private function bookChanges(): array
    {
        $changes = [];

        foreach (Book::query()->orderBy('title')->get() as $book) {
            $title = CatalogueText::title($book->title);

            if ($title !== $book->title) {
                $changes[] = [
                    'model' => $book, 'field' => 'title', 'what' => 'Title',
                    'before' => $book->title, 'after' => $title,
                ];
            }

            $description = CatalogueText::description($book->description);

            if ($description !== $book->description) {
                $changes[] = [
                    'model' => $book, 'field' => 'description', 'what' => 'Blurb',
                    'before' => $book->description ?? '', 'after' => $description ?? '',
                ];
            }
        }

        return $changes;
    }

    /**
     * @return list<array{model: Author, field: string, what: string, before: string, after: string}>
     */
    private function authorChanges(): array
    {
        $changes = [];

        foreach (Author::query()->orderBy('name')->get() as $author) {
            $name = CatalogueText::authorName($author->name);

            if ($name !== $author->name) {
                $changes[] = [
                    'model' => $author, 'field' => 'name', 'what' => 'Author',
                    'before' => $author->name, 'after' => $name,
                ];
            }
        }

        return $changes;
    }

    /**
     * Name what is wrong but not safely fixable by a rule.
     *
     * An author recorded as "Thomas, Sir Malory" is Sir Thomas Malory, and no
     * amount of comma-swapping gets there reliably — the same rule applied to
     * "Alex. McVeigh, Mrs. Miller" produces nonsense. Better to say so and
     * leave the admin to it than to quietly rename somebody wrongly.
     */
    private function reportJudgementCalls(): void
    {
        $authors = Author::query()
            ->orderBy('name')
            ->get()
            ->filter(fn (Author $a) => str_contains($a->name, ',') || preg_match('/^\p{Ll}/u', $a->name))
            ->pluck('name');

        $books = Book::query()->orderBy('title')->get();

        $volumes = $books
            ->filter(fn (Book $b) => preg_match('/\b(vol\.?|volume)\b/iu', $b->title))
            ->pluck('title');

        // Titles the restyling declined: a couple of lowercase words among
        // several proper nouns, which is where a rule stops being safe.
        $halfStyled = $books
            ->filter(fn (Book $b) => CatalogueText::titleNeedsAttention(CatalogueText::title($b->title)))
            ->pluck('title');

        if ($authors->isEmpty() && $volumes->isEmpty() && $halfStyled->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->comment('Left alone — these need a person:');

        foreach ($authors as $name) {
            $this->line("  author, recorded surname-first or lowercase:  {$name}");
        }

        foreach ($halfStyled as $title) {
            $this->line('  title, part sentence case, part proper nouns: '.self::shorten($title));
        }

        foreach ($volumes as $title) {
            $this->line('  part of a set, sold on its own:               '.self::shorten($title));
        }
    }

    private static function shorten(string $value): string
    {
        return mb_strlen($value) > 58 ? mb_substr($value, 0, 57).'…' : $value;
    }
}
