# ISPGrid add-ons

The list of add-ons an [ISPGrid](https://github.com/ispgrid/ispgrid) server may install. Each version is listed with
the exact commit that was reviewed, and a server installs that commit and nothing else.

"Listed" means reviewed by ISPGrid before listing: the code was read for what it does with your server and your
customers' data. It does not mean guaranteed safe. Read an add-on's README before you install it.

## On your server

ISPGrid v0.1.3 or newer:

    sudo ispgrid addon list                        # installed add-ons, updates, and what else is listed
    sudo ispgrid addon add ispgrid/documents       # the newest version for your ISPGrid
    sudo ispgrid addon add ispgrid/ipdr@v0.1.2     # hold one version
    sudo ispgrid addon update                      # move every add-on to its newest listed version
    sudo ispgrid addon remove ispgrid/documents    # its data, such as its database, is kept

The server reads this repository with `git clone`, so a change merged here reaches servers on their next add or
update. Add-ons are never installed from the panel.

A version that turns out to be broken or unsafe is not deleted: it is marked `yanked` with the reason. Servers stop
installing it, and `sudo ispgrid addon list` shows the reason on servers that have it.

To install something that is not listed (your own add-on, or a branch), add `--allow-unlisted`. Its code was not
reviewed, and every installer run says so.

## Write your own add-on

- **[The guide](docs/creating-an-addon.md):** from an empty repository to a listed add-on.
- **[The template](https://github.com/ispgrid/addon-template):** a small add-on that works, to start from (**Use this
  template** on GitHub).
- **[The agent skill](skills/creating-ispgrid-addons/SKILL.md):** for AI coding agents such as Claude Code. Copy it
  into your agent's skills folder.
- **The API reference:** core's
  [CONTRIBUTING.md, "Write an add-on"](https://github.com/ispgrid/ispgrid/blob/master/CONTRIBUTING.md#write-an-add-on).
- **What the review looks for:** [REVIEWING.md](REVIEWING.md).
- **How to list it:** [CONTRIBUTING.md](CONTRIBUTING.md).

## registry.json

```json
{
  "schema": 1,
  "addons": {
    "vendor/package": {
      "description": "One line, shown by sudo ispgrid addon list",
      "author": "Your name",
      "official": false,
      "versions": {
        "v0.1.0": { "commit": "<the 40-character commit the tag points to>", "core": ["0.1"] },
        "v0.1.1": { "commit": "...", "core": ["0.1"], "yanked": "Why it was pulled back" }
      }
    }
  }
}
```

The add-on's code lives at `https://github.com/vendor/package`. `core` lists the ISPGrid series the version supports
and equals `extra.ispgrid.core` in its `composer.json`.

## License

MIT
