<?php

declare(strict_types=1);

namespace Jthayne\FormulaEngine\Ast;

interface Node
{
    public function accept(NodeVisitor $visitor): mixed;
}
