<?php

use Bpmore\Wrapped\Sharing\YouTube;
use Bpmore\Wrapped\Sharing\YouTubeVideo;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/*
 * A pasted YouTube link, read for its video id and asked about once. No
 * key, no quota: oEmbed. The network is faked throughout; nothing here
 * talks to YouTube.
 */

it('reads the video id out of every shape of link people paste', function (string $url) {
    expect(YouTubeVideo::idFrom($url))->toBe('dQw4w9WgXcQ');
})->with([
    'watch' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'watch, no www' => 'https://youtube.com/watch?v=dQw4w9WgXcQ',
    'watch, no scheme' => 'youtube.com/watch?v=dQw4w9WgXcQ',
    'watch, mobile' => 'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
    'watch, music' => 'https://music.youtube.com/watch?v=dQw4w9WgXcQ&list=RDAMVM',
    'watch, with a timestamp' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=43s',
    'watch, id not first' => 'https://www.youtube.com/watch?feature=shared&v=dQw4w9WgXcQ',
    'short link' => 'https://youtu.be/dQw4w9WgXcQ',
    'short link, with tracking' => 'https://youtu.be/dQw4w9WgXcQ?si=abc123',
    'shorts' => 'https://www.youtube.com/shorts/dQw4w9WgXcQ',
    'embed' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
    'embed, no-cookie' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
    'live' => 'https://www.youtube.com/live/dQw4w9WgXcQ',
    'bare id' => 'dQw4w9WgXcQ',
    'with spaces around' => '  https://youtu.be/dQw4w9WgXcQ  ',
]);

it('does not read an id into things that are not YouTube links', function (string $url) {
    expect(YouTubeVideo::idFrom($url))->toBeNull();
})->with([
    'another site' => 'https://vimeo.com/123456789',
    'a lookalike host' => 'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ',
    'a channel' => 'https://www.youtube.com/@someone',
    'a playlist' => 'https://www.youtube.com/playlist?list=PL123',
    'watch with no id' => 'https://www.youtube.com/watch',
    'an id of the wrong length' => 'https://youtu.be/short',
    'a sentence' => 'that one song from the summer',
    'nothing' => '',
]);

function oembedOk(string $title = 'Never Gonna Give You Up', ?string $thumb = 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg'): void
{
    Http::fake([YouTube::OEMBED.'*' => Http::response(array_filter([
        'title' => $title,
        'author_name' => 'Rick Astley',
        'thumbnail_url' => $thumb,
        'type' => 'video',
    ]))]);
}

it('asks oEmbed about the id and keeps the title and thumbnail', function () {
    oembedOk();

    $video = app(YouTube::class)->resolve('https://youtu.be/dQw4w9WgXcQ?si=x');

    expect($video->id)->toBe('dQw4w9WgXcQ')
        ->and($video->title)->toBe('Never Gonna Give You Up')
        ->and($video->thumbnail)->toBe('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg')
        ->and($video->url())->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), YouTube::OEMBED)
        && $request['url'] === 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'
        && $request['format'] === 'json');
});

it('is a validation error on music when the text is not a YouTube link, without asking YouTube', function () {
    Http::fake();

    try {
        app(YouTube::class)->resolve('https://vimeo.com/123');
        $this->fail('Expected a validation error.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('music')
            ->and($e->errors()['music'][0])->toContain('does not look like a YouTube link');
    }

    Http::assertNothingSent();
});

it('is a validation error on music when YouTube does not know the video', function (int $status) {
    Http::fake([YouTube::OEMBED.'*' => Http::response('', $status)]);

    try {
        app(YouTube::class)->resolve('https://youtu.be/dQw4w9WgXcQ');
        $this->fail('Expected a validation error.');
    } catch (ValidationException $e) {
        expect($e->errors()['music'][0])->toContain('did not recognise that video');
    }
})->with([401, 403, 404, 500]);

it('is the same validation error when YouTube cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    expect(fn () => app(YouTube::class)->resolve('https://youtu.be/dQw4w9WgXcQ'))
        ->toThrow(ValidationException::class);
});

it('treats an answer with no title as no answer', function () {
    Http::fake([YouTube::OEMBED.'*' => Http::response(['type' => 'video'])]);

    expect(app(YouTube::class)->lookup('dQw4w9WgXcQ'))->toBeNull();
});

it('does without a thumbnail when none is given', function () {
    oembedOk(thumb: null);

    expect(app(YouTube::class)->lookup('dQw4w9WgXcQ')?->thumbnail)->toBeNull();
});
