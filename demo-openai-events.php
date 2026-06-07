<?php

require __DIR__ . '/vendor/autoload.php';

use LLMesh\Core\LLMesh;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\OpenAI\OpenAIProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use LLMesh\Core\Events\GenerationStarted;
use LLMesh\Core\Events\GenerationCompleted;
use LLMesh\Core\Events\GenerationFailed;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

$apiKey = isset($_ENV['OPENAI_API_KEY']) ? $_ENV['OPENAI_API_KEY'] : getenv('OPENAI_API_KEY');
if (!$apiKey || empty(trim($apiKey))) {
    echo "OPENAI_API_KEY is not set or empty in .env. Test skipped.\n";
    exit(0);
}

class SimpleEventDispatcher implements EventDispatcherInterface
{
    private array $listeners = [];

    public function addListener(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(object $event): object
    {
        $eventClass = get_class($event);
        if (isset($this->listeners[$eventClass])) {
            foreach ($this->listeners[$eventClass] as $listener) {
                $listener($event);
            }
        }
        return $event;
    }
}

echo "Testing PSR-14 Event Dispatching with OpenAI...\n\n";

try {
    $provider = new OpenAIProvider($apiKey);
    $dispatcher = new SimpleEventDispatcher();

    $dispatcher->addListener(GenerationStarted::class, function (GenerationStarted $event) {
        echo "\n📢 [Event] GenerationStarted:\n";
        echo "   Provider: " . $event->provider . "\n";
        echo "   Prompt:   " . ($event->options->prompt ?? '(No prompt)') . "\n";
        echo "------------------------------------------------\n";
    });

    $dispatcher->addListener(GenerationCompleted::class, function (GenerationCompleted $event) {
        echo "\n📢 [Event] GenerationCompleted:\n";
        echo "   Provider:    " . $event->provider . "\n";
        echo "   Duration:    " . $event->durationMs . " ms\n";
        echo "   Tokens:      In=" . $event->response->getUsage()->getInputTokens() . 
                           ", Out=" . $event->response->getUsage()->getOutputTokens() . 
                           ", Total=" . $event->response->getUsage()->getTotalTokens() . "\n";
        echo "------------------------------------------------\n";
    });

    $llmesh = LLMesh::make()->withEventDispatcher($dispatcher);

    echo "Sending generation request via LLMesh facade...\n";
    $response = $llmesh->generateText(
        $provider,
        GenerateTextOptions::make()->withPrompt('Say "OpenAI is here!" in French.')
    );

    echo "\nResponse received: " . $response->getText() . "\n";

} catch (\Throwable $e) {
    echo "Error occurred: " . $e->getMessage() . "\n";
}
