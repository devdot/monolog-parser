<?php

namespace Devdot\Monolog\Exceptions;

class FileNotFoundException extends \Exception
{
    public function __construct(string $filename)
    {
        parent::__construct(
            'File not found: ' . $filename,
        );
    }
}
