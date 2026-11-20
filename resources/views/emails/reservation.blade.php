<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $mailSubject }}</title>
</head>
<body style="margin:0;padding:24px;background:#ffffff;color:#000000;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;">
    <div style="max-width:560px;margin:0 auto;border:1px solid #000000;">
        <div style="padding:16px 24px;background:#000000;color:#ffffff;font-size:18px;font-weight:bold;">
            {{ $shop['name'] }}
        </div>

        <div style="padding:24px;">
            <h1 style="margin:0 0 16px;font-size:20px;">{{ $heading }}</h1>

            @foreach ($lines as $line)
                <p style="margin:0 0 12px;">{{ $line }}</p>
            @endforeach
        </div>

        @if ($shop['phone'] || $shop['address'])
            <div style="padding:16px 24px;border-top:1px solid #000000;font-size:13px;">
                @if ($shop['address'])
                    {{ $shop['address'] }}<br>
                @endif
                @if ($shop['phone'])
                    Phone: {{ $shop['phone'] }}
                @endif
            </div>
        @endif
    </div>
</body>
</html>
