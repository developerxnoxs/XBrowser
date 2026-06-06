<?php

declare(strict_types=1);

namespace Xbrowser\Utils;

class Logger
{
    private bool $verbose = false;
    private bool $silent = false;
    private string $logFile = '';
    private array $history = [];

    private const RESET  = "\033[0m";
    private const BOLD   = "\033[1m";
    private const GREEN  = "\033[32m";
    private const YELLOW = "\033[33m";
    private const RED    = "\033[31m";
    private const CYAN   = "\033[36m";
    private const GRAY   = "\033[90m";

    public function __construct(bool $verbose = false, string $logFile = '')
    {
        $this->verbose = $verbose;
        $this->logFile = $logFile;
    }

    public function setVerbose(bool $verbose): void
    {
        $this->verbose = $verbose;
    }

    public function setSilent(bool $silent): void
    {
        $this->silent = $silent;
    }

    public function info(string $message): void
    {
        $this->log('INFO', $message, self::GREEN);
    }

    public function debug(string $message): void
    {
        if ($this->verbose) {
            $this->log('DEBUG', $message, self::CYAN);
        }
    }

    public function warn(string $message): void
    {
        $this->log('WARN', $message, self::YELLOW);
    }

    public function error(string $message): void
    {
        $this->log('ERROR', $message, self::RED, STDERR);
    }

    public function success(string $message): void
    {
        if (!$this->silent) {
            $timestamp = $this->timestamp();
            $line = self::BOLD . self::GREEN . "✓ {$message}" . self::RESET;
            echo $line . PHP_EOL;
            $this->record("SUCCESS", $message);
            $this->writeToFile("SUCCESS", $message);
        }
    }

    public function raw(string $message): void
    {
        if (!$this->silent) {
            echo $message . PHP_EOL;
        }
    }

    public function getHistory(): array
    {
        return $this->history;
    }

    private function log(string $level, string $message, string $color, $stream = STDOUT): void
    {
        $this->record($level, $message);
        $this->writeToFile($level, $message);

        if ($this->silent) {
            return;
        }

        $timestamp = $this->timestamp();
        $formatted = "{$color}[{$level}]{$this->reset()} {$this->gray($timestamp)} {$message}";
        fwrite($stream, $formatted . PHP_EOL);
    }

    private function timestamp(): string
    {
        return date('H:i:s');
    }

    private function gray(string $text): string
    {
        return self::GRAY . $text . self::RESET;
    }

    private function reset(): string
    {
        return self::RESET;
    }

    private function record(string $level, string $message): void
    {
        $this->history[] = [
            'level'     => $level,
            'message'   => $message,
            'timestamp' => time(),
        ];
    }

    private function writeToFile(string $level, string $message): void
    {
        if ($this->logFile === '') {
            return;
        }

        $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $level, $message);
        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
