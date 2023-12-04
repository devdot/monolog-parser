<?php

use PHPUnit\Framework\TestCase;
use Devdot\Monolog\Log;
use Devdot\Monolog\LogRecord;

final class LogTest extends TestCase
{
    protected function makeRandomRecord(int $timestamp = null): LogRecord
    {
        return new LogRecord(
            (new \DateTimeImmutable())->setTimestamp($timestamp ?? rand(0, time())),
            'ch' . rand(0, 99),
            (string) array_rand(['INFO', 'DEBUG', 'ERROR', 'WARNING']),
            'message' . rand(0, 99),
            ['test' => true], // this is possible, but not recommended and won't happen with json_decode @phpstan-ignore-line
            (object) ['context' => false]
        );
    }

    protected function makeRandomLog(int $size = 2): Log
    {
        $records = [];
        for ($i = 0; $i < $size; $i++) {
            $records[] = $this->makeRandomRecord();
        }
        return new Log(...$records);
    }

    public function testMakeRandomRecordHelper()
    {
        $record = $this->makeRandomRecord();
        $this->assertInstanceOf(\DateTimeImmutable::class, $record->datetime);
        $this->assertIsString($record->channel);
        $this->assertIsString($record->level);
        $this->assertIsString($record->message);
        $this->assertIsArray($record->context);
        $this->assertCount(1, $record->context);
        $this->assertTrue($record['context']['test']);
        $this->assertIsObject($record->extra);
        $this->assertFalse($record->extra->context);
        $this->assertNotSame($this->makeRandomRecord(), $this->makeRandomRecord());
    }

    public function testMakeRandomLogHelper()
    {
        $this->assertCount(10, $this->makeRandomLog(10));
        $this->assertCount(1, $this->makeRandomLog(1));
        $this->assertInstanceOf(LogRecord::class, $this->makeRandomLog(1)[0]);
        $this->assertNotSame($this->makeRandomLog(), $this->makeRandomLog());
    }

    public function testConstruct()
    {
        $record0 = $this->makeRandomRecord();
        $record1 = $this->makeRandomRecord();
        $record2 = $this->makeRandomRecord();

        // simple create
        $log = new Log($record0, $record1, $record2);
        $this->assertCount(3, $log);

        // using array roll-out
        $log = new Log(...[$record0, $record1, $record2]);
        $this->assertCount(3, $log);

        // with just one element
        $this->assertCount(1, new Log($record0));
    }

    public function testConstructWithArrayException()
    {
        // try to pass an array into the constructor and it will fail
        $this->expectException(\TypeError::class);
        // this should fail, it's not how the constructor is supposed to work @phpstan-ignore-next-line
        $log = new Log([
            $this->makeRandomRecord(),
            $this->makeRandomRecord(),
            $this->makeRandomRecord(),
        ]);
    }

    public function testConstructWithNamedParameters()
    {
        // this is technically possible
        $log = new Log(
            $this->makeRandomRecord(),
            n: $this->makeRandomRecord(),
            test: $this->makeRandomRecord(),
        );
        $this->assertTrue($log->offsetExists('test')); // this is rightly complained about @phpstan-ignore-line
    }

    public function testObjectInterface()
    {
        // make a new log out of a handful of log records
        $record0 = $this->makeRandomRecord();
        $record1 = $this->makeRandomRecord();
        $record2 = $this->makeRandomRecord();

        // now lets make sure these records are actually not identical
        $this->assertNotSame($record0, $record1);
        $this->assertNotSame($record1, $record2);

        // and continue to make a log
        $log = new Log($record0, $record1, $record2);

        // check the count
        $this->assertEquals(3, count($log));
        $this->assertCount(3, $log);

        // check access
        $this->assertInstanceOf(LogRecord::class, $log[0]);
        $this->assertInstanceOf(LogRecord::class, $log[1]);
        $this->assertInstanceOf(LogRecord::class, $log[2]);

        // check not exists
        $this->assertTrue(isset($log[0]));
        $this->assertFalse(isset($log[4]));

        // and lets see if it returns identical objects
        $this->assertSame($record0, $log[0]);
        $this->assertSame($record1, $log[1]);
        $this->assertSame($record2, $log[2]);

        // and finally loop through it like through an array
        $c = 0;
        foreach ($log as $record) {
            $this->assertIsString($record->message);
            $c++;
        }
        $this->assertEquals(count($log), $c);
    }

    public function testSetException()
    {
        $log = $this->makeRandomLog(10);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unsupported operation');
        $log[3] = $this->makeRandomRecord();
    }

    public function testUnsetException()
    {
        $log = $this->makeRandomLog(10);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unsupported operation');
        unset($log[9]);
    }

    public function testGetOutOfBoundsError()
    {
        $log = $this->makeRandomLog(10);
        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessage('Undefined array key 10');
        $record = $log[10];
        $this->assertTrue($record->context['test']);
    }

    public function testAppendException()
    {
        $log = $this->makeRandomLog(10);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unsupported operation');
        $log[] = $this->makeRandomRecord(); // this will throw an exception @phpstan-ignore-line
    }

    public function testSortByDatetime()
    {
        // create an unsorted log
        $log = new Log(...[
            $this->makeRandomRecord(100),
            $this->makeRandomRecord(400),
            $this->makeRandomRecord(200),
            $this->makeRandomRecord(900),
            $this->makeRandomRecord(0),
        ]);
        // save the first object for reference
        $record0 = $log[0];
        $this->assertEquals(100, $record0->datetime->format('U'));

        // and sort it
        $log->sortByDatetime();
        $this->assertEquals(900, $log[0]->datetime->format('U'));
        $this->assertEquals(400, $log[1]->datetime->format('U'));
        $this->assertEquals(200, $log[2]->datetime->format('U'));
        $this->assertEquals(100, $log[3]->datetime->format('U'));
        $this->assertEquals(0, $log[4]->datetime->format('U'));

        // check the record0 moved correctly
        $this->assertNotSame($record0, $log[0]);
        $this->assertSame($record0, $log[3]);

        // resort and check again (should not change)
        $log->sortByDatetime();
        $this->assertSame($record0, $log[3]);

        // and sort ascending
        $log->sortByDatetime(true);
        $this->assertEquals(0, $log[0]->datetime->format('U'));
        $this->assertEquals(100, $log[1]->datetime->format('U'));
        $this->assertEquals(200, $log[2]->datetime->format('U'));
        $this->assertEquals(400, $log[3]->datetime->format('U'));
        $this->assertEquals(900, $log[4]->datetime->format('U'));

        $this->assertSame($record0, $log[1]);
    }

    public function testCastToArray()
    {
        $log = $this->makeRandomLog(2);
        $array = (array) $log;
        $this->assertCount(2, $log);
        $this->assertCount(2, $array);
        $this->assertInstanceOf(Log::class, $log);
        $this->assertIsArray($array);
        // compare full
        foreach ($log as $key => $record) {
            $this->assertSame($record, $array[$key]);
        }
    }
}
