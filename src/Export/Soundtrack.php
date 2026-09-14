<?php

namespace Bpmore\Wrapped\Export;

/**
 * One piece of music a video can have under it.
 */
class Soundtrack
{
    public function __construct(
        public readonly string $handle,
        public readonly string $name,
        public readonly string $description,
        public readonly string $path,
        /** Shipped with the addon, as opposed to supplied by the site. */
        public readonly bool $bundled,
    ) {}

    /**
     * What an <audio> tag should be told. The bundled files are Opus in an
     * M4A box, which is not what ".m4a" usually means, so this is decided
     * from the extension and stated rather than left for the browser to sniff.
     */
    public function mimeType(): string
    {
        return match (strtolower(pathinfo($this->path, PATHINFO_EXTENSION))) {
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
