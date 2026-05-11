# Release Process

Phison is distributed as the Composer package `ydah/phison`. Composer package
versions are derived from Git tags; do not add a `version` field to
`composer.json`.

## Versioning Policy

Use Semantic Versioning once the first stable release is tagged:

- `MAJOR` changes may break grammar syntax, generated parser behavior, CLI
  flags, or runtime interfaces.
- `MINOR` changes add compatible grammar features, report formats, table
  layouts, diagnostics, or target PHP support.
- `PATCH` changes fix bugs, improve diagnostics, or make compatible
  performance and documentation changes.

Before `v1.0.0`, `0.MINOR.0` may contain breaking changes. Prefer documenting
those breaks in the GitHub release notes.

## Packagist

Packagist should track `https://github.com/ydah/phison`. Configure the
Packagist GitHub hook so pushed tags are indexed automatically. If the hook is
not configured yet, update the package manually from the Packagist package
page after pushing a tag.

## Checklist

1. Confirm the working tree is clean except for intended release changes.
2. Run the same checks as CI:

   ```bash
   composer validate --strict
   composer install --no-interaction --no-progress --prefer-dist
   composer lint
   composer test
   composer phpstan
   composer cs-check
   composer validate:example
   composer generate:example
   composer run:example
   composer benchmark
   ```

3. Draft GitHub release notes with user-visible changes and any breaking
   changes.
4. Create and push an annotated release tag:

   ```bash
   git tag -a v0.1.0 -m "Release v0.1.0"
   git push origin v0.1.0
   ```

5. Wait for the release workflow to pass and create the GitHub release.
6. Confirm Packagist lists the new tag and the install command works:

   ```bash
   composer require ydah/phison --dev
   ```
