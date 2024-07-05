<?php

namespace BlueFission\BlueCore\Generation;

class CSSGenerator implements ICSSGenerator, IGenerator {
    private $css;

    public function __construct($config = []) {
        $this->css = "";
    }

    public function addRule($selector, $properties = []) {
        $this->css .= "$selector {\n";
        foreach ($properties as $property => $value) {
            $this->css .= "\t$property: $value;\n";
        }
        $this->css .= "}\n";
    }

    public function addRules($rules = []) {
        foreach ($rules as $selector => $properties) {
            $this->addRule($selector, $properties);
        }
    }

    public function render() {
        return "<style>\n$this->css\n</style>";
    }

    public function generate(string $name, string $prompt) {
        return $this->render();
    }

    public function getType() {
        return 'css';
    }
}