<?php
namespace BlueFission\BlueCore\Skill\Intent;

// Matcher.php
use BlueFission\Automata\Context;
use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\BlueCore\Skill\BaseSkill;
use BlueFission\Services\Service;

class Matcher
{
    protected $intentAnalyzer;
    
    // Use static properties to store skills and intents globally
    protected static $skills = [];
    protected static $intents = [];
    protected static $intentSkillMap = [];


    public function __construct(IAnalyzer $intentAnalyzer)
    {
        $this->intentAnalyzer = $intentAnalyzer;
    }

    public function registerSkill(BaseSkill $skill): self
    {
        self::$skills[$skill->name()] = $skill;
        return $this;
    }

    public function registerIntent($intent): self
    {
        // self::$intents[$intent->getLabel()] = $skillName;
        self::$intents[$intent->getLabel()] = $intent;
        return $this;
    }

    public function associate($intent, $skill): self
    {
        $intentLabel = $intent->getLabel();
        $skillName = $skill->name();

        if (!isset($this->intentSkillMap[$intentLabel])) {
            self::$intentSkillMap[$intentLabel] = [];
        }

        self::$intentSkillMap[$intentLabel][] = $skillName;

        return $this;
    }

    public function map()
    {
        return self::$intentSkillMap;
    }

    public function getIntent(string $intentLabel): ?Intent
    {
        return self::$intents[$intentLabel] ?? null;
    }

    public function getSkill(string $skillName): ?BaseSkill
    {
        return self::$skills[$skillName] ?? null;
    }

    public function getIntents(): array
    {
        return self::$intents;
    }

    public function getSkills(): array
    {
        return self::$skills;
    }

    public function match($input, Context $context): ?array
    {
        $intentScores = $this->intentAnalyzer->analyze($input, $context, self::$intents);

        return $intentScores;
    }

    public function process($intent, Context $context): ?string
    {
        if ( is_string($intent) ) {
            $intent = $this->getIntent($intent);
        }
        $skillNames = isset(self::$intentSkillMap[$intent->getLabel()]) ? self::$intentSkillMap[$intent->getLabel()] : [];

        if (!empty($skillNames)) {
            $skill = $this->getSkill($skillNames[0]);
            $skill->execute($context);
            return $skill->response();
        }

        return null;
    }

}
