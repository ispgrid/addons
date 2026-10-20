# Reviewing an add-on

An add-on runs inside the panel, as the panel's user, with full access to the `radius` database that decides who
gets online. Review it like code you are about to run on every server.

For a new add-on, read all of it. For a new version, read the whole diff from the last listed commit:

    git diff <listed commit> <new commit>

Refuse, or ask for changes, when the code:

- writes any `rad*` table, or runs SQL outside its own `ispgrid_...` database and core's documented add-on API;
- runs shell commands (`exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen`, `Process`, backticks) or
  `eval`, or loads code from the network;
- makes network calls that its README does not explain;
- reads `.env`, keys, or other add-ons' data, or writes outside its own path on the `addons` disk;
- adds routes without `auth` and a permission check, or a Livewire component without `AuthorizesEveryRequest`;
- serves private files outside `PrivateFile`, or without a permission check and an Activity log entry;
- uses `autoload.files`, or has obfuscated, encoded or minified PHP;
- registers a permission by checking a role name instead of a permission box;
- drops data in a migration's `down()`, or deletes a customer's data on its own;
- does anything its README does not say it does.

Also check:

- its migrations create only its own database, tables and `permissions` rows;
- `extra.ispgrid` lists every database and path it uses;
- the README says what it stores and what it sends anywhere;
- the author answers questions on the pull request.

The rules add-ons are written to are in core's
[CONTRIBUTING.md](https://github.com/ispgrid/ispgrid/blob/master/CONTRIBUTING.md#write-an-add-on).
