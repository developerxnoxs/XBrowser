<?php

declare(strict_types=1);

namespace Xbrowser\Commands;

use Xbrowser\Browser\Browser;
use Xbrowser\Browser\Page;
use Xbrowser\Utils\SessionManager;

class ShellCommand
{
    private ?Page $page = null;

    private const COMMANDS = [
        'open <url>'              => 'Navigate to a URL',
        'render'                  => 'Render current page to terminal',
        'html'                    => 'Dump raw HTML of current page',
        'click <selector>'        => 'Click an element',
        'type <selector> <text>'  => 'Type into an element',
        'eval <expression>'       => 'Run JavaScript',
        'screenshot <file>'       => 'Take screenshot',
        'submit [selector]'       => 'Submit a form',
        'back'                    => 'Go back',
        'forward'                 => 'Go forward',
        'reload'                  => 'Reload the page',
        'wait <selector>'         => 'Wait for element',
        'cookies'                 => 'Show current cookies',
        'title'                   => 'Show page title',
        'url'                     => 'Show current URL',
        'save-session <name>'     => 'Save browser session',
        'load-session <name>'     => 'Load browser session',
        'network'                 => 'Show network summary',
        'clear'                   => 'Clear the terminal',
        'help'                    => 'Show this help',
        'quit / exit'             => 'Close browser and exit',
    ];

    public function __construct(private readonly Browser $browser) {}

    public function execute(array $args): void
    {
        $this->browser->launch();
        $this->page = $this->browser->newPage();

        $this->showBanner();

        while (true) {
            $prompt = $this->page ? "\033[36m[" . ($this->page->getTitle() ?: 'Xbrowser') . "]\033[0m \033[32m>\033[0m " : "\033[32m>\033[0m ";
            echo $prompt;

            $line = fgets(STDIN);
            if ($line === false) {
                break;
            }

            $input = trim($line);
            if ($input === '') {
                continue;
            }

            if (in_array($input, ['quit', 'exit', 'q'], true)) {
                echo "\nGoodbye!\n";
                break;
            }

            $this->dispatch($input);
        }

        $this->browser->close();
    }

    private function dispatch(string $input): void
    {
        $parts   = $this->tokenize($input);
        $command = strtolower($parts[0] ?? '');
        $cmdArgs = array_slice($parts, 1);

        try {
            match($command) {
                'open', 'goto', 'go' => $this->cmdOpen($cmdArgs),
                'render'             => $this->cmdRender(),
                'html'               => $this->cmdHtml(),
                'click'              => $this->cmdClick($cmdArgs),
                'type'               => $this->cmdType($cmdArgs),
                'eval', 'js'         => $this->cmdEval($cmdArgs),
                'screenshot'         => $this->cmdScreenshot($cmdArgs),
                'submit'             => $this->cmdSubmit($cmdArgs),
                'back'               => $this->cmdBack(),
                'forward'            => $this->cmdForward(),
                'reload', 'refresh'  => $this->cmdReload(),
                'wait'               => $this->cmdWait($cmdArgs),
                'cookies'            => $this->cmdCookies(),
                'title'              => $this->cmdTitle(),
                'url'                => $this->cmdUrl(),
                'save-session'       => $this->cmdSaveSession($cmdArgs),
                'load-session'       => $this->cmdLoadSession($cmdArgs),
                'network'            => $this->cmdNetwork(),
                'clear', 'cls'       => $this->cmdClear(),
                'help', '?'          => $this->cmdHelp(),
                default              => $this->unknownCommand($command),
            };
        } catch (\Throwable $e) {
            echo "\033[31mError: " . $e->getMessage() . "\033[0m\n";
        }
    }

    private function cmdOpen(array $args): void
    {
        if (empty($args[0])) { echo "Usage: open <url>\n"; return; }
        $this->page->goto($args[0]);
        echo $this->page->render();
    }

    private function cmdRender(): void
    {
        echo $this->page->render();
    }

    private function cmdHtml(): void
    {
        echo $this->page->html() . "\n";
    }

    private function cmdClick(array $args): void
    {
        if (empty($args[0])) { echo "Usage: click <selector>\n"; return; }
        $this->page->click($args[0]);
        echo "\033[32mClicked: {$args[0]}\033[0m\n";
    }

    private function cmdType(array $args): void
    {
        if (count($args) < 2) { echo "Usage: type <selector> <text>\n"; return; }
        $this->page->type($args[0], $args[1]);
        echo "\033[32mTyped into: {$args[0]}\033[0m\n";
    }

    private function cmdEval(array $args): void
    {
        if (empty($args[0])) { echo "Usage: eval <expression>\n"; return; }
        $result = $this->page->evaluate($args[0]);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }

    private function cmdScreenshot(array $args): void
    {
        $file = $args[0] ?? 'screenshot.png';
        $this->page->screenshot($file);
    }

    private function cmdSubmit(array $args): void
    {
        $this->page->submit($args[0] ?? 'form');
        echo "\033[32mForm submitted\033[0m\n";
    }

    private function cmdBack(): void
    {
        $this->page->goBack();
        echo "\033[32mNavigated back\033[0m\n";
    }

    private function cmdForward(): void
    {
        $this->page->goForward();
        echo "\033[32mNavigated forward\033[0m\n";
    }

    private function cmdReload(): void
    {
        $this->page->reload();
        echo "\033[32mPage reloaded\033[0m\n";
    }

    private function cmdWait(array $args): void
    {
        if (empty($args[0])) { echo "Usage: wait <selector>\n"; return; }
        $this->page->waitForSelector($args[0]);
        echo "\033[32mSelector found: {$args[0]}\033[0m\n";
    }

    private function cmdCookies(): void
    {
        $cookies = $this->page->getCookies();
        if (empty($cookies)) {
            echo "No cookies.\n";
            return;
        }
        foreach ($cookies as $cookie) {
            printf("  \033[36m%-30s\033[0m = %s\n", $cookie['name'] ?? '', $cookie['value'] ?? '');
        }
    }

    private function cmdTitle(): void
    {
        echo $this->page->getTitle() . "\n";
    }

    private function cmdUrl(): void
    {
        echo $this->page->getUrl() . "\n";
    }

    private function cmdSaveSession(array $args): void
    {
        if (empty($args[0])) { echo "Usage: save-session <name>\n"; return; }
        $this->page->saveSession($args[0], new SessionManager());
    }

    private function cmdLoadSession(array $args): void
    {
        if (empty($args[0])) { echo "Usage: load-session <name>\n"; return; }
        $this->page->loadSession($args[0], new SessionManager());
        echo $this->page->render();
    }

    private function cmdNetwork(): void
    {
        $inspector = $this->page->getNetworkInspector();
        $summary   = $inspector->summary();
        echo "\nRequests: {$summary['total']} | Succeeded: {$summary['succeeded']} | Failed: {$summary['failed']}\n";
        foreach ($inspector->getRequests() as $req) {
            printf("  \033[32m%-7s\033[0m %s\n", $req->method, $req->url);
        }
    }

    private function cmdClear(): void
    {
        echo "\033[2J\033[H";
    }

    private function cmdHelp(): void
    {
        echo "\n\033[1mAvailable commands:\033[0m\n";
        foreach (self::COMMANDS as $cmd => $desc) {
            printf("  \033[32m%-32s\033[0m %s\n", $cmd, $desc);
        }
        echo "\n";
    }

    private function unknownCommand(string $cmd): void
    {
        echo "\033[31mUnknown command: {$cmd}\033[0m. Type 'help' for available commands.\n";
    }

    private function showBanner(): void
    {
        echo "\n\033[1m\033[36mXbrowser v1.0.0\033[0m — Terminal Browser\n";
        echo "Type '\033[32mhelp\033[0m' for available commands, 'quit' to exit.\n\n";
    }

    private function tokenize(string $input): array
    {
        $parts = [];
        preg_match_all('/(?:"([^"]*)")|(?:\'([^\']*)\')|(\S+)/', $input, $matches);
        foreach ($matches[0] as $i => $match) {
            if ($matches[1][$i] !== '') {
                $parts[] = $matches[1][$i];
            } elseif ($matches[2][$i] !== '') {
                $parts[] = $matches[2][$i];
            } else {
                $parts[] = $matches[3][$i];
            }
        }
        return $parts;
    }
}
