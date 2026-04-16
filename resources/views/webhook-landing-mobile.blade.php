<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Overlabels GPS Setup</title>
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
            padding: 1rem;
            max-width: 34rem;
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
        .open-app-btn {
            display: inline-block;
            background: #7c3aed;
            color: #fff;
            border: none;
            border-radius: 0.5rem;
            padding: 0.875rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            font-family: inherit;
            text-decoration: none;
            text-align: center;
            margin-bottom: 1.5rem;
            transition: background 0.15s;
        }
        .open-app-btn:hover { background: #6d28d9; }
        .open-app-btn:active { background: #5b21b6; }
        .divider {
            border: none;
            border-top: 1px solid #262626;
            margin: 1.5rem 0;
        }
        .steps {
            font-size: 0.875rem;
            color: #a3a3a3;
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }
        .steps ol {
            padding-left: 1.25rem;
        }
        .steps li {
            margin-bottom: 0.625rem;
        }
        .steps strong {
            color: #e5e5e5;
        }
        .steps code {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 0.25rem;
            padding: 0.125rem 0.375rem;
            font-size: 0.8125rem;
            font-family: ui-monospace, monospace;
        }
        .controls-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.375rem;
            margin-top: 0.375rem;
        }
        .copyable-group {
            position: relative;
            margin-top: 0.5rem;
            margin-bottom: 0.25rem;
        }
        .copyable-input {
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
        .copyable-input:focus {
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
        .section-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #737373;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">Overlabels</div>
        <h1>Set up Overlabels GPS on your phone</h1>

        <a href="{{ $deepLink }}" class="open-app-btn">Open in Overlabels GPS app</a>

        <hr class="divider">

        <p class="section-label">Manual setup</p>
        <div class="steps">
            <ol>
                <li>
                    Open the Overlabels GPS app on your Android device.
                </li>
                <li>
                    Go to <strong>Settings</strong> and enter the <strong>Endpoint URL</strong> below:
                    <div class="copyable-group">
                        <input
                            type="text"
                            class="copyable-input"
                            id="webhook-url"
                            value="{{ $webhookUrl }}"
                            readonly
                        >
                        <button class="copy-btn" id="copy-url-btn" onclick="copyValue('webhook-url', 'copy-url-btn')">Copy</button>
                    </div>
                </li>
                <li>
                    Enter the <strong>Token</strong>. You can find this on the
                    <a href="{{ url('/settings/integrations/overlabels-mobile') }}" style="color: #a78bfa; text-decoration: underline;">Overlabels GPS settings page</a>
                    in your Overlabels dashboard (you may need to use the deep link or copy it from there).
                </li>
                <li>
                    Tap <strong>Test Connection</strong> to verify everything works, then start tracking.
                </li>
                <li>
                    Your overlays now have live GPS controls:
                    <div class="controls-list">
                        <code>[[[c:overlabels-mobile:gps_speed]]]</code>
                        <code>[[[c:overlabels-mobile:gps_lat]]]</code>
                        <code>[[[c:overlabels-mobile:gps_lng]]]</code>
                        <code>[[[c:overlabels-mobile:gps_distance]]]</code>
                    </div>
                </li>
            </ol>
        </div>
        <div class="warning">
            <svg class="warning-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <span>
                This URL contains a unique key tied to your account.
                Do not share it with anyone.
            </span>
        </div>
    </div>
    <script>
        function copyValue(inputId, btnId) {
            const input = document.getElementById(inputId);
            const btn = document.getElementById(btnId);

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
