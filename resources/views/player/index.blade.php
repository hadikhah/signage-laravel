<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Signage Player</title>
    <link rel="stylesheet" href="{{ asset('/player/player.css') }}">
</head>

<body>
    <header id="header">
        <div id="daily-message" style="display:none"></div>
        <div id="persian-datetime">{{ $initialDateTime['Full'] }}</div>
    </header>

    <main id="stage" aria-live="polite"></main>

    <div id="empty" style="display:none">
        <h1>No content</h1>
        <p>Retrying in 60 seconds&hellip;</p>
    </div>

    <div id="boot" style="display:none">
        <p>Loading playlist&hellip;</p>
    </div>

    <script>
        window.MONITOR_IP = @json($monitorIp);

        window.PLAYER_ENDPOINTS = {
            playback: @json(route('playback.monitor-contents')),
        };
        window.MONITOR_IP = @json($monitorIp);
    </script>
    <script defer src="{{ asset('player/player.js') }}?v={{ filemtime(public_path('player/player.js')) }}"></script>


</body>

</html>
