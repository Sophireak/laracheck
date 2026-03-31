<?php

namespace Sophireak\Laracheck\Checks;

class AssetCheck
{
    public string $name = 'Frontend assets';

    public function run(bool $autoFix = true): array
    {
        $issues   = [];
        $warnings = [];
        $fixed    = [];

        // No package.json = not a frontend project, skip
        if (! file_exists(base_path('package.json'))) {
            return compact('issues', 'warnings', 'fixed');
        }

        // node_modules
        if (! is_dir(base_path('node_modules'))) {
            if ($autoFix) {
                shell_exec('npm install 2>&1');
                $fixed[] = 'npm install completed';
            } else {
                $issues[] = 'node_modules missing — run: npm install';
            }
        }

        // public/build missing entirely
        if (! is_dir(public_path('build'))) {
            if ($autoFix) {
                shell_exec('npm run build 2>&1');
                $fixed[] = 'Assets compiled (npm run build)';
            } else {
                $issues[] = 'Assets not compiled — run: npm run build';
            }
            return compact('issues', 'warnings', 'fixed');
        }

        // Only rebuild if frontend source files actually changed
        if ($this->frontendChanged()) {
            if ($autoFix) {
                shell_exec('npm run build 2>&1');
                $fixed[] = 'Assets rebuilt (frontend files changed)';
            } else {
                $warnings[] = 'Frontend files changed since last build — run: npm run build';
            }
        }

        return compact('issues', 'warnings', 'fixed');
    }

    private function frontendChanged(): bool
    {
        $buildTime = filemtime(public_path('build'));

        foreach (['resources/js', 'resources/css', 'resources/vue', 'resources/ts'] as $dir) {
            $fullPath = base_path($dir);
            if (! is_dir($fullPath)) continue;

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($fullPath, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->isFile() && $file->getMTime() > $buildTime) {
                    return true;
                }
            }
        }

        return false;
    }
}
