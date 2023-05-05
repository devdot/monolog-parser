<?php

namespace Devdot\Monolog;

/**
 * Structure for holding the properties of a single log record.
 * @implements \ArrayAccess<string, mixed>
 * @author Thomas Kuschan
 * @copyright (c) 2023
 */
class LogRecord implements \ArrayAccess {
    /**
     * Create a LogRecord to hold a single log entry.
     * @param \DateTimeImmutable $datetime
     * @param string $channel
     * @param string $level
     * @param string $message
     * @param array<int, mixed>|\stdClass|string|\NULL $context
     * @param array<int, mixed>|\stdClass|string|\NULL $extra
     */
    public function __construct(
        public readonly \DateTimeImmutable $datetime,
        public readonly string $channel,
        public readonly string $level,
        public readonly string $message,
        public readonly array|\stdClass|string|NULL $context = [],
        public readonly array|\stdClass|string|NULL $extra = [],
    ) {
    }

    public function offsetSet(mixed $offset, mixed $value): void {
        // we do not support setting in any way
        throw new \LogicException('Unsupported operation');
    }

    public function &offsetGet(mixed $offset): mixed {
        // avoid returning readonly props by ref as this is illegal
        $copy = $this->{$offset};
        return $copy;
    }

    public function offsetExists(mixed $offset): bool {
        return isset($this->{$offset});
    }

    public function offsetUnset(mixed $offset): void {
        throw new \LogicException('Unsupported operation');
    }
}
