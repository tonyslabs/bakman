<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * El script falló pero dejó log: el run lo enlaza igual para poder revisarlo.
 */
class ScriptFailedException extends RuntimeException
{
    public function __construct(string $message, public readonly string $logPath)
    {
        parent::__construct($message);
    }
}
