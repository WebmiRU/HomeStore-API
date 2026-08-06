<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store List</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 40px;
            background: #1a1a2e;
            color: #e0e0e0;
        }
        .store-item {
            background: #16213e;
            padding: 30px;
            margin-bottom: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.3);
        }
        .store-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 16px;
            color: #e0e0e0;
        }
        .store-code {
            font-size: 12px;
            color: #8ab4f8;
            margin-bottom: 16px;
            word-break: break-all;
        }
        .store-qr {
            display: block;
        }
        hr {
            border: none;
            border-top: 2px solid #2a2a4a;
            margin: 30px 0;
        }
    </style>
</head>
<body>
    @foreach ($stores as $store)
        <div class="store-item">
            <div class="store-title">{{ $store->title }}</div>
            <div class="store-code">{{ strtoupper(str_replace('-', '', (string) $store->code->code)) }}</div>
            <div class="store-qr">{!! $store->qrSvg !!}</div>
        </div>
        @unless ($loop->last)
            <hr/>
        @endunless
    @endforeach
</body>
</html>
