<?php

namespace Sophireak\Laracheck\Checks;

class MigrationCheck
{
    public string $name = 'Migrations';

    public function run(bool $autoFix = true): array
    {
        $issues   = [];
        $warnings = [];
        $fixed    = [];

        try {
            $output = shell_exec('php artisan migrate:status --no-ansi 2>&1');

            // Can't connect — soft skip
            if (str_contains($output ?? '', 'Could not connect')
                || str_contains($output ?? '', 'Connection refused')
                || str_contains($output ?? '', 'Access denied')) {
                $warnings[] = 'Cannot connect to DB — check your .env DB credentials';
                return compact('issues', 'warnings', 'fixed');
            }

            $pending = substr_count($output ?? '', 'Pending');

            if ($pending > 0) {
                // Never auto-run migrations — shared DB risk
                $warnings[] = "{$pending} pending migration(s) — run: php artisan migrate";
            }

        } catch (\Throwable $e) {
            $warnings[] = 'Could not check migrations: ' . $e->getMessage();
        }

        return compact('issues', 'warnings', 'fixed');
    }
}
