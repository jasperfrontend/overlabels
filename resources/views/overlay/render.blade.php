{{-- resources/views/overlay/render.blade.php --}}
    <!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $template->name }} - Public Preview</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: transparent;
        }
        {!! $css !!}
    </style>
</head>
<body>
{!! $html !!}

@if(!$isParsed)
    <div style="position: fixed; bottom: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; padding: 5px 10px; border-radius: 3px; font-size: 12px;">
        Preview Mode - Template tags not parsed
        <button onclick="copyToClipboard('html')"
                style="margin-left: 10px; padding: 2px 5px; border-radius: 3px; border: 1px solid white; background: transparent; color: white; cursor: pointer;">
            Copy HTML
        </button>
        <button onclick="copyToClipboard('css')"
                style="margin-left: 5px; padding: 2px 5px; border-radius: 3px; border: 1px solid white; background: transparent; color: white; cursor: pointer;">
            Copy CSS
        </button>
        <script>
            function copyToClipboard(type) {
                const content = type === 'html' ? {!! json_encode($html) !!} : {!! json_encode($css) !!};
                navigator.clipboard.writeText(content).then(() => {
                    alert(`${type.toUpperCase()} copied to clipboard!`);
                }).catch(err => {
                    console.error('Failed to copy:', err);
                });
            }
        </script>
    </div>
@endif

@if($js)
    <script>
        {!! $js !!}
    </script>
@endif
</body>
</html>
