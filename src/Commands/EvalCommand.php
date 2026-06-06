<?php

declare(strict_types=1);

namespace Xbrowser\Commands;

use Xbrowser\Browser\Browser;

class EvalCommand
{
    public function __construct(private readonly Browser $browser) {}

    public function execute(array $args): void
    {
        if (count($args) < 2) {
            fwrite(STDERR, "Usage: Xbrowser eval <url> <expression>\n");
            exit(1);
        }

        $url        = $args[0];
        $expression = $args[1];

        $this->browser->launch();
        $page = $this->browser->newPage();

        try {
            $page->goto($url);
            $result = $page->evaluate($expression);
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        } finally {
            $this->browser->close();
        }
    }
}
