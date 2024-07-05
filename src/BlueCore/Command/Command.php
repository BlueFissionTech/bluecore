<?php
namespace BlueFission\BlueCore\Command;

// Command.php
class Command
{
    public $verb;
    public $resources;
    public $args;

    public function __construct()
    {
        $this->resources = [];
        $this->args = [];
    }
}