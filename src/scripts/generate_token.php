<?php

// Script to generate an auto-login token for User 1

$dataDir = dirname(__DIR__) . '/data';
$jsonFile = $dataDir . '/auth_tokens.json';

// Ensure data directory exists
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

// Load existing tokens
$tokens = [];
if (file_exists($jsonFile)) {
    $content = file_get_contents($jsonFile);
    $data = json_decode($content, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($data['tokens'])) {
        $tokens = $data['tokens'];
    }
}

// Generate new token
$token = bin2hex(random_bytes(32)); // 64 chars
$userId = 1;

$tokens[$token] = [
    'user_id' => $userId,
    'created_at' => date('Y-m-d H:i:s')
];

// Save back to file
$newData = ['tokens' => $tokens];
if (file_put_contents($jsonFile, json_encode($newData, JSON_PRETTY_PRINT))) {
    echo "Token generated successfully.\n";
    echo "User ID: $userId\n";
    echo "Token: $token\n";
    echo "Link: /?token=$token\n";
} else {
    echo "Error saving token to $jsonFile\n";
    exit(1);
}
