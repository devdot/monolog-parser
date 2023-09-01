<?php

namespace Devdot\Monolog\Exceptions;

class ParserNotReadyException extends \Exception
{
    public function __construct()
    {
        parent::__construct(
            'Parser is not ready!'
        );
    }
}
