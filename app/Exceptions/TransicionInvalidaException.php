<?php

namespace App\Exceptions;

use RuntimeException;

class TransicionInvalidaException extends RuntimeException
{
    public static function para(string $desde, string $hacia): self
    {
        return new self("Un soporte en estado '{$desde}' no puede pasar a '{$hacia}'.");
    }
}
