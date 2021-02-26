<?php

declare(strict_types=1);

namespace Rector\RemovingStatic\Rector\StaticCall;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PHPStan\Type\ObjectType;
use Rector\Core\Configuration\Option;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\ValueObject\MethodName;
use Rector\Naming\Naming\PropertyNaming;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Symplify\PackageBuilder\Parameter\ParameterProvider;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Rector\RemovingStatic\Tests\Rector\StaticCall\DesiredStaticCallTypeToDynamicRector\DesiredStaticCallTypeToDynamicRectorTest
 */
final class DesiredStaticCallTypeToDynamicRector extends AbstractRector
{
    /**
     * @var ObjectType[]
     */
    private $staticObjectTypes = [];

    /**
     * @var PropertyNaming
     */
    private $propertyNaming;

    public function __construct(PropertyNaming $propertyNaming, ParameterProvider $parameterProvider)
    {
        $typesToRemoveStaticFrom = $parameterProvider->provideArrayParameter(Option::TYPES_TO_REMOVE_STATIC_FROM);
        foreach ($typesToRemoveStaticFrom as $typeToRemoveStaticFrom) {
            $this->staticObjectTypes[] = new ObjectType($typeToRemoveStaticFrom);
        }

        $this->propertyNaming = $propertyNaming;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Change defined static service to dynamic one', [
            new CodeSample(
                <<<'CODE_SAMPLE'
final class SomeClass
{
    public function run()
    {
        SomeStaticMethod::someStatic();
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
final class SomeClass
{
    public function run()
    {
        $this->someStaticMethod::someStatic();
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
        return [StaticCall::class];
    }

    /**
     * @param StaticCall $node
     */
    public function refactor(Node $node): ?Node
    {
        foreach ($this->staticObjectTypes as $objectType) {
            if (! $this->isObjectType($node->class, $objectType)) {
                continue;
            }

            // is the same class or external call?
            $className = $this->getName($node->class);
            if ($className === 'self') {
                return $this->createFromSelf($node);
            }

            $propertyName = $this->propertyNaming->fqnToVariableName($objectType);

            $currentMethodName = $node->getAttribute(AttributeKey::METHOD_NAME);
            if ($currentMethodName === MethodName::CONSTRUCT) {
                $propertyFetch = new Variable($propertyName);
            } else {
                $propertyFetch = new PropertyFetch(new Variable('this'), $propertyName);
            }

            return new MethodCall($propertyFetch, $node->name, $node->args);
        }

        return null;
    }

    private function createFromSelf(StaticCall $staticCall): MethodCall
    {
        return new MethodCall(new Variable('this'), $staticCall->name, $staticCall->args);
    }
}
