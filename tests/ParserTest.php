<?php

use Devdot\Monolog\Exceptions\FileNotFoundException;
use Devdot\Monolog\Exceptions\ParserNotReadyException;
use PHPUnit\Framework\TestCase;
use Devdot\Monolog\Parser;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

final class ParserTest extends TestCase {
    protected $files = [
        __DIR__.'/files/test.log',
    ];

    protected $invalidFiles = [
        __DIR__.'/file.log',
        __DIR__.'/asdf',
    ];

    protected $tempFile = __DIR__.'/files/__test.tmp.log';

    public function testConstruct() {
        // normal construction
        $parser = new Parser();
        $this->assertInstanceOf(Parser::class, $parser);
        
        // with valid filename
        $parser = new Parser($this->files[0]);
        $this->assertInstanceOf(Parser::class, $parser);
        $this->assertTrue($parser->isReady(), 'Testfile '.$this->files[0].' is not ready!');


        // with invalid filename
        $this->expectException(FileNotFoundException::class);
        $parser = new Parser($this->invalidFiles[0]);
    }

    public function testIsReady() {
        // test false on no filename
        $this->assertFalse((new Parser())->isReady());
        // test valid files
        foreach($this->files as $filename) {
            $this->assertTrue((new Parser($filename))->isReady(), 'Testfile '.$filename.' is not ready!');
            $this->assertTrue((new Parser())->setFilename($filename)->isReady(), 'Testfile '.$filename.' is not ready!');
        }
        // test invalid files
        foreach($this->invalidFiles as $filename) {
            $parser = new Parser();
            try {
                $parser->setFilename($filename);
            }
            catch(FileNotFoundException $e) {
            }
            $this->assertFalse($parser->isReady(), 'Testfile '.$filename.' is ready!');
        }
    }

    public function testGetNotReady() {
        $this->expectException(ParserNotReadyException::class);
        $parser = new Parser();
        $parser->get();
    }

    public function testGetDynamic() {
        // run a test with dynamic file generation and parsing
        $channel = 'logtesting';
        $message = 'Test-Message';
        $context = ['apple', 'banana', 'orange'];


        // remove the file
        if(file_exists($this->tempFile))
            unlink($this->tempFile);  
        $this->assertFileDoesNotExist($this->tempFile, 'Temporary file exists from previous test run and could not be deleted!');

        // create a new logger
        $logger = new Logger($channel);
        $logger->pushHandler(new StreamHandler($this->tempFile, Logger::WARNING));

        // push message
        $timeBefore = time();
        $logger->error($message, $context);
        $timeAfter = time();

        // confirm the file was created
        $this->assertFileExists($this->tempFile, 'Temporary file could not be created by Monolog');

        // now read the file with a parser
        $parser = new Parser($this->tempFile);
        $this->assertTrue($parser->isReady());
        $records = $parser->get();
        $this->assertCount(1, $records);
        
        // confirm the entire record
        $record = $records[0];
        $this->assertGreaterThanOrEqual($timeBefore, $record['datetime']->format('U'));
        $this->assertLessThanOrEqual($timeAfter, $record['datetime']->format('U'));
        $this->assertEquals($channel, $record['channel']);
        $this->assertEquals($message, $record['message']);
        $this->assertCount(count($context), $record['context']);
        $this->assertIsArray($record['context']);
        $this->assertSame($context, $record['context']);

        // remove the temp file
        unlink($this->tempFile);  
        $this->assertFileDoesNotExist($this->tempFile, 'Temporary file could not be deleted!');
    }
}
