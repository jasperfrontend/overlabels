<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Pint extends Command
{
    protected $signature = 'pint {--test : Run in test mode (no changes)} {--dirty : Only fix dirty files}';

    protected $description = 'Run Laravel Pint (code style fixer)';

    public function handle(): int
    {
        $args = [base_path('vendor/bin/pint')];

        if ($this->option('test')) {
            $args[] = '--test';
        }

        if ($this->option('dirty')) {
            $args[] = '--dirty';
        }

        $process = new \Symfony\Component\Process\Process($args, base_path());
        $process->setTimeout(120);
        $process->setTty(false);
        $process->run(fn ($type, $buffer) => $this->output->write($buffer));

        return $process->getExitCode();
    }
}
