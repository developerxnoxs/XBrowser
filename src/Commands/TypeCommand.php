<?php

declare(strict_types=1);

namespace Xbrowser\Commands;

use Xbrowser\Browser\Browser;

class TypeCommand
{
    public function __construct(private readonly Browser $browser) {}

    public function execute(array $args): void
    {
        if (count($args) < 3) {
            fwrite(STDERR, "Usage: Xbrowser type <url> <selector> <text>\n");
            exit(1);
        }

        [$url, $selector, $text] = $args;

        $this->browser->launch();
        $page = $this->browser->newPage();

        try {
            $page->goto($url);
            $page->type($selector, $text);
            $this->browser->getLogger()->success("Typed '{$text}' into '{$selector}'");
        } finally {
            $this->browser->close();
        }
    }
}
