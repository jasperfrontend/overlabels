<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class OgRenderTest extends Command
{
    protected $signature = 'og:test
        {--svg= : Path to source SVG (default: public/ogimage.svg)}
        {--out= : Path to output PNG (default: public/og/test.png)}
        {--font-dir= : Path to fonts dir (default: resources/fonts)}
        {--width=1200 : Render width in pixels}';

    protected $description = 'Proof-of-concept: render a static SVG to PNG via the resvg CLI';

    public function handle(): int
    {
        $svg = $this->option('svg') ?: base_path('public/ogimage.svg');
        $out = $this->option('out') ?: base_path('public/og/test.png');
        $fontDir = $this->option('font-dir') ?: base_path('resources/fonts');
        $width = (int) $this->option('width');

        if (! File::exists($svg)) {
            $this->error("SVG not found: {$svg}");

            return self::FAILURE;
        }

        if (! File::isDirectory($fontDir)) {
            $this->error("Fonts directory not found: {$fontDir}");

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($out));

        $bin = env('RESVG_BIN', 'resvg');

        $args = [
            $bin,
            '--use-fonts-dir', $fontDir,
            '--width', (string) $width,
            $svg,
            $out,
        ];

        $this->line('<comment>Running:</comment> '.implode(' ', $args));

        $process = new Process($args, base_path());
        $process->setTimeout(30);

        try {
            $process->mustRun(fn ($type, $buffer) => $this->output->write($buffer));
        } catch (ProcessFailedException $e) {
            $this->newLine();
            $this->error('resvg failed. Make sure the binary is installed and on PATH (or set RESVG_BIN).');
            $this->line('  Install: https://github.com/linebender/resvg/releases');

            return self::FAILURE;
        }

        $bytes = File::size($out);
        $this->newLine();
        $this->info("Wrote {$out} ({$this->humanBytes($bytes)})");

        return self::SUCCESS;
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / 1024 / 1024, 2).' MB';
    }
}
