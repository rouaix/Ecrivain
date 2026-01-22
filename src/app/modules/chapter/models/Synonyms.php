<?php

/**
 * Synonyms helper provides a tiny in‑memory thesaurus used for word
 * substitution suggestions. In a production system you might
 * integrate with an external API or use a comprehensive dictionary,
 * however for this example a small built‑in map suffices.
 */
class Synonyms
{
    /**
     * Map of base words to arrays of synonyms.
     *
     * @var array
     */
    protected static $data = [
        'happy' => ['joyful', 'pleased', 'content', 'cheerful', 'delighted'],
        'sad' => ['unhappy', 'sorrowful', 'downcast', 'mournful', 'depressed'],
        'big' => ['large', 'huge', 'enormous', 'gigantic', 'vast'],
        'small' => ['tiny', 'little', 'miniature', 'minute', 'petite'],
        'fast' => ['quick', 'swift', 'rapid', 'speedy', 'fleet'],
        'slow' => ['sluggish', 'lethargic', 'sluggish', 'delayed', 'unhurried'],
        'smart' => ['intelligent', 'clever', 'bright', 'sharp', 'brilliant'],
        'good' => ['excellent', 'great', 'positive', 'favorable', 'beneficial'],
        'bad' => ['poor', 'unfavorable', 'negative', 'adverse', 'harmful'],
        'interesting' => ['fascinating', 'engaging', 'intriguing', 'captivating', 'compelling'],
        'beautiful' => ['gorgeous', 'attractive', 'lovely', 'stunning', 'pretty'],
        'strong' => ['powerful', 'sturdy', 'robust', 'durable', 'solid'],
        'weak' => ['fragile', 'feeble', 'frail', 'delicate', 'vulnerable'],
        'quick' => ['fast', 'rapid', 'swift', 'speedy', 'brisk'],
        'slowly' => ['leisurely', 'gradually', 'unhurriedly', 'ponderously', 'sluggishly'],
    ];

    /**
     * Return synonyms for a given word. Case insensitive.
     *
     * @param string $word
     * @return array
     */
    public static function get(string $word): array
    {
        $key = strtolower(trim($word));
        return self::$data[$key] ?? [];
    }
}