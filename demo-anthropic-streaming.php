<?php

require __DIR__ . '/vendor/autoload.php';

use LLMesh\Core\LLMesh;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\Anthropic\AnthropicProvider;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

$apiKey = isset($_ENV['ANTHROPIC_API_KEY']) ? $_ENV['ANTHROPIC_API_KEY'] : getenv('ANTHROPIC_API_KEY');
if (!$apiKey || empty(trim($apiKey))) {
    echo "ANTHROPIC_API_KEY is not set or empty in .env. Streaming test skipped.\n";
    exit(0);
}

echo "Testing Anthropic streamText...\n\n";

try {
    $provider = new AnthropicProvider($apiKey);
    $stream = LLMesh::streamText(
        $provider,
        GenerateTextOptions::make()->withPrompt('Write a 2-sentence poem about PHP.')
    );

    foreach ($stream as $chunk) {
        echo $chunk->text;
        flush();
    }
    echo "\n\nStream finished successfully!\n";
} catch (\Throwable $e) {
    echo "Error occurred during streaming: " . $e->getMessage() . "\n";
}
