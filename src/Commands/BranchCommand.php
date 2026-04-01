<?php

namespace Sophireak\Laracheck\Commands;

use Illuminate\Console\Command;

class BranchCommand extends Command
{
    protected $signature   = 'laracheck:branch';
    protected $description = 'Create a properly named feature branch interactively';

    private array $types = [
        'feature' => 'New feature or task',
        'fix'     => 'Bug fix',
        'hotfix'  => 'Urgent production fix',
        'chore'   => 'Cleanup, refactor, or maintenance',
    ];

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=cyan;options=bold>LaraCheck</> <fg=gray>— Create Branch</>');
        $this->line('  <fg=gray>─────────────────────────────────────────</>');
        $this->newLine();

        // Check they are on develop or main before branching
        $current = trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>&1') ?? '');

        if (! in_array($current, ['develop', 'main', 'master'])) {
            $this->line("  <fg=yellow>⚠</> You are on '<fg=yellow>{$current}</>' — recommended to branch from develop.");
            $this->newLine();

            if ($this->confirm('  Switch to develop first?', true)) {
                $this->switchToDevelop();
                $current = 'develop';
            } else {
                $this->line("  <fg=gray>OK — branching from: {$current}</>");
            }
            $this->newLine();
        } else {
            $this->line("  <fg=cyan>→</> Pulling latest {$current}...");
            shell_exec("git pull origin {$current} 2>&1");
            $this->line("  <fg=green>✔</> Up to date with {$current}");
            $this->newLine();
        }

        // Pick branch type
        $typeChoice = $this->choice(
            '  What type of work is this?',
            array_map(
                fn($type, $desc) => "{$type} — {$desc}",
                array_keys($this->types),
                array_values($this->types)
            ),
            0
        );

        $type = explode(' — ', $typeChoice)[0];
        $this->newLine();

        // Task name
        $taskName = $this->askTaskName();

        // Build branch name
        $branchName = $type . '/' . $taskName;

        $this->newLine();
        $this->line("  Branch name: <fg=cyan>{$branchName}</>");
        $this->newLine();

        if (! $this->confirm('  Create this branch?', true)) {
            $this->line('  <fg=yellow>Cancelled.</> Run again when ready.');
            $this->newLine();
            return 0;
        }

        // Create and switch
        $output = shell_exec("git checkout -b {$branchName} 2>&1");

        if (str_contains($output ?? '', 'already exists')) {
            $this->newLine();
            $this->line("  <fg=yellow>⚠</> Branch '{$branchName}' already exists.");

            if ($this->confirm('  Switch to it instead?', true)) {
                shell_exec("git checkout {$branchName} 2>&1");
                $this->line("  <fg=green>✔</> Switched to <fg=cyan>{$branchName}</>");

                // Push existing branch to GitHub
                $this->pushToGitHub($branchName);
            }

            $this->newLine();
            return 0;
        }

        // Auto-push to GitHub so it's visible immediately
        $this->newLine();
        $this->line("  <fg=green;options=bold>✔ Branch created:</> <fg=cyan>{$branchName}</>");
        $this->newLine();
        $this->pushToGitHub($branchName);

        // Final instructions
        $this->newLine();
        $this->line('  <fg=gray>Work freely — commit as much as you want on this branch.</>');
        $this->newLine();
        $this->line('  <fg=gray>When done:</>');
        $this->line("    <fg=cyan>git add .</>");
        $this->line("    <fg=cyan>git commit -m \"feat: your message\"</>");
        $this->line("    <fg=cyan>git push origin {$branchName}</>");
        $this->newLine();
        $this->line('  <fg=gray>Then open a Pull Request → develop on GitHub.</>');
        $this->newLine();

        return 0;
    }

    // ── Push branch to GitHub immediately ────────────────────────────────────
    private function pushToGitHub(string $branchName): void
    {
        $this->line("  <fg=cyan>→</> Pushing branch to GitHub...");
        $output = shell_exec("git push origin {$branchName} 2>&1");

        if (str_contains($output ?? '', 'error') || str_contains($output ?? '', 'fatal')) {
            $this->line("  <fg=yellow>⚠</> Could not push to GitHub automatically.");
            $this->line("    Run manually: <fg=cyan>git push origin {$branchName}</>");
        } else {
            $this->line("  <fg=green>✔</> Branch is now visible on GitHub</>");
            $this->line("  <fg=gray>github.com → your repo → branches → {$branchName}</>");
        }
    }

    // ── Ask for task name, validate and slugify ───────────────────────────────
    private function askTaskName(): string
    {
        while (true) {
            $raw = $this->ask('  Task name (e.g. login page, fix user bug)');

            if (empty(trim($raw ?? ''))) {
                $this->line('  <fg=red>Task name cannot be empty. Try again.</>');
                continue;
            }

            $slug = strtolower(trim($raw));
            $slug = preg_replace('/[^a-z0-9\s\-]/', '', $slug);
            $slug = preg_replace('/[\s\-]+/', '-', $slug);
            $slug = trim($slug, '-');

            if (empty($slug)) {
                $this->line('  <fg=red>Invalid name. Use letters, numbers, spaces or hyphens.</>');
                continue;
            }

            return $slug;
        }
    }

    // ── Switch to develop, create it if it doesn't exist ─────────────────────
    private function switchToDevelop(): void
    {
        $branches = shell_exec('git branch -a 2>&1') ?? '';

        if (! str_contains($branches, 'develop')) {
            $this->line('  <fg=yellow>⚠</> develop branch does not exist yet.');

            if ($this->confirm('  Create it now?', true)) {
                shell_exec('git checkout -b develop 2>&1');
                shell_exec('git push origin develop 2>&1');
                $this->line('  <fg=green>✔</> develop branch created and pushed');
            }
        } else {
            shell_exec('git checkout develop 2>&1');
            shell_exec('git pull origin develop 2>&1');
            $this->line('  <fg=green>✔</> Switched to develop');
        }
    }
}
