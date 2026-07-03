<?php

class AiService extends Prefab
{
    private $provider;
    private $apiKey;
    private $model;

    /**
     * Providers dont l'API est compatible OpenAI (chat/completions, Bearer).
     * Ajouter une entrée ici suffit à activer un nouveau provider OpenAI-compatible.
     */
    private const OPENAI_COMPATIBLE = [
        'openai'     => 'https://api.openai.com/v1/chat/completions',
        'mistral'    => 'https://api.mistral.ai/v1/chat/completions',
        'deepseek'   => 'https://api.deepseek.com/v1/chat/completions',
        'openrouter' => 'https://openrouter.ai/api/v1/chat/completions',
        'zai'        => 'https://api.z.ai/api/paas/v4/chat/completions',
        'xai'        => 'https://api.x.ai/v1/chat/completions',
        'groq'       => 'https://api.groq.com/openai/v1/chat/completions',
    ];

    public function __construct(string $provider, string $apiKey, string $model)
    {
        $this->provider = strtolower($provider);
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    /**
     * Generate text based on a prompt.
     *
     * @param string $systemPrompt Context/Role definition
     * @param string $userPrompt The actual input
     * @param float $temperature Creativity (0.0 to 1.0)
     * @return array ['success' => bool, 'text' => string, 'prompt_tokens' => int, 'completion_tokens' => int, 'error' => string]
     */
    public function generate(string $systemPrompt, string $userPrompt, float $temperature = 0.7, int $maxTokens = 800)
    {
        if (!$this->apiKey) {
            return ['success' => false, 'error' => 'Clé API manquante'];
        }

        switch ($this->provider) {
            case 'gemini':
                return $this->generateGemini($systemPrompt, $userPrompt, $temperature, $maxTokens);
            case 'anthropic':
                return $this->generateAnthropic($systemPrompt, $userPrompt, $temperature, $maxTokens);
            default:
                // Tous les providers OpenAI-compatibles (openai, mistral, deepseek,
                // openrouter, zai, xai, groq…) partagent le même appel.
                $url = self::OPENAI_COMPATIBLE[$this->provider] ?? self::OPENAI_COMPATIBLE['openai'];
                return $this->generateOpenAI($systemPrompt, $userPrompt, $temperature, $url, $maxTokens);
        }
    }

    private function generateOpenAI($systemPrompt, $userPrompt, $temperature, $url = 'https://api.openai.com/v1/chat/completions', $maxTokens = 800)
    {
        $data = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => $temperature,
            'max_tokens' => $maxTokens
        ];

        $headers = "Content-Type: application/json\r\n" .
            "Authorization: Bearer " . $this->apiKey . "\r\n";

        return $this->makeRequest($url, $data, $headers, function ($json) {
            // OpenAI error format: {"error": {"message": "..."}}
            if (isset($json['error'])) {
                return ['success' => false, 'error' => 'API Error: ' . ($json['error']['message'] ?? json_encode($json['error']))];
            }
            // Mistral error format: {"object": "error", "message": "..."}
            if (isset($json['object']) && $json['object'] === 'error') {
                return ['success' => false, 'error' => 'API Error: ' . ($json['message'] ?? json_encode($json))];
            }
            $text = $json['choices'][0]['message']['content'] ?? null;
            if (!$text) {
                return ['success' => false, 'error' => 'Invalid Response: ' . json_encode(array_keys($json))];
            }
            return [
                'success'           => true,
                'text'              => $text,
                'prompt_tokens'     => (int) ($json['usage']['prompt_tokens']     ?? 0),
                'completion_tokens' => (int) ($json['usage']['completion_tokens'] ?? 0),
            ];
        });
    }

    private function generateGemini($systemPrompt, $userPrompt, $temperature, $maxTokens = 800)
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . $this->model . ":generateContent?key=" . $this->apiKey;

        $data = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $systemPrompt . "\n\n" . $userPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxTokens
            ]
        ];

        // Gemini doesn't use Bearer token usually if passed in URL, but let's stick to URL param
        $headers = "Content-Type: application/json\r\n";

        return $this->makeRequest($url, $data, $headers, function ($json) {
            if (isset($json['error'])) {
                return ['success' => false, 'error' => 'Gemini Error: ' . ($json['error']['message'] ?? json_encode($json['error']))];
            }
            $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (!$text) {
                return ['success' => false, 'error' => 'Invalid Response'];
            }
            return [
                'success'           => true,
                'text'              => $text,
                'prompt_tokens'     => (int) ($json['usageMetadata']['promptTokenCount']     ?? 0),
                'completion_tokens' => (int) ($json['usageMetadata']['candidatesTokenCount'] ?? 0),
            ];
        });
    }

    private function generateAnthropic($systemPrompt, $userPrompt, $temperature, $maxTokens = 800)
    {
        $url = 'https://api.anthropic.com/v1/messages';

        $data = [
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => $temperature
        ];

        $headers = "Content-Type: application/json\r\n" .
            "x-api-key: " . $this->apiKey . "\r\n" .
            "anthropic-version: 2023-06-01\r\n";

        return $this->makeRequest($url, $data, $headers, function ($json) {
            if (isset($json['error'])) {
                return ['success' => false, 'error' => 'Anthropic Error: ' . ($json['error']['message'] ?? json_encode($json['error']))];
            }
            $text = $json['content'][0]['text'] ?? null;
            if (!$text) {
                return ['success' => false, 'error' => 'Invalid Response'];
            }
            return [
                'success'           => true,
                'text'              => $text,
                'prompt_tokens'     => (int) ($json['usage']['input_tokens']  ?? 0),
                'completion_tokens' => (int) ($json['usage']['output_tokens'] ?? 0),
            ];
        });
    }

    private function makeRequest($url, $data, $headers, $callback)
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($payload === false) {
            return [
                'success' => false,
                'error' => 'Invalid JSON payload: ' . json_last_error_msg()
            ];
        }

        $options = [
            'http' => [
                'header' => $headers,
                'method' => 'POST',
                'content' => $payload,
                'ignore_errors' => true,
                'timeout' => 60
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            return ['success' => false, 'error' => 'Connectivity Error: ' . ($error['message'] ?? 'unknown')];
        }

        $json = json_decode($response, true);
        if (!$json) {
            return ['success' => false, 'error' => 'Invalid JSON Response: ' . substr($response, 0, 100)];
        }

        return $callback($json);
    }

    /**
     * Get synonyms for a word/phrase.
     */
    public function getSynonyms(string $word): array
    {
        $system = "You are a helpful writing assistant. Provide a JSON array of 5-10 relevant synonyms for the given word or phrase, adapted to a literary context. Output ONLY valid JSON array of strings.";
        $prompt = "Synonyms for: " . $word;

        $result = $this->generate($system, $prompt, 0.5, 100);

        if ($result['success']) {
            $text = $result['text'];
            $text = str_replace(['```json', '```'], '', $text);

            // Clean common prefixes if models ignore JSON only instruction
            $start = strpos($text, '[');
            $end = strrpos($text, ']');
            if ($start !== false && $end !== false) {
                $text = substr($text, $start, $end - $start + 1);
            }

            $decoded = json_decode(trim($text), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }
}
