<?php

declare(strict_types=1);

/**
 * Normalize page_scope for create-nsc-pages.php and Tools → NSC seeders.
 *
 * Do not use sanitize_key(): the sanitize_key filter may strip "-" on some hosts/plugins,
 * breaking slugs like "why-nsc" (empty results table, cleanup "0 pages").
 *
 * @param list<string> $knownSlugs Valid page slugs from the seeder list (e.g. array_column($pages, 'slug')).
 * @return string Resolved scope: '', 'all', 'policies', or a slug. Null = invalid non-empty input.
 */
function nsc_normalize_page_scope_string(string $input, array $knownSlugs): ?string
{
    $raw = trim($input);
    if ($raw === '') {
        return '';
    }

    $s = strtolower($raw);
    $s = (string) preg_replace('/[^a-z0-9\-]/', '', $s);
    if ($s === '') {
        return null;
    }

    if ($s === 'all') {
        return 'all';
    }

    if ($s === 'policies') {
        return 'policies';
    }

    if (in_array($s, $knownSlugs, true)) {
        return $s;
    }

    $compactToSlug = [];
    foreach ($knownSlugs as $slug) {
        if (strpos($slug, '-') === false) {
            continue;
        }
        $compact = str_replace('-', '', $slug);
        if ($compact !== '' && !isset($compactToSlug[$compact])) {
            $compactToSlug[$compact] = $slug;
        }
    }

    if (isset($compactToSlug[$s])) {
        return $compactToSlug[$s];
    }

    return null;
}
