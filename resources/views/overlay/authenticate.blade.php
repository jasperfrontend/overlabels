{{-- resources/views/overlay/authenticate.blade.php --}}
    <!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Loading Overlay...</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: transparent;
            font-family: Arial, sans-serif;
        }
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: #666;
        }
        .error {
            display: none;
            color: #ff0000;
            text-align: center;
            padding: 20px;
        }
    </style>
</head>
<body>
<div class="loading" id="loading">Loading overlay...</div>
<div class="error" id="error"></div>
<div id="overlay-content"></div>

<script>
    // Get token from URL fragment
    const hash = window.location.hash.substring(1);
    const slug = '{{ $slug }}';

    if (!hash || hash.length !== 64) {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('error').style.display = 'block';
        document.getElementById('error').textContent = 'Invalid or missing authentication token';
    } else {
        // Fetch the parsed overlay content
        fetch('/api/overlay/render', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                slug: slug,
                token: hash
            })
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Authentication failed', response);

                }
                return response.json();
            })
            .then(data => {
                document.getElementById('loading').style.display = 'none';

                // Create a style element
                if (data.css) {
                    const style = document.createElement('style');
                    style.textContent = data.css;
                    document.head.appendChild(style);
                }

                // Insert HTML
                document.getElementById('overlay-content').innerHTML = data.html;

                // Execute JavaScript
                if (data.js) {
                    const script = document.createElement('script');
                    script.textContent = data.js;
                    document.body.appendChild(script);
                }
            })
            .catch(error => {
                document.getElementById('loading').style.display = 'none';
                document.getElementById('error').style.display = 'block';
                document.getElementById('error').textContent = error.message;
            });
    }
</script>
</body>
</html>
