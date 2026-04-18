# Release Checklist

Use this checklist when cutting the next pre-`1.0.0` release of `apntalk/esl-core`.

## Pre-flight

- Confirm the working tree is clean or intentionally scoped.
- Resolve or explicitly exclude unrelated local changes before tagging.
- Review `CHANGELOG.md` and any release notes you intend to publish for the intended version.
- Move release-scoped changelog entries out of `[Unreleased]` into the intended
  version section before tagging or publishing.
- Confirm deferred items remain out of scope for the release.
- If `docs/releases/` contains older draft notes, confirm they are clearly marked
  as historical or superseded rather than current release truth.

## Verification

- Run `composer validate --strict`
- Run `composer check`
- If the touched area is narrow, run the narrowest useful suite first: `composer unit`, `composer contract`, or `composer integration`
- Run `composer smoke` when you want a fast supported-path sanity pass in addition to the main release gate
- If you need a standalone style check outside `composer check`, run `./vendor/bin/php-cs-fixer fix --dry-run --diff --sequential`

## Release-facing review

- Confirm `README.md` still states clearly what the package is and is not.
- Confirm preferred vs advanced vs internal seam labels still align across `README.md`, `docs/public-api.md`, and `docs/capabilities.md`.
- Confirm `docs/public-api.md` matches the intended supported surface.
- Confirm `docs/capabilities.md` matches tested behavior.
- Confirm every newly promoted live fixture has a provenance entry in `docs/live-fixture-provenance.md` and that the listed source capture still exists under `tools/smoke/captures/`.
- Confirm `docs/replay-primitives.md` and `docs/correlation.md` do not imply a replay runtime.
- Confirm `CHANGELOG.md` is release-facing and does not overclaim completeness or stability.

## Tagging

- Choose the conservative next pre-`1.0.0` tag.
- Create the annotated git tag.
- Push the commit and tag.
- Publish the package/release notes through the maintainer’s normal channel.

## Post-tag sanity

- Verify CI passes on the release commit or release PR under the currently configured workflow triggers.
- If tag-triggered CI is later configured, verify it also passes on the tag.
- Verify Packagist or the chosen distribution channel sees the new tag.
- Capture any intentionally deferred items for the next pre-`1.0.0` planning pass.
