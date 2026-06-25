<?php
namespace BlueFission\BlueCore\Generation;

use BlueFission\Str;
use BlueFission\Utils\File;
use BlueFission\Utils\Path;
use BlueFission\Val;

abstract class BaseGenerator implements IGenerator
{
    protected $templatePath;
    protected $outputPath;
    protected $aiCodeGenerator;

    public function __construct(string $templatePath, string $outputPath, IAICodeGenerator $aiCodeGenerator)
    {
        $this->templatePath = $templatePath;
        $this->outputPath = $outputPath;
        $this->aiCodeGenerator = $aiCodeGenerator;
    }

    public function generate(string $name, string $userPrompt): bool
    {
        $template = File::readContents($this->templatePath);

        $generatedCode = $this->generateCodeFromAI($template, $userPrompt);
        if (Val::isEmpty($generatedCode)) {
            return false;
        }

        $outputFile = $this->getOutputFile($name);
        return File::writeAtomic($outputFile, $generatedCode) === Path::normalize($outputFile);
    }

    protected function generateClassName(string $userPrompt): string
    {
        // Attempt to programmatically determine a name
        $name = $this->extractClassNameFromUserPrompt($userPrompt);

        // If unable to determine a name, request an AI-generated name
        if (Val::isEmpty($name)) {
            $name = $this->requestAIGeneratedClassName($userPrompt);
        }

        return $name;
    }

    protected function extractClassNameFromUserPrompt(string $userPrompt): ?string
	{
        $matches = Str::matchPattern($userPrompt, '/\b(create|build)\s+(a|an)\s+(?<type>\w+)\s+(?<name>\w+)/i');
	    if ($matches) {
	        return Str::capitalize($matches['name']) . Str::capitalize($matches['type']);
	    }

	    return null;
	}

	protected function requestAIGeneratedClassName(string $userPrompt): string
	{
	    $generatedName = $this->aiCodeGenerator->generateClassName($userPrompt);

        if (Val::isEmpty($generatedName)) {
            return 'Untitled' . Str::capitalize($this->getType());
        }

        return $generatedName;
	}

    protected function generateCodeFromAI(string $template, string $userPrompt): ?string
    {
        return $this->aiCodeGenerator->generateCode($template, $userPrompt);
    }

    protected function getOutputFile(string $name): string
    {
        return Path::normalize($this->outputPath . DIRECTORY_SEPARATOR . $name . '.php');
    }

    abstract public function getType(): string;
}
