<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class MyBikeLogger
{
    private $logFile;

    public function __construct($logFile)
    {
        $this->logFile = $logFile;
    }

    public function info($message)
    {
        $this->write('INFO', $message);
    }

    public function error($message)
    {
        $this->write('ERROR', $message);
    }

    private function write($level, $message)
    {
        if (file_exists($this->logFile) && filesize($this->logFile) > MYBIKE_LOG_MAX_SIZE) {
            rename($this->logFile, $this->logFile . '.old');
        }
        $line = date('Y-m-d H:i:s') . ' [' . $level . '] ' . $message . PHP_EOL;
        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
