<?php

require __DIR__ . '/vendor/autoload.php';

use LLMesh\Core\LLMesh;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\Core\Memory\InMemoryStore;
use LLMesh\OpenAI\OpenAIProvider;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

$apiKey = isset($_ENV['OPENAI_API_KEY']) ? $_ENV['OPENAI_API_KEY'] : getenv('OPENAI_API_KEY');
if (!$apiKey || empty(trim($apiKey))) {
    echo "OPENAI_API_KEY is not set or empty in .env. Test skipped.\n";
    exit(0);
}

echo "Testing OpenAI Conversation Memory...\n\n";

$provider = new OpenAIProvider($apiKey);
$store = new InMemoryStore();
$sessionId = 'openai-user-session-888';

try {
    // Round 1: Introduce ourselves
    echo "Round 1: Prompting LLM...\n";
    $options1 = GenerateTextOptions::make()
        ->withPrompt("Hello! My name is Charlie and my favorite food is pizza.")
        ->withMemory($store, $sessionId);

    $response1 = LLMesh::generateText($provider, $options1);
    echo "OpenAI: " . $response1->getText() . "\n\n";

    // Round 2: Ask OpenAI to recall
    echo "Round 2: Prompting LLM (should remember name/food)...\n";
    $options2 = GenerateTextOptions::make()
        ->withPrompt("What is my name and what do I like to eat?")
        ->withMemory($store, $sessionId);

    $response2 = LLMesh::generateText($provider, $options2);
    echo "OpenAI: " . $response2->getText() . "\n";

} catch (\Throwable $e) {
    echo "Error occurred during memory test: " . $e->getMessage() . "\n";
}
