<?php

use App\Services\HtmlSanitizationService;

// --- Section 1: Script tags ---

test('strips script tags with content', function () {
    expect(HtmlSanitizationService::sanitize('<div>hello</div><script>alert(1)</script>'))
        ->toBe('<div>hello</div>');
});

test('strips script tags with attributes', function () {
    expect(HtmlSanitizationService::sanitize('<script type="text/javascript">evil()</script>'))
        ->toBe('');
});

// --- Section 2: Event handlers ---

test('strips svg onload event handler', function () {
    expect(HtmlSanitizationService::sanitize('<svg onload=alert(1)>'))
        ->toBe('<svg>');
});

test('strips onclick event handler', function () {
    expect(HtmlSanitizationService::sanitize('<button onclick="alert(1)">Click</button>'))
        ->toBe('<button>Click</button>');
});

test('strips onerror event handler', function () {
    expect(HtmlSanitizationService::sanitize('<img onerror="alert(1)" src="x">'))
        ->toBe('<img src="x">');
});

// --- Section 3: javascript: URIs ---

test('strips javascript uri in form action', function () {
    expect(HtmlSanitizationService::sanitize('<form action="javascript:alert(1)"><button>Click</button></form>'))
        ->toBe('<form><button>Click</button></form>');
});

test('strips javascript uri in href', function () {
    expect(HtmlSanitizationService::sanitize('<a href="javascript:alert(1)">link</a>'))
        ->toBe('<a>link</a>');
});

test('strips javascript uri in src', function () {
    expect(HtmlSanitizationService::sanitize('<iframe src="javascript:alert(1)">'))
        ->toBe('<iframe>');
});

test('strips javascript uri in formaction', function () {
    expect(HtmlSanitizationService::sanitize('<button formaction="javascript:alert(1)">Go</button>'))
        ->toBe('<button>Go</button>');
});

// --- Section 5: Meta refresh ---

test('strips meta refresh with javascript uri', function () {
    expect(HtmlSanitizationService::sanitize('<meta http-equiv="refresh" content="0;url=javascript:alert(1)">'))
        ->toBe('');
});

test('strips meta refresh with data uri', function () {
    expect(HtmlSanitizationService::sanitize('<meta http-equiv="refresh" content="0;url=data:text/html,<script>alert(1)</script>">'))
        ->toBe('');
});

// --- Section 6: CSS javascript: in url() ---

test('neutralises javascript inside css url()', function () {
    $html = '<div style="background: url(\'javascript:alert(6)\')">test</div>';
    $result = HtmlSanitizationService::sanitize($html);
    expect($result)->not->toContain('javascript:');
    expect($result)->toContain('url(about:blank)');
});

// --- Section 9: HTML-entity-encoded javascript: URIs ---

test('strips decimal-entity-encoded javascript uri in href', function () {
    // &#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58; = javascript:
    $encoded = '&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;alert(1)';
    expect(HtmlSanitizationService::sanitize('<a href="'.$encoded.'">link</a>'))
        ->toBe('<a>link</a>');
});

test('strips hex-entity-encoded javascript uri in href', function () {
    // &#x6A;&#x61;&#x76;&#x61;&#x73;&#x63;&#x72;&#x69;&#x70;&#x74;&#x3A; = javascript:
    $encoded = '&#x6A;&#x61;&#x76;&#x61;&#x73;&#x63;&#x72;&#x69;&#x70;&#x74;&#x3A;alert(1)';
    expect(HtmlSanitizationService::sanitize('<a href="'.$encoded.'">link</a>'))
        ->toBe('<a>link</a>');
});

test('strips entity-encoded javascript uri in form action', function () {
    $encoded = '&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;alert(1)';
    expect(HtmlSanitizationService::sanitize('<form action="'.$encoded.'"><button>Go</button></form>'))
        ->toBe('<form><button>Go</button></form>');
});

// --- Safe content: must survive untouched ---

test('preserves safe form action', function () {
    expect(HtmlSanitizationService::sanitize('<form action="/submit"><button>OK</button></form>'))
        ->toBe('<form action="/submit"><button>OK</button></form>');
});

test('preserves safe href', function () {
    expect(HtmlSanitizationService::sanitize('<a href="https://example.com">link</a>'))
        ->toBe('<a href="https://example.com">link</a>');
});

test('preserves normal html structure', function () {
    $html = '<div class="overlay"><h1>Stream Starting</h1><p>Please wait...</p></div>';
    expect(HtmlSanitizationService::sanitize($html))->toBe($html);
});

test('preserves safe css url()', function () {
    $html = '<div style="background: url(\'https://example.com/bg.png\')">test</div>';
    expect(HtmlSanitizationService::sanitize($html))->toBe($html);
});

test('handles case insensitive patterns', function () {
    expect(HtmlSanitizationService::sanitize('<SCRIPT>alert(1)</SCRIPT>'))->toBe('');
    expect(HtmlSanitizationService::sanitize('<div ONCLICK="alert(1)">'))->toBe('<div>');
    expect(HtmlSanitizationService::sanitize('<a HREF="JAVASCRIPT:alert(1)">x</a>'))->toBe('<a>x</a>');
});

test('sanitizeTemplateFields only processes relevant fields', function () {
    $input = [
        'name' => '<script>alert(1)</script>My Template',
        'html' => '<div onclick="alert(1)">hello</div>',
        'css' => 'body { color: red; }',
        'type' => 'static',
    ];

    $result = HtmlSanitizationService::sanitizeTemplateFields($input);

    // name is NOT sanitized (not in the list)
    expect($result['name'])->toBe('<script>alert(1)</script>My Template');
    // html IS sanitized
    expect($result['html'])->toBe('<div>hello</div>');
    // css is unchanged (no dangerous content)
    expect($result['css'])->toBe('body { color: red; }');
    // type is unchanged
    expect($result['type'])->toBe('static');
});
