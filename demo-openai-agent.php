<?php

require __DIR__ . '/vendor/autoload.php';

use LLMesh\Core\LLMesh;
use LLMesh\Core\Agents\Agent;
use LLMesh\Core\Tools\Tool;
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

echo "Testing OpenAI Agent with custom tools...\n\n";

$calculatorTool = Tool::make('calculate_cube')
    ->description('Calculate the cube of a number.')
    ->parameters([
        'number' => Tool::integer('The number to cube')->required(),
    ])
    ->handler(function (array $params): array {
        $num = $params['number'];
        return ['result' => $num * $num * $num];
    });

try {
    $provider = new OpenAIProvider($apiKey);
    $agent = Agent::make(
        provider:     $provider,
        systemPrompt: 'You are a math assistant. Use the calculate_cube tool when asked to cube a number.',
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

    $result = $agent->run('What is the cube of 6?');

    echo "\nFinal Answer:\n" . $result->finalText . "\n";
} catch (\Throwable $e) {
    echo "Error occurred in agent execution: " . $e->getMessage() . "\n";
}
