<?php

class OpenAIService extends Prefab
{
    private $apiKey;
    private $model;

    public function __construct()
    {
        $f3 = Base::instance();
        $this->apiKey = $f3->get('OPENAI_API_KEY');
        $this->model = $f3->get('OPENAI_MODEL') ?: 'gpt-4o';
    }

    /**
     * Generate text based on a prompt.
     *
     * @param string $systemPrompt Context/Role definition
     * @param string $userPrompt The actual input
     * @param float $temperature Creativity (0.0 to 1.0)
     * @return array ['success' => bool, 'text' => string, 'error' => string]
     */
    public function generate(string $systemPrompt, string $userPrompt, float $temperature = 0.7)
    {
        if (!$this->apiKey) {
            return ['success' => false, 'error' => 'Clé API manquante'];
        }

        $url = 'https://api.openai.com/v1/chat/completions';
        $data = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => $temperature,
            'max_tokens' => 1000
        ];

        // Use file_get_contents with stream context instead of cURL
        // because curl_init() is undefined on this server.
        $options = [
            'http' => [
                'header' => "Content-Type: application/json\r\n" .
                    "Authorization: Bearer " . $this->apiKey . "\r\n",
                'method' => 'POST',
                'content' => json_encode($data),
                'ignore_errors' => true,
                'timeout' => 60
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            return ['success' => false, 'error' => 'Erreur connectivité: ' . ($error['message'] ?? 'inconnue')];
        }

        // Parse headers to check status if needed, but response content contains error details usually.
        // With ignore_errors=true, we get the body even on 4xx/5xx.

        $json = json_decode($response, true);

        // OpenAI returns 'error' object on failure
        if (isset($json['error'])) {
            return ['success' => false, 'error' => 'OpenAI Error: ' . ($json['error']['message'] ?? json_encode($json['error']))];
        }

        $text = $json['choices'][0]['message']['content'] ?? null;

        if ($text) {
            return ['success' => true, 'text' => $text];
        }

        return ['success' => false, 'error' => 'Réponse invalide: ' . $response];
    }

    /**
     * Get synonyms for a word/phrase.
     *
     * @param string $word
     * @return array
     */
    public function getSynonyms(string $word): array
    {
        $system = "You are a helpful writing assistant. Provide a JSON array of 5-10 relevant synonyms for the given word or phrase, adapted to a literary context. Output ONLY valid JSON array of strings.";
        $prompt = "Synonyms for: " . $word;

        $result = $this->generate($system, $prompt, 0.5);

        if ($result['success']) {
            $text = $result['text'];
            // Clean up code blocks if generic model returns them
            $text = str_replace(['```json', '```'], '', $text);
            $decoded = json_decode(trim($text), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }
}
