<?php
namespace BlueFission\BlueCore\Generation;

class AdminModuleGenerator implements IGenerator
{
    protected $tableName;

    public function __construct($tableName)
    {
        $this->tableName = $tableName;
    }

    public function generate(string $name, string $prompt): bool
    {
        // Generate an admin module for managing the data in $this->tableName  
    }

    public function getType(): string
    {
        return 'admin_module';
    }
}
