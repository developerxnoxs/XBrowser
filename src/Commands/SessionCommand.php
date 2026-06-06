<?php

declare(strict_types=1);

namespace Xbrowser\Commands;

use Xbrowser\Browser\Browser;
use Xbrowser\Utils\SessionManager;

class SessionCommand
{
    public function __construct(private readonly Browser $browser) {}

    public function executeSave(array $args): void
    {
        if (count($args) < 2) {
            fwrite(STDERR, "Usage: Xbrowser save-session <url> <name>\n");
            exit(1);
        }

        [$url, $name] = $args;
        $sessions = new SessionManager();

        $this->browser->launch();
        $page = $this->browser->newPage();

        try {
            $page->goto($url);
            $page->saveSession($name, $sessions);
        } finally {
            $this->browser->close();
        }
    }

    public function executeLoad(array $args): void
    {
        if (empty($args[0])) {
            fwrite(STDERR, "Usage: Xbrowser load-session <name>\n");
            exit(1);
        }

        $name     = $args[0];
        $sessions = new SessionManager();

        $this->browser->launch();
        $page = $this->browser->newPage();

        try {
            $page->loadSession($name, $sessions);
            echo $page->render();
        } finally {
            $this->browser->close();
        }
    }
}
