<?php

namespace Rector\PSR4\Tests\Rector\Namespace_\MultipleClassFileToPsr4ClassesRector\Fixture;

interface ClassTraitAndInterface
{
}

final class MyClass
{
}

trait MyTrait
{
}

?>
-----
<?php

namespace Rector\PSR4\Tests\Rector\Namespace_\MultipleClassFileToPsr4ClassesRector\Fixture;


?>
