## Add-on

- Repository: https://github.com/
- Version(s):
- What it does, or what changed since the last listed version:
- What it stores, and what it sends anywhere:

## Review (maintainer)

- [ ] Read the whole code, or the whole diff from the last listed commit
- [ ] No `rad*` writes, no SQL outside its own database and core's API
- [ ] No shell commands, `eval`, or code loaded from the network
- [ ] Network calls explained in the README
- [ ] No `.env`, keys or other add-ons' data; files only in its own path
- [ ] Routes and components check a permission on every request
- [ ] No `autoload.files`, nothing obfuscated or minified
- [ ] Migrations create only its own objects; `down()` keeps data
- [ ] README matches what the code does
