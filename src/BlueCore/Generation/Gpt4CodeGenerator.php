<?php
namespace BlueFission\BlueCore\Generation;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class Gpt4CodeGenerator implements AICodeGenerator
{
    private $apiKey;
    private $apiUrl = 'https://api.openai.com/v1/engines/davinci-codex/completions';

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function generateCode(string $template, string $userPrompt): ?string
    {
        $client = new Client();

        $prompt = "Generate PHP code for the following description:\n{$userPrompt}\n\nTemplate:\n{$template}\n\nGenerated Code:";

        try {
            $response = $client->post($this->apiUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ],
                'json' => [
                    'prompt' => $prompt,
                    'max_tokens' => 200,
                    'n' => 1,
                    'stop' => null,
                    'temperature' => 0.5,
                ],
            ]);

            $responseBody = json_decode($response->getBody(), true);

            if (isset($responseBody['choices'][0]['text'])) {
                return trim($responseBody['choices'][0]['text']);
            }

        } catch (RequestException $e) {
            // Handle API request exceptions
        }

        return null;
    }

    public function generateClassName(string $userPrompt): ?string
    {
        $client = new Client();

        $prompt = "Generate a class name for the following description:\n{$userPrompt}\n\nClass Name:";

        try {
            $response = $client->post($this->apiUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ],
                'json' => [
                    'prompt' => $prompt,
                    'max_tokens' => 10,
                    'n' => 1,
                    'stop' => null,
                    'temperature' => 0.5,
                ],
            ]);

            $responseBody = json_decode($response->getBody(), true);

            if (isset($responseBody['choices'][0]['text'])) {
                return trim($responseBody['choices'][0]['text']);
            }

        } catch (RequestException $e) {
            // Handle API request exceptions
        }

        return null;
    }
}
