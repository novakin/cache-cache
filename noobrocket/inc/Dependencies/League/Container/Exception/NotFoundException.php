<?php

declare(strict_types=1);

namespace NoobRocket\Dependencies\League\Container\Exception;

use NoobRocket\Dependencies\Psr\Container\NotFoundExceptionInterface;
use InvalidArgumentException;

class NotFoundException extends InvalidArgumentException implements NotFoundExceptionInterface
{
}
