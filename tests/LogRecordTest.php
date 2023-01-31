<?php

use PHPUnit\Framework\TestCase;
use Devdot\Monolog\LogRecord;

final class LogRecordTest extends TestCase {
    public function testConstruct() {
        $record = new LogRecord(
            new \DateTimeImmutable('2023-01-02 08:00:01'),
            'test-channel',
            'test-level',
            'test-message',
        );

        $this->assertInstanceOf(LogRecord::class, $record);
        $this->assertInstanceOf(\DateTimeImmutable::class, $record['datetime']);
        $this->assertEquals('2023-01-02 08:00:01', $record['datetime']->format('Y-m-d H:i:s'));
        $this->assertEquals('test-channel', $record['channel']);
        $this->assertEquals('test-level', $record['level']);
        $this->assertEquals('test-message', $record['message']);
        $this->assertIsArray($record['context']);
        $this->assertCount(0, $record['context']);
        $this->assertIsArray($record['extra']);
        $this->assertCount(0, $record['extra']);
    }

    public function testArrayAccess() {
        $record = new LogRecord(
            new \DateTimeImmutable('2023-01-02 08:00:01'),
            'test-channel',
            'test-level',
            'test-message',
        );

        // this is an object that can be accessed as an array
        $this->assertIsObject($record);
        $this->assertIsNotArray($record);

        // make sure we can read        
        $this->assertIsObject($record['datetime']);
        $this->assertIsString($record['channel']);
        
        // make sure we can't write or unset
        try {
            $record['level'] = 'test';
            throw new \Exception();
        }
        catch(\Exception $e) {
            $this->assertInstanceOf(\LogicException::class, $e);
        }
        try {
            unset($record['message']);
            throw new \Exception();
        }
        catch(\Exception $e) {
            $this->assertInstanceOf(\LogicException::class, $e);
        }
    }
}
