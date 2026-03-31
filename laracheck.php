<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Anthropic API Key
    |--------------------------------------------------------------------------
    | Used for AI-powered explanations when issues are found.
    | Get yours at https://console.anthropic.com/
    | Better to set this in .env as ANTHROPIC_API_KEY.
    */
    'anthropic_api_key' => env('ANTHROPIC_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Model
    |--------------------------------------------------------------------------
    */
    'model' => env('LARACHECK_MODEL', 'claude-sonnet-4-20250514'),

    /*
    |--------------------------------------------------------------------------
    | Protected Branches
    |--------------------------------------------------------------------------
    | Pushing directly to these branches triggers a warning.
    */
    'protected_branches' => ['main', 'master'],

    /*
    |--------------------------------------------------------------------------
    | Mode
    |--------------------------------------------------------------------------
    | soft   = warns but never blocks push (default, good for adoption)
    | strict = blocks push if unfixed issues remain
    |
    | Override per-push with: php artisan laracheck --strict
    */
    'mode' => env('LARACHECK_MODE', 'soft'),

];
