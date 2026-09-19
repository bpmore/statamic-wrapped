<?php

namespace Bpmore\Wrapped\Sharing;

/**
 * One YouTube video, as much of it as a share link keeps: the id, and the
 * title and thumbnail YouTube gave for it when the link was made.
 */
final class YouTubeVideo
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?string $thumbnail,
    ) {}

    /**
     * The video id out of a pasted link, or null when it is not one.
     *
     * Every shape people paste: watch?v=, youtu.be/, shorts/, embed/, live/,
     * with or without a scheme, www., m. or music., and with whatever else is
     * in the query string (t=, list=, si=). A bare id is taken as itself.
     */
    public static function idFrom(string $url): ?string
    {
        $url = trim($url);

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
            return $url;
        }

        $parts = parse_url(str_contains($url, '://') ? $url : 'https://'.$url);

        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        $host = strtolower(preg_replace('/^(www|m|music)\./', '', $parts['host']) ?? '');
        $path = $parts['path'] ?? '';

        if ($host === 'youtu.be' && preg_match('#^/([A-Za-z0-9_-]{11})/?$#', $path, $m)) {
            return $m[1];
        }

        if (! in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
            return null;
        }

        if ($path === '/watch') {
            parse_str($parts['query'] ?? '', $query);

            return is_string($v = $query['v'] ?? null) && preg_match('/^[A-Za-z0-9_-]{11}$/', $v) ? $v : null;
        }

        return preg_match('#^/(?:shorts|embed|live|v)/([A-Za-z0-9_-]{11})/?$#', $path, $m) ? $m[1] : null;
    }

    /** The plain watch URL, for showing and for asking YouTube about it. */
    public function url(): string
    {
        return 'https://www.youtube.com/watch?v='.$this->id;
    }

    /**
     * @return array{id: string, title: string, thumbnail: string|null, url: string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'thumbnail' => $this->thumbnail, 'url' => $this->url()];
    }
}
