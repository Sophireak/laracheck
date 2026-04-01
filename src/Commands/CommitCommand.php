<?php

namespace Sophireak\Laracheck\Commands;

use Illuminate\Console\Command;

class CommitCommand extends Command
{
    protected $signature   = 'laracheck:commit';
    protected $description = 'AI-suggested commit message based on your staged changes';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=cyan;options=bold>LaraCheck</> <fg=gray>— Smart Commit</>');
        $this->line('  <fg=gray>─────────────────────────────────────────</>');
        $this->newLine();

        // Check staged files exist
        $staged = trim(shell_exec('git diff --cached --name-only 2>&1') ?? '');

        if (empty($staged)) {
            $this->line('  <fg=yellow>⚠</> No staged files found.');
            $this->line('  <fg=gray>Stage your changes first: git add .</>');
            $this->newLine();
            return 0;
        }

        // Show what's staged
        $files = array_filter(explode("\n", $staged));
        $count = count($files);
        $this->line("  <fg=green>✔</> {$count} staged file(s):");
        foreach ($files as $file) {
            $this->line("    <fg=gray>- {$file}</>");
        }
        $this->newLine();

        // Get the diff (limit size to avoid huge API calls)
        $diff = shell_exec('git diff --cached 2>&1') ?? '';
        $diff = mb_substr($diff, 0, 3000); // cap at 3000 chars

        // Generate AI suggestion
        $key = config('laracheck.anthropic_api_key', env('ANTHROPIC_API_KEY'));

        $suggested = null;

        if (! empty($key)) {
            $this->line('  <fg=cyan>→</> AI is reading your changes...');

            $suggested = $this->suggestMessage($diff, $files, $key);

            if ($suggested) {
                $this->newLine();
                $this->line('  <fg=cyan;options=bold>Suggested commit message:</>');
                $this->line("  <fg=green>{$suggested}</>");
                $this->newLine();
            }
        } else {
            $this->line('  <fg=yellow>⚠</> No ANTHROPIC_API_KEY — skipping AI suggestion.');
            $this->line('  <fg=gray>Add it to .env to enable smart commit messages.</>');
            $this->newLine();
        }

        // Let them confirm, edit, or write their own
        $choices = array_filter([
            $suggested ? "Use: {$suggested}" : null,
            'Write my own message',
            'Cancel',
        ]);

        $choice = $this->choice('  What do you want to do?', array_values($choices), 0);

        if ($choice === 'Cancel') {
            $this->line('  <fg=yellow>Cancelled.</> Your staged changes are still ready.');
            $this->newLine();
            return 0;
        }

        if ($choice === 'Write my own message') {
            $message = $this->ask('  Your commit message');
            if (empty(trim($message ?? ''))) {
                $this->line('  <fg=red>Empty message. Commit cancelled.</>');
                $this->newLine();
                return 1;
            }
        } else {
            // They chose the suggested message — allow editing
            $message = $this->ask('  Confirm or edit the message', $suggested);
        }

        // Commit
        $output = shell_exec("git commit -m " . escapeshellarg($message) . " 2>&1");

        $this->newLine();

        if (
            str_contains($output ?? '', 'master')
            || str_contains($output ?? '', 'main')
            || str_contains($output ?? '', 'feat')
            || str_contains($output ?? '', 'fix')
        ) {
            $this->line('  <fg=green;options=bold>✔ Committed!</>');
            $this->line("  <fg=gray>{$message}</>");
        } else {
            $this->line('  <fg=green>✔ Done:</>');
            $this->line("  <fg=gray>{$output}</>");
        }

        $this->newLine();
        $this->line('  <fg=gray>When ready to push: git push origin ' . trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>&1') ?? 'your-branch') . '</>');
        $this->newLine();

        return 0;
    }

    private function suggestMessage(string $diff, array $files, string $key): ?string
    {
        $fileList = implode(', ', array_slice($files, 0, 10));

        $prompt = "Based on this git diff, suggest ONE commit message following conventional commits format (feat:, fix:, chore:, refactor:, etc.).\n\n"
            . "Changed files: {$fileList}\n\n"
            . "Diff:\n{$diff}\n\n"
            . "Rules:\n"
            . "- One line only, max 72 characters\n"
            . "- Start with type: feat|fix|chore|refactor|style|docs\n"
            . "- Be specific about what changed\n"
            . "- No period at the end\n"
            . "- Output ONLY the commit message, nothing else";

        $payload = json_encode([
            'model'      => config('laracheck.model', 'claude-sonnet-4-20250514'),
            'max_tokens' => 100,
            'system'     => 'You are a Git expert. Output only the commit message, nothing else. No explanation, no quotes, no markdown.',
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: ' . $key,
                'anthropic-version: 2023-06-01',
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
        ]);

        $res  = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // DEBUG — shows exactly what's happening
        $this->line("  <fg=gray>HTTP: {$code}</>");
        $this->line("  <fg=gray>Curl error: " . ($err ?: 'none') . "</>");
        $this->line("  <fg=gray>Response: " . mb_substr($res ?? '', 0, 300) . "</>");

        $data = json_decode($res, true);
        $text = trim($data['content'][0]['text'] ?? '');

        return ! empty($text) ? $text : null;
    }
}
