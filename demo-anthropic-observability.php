<?php

require __DIR__ . '/vendor/autoload.php';

use LLMesh\Core\LLMesh;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\Core\Observability\MiddlewareStack;
use LLMesh\Core\Observability\LoggingMiddleware;
use LLMesh\Core\Observability\CostTrackingMiddleware;
use LLMesh\Core\Observability\UsageTracker;
use LLMesh\Anthropic\AnthropicProvider;
use Psr\Log\AbstractLogger;

use LLMesh\Core\Observability\CostCalculator;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

$apiKey = isset($_ENV['ANTHROPIC_API_KEY']) ? $_ENV['ANTHROPIC_API_KEY'] : getenv('ANTHROPIC_API_KEY');
if (!$apiKey || empty(trim($apiKey))) {
    echo "ANTHROPIC_API_KEY is not set or empty in .env. Observability test skipped.\n";
    exit(0);
}

// Register pricing for specific Claude model tags to ensure cost calculation works
CostCalculator::setPricing('claude-sonnet-4-5-20250929', 3.00, 15.00);
CostCalculator::setPricing('claude-3-5-sonnet-20241022', 3.00, 15.00);

// Simple PSR-3 console logger implementation
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

echo "Testing Anthropic Observability Stack (Logging & Cost Tracking)...\n\n";

try {
    $rawProvider = new AnthropicProvider($apiKey);
    $tracker = new UsageTracker();
    $logger = new ConsoleLogger();

    // Wrap the raw provider with logging and cost tracking middlewares
    $provider = MiddlewareStack::wrap($rawProvider)
        ->with(new LoggingMiddleware($logger))
        ->with(new CostTrackingMiddleware($tracker));

    // Request 1: Generate Text
    echo "Sending request 1 (generateText)...\n";
    $response = LLMesh::generateText(
        $provider,
        GenerateTextOptions::make()->withPrompt('Tell me a very short 1-line joke about PHP.')
    );
    echo "Response Joke: " . $response->getText() . "\n";
    echo "Raw response model: " . ($response->getRaw()['model'] ?? 'N/A') . "\n\n";

    // Request 2: Stream Text
    echo "Sending request 2 (streamText)...\n";
    $stream = LLMesh::streamText(
        $provider,
        GenerateTextOptions::make()->withPrompt('Say "PHP is awesome" in Spanish.')
    );
    
    echo "Stream Output: ";
    foreach ($stream as $chunk) {
        echo $chunk->text;
        flush();
    }
    echo "\n\n";

    // Print Usage and Cost Summary
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
