<?php

namespace Devdot\Monolog;

/**
 * Acts as a Monolog Parser, containing access to one logfile.
 * @author Thomas Kuschan
 * @copyright (c) 2023
 */
class Parser
{
    protected Log $records;

    protected \SplFileObject $file;

    public const PATTERN_MONOLOG2 =
        "/^" . // start with newline
        "\[(?<datetime>.*)\] " . // find the date that is between two brackets []
        "(?<channel>[\w-]+).(?<level>\w+): " . // get the channel and log level, they look lilke this: channel.ERROR, follow by colon and space
        "(?<message>[^\[\{\\n]+)" . // next up is the message (containing anything except [ or {, nor a new line)
        "(?:(?<context> (\[.*?\]|\{.*?\}))|)" . // followed by a space and anything (non-greedy) in either square [] or curly {} brackets, or nothing at all (skips ahead to line end)
        "(?:(?<extra> (\[.*\]|\{.*\}))|)" . // followed by a space and anything (non-greedy) in either square [] or curly {} brackets, or nothing at all (skips ahead to line end)
        "\s{0,2}$/m"; // end with up to 2 optional spaces and the endline marker, flag: m = multiline

    public const PATTERN_MONOLOG2_MULTILINE = // same as PATTERN_MONOLOG2 except for annotated changed
        "/^" .
        "\[(?<datetime>[^\]]*)\] " . // allow anything until the first closing bracket ]
        "(?<channel>[\w-]+).(?<level>\w+): " .
        "(?<message>[^\[\{]+)" . // allow \n character in message string
        "(?:(?<context> (\[.*?\]|\{.*?\}))|)" .
        "(?:(?<extra> (\[.*?\]|\{.*?\}))|)" . // . has to be non-greedy so it doesn't take everything in
        "\s{0,2}$" .
        "(?=\\n(?:\[|\z))" . // use look-ahead to match (a) a following newline and opening bracket [ (that would signal the next log entry)
        "/ms"; // flags: m = multiline, s = . includes newline character

    public const PATTERN_LARAVEL =
        "/^" . // start with newline
        "\[(?<datetime>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] " . // find the datetime with a specific Y-m-d H:i:s format between square brackets [] and a tailing space
        "(?<channel>\w+)\.(?<level>\w+): " . // get the channel and log level, they look lilke this: channel.ERROR, follow by colon and space
        "(?<message>.*?)" . // get the message, but with the non-greedy selector *? instead of the gready * for any . character (this will catch as few characters as possible until it finds the next part of the pattern)
        "(?: (?<context>\{\".*?\})|)" . // get the context: the context is optional, which is why the outer group () starts with the non-capture flag ?: (it will not show in matches). it will either (a) capture a space followed by the context (non-greedy) in curly brackets and starting with a ", or (b) nothing
        " $" . // after this the line must have a space and then endline (this is because Laravel never puts anything in the Monolog2 extra array and uses the "hide if empty" option, which still produces a tailing space with the Monolog2 default formatting string)
        "(?=\\n(?:\z|\[))" . // look-ahead to make sure we are capturing everything until the next log entry (beginning with [) or end of file (\z)
        "/ms"; // flags: m = multiline, s = . includes newline character

    protected string $pattern = self::PATTERN_MONOLOG2;

    protected bool $optionSortDatetime = false;
    protected bool $optionJsonAsText = false;
    protected bool $optionSkipExceptions = false;
    protected bool $optionJsonFailSoft = false;

    public const OPTION_NONE            = 0;
    public const OPTION_SORT_DATETIME   = 0b00000001;
    public const OPTION_JSON_AS_TEXT    = 0b00000010;
    public const OPTION_SKIP_EXCEPTIONS = 0b00000100;
    public const OPTION_JSON_FAIL_SOFT  = 0b00001000;

    /**
     * Create a new instance of Parser.
     * @param string $filename Absolute path to the existing log file.
     * @throws Exceptions\FileNotFoundException if the given file cannot be found.
     */
    public function __construct(string $filename = '')
    {
        // if we were given a filename, initialize the file right away
        if ($filename !== '') {
            $this->initializeFileObject($filename);
        }
    }

    /**
     * Create a new instance (equivalent to __construct).
     * @param string $filename Absolute path to the existing log file.
     * @throws Exceptions\FileNotFoundException if the given file cannot be found.
     */
    public static function new(string $filename = ''): self
    {
        return new self($filename);
    }

    /**
     * Set the file at $filename.
     * @param string $filename Absolute path to the existing log file.
     * @throws Exceptions\FileNotFoundException if the given file cannot be found.
     */
    public function setFile(string $filename): self
    {
        $this->initializeFileObject($filename);
        return $this;
    }

    /**
     * Returns true when the parser is ready to parse, otherwise false. This state requires that an existing, readable file to be set.
     */
    public function isReady(): bool
    {
        if (isset($this->file)) {
            return $this->file->isReadable() && $this->file->valid();
        }
        return false;
    }

    /**
     * Set the pattern that will be used by parse when parsing the logfile.
     * @param string $pattern The regex needs to be valid and with named subpatterns.
     */
    public function setPattern(string $pattern): self
    {
        $this->pattern = $pattern;
        return $this;
    }

    /**
     * Set parsing options with the provided option flags.
     * @param int $options Provide options with combined binary flags.
     */
    public function setOptions(int $options): self
    {
        // set all the options via bitwise operators
        $this->optionSortDatetime   = ($options & self::OPTION_SORT_DATETIME) > 0;
        $this->optionJsonAsText     = ($options & self::OPTION_JSON_AS_TEXT) > 0;
        $this->optionSkipExceptions = ($options & self::OPTION_SKIP_EXCEPTIONS) > 0;
        $this->optionJsonFailSoft   = ($options & self::OPTION_JSON_FAIL_SOFT) > 0;
        // return the instance
        return $this;
    }

    /**
     * Get the results of the last parse. If no results exist, parse will be called internally.
     * @param bool $returnFromCache Set to false if you do not want the records to be loaded from cache but to re-parse the file.
     * @throws Exceptions\ParserNotReadyException If the parser was not ready to parse (e.g. the file was not set)
     * @throws Exceptions\LogParsingException If there were any errors in parsing the log file, as configured by options.
     * @return Log Array containing the results as LogRecord objects
     */
    public function get(bool $returnFromCache = true): Log
    {
        // if we shall not return from cache, clear first
        if (!$returnFromCache) {
            $this->clear();
        }
        // check if we need to parse the records
        if (!isset($this->records)) {
            $this->parse();
        }
        // return the stored records
        return $this->records;
    }

    /**
     * Clear the cache from previous parse and rewind the file if it was already read.
     */
    public function clear(): self
    {
        // clear the internal cache that was created by the last parse
        unset($this->records);

        // if a file was set, reset it to file start
        if (isset($this->file)) {
            $this->file->rewind();
        }

        return $this;
    }

    /**
     * This will parse the given string or the set file if $string is empty.
     * @param string $string If an empty string is provided, the Parser will parse the file that was provided earlier.
     * @throws Exceptions\ParserNotReadyException If no string was provided and the parser was not ready to parse (e.g. the file was not set)
     * @throws Exceptions\LogParsingException If there were any errors in parsing the log file, as configured by options.
     * @return Parser|\NULL
     */
    public function parse(string $string = ''): self|null
    {
        // let's check if we have a string given to validate
        $str = '';
        if ($string === '') {
            // make sure the file is ready, if not raise an exception
            if (!$this->isReady()) {
                throw new Exceptions\ParserNotReadyException();
            }

            // load the file content
            while (!$this->file->eof()) {
                $str .= $this->file->fgets();
            }

            // rewind the file to the parser remains ready
            $this->file->rewind();
        } else {
            // simply use the provided string
            $str = $string;
        }

        // parse with regex
        $matches = [];
        preg_match_all($this->pattern, $str, $matches, PREG_SET_ORDER, 0);

        // iterate through the records and put them into the array
        $records = [];
        foreach ($matches as $match) {
            $entry = new LogRecord(
                new \DateTimeImmutable($match['datetime']),
                $match['channel'] ?? '',
                $match['level'] ?? '',
                trim($match['message'] ?? ''),
                $this->processJson($match['context'] ?? '[]'),
                $this->processJson($match['extra'] ?? '[]'),
            );
            $records[] = $entry;
        }

        // create the log
        $this->records = new Log(...$records);

        // check if the records ought to be sorted
        if ($this->optionSortDatetime) {
            $this->sortRecords();
        }

        return $this;
    }

    /**
     * Process a JSON block into either context or option
     * @param string $text Input string as read from the file. This method will modify the string to make it parsable.
     * @throws Exceptions\LogParsingException When json_decode fails and the parser is set to throw this exception.
     * @return \stdClass|array<int, mixed>|string|\NULL
     */
    protected function processJson(string $text): \stdClass|array|string|null
    {
        // replace characters to make JSON parsable
        $json = str_replace(["\r", "\n"], ['', '\n'], $text);
        // check if we have to parse it anyways
        if ($this->optionJsonAsText) {
            // just return the trimmed string
            return trim($json);
        }
        $object = json_decode($json);
        // make sure the json decode did not fail
        if ($object === null) {
            // check the soft fail option
            if ($this->optionJsonFailSoft) {
                // now just add the json as text instead of failing, since parsing didn't work
                return trim($json);
            }
            // only throw an exception if the option allows for it
            if ($this->optionSkipExceptions === false) {
                $filename = isset($this->file) ? $this->file->getFilename() : '[STRING]';
                throw new Exceptions\LogParsingException($filename, 'Failed to decode JSON: ' . $json);
            }
        }
        // and let's typecast this if it's not array or object
        if (!is_array($object) && !($object instanceof \stdClass) && $object !== null) {
            // simply put it into a an array
            $object = [$object];
        }
        return $object;
    }

    protected function initializeFileObject(string $filename): void
    {
        // check if this file exists
        if (!file_exists($filename)) {
            throw new Exceptions\FileNotFoundException($filename);
        }

        // initialize the file object
        $this->file = new \SplFileObject($filename, 'r');
    }

    protected function sortRecords(): void
    {
        // sort the records that are saved currently
        // we don't need to sort if there are less than 2 items here
        if (!isset($this->records) || count($this->records) <= 1) {
            return;
        }

        // the log can sort itself
        $this->records->sortByDatetime();
    }
}
