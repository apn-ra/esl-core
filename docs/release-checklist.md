# Release Checklist

Use this checklist when cutting the next pre-`1.0.0` release of `apntalk/esl-core`.

## Pre-flight

- Confirm the working tree is clean or intentionally scoped.
- Resolve or explicitly exclude unrelated local changes before tagging.
- Review `CHANGELOG.md` and the draft release notes for the intended version.
- Confirm deferred items remain out of scope for the release.

## Verification

- Run `composer smoke`
- Run `composer validate --strict`
- Run `composer check`
- Run `./vendor/bin/phpstan analyse --no-progress`
- Run `./vendor/bin/phpunit --no-coverage`
- If you need a standalone style check outside `composer check`, run `./vendor/bin/php-cs-fixer fix --dry-run --diff --sequential`

## Release-facing review

- Confirm `README.md` still states clearly what the package is and is not.
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

- Verify CI passes on the tagged commit.
- Verify Packagist or the chosen distribution channel sees the new tag.
- Capture any intentionally deferred items for the next pre-`1.0.0` planning pass.
