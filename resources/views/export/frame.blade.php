{{--
    One frame of the video: 1080x1920, portrait, for Reels, Stories and TikTok.

    Four kinds, one template. The intro says whose year it is, the cards say
    one fact each, the outro closes, and the footer is a transparent overlay
    the renderer pins over everything so it never moves. Same rules as the
    card image: inline styles, system fonts, nothing fetched.

    Text is set larger and lower than instinct suggests: on a phone the top
    tenth is under the app's own chrome and the bottom fifth is under its
    caption and buttons. The middle is the only part reliably seen.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $heading ?? $period }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            width: {{ $width }}px;
            height: {{ $height }}px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 240px 96px 300px;
            background: {{ $kind === 'footer' ? 'transparent' : '#16161d' }};
            color: #f7f7f8;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .heading {
            font-size: 40px;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #8b8b96;
            margin-bottom: 48px;
        }

        .body {
            font-size: {{ mb_strlen($body ?? '') > 80 ? 76 : 96 }}px;
            font-weight: 700;
            line-height: 1.12;
            letter-spacing: -0.025em;
        }

        .title {
            font-size: 120px;
            font-weight: 700;
            line-height: 1.05;
            letter-spacing: -0.03em;
        }

        .subtitle {
            font-size: 44px;
            color: #8b8b96;
            margin-top: 40px;
        }

        .footer {
            position: absolute;
            left: 96px;
            right: 96px;
            bottom: 200px;
            display: flex;
            justify-content: space-between;
            font-size: 32px;
            color: #8b8b96;
        }

        .period { font-weight: 600; color: #f7f7f8; }
    </style>
</head>
<body>
    {{--
        The footer is its own kind, rendered once on a transparent ground and
        laid over the whole video by the renderer. It never moves, whatever
        the frames underneath are doing.
    --}}
    @if ($kind === 'footer')
        <div class="footer">
            <span class="period">{{ $period }}</span>
            <span>{{ $site }}</span>
        </div>
    @elseif ($kind === 'intro')
        <p class="title">{{ $period }}<br>Wrapped</p>
        <p class="subtitle">{{ $site }}</p>
    @elseif ($kind === 'outro')
        <p class="title">{{ $outro }}</p>
    @else
        <p class="heading">{{ $heading }}</p>
        <p class="body">{{ $body }}</p>
    @endif
</body>
</html>
