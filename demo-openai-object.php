<?php

require __DIR__ . '/vendor/autoload.php';

use LLMesh\Core\LLMesh;
use LLMesh\Core\Generators\GenerateObjectOptions;
use LLMesh\Core\Schema\Schema;
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

echo "Testing OpenAI Structured Object Generation...\n\n";

$schema = Schema::object([
    'name'      => Schema::string()->required()->description('The person\'s full name'),
    'age'       => Schema::integer()->required()->minimum(0),
    'interests' => Schema::array(Schema::string())->required(),
])->required(['name', 'age', 'interests']);

try {
    $provider = new OpenAIProvider($apiKey);
    $response = LLMesh::generateObject(
        $provider,
        GenerateObjectOptions::make()
            ->withPrompt('Extract information from: Alice is a 28-year-old doctor who loves hiking, photography, and painting.')
            ->withSchema($schema)
    );

    echo "Object parsing succeeded!\n";
    print_r($response->object);
} catch (\Throwable $e) {
    echo "Error occurred during structured generation: " . $e->getMessage() . "\n";
    if ($e instanceof \LLMesh\Core\Exceptions\ValidationException) {
        print_r($e->errors());
    }
}
