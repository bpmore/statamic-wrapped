{{--
    One card, sized for social.

    Deliberately self-contained: inline styles, system fonts, no network. The
    renderer loads this from a file:// URL with no internet, so anything fetched
    would simply be missing from the picture.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $heading }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            width: {{ $width }}px;
            height: {{ $height }}px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 72px;
            background: {{ $theme->background }};
            color: {{ $theme->text }};
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .heading {
            font-size: 30px;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: {{ $theme->accent ?? $theme->muted }};
        }

        .body {
            font-size: {{ mb_strlen($body) > 90 ? 52 : 68 }}px;
            font-weight: 700;
            line-height: 1.18;
            letter-spacing: -0.02em;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            font-size: 26px;
            color: {{ $theme->muted }};
        }

        .period { font-weight: 600; color: {{ $theme->text }}; }
    </style>
</head>
<body>
    <p class="heading">{{ $heading }}</p>

    <p class="body">{{ $body }}</p>

    <div class="footer">
        <span class="period">{{ $period }}</span>
        <span>{{ $site }}</span>
    </div>
</body>
</html>
