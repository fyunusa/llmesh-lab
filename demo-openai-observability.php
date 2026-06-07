<?php

require __DIR__ . '/vendor/autoload.php';

use LLMesh\Core\LLMesh;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\Core\Observability\MiddlewareStack;
use LLMesh\Core\Observability\LoggingMiddleware;
use LLMesh\Core\Observability\CostTrackingMiddleware;
use LLMesh\Core\Observability\UsageTracker;
use LLMesh\Core\Observability\CostCalculator;
use LLMesh\OpenAI\OpenAIProvider;
use Psr\Log\AbstractLogger;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

$apiKey = isset($_ENV['OPENAI_API_KEY']) ? $_ENV['OPENAI_API_KEY'] : getenv('OPENAI_API_KEY');
if (!$apiKey || empty(trim($apiKey))) {
    echo "OPENAI_API_KEY is not set or empty in .env. Test skipped.\n";
    exit(0);
}

// Register pricing for gpt-4o versions
CostCalculator::setPricing('gpt-4o', 2.50, 10.00);
CostCalculator::setPricing('gpt-4o-2024-05-13', 2.50, 10.00);
CostCalculator::setPricing('gpt-4o-2024-08-06', 2.50, 10.00);

class ConsoleLogger extends AbstractLogger
{
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $time = date('Y-m-d H:i:s');
        $levelUpper = strtoupper((string)$level);
        echo "[{$time}] [{$levelUpper}] {$message}\n";
        if (!empty($context)) {
            echo "Context: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
        }
        echo "------------------------------------------------\n";
    }
}

echo "Testing OpenAI Observability Stack (Logging & Cost Tracking)...\n\n";

try {
    $rawProvider = new OpenAIProvider($apiKey);
    $tracker = new UsageTracker();
    $logger = new ConsoleLogger();

    $provider = MiddlewareStack::wrap($rawProvider)
        ->with(new LoggingMiddleware($logger))
        ->with(new CostTrackingMiddleware($tracker));

    // Request 1: Generate Text
    echo "Sending request 1 (generateText)...\n";
    $response = LLMesh::generateText(
        $provider,
        GenerateTextOptions::make()->withPrompt('Tell me a very short 1-line joke about OpenAI.')
    );
    echo "Response Joke: " . $response->getText() . "\n\n";

    // Request 2: Stream Text
    echo "Sending request 2 (streamText)...\n";
    $stream = LLMesh::streamText(
        $provider,
        GenerateTextOptions::make()->withPrompt('Say "OpenAI is cool" in Spanish.')
    );
    
    echo "Stream Output: ";
    foreach ($stream as $chunk) {
        echo $chunk->text;
        flush();
    }
    echo "\n\n";

    // Print Summary
    $summary = $tracker->getSummary();
    echo "=== Usage & Cost Summary ===\n";
    echo "Total Calls:      " . $summary['calls'] . "\n";
    echo "Input Tokens:     " . $summary['tokens_in'] . "\n";
    echo "Output Tokens:    " . $summary['tokens_out'] . "\n";
    echo "Total Tokens:     " . $summary['total_tokens'] . "\n";
    echo "Estimated Cost:   $" . number_format($summary['cost_usd'], 6) . " USD\n";
    echo "============================\n";

} catch (\Throwable $e) {
    echo "Error occurred: " . $e->getMessage() . "\n";
}
