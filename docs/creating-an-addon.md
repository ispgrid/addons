# Creating an ISPGrid add-on

An add-on is its own repository and Composer package that adds a feature to [ISPGrid](https://github.com/ispgrid/ispgrid)
without changing core: a page, a setting, a tab on the customer page, scheduled work, its own database. Servers
install it with `sudo ispgrid addon add vendor/package`, once it is in this list.

An add-on runs inside the panel with access to the `radius` database that decides who gets online. So it may use only
core's public add-on API, and every version is reviewed before servers can install it. If your add-on needs something
core does not offer, ask for it in an [ISPGrid issue](https://github.com/ispgrid/ispgrid/issues) first: a new hook in
core, released in a core version, is the way, never reaching around the API.

**Two documents go with this guide:**
- **The API reference:** core's [CONTRIBUTING.md, "Write an add-on"](https://github.com/ispgrid/ispgrid/blob/master/CONTRIBUTING.md#write-an-add-on).
  It is versioned with core. Read it at the tag of the core version you build for: it lists exactly which classes you
  may use and since which core version.
- **What the review refuses:** [REVIEWING.md](../REVIEWING.md). Read it before you write code, not after.

## 1. Start from the template

Use [ispgrid/addon-template](https://github.com/ispgrid/addon-template) (**Use this template** on GitHub). It is a
small add-on that works: a page in the Add-ons menu, a setting on System, then Add-ons, two permission boxes, Hindi
strings, tests and CI. Its README says what to rename.

Name the repository like the package: a server installs `vendor/package` from `https://github.com/vendor/package`.

## 2. Set up an ISPGrid checkout

You develop and test inside an ISPGrid checkout, next to your add-on:

```bash
git clone https://github.com/ispgrid/ispgrid.git
cd ispgrid
composer install
npm ci && npm run build
cp .env.example .env && php artisan key:generate
```

The tests use MySQL or MariaDB on `127.0.0.1:3306`, user `root` without a password, database `radius_test` (set in
`phpunit.xml`): create that database first. Link your add-on and run its tests:

```bash
cp composer.json composer.lock ../addon-backup/      # restore them when you are done
composer config repositories.my-addon '{"type": "path", "url": "../my-addon", "options": {"symlink": true}}'
composer require vendor/package:@dev
ISPGRID_ADDONS=on php vendor/bin/pest --test-directory=vendor/vendor/package/tests vendor/vendor/package/tests
```

Never commit the checkout's changed `composer.json` and `composer.lock`. Core's own tests run with add-ons off
(`ISPGRID_ADDONS` unset).

### With an AI coding agent

This repository has an agent skill, [skills/creating-ispgrid-addons](../skills/creating-ispgrid-addons/SKILL.md):
copy it into your agent's skills folder (for Claude Code, `.claude/skills/` in your add-on or the checkout).

For Laravel, Livewire, Tailwind and Pest, ISPGrid uses [Laravel Boost](https://github.com/laravel/boost), which is
already in the checkout. Run `php artisan boost:install` there and pick the skills `livewire-development`,
`tailwindcss-development`, `laravel-best-practices` and `testing-best-practices`. Leave out `fluxui-development`:
ISPGrid does not use Flux. Boost writes the skills for the exact versions ISPGrid uses; run `php artisan
boost:update` after you update the checkout. Where a Boost skill and this guide disagree, this guide wins (see
section 4).

## 3. Building blocks

Everything is registered in your service provider, listed in `composer.json` under `extra.ispgrid.providers` (never
`extra.laravel`). The exact signatures are in the API reference.

| You want | Use |
|---|---|
| A page in the menu | `Navigation::add(new NavItem('addons', ...))`: the "Add-ons" group. A big add-on may add its own group, order 36 to 39 |
| A permission box | `Permissions::register('<verb>-<noun>', label, area, implies)` on every core version, plus its `permissions` row in a migration |
| Settings | One Livewire component: `AddonSettings::register('vendor/package', 'prefix::settings', '<your-permission>')`; values with `AppSetting::put('<prefix>.<name>', ...)` |
| A tab on the customer page | `CustomerTabs::register(...)` (core 0.1.2) |
| A tab in the customer form | A class implementing `App\Contracts\CustomerFormTab`, `CustomerFormTabs::register(...)` |
| A card on the customer page | `CustomerCards::register(...)` |
| Work on finished sessions | `SessionKeepers::register(...)` (`App\Contracts\SessionKeeper`) |
| A label in the Activity log | `ActivityFormatter::addEventLabel('<event>', fn () => __('...'))` |
| Scheduled work, commands | `callAfterResolving(Schedule::class, ...)`, `$this->commands([...])` |
| Views, strings, routes, migrations | `loadViewsFrom`, `loadJsonTranslationsFrom`, `loadRoutesFrom`, `loadMigrationsFrom`, `Livewire::addNamespace('<prefix>', viewPath: ...)` |
| Its own data | A database named `ispgrid_<name>`, created in a migration and listed in `extra.ispgrid.databases` |
| Files | The `addons` disk, under one directory listed in `extra.ispgrid.paths`; sent with `PrivateFile::response()` from your own route |

`composer.json` has `"require": {"php": "^8.4"}` and nothing else: an add-on can't bring Composer packages (the
list's CI refuses them). Its `description` is shown on System, then Add-ons, and in `sudo ispgrid addon list`.
`extra.ispgrid.core` lists the core series it supports, such as `["0.1"]`.

## 4. ISPGrid's rules (they win over general Laravel and Livewire advice)

**Pages and views**
- Livewire components are **multi-file**: the class in `name.php`, the view in `name.blade.php` next to it. No
  single-file components and no `⚡` in file names, whatever the Livewire docs or a Boost skill show.
- **No logic in Blade.** Views only show what the class gives them (computed properties, public properties), so
  themes can replace them later.
- **No Flux.** Use core's `x-ui.*` components: `heading`, `text`, `card`, `input`, `select`, `textarea`, `checkbox`,
  `switch`, `button`, `table` with `th` and `td`, `badge`, `alert`, `modal`, `tabs`, `file-upload` and the others
  in core's `resources/views/components/ui`.
- **Tailwind:** core builds its CSS from its own views only, so add-on views are not scanned. A Tailwind class that
  core does not use anywhere has no CSS, and silently does nothing. Build pages from `x-ui.*` components and simple
  layout classes (`grid`, `gap-*`, `sm:grid-cols-*`, `mt-*`), and check every page in the browser.
- **Icons:** menu items and buttons can use only the icon names core knows (`App\Support\Heroicons`, such as
  `puzzle-piece`, `document-magnifying-glass`, `users`).
- Every string goes through `__()`, with its Hindi in `lang/hi.json`.

**Access**
- Every Livewire component (page, settings, tab, card) re-checks its permission on every request: `use
  AuthorizesEveryRequest` with `authorizeAccess()`, and `authorize()` again in each action that changes something.
- Access is always a permission box, never a role name. A migration may give a new box only to roles that already had
  the access it replaces. Never give it to a role by name: Admin has every box, and operators tick the rest on Roles.
- Routes: `Route::middleware(['web', 'auth'])`, route names starting with your prefix.

**Data**
- **Never write a `rad*` table** (`radcheck`, `radreply`, `radusergroup`, `radacct`, ...). What FreeRADIUS must do
  differently is a core change.
- Your own data goes in your own database (`ispgrid_<name>`) and your own directory on the `addons` disk, both listed
  in `extra.ispgrid`. The backup keeps them, and `sudo ispgrid addon remove vendor/package --delete-data` removes
  them. Nothing else removes them.
- A table in `radius` is saved atomically with the customer, but `--delete-data` does not drop it: use one only for
  data that is harmless to keep.
- A migration's `down()` never drops data. Never delete a customer's data on your own when a customer is deleted.

**Customer form tabs**
- The tab's state comes from the browser: `rules()` covers every field you save, and `warnings()` copes with any
  shape.
- Rows in `radius` go in `save()`, which runs inside core's transaction and may run twice. Files, messages and
  writes to your own database go in `saved()`, after the commit.
- Validation errors are keyed `addonTabs.<key>.<field>`.

**Files**
- Upload with `<x-ui.file-upload>`, and validate the type by content (`mimetypes:`) and the size (`max:10000` or
  less).
- Store files under random names in your directory. Send them only from your own route, after the permission check,
  with `PrivateFile::response()`. Never give a file a public URL.
- Log every view or download of private customer files in the Activity log.

**Older cores**
- A server on an older core receives your newest version that lists its series. Check for newer seams with
  `class_exists(...)` or `method_exists(...)`, and keep working without them.
- Keep registering your permissions on every core, keep scheduled work running, and never lose data. The API
  reference says which seam arrived in which core version.

## 5. Test

Test with Pest inside the checkout, using core's factories and pages (for example
`Livewire::test('pages::customers.form')`). Your runtime code may use only the API; your tests may use more.

**What to test:**
- **Every permission:** allowed, refused, and an open page whose permission is taken away.
- **Tampered state:** form-tab state changed in the browser.
- **The data you write:** your own database and files, and that nothing is written outside them.
- **Translations:** the `TranslationsTest` from the template, which checks that every string has its Hindi.

If you have your own database, empty its tables in a `beforeEach` in `tests/Pest.php`: it sits outside the
`RefreshDatabase` transaction. Run Pint on your code. The template's CI runs the tests inside ISPGrid's `master`
every week, so a change in core that breaks your add-on shows up early.

## 6. Try it on a server

On a test server with ISPGrid v0.1.3 or newer:

```bash
sudo ispgrid addon add vendor/package@dev-master --allow-unlisted    # a branch, before it is listed
sudo ispgrid addon list
sudo ispgrid addon remove vendor/package --delete-data               # also removes its database and files
```

`--allow-unlisted` installs code that was not reviewed, and every run says so. Use it only on your own servers.

## 7. Release and list it

1. Tag a release, such as `v0.1.0`, on your default branch. **Never move a tag after it is listed.** Servers compare
   the tag with the listed commit and refuse to install when they differ. Fix a release with a new version.
2. Fork this repository, run `php bin/add-version vendor/package v0.1.0`, check with `php bin/check`, and open a
   pull request. Say what the add-on does, what it stores, and what it sends anywhere.
3. CI checks the format, the tag and your `composer.json`. Then the maintainer reads the code
   ([REVIEWING.md](../REVIEWING.md)) and merges it, or asks for changes.
4. Every new version comes the same way: tag it, then open a pull request adding it. The review reads the diff from
   your last listed version.

To pull a broken version back, open a pull request that adds `"yanked": "<the reason>"` to it.

## Common mistakes

| Mistake | Fix |
|---|---|
| `use Throwable;` or another global class import in a Livewire component file | The compiled class has no namespace: write `Throwable` without the import |
| `$user->hasRole('Technician')` | Add a permission box: roles are the operator's to define |
| A core class that the API reference does not list | Ask for a core hook instead: anything else can change in any release |
| A permission registered only on newer cores | Older cores refuse to save a role holding a box they do not list: register it on every core |
| A Tailwind class core does not use | It has no CSS on the server: use `x-ui.*` components |
| Committing the checkout's `composer.json` and `composer.lock` | Restore them from your copy |
| `extra.laravel` in composer.json | Core loads providers from `extra.ispgrid.providers` only |
| A Composer package in `require` | Not allowed: copy the small piece you need, or ask for it in core |
