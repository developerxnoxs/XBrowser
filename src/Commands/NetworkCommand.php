<?php

declare(strict_types=1);

namespace Xbrowser\Commands;

use Xbrowser\Browser\Browser;

class NetworkCommand
{
    public function __construct(private readonly Browser $browser) {}

    public function execute(array $args): void
    {
        if (empty($args[0])) {
            fwrite(STDERR, "Usage: Xbrowser network <url>\n");
            exit(1);
        }

        $url = $args[0];

        $this->browser->launch();
        $page = $this->browser->newPage();

        try {
            $inspector = $page->getNetworkInspector();
            $page->goto($url);

            $requests  = $inspector->getRequests();
            $responses = $inspector->getResponses();
            $summary   = $inspector->summary();

            echo "\n\033[1mNetwork Inspector\033[0m\n";
            echo str_repeat('─', 60) . "\n";
            printf("Total requests: %d | Succeeded: %d | Failed: %d\n\n",
                $summary['total'], $summary['succeeded'], $summary['failed']
            );

            echo "\033[1mRequests:\033[0m\n";
            foreach ($requests as $req) {
                $method = $req->method;
                $color  = match($method) {
                    'GET'    => "\033[32m",
                    'POST'   => "\033[33m",
                    'DELETE' => "\033[31m",
                    default  => "\033[36m",
                };
                printf("  %s%-7s\033[0m %s\n", $color, $method, $req->url);
            }

            echo "\n\033[1mResponses:\033[0m\n";
            foreach ($responses as $resp) {
                $color  = $resp->isSuccess() ? "\033[32m" : "\033[31m";
                printf("  %s%d\033[0m %s\n", $color, $resp->statusCode, $resp->url);
            }

        } finally {
            $this->browser->close();
        }
    }
}
