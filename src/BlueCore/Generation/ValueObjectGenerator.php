<?php
namespace BlueFission\BlueCore\Generation;

use BlueFission\Str;

class ValueObjectGenerator extends BaseGenerator
{
    public function getType(): string
    {
        return 'valueobject';
    }

    public function generateValueObjectFromHeaders($tableName, $headers)
    {
        $valueObjectCode = '';

        foreach ($headers as $header) {
            $propertyName = Str::lower($header);
            $valueObjectCode .= "\tpublic \${$propertyName};\n";
        }

        return $valueObjectCode;
    }
}
