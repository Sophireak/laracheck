<?php

namespace Sophireak\Laracheck\Checks;

class ComposerCheck
{
    public string $name = 'Composer dependencies';

    public function run(bool $autoFix = true): array
    {
        $issues   = [];
        $warnings = [];
        $fixed    = [];

        if (! is_dir(base_path('vendor'))) {
            if ($autoFix) {
                shell_exec('composer install 2>&1');
                $fixed[] = 'composer install completed';
            } else {
                $issues[] = 'vendor/ missing — run: composer install';
            }
            return compact('issues', 'warnings', 'fixed');
        }

        // composer.json newer than lock
        $json = base_path('composer.json');
        $lock = base_path('composer.lock');

        if (file_exists($json) && file_exists($lock)) {
            if (filemtime($json) > filemtime($lock)) {
                $warnings[] = 'composer.json changed since last composer update';
            }
        }

        if (! file_exists($lock)) {
            $warnings[] = 'composer.lock missing — it should be committed to git';
        }

        return compact('issues', 'warnings', 'fixed');
    }
}
