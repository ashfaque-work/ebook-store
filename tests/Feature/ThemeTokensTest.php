<?php

/**
 * The store has two themes, and a hardcoded light-mode colour only misbehaves
 * in one of them. Nothing errors: a `bg-white` card in dark mode simply keeps
 * its white background while the text on it goes light, and the result is
 * invisible text that no test and no local click-through in the default theme
 * will ever catch.
 *
 * The design system's tokens — surface, raised, line, content, muted — follow
 * the theme, so using them is the fix and this is the guard.
 */
$allowed = [
    // The fallback cover is deliberately dark in both themes: it stands in for
    // artwork, and a book spine does not turn white because the page did.
    'resources/js/Components/BookCover.vue',
];

test('no component hardcodes a colour that only works in one theme', function () use ($allowed) {
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('js'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'vue') {
            continue;
        }

        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));

        if (in_array($relative, $allowed, true)) {
            continue;
        }

        foreach (file($file->getPathname()) as $number => $line) {
            // A `dark:` counterpart on the same line means the pair was
            // considered, which is the other legitimate way to do this.
            if (str_contains($line, 'dark:')) {
                continue;
            }

            if (preg_match('/(?<![\w:-])(bg-white|bg-gray-\d+|text-gray-\d+|border-gray-\d+|divide-gray-\d+)/', $line, $m)) {
                $offenders[] = $relative.':'.($number + 1).'  '.$m[1];
            }
        }
    }

    expect($offenders)->toBe([], "Use theme tokens (bg-raised, border-line, text-content, text-muted) instead:\n".implode("\n", $offenders));
});
