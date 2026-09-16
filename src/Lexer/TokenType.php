<?php

declare(strict_types=1);

namespace Jthayne\FormulaEngine\Lexer;

enum TokenType
{
    case Identifier;
    case Variable;
    case String;
    case Number;
    case Operator;
    case LParen;
    case RParen;
    case Pipe;
    case Comma;
    case Eof;
}
