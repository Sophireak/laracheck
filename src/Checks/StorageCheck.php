<?php

namespace Sophireak\Laracheck\Checks;

class StorageCheck
{
    public string $name = 'Storage & permissions';

    public function run(bool $autoFix = true): array
    {
        $issues   = [];
        $warnings = [];
        $fixed    = [];

        // Symlink
        if (! file_exists(public_path('storage'))) {
            if ($autoFix) {
                \Artisan::call('storage:link');
                $fixed[] = 'Storage symlink created';
            } else {
                $issues[] = 'Storage symlink missing — run: php artisan storage:link';
            }
        }

        // Writable directories
        $dirs = [
            'storage/app'                => storage_path('app'),
            'storage/app/public'         => storage_path('app/public'),
            'storage/logs'               => storage_path('logs'),
            'storage/framework/cache'    => storage_path('framework/cache'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/views'    => storage_path('framework/views'),
            'bootstrap/cache'            => base_path('bootstrap/cache'),
        ];

        foreach ($dirs as $label => $path) {
            if (! is_dir($path)) {
                if ($autoFix) {
                    mkdir($path, 0775, true);
                    $fixed[] = "Created {$label}";
                } else {
                    $issues[] = "{$label} directory missing";
                }
                continue;
            }

            if (! is_writable($path)) {
                if ($autoFix) {
                    shell_exec("chmod -R 775 {$path}");
                    $fixed[] = "Fixed permissions on {$label}";
                } else {
                    $issues[] = "{$label} is not writable";
                }
            }
        }

        return compact('issues', 'warnings', 'fixed');
    }
}
