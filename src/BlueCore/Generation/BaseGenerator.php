<?php
namespace BlueFission\BlueCore\Generation;

abstract class BaseGenerator implements IGenerator
{
    protected $templatePath;
    protected $outputPath;
    protected $aiCodeGenerator;

    public function __construct(string $templatePath, string $outputPath, AICodeGenerator $aiCodeGenerator)
    {
        $this->templatePath = $templatePath;
        $this->outputPath = $outputPath;
        $this->aiCodeGenerator = $aiCodeGenerator;
    }

    public function generate(string $name, string $userPrompt): bool
    {
        $template = file_get_contents($this->templatePath);

        $generatedCode = $this->generateCodeFromAI($template, $userPrompt);
        if (!$generatedCode) {
            return false;
        }

        $outputFile = $this->getOutputFile($name);
        return file_put_contents($outputFile, $generatedCode) !== false;
    }

    protected function generateClassName(string $userPrompt): string
    {
        // Attempt to programmatically determine a name
        $name = $this->extractClassNameFromUserPrompt($userPrompt);

        // If unable to determine a name, request an AI-generated name
        if (empty($name)) {
            $name = $this->requestAIGeneratedClassName($userPrompt);
        }

        return $name;
    }

    protected function extractClassNameFromUserPrompt(string $userPrompt): ?string
	{
	    if (preg_match('/\b(create|build)\s+(a|an)\s+(?<type>\w+)\s+(?<name>\w+)/i', $userPrompt, $matches)) {
	        return ucfirst($matches['name']) . ucfirst($matches['type']);
	    }

	    return null;
	}

	protected function requestAIGeneratedClassName(string $userPrompt): string
	{
	    $generatedName = $this->aiCodeGenerator->generateClassName($userPrompt);

        if (empty($generatedName)) {
            return 'Untitled' . ucfirst($this->getType());
        }

        return $generatedName;
	}

    protected function generateCodeFromAI(string $template, string $userPrompt): ?string
    {
        return $this->aiCodeGenerator->generateCode($template, $userPrompt);
    }

    protected function getOutputFile(string $name): string
    {
        return $this->outputPath . '/' . $name . '.php';
    }

    abstract public function getType(): string;
}
