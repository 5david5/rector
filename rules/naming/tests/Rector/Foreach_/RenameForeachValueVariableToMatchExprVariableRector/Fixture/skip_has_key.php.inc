<?php

namespace Rector\Naming\Tests\Rector\Foreach_\RenameForeachValueVariableToMatchExprVariableRector\Fixture;

class SkipHasKey
{
    public function run()
    {
        $array = [];
        foreach ($configuration as $constantName => $variableConfiguration) {
            $array[$constantName] = $variableConfiguration;
        }
    }
}