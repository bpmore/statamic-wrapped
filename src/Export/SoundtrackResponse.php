<?php

namespace Bpmore\Wrapped\Export;

use Symfony\Component\HttpFoundation\Response;

/**
 * A soundtrack as an HTTP response, for the preview button in the control
 * panel and the music on a public page alike.
 *
 * A file on this server goes out as one, with range requests and all, which
 * is what an <audio> element wants; a track in a container elsewhere is
 * streamed through.
 */
class SoundtrackResponse
{
    public static function for(Soundtrack $track): Response
    {
        $headers = [
            'Content-Type' => $track->mimeType(),
            'Cache-Control' => 'private, max-age=3600',
        ];

        if ($track->path !== null) {
            return response()->file($track->path, $headers);
        }

        return response()->stream(function () use ($track) {
            $stream = $track->stream();
            fpassthru($stream);
            fclose($stream);
        }, 200, $headers);
    }
}
