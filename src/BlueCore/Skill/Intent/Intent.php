<?php
namespace BlueFission\BlueCore\Skill\Intent;

// Intent.php
class Intent
{
    protected $label;
    protected $name;
    protected $criteria;
    protected $relatedIntents;

    public function __construct(string $label, string $name, array $criteria = [])
    {
        $this->label = $label;
        $this->name = $name;
        $this->criteria = $criteria;
        $this->relatedIntents = [];
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCriteria(): array
    {
        return $this->criteria;
    }

    public function getRelatedIntents(): array
    {
        return $this->relatedIntents;
    }
}
