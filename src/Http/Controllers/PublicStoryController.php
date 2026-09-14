<?php

namespace Bpmore\Wrapped\Http\Controllers;

use Bpmore\Wrapped\Export\Theme;
use Bpmore\Wrapped\Sharing\ShareLinks;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The public page behind a share link. No control panel, no login, no
 * index: reached by the link and by nothing else.
 */
class PublicStoryController extends Controller
{
    public function show(ShareLinks $links, Theme $theme, string $token): View
    {
        $share = $links->resolve($token)
            ?? throw new NotFoundHttpException('There is nothing here.');

        return view('wrapped::share.story', [
            'share' => $share,
            'theme' => $theme,
        ]);
    }
}
