<?php
namespace BlueFission\BlueCore\Generation;

interface IAICodeGenerator
{
    public function generateCode(string $template, string $userPrompt): ?string;
    public function generateClassName(string $userPrompt): ?string;
}