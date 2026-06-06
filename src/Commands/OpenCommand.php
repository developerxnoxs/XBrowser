<?php

declare(strict_types=1);

namespace Xbrowser\Commands;

use Xbrowser\Browser\Browser;

class OpenCommand
{
    public function __construct(private readonly Browser $browser) {}

    public function execute(array $args): void
    {
        if (empty($args[0])) {
            fwrite(STDERR, "Usage: Xbrowser open <url>\n");
            exit(1);
        }

        $url = $args[0];

        $this->browser->launch();
        $page = $this->browser->newPage();

        try {
            $page->goto($url);
            $rendered = $page->render();
            echo $rendered;
        } finally {
            $this->browser->close();
        }
    }
}
