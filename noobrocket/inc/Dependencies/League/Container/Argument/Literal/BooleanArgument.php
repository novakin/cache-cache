<?php

declare(strict_types=1);

namespace NoobRocket\Dependencies\League\Container\Argument\Literal;

use NoobRocket\Dependencies\League\Container\Argument\LiteralArgument;

class BooleanArgument extends LiteralArgument
{
    public function __construct(bool $value)
    {
        parent::__construct($value, LiteralArgument::TYPE_BOOL);
    }
}
