# Verification — 8 October 2026

Environment: Windows, PHP 8.3.6, MySQL 8.3.0, PhpSpreadsheet 5.10.0. HTTP integration tests used PHP's localhost development server and a newly created temporary database. The test database, temporary configuration and uploaded test image were removed afterward.

**64 integration checks and 7 AI UI regression checks passed.** All changed application PHP files passed syntax checks.

ChatGPT subscription integration: 14 offline checks cover signed identity acceptance, issuer/audience/nonce/expiry/subject validation, forged signature rejection, unsafe state paths, completed SSE streams, failure/incomplete events, and interrupted streams. Run `php tests/chatgpt.php`. Composer uses firebase/php-jwt 7.2.1 with no reported security advisories. End-to-end subscription inference requires the user's browser sign-in and plan consent and remains unverified until that is completed.

Live WAMP checks confirm authenticated sign-in routes through the loopback handoff with PKCE and the plan scope, invalid callbacks return HTTP 400, normal app pages still render, and private files remain blocked. All 64 integration checks and 7 interface checks were rerun after the subscription change.

Six sign-in interface checks (`node tests/chatgpt-ui.js`) cover the visible browser link, duplicate clicks, network errors, timeout, unexpected HTML, and unsafe returned addresses. Sign-in uses JSON preparation followed by a visible user-clicked link to avoid silent in-app browser redirect blocking.

Subscription streamed-text regression: 16 security/stream checks now include terminal events without an output array and terminal text taking precedence over accumulated deltas. Live deployed image extraction succeeded with a synthetic requirements PNG and the user's authorized subscription; no customer data or paid API fallback was used. Completed events can omit output, so the parser retains text deltas and done events and still rejects incomplete or interrupted streams.

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
- Missing AI setup returns an immediate JSON error for image requests; expired forms return JSON errors; AI settings save privately without displaying the key. No live key was used in tests.
- AI UI recovers after configuration errors, network errors, unexpected HTML, timeout and cancellation. Duplicate clicks send a single request; success navigates to the reviewed draft. Run `node tests/ai-ui.js` from the project root.
- Regression tests reproduce a hidden `name="action"` input masking the DOM form.action property, and verify every AI upload goes to the page URL instead of `/[object HTMLInputElement]`.
- PDF/JPG/PNG order-sheet uploads and authenticated downloads; attachments saved through order data entry; fake PDFs rejected before an order can be partially saved.
- Saved sheet selector and direct-upload controls in the AI assistant; image and PDF input payloads match their respective API types; saved sheets cannot be selected from a different order; downloads require login.
- No PHP warnings, fatal errors or SQL errors in the final HTTP test run's server log.

Not verified here: live OpenAI calls (no key configured), a browser visual/accessibility audit, Apache virtual-host configuration on the destination PC, other PHP/MySQL versions, courier quote accuracy or Internet deployment. The responsive CSS is supplied, but visual appearance has not been tested in a browser. The README explains these setup and MVP boundaries.

Run `php tests/smoke.php` in a disposable copy without config.php to reproduce the integration checks. See README for database credentials and safety details.
