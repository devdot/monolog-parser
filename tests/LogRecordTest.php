<?php

use PHPUnit\Framework\TestCase;
use Devdot\Monolog\LogRecord;

final class LogRecordTest extends TestCase
{
    public function testConstruct(): void
    {
        $record = new LogRecord(
            new \DateTimeImmutable('2023-01-02 08:00:01'),
            'test-channel',
            'test-level',
            'test-message',
        );

        $this->assertInstanceOf(LogRecord::class, $record);
        $this->assertInstanceOf(\DateTimeImmutable::class, $record->datetime);
        $this->assertEquals('2023-01-02 08:00:01', $record->datetime->format('Y-m-d H:i:s'));
        $this->assertEquals('test-channel', $record->channel);
        $this->assertEquals('test-level', $record->level);
        $this->assertEquals('test-message', $record->message);
        $this->assertIsArray($record->context);
        $this->assertCount(0, $record->context);
        $this->assertIsArray($record->extra);
        $this->assertCount(0, $record->extra);
    }

    public function testArrayAccess(): void
    {
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
        } catch (\Exception $e) {
            $this->assertInstanceOf(\LogicException::class, $e);
        }
        try {
            unset($record['message']);
            throw new \Exception();
        } catch (\Exception $e) {
            $this->assertInstanceOf(\LogicException::class, $e);
        }
    }

    public function testClassIsImmutable(): void
    {
        $date = new \DateTimeImmutable();
        $record = new LogRecord(
            $date,
            'channel',
            'level',
            'hey message',
        );
        $this->assertSame($date, $record->datetime);
        $this->assertSame('channel', $record->channel);
        $this->assertSame('level', $record->level);
        $this->assertSame('hey message', $record->message);
        $this->assertSame([], $record->context);
        $this->assertSame([], $record->extra);

        $this->assertReadonly($record, 'datetime', new \DateTimeImmutable());
        $this->assertReadonly($record, 'channel', '2');
        $this->assertReadonly($record, 'level', 'up');
        $this->assertReadonly($record, 'context', []);
        $this->assertReadonly($record, 'extra', []);
    }

    private function assertReadonly(LogRecord $record, string $property, mixed $value = null): void
    {
        try {
            $record->$property = $value;
        } catch (\Error $e) {
            if ($e->getMessage() === 'Cannot modify readonly property ' . $record::class . '::$' . $property) {
                return;
            }
        }
        $this->assertFalse(true, 'Property ' . $property . ' of ' . $record::class . ' is not readonly!');
    }
}
