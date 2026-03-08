{{-- resources/views/overlay/render.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/favicon.svg" sizes="any">
    <title>{{ $template->name }} - {{ $isParsed ? 'Live' : 'Public Preview' }}</title>
    <style>
        {!! $css !!}
    </style>
    @if($isParsed)
        @vite('resources/js/overlay/app.js')
    @endif
    @if(str_contains($html, 'dotlottie-wc'))
        <script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.6.2/dist/dotlottie-wc.js" type="module"></script>
    @endif
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
    <style>
        .olb-bar { position: fixed; bottom: 0; left: 0; right: 0; z-index: 10000; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 12px; }
        .olb-inner { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 14px; background: rgba(15, 15, 20, 0.85); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-top: 1px solid rgba(255,255,255,0.08); }
        .olb-label { color: rgba(255,255,255,0.5); white-space: nowrap; }
        .olb-actions { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .olb-btn { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.75); cursor: pointer; font: inherit; font-size: 11px; letter-spacing: 0.3px; transition: all 0.15s ease; line-height: 1.4; }
        .olb-btn:hover { background: rgba(255,255,255,0.12); color: #fff; border-color: rgba(255,255,255,0.2); }
        .olb-btn:active { transform: scale(0.97); }
        .olb-btn--accent { background: rgba(139, 92, 246, 0.2); border-color: rgba(139, 92, 246, 0.3); color: rgba(190, 170, 255, 0.9); }
        .olb-btn--accent:hover { background: rgba(139, 92, 246, 0.35); border-color: rgba(139, 92, 246, 0.5); color: #fff; }
        .olb-sep { width: 1px; height: 16px; background: rgba(255,255,255,0.1); margin: 0 2px; }
    </style>
    <div class="olb-bar">
        <div class="olb-inner">
            <span class="olb-label">Preview &mdash; tags not parsed</span>
            <div class="olb-actions">
                <button class="olb-btn" data-copy="head" title="Copy &lt;head&gt;">HEAD</button>
                <button class="olb-btn" data-copy="html" title="Copy HTML body">HTML</button>
                <button class="olb-btn" data-copy="css" title="Copy CSS">CSS</button>

                @if($template->screenshot_url)
                    <div class="olb-sep"></div>
                    <button class="olb-btn" onclick="window.open('{{ route('overlay.public.screenshot', $template->slug) }}', '_blank')" title="View screenshot">Screenshot</button>
                @endif

                <div class="olb-sep"></div>

                @auth
                    <form action="{{ route('templates.fork', $template) }}" method="POST" style="display:inline">
                        @csrf
                        <button type="submit" class="olb-btn olb-btn--accent" title="Fork to your account">Fork</button>
                    </form>
                @else
                    <button class="olb-btn olb-btn--accent" onclick="window.location.href='/dashboard'" title="Log in to fork">Login to fork</button>
                @endauth
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-copy]');
            if (!btn) return;
            const key = btn.dataset.copy;
            const OVERLABELS_COPY = {
                head: @json($head),
                html: @json($html),
                css:  @json($css),
            };
            const content = OVERLABELS_COPY[key];
            if (typeof content !== 'string') return;
            navigator.clipboard.writeText(content).then(() => {
                const orig = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(() => btn.textContent = orig, 1500);
            });
        });
    </script>
@endif
</body>
</html>
