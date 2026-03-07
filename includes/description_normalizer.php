<?php

require_once __DIR__ . '/normalize.php';

function description_is_url_only(string $text): bool
{
    return (bool) preg_match('~^https?://\S+$~i', trim($text));
}

function description_extract_urls(string $text): array
{
    if (!preg_match_all('~https?://[^\s<>"\')]+~i', $text, $matches)) {
        return [];
    }
    return array_values(array_unique(array_map(static fn($v) => trim((string) $v), $matches[0])));
}

function description_strip_terminal_punctuation(string $text): string
{
    return rtrim($text, " \t\n\r\0\x0B.;");
}

function description_set_aside_sentence(?string $phrase): ?string
{
    $phrase = normalize_whitespace((string) $phrase);
    if ($phrase === '') {
        return null;
    }

    $phrase = preg_replace('/\s+set[- ]aside$/i', '', $phrase) ?? $phrase;
    $phraseLower = strtolower(trim($phrase));

    $known = [
        'small business' => 'This opportunity is restricted to small businesses.',
        'total small business' => 'This opportunity is restricted to small businesses.',
        'service-disabled veteran-owned small business' => 'This opportunity is restricted to service-disabled veteran-owned small businesses.',
        'sdvosb' => 'This opportunity is restricted to service-disabled veteran-owned small businesses.',
        'women-owned small business' => 'This opportunity is restricted to women-owned small businesses.',
        'wosb' => 'This opportunity is restricted to women-owned small businesses.',
        '8(a)' => 'This opportunity is restricted to eligible 8(a) small businesses.',
    ];

    if (isset($known[$phraseLower])) {
        return $known[$phraseLower];
    }

    return 'This opportunity is a ' . $phrase . ' set-aside.';
}

function description_is_placeholder_text(string $text): bool
{
    $text = strtolower(trim($text));
    if ($text === '') {
        return true;
    }

    if ($text === 'array' || str_starts_with($text, 'array(') || str_starts_with($text, 'array (')) {
        return true;
    }

    static $junk = [
        'n/a',
        'na',
        'null',
        'none',
        'not listed',
        'not available',
        'not provided',
        'unknown',
        '-',
        '--',
        '{}',
        '[]',
    ];
    return in_array($text, $junk, true);
}

function description_is_displayable_text(?string $text): bool
{
    $text = trim((string) $text);
    if ($text === '' || description_is_placeholder_text($text)) {
        return false;
    }
    if (description_is_url_only($text)) {
        return false;
    }
    return true;
}

function description_build_summary_plain(?string $text): ?string
{
    $text = clean_nullable($text);
    if ($text === null) {
        return null;
    }

    $sentences = preg_split('/(?<=[.!?])\s+/', $text) ?: [];
    $first = trim((string) ($sentences[0] ?? $text));
    if ($first === '') {
        $first = $text;
    }

    if (mb_strlen($first) > 220) {
        $first = rtrim(mb_substr($first, 0, 217)) . '...';
    }
    return $first;
}

function description_clean_generic_text(string $text): array
{
    $meta = [
        'url_stripped' => 0,
        'urls_detected' => 0,
    ];

    $urls = description_extract_urls($text);
    if ($urls) {
        $meta['urls_detected'] = count($urls);
        $meta['url_stripped'] = 1;
        $text = preg_replace('~https?://[^\s<>"\')]+~i', '', $text) ?? $text;
    }

    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\b(?:naics|psc|solicitation(?: number)?|notice id|set-aside)\s*:\s*[A-Za-z0-9\-\(\), ]{2,80}(?=[.;]|\s{2,}|$)/i', '', $text) ?? $text;
    $text = preg_replace('/\b(?:far|dfars)\s*\d{1,3}(?:\.\d{1,4})?(?:-\d+)?\b[^.;]{0,140}(?=[.;]|$)/i', '', $text) ?? $text;
    $text = preg_replace('/\b(?:clause|provision)\s+\d{2,3}\.\d{1,4}(?:-\d+)?\b[^.;]{0,140}(?=[.;]|$)/i', '', $text) ?? $text;
    $text = preg_replace('/(?:\|\s*){2,}|(?:-\s*){3,}|(?:_\s*){3,}/', ' ', $text) ?? $text;
    $text = preg_replace('/\s*[;|]\s*/', '. ', $text) ?? $text;
    $text = preg_replace('/\b(?:please\s+see|refer\s+to)\s+(?:the\s+)?(?:attached|full)\s+(?:notice|solicitation)\b[^.;]{0,120}(?=[.;]|$)/i', '', $text) ?? $text;
    $text = preg_replace('/\b(?:this\s+is\s+not\s+a\s+solicitation|for\s+informational\s+purposes\s+only)\b[^.;]{0,120}(?=[.;]|$)/i', '', $text) ?? $text;
    $text = preg_replace('/\s+,/', ',', $text) ?? $text;
    $text = preg_replace('/\.\s*\./', '.', $text) ?? $text;
    $text = normalize_whitespace($text);
    $text = description_strip_terminal_punctuation($text);

    if ($text !== '') {
        $text .= '.';
    }

    return [$text, $meta];
}

function description_clean_sam_opportunity_text(string $text, array $context = []): string
{
    $working = $text;
    $setAsideSentence = null;
    $setAsideFromField = clean_nullable($context['set_aside_label'] ?? null);
    if ($setAsideFromField !== null) {
        $setAsideSentence = description_set_aside_sentence($setAsideFromField);
    }

    if (preg_match('/^\s*((?:total|partial)\s+)?([A-Za-z0-9\(\)\/\-\s]{2,100}?)\s+set[- ]aside\s+for\s+/i', $working, $m)) {
        $phrase = normalize_whitespace(trim((string) (($m[1] ?? '') . ($m[2] ?? ''))));
        $setAsideSentence = description_set_aside_sentence($phrase);
        $working = ltrim(substr($working, strlen((string) $m[0])));
    }

    $working = preg_replace('/\bin support of\s+(the\s+)?([^.;]+?)(?=[.;]|$)/i', 'located at $1$2', $working) ?? $working;
    $working = preg_replace('/\bin support of\b/i', 'for', $working) ?? $working;

    $working = preg_replace('/\bfor\s+1\s+([A-Za-z][^,.;]{1,120})(?=\s+located at\b|,|\.|;|$)/i', 'for a $1', $working) ?? $working;

    $working = preg_replace('/\bseries\s+main\s+image\s+printer\b/i', 'printer', $working) ?? $working;
    $working = preg_replace('/\bmain\s+image\s+printer\b/i', 'printer', $working) ?? $working;

    $working = preg_replace('/\b(preventative maintenance and service|preventive maintenance and service)\s+for\s+a\b/i', '$1 contract for a', $working, 1) ?? $working;
    $working = preg_replace('/\s+,/', ',', $working) ?? $working;
    $working = normalize_whitespace($working);
    $working = description_strip_terminal_punctuation($working);
    if ($working !== '') {
        $working .= '.';
    }

    if ($setAsideSentence !== null && stripos($working, $setAsideSentence) === false) {
        $working .= ' ' . $setAsideSentence;
    }
    $working = normalize_whitespace($working);
    return $working;
}

function normalize_listing_description(string $source, ?string $rawDescription, array $record = []): array
{
    $source = strtolower(trim($source));
    $rawOriginal = trim((string) $rawDescription);
    $raw = $rawOriginal;

    $meta = [
        'source' => $source,
        'had_url' => 0,
        'url_stripped' => 0,
        'needs_noticedesc_enrichment' => 0,
        'had_placeholder' => 0,
        'had_usable_description' => 0,
        'changed' => 0,
    ];

    if ($raw === '' || description_is_placeholder_text($raw)) {
        $meta['had_placeholder'] = 1;
        return [
            'description_raw' => $raw !== '' ? $raw : null,
            'description_clean' => null,
            'summary_plain' => null,
            'description_display' => null,
            'changed' => false,
            'meta' => $meta,
        ];
    }

    $meta['had_usable_description'] = 1;
    if (description_extract_urls($raw)) {
        $meta['had_url'] = 1;
    }
    if ($source === 'sam_opportunity' && preg_match('~https?://[^\s]*noticedesc~i', $raw)) {
        $meta['needs_noticedesc_enrichment'] = 1;
    }

    [$working, $genericMeta] = description_clean_generic_text($raw);
    $meta['url_stripped'] = (int) ($genericMeta['url_stripped'] ?? 0);

    if (!description_is_displayable_text($working)) {
        return [
            'description_raw' => $raw,
            'description_clean' => null,
            'summary_plain' => null,
            'description_display' => null,
            'changed' => false,
            'meta' => $meta,
        ];
    }

    if ($source === 'sam_opportunity') {
        $working = description_clean_sam_opportunity_text($working, $record);
    }

    $changed = $working !== $raw;
    $meta['changed'] = $changed ? 1 : 0;
    $clean = $changed ? $working : null;
    $display = $clean ?? $raw;

    return [
        'description_raw' => $raw,
        'description_clean' => $clean,
        'summary_plain' => description_build_summary_plain($display),
        'description_display' => $display,
        'changed' => $changed,
        'meta' => $meta,
    ];
}

function normalizeListingDescription(string $source, ?string $rawDescription, array $record = []): array
{
    return normalize_listing_description($source, $rawDescription, $record);
}

function normalize_opportunity_description(?string $rawDescription, array $context = []): array
{
    return normalize_listing_description('sam_opportunity', $rawDescription, $context);
}
