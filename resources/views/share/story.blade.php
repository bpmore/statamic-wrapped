{{--
    The public page behind a share link. Plain, real text: every card as a
    heading and a sentence, in the site's Wrapped colours. Task 38 turns this
    into the tap-through story; whatever it becomes, this text stays the
    accessible version underneath.

    noindex, and no sitemap entry: reached by the link and by nothing else.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $share->label }} Wrapped · {{ $share->site }}</title>
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0;
            min-height: 100vh;
            background: {{ $theme->background }};
            color: {{ $theme->text }};
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            line-height: 1.4;
        }
        main { max-width: 40rem; margin: 0 auto; padding: 3rem 1.5rem 4rem; }
        .logo { max-width: 12rem; max-height: 4rem; object-fit: contain; display: block; margin-bottom: 2rem; }
        h1 { font-size: 2rem; line-height: 1.15; margin: 0 0 0.25rem; }
        .site { color: {{ $theme->muted }}; margin: 0 0 2.5rem; }
        dl { margin: 0; }
        dt { font-size: 0.875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: {{ $theme->accent ?? $theme->muted }}; }
        dd { margin: 0.25rem 0 1.75rem; font-size: 1.375rem; }
        footer { color: {{ $theme->muted }}; font-size: 0.875rem; margin-top: 3rem; }
    </style>
</head>
<body>
    <main>
        @if ($theme->logo)
            <img class="logo" src="{{ $theme->logo }}" alt="">
        @endif

        <h1>{{ __('wrapped::messages.story.intro', ['period' => $share->label]) }}</h1>
        <p class="site">{{ $share->site }}</p>

        <dl>
            @foreach ($share->cards as $card)
                <dt>{{ $card['heading'] }}</dt>
                <dd>{{ $card['body'] }}</dd>
            @endforeach
        </dl>

        <footer>{{ \Bpmore\Wrapped\Snapshots\Period::outro($share->period_key) }}</footer>
    </main>
</body>
</html>
