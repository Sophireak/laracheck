<?php

namespace Sophireak\Laracheck\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature   = 'laracheck:install';
    protected $description = 'One-time setup: publish config, add API key, activate git hook';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=cyan;options=bold>LaraCheck — Install</> <fg=gray>by Sophireak</>');
        $this->line('  <fg=gray>─────────────────────────────────────────</>');
        $this->newLine();

        $this->publishConfig();
        $this->setupApiKey();
        $this->installHook();
        $this->testRun();

        $this->newLine();
        $this->line('  <fg=green;options=bold>Done!</> Run <fg=cyan>php artisan laracheck</> anytime.');
        $this->line('  <fg=gray>From now on it runs automatically before every git push.</>');
        $this->newLine();

        return 0;
    }

    // ── Step 1: Config ────────────────────────────────────────────────────────
    private function publishConfig(): void
    {
        $this->line('  <options=bold>1. Config</>');

        if (file_exists(config_path('laracheck.php'))) {
            $this->line('     <fg=green>✔</> Already published');
        } else {
            $this->callSilent('vendor:publish', ['--tag' => 'laracheck-config']);
            $this->line('     <fg=green>✔</> Published → config/laracheck.php');
        }

        $this->newLine();
    }

    // ── Step 2: API key ───────────────────────────────────────────────────────
    private function setupApiKey(): void
    {
        $this->line('  <options=bold>2. Anthropic API key</>');

        if (! empty(env('ANTHROPIC_API_KEY'))) {
            $this->line('     <fg=green>✔</> ANTHROPIC_API_KEY already set');
            $this->newLine();
            return;
        }

        $this->line('     <fg=yellow>⚠</> Not set — AI explanations need this');
        $this->line('     Get yours: <fg=cyan>https://console.anthropic.com/</>');
        $this->newLine();

        if ($this->confirm('     Add it now?', true)) {
            $key = $this->secret('     Paste your API key');
            if (! empty($key)) {
                file_put_contents(base_path('.env'), "\nANTHROPIC_API_KEY={$key}\n", FILE_APPEND);
                $this->line('     <fg=green>✔</> Added to .env');
            } else {
                $this->line('     Skipped — add ANTHROPIC_API_KEY to .env later');
            }
        } else {
            $this->line('     Skipped — checks still run without AI explanations');
        }

        $this->newLine();
    }

    // ── Step 3: Git hook ──────────────────────────────────────────────────────
    private function installHook(): void
    {
        $this->line('  <options=bold>3. Git hook</>');

        // Check if already active
        $current = trim(shell_exec('git config core.hooksPath 2>&1') ?? '');
        if ($current === '.githooks') {
            $this->line('     <fg=green>✔</> Git hook already active');
            $this->newLine();
            return;
        }

        // Publish the hook stub if not there yet
        if (! file_exists(base_path('.githooks/pre-push'))) {
            $this->callSilent('vendor:publish', ['--tag' => 'laracheck-hooks']);
            shell_exec('chmod +x ' . base_path('.githooks/pre-push'));
            $this->line('     <fg=green>✔</> Hook file created → .githooks/pre-push');
        }

        // Activate
        shell_exec('git config core.hooksPath .githooks');
        $this->line('     <fg=green>✔</> Git hook activated');
        $this->line('     <fg=gray>Commit .githooks/ so teammates get it automatically</>');

        $this->newLine();
    }

    // ── Step 4: Quick test ────────────────────────────────────────────────────
    private function testRun(): void
    {
        $this->line('  <options=bold>4. Quick test</>');
        $this->newLine();

        if ($this->confirm('     Run laracheck now to verify everything works?', true)) {
            $this->newLine();
            $this->call('laracheck', ['--no-ai' => true]);
        } else {
            $this->line('     Skipped — run php artisan laracheck when ready');
        }
    }
}
