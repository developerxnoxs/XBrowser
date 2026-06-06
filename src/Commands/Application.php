<?php

declare(strict_types=1);

namespace Xbrowser\Commands;

use Xbrowser\Browser\BrowserFactory;
use Xbrowser\Utils\ConfigManager;
use Xbrowser\Utils\Logger;

class Application
{
    private array $commands = [];
    private Logger $logger;
    private ConfigManager $config;
    private bool $verbose = false;

    private const VERSION = '1.0.0';
    private const BANNER  = <<<BANNER

  ██╗  ██╗██████╗ ██████╗  ██████╗ ██╗    ██╗███████╗███████╗██████╗
  ╚██╗██╔╝██╔══██╗██╔══██╗██╔═══██╗██║    ██║██╔════╝██╔════╝██╔══██╗
   ╚███╔╝ ██████╔╝██████╔╝██║   ██║██║ █╗ ██║███████╗█████╗  ██████╔╝
   ██╔██╗ ██╔══██╗██╔══██╗██║   ██║██║███╗██║╚════██║██╔══╝  ██╔══██╗
  ██╔╝ ██╗██████╔╝██║  ██║╚██████╔╝╚███╔███╔╝███████║███████╗██║  ██║
  ╚═╝  ╚═╝╚═════╝ ╚═╝  ╚═╝ ╚═════╝  ╚══╝╚══╝ ╚══════╝╚══════╝╚═╝  ╚═╝

BANNER;

    public function __construct()
    {
        $this->config = new ConfigManager();
        $this->logger = new Logger();
        $this->registerDefaultCommands();
    }

    public function run(array $argv): void
    {
        $argv = array_values($argv);

        $this->parseGlobalFlags($argv);

        if ($this->verbose) {
            $this->logger->setVerbose(true);
        }

        $command = $argv[1] ?? '';

        if ($command === '' || $command === '--help' || $command === '-h') {
            $this->showHelp();
            return;
        }

        if ($command === '--version' || $command === '-v') {
            echo "Xbrowser v" . self::VERSION . PHP_EOL;
            return;
        }

        if (!isset($this->commands[$command])) {
            $this->logger->error("Unknown command: {$command}");
            $this->showHelp();
            exit(1);
        }

        try {
            $args    = array_slice($argv, 2);
            $args    = array_filter($args, fn($a) => !str_starts_with($a, '--verbose') && $a !== '-V');
            $handler = $this->commands[$command];
            $handler(array_values($args));
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            if ($this->verbose) {
                $this->logger->error($e->getTraceAsString());
            }
            exit(1);
        }
    }

    public function registerCommand(string $name, callable $handler): void
    {
        $this->commands[$name] = $handler;
    }

    private function parseGlobalFlags(array &$argv): void
    {
        foreach ($argv as $i => $arg) {
            if ($arg === '--verbose' || $arg === '-V') {
                $this->verbose = true;
            }
        }
    }

    private function registerDefaultCommands(): void
    {
        $self = $this;

        $this->commands['open'] = function (array $args) use ($self) {
            (new OpenCommand($self->createBrowser()))->execute($args);
        };

        $this->commands['click'] = function (array $args) use ($self) {
            (new ClickCommand($self->createBrowser()))->execute($args);
        };

        $this->commands['type'] = function (array $args) use ($self) {
            (new TypeCommand($self->createBrowser()))->execute($args);
        };

        $this->commands['eval'] = function (array $args) use ($self) {
            (new EvalCommand($self->createBrowser()))->execute($args);
        };

        $this->commands['screenshot'] = function (array $args) use ($self) {
            (new ScreenshotCommand($self->createBrowser()))->execute($args);
        };

        $this->commands['html'] = function (array $args) use ($self) {
            (new HtmlCommand($self->createBrowser()))->execute($args);
        };

        $this->commands['shell'] = function (array $args) use ($self) {
            (new ShellCommand($self->createBrowser()))->execute($args);
        };

        $this->commands['network'] = function (array $args) use ($self) {
            (new NetworkCommand($self->createBrowser()))->execute($args);
        };

        $this->commands['record'] = function (array $args) use ($self) {
            (new RecordCommand($self->createBrowser()))->execute($args);
        };

        $this->commands['save-session'] = function (array $args) use ($self) {
            (new SessionCommand($self->createBrowser()))->executeSave($args);
        };

        $this->commands['load-session'] = function (array $args) use ($self) {
            (new SessionCommand($self->createBrowser()))->executeLoad($args);
        };
    }

    private function createBrowser(): \Xbrowser\Browser\Browser
    {
        return BrowserFactory::create(['verbose' => $this->verbose]);
    }

    private function showHelp(): void
    {
        echo self::BANNER;
        echo "  Terminal Browser powered by Chrome DevTools Protocol\n";
        echo "  Version: " . self::VERSION . "\n\n";

        echo "\033[1mUSAGE:\033[0m\n";
        echo "  Xbrowser [--verbose] <command> [arguments]\n\n";

        echo "\033[1mCOMMANDS:\033[0m\n";
        $cmds = [
            ['open <url>',               'Open a URL and render it in the terminal'],
            ['click <selector>',         'Click an element by CSS selector'],
            ['type <selector> <text>',   'Type text into an input element'],
            ['eval <expression>',        'Evaluate JavaScript and print result'],
            ['screenshot <file>',        'Take a screenshot (saves as PNG)'],
            ['html',                     'Dump the full page HTML'],
            ['shell',                    'Start an interactive browser shell'],
            ['network',                  'Show network request/response log'],
            ['record',                   'Record actions and export as PHP script'],
            ['save-session <name>',      'Save current browser session'],
            ['load-session <name>',      'Load a previously saved session'],
        ];

        foreach ($cmds as [$cmd, $desc]) {
            printf("  \033[32m%-30s\033[0m %s\n", $cmd, $desc);
        }

        echo "\n\033[1mOPTIONS:\033[0m\n";
        echo "  \033[32m--verbose, -V\033[0m    Show debug output\n";
        echo "  \033[32m--version, -v\033[0m    Show version\n";
        echo "  \033[32m--help, -h\033[0m       Show this help\n";
        echo PHP_EOL;
    }
}
