<?php

class ContentGenerator implements IGenerator
{
    protected $aiCopyGenerator;

    public function __construct(AICopyGenerator $aiCopyGenerator)
    {
        $this->aiCopyGenerator = $aiCopyGenerator;
    }

    public function generate(string $name, string $prompt)
    {
        switch ($name) {
            case 'copy':
                return $this->copy($prompt);
            case 'image':
                return $this->image($prompt);
            // ...
        }
    }

    public function getType(): string
    {
        return 'content';
    }

    private function copy($prompt)
    {
        return $this->aiCopyGenerator->generateText($prompt);
    }

    private function image($prompt)
    {
        return $this->aiCopyGenerator->generateImage($prompt);
    }
}
