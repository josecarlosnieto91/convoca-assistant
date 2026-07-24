# Convoca Assistant — RELEASE Checklist

## Before release

- [ ] Run `composer test` (PHPCS + PHPStan + PHPUnit)
- [ ] Run `npm test` in `tests/` (Jest JS tests)
- [ ] Verify index regeneration works (manual test in admin)
- [ ] Verify widget renders correctly (floating + shortcode)
- [ ] Verify search returns results
- [ ] Verify REST API endpoints respond
- [ ] Verify admin pages load without errors
- [ ] Verify CPTs (FAQ, KB) are accessible
- [ ] Verify synonyms and stop words save correctly
- [ ] Verify export/import works (knowledge + settings)
- [ ] Verify mobile responsive (480px breakpoint)
- [ ] Verify dark mode (prefers-color-scheme)
- [ ] Verify keyboard navigation (Tab, Escape, Enter)

## Version bump

- [ ] Update `convoca-assistant.php`: version, tested up to
- [ ] Update `readme.txt`: stable tag, tested up to
- [ ] Update `composer.json`: version (if applicable)
- [ ] Update `docs/CHANGELOG.md`

## Build

- [ ] Run `composer install --no-dev` to strip dev deps
- [ ] Regenerate Fuse.js bundle if version changed
- [ ] Compile .pot file: `wp i18n make-pot . languages/convoca-assistant.pot`
- [ ] Verify no dev files in distribution (tests/, dev-tools/)

## Git

- [ ] Create release branch: `release/v0.1.0`
- [ ] Commit all changes
- [ ] Create GitHub release with tag
- [ ] Attach plugin ZIP
