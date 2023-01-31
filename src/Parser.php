<?php

namespace Devdot\Monolog;

class Parser {

    protected array $records;

    protected \SplFileObject $file;

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
