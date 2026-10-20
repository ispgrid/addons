# Listing an add-on or a new version

1. Tag a release of your add-on (`v0.1.0`). Its repository is `https://github.com/vendor/package`, public, and its
   `composer.json` has:
   - `name` equal to the repository name;
   - a license from MIT, BSD-2-Clause, BSD-3-Clause, Apache-2.0 or ISC;
   - `require` with only `php`;
   - `extra.ispgrid` with `core`;
   - no `extra.laravel`.
2. Fork this repository and run `php bin/add-version vendor/package v0.1.0`. It reads the tag's commit and your
   `composer.json` and adds the entry.
3. Open a pull request. Say what the add-on does, or what changed since the last listed version.

CI (`php bin/check`) checks:
- the format;
- that the tag points at the listed commit;
- the `composer.json` rules above;
- that versions already listed are unchanged.

Then the maintainer reviews the code (see [REVIEWING.md](REVIEWING.md)) and merges, or asks for changes.

Never move a tag after it is listed: servers compare the tag with the listed commit and refuse to install when they
differ. Fix a release with a new version.

A listed version can't be changed or removed. To pull one back, open a pull request that adds
`"yanked": "<the reason>"` to it.

Run the tests with `php tests/run.php`, and the full check with `php bin/check`.
