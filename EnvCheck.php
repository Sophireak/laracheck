<?php

namespace Sophireak\Laracheck\Checks;

class EnvCheck
{
    public string $name = '.env configuration';

    public function run(bool $autoFix = true): array
    {
        $issues   = [];
        $warnings = [];
        $fixed    = [];

        $envPath     = base_path('.env');
        $examplePath = base_path('.env.example');

        // .env missing
        if (! file_exists($envPath)) {
            if ($autoFix && file_exists($examplePath)) {
                copy($examplePath, $envPath);
                $fixed[]    = '.env created from .env.example';
                $warnings[] = 'Fill in DB_PASSWORD and other secrets in .env';
            } else {
                $issues[] = '.env is missing — run: cp .env.example .env';
            }
            return compact('issues', 'warnings', 'fixed');
        }

        // APP_KEY
        if (empty(env('APP_KEY'))) {
            if ($autoFix) {
                \Artisan::call('key:generate');
                $fixed[] = 'APP_KEY generated';
            } else {
                $issues[] = 'APP_KEY not set — run: php artisan key:generate';
            }
        }

        // Keys missing vs .env.example
        if (file_exists($examplePath)) {
            $missing = array_diff(
                $this->parseKeys($examplePath),
                $this->parseKeys($envPath)
            );
            if (! empty($missing)) {
                $warnings[] = 'Missing .env keys: ' . implode(', ', $missing);
            }
        }

        // Empty critical DB keys
        $empty = array_filter(
            ['DB_HOST', 'DB_DATABASE', 'DB_USERNAME'],
            fn($k) => empty(env($k))
        );
        if (! empty($empty)) {
            $warnings[] = 'Empty DB keys: ' . implode(', ', $empty);
        }

        // APP_DEBUG on production
        if (env('APP_ENV') === 'production' && env('APP_DEBUG', false)) {
            $issues[] = 'APP_DEBUG=true in production — must be false';
        }

        return compact('issues', 'warnings', 'fixed');
    }

    private function parseKeys(string $path): array
    {
        $keys = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#') || ! str_contains($line, '=')) continue;
            $keys[] = explode('=', $line, 2)[0];
        }
        return $keys;
    }
}
