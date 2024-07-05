<?php
// BaseSkill.php
namespace BlueFission\BlueCore\Skill;

use BlueFission\Automata\Context;
abstract class BaseSkill {
    protected $name;

    public function __construct(string $name) {
        $this->name = $name;
    }

    public function name(): string {
        return $this->name;
    }

    abstract public function execute(Context $context);

    abstract public function response(): string;
}
