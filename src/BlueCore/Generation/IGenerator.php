<?php
namespace BlueFission\BlueCore\Generation;

interface IGenerator {
	public function generate(string $name, string $userPrompt);
	public function getType(): string;
}
