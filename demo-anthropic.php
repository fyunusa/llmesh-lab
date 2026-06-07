<?php

require __DIR__ . '/vendor/autoload.php';

use LLMesh\Core\LLMesh;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\Anthropic\AnthropicProvider;

// Load environment variables from .env file if it exists
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

echo "LLMesh Core & Anthropic Classes loaded successfully!\n";

$apiKey = isset($_ENV['ANTHROPIC_API_KEY']) ? $_ENV['ANTHROPIC_API_KEY'] : getenv('ANTHROPIC_API_KEY');
if (!$apiKey || empty(trim($apiKey))) {
    echo "ANTHROPIC_API_KEY is not set or empty in .env. Real API call skipped.\n";
    exit(0);
}

try {
    $provider = new AnthropicProvider($apiKey);
    $response = LLMesh::generateText(
        $provider,
        GenerateTextOptions::make()->withPrompt('Say hello!')
    );

    echo "Response text:\n" . $response->getText() . "\n";
} catch (\Throwable $e) {
    echo "Error occurred during generation:\n" . $e->getMessage() . "\n";
}
