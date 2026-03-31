# LaraCheck

A smart pre-push helper for Laravel teams.
Checks your project, fixes safe issues silently, and explains problems with AI.

---

## Install

```bash
composer require sophireak/laracheck
php artisan laracheck:install
```

Done. It runs automatically before every `git push`.

---

## Commands

```bash
# Run manually
php artisan laracheck

# Run without auto-fix
php artisan laracheck --no-fix

# Run without AI explanations (faster, offline)
php artisan laracheck --no-ai

# Block push if issues remain
php artisan laracheck --strict

# One-time setup
php artisan laracheck:install

# Emergency bypass
git push --no-verify
```

---

## What it checks

| Check | Auto-fix |
|---|---|
| `.env` exists | ✅ |
| `APP_KEY` set | ✅ |
| Missing `.env` keys | ⚠ warn |
| Pending migrations | ⚠ warn only |
| `node_modules` | ✅ |
| Assets (only if frontend changed) | ✅ |
| Storage symlink & permissions | ✅ |
| `vendor/` | ✅ |
| Uncommitted files | ⚠ warn |
| Push to main/master | ⚠ warn |

Auto-fix is safe only — setup, installs, permissions.
Never touches source code or git history.

---

## Config

```bash
php artisan vendor:publish --tag=laracheck-config
```

Edit `config/laracheck.php`:

```php
'mode'               => 'soft',        // soft | strict
'protected_branches' => ['main', 'master'],
'anthropic_api_key'  => env('ANTHROPIC_API_KEY'),
```

---

## .env

```env
ANTHROPIC_API_KEY=sk-ant-your-key-here
LARACHECK_MODE=soft
```

---

## License
MIT — Sophireak
