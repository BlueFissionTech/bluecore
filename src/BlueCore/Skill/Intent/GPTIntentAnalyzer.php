<?php
// GPTIntentAnalyzer.php
namespace BlueFission\BlueCore\Skill\Intent;

use BlueFission\BlueCore\Skill\Intent\Context;
use App\Business\OpenAIService;

class GPTIntentAnalyzer implements IAnalyzer
{
    private $openai;

    public function __construct(OpenAIService $openai)
    {
        if (!$openai) {
            throw new \Exception("OpenAI service is not registered.");
        }
        $this->openai = $openai;
    }

    public function analyze(string $input, Context $context, array $intents): array
    {
        $scores = [];

        foreach ($intents as $intentName => $intent) {
            $criteria = $intent->getCriteria();
            $keywords = array_map(function ($keyword) {
                return $keyword['word'];
            }, $criteria['keywords']);

            // Prepare the prompt
            $prompt = "Rate the similarity of the following input to these keywords: \"$input\". Keywords: ";
            $prompt .= implode(', ', $keywords);
            $prompt .= '. Score from 0 to 1.';

            // Get the GPT-3 completion
            $response = $this->openai->complete($prompt);

            // Get the score from the response
            $score = floatval(trim($response['choices'][0]['text']));

            if ($score > 0) {
                $scores[$intent->getName()] = $score;
            }
        }

        return $scores;
    }
}
