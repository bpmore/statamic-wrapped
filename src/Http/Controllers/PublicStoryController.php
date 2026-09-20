<?php

namespace Bpmore\Wrapped\Http\Controllers;

use Bpmore\Wrapped\Export\SoundtrackResponse;
use Bpmore\Wrapped\Export\Soundtracks;
use Bpmore\Wrapped\Export\Theme;
use Bpmore\Wrapped\Sharing\ShareLinks;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The public page behind a share link. No control panel, no login, no
 * index: reached by the link and by nothing else.
 */
class PublicStoryController extends Controller
{
    public function show(ShareLinks $links, Theme $theme, Soundtracks $soundtracks, string $token): View
    {
        $share = $links->resolve($token)
            ?? throw new NotFoundHttpException('There is nothing here.');

        return view('wrapped::share.story', [
            'share' => $share,
            'theme' => $theme,
            'soundtrack' => $share->soundtrack($soundtracks),
        ]);
    }

    /**
     * The link's soundtrack, streamed for the page's own <audio>. Served
     * only while the link is: revoked, expired or switched off, the music
     * stops with the page, whatever container the file sits in.
     */
    public function music(ShareLinks $links, Soundtracks $soundtracks, string $token): Response
    {
        $share = $links->resolve($token)
            ?? throw new NotFoundHttpException('There is nothing here.');

        $track = $share->soundtrack($soundtracks)
            ?? throw new NotFoundHttpException('There is no music here.');

        return SoundtrackResponse::for($track);
    }
}
