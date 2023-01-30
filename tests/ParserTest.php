<?php

use Devdot\Monolog\Exceptions\FileNotFoundException;
use PHPUnit\Framework\TestCase;
use Devdot\Monolog\Parser;

final class ParserTest extends TestCase {
    protected $files = [
        __DIR__.'/files/test.log',
    ];

    protected $invalidFiles = [
        __DIR__.'/file.log',
        __DIR__.'/asdf',
    ];

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
}
