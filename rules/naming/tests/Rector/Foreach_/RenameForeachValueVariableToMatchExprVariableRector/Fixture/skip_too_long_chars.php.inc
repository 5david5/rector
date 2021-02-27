<?php

namespace Rector\Naming\Tests\Rector\Foreach_\RenameForeachValueVariableToMatchExprVariableRector\Fixture;

class SkipTooLongChars
{
    public function run()
    {
        $array = [];
        foreach ($variablesvariablesvariablesvariablesvariablesvariablesvariables as $property) {
            $array[] = $property;
        }
    }
}

?>