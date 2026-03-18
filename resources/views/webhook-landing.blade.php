<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>GPSLogger Setup - Overlabels</title>
    <link rel="icon" href="/favicon.png" sizes="any">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=albert-sans:400,500,600" rel="stylesheet" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Albert Sans', system-ui, -apple-system, sans-serif;
            background: #0a0a0a;
            color: #e5e5e5;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            background: #171717;
            border: 1px solid #262626;
            border-radius: 1rem;
            padding: 2rem;
            max-width: 28rem;
            width: 100%;
        }
        .logo {
            font-size: 1.125rem;
            font-weight: 600;
            color: #a78bfa;
            margin-bottom: 1.5rem;
        }
        h1 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #fafafa;
            margin-bottom: 0.75rem;
            line-height: 1.4;
        }
        .instructions {
            font-size: 0.9375rem;
            color: #a3a3a3;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        .url-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .url-input {
            width: 100%;
            background: #0a0a0a;
            border: 1px solid #404040;
            border-radius: 0.5rem;
            padding: 0.75rem 4.5rem 0.75rem 0.875rem;
            color: #e5e5e5;
            font-size: 0.8125rem;
            font-family: ui-monospace, monospace;
            outline: none;
            text-overflow: ellipsis;
        }
        .url-input:focus {
            border-color: #a78bfa;
        }
        .copy-btn {
            position: absolute;
            right: 0.375rem;
            top: 50%;
            transform: translateY(-50%);
            background: #7c3aed;
            color: #fff;
            border: none;
            border-radius: 0.375rem;
            padding: 0.5rem 0.875rem;
            font-size: 0.8125rem;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.15s;
        }
        .copy-btn:hover { background: #6d28d9; }
        .copy-btn:active { background: #5b21b6; }
        .copy-btn.copied {
            background: #16a34a;
        }
        .warning {
            display: flex;
            gap: 0.625rem;
            background: #1c1007;
            border: 1px solid #422006;
            border-radius: 0.5rem;
            padding: 0.875rem;
            font-size: 0.8125rem;
            color: #fbbf24;
            line-height: 1.5;
        }
        .warning-icon {
            flex-shrink: 0;
            margin-top: 0.0625rem;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">Overlabels</div>
        <h1>GPSLogger Setup</h1>
        <p class="instructions">
            Copy the URL below and paste it as the URL in GPSLogger's
            <strong>Log to custom URL</strong> settings.
        </p>
        <div class="url-group">
            <input
                type="text"
                class="url-input"
                id="webhook-url"
                value="{{ $webhookUrl }}"
                readonly
            >
            <button class="copy-btn" id="copy-btn" onclick="copyUrl()">Copy</button>
        </div>
        <div class="warning">
            <svg class="warning-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <span>
                This URL contains a unique key tied to your account.
                Do not share it with anyone - anyone with this URL can send
                location data to your overlays.
            </span>
        </div>
    </div>
    <script>
        function copyUrl() {
            const input = document.getElementById('webhook-url');
            const btn = document.getElementById('copy-btn');

            navigator.clipboard.writeText(input.value).then(function() {
                btn.textContent = 'Copied!';
                btn.classList.add('copied');
                input.select();

                setTimeout(function() {
                    btn.textContent = 'Copy';
                    btn.classList.remove('copied');
                }, 2000);
            }).catch(function() {
                // Fallback for older browsers
                input.select();
                document.execCommand('copy');
                btn.textContent = 'Copied!';
                btn.classList.add('copied');

                setTimeout(function() {
                    btn.textContent = 'Copy';
                    btn.classList.remove('copied');
                }, 2000);
            });
        }
    </script>
</body>
</html>
