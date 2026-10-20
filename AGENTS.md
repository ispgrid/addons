# Instructions for AI coding agents

This repository is the ISPGrid add-on list: `registry.json` names every add-on version an ISPGrid server may install,
at the commit that was reviewed.

- **Building an add-on:** follow `skills/creating-ispgrid-addons/SKILL.md` and `docs/creating-an-addon.md`. Start from
  github.com/ispgrid/addon-template.
- **Listing a version:** run `php bin/add-version vendor/package vX.Y.Z`, then `php bin/check`, and open a pull
  request. Never edit the `commit` or `core` of a version that is already listed. Pull a version back by adding
  `"yanked": "<reason>"`.
- **Tests:** `php tests/run.php`.
