<?php

interface IAICopyGenerator
{
    public function generateText(string $prompt): ?string;
    public function generateImage(string $prompt): ?string;
}
