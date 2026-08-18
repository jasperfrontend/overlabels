<?php

/*
 * The production PHP configuration.
 *
 * These exist because of a specific, four-month-old failure: the FrankenPHP
 * base image ships php.ini-development AND php.ini-production and activates
 * NEITHER, so every setting ran on a compiled-in default. `display_errors` was
 * On and `log_errors` was Off, meaning PHP warnings were printed into response
 * bodies and recorded nowhere. Nothing anywhere in the codebase asserted
 * otherwise, so nothing caught it.
 *
 * The point of these tests is narrow but real: an ini file that is not COPYed
 * into the image is an ini file that does nothing, and it fails silently and
 * invisibly - exactly the shape of the original bug. They cannot verify the
 * running container, only that what we ship is coherent.
 */

function dockerfile(): string
{
    return file_get_contents(base_path('Dockerfile'));
}

function iniFile(string $name): string
{
    return file_get_contents(base_path("docker/{$name}"));
}

test('every ini file we ship is actually copied into the image', function () {
    $shipped = array_map('basename', glob(base_path('docker/php-*.ini')));

    expect($shipped)->not->toBeEmpty();

    foreach ($shipped as $file) {
        $this->assertStringContainsString(
            "COPY docker/{$file} ",
            dockerfile(),
            "docker/{$file} exists but the Dockerfile never copies it, so it has no effect in production.",
        );
    }
});

test('the copied ini files land in the conf.d directory PHP scans', function () {
    // Anywhere else and they are inert. $PHP_INI_DIR/conf.d is the only path
    // the base image reports as "Scan this dir for additional .ini files".
    preg_match_all('/^COPY docker\/php-[\w-]+\.ini (\S+)/m', dockerfile(), $matches);

    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $destination) {
        expect($destination)->toStartWith('$PHP_INI_DIR/conf.d/');
    }
});

test('errors are never printed into a response and always logged somewhere', function () {
    $ini = iniFile('php-errors.ini');

    expect($ini)->toMatch('/^\s*display_errors\s*=\s*Off\s*$/mi')
        ->and($ini)->toMatch('/^\s*display_startup_errors\s*=\s*Off\s*$/mi')
        // log_errors defaults to OFF when no php.ini is active, so without this
        // the warnings had no destination at all except the browser.
        ->and($ini)->toMatch('/^\s*log_errors\s*=\s*On\s*$/mi');
});

test('exception traces cannot carry argument values into the logs', function () {
    // Laravel writes traces to storage/logs. With args recorded, any exception
    // below a function that received a token or password logs it in plaintext.
    $ini = iniFile('php-hardening.ini');

    expect($ini)->toMatch('/^\s*zend\.exception_ignore_args\s*=\s*On\s*$/mi')
        ->and($ini)->toMatch('/^\s*zend\.exception_string_param_max_len\s*=\s*0\s*$/mi');
});

test('the PHP version is not advertised in response headers', function () {
    expect(iniFile('php-hardening.ini'))->toMatch('/^\s*expose_php\s*=\s*Off\s*$/mi');
});

test('upload limits stay above what the client-side check allows through', function () {
    $ini = iniFile('php-uploads.ini');

    // If PHP is the one to refuse, it refuses at request startup where Laravel
    // cannot turn it into a useful message.
    expect($ini)->toMatch('/^\s*upload_max_filesize\s*=\s*10M\s*$/mi')
        ->and($ini)->toMatch('/^\s*post_max_size\s*=\s*12M\s*$/mi');
});
