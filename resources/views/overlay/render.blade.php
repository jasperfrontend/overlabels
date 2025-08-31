{{-- resources/views/overlay/render.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="https://overlabels.com/favicon.png" sizes="any">
    <title>{{ $template->name }} - Public Preview</title>
    <style>
        {!! $css !!}
        button:hover {
            background: #fff !important;
            color: #000 !important;
        }
    </style>
    @vite('resources/js/overlay/app.js')
    <script
        src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.6.2/dist/dotlottie-wc.js"
        type="module"
    ></script>
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
    {!! $head !!}
</head>
<body style="visibility: hidden;">
<script>0</script> <!-- firefox hack against FUC -->
{!! $html !!}

@if(!$isParsed)
    <div style="position: fixed; top: 0; right: 0; width: 100%; background: rgba(0,0,0,0.7); color: white; padding: 5px 5px 5px 10px; border-radius: 0; font: 11px sans-serif; z-index: 1000;">
        <div style="display: flex; flex-flow: row nowrap; align-items: center; justify-content: space-between">
            <div>
                Preview Mode - Template tags not parsed.
            </div>
            <div>
                Click to copy:
                <button
                    title="Copy the <head> from this overlay"
                    id="overlabels-copy-to-clipboard-html-34jkd0scnj2e3mg"
                    onclick="copyToClipboard('head')" style="margin-left: 10px; padding: 2px 5px; border-radius: 3px; border: 1px solid white; background: transparent; color: white; cursor: pointer;"
                >
                    HEAD
                </button>
                <button
                    title="Copy the html from this overlay"
                    id="overlabels-copy-to-clipboard-html-34jkd0scnj2e3mg"
                    onclick="copyToClipboard('html')" style="margin-left: 10px; padding: 2px 5px; border-radius: 3px; border: 1px solid white; background: transparent; color: white; cursor: pointer;"
                >
                    HTML
                </button>
                <button
                    title="Copy the css from this overlay"
                    id="overlabels-copy-to-clipboard-css-34jkd0scnj2e3mg"
                    onclick="copyToClipboard('css')" style="margin-left: 10px; padding: 2px 5px; border-radius: 3px; border: 1px solid white; background: transparent; color: white; cursor: pointer;">
                    CSS
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
                       Login to fork
                    </button>
                @endauth
            </div>
        </div>
        <script>
            function copyToClipboard(type) {
                let content = null;
                if (type === 'html') {
                    content = {!! json_encode($html) !!}
                } else if (type === 'css') {
                    content = {!! json_encode($css) !!}
                } else if (type === 'head') {
                    content = {!! json_encode($head) !!}
                } else {
                    alert('You are trying to copy something that does not exist.')
                }
                navigator.clipboard.writeText(content).then(() => {
                    alert(`<${type}> copied to clipboard!`);
                }).catch(err => {
                    console.error('Failed to copy:', err);
                });
            }
        </script>
    </div>
@endif
</body>
</html>
