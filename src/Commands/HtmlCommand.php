<?php

declare(strict_types=1);

namespace Xbrowser\Commands;

use Xbrowser\Browser\Browser;

class HtmlCommand
{
    public function __construct(private readonly Browser $browser) {}

    public function execute(array $args): void
    {
        if (empty($args[0])) {
            fwrite(STDERR, "Usage: Xbrowser html <url>\n");
            exit(1);
        }

        $url = $args[0];

        $this->browser->launch();
        $page = $this->browser->newPage();

        try {
            $page->goto($url);
            echo $page->html();
        } finally {
            $this->browser->close();
        }
    }
}
