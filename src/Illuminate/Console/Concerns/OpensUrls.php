<?php

namespace Illuminate\Console\Concerns;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Env;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * @mixin Command
 */
trait OpensUrls
{
    protected static function systemOsFamily(): string
    {
        return PHP_OS_FAMILY;
    }

    /**
     * Open the URL in the user's browser.
     *
     * @param  string  $url
     * @return void
     */
    protected function open($url)
    {
        ($this->urlOpener ?? function ($url) {
            if (Env::get('ARTISAN_DOCS_OPEN_STRATEGY')) {
                $this->openViaCustomStrategy($url);
            } elseif (in_array(static::systemOsFamily(), ['Darwin', 'Windows', 'Linux'])) {
                $this->openViaBuiltInStrategy($url);
            } else {
                $this->components->warn('Unable to open the URL on your system. You will need to open it yourself or create a custom opener for your system.');
            }
        })($url);
    }

    /**
     * Open the URL via a custom strategy.
     *
     * @param  string  $url
     * @return void
     */
    protected function openViaCustomStrategy($url)
    {
        try {
            $command = require Env::get('ARTISAN_DOCS_OPEN_STRATEGY');
        } catch (\Throwable) {
            $command = null;
        }

        if (! is_callable($command)) {
            $this->components->warn('Unable to open the URL with your custom strategy. You will need to open it yourself.');

            return;
        }

        $command($url);
    }

    /**
     * Open the URL via the built in strategy.
     *
     * @param  string  $url
     * @return void
     */
    protected function openViaBuiltInStrategy($url)
    {
        if (static::systemOsFamily() === 'Windows') {
            $process = tap(Process::fromShellCommandline(escapeshellcmd("start {$url}")))->run();

            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            return;
        }

        $binary = Collection::make(match (static::systemOsFamily()) {
            'Darwin' => ['open'],
            'Linux' => ['xdg-open', 'wslview'],
        })->first(fn ($binary) => (new ExecutableFinder)->find($binary) !== null);

        if ($binary === null) {
            $this->components->warn('Unable to open the URL on your system. You will need to open it yourself or create a custom opener for your system.');

            return;
        }

        $process = tap(Process::fromShellCommandline(escapeshellcmd("{$binary} {$url}")))->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
