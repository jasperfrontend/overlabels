{{-- resources/views/overlay/render.blade.php --}}
    <!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
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
    </div>
@endif

@if($js)
    <script>
        {!! $js !!}
    </script>
@endif
</body>
</html>
