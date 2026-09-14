<?php

namespace Bpmore\Wrapped\Export;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Screenshots a card with headless Chrome.
 *
 * Chrome is driven directly rather than through Puppeteer or Browsershot. Those
 * would put Node and a several-hundred-megabyte browser download between a free
 * addon and a picture of a number, and card layout is only HTML and CSS — see
 * SPEC.md §5. If a site already has Chrome, this works; if it does not, the
 * export is unavailable and the control panel screen carries on regardless.
 */
class ChromeRenderer implements ImageRenderer
{
    /**
     * Where Chrome usually lives, if the site has not said.
     */
    protected const LIKELY_PATHS = [
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        '/Applications/Chromium.app/Contents/MacOS/Chromium',
        '/usr/bin/google-chrome',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
        '/usr/local/bin/chrome',
        '/snap/bin/chromium',
    ];

    public function __construct(protected int $timeout = 30) {}

    public function isAvailable(): bool
    {
        return $this->binary() !== null;
    }

    public function render(string $html, int $width, int $height, bool $transparent = false): string
    {
        $binary = $this->binary();

        if ($binary === null) {
            throw new RuntimeException('No Chrome or Chromium binary was found. Set wrapped.images.chrome to its path.');
        }

        $directory = $this->workspace();
        $page = $directory.'/card.html';
        $image = $directory.'/card.png';

        try {
            file_put_contents($page, $html);

            $process = new Process([
                $binary,
                '--headless=new',
                '--disable-gpu',
                // Containers give Chrome a 64MB /dev/shm, which is not enough.
                '--disable-dev-shm-usage',
                '--hide-scrollbars',
                '--force-device-scale-factor=2',
                // Opaque white unless told otherwise; an overlay needs the
                // alpha channel kept.
                '--default-background-color='.($transparent ? '00000000' : 'ffffffff'),
                "--window-size={$width},{$height}",
                "--screenshot={$image}",
                'file://'.$page,
            ], timeout: $this->timeout);

            $process->mustRun();

            if (! is_file($image)) {
                throw new RuntimeException('Chrome ran but produced no image.');
            }

            $bytes = file_get_contents($image);

            if ($bytes === false || $bytes === '') {
                throw new RuntimeException('Chrome produced an empty image.');
            }

            return $bytes;
        } catch (ProcessFailedException $e) {
            throw new RuntimeException('Chrome failed to render the card: '.$e->getMessage(), previous: $e);
        } finally {
            @unlink($page);
            @unlink($image);
            @rmdir($directory);
        }
    }

    protected function binary(): ?string
    {
        $configured = config('wrapped.images.chrome');

        if (is_string($configured) && $configured !== '') {
            return is_executable($configured) ? $configured : null;
        }

        foreach (self::LIKELY_PATHS as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function workspace(): string
    {
        $directory = sys_get_temp_dir().'/wrapped-'.bin2hex(random_bytes(8));

        if (! mkdir($directory, 0700) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create a temporary directory to render into.');
        }

        return $directory;
    }
}
