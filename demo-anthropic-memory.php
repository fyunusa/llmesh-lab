<?php

require __DIR__ . '/vendor/autoload.php';

use LLMesh\Core\LLMesh;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\Core\Memory\InMemoryStore;
use LLMesh\Anthropic\AnthropicProvider;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

$apiKey = isset($_ENV['ANTHROPIC_API_KEY']) ? $_ENV['ANTHROPIC_API_KEY'] : getenv('ANTHROPIC_API_KEY');
if (!$apiKey || empty(trim($apiKey))) {
    echo "ANTHROPIC_API_KEY is not set or empty in .env. Memory test skipped.\n";
    exit(0);
}

echo "Testing Anthropic Conversation Memory...\n\n";

$provider = new AnthropicProvider($apiKey);
$store = new InMemoryStore();
$sessionId = 'user-session-999';

try {
    // Round 1: Introduce ourselves
    echo "Round 1: Prompting LLM...\n";
    $options1 = GenerateTextOptions::make()
        ->withPrompt("Hello! My name is Alice and my favorite color is purple.")
        ->withMemory($store, $sessionId);

    $response1 = LLMesh::generateText($provider, $options1);
    echo "Claude: " . $response1->getText() . "\n\n";

    // Round 2: Ask Claude to recall
    echo "Round 2: Prompting LLM (should remember name/color)...\n";
    $options2 = GenerateTextOptions::make()
        ->withPrompt("What is my name and what color do I like?")
        ->withMemory($store, $sessionId);

    $response2 = LLMesh::generateText($provider, $options2);
    echo "Claude: " . $response2->getText() . "\n";

} catch (\Throwable $e) {
    echo "Error occurred during memory test: " . $e->getMessage() . "\n";
}
