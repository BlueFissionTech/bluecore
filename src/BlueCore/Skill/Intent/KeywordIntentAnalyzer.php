<?php
// KeywordIntentAnalyzer.php
namespace BlueFission\BlueCore\Skill\Intent;

use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Automata\Context;
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\ModelManager;

class KeywordIntentAnalyzer implements IAnalyzer
{
    private $intentClassifier;
    private $modelDirPath;

    public function __construct(NaiveBayesTextClassification $intentClassifier, string $modelDirPath)
    {
        $this->intentClassifier = $intentClassifier;
        $this->modelDirPath = $modelDirPath;
    }

    public function analyze(string $input, Context $context, array $intents): array
    {
        $scores = [];

        // Tokenize the input into words and convert to lowercase
        $inputWords = preg_split('/\s+/', strtolower($input));
        $inputWordCount = count($inputWords);

        $samples = [];
        $labels = [];

        foreach ($intents as $intentName => $intent) {
            $criteria = $intent->getCriteria();
            // Calculate the score for this intent based on keywords, context, and other criteria.
            $score = 0;

            foreach ($criteria['keywords'] as $keyword) {
                $keywordLower = strtolower($keyword['word']);

                $samples[] = $keyword['word'];
                $labels[] = $intent->getLabel();
                
                foreach ($inputWords as $inputWord) {
                    $similarityPercent = 0;
                    similar_text($inputWord, $keywordLower, $similarityPercent);

                    // Convert the similarity percentage to a value between 0 and 1
                    $similarityScore = $similarityPercent / 100;

                    // Calculate the multiplier based on the input word count
                    $multiplier = 1 / $inputWordCount;
                    $multiplier = $multiplier*$keyword['priority'];

                    // Increase the score based on the similarity and multiplier
                    // $score += $keyword['priority'] * $similarityScore * $multiplier;
                    if ( $similarityScore > $score ) {
                        $score = $similarityScore*$multiplier;
                    }
                }
            }

            $scores[$intent->getLabel()] = $score;
        }

        $modelFilePath = OPUS_ROOT . '/storage/models/intent_model.phpml';
        $modelManager = new ModelManager();
        if ( file_exists($modelFilePath) ) {
            $loadedPipeline = $modelManager->restoreFromFile($modelFilePath);

            $this->intentClassifier->setPipeline($loadedPipeline);
        } else {
            $skills = \App::instance()->service('skill');

            $this->intentClassifier->train($samples, $labels);

            if ( !file_exists(OPUS_ROOT . '/storage/models') ) {
                mkdir(OPUS_ROOT . '/storage/models');
            }

            $modelManager->saveToFile($this->intentClassifier->getPipeline(), $modelFilePath);
        }

        $classification = $this->intentClassifier->predict($input);
        if ( !isset($scores[$classification]) ) {
            $scores[$classification] = 0;
        }

        $scores[$classification] = $this->classificationBonus($scores[$classification], .1);

        if ( !empty($scores) ) {
            arsort($scores);
        }

        return $scores;
    }

    private function classificationBonus($current_score, $bonus_percentage) {
        // Ensure current_score and bonus_percentage are within the valid range
        $current_score = max(0, min(1, $current_score));
        $bonus_percentage = max(0, min(1, $bonus_percentage));

        // Calculate the new score
        $new_score = $current_score + (1 - $current_score) * $bonus_percentage;

        // Return the new score
        return $new_score;
    }
}
