<?php

declare(strict_types=1);

namespace Rector\RemovingStatic\Rector\Class_;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Type\ObjectType;
use Rector\Core\Configuration\Option;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\ValueObject\MethodName;
use Rector\Naming\Naming\PropertyNaming;
use Rector\RemovingStatic\NodeAnalyzer\StaticCallPresenceAnalyzer;
use Symplify\PackageBuilder\Parameter\ParameterProvider;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @sponsor Thanks https://amateri.com for sponsoring this rule - visit them on https://www.startupjobs.cz/startup/scrumworks-s-r-o
 *
 * @see \Rector\RemovingStatic\Tests\Rector\Class_\DesiredClassTypeToDynamicRector\DesiredClassTypeToDynamicRectorTest
 */
final class DesiredClassTypeToDynamicRector extends AbstractRector
{
    /**
     * @var ObjectType[]
     */
    private $staticObjectTypes = [];

    /**
     * @var PropertyNaming
     */
    private $propertyNaming;

    /**
     * @var StaticCallPresenceAnalyzer
     */
    private $staticCallPresenceAnalyzer;

    public function __construct(
        PropertyNaming $propertyNaming,
        StaticCallPresenceAnalyzer $staticCallPresenceAnalyzer,
        ParameterProvider $parameterProvider
    ) {
        $typesToRemoveStaticFrom = $parameterProvider->provideArrayParameter(Option::TYPES_TO_REMOVE_STATIC_FROM);
        foreach ($typesToRemoveStaticFrom as $singleTypesToRemoveStaticFrom) {
            $this->staticObjectTypes[] = new ObjectType($singleTypesToRemoveStaticFrom);
        }

        $this->propertyNaming = $propertyNaming;
        $this->staticCallPresenceAnalyzer = $staticCallPresenceAnalyzer;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Change full static service, to dynamic one', [
            new CodeSample(
                <<<'CODE_SAMPLE'
class AnotherClass
{
    public function run()
    {
        SomeClass::someStatic();
    }
}

class SomeClass
{
    public static function run()
    {
        self::someStatic();
    }

    private static function someStatic()
    {
    }
}
CODE_SAMPLE
,
                <<<'CODE_SAMPLE'
class AnotherClass
{
    /**
     * @var SomeClass
     */
    private $someClass;

    public fuction __construct(SomeClass $someClass)
    {
        $this->someClass = $someClass;
    }

    public function run()
    {
        SomeClass::someStatic();
    }
}

class SomeClass
{
    public function run()
    {
        $this->someStatic();
    }

    private function someStatic()
    {
    }
}
CODE_SAMPLE
            ),
        ]);
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /**
     * @param Class_ $node
     */
    public function refactor(Node $node): ?Node
    {
        foreach ($this->staticObjectTypes as $staticObjectType) {
            // do not any dependencies to class itself
            if ($this->isObjectType($node, $staticObjectType)) {
                continue;
            }

            $this->completeDependencyToConstructorOnly($node, $staticObjectType);

            if ($this->staticCallPresenceAnalyzer->hasClassAnyMethodWithStaticCallOnType($node, $staticObjectType)) {
                $propertyExpectedName = $this->propertyNaming->fqnToVariableName($staticObjectType);
                $this->addConstructorDependencyToClass($node, $staticObjectType, $propertyExpectedName);

                return $node;
            }
        }

        return null;
    }

    private function completeDependencyToConstructorOnly(Class_ $class, ObjectType $objectType): void
    {
        $constructClassMethod = $class->getMethod(MethodName::CONSTRUCT);
        if (! $constructClassMethod instanceof ClassMethod) {
            return;
        }

        $hasStaticCall = $this->staticCallPresenceAnalyzer->hasMethodStaticCallOnType(
            $constructClassMethod,
            $objectType
        );

        if (! $hasStaticCall) {
            return;
        }

        $propertyExpectedName = $this->propertyNaming->fqnToVariableName($objectType);

        if ($this->isTypeAlreadyInParamMethod($constructClassMethod, $objectType)) {
            return;
        }

        $constructClassMethod->params[] = new Param(
            new Variable($propertyExpectedName),
            null,
            new FullyQualified($objectType->getClassName())
        );
    }

    private function isTypeAlreadyInParamMethod(ClassMethod $classMethod, ObjectType $objectType): bool
    {
        foreach ($classMethod->getParams() as $param) {
            if ($param->type === null) {
                continue;
            }

            if ($this->isName($param->type, $objectType->getClassName())) {
                return true;
            }
        }

        return false;
    }
}
