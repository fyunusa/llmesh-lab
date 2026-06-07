<?php

require __DIR__ . '/vendor/autoload.php';

use LLMesh\Core\RAG\Pipeline;
use LLMesh\Core\RAG\Loaders\ArrayLoader;
use LLMesh\Core\RAG\Splitters\RecursiveCharacterSplitter;
use LLMesh\Core\RAG\VectorStores\InMemoryVectorStore;
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

echo "Testing OpenAI RAG Pipeline (Ingestion & Retrieval via Live Embeddings)...\n\n";

try {
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
    $provider = new OpenAIProvider($apiKey);
    $vectorStore = new InMemoryVectorStore();

    // Configure RAG pipeline
    echo "Configuring RAG Pipeline...\n";
    $pipeline = Pipeline::make()
        ->load($loader)
        ->split($splitter)
        ->embed($provider) // Uses text-embedding-3-small under the hood
        ->store($vectorStore)
        ->onProgress(function (int $done, int $total) {
            echo "   [Progress] Ingested chunk {$done}/{$total}\n";
        });

    // Run Ingestion
    echo "Running Ingestion Pipeline...\n";
    $result = $pipeline->run();

    echo "\nIngestion completed successfully!\n";
    echo "   Documents Loaded:  " . $result->documentsLoaded . "\n";
    echo "   Chunks Created:    " . $result->chunksCreated . "\n";
    echo "   Chunks Stored:     " . $result->chunksStored . "\n";
    echo "   Time taken:        " . $result->durationMs . " ms\n\n";

    // Query & Retrieve
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
