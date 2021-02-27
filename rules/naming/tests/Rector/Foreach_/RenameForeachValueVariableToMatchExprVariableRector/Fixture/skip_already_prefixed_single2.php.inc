<?php

namespace Rector\Naming\Tests\Rector\Foreach_\RenameForeachValueVariableToMatchExprVariableRector\Fixture;

class SkipAlreadyPrefixedSingle2
{
    public function run()
    {
        foreach ($node as $singleNode) {
            $printedContent = $standard->prettyPrint([$singleNode]);
            dump($printedContent);
        }
    }
}

?>
