<?php

namespace Paketuki;

/**
 * Simple file-based logger
 */
class Logger
{
    private string $logFile;
    private bool $debug;

    public function __construct(string $logFile = 'logs/app.log', bool $debug = false)
    {
        $this->logFile = $logFile;
        $this->debug = $debug;
        
        // Ensure log directory exists
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context);
        $logLine = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
        
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        if ($this->debug && $level === 'DEBUG') {
            error_log($logLine);
        }
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        if ($this->debug) {
            $this->log('DEBUG', $message, $context);
        }
    }
}
