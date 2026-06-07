<?php

require __DIR__ . '/vendor/autoload.php';

use LLMesh\Core\LLMesh;
use LLMesh\Core\Agents\Agent;
use LLMesh\Core\Tools\Tool;
use LLMesh\Anthropic\AnthropicProvider;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

$apiKey = isset($_ENV['ANTHROPIC_API_KEY']) ? $_ENV['ANTHROPIC_API_KEY'] : getenv('ANTHROPIC_API_KEY');
if (!$apiKey || empty(trim($apiKey))) {
    echo "ANTHROPIC_API_KEY is not set or empty in .env. Agent test skipped.\n";
    exit(0);
}

echo "Testing Anthropic Agent with custom tools...\n\n";

// Define a simple math tool
$calculatorTool = Tool::make('calculate_square')
    ->description('Calculate the square of a number.')
    ->parameters([
        'number' => Tool::integer('The number to square')->required(),
    ])
    ->handler(function (array $params): array {
        $num = $params['number'];
        return ['result' => $num * $num];
    });

try {
    $provider = new AnthropicProvider($apiKey);
    $agent = Agent::make(
        provider:     $provider,
        systemPrompt: 'You are a math assistant. Use the calculate_square tool when asked to square a number.',
        tools:        [$calculatorTool],
        maxSteps:     5
    )->onStep(function ($step) {
        echo "--- Agent Step Completed ---\n";
        if (!empty($step->toolCalls)) {
            foreach ($step->toolCalls as $call) {
                echo "Model called tool: {$call->name} with arguments: " . json_encode($call->arguments) . "\n";
            }
        } else {
            echo "Model returned final response.\n";
        }
    });

    $result = $agent->run('What is the square of 12?');

    echo "\nFinal Answer:\n" . $result->finalText . "\n";
} catch (\Throwable $e) {
    echo "Error occurred in agent execution: " . $e->getMessage() . "\n";
}
