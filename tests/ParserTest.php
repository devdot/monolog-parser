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
        __DIR__.'/files/emergency.log',
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

    public function testGetAll() {
        // simply test if any of our testfiles can be parsed without exceptions
        foreach($this->files as $file) {
            $parser = new Parser($file);
            $this->assertTrue($parser->isReady(), 'File '.$file.' is not ready!');
            $records = $parser->get();
            $this->assertIsArray($records, 'Parsing results from '.$file.' are not an array!');
        }
    }

    public function testGetEmergencyLog() {
        // validate with the emergency.log file
        $parser = new Parser($this->files[1]);
        $parser->setPattern(Parser::PATTERN_LARAVEL);
        $records = $parser->get();
        $this->assertCount(4, $records);
        $this->assertEquals('laravel', $records[0]['channel']);
        $this->assertEquals('EMERGENCY', $records[0]['level']);
        $this->assertEquals('2023-01-26 17:21:36', $records[0]['datetime']->format('Y-m-d H:i:s'));
        $this->assertEquals('Unable to create configured logger. Using emergency logger.', $records[0]['message']);
        $this->assertIsObject($records[0]['context']);
        $this->assertIsString($records[0]['context']->exception);
        $this->assertEquals(
             '[object] (InvalidArgumentException(code: 0): Log [nono] is not defined. at /mnt/d/projects/laravel/vendor/laravel/framework/src/Illuminate/Log/LogManager.php:210)'.PHP_EOL
            .'[stacktrace]'.PHP_EOL
            .'#0 /mnt/d/projects/laravel/vendor/laravel/framework/src/Illuminate/Log/LogManager.php(135): Illuminate\\Log\\LogManager->resolve()'.PHP_EOL
            .'#1 /mnt/d/projects/laravel/vendor/laravel/framework/src/Illuminate/Log/LogManager.php(122): Illuminate\\Log\\LogManager->get()'.PHP_EOL
            .'#2 /mnt/d/projects/laravel/vendor/laravel/framework/src/Illuminate/Log/LogManager.php(645): Illuminate\\Log\\LogManager->driver()'.PHP_EOL
            .'#3 /mnt/d/projects/laravel/vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php(274): Illuminate\\Log\\LogManager->error()'.PHP_EOL
            .'#4 /mnt/d/projects/laravel/vendor/nunomaduro/collision/src/Adapters/Laravel/ExceptionHandler.php(46): Illuminate\\Foundation\\Exceptions\\Handler->report()'.PHP_EOL
            .'#5 /mnt/d/projects/laravel/vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php(454): NunoMaduro\\Collision\\Adapters\\Laravel\\ExceptionHandler->report()'.PHP_EOL
            .'#6 /mnt/d/projects/laravel/vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php(157): Illuminate\\Foundation\\Console\\Kernel->reportException()'.PHP_EOL
            .'#7 /mnt/d/projects/laravel/artisan(37): Illuminate\\Foundation\\Console\\Kernel->handle()'.PHP_EOL
            .'#8 {main}'.PHP_EOL
            , $records[0]['context']->exception
        );
        $this->assertCount(0, $records[0]['extra']);
        $this->assertEquals('laravel', $records[1]['channel']);
        $this->assertEquals('ERROR', $records[1]['level']);
        $this->assertEquals('17:21:36 26.01.2023', $records[1]['datetime']->format('H:i:s d.m.Y'));
        $this->assertEquals('Trying to access array offset on value of type null', $records[1]['message']);
        $this->assertIsObject($records[1]['context']);
        $this->assertIsString($records[1]['context']->exception);
        $this->assertEquals(
             '[object] (ErrorException(code: 0): Trying to access array offset on value of type null at /mnt/d/projects/components/log-artisan/src/Commands/ShowLog.php:202)'.PHP_EOL
            .'[stacktrace]'.PHP_EOL
            .'#0 /mnt/d/projects/laravel/vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php(266): Illuminate\\Foundation\\Bootstrap\\HandleExceptions->handleError()'.PHP_EOL
            .'#1 /mnt/d/projects/components/log-artisan/src/Commands/ShowLog.php(202): Illuminate\\Foundation\\Bootstrap\\HandleExceptions->Illuminate\\Foundation\\Bootstrap\\{closure}()'.PHP_EOL
            .'#2 /mnt/d/projects/components/log-artisan/src/Commands/ShowLog.php(84): Devdot\\LogArtisan\\Commands\\ShowLog->getFilesFromChannel()'.PHP_EOL
            .'#3 /mnt/d/projects/laravel/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): Devdot\\LogArtisan\\Commands\\ShowLog->handle()'.PHP_EOL
            .'#4 /mnt/d/projects/laravel/vendor/laravel/framework/src/Illuminate/Container/Util.php(41): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()'.PHP_EOL
            .'#5 /mnt/d/projects/laravel/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(93): Illuminate\\Container\\Util::unwrapIfClosure()'.PHP_EOL
            .'#6 /mnt/d/projects/laravel/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(37): Illuminate\\Container\\BoundMethod::callBoundMethod()'.PHP_EOL
            .'#7 /mnt/d/projects/laravel/vendor/laravel/framework/src/Illuminate/Container/Container.php(663): Illuminate\\Container\\BoundMethod::call()'.PHP_EOL
            .'#8 /mnt/d/projects/laravel/vendor/laravel/framework/src/Illuminate/Console/Command.php(182): Illuminate\\Container\\Container->call()'.PHP_EOL
            .'#9 /mnt/d/projects/laravel/vendor/symfony/console/Command/Command.php(312): Illuminate\\Console\\Command->execute()'.PHP_EOL
            .'#10 /mnt/d/projects/laravel/vendor/laravel/framework/src/Illuminate/Console/Command.php(152): Symfony\\Component\\Console\\Command\\Command->run()'.PHP_EOL
            .'#11 /mnt/d/projects/laravel/vendor/symfony/console/Application.php(1022): Illuminate\\Console\\Command->run()'.PHP_EOL
            .'#12 /mnt/d/projects/laravel/vendor/symfony/console/Application.php(314): Symfony\\Component\\Console\\Application->doRunCommand()'.PHP_EOL
            .'#13 /mnt/d/projects/laravel/vendor/symfony/console/Application.php(168): Symfony\\Component\\Console\\Application->doRun()'.PHP_EOL
            .'#14 /mnt/d/projects/laravel/vendor/laravel/framework/src/Illuminate/Console/Application.php(102): Symfony\\Component\\Console\\Application->run()'.PHP_EOL
            .'#15 /mnt/d/projects/laravel/vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php(155): Illuminate\\Console\\Application->run()'.PHP_EOL
            .'#16 /mnt/d/projects/laravel/artisan(37): Illuminate\\Foundation\\Console\\Kernel->handle()'.PHP_EOL
            .'#17 {main}'.PHP_EOL
            , $records[1]['context']->exception
        );
        $this->assertCount(0, $records[1]['extra']);
        $this->assertEquals('laravel', $records[2]['channel']);
        $this->assertEquals('EMERGENCY', $records[2]['level']);
        $this->assertEquals('2023-01-26 17:21:52', $records[2]['datetime']->format('Y-m-d H:i:s'));
        $this->assertEquals('Unable to create configured logger. Using emergency logger.', $records[2]['message']);
        $this->assertIsObject($records[2]['context']);
        $this->assertIsString($records[2]['context']->exception);
        $this->assertCount(0, $records[2]['extra']);
        $this->assertEquals('laravel', $records[3]['channel']);
        $this->assertEquals('ERROR', $records[3]['level']);
        $this->assertEquals('17:21:52 26.01.2023', $records[3]['datetime']->format('H:i:s d.m.Y'));
        $this->assertEquals('Undefined array key "driver"', $records[3]['message']);
        $this->assertIsObject($records[3]['context']);
        $this->assertIsString($records[3]['context']->exception);
        $this->assertCount(0, $records[3]['extra']);
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

        // push second message
        $logger->warning('test');

        // confirm the file was created
        $this->assertFileExists($this->tempFile, 'Temporary file could not be created by Monolog');

        // now read the file with a parser
        $parser = new Parser($this->tempFile);
        $this->assertTrue($parser->isReady());
        $records = $parser->get();
        $this->assertCount(2, $records);
        
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
