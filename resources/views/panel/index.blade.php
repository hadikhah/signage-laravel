<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Signage Panel</title>
    <link rel="stylesheet"
        href="{{ asset('assets/panel/panel.css') }}?v={{ filemtime(public_path('assets/panel/panel.css')) }}">
</head>

<body>
    <div class="container">
        @if (!$monitor)
            <div class="card error">
                <h1>No monitor registered</h1>
                <p>Your IP: <strong>{{ $ip }}</strong></p>
                <p>No monitor is registered at this IP address.</p>
                @if ($devOverride)
                    <p class="hint">Dev tip: add <code>?ip=10.0.0.1</code> to the URL to act as a different monitor.
                    </p>
                @endif
            </div>
        @else
            <div class="card">
                <h1>{{ $monitor->host_name ?? 'Monitor' }} <small>({{ $monitor->ip_address }})</small></h1>
                <p class="muted">{{ $monitorContents->count() }} content(s)</p>
            </div>

            <div class="card">
                <h2>Add content</h2>
                <form id="upload-form" enctype="multipart/form-data">
                    <label>Type
                        <select name="content_type" id="content_type">
                            <option value="1">Image</option>
                            <option value="2">Video</option>
                            <option value="5">URL</option>
                        </select>
                    </label>

                    <div id="file-row">
                        <label>File (max 10MB)
                            <input type="file" name="file" id="file">
                        </label>
                    </div>

                    <div id="url-fields" style="display:none">
                        <label>URL
                            <input type="url" name="url" placeholder="https://example.com" maxlength="500">
                        </label>
                        <label>Username (optional)
                            <input type="text" name="url_user_name" maxlength="255" autocomplete="off">
                        </label>
                        <label>Password (optional)
                            <input type="password" name="url_password" maxlength="255" autocomplete="new-password">
                        </label>
                    </div>

                    <div id="duration-row">
                        <label>Display duration (1&ndash;300 seconds)
                            <input type="number" name="display_time" id="display_time" min="1" max="300"
                                value="10" required>
                        </label>
                        <small class="muted">How long to show this before advancing to the next item.</small>
                    </div>


                    <button type="submit">Upload</button>
                    <div id="form-message" role="status"></div>
                </form>
            </div>

            <div class="card">
                <h2>Current contents</h2>
                @if ($monitorContents->isEmpty())
                    <p class="muted">No contents yet.</p>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Name</th>
                                <th>Duration</th>
                                <th>Order</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($monitorContents as $monitorContent)
                                <tr>
                                    <td>{{ ['1' => 'Image', '2' => 'Video', '3' => 'Text', '4' => 'Flash', '5' => 'URL'][$monitorContent->content->content_type] ?? '?' }}
                                    </td>
                                    <td>{{ $monitorContent->content->client_file_name ?? $monitorContent->content->content_name }}
                                    </td>

                                    <td>
                                        {{ $monitorContent->content->content_type === App\Enums\ContentType::Video->value ? '—' : $monitorContent->display_time . 's' }}
                                    </td>

                                    <td>
                                        {{ $monitorContent->content->order }}
                                    </td>

                                    <td>
                                        <button class="delete-btn"
                                            data-id="{{ $monitorContent->content->id }}">Delete</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif
    </div>

    <script>
        window.PANEL_CONFIG = {
            uploadUrl: @json(route('panel.contents.store')),
            deleteUrlTemplate: @json(route('panel.contents.destroy', ['content' => 'ID'])),
            ip: @json($ip),
            isDev: @json($devOverride),
        };
    </script>
    <script src="{{ asset('assets/panel/panel.js') }}?v={{ filemtime(public_path('assets/panel/panel.js')) }}"></script>
</body>

</html>
