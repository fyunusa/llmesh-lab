<?php

require __DIR__ . '/vendor/autoload.php';

use LLMesh\Core\LLMesh;
use LLMesh\Core\Generators\GenerateTextOptions;
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

echo "Testing OpenAI Basic Text Generation...\n\n";

try {
    $provider = new OpenAIProvider($apiKey);
    $response = LLMesh::generateText(
        $provider,
        GenerateTextOptions::make()->withPrompt('Say hello in 1 sentence!')
    );

    echo "Response:\n" . $response->getText() . "\n\n";
    
    echo "Token Usage Details:\n";
    print_r($response->getUsage()->toArray());
} catch (\Throwable $e) {
    echo "Error occurred: " . $e->getMessage() . "\n";
}
