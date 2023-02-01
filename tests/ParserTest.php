<?php

use Devdot\Monolog\Exceptions\FileNotFoundException;
use Devdot\Monolog\Exceptions\ParserNotReadyException;
use PHPUnit\Framework\TestCase;
use Devdot\Monolog\Parser;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

final class ParserTest extends TestCase {
    protected $files = [
        'test' => __DIR__.'/files/test.log',
        'laravel-emergency' => __DIR__.'/files/emergency.log',
        'ddtraceweb' =>__DIR__.'/files/ddtraceweb-monolog-parser-test.log',
        'laravel' => __DIR__.'/files/laravel.log',
        'datetime' => __DIR__.'/files/datetime.log',
        'datetime-laravel' => __DIR__.'/files/datetime-laravel.log',
        'monolog2' => __DIR__.'/files/monolog2.log',
    ];

    protected $invalidFiles = [
        __DIR__.'/file.log',
        __DIR__.'/asdf',
    ];

    protected $tempFile = __DIR__.'/files/__test.tmp.log';

    public function assertLogRecords(array $records, array $dates, array $channels, array $levels, array $messages, array $contexts, array $extras, string $errorMsg = 'Error validating log record %s: %s failed') {
        foreach($records as $key => $record) {
            $this->assertSame($dates[$key], $record['datetime']->format('Y-m-d'), sprintf($errorMsg, $key, 'datetime'));
            $this->assertSame($channels[$key], $record['channel'], sprintf($errorMsg, $key, 'channel'));
            $this->assertSame($levels[$key], $record['level'], sprintf($errorMsg, $key, 'level'));
            $this->assertSame($messages[$key], $record['message'], sprintf($errorMsg, $key, 'message'));
            switch($contexts[$key]) {
                case 'array':
                    $this->assertIsArray($record['context'], sprintf($errorMsg, $key, 'context not array'));
                    break;
                case 'object':
                    $this->assertIsObject($record['context'], sprintf($errorMsg, $key, 'context not object'));
                    break;
                case null:
                    $this->assertIsArray($record['context'], sprintf($errorMsg, $key, 'context not array'));
                    $this->assertEmpty($record['context'], sprintf($errorMsg, $key, 'context not empty'));
                    break;
            }
            switch($extras[$key]) {
                case 'array':
                    $this->assertIsArray($record['extra'], sprintf($errorMsg, $key, 'extra not array'));
                    break;
                case 'object':
                    $this->assertIsObject($record['extra'], sprintf($errorMsg, $key, 'extra not object'));
                    break;
                case null:
                    $this->assertIsArray($record['extra'], sprintf($errorMsg, $key, 'extra not array'));
                    $this->assertEmpty($record['extra'], sprintf($errorMsg, $key, 'extra not empty'));
                    break;
            }
        }
    }

    public function testConstruct() {
        // normal construction
        $parser = new Parser();
        $this->assertInstanceOf(Parser::class, $parser);
        
        // with valid filename
        $parser = new Parser($this->files['test']);
        $this->assertInstanceOf(Parser::class, $parser);
        $this->assertTrue($parser->isReady(), 'Testfile '.$this->files['test'].' is not ready!');


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
        $parser = new Parser($this->files['laravel-emergency']);
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

    public function testGetContextWithDatetime() {
        // testcase where the context contains a datetime string just like the main string
        $parser = new Parser($this->files['datetime']);
        $records = $parser->get();

        // check if all went well
        $datetime = '2023-01-01 02:03:04';
        $this->assertCount(3, $records);
        $this->assertIsObject($records[0]['context']);
        $this->assertObjectHasAttribute('date', $records[0]['context']);
        $this->assertEquals('['.$datetime.']', $records[0]['context']->date);
        $this->assertIsArray($records[1]['context']);
        $this->assertCount(1, $records[1]['context']);
        $this->assertEquals($datetime, $records[1]['context'][0]);
        $this->assertIsArray($records[2]['context']);
        $this->assertCount(1, $records[2]['context']);
        $this->assertEquals('['.$datetime.']', $records[2]['context'][0]);

        // some more general testing
        foreach($records as $record) {
            $this->assertEquals('2023-01-31 12:00:00', $record['datetime']->format('Y-m-d H:i:s'));
            $this->assertEquals('test', $record['channel']);
            $this->assertEquals('INFO', $record['level']);
            $this->assertEquals('datetime string in context', $record['message']);
            $this->assertIsArray($record['extra']);
            $this->assertCount(0, $record['extra']);
        }
    }

    public function testGetContextWithDatetimeLaravel() {
        // testcase where the context contains a datetime string just like the main string
        $parser = new Parser($this->files['datetime-laravel']);
        $parser->setPattern(Parser::PATTERN_LARAVEL);
        $records = $parser->get();

        // some general testing
        foreach($records as $record) {
            $this->assertEquals('2023-01-31 12:00:00', $record['datetime']->format('Y-m-d H:i:s'));
            $this->assertEquals('test', $record['channel']);
            $this->assertEquals('INFO', $record['level']);
            $this->assertCount(5, $records);
            $this->assertIsObject($record['context']);
            $this->assertObjectHasAttribute('date', $record['context']);
            $this->assertIsArray($record['extra']);
            $this->assertCount(0, $record['extra']);
        }

        // check if all went well
        $this->assertEquals('[2023-01-01 02:03:04]', $records[0]['context']->date);
        $this->assertEquals("\n[2023-01-01 02:03:04] more text\n", $records[1]['context']->date);
        $this->assertEquals("\n[2023-01-01 02:03:04] fail.ERROR: this is part of a string!\n", $records[2]['context']->date);
        $this->assertEquals('[2023-01-01 02:03:04]', $records[3]['context']->date);
        $this->assertEquals('2023-01-01 02:03:04', $records[4]['context']->date);
    }

    public function testGetDdtraceWebLog() {
        // using https://github.com/ddtraceweb/monolog-parser/blob/master/tests/files/test.log
        // load the file and parse it
        $records = (new Parser($this->files['ddtraceweb']))->get();

        $this->assertCount(8, $records);

        // manually check every detail of the log
        // LOG #0
        $record = $records[0];
        $this->assertInstanceOf(\DateTimeImmutable::class, $record['datetime']);
        $this->assertEquals('2013-08-15 14:19:51', $record['datetime']->format('Y-m-d H:i:s'));
        $this->assertEquals('test', $record['channel']);
        $this->assertEquals('INFO', $record['level']);
        $this->assertEquals('foobar', $record['message']);
        $this->assertIsObject($record['context']);
        $this->assertObjectHasAttribute('foo', $record['context']);
        $this->assertEquals('bar', $record['context']->foo);
        $this->assertIsArray($record['extra']);
        $this->assertCount(0, $record['extra']);

        // LOG #1
        $record = $records[1];
        $this->assertInstanceOf(\DateTimeImmutable::class, $record['datetime']);
        $this->assertEquals('2013-08-15 14:19:51', $record['datetime']->format('Y-m-d H:i:s'));
        $this->assertEquals('aha', $record['channel']);
        $this->assertEquals('DEBUG', $record['level']);
        $this->assertEquals('foobar', $record['message']);
        $this->assertIsArray($record['context']);
        $this->assertCount(0, $record['context']);
        $this->assertIsArray($record['extra']);
        $this->assertCount(0, $record['extra']);

        // LOG #2
        $record = $records[2];
        $this->assertInstanceOf(\DateTimeImmutable::class, $record['datetime']);
        $this->assertEquals('2013-08-15 14:19:51', $record['datetime']->format('Y-m-d H:i:s'));
        $this->assertEquals('context', $record['channel']);
        $this->assertEquals('INFO', $record['level']);
        $this->assertEquals('multicontext', $record['message']);
        $this->assertIsArray($record['context']);
        $this->assertCount(2, $record['context']);
        $this->assertIsObject($record['context'][0]);
        $this->assertObjectHasAttribute('foo', $record['context'][0]);
        $this->assertEquals('bar', $record['context'][0]->foo);
        $this->assertIsObject($record['context'][1]);
        $this->assertObjectHasAttribute('bat', $record['context'][1]);
        $this->assertEquals('baz', $record['context'][1]->bat);
        $this->assertIsArray($record['extra']);
        $this->assertCount(0, $record['extra']);

        // LOG #3
        $record = $records[3];
        $this->assertInstanceOf(\DateTimeImmutable::class, $record['datetime']);
        $this->assertEquals('2013-08-15 14:19:51', $record['datetime']->format('Y-m-d H:i:s'));
        $this->assertEquals('context', $record['channel']);
        $this->assertEquals('INFO', $record['level']);
        $this->assertEquals('multicontext', $record['message']);
        $this->assertIsArray($record['context']);
        $this->assertCount(2, $record['context']);
        $this->assertIsObject($record['context'][0]);
        $this->assertObjectHasAttribute('foo', $record['context'][0]);
        $this->assertEquals('bar', $record['context'][0]->foo);
        $this->assertObjectHasAttribute('stuff', $record['context'][0]);
        $this->assertEquals('and things', $record['context'][0]->stuff);
        $this->assertIsObject($record['context'][1]);
        $this->assertObjectHasAttribute('bat', $record['context'][1]);
        $this->assertEquals('baz', $record['context'][1]->bat);
        $this->assertIsArray($record['extra']);
        $this->assertCount(0, $record['extra']);

        // LOG #4
        $record = $records[4];
        $this->assertInstanceOf(\DateTimeImmutable::class, $record['datetime']);
        $this->assertEquals('2013-08-15 14:19:51', $record['datetime']->format('Y-m-d H:i:s'));
        $this->assertEquals('context', $record['channel']);
        $this->assertEquals('INFO', $record['level']);
        $this->assertEquals('multicontext with empty', $record['message']);
        $this->assertIsArray($record['context']);
        $this->assertCount(2, $record['context']);
        $this->assertIsObject($record['context'][0]);
        $this->assertObjectHasAttribute('foo', $record['context'][0]);
        $this->assertEquals('bar', $record['context'][0]->foo);
        $this->assertObjectHasAttribute('stuff', $record['context'][0]);
        $this->assertEquals('and things', $record['context'][0]->stuff);
        $this->assertIsArray($record['context'][1]);
        $this->assertCount(0, $record['context'][1]);
        $this->assertIsArray($record['extra']);
        $this->assertCount(0, $record['extra']);

        // LOG #5
        $record = $records[5];
        $this->assertInstanceOf(\DateTimeImmutable::class, $record['datetime']);
        $this->assertEquals('2013-08-15 14:19:51', $record['datetime']->format('Y-m-d H:i:s'));
        $this->assertEquals('context', $record['channel']);
        $this->assertEquals('INFO', $record['level']);
        $this->assertEquals('multicontext with spaces', $record['message']);
        $this->assertIsArray($record['context']);
        $this->assertCount(2, $record['context']);
        $this->assertIsObject($record['context'][0]);
        $this->assertObjectHasAttribute('foo', $record['context'][0]);
        $this->assertEquals('bar', $record['context'][0]->foo);
        $this->assertObjectHasAttribute('stuff', $record['context'][0]);
        $this->assertEquals('and things', $record['context'][0]->stuff);
        $this->assertIsObject($record['context'][1]);
        $this->assertObjectHasAttribute('bat', $record['context'][1]);
        $this->assertEquals('baz', $record['context'][1]->bat);
        $this->assertIsArray($record['extra']);
        $this->assertCount(0, $record['extra']);

        // LOG #6
        $record = $records[6];
        $this->assertInstanceOf(\DateTimeImmutable::class, $record['datetime']);
        $this->assertEquals('2013-08-15 14:19:51', $record['datetime']->format('Y-m-d H:i:s'));
        $this->assertEquals('extra', $record['channel']);
        $this->assertEquals('INFO', $record['level']);
        $this->assertEquals('context and extra', $record['message']);
        $this->assertIsArray($record['context']);
        $this->assertCount(2, $record['context']);
        $this->assertIsObject($record['context'][0]);
        $this->assertObjectHasAttribute('foo', $record['context'][0]);
        $this->assertEquals('bar', $record['context'][0]->foo);
        $this->assertObjectHasAttribute('stuff', $record['context'][0]);
        $this->assertEquals('and things', $record['context'][0]->stuff);
        $this->assertIsObject($record['context'][1]);
        $this->assertObjectHasAttribute('bat', $record['context'][1]);
        $this->assertEquals('baz', $record['context'][1]->bat);
        $this->assertIsArray($record['extra']);
        $this->assertCount(2, $record['extra']);
        $this->assertIsObject($record['extra'][0]);
        $this->assertObjectHasAttribute('weebl', $record['extra'][0]);
        $this->assertEquals('bob', $record['extra'][0]->weebl);
        $this->assertIsObject($record['extra'][1]);
        $this->assertObjectHasAttribute('lobob', $record['extra'][1]);
        $this->assertEquals('lo', $record['extra'][1]->lobob);

        // LOG #7
        $record = $records[7];
        $this->assertInstanceOf(\DateTimeImmutable::class, $record['datetime']);
        $this->assertEquals('2023-01-31 12:00:00', $record['datetime']->format('Y-m-d H:i:s'));
        $this->assertEquals('test', $record['channel']);
        $this->assertEquals('INFO', $record['level']);
        $this->assertEquals('extra as object', $record['message']);
        $this->assertIsArray($record['context']);
        $this->assertCount(0, $record['context']);
        $this->assertIsObject($record['extra']);
        $this->assertObjectHasAttribute('test', $record['extra']);
        $this->assertIsBool($record['extra']->test);
        $this->assertTrue($record['extra']->test);
    }

    public function testGetLaravel() {
        // using the real log file laravel.log
        $parser = new Parser($this->files['laravel']);
        $parser->setPattern(Parser::PATTERN_LARAVEL);
        $records = $parser->get();

        // check for the right amount of log entries
        $count = 66;
        $this->assertCount($count, $records);

        // build the testing reference
        $datetimeMin = strtotime('2023-01-26 10:38:04');
        $datetimeMax = strtotime('2023-01-30 11:50:35');
        $referenceChannel = array_fill(0, $count, 'local'); // all channels are 'local'
        $referenceLevel = array_fill(0, $count, 'ERROR'); // set this default
        // now overwrite the exceptions
        $exceptionKeys = [11, 12, 14, 15, 17, 18, 20, 21, 23, 24, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 38, 39, 42, 43];
        array_walk($referenceChannel, fn(&$value, $key) => ($value = in_array($key, $exceptionKeys) ? 'laravel' : $value));
        array_walk($referenceLevel, fn(&$value, $key) => ($value = in_array($key, $exceptionKeys) ? 'EMERGENCY' : $value));
        $referenceMessage = [
            0 => 'Database file at path [laravel] does not exist. Ensure this is an absolute path to the database. (SQL: PRAGMA foreign_keys = ON;)',
            2 => 'Database file at path [atabase/db.sqlite] does not exist. Ensure this is an absolute path to the database. (SQL: PRAGMA foreign_keys = ON;)',
            10 => 'htmlspecialchars(): Argument #1 ($string) must be of type string, array given',
            12 => 'Unable to create configured logger. Using emergency logger.',
            13 => 'Class "Devdot\LogArtisan\Commands\Storage" not found',
            19 => 'Unable to retrieve the last_modified for file at location: mnt/d/projects/laravel/storage/logs/laravel.log.',
            25 => 'Undefined variable $level',
            27 => 'test',
            36 => 'foreach() argument must be of type array|object, null given',
            37 => 'Undefined array key "path"',
            46 => "Command \"log:test\" is not defined.\n\nDid you mean one of these?\n    log\n    log:show\n    make:test\n    schedule:test", 
            48 => 'syntax error, unexpected token "return"',
            49 => 'Using $this when not in object context',
            65 => 'Class "Dubture\Monolog\Reader\LogReader" not found',
        ];
        $referenceContextObject = array_fill(0, $count, true);
        $referenceContextObject[27] = false;
        $referenceContextObject[29] = false;
        $referenceContextObject[31] = false;
        $referenceContextExceptionLength = [
            0 => 11917-215, // the string contains 11917 characters, but that includes 215 escaped backslashes \\ which will not remain in the string after it is parsed
            1 => 11397-191,
            4 => 11939-215,
            5 => 832-18-2, // 18 \\ and 2 \"
            59 => 1448-22-2,
        ];

        // run through all records and compare them to reference
        foreach($records as $key => $record) {
            $msg = 'Log #'.$key.': ';
            $this->assertInstanceOf(\DateTimeImmutable::class, $record['datetime'], $msg.'DateTime wrong class');
            $this->assertGreaterThanOrEqual($datetimeMin, $record['datetime']->format('U'), $msg.'DateTime date old');
            $this->assertLessThanOrEqual($datetimeMax, $record['datetime']->format('U'), $msg.'DateTime date new');
            $this->assertEquals($referenceChannel[$key], $record['channel'], $msg.'channel wrong');
            $this->assertEquals($referenceLevel[$key], $record['level'], $msg.'level wrong');
            
            // decide how to assert the message
            if(array_key_exists($key, $referenceMessage))
                $this->assertEquals($referenceMessage[$key], $record['message'], $msg.'wrong message!');

            if($referenceContextObject[$key]) {
                $this->assertIsObject($record['context'], $msg.'context is no object');
                $this->assertObjectHasAttribute('exception', $record['context']);
            }
            else {
                $this->assertIsNotObject($record['context'], $msg.'context is object');
            }

            // check strlen if it is given
            if(array_key_exists($key, $referenceContextExceptionLength)) {
                $this->assertEquals($referenceContextExceptionLength[$key], strlen($record['context']->exception), $msg.'exception length mismatch');
            }
        }
    }

    public function testGetMonolog2() {
        // $this->buildMonolog2();

        $parser = new Parser($this->files['monolog2']);
        $records = $parser->get();

        // check if this did work as it should with the default log Monolog2 Pattern
        // $this->assertCount(16, $records); 
        $this->assertCount(11, $records); // the default pattern can only find 11 out of 16 because it does not do multiline matching
        
        // check through the found records
        $dates = array_fill(0, 11, '2023-01-31');
        $channels = ['log', 'meh', 'meh', 'meh', 'meh', 'core', 'core', 'core', 'core', 'argh', 'argh'];
        $levels = ['WARNING', 'ERROR', 'ERROR', 'ERROR', 'ERROR', 'CRITICAL', 'CRITICAL', 'INFO', 'INFO', 'ERROR', 'ERROR'];
        $messages = ['foo', 'foo', 'log', 'log', 'foobar', 'foobar', 'foobar', 'foo bar', 'foo', 'dip', 'log'];
        $contexts = ['array', 'object', 'array', null, 'array', 'object', 'object', 'array', null, 'object', 'array'];
        $extras = ['array', 'array', 'object', null, 'object', 'array', 'array', 'array', null, null, null];

        $this->assertLogRecords($records, $dates, $channels, $levels, $messages, $contexts, $extras);

        // manually confirm more details
        $this->assertEmpty($records[0]['context']);
        $this->assertEmpty($records[0]['extra']);
        $this->assertSame('{"foo":"bar","baz":"qux","bool":false,"null":null}', json_encode($records[1]['context']));
        $this->assertEmpty($records[1]['extra']);
        $this->assertEmpty($records[2]['context']);
        $this->assertObjectHasAttribute('ip', $records[2]['extra']);
        $this->assertSame('127.0.0.1', $records[2]['extra']->ip);
        $this->assertEmpty($records[3]['context']);
        $this->assertEmpty($records[3]['extra']);
        $this->assertEmpty($records[4]['context']);
        $this->assertSame('{"foo":{"stdClass":[]},"bar":{"Monolog\\\\Logger":[]},"baz":[],"res":"[resource(stream)]"}', json_encode($records[4]['extra']));
        $this->assertSame('{"exception":"[object] (RuntimeException(code: 0): Foo at \/mnt\/d\/projects\/components\/monolog-parser\/tests\/ParserTest.php:568)"}', json_encode($records[5]['context']));
        $this->assertEmpty($records[5]['extra']);
        $this->assertSame('{"exception":"[object] (RuntimeException(code: 0): Foo at \/mnt\/d\/projects\/components\/monolog-parser\/tests\/ParserTest.php:642)\n[previous exception] [object] (LogicException(code: 0): Wut? at \/mnt\/d\/projects\/components\/monolog-parser\/tests\/ParserTest.php:638)"}', json_encode($records[6]['context']));
        $this->assertEmpty($records[6]['extra']);
        $this->assertEmpty($records[7]['context']);
        $this->assertEmpty($records[7]['extra']);
        $this->assertEmpty($records[8]['context']);
        $this->assertEmpty($records[8]['extra']);    
        $this->assertObjectHasAttribute('foo', $records[9]['context']);
        $this->assertSame('bar', $records[9]['context']->foo);
        $this->assertCount(2, $records[10]['context']);
    }

    private function buildMonolog2() {
        // this function was used to build the monolog2 test case
        // using testcases from https://github.com/Seldaek/monolog/blob/2.x/tests/Monolog/Formatter/LineFormatterTest.php
        $stream = new StreamHandler($this->files['monolog2']);

        // testDefFormatWithString
        $this->buildMonolog2Helper($stream, new LineFormatter(null, 'Y-m-d'), [
            'level_name' => 'WARNING',
            'channel' => 'log',
            'context' => [],
            'message' => 'foo',
            'datetime' => new \DateTimeImmutable,
            'extra' => [],
        ]);
        // testDefFormatWithArrayContext
        $this->buildMonolog2Helper($stream, new LineFormatter(null, 'Y-m-d'), [
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'message' => 'foo',
            'datetime' => new \DateTimeImmutable,
            'extra' => [],
            'context' => [
                'foo' => 'bar',
                'baz' => 'qux',
                'bool' => false,
                'null' => null,
            ],
        ]);
        // testDefFormatExtras
        $this->buildMonolog2Helper($stream, new LineFormatter(null, 'Y-m-d'), [
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => [],
            'datetime' => new \DateTimeImmutable,
            'extra' => ['ip' => '127.0.0.1'],
            'message' => 'log',
        ]);
        // testFormatExtras: skip (non-default pattern)
        // testContextAndExtraOptionallyNotShownIfEmpty
        $this->buildMonolog2Helper($stream, new LineFormatter(null, 'Y-m-d', false, true), [
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => [],
            'datetime' => new \DateTimeImmutable,
            'extra' => [],
            'message' => 'log',
        ]);
        // testContextAndExtraReplacement: skip
        // testDefFormatWithObject
        $this->buildMonolog2Helper($stream, new LineFormatter(null, 'Y-m-d'), [
            'level_name' => 'ERROR',
            'channel' => 'meh',
            'context' => [],
            'datetime' => new \DateTimeImmutable,
            'extra' => ['foo' => new \stdClass, 'bar' => new Logger('test'), 'baz' => [], 'res' => fopen('php://memory', 'rb')],
            'message' => 'foobar',
        ]);
        // testDefFormatWithException
        $this->buildMonolog2Helper($stream, new LineFormatter(null, 'Y-m-d'), [
            'level_name' => 'CRITICAL',
            'channel' => 'core',
            'context' => ['exception' => new \RuntimeException('Foo')],
            'datetime' => new \DateTimeImmutable,
            'extra' => [],
            'message' => 'foobar',
        ]);
        // testDefFormatWithExceptionAndStacktrace
        $formatter = new LineFormatter(null, 'Y-m-d');
        $formatter->includeStacktraces();
        $this->buildMonolog2Helper($stream, $formatter, [
            'level_name' => 'CRITICAL',
            'channel' => 'core',
            'context' => ['exception' => new \RuntimeException('Foo')],
            'datetime' => new \DateTimeImmutable,
            'extra' => [],
            'message' => 'foobar',
        ]);
        // testInlineLineBreaksRespectsEscapedBackslashes (customized)
        $formatter = new LineFormatter(null, 'Y-m-d');
        $formatter->allowInlineLineBreaks();
        $this->buildMonolog2Helper($stream, $formatter, [
            'level_name' => 'WARNING',
            'channel' => 'log',
            'context' => ["test" => "foo\nbar\\name-with-n"],
            'message' => 'foo',
            'datetime' => new \DateTimeImmutable,
            'extra' => [],
        ]);
        // testDefFormatWithExceptionAndStacktraceParserFull
        $formatter = new LineFormatter(null, 'Y-m-d');
        $formatter->includeStacktraces(true, function ($line) {
            return $line;
        });
        $this->buildMonolog2Helper($stream, $formatter, [
            'level_name' => 'CRITICAL',
            'channel' => 'core',
            'context' => ['exception' => new \RuntimeException('Foo')],
            'datetime' => new \DateTimeImmutable,
            'extra' => [],
            'message' => 'foobar',
        ]);
        // testDefFormatWithExceptionAndStacktraceParserCustom
        $formatter = new LineFormatter(null, 'Y-m-d');
        $formatter->includeStacktraces(true, function ($line) {
            if (strpos($line, 'TestCase.php') === false) {
                return $line;
            }
        });
        $this->buildMonolog2Helper($stream, $formatter, [
            'level_name' => 'CRITICAL',
            'channel' => 'core',
            'context' => ['exception' => new \RuntimeException('Foo')],
            'datetime' => new \DateTimeImmutable,
            'extra' => [],
            'message' => 'foobar',
        ]);
        // testDefFormatWithExceptionAndStacktraceParserEmpty
        $formatter = new LineFormatter(null, 'Y-m-d');
        $formatter->includeStacktraces(true, function ($line) {
            return null;
        });
        $this->buildMonolog2Helper($stream, $formatter, [
            'level_name' => 'CRITICAL',
            'channel' => 'core',
            'context' => ['exception' => new \RuntimeException('Foo')],
            'datetime' => new \DateTimeImmutable,
            'extra' => [],
            'message' => 'foobar',
        ]);
        // testDefFormatWithPreviousException
        $formatter = new LineFormatter(null, 'Y-m-d');
        $previous = new \LogicException('Wut?');
        $this->buildMonolog2Helper($stream, $formatter, [
            'level_name' => 'CRITICAL',
            'channel' => 'core',
            'context' => ['exception' => new \RuntimeException('Foo', 0, $previous)],
            'datetime' => new \DateTimeImmutable,
            'extra' => [],
            'message' => 'foobar',
        ]);
        // testDefFormatWithSoapFaultException: skip
        // testBatchFormat: skip
        // testFormatShouldStripInlineLineBreaks
        $this->buildMonolog2Helper($stream, new LineFormatter(null, 'Y-m-d'), [
            'level_name' => 'INFO',
            'message' => "foo\nbar",
            'context' => [],
            'extra' => [],
            'channel' => 'core',
            'datetime' => new \DateTimeImmutable,
        ]);
        // testFormatShouldNotStripInlineLineBreaksWhenFlagIsSet
        $this->buildMonolog2Helper($stream, new LineFormatter(null, 'Y-m-d', true), [
            'level_name' => 'INFO',
            'message' => "foo\nbar",
            'context' => [],
            'extra' => [],
            'channel' => 'core',
            'datetime' => new \DateTimeImmutable,
        ]);
        // custom test: optional context/extra with only context
        $this->buildMonolog2Helper($stream, new LineFormatter(null, 'Y-m-d', false, true), [
            'level_name' => 'ERROR',
            'channel' => 'argh',
            'context' => ['foo' => 'bar'],
            'datetime' => new \DateTimeImmutable,
            'extra' => [],
            'message' => 'dip',
        ]);
        // custom test: optional context/extra with only extra
        $this->buildMonolog2Helper($stream, new LineFormatter(null, 'Y-m-d', false, true), [
            'level_name' => 'ERROR',
            'channel' => 'argh',
            'context' => [],
            'datetime' => new \DateTimeImmutable,
            'extra' => ['foo', 'wizz'],
            'message' => 'log',
        ]);
    }

    private function buildMonolog2Helper(StreamHandler $stream, LineFormatter $formatter, array $message) {
        if(!isset($message['level'])) {
            $level = $message['level_name'];
            $message['level'] = Logger::getLevels()[$level];
        }
        $stream->setFormatter($formatter);
        $stream->handle($message);
    }
}
