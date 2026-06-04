<?php

function file_search_normalize(string $value): string {
    $value = strtolower(pathinfo($value, PATHINFO_FILENAME));
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
    return trim(preg_replace('/\s+/', ' ', $value));
}

function file_search_similarity(string $query, string $candidate): float {
    if ($query === '' || $candidate === '') return 0.0;
    $distance = levenshtein($query, $candidate);
    $length = max(strlen($query), strlen($candidate));
    return $length > 0 ? max(0.0, 1.0 - ($distance / $length)) : 0.0;
}

function file_search_starts_with(string $value, string $prefix): bool {
    return $prefix !== '' && substr($value, 0, strlen($prefix)) === $prefix;
}

function file_search_score(array $file, string $query): int {
    $query = file_search_normalize($query);
    if ($query === '') return 1;

    $filename = file_search_normalize((string)($file['filename'] ?? ''));
    $words = array_values(array_filter(explode(' ', $filename)));
    $score = 0;

    if ($filename === $query) $score = 1000;
    elseif (file_search_starts_with($filename, $query)) $score = 900;
    elseif (in_array($query, $words, true)) $score = 860;
    elseif (array_filter($words, fn($word) => file_search_starts_with($word, $query))) $score = 820;
    elseif (str_contains($filename, $query)) $score = 760;

    $bestSimilarity = file_search_similarity($query, $filename);
    foreach ($words as $word) {
        $bestSimilarity = max($bestSimilarity, file_search_similarity($query, $word));
    }
    $minimumSimilarity = strlen($query) <= 4 ? 0.72 : 0.68;
    if ($bestSimilarity >= $minimumSimilarity) {
        $score = max($score, 500 + (int)round($bestSimilarity * 200));
    }

    foreach (['project_name' => 250, 'folder_name' => 210, 'sub_folder_name' => 190, 'file_type' => 150, 'project_description' => 120] as $field => $weight) {
        $value = file_search_normalize((string)($file[$field] ?? ''));
        if ($value !== '' && str_contains($value, $query)) $score = max($score, $weight);
    }
    return $score;
}
