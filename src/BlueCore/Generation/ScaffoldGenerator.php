<?php
namespace BlueFission\BlueCore\Generation;

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
        if (strpos(strtolower($header), 'id') !== false) {
            return 'incrementer';
        } elseif (strpos(strtolower($header), 'date') !== false) {
            return 'date';
        } else {
            return 'text';
        }
    }
}
