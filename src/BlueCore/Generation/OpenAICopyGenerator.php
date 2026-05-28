<?php
namespace BlueFission\BlueCore\Generation;

class OpenAICopyGenerator implements IAICopyGenerator
{
    protected $_llmClient;

    public function __construct($llmClient)
    {
        $this->_llmClient = $llmClient;
    }

    public function generateText(string $prompt): ?string
    {
        $result = $this->_llmClient->complete($prompt);
        return $result;
    }

    public function generateImage(string $prompt): ?string
    {
        $result = $this->_llmClient->image($prompt);
        return $result;
    }
}
