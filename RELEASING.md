# Releasing PHP Serial

This repository uses a two-branch model and immutable tags.

| Branch | Purpose |
| --- | --- |
| `develop` | Integration branch. All feature work and fixes land here first. |
| `master` | Stable branch. Only fast-forward/merge commits from `develop` that passed CI. |

Published versions are GitHub tags named `vMAJOR.MINOR.PATCH` (SemVer) created
on `master`. The tag is what triggers the release workflow.

## One-time configuration

### Packagist

1. Create or log in to the [Packagist](https://packagist.org/) account that owns
   the `hyperthese` vendor.
2. Submit `https://github.com/Xowap/PHP-Serial` as a package. The package name
   is read from `composer.json` (`hyperthese/php-serial`).
3. Install the [Packagist GitHub App](https://github.com/apps/packagist) (or add
   the webhook shown by Packagist) so new tags are crawled automatically.
4. Confirm the package page lists the `v2.0.0` tag after the release.

No secret is required for the webhook flow. If you prefer the API instead, add
the Packagist username and API token as repository secrets and call
`https://packagist.org/api/update-package`.

### GitHub Pages

1. Go to **Settings → Pages**.
2. Under **Build and deployment → Source**, choose **GitHub Actions**.
3. Push to `master` (or run the **Documentation** workflow manually).
4. The site is published at <https://xowap.github.io/PHP-Serial/>.

### Optional hardening

- Protect `master`: require the `CI` workflow, disallow direct pushes, allow
  only merge commits from `develop`.
- Protect `develop`: require the `CI` workflow and at least one review.
- Enable Dependabot for GitHub Actions and Composer.

## Release checklist

```bash
# 1. Everything is green on develop
git checkout develop
git pull --ff-only
composer check

# 2. Merge develop into master
git checkout master
git pull --ff-only
git merge --no-ff develop -m "Release v2.0.0"

# 3. Tag the release commit
git tag -a v2.0.0 -m "PHP Serial 2.0.0"
git push origin master
git push origin v2.0.0
```

The tag triggers:

- `.github/workflows/release.yml`: rebuilds the documentation-independent test
  matrix requirements (`composer check`) and creates the GitHub Release with
  generated notes.
- Packagist: crawls the new tag and publishes `2.0.0` (webhook flow).
- `.github/workflows/docs.yml`: deploys the documentation from `master`.

## After the release

```bash
# Keep develop aligned with the released code
git checkout develop
git merge --ff-only master
git push origin develop
```

## Versioning notes

- `composer.json` intentionally has no `version` field; Packagist derives the
  version from the tag. A `dev-develop` branch alias (`2.0.x-dev`) is declared
  for consumers that track the development branch.
- A breaking change requires a new major tag (`v3.0.0`) and a migration section
  in `CHANGELOG.md`.
- Do not move or delete published tags; Packagist caches them.
