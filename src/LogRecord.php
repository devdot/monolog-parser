<?php

namespace Devdot\Monolog;

/**
 * Structure for holding the properties of a single log record.
 * @implements \ArrayAccess<string, mixed>
 * @author Thomas Kuschan
 * @copyright (c) 2023
 */
readonly class LogRecord implements \ArrayAccess
{
    /**
     * Create a LogRecord to hold a single log entry.
     * @param array<int, mixed>|\stdClass|string|\NULL $context
     * @param array<int, mixed>|\stdClass|string|\NULL $extra
     */
    public function __construct(
        public \DateTimeImmutable $datetime,
        public string $channel,
        public string $level,
        public string $message,
        public array|\stdClass|string|null $context = [],
        public array|\stdClass|string|null $extra = [],
    ) {
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        // we do not support setting in any way
        throw new \LogicException('Unsupported operation');
    }

    public function &offsetGet(mixed $offset): mixed
    {
        // avoid returning readonly props by ref as this is illegal
        $copy = $this->{$offset};
        return $copy;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->{$offset});
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Unsupported operation');
    }
}
