# Verification — 8 October 2026

Environment: Windows, PHP 8.3.6, MySQL 8.3.0, PhpSpreadsheet 5.10.0. HTTP integration tests used PHP's localhost development server and a newly created temporary database. The test database, temporary configuration and uploaded test image were removed afterward.

**42 integration checks passed.** All application PHP files passed syntax checks.

Verified:

- Login, logout, authentication guard and CSRF rejection.
- Dashboard and every page render with seeded data; order/item pages load their records.
- Customer creation and HTML escaping; order creation/update; item creation and fractional-quantity rejection.
- Independent component preparation status; workflow application; completion timestamps; repeated suggestions preserve existing status.
- Notes/follow-ups, follow-up completion, calendar deadline rendering and offline workflow suggestions.
- Valid product image upload and authenticated image serving; fake executable-image rejection; image access after logout does not return image bytes.
- CSV, XLSX and legacy XLS imports; matching slabs updated; invalid-rate import saved no rows.
- Cheapest/fastest ranking, exact 5 kg slab boundary, currency isolation.
- Missing AI configuration handled with a clear message.
- No PHP warnings, fatal errors or SQL errors in the final HTTP test run's server log.

Not verified here: live OpenAI calls (no key configured), a browser visual/accessibility audit, Apache virtual-host configuration on the destination PC, other PHP/MySQL versions, courier quote accuracy or Internet deployment. The responsive CSS is supplied, but visual appearance has not been tested in a browser. The README explains these setup and MVP boundaries.

Run `php tests/smoke.php` in a disposable copy without config.php to reproduce the integration checks. See README for database credentials and safety details.
