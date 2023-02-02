Monolog Parser
==============

A library for parsing [monolog](https://github.com/Seldaek/monolog) logfiles.

This library is compatible with **Monolog 2** and provides parse options for multiline logs and [Laravel](https://laravel.com/) logfiles.

## Installation

Install the library using [composer](https://getcomposer.org/):

```bash
composer require devdot/monolog-parser
```

## Basic Usage

Example for parsing a logfile `test.log` using the default options:

```php
<?php
use Devdot\Monolog\Parser;

// create a new parser bound to the given file
$parser = new Parser('test.log');

// retrieve the log records and print them
$records = $parser->get();
foreach($records as $record) {
    printf('Logged %s at %s with message: %s',
        $record['level'],
        $record['datetime']->format('Y-m-d H:i:s'),
        $record['message'],
    );
}
```

You can create a new `Parser` object by using the static function `new` as well:

```php
<?php
use Devdot\Monolog\Parser;

$records = Parser::new('test.log')->get();

// ...
```

## Documentation

### Parser instance

Create new `Parser` instances:
```php
$parser = new Parser();
$parser = Parser::new(); // equivalent to new Parser()
```

For parameters, see [Method Reference](#method-reference).

Most methods return the `Parser` instance itself, allowing the calls to be chained in one line as seen in the exampled below.

Every `Parser` is meant to be linked to only one file, however that is not a hard restriction and depends on your usage.

### Files and Ready State

The parser has to be provided a filename (or a string, see [Parsing](#parsing)) in order to become ready to parse. You can set the filename in the [constructor](#parser-instance) or with `setFile`. Check, whether a parser is ready with `isReady`:

```php
$parser = Parser::new();
$parser->setFile('test.log'); // throws FileNotFoundException if file does not exist
$parser->isReady(); // true. The file is ready to be parsed
```

### Parsing

Parsing happens either when `parse` or `get` are called. The `Parser` object will store the results and return them from cache when `get` is called unless `parse` is called again directly or `clear` is called.

```php
$parser = Parser::new()->setFile('test.log');
$records = $parser->parse()->get();
$records = $parser->get(); // this will return the records from cache
$records = $parser->parse()->get(); // this will re-parse the file
```

Clear the cached records:

```php
$parser = Parser::new()->setFile('test.log');
$records = $parser->get(); // this will parse the file
$records = $parser->clear()->get(); // this will re-parse the file
$records = $parser->get(false); // this will re-parse the file
```

Parse a string instead of a file:

```php
$parser = new Parser();
$records = $parser->parse('[2023-01-01] test.DEBUG: message')->get();
```

### Log Records

The log records returned by `get` are an array of type `LogRecord`. These objects are readonly and can be accessed like this:

```php
$records = Parser::new('test.log')->get();
foreach($records as $record) {
    $record['datetime']; // object of type DateTimeImmutable
    $record['channel']; // string
    $record['message']; // string
    $record['context']; // object, empty array by default (decoded JSON)
    $record['extra']; // object or array, empty array by default (decoded JSON)
}
```
For reference, Monolog log records look like this:

```txt
[2020-01-01 18:00:00] debugging.WARNING: this is a message {"foo":"bar"} ["baz","bud"]
 ----- datetime ----  -channel- -level-  ---- message ---- -- context -- --- extra ---
```

### Patterns

Besides the default pattern, there are other patterns provided as listed below. Patterns have to have named subpatterns to work correctly. You can set any pattern like this:

```php
// use provided patterns
$parser = Parser::new()->setPattern(Parser::PATTERN_LARAVEL);
// or build your regex with named subpatterns like this:
$parser->setPattern('/^\[(?<datetime>.*?)\] (?<message>.*?) \| (?<channel>\w+).(?<level>\w+)$/m');
```

- `PATTERN_MONOLOG2` (default): will parse most configurations of Monolog's `LineFormatter` pattern.
- `PATTERN_MONOLOG2_MULTILINE`: will parse most configurations of Monolog's `LineFormatter` with multiline support.
- `PATTERN_LARAVEL`: will parse Laravel logfiles that were created with the Monolog configuration that is provided by Laravel. 

### Exceptions

Monolog-Parser will throw exceptions as listed below.

- `FileNotFoundException`: Whenever a file is accessed that does not exist, this exception will be thrown. Note: this will happen, when `setFile` is called with a non-existing file.
- `ParserNotReadyException`: Whenever `parse` or `get` are called while the parser is not ready (`isReady()` is `false`), this exception will be thrown. This is not the case if `parse` is provided with a string.
- `LogParsingException`: Whenever the parsing of a logfile or log record fails, this exception will be thrown. See [Limits and failing logs](#limits-and-failing-logs)

### Limits and failing logs

If reading your logs fails, please make sure the logs are generated correctly and not listed on the list below. If files generated by the default `LineFormatter` pattern of Monolog fail to be parsed, please let me know and submit an issue. If you are using non-default patterns, I would still be interested to provide parse patterns if they are commonly used.

Log files or records that can (currently) not be parsed by Monolog-Parser:

- Invalid JSON in the context/extra section of a log entry (will throw a [`LogParsingException`](#exceptions)).
- Valid JSON with misleading closing brackets like all listed in [this testfile](tests/files/brackets-fail.log). However, the examples in [this other testfile](tests/files/brackets.log) will *not* throw an error.

### Method Reference

If not stated otherwise, methods return the object `$this`.

| Method        | Parameters    | Description   |
|---------------|---------------|---------------|
| `__construct` | `string $filename`: see `setFile` | Create a new instance of `Parser`. |
| `::new` | `string $filename`: see `setFile` | Create a new instance (equivalent to __construct). |
| `setFile` | `string $filename`: full path to a valid file | Set the file at `$filename`. If the files does not exist, this will throw a `FileNotFoundException`. |
| `setPattern` | `string $pattern = ''`: regex pattern that will be used by `parse`. | Set the pattern that will be used by `parse` when parsing the logfile. The regex needs to be valid PHP regex with named subpatterns ([see Patterns](#patterns)). |
| `isReady` | | Returns `true` when the parser is ready to parse, otherwise `false`. This state requires that an existing, readable file to be set. |
| `parse` | `string` $string: optional string to be parsed instead of a file. | This will parse the given string or the set file (see `setFile`) if `$string` is empty. |
| `get` | `bool $returnFromCache = true`: optional bool to decided whether cache from previous `parse` or `get` should be returned. | Get the results of the last `parse`. If no results exist, `parse` will be called internally. Set `$returnFromCache` to `false` if you do not want the records to be loaded from cache but to re-parse the file. | 
| `clear` | | Clear the cache from previous `parse` and rewind the file if it was already read. |

## About

### Requirements

- Monolog-Parser works with PHP 8.1 or above.
- Monolog-Parser does *not* require any Monolog package.

### License

Monolog-Parser is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

### Acknowledgements

This library was inspired by [ddtraceweb's monolog parser](https://github.com/ddtraceweb/monolog-parser) and [haruncpi's laravel log reader](https://github.com/haruncpi/laravel-log-reader).
