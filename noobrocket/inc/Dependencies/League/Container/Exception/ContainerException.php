<?php

declare(strict_types=1);

namespace NoobRocket\Dependencies\League\Container\Exception;

use NoobRocket\Dependencies\Psr\Container\ContainerExceptionInterface;
use RuntimeException;

class ContainerException extends RuntimeException implements ContainerExceptionInterface
{
}
