<?php

namespace Sophireak\Laracheck\Commands;

use Illuminate\Console\Command;
use Sophireak\Laracheck\Checks\EnvCheck;
use Sophireak\Laracheck\Checks\MigrationCheck;
use Sophireak\Laracheck\Checks\AssetCheck;
use Sophireak\Laracheck\Checks\StorageCheck;
use Sophireak\Laracheck\Checks\ComposerCheck;

class LaracheckCommand extends Command
{
    protected $signature = 'laracheck
        {--no-fix   : Run checks only, no auto-fix}
        {--no-ai    : Skip AI explanations}
        {--strict   : Block push if any issues remain}';

    protected $description = 'Run pre-push checks, auto-fix safe issues, and explain problems with AI';

    private array $allFixed    = [];
    private array $allWarnings = [];
    private array $allIssues   = [];

    public function handle(): int
    {
        $this->printBanner();

        $autoFix = ! $this->option('no-fix');

        $checks = [
            new EnvCheck(),
            new MigrationCheck(),
            new AssetCheck(),
            new StorageCheck(),
            new ComposerCheck(),
        ];

        // ── Progress bar ─────────────────────────────────────────────────────
        $total = count($checks) + 1; // +1 for git check
        $bar   = $this->output->createProgressBar($total);
        $bar->setFormat("\n  %message%\n  [%bar%] %percent%%\n");
        $bar->setBarCharacter('█');
        $bar->setEmptyBarCharacter('░');
        $bar->setProgressCharacter('█');
        $bar->start();

        foreach ($checks as $check) {
            $bar->setMessage("<fg=cyan>Checking {$check->name}...</>");
            $bar->advance();
            usleep(200000); // slight pause so progress feels real

            $result = $check->run($autoFix);

            foreach ($result['fixed']    as $m) $this->allFixed[]    = $m;
            foreach ($result['warnings'] as $m) $this->allWarnings[] = $m;
            foreach ($result['issues']   as $m) $this->allIssues[]   = $m;
        }

        // Git check
        $bar->setMessage('<fg=cyan>Checking git status...</>');
        $bar->advance();
        $this->runGitChecks();

        $bar->setMessage('<fg=green>Done!</>');
        $bar->finish();

        $this->newLine(2);

        // ── Summary ───────────────────────────────────────────────────────────
        $this->printSummary();

        // ── AI explanation for unfixed issues ─────────────────────────────────
        if (! empty($this->allIssues) && ! $this->option('no-ai')) {
            $this->runAI();
        }

        // ── Exit code ─────────────────────────────────────────────────────────
        if (! empty($this->allIssues) && $this->option('strict')) {
            $this->newLine();
            $this->line('  <fg=red;options=bold>Push blocked (strict mode). Fix the issues above.</>');
            $this->line('  <fg=yellow>Bypass: git push --no-verify</>');
            $this->newLine();
            return 1;
        }

        return 0;
    }

    // ── Git checks ────────────────────────────────────────────────────────────
    private function runGitChecks(): void
    {
        $status = trim(shell_exec('git status --short 2>&1') ?? '');
        if (! empty($status)) {
            $count = count(array_filter(explode("
", $status)));
            $this->allWarnings[] = "{$count} uncommitted file(s) — were these intentional?";
        }

        $branch    = trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>&1') ?? '');
        $protected = config('laracheck.protected_branches', ['main', 'master', 'develop']);

        if (in_array($branch, $protected)) {
            $this->allIssues[] = "Direct push to \'{$branch}\' is not allowed. "
                . "Run: php artisan laracheck:branch";
        } elseif (! preg_match('/^(feature|fix|hotfix|chore)\//', $branch)) {
            $this->allWarnings[] = "Branch \'{$branch}\' doesn\'t follow naming convention. "
                . "Use feature/name, fix/name etc. Tip: php artisan laracheck:branch";
        }
    }

    // ── Summary ───────────────────────────────────────────────────────────────
    private function printSummary(): void
    {
        $this->line('  <fg=cyan;options=bold>─── Summary ───────────────────────────────────</>');
        $this->newLine();

        if (! empty($this->allFixed)) {
            $this->line('  <fg=green>Fixed:</>');
            foreach ($this->allFixed as $f) {
                $this->line("    <fg=green>✔</> {$f}");
            }
            $this->newLine();
        }

        if (! empty($this->allWarnings)) {
            $this->line('  <fg=yellow>Warnings:</>');
            foreach ($this->allWarnings as $w) {
                $this->line("    <fg=yellow>⚠</> {$w}");
            }
            $this->newLine();
        }

        if (empty($this->allIssues)) {
            $mode = $this->option('strict') ? 'strict' : 'soft';
            $this->line("  <fg=green;options=bold>Ready to push!</>  <fg=gray>mode: {$mode}</>");
        } else {
            $this->line('  <fg=red;options=bold>Issues that need fixing:</>');
            foreach ($this->allIssues as $i => $issue) {
                $this->line('    <fg=red>' . ($i + 1) . '.</> ' . $issue);
            }
        }

        $this->newLine();
    }

    // ── AI explanation ────────────────────────────────────────────────────────
    private function runAI(): void
    {
        $key = config('laracheck.anthropic_api_key', env('ANTHROPIC_API_KEY'));

        if (empty($key)) {
            $this->line('  <fg=yellow>Tip: Add ANTHROPIC_API_KEY to .env for AI-powered explanations.</>');
            $this->newLine();
            return;
        }

        $this->line('  <fg=cyan>AI is explaining the issues...</>');

        $numbered = implode("\n", array_map(
            fn($i, $v) => ($i + 1) . '. ' . $v,
            array_keys($this->allIssues),
            $this->allIssues
        ));

        $payload = json_encode([
            'model'      => config('laracheck.model', 'claude-sonnet-4-20250514'),
            'max_tokens' => 800,
            'system'     => 'You are helping a Laravel developer fix issues before pushing to Git. Be short, friendly, and give exact terminal commands. No fluff.',
            'messages'   => [[
                'role'    => 'user',
                'content' => "Fix these issues in a Laravel 11 + Tailwind + MySQL project:\n\n{$numbered}\n\nFor each: 1 sentence why it matters, exact command to fix, any gotcha.",
            ]],
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: ' . $key,
                'anthropic-version: 2023-06-01',
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
        ]);

        $res  = json_decode(curl_exec($ch), true);
        curl_close($ch);
        $text = $res['content'][0]['text'] ?? null;

        if (! $text) {
            $this->warn('  AI did not respond. Check ANTHROPIC_API_KEY.');
            return;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>┌─ AI Explanation ──────────────────────────────┐</>');
        $this->newLine();
        foreach (explode("\n", $text) as $line) {
            $this->line('  ' . $line);
        }
        $this->newLine();
        $this->line('  <fg=cyan;options=bold>└───────────────────────────────────────────────┘</>');
        $this->newLine();
    }

    private function printBanner(): void
    {
        $this->newLine();
        $this->line('  <fg=cyan;options=bold>LaraCheck</> <fg=gray>by Sophireak</>');
        $this->line('  <fg=gray>─────────────────────────────────────────</>');
        $this->newLine();
    }
}
