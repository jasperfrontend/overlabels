<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Loading Overlay...</title>
    @vite('resources/js/overlay/app.js')
    <script
        src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.6.2/dist/dotlottie-wc.js"
        type="module"
    ></script>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
        }
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            font-weight: bold;
            font-family: monospace;
            font-size: 30px;
            letter-spacing: 4px;
            color: #666;
        }
        .error {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 999999;
            background: #dc2626;
            color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 18px;
            font-weight: 700;
            text-align: center;
            padding: 20px 24px;
            box-shadow: 0 4px 20px rgba(220, 38, 38, 0.5);
            animation: healthBannerSlideIn 0.3s ease-out;
        }

        /* Health status banner — rendered by Vue but styled here so it's available immediately */
        .overlay-health-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 999999;
            background: #dc2626;
            color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            box-shadow: 0 4px 20px rgba(220, 38, 38, 0.5);
            animation: healthBannerSlideIn 0.3s ease-out;
        }
        .overlay-health-banner__inner {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 24px;
            max-width: 100%;
        }
        .overlay-health-banner__icon {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 900;
        }
        .overlay-health-banner__text {
            flex: 1;
            min-width: 0;
        }
        .overlay-health-banner__message {
            font-size: 18px;
            font-weight: 700;
            line-height: 1.3;
        }
        .overlay-health-banner__reload,
        .overlay-health-banner__retry {
            font-size: 14px;
            font-weight: 400;
            opacity: 0.85;
            margin-top: 4px;
        }
        @keyframes healthBannerSlideIn {
            from { transform: translateY(-100%); opacity: 0; }
            to   { transform: translateY(0);     opacity: 1; }
        }
    </style>

</head>
<body>
<div class="loading" id="loading">Overlabels :: Loading</div>
<div class="error" id="error"></div>
<div id="overlay-content"></div>
<script>
    const slug = '{{ $slug }}';
    const token = window.location.hash.substring(1);

    if (!token || token.length !== 64) {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('error').style.display = 'block';
        document.getElementById('error').innerHTML = '<strong>Your overlay link is broken or incomplete.</strong><br><br>To fix this:<br>1. Go to <u>overlabels.com</u> and log in with your Twitch account<br>2. Go to Token Generator and create a new overlay token. Copy the fresh overlay token from your dashboard<br>3. Paste it at the end of your OBS browser source after the # like #12345etc<br><br>Then right-click this source in OBS and click <strong>Refresh</strong>.';
        document.title = 'Overlay link broken — visit overlabels.com';
    } else {
        window.__OVERLAY__ = { slug, token };
    }
</script>
</body>
</html>
