<?php

namespace App\Exceptions;

use RuntimeException;

class ExtraccionNoticiaException extends RuntimeException
{
    public function __construct(
        public readonly string $codigo,
        string $mensaje,
        public readonly int $estado,
    ) {
        parent::__construct($mensaje);
    }
}
