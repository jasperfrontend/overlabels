<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="/favicon.svg" sizes="any">
    <title>Stream Events - Overlabels</title>
    @vite('resources/js/events-feed/app.ts')
</head>
<body class="bg-background text-foreground">
<div id="events-feed-root"></div>
<div id="events-feed-error" style="display:none; min-height:100vh; align-items:center; justify-content:center; padding:24px; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size:15px; line-height:1.5; text-align:center; color:#a1a1aa; background:#09090b;"></div>
<script>
    (function () {
        // The token lives in the URL fragment, which never reaches the
        // server. The Vue app sends it to /api/events itself.
        const token = window.location.hash.substring(1);
        if (!token || token.length !== 64) {
            const el = document.getElementById('events-feed-error');
            el.style.display = 'flex';
            el.textContent = 'This link is incomplete. Copy the full events feed link, including everything after the # sign, from the Events page on Overlabels.';
        } else {
            window.__EVENTS_FEED__ = { token: token };
        }
    })();
</script>
</body>
</html>
