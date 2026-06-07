<?php

require __DIR__ . '/vendor/autoload.php';

use LLMesh\Core\LLMesh;
use LLMesh\Core\Generators\GenerateObjectOptions;
use LLMesh\Core\Schema\Schema;
use LLMesh\Anthropic\AnthropicProvider;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

$apiKey = isset($_ENV['ANTHROPIC_API_KEY']) ? $_ENV['ANTHROPIC_API_KEY'] : getenv('ANTHROPIC_API_KEY');
if (!$apiKey || empty(trim($apiKey))) {
    echo "ANTHROPIC_API_KEY is not set or empty in .env. Structured output test skipped.\n";
    exit(0);
}

echo "Testing Anthropic Structured Object Generation...\n\n";

// Define schema for person extraction
$schema = Schema::object([
    'name'      => Schema::string()->required()->description('The person\'s full name'),
    'age'       => Schema::integer()->required()->minimum(0),
    'interests' => Schema::array(Schema::string())->required(),
])->required(['name', 'age', 'interests']);

try {
    $provider = new AnthropicProvider($apiKey);
    $response = LLMesh::generateObject(
        $provider,
        GenerateObjectOptions::make()
            ->withPrompt('Extract information from: Bob is a 45-year-old engineer who loves coding, chess, and gardening.')
            ->withSchema($schema)
            ->withMode(\LLMesh\Core\Generators\OutputMode::TOOL_MODE)
    );

    echo "Object parsing succeeded!\n";
    print_r($response->object);
} catch (\Throwable $e) {
    echo "Error occurred during structured generation: " . $e->getMessage() . "\n";
    if ($e instanceof \LLMesh\Core\Exceptions\ValidationException) {
        print_r($e->errors());
    }
    echo $e->getTraceAsString() . "\n";
}
