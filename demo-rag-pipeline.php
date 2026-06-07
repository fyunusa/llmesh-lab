<?php

require __DIR__ . '/vendor/autoload.php';

use LLMesh\Core\RAG\Pipeline;
use LLMesh\Core\RAG\Loaders\ArrayLoader;
use LLMesh\Core\RAG\Splitters\RecursiveCharacterSplitter;
use LLMesh\Core\RAG\VectorStores\InMemoryVectorStore;
use LLMesh\Core\RAG\Document;

// Deterministic mock embedding provider to enable testing RAG without an API key
class MockEmbeddingProvider implements \LLMesh\Core\Contracts\ProviderInterface
{
    public function chat(array $messages, array $options = []): \LLMesh\Core\Contracts\ResponseInterface
    {
        throw new \BadMethodCallException();
    }

    public function stream(array $messages, array $options = []): \LLMesh\Core\Contracts\StreamInterface
    {
        throw new \BadMethodCallException();
    }

    public function embed(string|array $input, array $options = []): \LLMesh\Core\Contracts\EmbeddingResponseInterface
    {
        if (is_array($input)) {
            throw new \InvalidArgumentException();
        }
        $vector = $this->generateVector($input);
        return new \LLMesh\Core\Data\ProviderEmbeddingResponse($vector, strlen($input));
    }

    public function embedBatch(array $inputs, array $options = []): array
    {
        $responses = [];
        foreach ($inputs as $input) {
            $responses[] = $this->embed($input, $options);
        }
        return $responses;
    }

    public function supports(string $capability): bool
    {
        return $capability === 'embeddings' || $capability === 'batch_embeddings';
    }

    private function generateVector(string $text): array
    {
        // 1536-dimension normalized vector
        $vector = array_fill(0, 1536, 0.0);
        
        $keywords = [
            'php'      => [10, 0.9],
            'llmesh'   => [20, 0.95],
            'rag'      => [30, 0.85],
            'retrieval'=> [40, 0.8],
            'python'   => [50, 0.7],
            'gardening'=> [60, 0.9],
            'potatoes' => [70, 0.95],
        ];

        $lower = strtolower($text);
        foreach ($keywords as $word => $data) {
            if (str_contains($lower, $word)) {
                $vector[$data[0]] = $data[1];
            }
        }

        // Add deterministic noise
        $hash = md5($text);
        for ($i = 0; $i < 32; $i++) {
            $val = hexdec($hash[$i]) / 15.0;
            $vector[$i * 45] = max($vector[$i * 45], $val * 0.1);
        }

        // Normalize
        $norm = 0.0;
        foreach ($vector as $val) {
            $norm += $val * $val;
        }
        $norm = sqrt($norm);
        if ($norm > 0) {
            foreach ($vector as $i => $val) {
                $vector[$i] = $val / $norm;
            }
        }

        return $vector;
    }
}

echo "Testing LLMesh RAG Pipeline (Ingestion & Retrieval)...\n\n";

try {
    // 1. Prepare raw documents
    $texts = [
        'LLMesh is a lightweight PHP SDK for building AI applications, text generators, agents, and observability middlewares.',
        'Retrieval-Augmented Generation (RAG) splits text documents, embeds them into vectors, and retrieves top matching chunks for context.',
        'Python has multiple AI tools, but PHP is alive and has LLMesh for modular multi-provider AI application setups.',
        'For organic gardening, plant potatoes in full sun using well-drained, acidic soil rich in compost.',
    ];

    $metadata = [
        ['category' => 'tech', 'source' => 'llmesh-docs'],
        ['category' => 'rag', 'source' => 'rag-wiki'],
        ['category' => 'tech', 'source' => 'blog-post'],
        ['category' => 'gardening', 'source' => 'gardener-guide'],
    ];

    $loader = new ArrayLoader($texts, $metadata);
    $splitter = new RecursiveCharacterSplitter(chunkSize: 100, overlap: 10);
    $embedProvider = new MockEmbeddingProvider();
    $vectorStore = new InMemoryVectorStore();

    // 2. Build the pipeline
    echo "Configuring RAG Pipeline...\n";
    $pipeline = Pipeline::make()
        ->load($loader)
        ->split($splitter)
        ->embed($embedProvider)
        ->store($vectorStore)
        ->onProgress(function (int $done, int $total) {
            echo "   [Progress] Ingested chunk {$done}/{$total}\n";
        });

    // 3. Execute Ingestion: load -> split -> embed -> store
    echo "Running Ingestion Pipeline...\n";
    $result = $pipeline->run();

    echo "\nIngestion completed successfully!\n";
    echo "   Documents Loaded:  " . $result->documentsLoaded . "\n";
    echo "   Chunks Created:    " . $result->chunksCreated . "\n";
    echo "   Chunks Stored:     " . $result->chunksStored . "\n";
    echo "   Time taken:        " . $result->durationMs . " ms\n\n";

    // 4. Query & Retrieve Chunks
    $query = "Tell me about LLMesh and RAG in PHP";
    echo "Querying Vector Store for: \"{$query}\"...\n";

    $retrievedDocs = $pipeline->retrieve($query, topK: 2);

    echo "\n=== Top Retrieved Document Chunks ===\n";
    foreach ($retrievedDocs as $idx => $doc) {
        echo "\nRank " . ($idx + 1) . " (ID: {$doc->id}):\n";
        echo "   Content:  \"" . trim($doc->content) . "\"\n";
        echo "   Metadata: " . json_encode($doc->metadata) . "\n";
    }
    echo "=====================================\n";

} catch (\Throwable $e) {
    echo "Error occurred: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
