<?php

namespace Devdot\Monolog;

class Parser {

    protected array $records;

    protected \SplFileObject $file;

    public const PATTERN_MONOLOG2 = 
        "/^". // start with newline
        "\[(?<datetime>.*)\] ". // find the date that is between two brackets []
        "(?<channel>[\w-]+).(?<level>\w+): ". // get the channel and log level, they look lilke this: channel.ERROR, follow by colon and space
        "(?<message>[^\[\{\\n]+)". // next up is the message (containing anything except [ or {, nor a new line)
        "(?:(?<context> (\[.*?\]|\{.*?\}))|)". // followed by a space and anything (non-greedy) in either square [] or curly {} brackets, or nothing at all (skips ahead to line end)
        "(?:(?<extra> (\[.*\]|\{.*\}))|)". // followed by a space and anything (non-greedy) in either square [] or curly {} brackets, or nothing at all (skips ahead to line end)
        "\s{0,2}$/m"; // end with up to 2 optional spaces and the endline marker, flag: m = multiline

    public const PATTERN_MONOLOG2_MULTILINE = // same as PATTERN_MONOLOG2 except for annotated changed
        "/^".
        "\[(?<datetime>[^\]]*)\] ". // allow anything until the first closing bracket ]
        "(?<channel>[\w-]+).(?<level>\w+): ".
        "(?<message>[^\[\{]+)". // allow \n character in message string
        "(?:(?<context> (\[.*?\]|\{.*?\}))|)".
        "(?:(?<extra> (\[.*?\]|\{.*?\}))|)". // . has to be non-greedy so it doesn't take everything in
        "\s{0,2}$".
        "(?=\\n(?:\[|\z))". // use look-ahead to match (a) a following newline and opening bracket [ (that would signal the next log entry)
        "/ms"; // flags: m = multiline, s = . includes newline character
    
    public const PATTERN_LARAVEL = 
        "/^". // start with newline
        "\[(?<datetime>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] ". // find the datetime with a specific Y-m-d H:i:s format between square brackets [] and a tailing space
        "(?<channel>\w+)\.(?<level>\w+): ". // get the channel and log level, they look lilke this: channel.ERROR, follow by colon and space
        "(?<message>.*?)". // get the message, but with the non-greedy selector *? instead of the gready * for any . character (this will catch as few characters as possible until it finds the next part of the pattern)
        "(?: (?<context>\{.*?\})|)". // get the context: the context is optional, which is why the outer group () starts with the non-capture flag ?: (it will not show in matches). it will either (a) capture a space followed by the context (non-greedy) in curly brackets, or (b) nothing
        " $". // after this the line must have a space and then endline (this is because Laravel never puts anything in the Monolog2 extra array and uses the "hide if empty" option, which still produces a tailing space with the Monolog2 default formatting string)
        "(?=\\n(?:\z|\[))". // look-ahead to make sure we are capturing everything until the next log entry (beginning with [) or end of file (\z)
        "/ms"; // flags: m = multiline, s = . includes newline character

    protected string $pattern = self::PATTERN_MONOLOG2;

    protected bool $optionSortDatetime = false;
    protected bool $optionJsonAsText = false;
    protected bool $optionSkipExceptions = false;

    public const OPTION_NONE            = 0;
    public const OPTION_SORT_DATETIME   = 0b00000001;
    public const OPTION_JSON_AS_TEXT    = 0b00000010;
    public const OPTION_SKIP_EXCEPTIONS = 0b00000100;

    public function __construct(string $filename = '') {
        // if we were given a filename, initialize the file right away
        if(!empty($filename)) {
            $this->initializeFileObject($filename);
        }
    }

    public static function new(string $filename = '') {
        return new self($filename);
    }

    public function setFile(string $filename) {
        $this->initializeFileObject($filename);
        return $this;
    }

    public function isReady() {
        if(isset($this->file)) {
            return $this->file->isReadable() && $this->file->valid();
        }
        return false;
    }

    public function setPattern(string $pattern) {
        $this->pattern = $pattern;
        return $this;
    } 

    public function setOptions(int $options) {
        // set all the options via bitwise operators
        $this->optionSortDatetime   = $options & self::OPTION_SORT_DATETIME;
        $this->optionJsonAsText     = $options & self::OPTION_JSON_AS_TEXT;
        $this->optionSkipExceptions = $options & self::OPTION_SKIP_EXCEPTIONS;
        // return the instance
        return $this;
    }

    public function get(bool $returnFromCache = true) {
        // if we shall not return from cache, clear first
        if(!$returnFromCache) {
            $this->clear();
        }
        // check if we need to parse the records
        if(!isset($this->records)) {
            $this->parse();
        }
        // return the stored records
        return $this->records;
    }

    public function clear() {
        // clear the internal cache that was created by the last parse
        unset($this->records);

        // if a file was set, reset it to file start
        if(isset($this->file)) {
            $this->file->rewind();
        }

        return $this;
    }
        
    public function parse(string $string = '') {
        // let's check if we have a string given to validate
        $str = '';
        if(empty($string)) {
            // make sure the file is ready, if not raise an exception
            if(!$this->isReady()) {
                throw new Exceptions\ParserNotReadyException();
                return;
            }

            // load the file content
            while(!$this->file->eof()) {
                $str .= $this->file->fgets();
            }

            // rewind the file to the parser remains ready
            $this->file->rewind();
        }
        else {
            // simply use the provided string
            $str = $string;
        }

        // parse with regex
        $matches = [];
        preg_match_all($this->pattern, $str, $matches, PREG_SET_ORDER, 0);

        // iterate through the records and put them into the array
        $this->records = [];
        foreach($matches as $match) {
            $entry = new LogRecord(
                new \DateTimeImmutable($match['datetime']),
                $match['channel'] ?? '',
                $match['level'] ?? '',
                trim($match['message'] ?? ''),
                $this->processJson($match['context'] ?? '[]'),
                $this->processJson($match['extra'] ?? '[]'),
            );
            $this->records[] = $entry;
        }

        // check if the records ought to be sorted
        if($this->optionSortDatetime === true) {
            $this->sortRecords();
        }

        return $this;
    }

    protected function processJson(string $text) {
        // process the JSON in either context or option
        // replace characters to make JSON parsable
        $json = str_replace(["\r", "\n"], ['', '\n'], $text);
        // check if we have to parse it anyways
        if($this->optionJsonAsText) {
            // just return the trimmed string
            return trim($json);
        }
        $object = json_decode($json);
        // make sure the json decode did not fail
        if($object === null) {
            // only throw an exception if the option allows for it
            if($this->optionSkipExceptions === false) {
                $filename = isset($this->file) ? $this->file->getFilename() : '[STRING]';
                throw new Exceptions\LogParsingException($filename, 'Failed to decode JSON: '.$json);
                return;
            }
        }
        return $object;
    }

    protected function initializeFileObject(string $filename) {
        // check if this file exists
        if(!file_exists($filename)) {
            throw new Exceptions\FileNotFoundException($filename);
            return;
        }

        // initialize the file object
        $this->file = new \SplFileObject($filename, 'r');
    }

    protected function sortRecords() {
        // sort the records that are saved currently
        // we don't need to sort if there are less than 2 items here
        if(!isset($this->records) || count($this->records) <= 1) {
            return;
        }

        // using the php sort algorithm, sort this
        // sort DESCending (newest to oldest datetime)
        usort($this->records, fn($a, $b) => $b['datetime']->format('U') - $a['datetime']->format('U'));
    }
}
