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

test('strips onerror event handler', function () {
    expect(HtmlSanitizationService::sanitize('<img onerror="alert(1)" src="x">'))
        ->toBe('<img src="x">');
});

// --- Section 3: javascript: URIs ---

test('strips javascript uri in href', function () {
    expect(HtmlSanitizationService::sanitize('<a href="javascript:alert(1)">link</a>'))
        ->toBe('<a>link</a>');
});

test('strips javascript uri in src', function () {
    // iframe is stripped entirely now, but the javascript: URI rule still applies to other tags
    expect(HtmlSanitizationService::sanitize('<svg src="javascript:alert(1)">'))
        ->toBe('<svg>');
});

// --- Interactive elements stripped entirely ---

test('strips form blocks entirely', function () {
    expect(HtmlSanitizationService::sanitize('<form action="/submit"><input type="text"><button>OK</button></form>'))
        ->toBe('');
});

test('strips form and preserves surrounding content', function () {
    expect(HtmlSanitizationService::sanitize('<div>before</div><form><input></form><div>after</div>'))
        ->toBe('<div>before</div><div>after</div>');
});

test('strips button elements', function () {
    expect(HtmlSanitizationService::sanitize('<div>text</div><button>Click me</button><p>more</p>'))
        ->toBe('<div>text</div><p>more</p>');
});

test('strips input elements', function () {
    expect(HtmlSanitizationService::sanitize('<div>label</div><input type="text" value="data"><p>end</p>'))
        ->toBe('<div>label</div><p>end</p>');
});

test('strips self-closing input elements', function () {
    expect(HtmlSanitizationService::sanitize('<div>before</div><input /><div>after</div>'))
        ->toBe('<div>before</div><div>after</div>');
});

test('strips textarea elements', function () {
    expect(HtmlSanitizationService::sanitize('<div>label</div><textarea>user input</textarea><p>end</p>'))
        ->toBe('<div>label</div><p>end</p>');
});

test('strips select elements', function () {
    expect(HtmlSanitizationService::sanitize('<div>pick</div><select><option>A</option><option>B</option></select><p>end</p>'))
        ->toBe('<div>pick</div><p>end</p>');
});

test('strips object elements', function () {
    expect(HtmlSanitizationService::sanitize('<div>before</div><object data="something.swf" type="application/x-shockwave-flash"></object><div>after</div>'))
        ->toBe('<div>before</div><div>after</div>');
});

test('strips iframe elements with content', function () {
    expect(HtmlSanitizationService::sanitize('<div>before</div><iframe src="https://example.com"></iframe><div>after</div>'))
        ->toBe('<div>before</div><div>after</div>');
});

test('strips self-closing iframe elements', function () {
    expect(HtmlSanitizationService::sanitize('<div>before</div><iframe src="https://example.com" /><div>after</div>'))
        ->toBe('<div>before</div><div>after</div>');
});

test('strips embed elements', function () {
    expect(HtmlSanitizationService::sanitize('<div>before</div><embed src="video.mp4" type="video/mp4"><div>after</div>'))
        ->toBe('<div>before</div><div>after</div>');
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
    $encoded = '&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;alert(1)';
    expect(HtmlSanitizationService::sanitize('<a href="'.$encoded.'">link</a>'))
        ->toBe('<a>link</a>');
});

test('strips hex-entity-encoded javascript uri in href', function () {
    $encoded = '&#x6A;&#x61;&#x76;&#x61;&#x73;&#x63;&#x72;&#x69;&#x70;&#x74;&#x3A;alert(1)';
    expect(HtmlSanitizationService::sanitize('<a href="'.$encoded.'">link</a>'))
        ->toBe('<a>link</a>');
});

// --- Safe content: must survive untouched ---

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

    expect($result['name'])->toBe('<script>alert(1)</script>My Template');
    expect($result['html'])->toBe('<div>hello</div>');
    expect($result['css'])->toBe('body { color: red; }');
    expect($result['type'])->toBe('static');
});
