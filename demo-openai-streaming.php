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

echo "Testing OpenAI streamText...\n\n";

try {
    $provider = new OpenAIProvider($apiKey);
    $stream = LLMesh::streamText(
        $provider,
        GenerateTextOptions::make()->withPrompt('Write a 2-sentence poem about OpenAI.')
    );

    echo "Stream Output: ";
    foreach ($stream as $chunk) {
        echo $chunk->text;
        flush();
    }
    echo "\n\nStream finished successfully!\n";
} catch (\Throwable $e) {
    echo "Error occurred during streaming: " . $e->getMessage() . "\n";
}
