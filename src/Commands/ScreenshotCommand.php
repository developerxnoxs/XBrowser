<?php

declare(strict_types=1);

namespace Xbrowser\Commands;

use Xbrowser\Browser\Browser;

class ScreenshotCommand
{
    public function __construct(private readonly Browser $browser) {}

    public function execute(array $args): void
    {
        if (count($args) < 2) {
            fwrite(STDERR, "Usage: Xbrowser screenshot <url> <output.png>\n");
            exit(1);
        }

        $url    = $args[0];
        $output = $args[1];
        $format = str_ends_with(strtolower($output), '.jpg') ? 'jpeg' : 'png';

        $this->browser->launch();
        $page = $this->browser->newPage();

        try {
            $page->goto($url);
            $page->screenshot($output, $format);
        } finally {
            $this->browser->close();
        }
    }
}
