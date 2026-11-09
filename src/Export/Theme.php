<?php

namespace Bpmore\Wrapped\Export;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Statamic\Assets\Asset;
use Throwable;

/**
 * The look of everything exported: card images, video frames, the story.
 *
 * A site chooses a background and, optionally, an accent and a logo. It does
 * not choose the text color, and that is the point: text is worked out from
 * the background so it always clears WCAG AA's 4.5:1, whatever a site picks.
 * The muted gray used for labels is derived the same way. A site cannot
 * accidentally ship an unreadable Wrapped.
 *
 * There is no "site color" to read in Statamic — no standard place a brand
 * color lives — so this is config, not guesswork. The logo does have a
 * standard home, `statamic.cp.custom_logo_url`, and is picked up from there
 * unless the site says otherwise.
 */
class Theme
{
    public const DEFAULT_BACKGROUND = '#16161d';

    protected const LIGHT = '#f7f7f8';

    protected const DARK = '#16161d';

    /** WCAG 2.1 AA for normal text. */
    protected const MINIMUM_CONTRAST = 4.5;

    public function __construct(
        public readonly string $background,
        public readonly string $text,
        public readonly string $muted,
        public readonly ?string $accent,
        /** A data: URI, so a render with no network can still show it. */
        public readonly ?string $logo,
    ) {}

    /**
     * From the config file alone, ignoring anything saved on the settings
     * screen. What a site gets before anyone has opened that screen.
     */
    public static function fromConfig(): self
    {
        return static::fromValues((array) config('wrapped.theme', []));
    }

    /**
     * From the config file with the settings screen laid over it: whatever
     * somebody saved there wins, and the file answers for the rest.
     */
    public static function fromSettings(): self
    {
        return static::fromValues(ThemeSettings::values());
    }

    /**
     * @param  array{background?: mixed, accent?: mixed, logo?: mixed}  $values
     */
    public static function fromValues(array $values): self
    {
        $background = static::color($values['background'] ?? null) ?? self::DEFAULT_BACKGROUND;
        $text = static::textFor($background);

        return new self(
            background: $background,
            text: $text,
            muted: static::mutedFor($background, $text),
            accent: static::accentFor(static::color($values['accent'] ?? null), $background),
            logo: static::logoFrom($values['logo'] ?? config('statamic.cp.custom_logo_url')),
        );
    }

    public function isDark(): bool
    {
        return static::luminance($this->background) < 0.5;
    }

    /**
     * @return array{background: string, text: string, muted: string, accent: string|null, logo: string|null}
     */
    public function toArray(): array
    {
        return [
            'background' => $this->background,
            'text' => $this->text,
            'muted' => $this->muted,
            'accent' => $this->accent,
            'logo' => $this->logo,
        ];
    }

    /**
     * Light or dark text, whichever reads better on this background.
     *
     * The soft off-white and near-black are preferred for the look. But a
     * mid-brightness background — a saturated rose, say — can defeat both:
     * neither reaches 4.5:1. Pure white or pure black always does (the worst
     * case, around 18% luminance, still clears 4.56:1), so those are the
     * fallback rather than shipping text that fails AA.
     */
    protected static function textFor(string $background): string
    {
        $soft = static::contrast(self::LIGHT, $background) >= static::contrast(self::DARK, $background)
            ? self::LIGHT
            : self::DARK;

        if (static::contrast($soft, $background) >= self::MINIMUM_CONTRAST) {
            return $soft;
        }

        return static::contrast('#ffffff', $background) >= static::contrast('#000000', $background)
            ? '#ffffff'
            : '#000000';
    }

    /**
     * A quieter version of the text color for labels: the text blended toward
     * the background, but only as far as AA allows. Starts at a 45% blend and
     * backs off toward the text until 4.5:1 holds.
     */
    protected static function mutedFor(string $background, string $text): string
    {
        for ($mix = 0.45; $mix >= 0.0; $mix -= 0.05) {
            $candidate = static::blend($text, $background, $mix);

            if (static::contrast($candidate, $background) >= self::MINIMUM_CONTRAST) {
                return $candidate;
            }
        }

        return $text;
    }

    /**
     * An accent is only used where it can be read. One that fails AA against
     * the background is dropped, with a note in the log, rather than used
     * somewhere it would be illegible.
     */
    protected static function accentFor(?string $accent, string $background): ?string
    {
        if ($accent === null) {
            return null;
        }

        if (static::contrast($accent, $background) < self::MINIMUM_CONTRAST) {
            Log::warning('[wrapped] The theme accent does not have enough contrast against the background and was not used.', [
                'accent' => $accent,
                'background' => $background,
                'contrast' => round(static::contrast($accent, $background), 2),
            ]);

            return null;
        }

        return $accent;
    }

    /**
     * The logo as a data: URI, or null.
     *
     * Rendering happens from a file:// page with no network, so a URL would
     * simply be a missing image, and the public story page shows the same
     * logo to anyone with a link. So the logo is read from exactly two
     * places: an asset in the container the settings screen offers, through
     * that container's disk; or a file under the site's public directory,
     * given as a path or as its URL on this site. Nothing else on disk is
     * read (the value can be typed into a text field on a site with no
     * container, and the result is shown to the public), and nothing is
     * fetched from another host. The data URI is cached, keyed by the file
     * and when it last changed, so a public page view does not re-read it.
     */
    protected static function logoFrom(mixed $logo): ?string
    {
        if ($logo instanceof Asset) {
            return static::logoFromAsset($logo);
        }

        if (! is_string($logo) || trim($logo) === '') {
            return null;
        }

        $path = static::publicFile(trim($logo));

        if ($path === null) {
            return null;
        }

        return static::remembered('file:'.$path.':'.(string) @filemtime($path), function () use ($path): ?string {
            try {
                $bytes = file_get_contents($path);
                $mime = mime_content_type($path) ?: '';
            } catch (Throwable) {
                return null;
            }

            if ($bytes === false) {
                return null;
            }

            // finfo calls an SVG without an XML declaration text; the bytes know better.
            if (! str_starts_with($mime, 'image/')) {
                $mime = static::mimeFromBytes($bytes);
            }

            return static::dataUri($bytes, $mime);
        });
    }

    /**
     * An asset chosen on the settings screen, read through its container's
     * disk, wherever that disk is. Only the container the screen offers
     * counts: a value that names another container is not one the screen
     * could have produced.
     */
    protected static function logoFromAsset(Asset $asset): ?string
    {
        try {
            $offered = SavedSettings::formContainer()?->handle();

            if ($offered === null || $asset->containerHandle() !== $offered) {
                return null;
            }

            $identity = 'asset:'.$asset->containerHandle().'::'.$asset->path().':'.$asset->lastModified()->timestamp;

            return static::remembered($identity, function () use ($asset): ?string {
                $bytes = $asset->contents();

                return is_string($bytes) ? static::dataUri($bytes, static::mimeFromBytes($bytes)) : null;
            });
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The file a path or URL names, if and only if it is a regular file
     * under the site's public directory once every symlink and `..` is
     * resolved. `/img/logo.svg` is tried as a URL on this site first and as
     * a path on disk second; `https://this-site/img/logo.svg` is mapped to
     * disk; a URL to any other host, or a path anywhere else, is nothing.
     */
    protected static function publicFile(string $logo): ?string
    {
        $candidates = [];

        if (str_starts_with($logo, '/')) {
            $relative = (string) parse_url($logo, PHP_URL_PATH);
            $candidates[] = public_path(ltrim($relative, '/'));
            $candidates[] = $logo;
        } elseif (function_exists('url') && str_starts_with($logo, rtrim((string) url('/'), '/').'/')) {
            $relative = (string) parse_url(substr($logo, strlen(rtrim((string) url('/'), '/'))), PHP_URL_PATH);
            $candidates[] = public_path(ltrim($relative, '/'));
        } else {
            return null;
        }

        $root = realpath(public_path());

        if ($root === false) {
            return null;
        }

        foreach ($candidates as $candidate) {
            if (! is_file($candidate)) {
                continue;
            }

            $real = realpath($candidate);

            if ($real !== false && str_starts_with($real, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                return $real;
            }
        }

        return null;
    }

    /**
     * The data URI for one source, computed once a day per version of the
     * file. A cache that is down costs a re-read, never the logo.
     *
     * @param  Closure(): ?string  $compute
     */
    protected static function remembered(string $identity, Closure $compute): ?string
    {
        try {
            return Cache::remember('wrapped.theme.logo.'.sha1($identity), now()->addDay(), $compute);
        } catch (Throwable) {
            return $compute();
        }
    }

    protected static function dataUri(string $bytes, string $mime): ?string
    {
        if ($bytes === '' || ! str_starts_with($mime, 'image/')) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    protected static function mimeFromBytes(string $bytes): string
    {
        return match (true) {
            str_starts_with($bytes, "\x89PNG") => 'image/png',
            str_starts_with($bytes, "\xFF\xD8") => 'image/jpeg',
            str_starts_with($bytes, 'GIF8') => 'image/gif',
            str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP' => 'image/webp',
            str_contains(substr($bytes, 0, 512), '<svg') => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }

    /**
     * A #rgb or #rrggbb color, normalized to #rrggbb, or null for anything
     * else. Nothing that is not a color gets anywhere near a stylesheet.
     */
    protected static function color(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        if (preg_match('/^#([0-9a-f]{3})$/', $value, $m)) {
            return '#'.$m[1][0].$m[1][0].$m[1][1].$m[1][1].$m[1][2].$m[1][2];
        }

        return preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : null;
    }

    /**
     * WCAG relative luminance.
     */
    protected static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(function (string $channel) {
            $c = hexdec($channel) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * WCAG contrast ratio, 1:1 to 21:1.
     */
    public static function contrast(string $a, string $b): float
    {
        $la = static::luminance($a);
        $lb = static::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * $a moved $mix of the way toward $b, in plain RGB.
     */
    protected static function blend(string $a, string $b, float $mix): string
    {
        $ca = array_map('hexdec', str_split(ltrim($a, '#'), 2));
        $cb = array_map('hexdec', str_split(ltrim($b, '#'), 2));

        return sprintf('#%02x%02x%02x', ...array_map(
            fn (int $x, int $y) => (int) round($x + ($y - $x) * $mix),
            $ca,
            $cb,
        ));
    }
}
