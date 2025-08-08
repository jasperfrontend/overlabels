<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Loading Overlay...</title>
    @vite('https://jasper.up.railway.app/resources/js/overlay/app.js')

    <style>
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
            color: orangered;
            text-align: center;
            padding: 20px;
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
        document.getElementById('error').textContent = 'Invalid or missing authentication token';
        document.title = 'Invalid or missing authentication token';
    } else {
        // Expose token + slug for the Vue app (via window)
        window.__OVERLAY__ = { slug, token };
    }
</script>
</body>
</html>
