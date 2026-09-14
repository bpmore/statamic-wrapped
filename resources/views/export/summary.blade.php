{{-- Every card on one image. Taller than the single cards, same rules. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $period }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            width: {{ $width }}px;
            min-height: {{ $height }}px;
            padding: 64px;
            background: {{ $theme->background }};
            color: {{ $theme->text }};
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        h1 {
            font-size: 56px;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }

        .site { font-size: 26px; color: {{ $theme->muted }}; margin-bottom: 48px; }

        .logo { height: 56px; width: auto; max-width: 40%; object-fit: contain; margin-bottom: 32px; display: block; }

        .cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px 40px;
        }

        .heading {
            font-size: 20px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: {{ $theme->accent ?? $theme->muted }};
            margin-bottom: 6px;
        }

        .body { font-size: 30px; font-weight: 600; line-height: 1.3; }
    </style>
</head>
<body>
    @if ($theme->logo)
        <img class="logo" src="{{ $theme->logo }}" alt="">
    @endif
    <h1>{{ $period }} Wrapped</h1>
    <p class="site">{{ $site }}</p>

    <div class="cards">
        @foreach ($cards as $card)
            <div>
                <p class="heading">{{ $card['heading'] }}</p>
                <p class="body">{{ $card['body'] }}</p>
            </div>
        @endforeach
    </div>
</body>
</html>
