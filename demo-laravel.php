<?php

/**
 * LLMesh Demo: Laravel Integration
 *
 * This script demonstrates how LLMesh integrates natively with Laravel.
 * Since this is a standalone CLI script, we manually bootstrap a minimal
 * Laravel Illuminate\Foundation\Application container to simulate a real Laravel app environment.
 *
 * It showcases:
 *  - Laravel Config binding
 *  - Container singleton resolution for LLMesh managers/providers
 *  - Using the Laravel LLMesh Facade (LLMesh::generateText)
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Facade;
use LLMesh\Laravel\LLMeshServiceProvider;
use LLMesh\Laravel\Facades\LLMesh;
use LLMesh\Core\Generators\GenerateTextOptions;

echo "=== LLMesh Laravel Integration Demo ===\n\n";

// 1. Load Environment Variables from .env file
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

$openaiApiKey = isset($_ENV['OPENAI_API_KEY']) ? $_ENV['OPENAI_API_KEY'] : getenv('OPENAI_API_KEY');
$anthropicApiKey = isset($_ENV['ANTHROPIC_API_KEY']) ? $_ENV['ANTHROPIC_API_KEY'] : getenv('ANTHROPIC_API_KEY');

if (!$openaiApiKey && !$anthropicApiKey) {
    echo "⚠️  API Key Error: Neither OPENAI_API_KEY nor ANTHROPIC_API_KEY is configured in your .env file.\n";
    exit(1);
}

// 2. Initialize the Laravel Application Container
// In a real Laravel application, this is handled automatically during bootstrap.
$app = new Application(__DIR__);

// 3. Register the config Repository and configure LLMesh Laravel settings
// This replicates what you would define in config/llmesh.php
$app->singleton('config', function () use ($openaiApiKey, $anthropicApiKey) {
    return new Repository([
        'llmesh' => [
            // The default provider that LLMesh resolves when using the Facade
            'default' => $openaiApiKey ? 'openai' : 'anthropic',

            'providers' => [
                'openai' => [
                    'class'   => \LLMesh\OpenAI\OpenAIProvider::class,
                    'api_key' => $openaiApiKey,
                    'model'   => 'gpt-4o',
                    'options' => [],
                ],
                'anthropic' => [
                    'class'   => \LLMesh\Anthropic\AnthropicProvider::class,
                    'api_key' => $anthropicApiKey,
                    'model'   => 'claude-3-5-sonnet-20241022',
                    'options' => [],
                ],
            ],

            'memory' => [
                'driver' => 'in_memory', // Use in-memory driver for this demo CLI run
            ],
        ],
    ]);
});

// 4. Bind events service so LLMeshServiceProvider boots without issues
$app->singleton('events', function () {
    return new \Illuminate\Events\Dispatcher();
});

// 5. Register and Boot the Service Provider
// In Laravel, this happens automatically when the framework loads providers.
$provider = new LLMeshServiceProvider($app);
$provider->register();

// Trigger boot callbacks on the application container
$app->boot();

// 6. Bind the application instance to the Facade system
// This is what allows static facades (like LLMesh::generateText) to resolve their underlying instances.
Facade::setFacadeApplication($app);

// 7. Use the Laravel LLMesh Facade!
try {
    $defaultProvider = $app['config']->get('llmesh.default');
    echo "Using default Laravel LLMesh driver: '{$defaultProvider}'\n";

    $prompt = 'Why is Laravel so popular in the PHP ecosystem? Answer in exactly one sentence.';
    echo "Prompt: \"{$prompt}\"\n\n";

    echo "Sending request via LLMesh Laravel Facade...\n";
    // Notice we don't pass the provider object here! The Facade resolves the default provider from configuration.
    $response = LLMesh::generateText(
        GenerateTextOptions::make()->withPrompt($prompt)
    );

    echo "\n=== Response Details ===\n";
    echo "Generated Text:\n";
    echo "------------------------------------------------\n";
    echo $response->getText() . "\n";
    echo "------------------------------------------------\n\n";

    // Metadata details
    $usage = $response->getUsage();
    echo "Metadata & Token Usage:\n";
    echo " - Finish Reason:   " . $response->getFinishReason() . "\n";
    echo " - Input Tokens:    " . $usage->getInputTokens() . "\n";
    echo " - Output Tokens:   " . $usage->getOutputTokens() . "\n";
    echo " - Total Tokens:    " . $usage->getTotalTokens() . "\n";
    echo "=========================================\n";

} catch (\Throwable $e) {
    echo "❌ Error occurred: " . $e->getMessage() . "\n";
}
