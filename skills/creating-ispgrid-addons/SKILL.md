---
name: creating-ispgrid-addons
description: Use when creating or changing an ISPGrid add-on (a Composer package that extends the ISPGrid ISP panel), adding a page, permission, setting, customer tab or card, database, files or scheduled work to one, testing it inside an ISPGrid checkout, or releasing it and listing it in the ISPGrid add-on list (github.com/ispgrid/addons).
---

# Creating ISPGrid add-ons

## Overview

An ISPGrid add-on is its own repository and Composer package. It may use **only** core's public add-on API, and every
version is reviewed before servers can install it. If the add-on needs something core does not offer, that is a core
change first (an issue on github.com/ispgrid/ispgrid), never a way around the API.

The full guide: `docs/creating-an-addon.md` in github.com/ispgrid/addons. The API reference, versioned with core:
CONTRIBUTING.md, "Write an add-on", in github.com/ispgrid/ispgrid. What the review refuses: REVIEWING.md in
github.com/ispgrid/addons. Read the API reference before using any core class.

## Workflow

1. Start from github.com/ispgrid/addon-template and rename it (its README lists what). The repository name equals the
   package name: `vendor/package` lives at `https://github.com/vendor/package`.
2. Work in an ISPGrid checkout. Link the add-on as a path repository (`composer require vendor/package:@dev`) and
   never commit the checkout's changed `composer.json` and `composer.lock`.
3. For Laravel, Livewire, Tailwind and Pest, use Laravel Boost's skills: `php artisan boost:install` in the checkout
   (livewire-development, tailwindcss-development, laravel-best-practices, testing-best-practices; not
   fluxui-development). The rules below win where they disagree.
4. Write the failing test first, then the code. Run
   `ISPGRID_ADDONS=on php vendor/bin/pest --test-directory=vendor/vendor/package/tests vendor/vendor/package/tests`
   and Pint.
5. Release: tag `vX.Y.Z` (never move it), then a pull request to github.com/ispgrid/addons made with
   `php bin/add-version vendor/package vX.Y.Z`. Every new version needs its own pull request.

## Rules that win over general advice

| Area | Rule |
|---|---|
| composer.json | `require` has only `php`. Providers in `extra.ispgrid.providers`, never `extra.laravel`. `extra.ispgrid.core` lists supported series (`["0.1"]`). MIT, BSD, Apache-2.0 or ISC |
| Livewire | Multi-file components (`name.php` + `name.blade.php`), named `<prefix>::<page>`. No single-file components, no `⚡` in file names |
| Views | No logic in Blade. No Flux: only core's `x-ui.*` components. Every string in `__()` with Hindi in `lang/hi.json` |
| Tailwind | Add-on views are not in core's CSS build: a class core does not use does nothing. Use `x-ui.*` components and basic layout classes |
| Icons | Only names in core's `App\Support\Heroicons` |
| Access | Every component uses `AuthorizesEveryRequest` (`authorizeAccess()`) and calls `authorize()` again in each action. Access is a permission box, never a role name. A new box is never given to a role by name |
| Permissions | `Permissions::register()` in the provider on every core version, plus the `permissions` row (guard `web`) in a migration |
| FreeRADIUS | Never write any `rad*` table |
| Data | Own database `ispgrid_<name>` in `extra.ispgrid.databases`; files on the `addons` disk under one directory in `extra.ispgrid.paths`. `down()` never drops data |
| Files | `<x-ui.file-upload>`, type checked by content (`mimetypes:`), `max:10000`; sent only from your own route after the permission check with `PrivateFile::response()`; views and downloads logged in the Activity log |
| Customer form tab | `rules()` covers every saved field; `radius` rows in `save()` (may run twice), everything else in `saved()`; errors keyed `addonTabs.<key>.<field>` |
| Older cores | Check newer seams with `class_exists()` / `method_exists()` and keep working (and keep data safe) without them |

## Tests to write

Every permission (allowed, refused, an open page whose permission is taken away), tampered browser state, the data
written (and nothing outside the add-on's own database and directory), and the template's `TranslationsTest`.

## Common mistakes

| Mistake | Fix |
|---|---|
| `use Throwable;` in a Livewire component file | No namespace there: write `Throwable` without the import |
| `$user->hasRole(...)` | A permission box |
| A core class the API reference does not list | Ask for a core hook |
| A permission registered only on newer cores | Register it on every core |
| A Composer package in `require` | Not allowed |
| A moved tag | Servers refuse it: release a new version |
