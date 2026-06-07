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

    /**
     * Pesan informasi umum — hanya tampil jika verbose=true.
     */
    public function info(string $message): void
    {
        if ($this->verbose) {
            $this->log('INFO', $message, self::GREEN);
        } else {
            // Tetap rekam ke history & file meski tidak ditampilkan
            $this->record('INFO', $message);
            $this->writeToFile('INFO', $message);
        }
    }

    /**
     * Pesan debug detail — hanya tampil jika verbose=true.
     */
    public function debug(string $message): void
    {
        if ($this->verbose) {
            $this->log('DEBUG', $message, self::CYAN);
        } else {
            $this->record('DEBUG', $message);
            $this->writeToFile('DEBUG', $message);
        }
    }

    /**
     * Peringatan — selalu tampil kecuali mode silent.
     */
    public function warn(string $message): void
    {
        if (!$this->silent) {
            $this->log('WARN', $message, self::YELLOW);
        } else {
            $this->record('WARN', $message);
            $this->writeToFile('WARN', $message);
        }
    }

    /**
     * Error — selalu tampil ke STDERR kecuali mode silent.
     */
    public function error(string $message): void
    {
        $this->record('ERROR', $message);
        $this->writeToFile('ERROR', $message);

        if (!$this->silent) {
            $timestamp = $this->timestamp();
            $formatted = self::RED . '[ERROR]' . self::RESET . ' ' . self::GRAY . $timestamp . self::RESET . ' ' . $message;
            fwrite(STDERR, $formatted . PHP_EOL);
        }
    }

    /**
     * Pesan sukses (✓) — hanya tampil jika verbose=true.
     */
    public function success(string $message): void
    {
        $this->record('SUCCESS', $message);
        $this->writeToFile('SUCCESS', $message);

        if ($this->verbose && !$this->silent) {
            $line = self::BOLD . self::GREEN . "✓ {$message}" . self::RESET;
            echo $line . PHP_EOL;
        }
    }

    /**
     * Output teks mentah — hanya tampil jika verbose=true.
     */
    public function raw(string $message): void
    {
        if ($this->verbose && !$this->silent) {
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
