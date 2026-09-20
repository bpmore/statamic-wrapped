<?php

namespace Bpmore\Wrapped\Sharing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Asking YouTube about a pasted link, once, when a share link is made.
 *
 * Uses oEmbed, which needs no key and no quota, and gives back the title
 * and a thumbnail. The Data API's search was the alternative, and is capped
 * at 100 searches a day per key; a paste box does not need it. The lookup
 * doubles as the check that the video exists and can be embedded: a private
 * or removed video is a 401 or 404 here, and a link is not made for it.
 */
class YouTube
{
    public const OEMBED = 'https://www.youtube.com/oembed';

    /**
     * The video a pasted link points at, or a validation error on `music`
     * saying why not. Two ways to fail, told apart for the editor: the text
     * was not a YouTube link at all, or it was one and YouTube did not know it.
     *
     * @throws ValidationException
     */
    public function resolve(string $url): YouTubeVideo
    {
        $id = YouTubeVideo::idFrom($url);

        if ($id === null) {
            throw ValidationException::withMessages([
                'music' => 'That does not look like a YouTube link. Paste the address of a video, such as https://www.youtube.com/watch?v=… or https://youtu.be/….',
            ]);
        }

        $video = $this->lookup($id);

        if ($video === null) {
            throw ValidationException::withMessages([
                'music' => 'YouTube did not recognize that video. It may be private, removed, or not allowed to be embedded.',
            ]);
        }

        return $video;
    }

    /**
     * What YouTube says about a video id, or null when it says no or nothing.
     */
    public function lookup(string $id): ?YouTubeVideo
    {
        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->get(self::OEMBED, ['url' => 'https://www.youtube.com/watch?v='.$id, 'format' => 'json']);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $title = $response->json('title');
        $thumbnail = $response->json('thumbnail_url');

        if (! is_string($title) || $title === '') {
            return null;
        }

        return new YouTubeVideo($id, mb_substr($title, 0, 191), is_string($thumbnail) ? mb_substr($thumbnail, 0, 512) : null);
    }
}
