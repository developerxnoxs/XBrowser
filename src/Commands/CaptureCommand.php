<?php

declare(strict_types=1);

namespace Xbrowser\Commands;

use Xbrowser\Browser\Browser;

/**
 * Xbrowser capture <url> [--output=result.json] [--filter=domain] [--cred=keyword] [--post-only]
 *
 * Merekam semua request/response dari URL yang diberikan
 * dan menyimpannya ke file JSON.
 */
class CaptureCommand
{
    public function __construct(private readonly Browser $browser) {}

    public function execute(array $args): void
    {
        // Ambil URL (argumen pertama yang bukan flag)
        $url = null;
        foreach ($args as $a) {
            if (!str_starts_with($a, '--')) {
                $url = $a;
                break;
            }
        }

        if (!$url) {
            fwrite(STDERR, "Usage: Xbrowser capture <url> [--output=result.json] [--filter=domain] [--cred=keyword] [--post-only]\n");
            exit(1);
        }

        // Parse flags
        $output   = $this->flag($args, 'output',  'capture_result.json');
        $filter   = $this->flag($args, 'filter',  '');
        $credKw   = $this->flag($args, 'cred',    '');
        $postOnly = in_array('--post-only', $args, true);

        $this->browser->launch([
            'headless'   => true,
            'no_sandbox' => true,
        ]);

        $page = $this->browser->newPage();

        try {
            $capture = $page->startCapture();

            if ($filter) {
                foreach (explode(',', $filter) as $f) {
                    $capture->filterDomain(trim($f));
                }
            }

            if ($credKw) {
                $capture->scanCredentials(
                    array_map('trim', explode(',', $credKw))
                );
            }

            echo "\n\033[1mXbrowser — Network Capture\033[0m\n";
            echo str_repeat('─', 60) . "\n";
            printf("URL    : %s\n", $url);
            printf("Filter : %s\n", $filter ?: '(semua)');
            printf("Output : %s\n\n", $output);

            $page->goto($url);

            // Beri waktu untuk lazy-loaded request selesai
            usleep(2_000_000);
            $page->cdpPump(500);

            // Ambil body yang masih tertunda
            $capture->fetchPendingBodies();

            $all     = $capture->getAll();
            $summary = $capture->summary();

            // Tampilkan di terminal
            echo "\033[1mNetwork Requests:\033[0m\n";
            foreach ($all as $entry) {
                if ($postOnly && $entry->method !== 'POST') {
                    continue;
                }

                $color = match ($entry->method) {
                    'GET'    => "\033[32m",
                    'POST'   => "\033[33m",
                    'DELETE' => "\033[31m",
                    default  => "\033[36m",
                };

                $credFlag = $entry->hasCredentials ? ' \033[35m[CRED]\033[0m' : '';
                printf("  %s%-7s\033[0m %d  %s%s\n",
                    $color,
                    $entry->method,
                    $entry->responseStatus,
                    mb_substr($entry->url, 0, 90),
                    $credFlag
                );

                if ($entry->hasCredentials && $entry->postData) {
                    printf("         postData: %s\n", mb_substr($entry->postData, 0, 200));
                }
            }

            echo "\n" . str_repeat('─', 60) . "\n";
            printf("Total: %d | POST: %d | Kredensial: %d | Sukses: %d | Error: %d\n",
                $summary['total'],
                $summary['posts'],
                $summary['withCredentials'],
                $summary['succeeded'],
                $summary['failed']
            );

            $capture->saveJson($output);
            printf("\n\033[32m✓\033[0m Tersimpan ke: %s\n\n", $output);

        } finally {
            $this->browser->close();
        }
    }

    private function flag(array $args, string $name, string $default): string
    {
        foreach ($args as $a) {
            if (str_starts_with($a, "--{$name}=")) {
                return substr($a, strlen("--{$name}="));
            }
        }
        return $default;
    }
}
