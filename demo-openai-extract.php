<?php

require __DIR__ . '/vendor/autoload.php';

use LLMesh\Core\LLMesh;
use LLMesh\Core\Structured\LLMModel;
use LLMesh\Core\Structured\ExtractionOptions;
use LLMesh\Core\Structured\Attributes\Field;
use LLMesh\Core\Structured\Attributes\Description;
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

echo "Testing OpenAI Structured Extraction (Pydantic-style)...\n\n";

#[Description("User profile details extracted from a bio or description")]
class UserProfile extends LLMModel
{
    public function __construct(
        #[Field(description: "The user's full name", example: "John Doe")]
        public readonly string $fullName,

        #[Field(description: "The age in years", minimum: 0)]
        public readonly int $age,

        #[Field(description: "Hobbies and personal interests", example: ["hiking", "photography"])]
        public readonly array $interests,
    ) {}

    public function validate(): void
    {
        if ($this->age < 0) {
            throw new \InvalidArgumentException("Age cannot be negative.");
        }
    }
}

try {
    $provider = new OpenAIProvider($apiKey);
    $inputText = "Alice is a 28-year-old doctor who loves hiking, photography, and painting.";

    echo "Input Text: '$inputText'\n\n";
    echo "Running extractFrom()...\n";

    /** @var UserProfile $profile */
    $profile = LLMesh::make()->extractFrom(
        UserProfile::class,
        $inputText,
        $provider
    );

    echo "\nStructured Extraction succeeded!\n";
    echo "Class type: " . get_class($profile) . "\n";
    echo "Full Name: " . $profile->fullName . "\n";
    echo "Age: " . $profile->age . "\n";
    echo "Interests: " . implode(', ', $profile->interests) . "\n";

    echo "\nSerialized toArray():\n";
    print_r($profile->toArray());

} catch (\Throwable $e) {
    echo "Error occurred during structured extraction: " . $e->getMessage() . "\n";
    if ($e instanceof \LLMesh\Core\Exceptions\ValidationException) {
        print_r($e->errors());
    }
}
