<?php

namespace Devdot\Monolog\Exceptions;

class LogParsingException extends \Exception
{
    public function __construct(string $filename, string $extra = null)
    {
        parent::__construct(
            'Failed to parse ' . $filename . ($extra ? PHP_EOL . $extra : '')
        );
    }
}
