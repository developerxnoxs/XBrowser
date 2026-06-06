<?php

declare(strict_types=1);

namespace Xbrowser\Commands;

use Xbrowser\Browser\Browser;

class RecordCommand
{
    public function __construct(private readonly Browser $browser) {}

    public function execute(array $args): void
    {
        $output = $args[0] ?? 'recorded_script.php';

        $this->browser->launch();
        $page = $this->browser->newPage();
        $page->startRecording();

        echo "\n\033[1mXbrowser Recorder\033[0m\n";
        echo "Recording actions. Type commands and press Enter.\n";
        echo "Type 'stop' to finish and generate the script.\n\n";

        while (true) {
            echo "\033[33m[rec]\033[0m > ";
            $input = trim((string) fgets(STDIN));

            if ($input === '' || $input === 'quit' || $input === 'stop') {
                break;
            }

            $parts   = $this->parseInput($input);
            $command = $parts[0] ?? '';
            $cmdArgs = array_slice($parts, 1);

            try {
                match($command) {
                    'open', 'goto' => $page->goto($cmdArgs[0] ?? ''),
                    'click'        => $page->click($cmdArgs[0] ?? ''),
                    'type'         => $page->type($cmdArgs[0] ?? '', $cmdArgs[1] ?? ''),
                    'eval'         => print(json_encode($page->evaluate($cmdArgs[0] ?? '')) . "\n"),
                    'render'       => print($page->render()),
                    'screenshot'   => $page->screenshot($cmdArgs[0] ?? 'screenshot.png'),
                    'submit'       => $page->submit($cmdArgs[0] ?? 'form'),
                    'back'         => $page->goBack(),
                    'forward'      => $page->goForward(),
                    'reload'       => $page->reload(),
                    'wait'         => $page->waitForSelector($cmdArgs[0] ?? 'body'),
                    default        => print("\033[31mUnknown command: {$command}\033[0m\n"),
                };
            } catch (\Throwable $e) {
                echo "\033[31mError: " . $e->getMessage() . "\033[0m\n";
            }
        }

        $script = $page->stopRecording();

        file_put_contents($output, $script);
        echo "\n\033[32mScript saved to: {$output}\033[0m\n\n";
        echo $script . "\n";

        $this->browser->close();
    }

    private function parseInput(string $input): array
    {
        $parts = [];
        preg_match_all('/(?:"([^"]*)")|(\S+)/', $input, $matches);
        foreach ($matches[0] as $i => $match) {
            $parts[] = $matches[1][$i] !== '' ? $matches[1][$i] : $matches[2][$i];
        }
        return $parts;
    }
}
