{{-- resources/views/overlay/render.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $template->name }} - Public Preview</title>
    <style>
        {!! $css !!}
    </style>
    <script>
        // Helper function
        let domReady = (cb) => {
            document.readyState === 'interactive' || document.readyState === 'complete'
                ? cb()
                : document.addEventListener('DOMContentLoaded', cb);
        };

        domReady(() => {
            // Display body when DOM is loaded
            document.body.style.visibility = 'visible';
        });
    </script>
</head>
<body style="visibility: hidden;">
<script>0</script> <!-- firefox hack against FUC -->
{!! $html !!}

@if(!$isParsed)
    <div style="position: fixed; top: 40px; right: 10px; background: rgba(0,0,0,0.7); color: white; padding: 5px 5px 5px 10px; border-radius: 3px; font: 12px Arial, sans-serif; z-index: 1000;">
        Preview Mode - Template tags not parsed
        <button title="Copy the HTML from this overlay" id="overlabels-copy-to-clipboard-html-34jkd0scnj2e3mg" onclick="copyToClipboard('html')" style="margin-left: 10px; padding: 2px 5px; border-radius: 3px; border: 1px solid white; background: transparent; color: white; cursor: pointer;">
            Copy HTML
        </button>
        <button title="Copy the CSS from this overlay" id="overlabels-copy-to-clipboard-css-34jkd0scnj2e3mg" onclick="copyToClipboard('css')" style="margin-left: 10px; padding: 2px 5px; border-radius: 3px; border: 1px solid white; background: transparent; color: white; cursor: pointer;">
            Copy CSS
        </button>
        @auth
            <form action="{{ route('templates.fork', $template) }}" method="POST" style="display: inline;">
                @csrf
                <button type="submit" title="Fork this template to your Overlabels account" style="margin-left: 10px; padding: 2px 5px; border-radius: 3px; border: 1px solid white; background: transparent; color: white; cursor: pointer;">
                    Fork
                </button>
            </form>
        @else
            <button onclick="window.location.href='/'" title="Log in to Overlabels to fork this overlay to your account" style="margin-left: 10px; padding: 2px 5px; border-radius: 3px; border: 1px solid white; background: transparent; color: white; cursor: pointer;">
                Fork (Login Required)
            </button>
        @endauth
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
</body>
</html>
