<?php

namespace Devdot\Monolog;

class Parser {

    protected array $records;

    protected \SplFileObject $file;

    public const PATTERN_MONOLOG2 = 
        "/^". // start with newline
        "\[(?<datetime>.*)\] ". // find the date that is between two brackets []
        "(?<logger>[\w-]+).(?<level>\w+): ". // get the logger and log level, they look lilke this: channel.ERROR, follow by colon and space
        "(?<message>[^\[\{]+) ". // next up is the message, tailed by a space character
        "(?<context>[\[\{].*[\]\}]) ". // followed by context within either square [] or curly {} brackets, tailed by a space
        "(?<extra>[\[\{].*[\]\}])". // followed by extra within either square [] or curly {} brackets
        "$/m"; // end with endline marker, flag: m = multiline
    
    public const PATTERN_LARAVEL = 
        "/^". // start with newline
        "\[(?<datetime>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] ". // find the datetime with a specific Y-m-d H:i:s format between square brackets [] and a tailing space
        "(?<logger>\w+)\.(?<level>\w+): ". // get the logger and log level, they look lilke this: channel.ERROR, follow by colon and space
        "(?<message>.*?)". // get the message, but with the non-greedy selector *? instead of the gready * for any . character (this will catch as few characters as possible until it finds the next part of the pattern)
        "(?: (?<context>\{.*?\})\s+$|$)". // get the context. the context is optional, which is why the outer group () starts with the non-capture flag ?: (it will not show in matches). it will either (a) capture a space followed by the context (non-greedy) in curly brackets, an optional space character and end of line, or (b) just the end of line
        "/ms"; // flags: m = multiline, s = . includes newline character

    protected string $pattern = self::PATTERN_MONOLOG2;

    public function __construct(string $filename = '') {
        // if we were given a filename, initialize the file right away
        if(!empty($filename)) {
            $this->initializeFileObject($filename);
        }
    }

    public function setFilename(string $filename) {
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
    } 

    public function get() {
        // check if we need to parse the records
        if(!isset($this->records)) {
            $this->parse();
        }
        // return the stored records
        return $this->records;
    }
        
    public function parse() {
        // make sure the file is ready, if not raise an exception
        if(!$this->isReady()) {
            throw new Exceptions\ParserNotReadyException();
            return;
        }

        // load the file content
        $str = '';
        while(!$this->file->eof()) {
            $str .= $this->file->fgets();
        }

        // parse with regex
        $matches = [];
        preg_match_all($this->pattern, $str, $matches, PREG_SET_ORDER, 0);

        // iterate through the records and put them into the array
        $this->records = [];
        foreach($matches as $match) {
            $entry = new LogRecord(
                new \DateTimeImmutable($match['datetime']),
                $match['logger'] ?? '',
                $match['level'] ?? '',
                trim($match['message'] ?? ''),
                json_decode(str_replace(["\r", "\n"], ['', '\n'], $match['context']) ?? '[]'),
                json_decode($match['extra'] ?? '[]'),
            );
            $this->records[] = $entry;
        }

        return;
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


}
