<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/favicon.svg" sizes="any">
    <title>{{ $template->name }} - Screenshot</title>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0a0a0a; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        img { max-width: 100%; max-height: 100vh; display: block; }
    </style>
</head>
<body>
    <img
        src="{{ $template->screenshot_url }}"
        alt="Screenshot of {{ $template->name }}"
        width="1280"
        height="720"
    />
</body>
</html>
