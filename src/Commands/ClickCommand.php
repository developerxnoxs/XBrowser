<?php

declare(strict_types=1);

namespace Xbrowser\Commands;

use Xbrowser\Browser\Browser;

class ClickCommand
{
    public function __construct(private readonly Browser $browser) {}

    public function execute(array $args): void
    {
        if (count($args) < 2) {
            fwrite(STDERR, "Usage: Xbrowser click <url> <selector>\n");
            exit(1);
        }

        [$url, $selector] = $args;

        $this->browser->launch();
        $page = $this->browser->newPage();

        try {
            $page->goto($url);
            $page->click($selector);
            echo $page->render();
        } finally {
            $this->browser->close();
        }
    }
}
