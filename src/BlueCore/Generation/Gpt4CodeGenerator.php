<?php
namespace BlueFission\BlueCore\Generation;

use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Val;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class Gpt4CodeGenerator implements IAICodeGenerator
{
    private $apiKey;
    private $apiUrl = 'https://api.openai.com/v1/engines/davinci-codex/completions';
    private $client;

    public function __construct(string $apiKey, object $client = null)
    {
        $this->apiKey = $apiKey;
        $this->client = $client;
    }

    public function generateCode(string $template, string $userPrompt): ?string
    {
        $client = $this->client();

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

            $text = Arr::getPath($responseBody, ['choices', 0, 'text']);
            if (Val::isNotEmpty($text)) {
                return Str::trim($text);
            }

        } catch (RequestException $e) {
            // Handle API request exceptions
        }

        return null;
    }

    public function generateClassName(string $userPrompt): ?string
    {
        $client = $this->client();

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

            $text = Arr::getPath($responseBody, ['choices', 0, 'text']);
            if (Val::isNotEmpty($text)) {
                return Str::trim($text);
            }

        } catch (RequestException $e) {
            // Handle API request exceptions
        }

        return null;
    }

    private function client(): object
    {
        if (Val::isNotNull($this->client)) {
            if (!method_exists($this->client, 'post')) {
                throw new \RuntimeException('Injected code-generation client must expose a post method.');
            }

            return $this->client;
        }

        if (!class_exists(Client::class)) {
            throw new \RuntimeException('A Guzzle-compatible HTTP client is required to use Gpt4CodeGenerator.');
        }

        return new Client();
    }
}
