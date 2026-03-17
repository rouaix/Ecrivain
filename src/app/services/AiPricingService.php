<?php

/**
 * AiPricingService — compute estimated token cost from a JSON pricing table.
 *
 * Replaces the 112-line if/elseif chain in AiController::usage().
 * Pricing data lives in src/app/ai_pricing.json and can be updated without
 * touching PHP code.
 *
 * Pattern matching uses strpos() in declaration order, so put more specific
 * model-name substrings BEFORE generic prefixes in the JSON file.
 */
class AiPricingService
{
    /** @var array<string, list<array{pattern: string, input: float, output: float}>> */
    private array $table;

    public function __construct(?string $pricingFile = null)
    {
        $file = $pricingFile ?? __DIR__ . '/../ai_pricing.json';
        if (!file_exists($file)) {
            throw new \RuntimeException('ai_pricing.json not found at ' . $file);
        }
        $decoded = json_decode(file_get_contents($file), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid ai_pricing.json: ' . json_last_error_msg());
        }
        // Strip meta keys (_comment, _sources, …)
        $this->table = array_filter($decoded, fn($v) => is_array($v));
    }

    /**
     * Compute the estimated cost in USD for a given model and token counts.
     *
     * @param string $model           Raw model identifier (e.g. "gpt-4o-mini")
     * @param int    $promptTokens    Input / prompt token count
     * @param int    $completionTokens Output / completion token count
     * @return float Estimated cost in USD (0.0 if model not found in table)
     */
    public function computeCost(string $model, int $promptTokens, int $completionTokens): float
    {
        foreach ($this->table as $rates) {
            foreach ($rates as $entry) {
                if (!isset($entry['pattern'])) {
                    continue;
                }
                if (str_contains($model, $entry['pattern'])) {
                    return ($promptTokens * $entry['input'] + $completionTokens * $entry['output']) / 1_000_000;
                }
            }
        }
        return 0.0;
    }
}
