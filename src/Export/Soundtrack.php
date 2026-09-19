<?php

namespace Bpmore\Wrapped\Export;

use RuntimeException;
use Statamic\Assets\Asset;

/**
 * One piece of music a video can have under it.
 *
 * Most tracks are a file on this server: the bundled ones, the ones a site
 * names in config, and uploads to a local asset container. An upload to any
 * other kind of container (S3, say) is an asset instead, and becomes a file
 * only for as long as FFmpeg needs one; see `localPath()` and `release()`.
 */
class Soundtrack
{
    /** The temporary copy of an asset-backed track, while one exists. */
    protected ?string $copy = null;

    public function __construct(
        public readonly string $handle,
        public readonly string $name,
        public readonly string $description,
        /** A file on this server, or null for a track that lives in an asset container elsewhere. */
        public readonly ?string $path,
        /** Shipped with the addon, as opposed to supplied by the site. */
        public readonly bool $bundled,
        public readonly ?Asset $asset = null,
    ) {
        if ($path === null && $asset === null) {
            throw new RuntimeException('A soundtrack needs a file or an asset.');
        }
    }

    /**
     * A path FFmpeg can open. For a track on this server, its own file. For
     * one elsewhere, a copy made now in the temp directory, the same copy on
     * every call until `release()`.
     */
    public function localPath(): string
    {
        if ($this->path !== null) {
            return $this->path;
        }

        if ($this->copy !== null && is_file($this->copy)) {
            return $this->copy;
        }

        $copy = sys_get_temp_dir().'/wrapped-track-'.$this->handle.'-'.bin2hex(random_bytes(4)).'.'.$this->extension();

        $stream = $this->asset?->stream();

        if (! is_resource($stream)) {
            throw new RuntimeException("The soundtrack [{$this->handle}] could not be read from its container.");
        }

        $target = fopen($copy, 'wb');

        if ($target === false || stream_copy_to_stream($stream, $target) === false) {
            fclose($stream);
            throw new RuntimeException("The soundtrack [{$this->handle}] could not be copied for rendering.");
        }

        fclose($stream);
        fclose($target);

        return $this->copy = $copy;
    }

    /**
     * Remove the temporary copy, if one was made. Safe to call twice, or
     * on a track that never needed one.
     */
    public function release(): void
    {
        if ($this->copy !== null && is_file($this->copy)) {
            @unlink($this->copy);
        }

        $this->copy = null;
    }

    /**
     * The bytes, as a stream, for playing in the control panel. A file on
     * this server is opened; an asset elsewhere is read from its container.
     *
     * @return resource
     */
    public function stream()
    {
        $stream = $this->path !== null ? fopen($this->path, 'rb') : $this->asset?->stream();

        if (! is_resource($stream)) {
            throw new RuntimeException("The soundtrack [{$this->handle}] could not be read.");
        }

        return $stream;
    }

    /** Whether the file is on this server, as opposed to in a container elsewhere. */
    public function isLocal(): bool
    {
        return $this->path !== null;
    }

    public function extension(): string
    {
        $from = $this->path ?? (string) $this->asset?->path();

        return strtolower(pathinfo($from, PATHINFO_EXTENSION));
    }

    /**
     * What an <audio> tag should be told. The bundled files are Opus in an
     * M4A box, which is not what ".m4a" usually means, so this is decided
     * from the extension and stated rather than left for the browser to sniff.
     */
    public function mimeType(): string
    {
        return match ($this->extension()) {
            'mp3' => 'audio/mpeg',
            'm4a', 'mp4' => 'audio/mp4',
            'ogg', 'oga' => 'audio/ogg',
            'opus' => 'audio/ogg; codecs=opus',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
            'aac' => 'audio/aac',
            default => 'application/octet-stream',
        };
    }

    /**
     * @return array{handle: string, name: string, description: string, bundled: bool}
     */
    public function toArray(): array
    {
        return [
            'handle' => $this->handle,
            'name' => $this->name,
            'description' => $this->description,
            'bundled' => $this->bundled,
        ];
    }
}
