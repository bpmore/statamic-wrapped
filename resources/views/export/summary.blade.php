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
            background: #16161d;
            color: #f7f7f8;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        h1 {
            font-size: 56px;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }

        .site { font-size: 26px; color: #8b8b96; margin-bottom: 48px; }

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
            color: #8b8b96;
            margin-bottom: 6px;
        }

        .body { font-size: 30px; font-weight: 600; line-height: 1.3; }
    </style>
</head>
<body>
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
