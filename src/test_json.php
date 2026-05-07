<?php
$file = 'data/daniel@rouaix.com/ai_config.json';
if (!file_exists($file)) {
    echo "File not found: $file\n";
    exit(1);
}
$content = file_get_contents($file);
$json = json_decode($content, true);
if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON Error: " . json_last_error_msg() . "\n";
    echo "Last Error Code: " . json_last_error() . "\n";
} else {
    echo "JSON Valid.\n";
    echo "System Prompt: " . ($json['prompts']['system'] ?? 'MISSING') . "\n";
}
