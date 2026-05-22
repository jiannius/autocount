# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Laravel package (`jiannius/autocount`) that wraps the Autocount accounting software's HTTP API. It is not a standalone application — it's a library consumed by Laravel apps via Composer.

- PSR-4 root: `Jiannius\Autocount\` → `src/`
- Service provider auto-registered via `composer.json` → `extra.laravel.providers`
- Container binding: `app('autocount')` returns a fresh `Autocount` instance (`AutocountServiceProvider::boot`)

There is no build step, no lint config, and no test suite committed. `orchestra/testbench` is a dev dependency but nothing wires it up — adding tests means standing the harness up from scratch.

## Architecture

### The `Autocount` class is a trait composition

`src/Autocount.php` is intentionally thin: a constructor that pulls config from `services.autocount.*` (`url`, `app_id`, `user_id`, `password`), plus token handling and a single `callApi()` method. Every resource-specific method (invoice/debtor/creditor/cashbook/etc. CRUD) lives in its own trait under `src/Traits/` and is `use`d into the main class.

When adding support for a new Autocount API resource, the pattern is:

1. Create `src/Traits/<Resource>.php` with methods like `create<Resource>`, `get<Resource>s`, `update<Resource>`, `delete<Resource>s`.
2. Inside each method, call `$this->callApi(uri: '<Resource>/...', method: 'POST', data: ...)` — never call `Http::*` directly. `callApi` handles the cached JWT, the `AppId` header, and the response-level `Status === 'Fail'` check.
3. Document the upstream payload as a PHPDoc block above the method (see `Traits/Invoice.php`, `Traits/CashBook.php`). These docblocks are the de-facto API spec — Autocount's docs are not in the repo.
4. Register the trait with a `use` statement at the top of `src/Autocount.php`.

### Token & error conventions

- Token is cached in Laravel's cache under `autocount_token_<app_id>` by `getToken()`. It's only re-fetched on a cache miss; the package never expires the token itself.
- Two failure layers in `callApi()`: HTTP-level failure (`$result->failed()`) routes through `setFailedCallback()` if set, otherwise re-throws; response-level failure is detected by `Status === 'Fail'` in the JSON body and thrown as `\Exception` with the upstream `Message`.
- Several traits also re-check `Status === 'Fail'` after `callApi()` returns — this is a defensive double-check (the inner `callApi` already throws on top-level fail, but list responses may contain per-row statuses). When mirroring this in a new trait, follow the convention of the closest existing trait.
- `getX()` methods swallow "not found" errors and return `[]` (see `getInvoices`, `getCashBooks`). Match this idiom in new read methods so callers don't need try/catch for empty-result cases.

### Eloquent side: `HasAutocountFields`

`src/Models/Traits/HasAutocountFields.php` is mixed into the **consumer app's** Eloquent models to store the mapping between local records and Autocount codes (debtor code, creditor code, accounting codes, plus raw `request`/`response` payloads for audit). It uses a morph-many relation to `AutocountField` and the typed field is keyed by `AutocountFieldType` enum (`src/Enums/AutocountFieldType.php`).

Important: this trait references `App\Models\AutocountField` (the consumer app's model), not `Jiannius\Autocount\Models\AutocountField`. Consumers are expected to extend or alias the package model into their `App\Models` namespace. If you change the field types or the trait, keep this consumer-side coupling in mind.

The single migration `database/migrations/autocount_001_autocount_fields.php` creates the `autocount_fields` table (ulid pk, morphable parent, json `data`). The `HasAutocountFieldsObserver` deletes related fields when the parent model is deleted, including soft-deleted parents (`withoutGlobalScopes()`).

## Consumer setup (for reference)

A consuming app needs `config/services.php` entries:

```php
'autocount' => [
    'url' => env('AUTOCOUNT_URL'),
    'app_id' => env('AUTOCOUNT_APP_ID'),
    'user_id' => env('AUTOCOUNT_USER_ID'),
    'password' => env('AUTOCOUNT_PASSWORD'),
],
```

Settings can also be overridden per-call via `setUrl()`, `setAppId()`, `setUserId()`, `setPassword()`, `setFailedCallback()` — all chainable.

## Working in this repo

- `composer install` to pull deps. There is nothing else to "run" — this is a library.
- When debugging upstream API behavior, the fastest path is `$autocount->testConnection()` (returns bool, clears the cached token on success so the next call re-authenticates).
- Commit style from history: `ft:` for features, `fix:` for fixes (see `git log --oneline`).
