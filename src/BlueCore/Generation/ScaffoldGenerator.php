<?php
namespace BlueFission\BlueCore\Generation;

use BlueFission\Str;

class ScaffoldGenerator extends BaseGenerator
{
    public function getType(): string
    {
        return 'scaffold';
    }

    public function generateMigrationFromHeaders($tableName, $headers)
    {
        $scaffoldCode = '';

        // Programmatically generate the scaffold code
        $scaffoldCode .= "Scaffold::create('$tableName', function( Structure \$entity ) {\n";
        foreach ($headers as $header) {
            // Use a helper method to infer the field type from the header
            $fieldType = $this->inferFieldType($header);
            $scaffoldCode .= "    \$entity->{$fieldType}('$header');\n";
        }
        $scaffoldCode .= "});\n";

        return $scaffoldCode;
    }

    protected function inferFieldType($header)
    {
        // Analyze the header and return the appropriate field type
        // This is a simple example, adjust it to fit your needs
        $fieldName = Str::lower($header);
        if (Str::contains($fieldName, 'id')) {
            return 'incrementer';
        } elseif (Str::contains($fieldName, 'date')) {
            return 'date';
        } else {
            return 'text';
        }
    }
}
