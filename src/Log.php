<?php

namespace Devdot\Monolog;

/**
 * Structure that holds multiple LogRecords like a readonly array.
 * @extends \ArrayIterator<int, LogRecord>
 * @implements \ArrayAccess<int, LogRecord>
 * @author Thomas Kuschan
 * @copyright (c) 2023
 */
class Log extends \ArrayIterator implements \ArrayAccess
{
    /**
     * Create a Log from a group of records
     * @param LogRecord ...$records One or more log entries
     * @no-named-arguments This will disallow named parameters and force $records to array<int, LogRecord>
     */
    public function __construct(LogRecord ...$records)
    {
        parent::__construct($records);
    }

    /**
     * Sort the logs like an array by the datetime of the LogRecords it contains
     * @param bool $ascending Sorting order, defaults to false (meaning it sorts descending)
     * @return void
     */
    public function sortByDatetime(bool $ascending = false): void
    {
        if (count($this) == 0) {
            return;
        }

        // get the stored elements and rebuild the log
        $array = $this->getArrayCopy();

        // using the php sort algorithm, sort this
        // sort DESCending (newest to oldest datetime)
        usort($array, fn(LogRecord $a, LogRecord $b): int => (int) ($b->datetime->format('U') - $a->datetime->format('U')));

        // and if we sort ascending, simply reverse the array
        if ($ascending) {
            $array = array_reverse($array);
        }

        // finally set this array as the new content for us
        parent::__construct($array);
    }

    public function current(): LogRecord
    {
        return parent::current();
    }

    /**
     * Get a LogRecord
     * @param int $offset Index (int)
     * @throws \OutOfBoundsException When the offset is not defined
     * @return LogRecord
     */
    public function offsetGet(mixed $offset): LogRecord
    {
        if (parent::offsetExists($offset)) {
            $offset = parent::offsetGet($offset);
            if ($offset !== null) {
                return $offset;
            }
        }
        // exit with exception
        throw new \OutOfBoundsException('Undefined array key ' . $offset);
    }

    /**
     * Not supported, readonly!
     * @param int $offset
     * @param LogRecord $value
     * @throws \LogicException Always
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        // we do not support setting in any way
        throw new \LogicException('Unsupported operation');
    }

    /**
     * Not supported, readonly!
     * @param int $offset
     * @throws \LogicException Always
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Unsupported operation');
    }
}
