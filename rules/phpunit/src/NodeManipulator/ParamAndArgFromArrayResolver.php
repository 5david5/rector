<?php

declare(strict_types=1);

namespace Rector\PHPUnit\NodeManipulator;

use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Variable;
use PHPStan\Type\Type;
use Rector\NodeTypeResolver\NodeTypeResolver;
use Rector\NodeTypeResolver\PHPStan\Type\TypeFactory;
use Rector\PHPUnit\ValueObject\ParamAndArg;

final class ParamAndArgFromArrayResolver
{
    /**
     * @var NodeTypeResolver
     */
    private $nodeTypeResolver;

    /**
     * @var TypeFactory
     */
    private $typeFactory;

    public function __construct(NodeTypeResolver $nodeTypeResolver, TypeFactory $typeFactory)
    {
        $this->nodeTypeResolver = $nodeTypeResolver;
        $this->typeFactory = $typeFactory;
    }

    /**
     * @return ParamAndArg[]
     */
    public function resolve(Array_ $array, string $variableName): array
    {
        $isNestedArray = $this->isNestedArray($array);
        if ($isNestedArray) {
            return $this->collectParamAndArgsFromNestedArray($array, $variableName);
        }

        $itemsStaticType = $this->resolveItemStaticType($array, $isNestedArray);
        return $this->collectParamAndArgsFromNonNestedArray($array, $variableName, $itemsStaticType);
    }

    private function isNestedArray(Array_ $array): bool
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            if ($item->value instanceof Array_) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return ParamAndArg[]
     */
    private function collectParamAndArgsFromNestedArray(Array_ $array, string $variableName): array
    {
        $paramAndArgs = [];
        $i = 1;

        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            $nestedArray = $item->value;
            if (! $nestedArray instanceof Array_) {
                continue;
            }

            foreach ($nestedArray->items as $item) {
                if (! $item instanceof ArrayItem) {
                    continue;
                }

                $variable = new Variable($variableName . ($i === 1 ? '' : $i));

                $itemsStaticType = $this->nodeTypeResolver->getStaticType($item->value);
                $paramAndArgs[] = new ParamAndArg($variable, $itemsStaticType);
                ++$i;
            }
        }
        return $paramAndArgs;
    }

    private function resolveItemStaticType(Array_ $array, bool $isNestedArray): Type
    {
        $staticTypes = [];
        if (! $isNestedArray) {
            foreach ($array->items as $item) {
                if (! $item instanceof ArrayItem) {
                    continue;
                }

                $staticTypes[] = $this->nodeTypeResolver->getStaticType($item->value);
            }
        }

        return $this->typeFactory->createMixedPassedOrUnionType($staticTypes);
    }

    /**
     * @return ParamAndArg[]
     */
    private function collectParamAndArgsFromNonNestedArray(
        Array_ $array,
        string $variableName,
        Type $itemsStaticType
    ): array {
        $i = 1;
        $paramAndArgs = [];

        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            $variable = new Variable($variableName . ($i === 1 ? '' : $i));

            $paramAndArgs[] = new ParamAndArg($variable, $itemsStaticType);
            ++$i;

            if (! $item->value instanceof Array_) {
                break;
            }
        }

        return $paramAndArgs;
    }
}
