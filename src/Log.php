<?php

namespace Devdot\Monolog;

/**
 * Structure that holds multiple LogRecords like a readonly array.
 * @author Thomas Kuschan
 * @copyright (c) 2023
 */
class Log extends \ArrayIterator implements \ArrayAccess {
    public function __construct(LogRecord ...$records) {
        parent::__construct($records);
    }

    /**
     * Sort the logs like an array by the datetime of the LogRecords it contains
     * @param bool $ascending Sorting order, defaults to false (meaning it sorts descending)
     * @return void
     */
    public function sortByDatetime(bool $ascending = false): void {
        if (count($this) == 0)
            return;

        // get the stored elements and rebuild the log
        $array = $this->getArrayCopy();

        // using the php sort algorithm, sort this
        // sort DESCending (newest to oldest datetime)
        usort($array, fn(LogRecord $a, LogRecord $b) => $b->datetime->format('U') - $a->datetime->format('U'));

        // and if we sort ascending, simply reverse the array
        if ($ascending) {
            $array = array_reverse($array);
        }
        
        // finally set this array as the new content for us
        parent::__construct($array);
    }

    public function current(): LogRecord {
        return parent::current();
    }

    public function offsetGet($offset): LogRecord {
        if(parent::offsetExists($offset))
            return parent::offsetGet($offset);
        else
            throw new \OutOfBoundsException('Undefined array key '.$offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void {
        // we do not support setting in any way
        throw new \LogicException('Unsupported operation');
    }

    public function offsetUnset(mixed $offset): void {
        throw new \LogicException('Unsupported operation');
    }
}
