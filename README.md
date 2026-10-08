# Aini Wear — Production Manager

A small, desktop-first PHP/MySQL app for garment orders. No frontend build, CDN, framework or internet connection is needed for ordinary work. Optional AI uses an eligible ChatGPT subscription or a separately billed OpenAI API key.

## Installing from GitHub

After cloning, run `composer install --no-dev --prefer-dist` using PHP 8.3 or newer, then follow the WAMP installation steps below. Dependencies are pinned in composer.lock but vendor files are not committed. config.php, local php.ini, API keys, passwords and uploaded product/customer images are excluded from this repository.

The included public/.user.ini sets upload limits for PHP CGI/FastCGI. If WAMP defaults to PHP 8.2, select PHP 8.3 for this app through a WAMP virtual host, or add the following to public/.htaccess using your actual PHP installation path:

```apache
Options +ExecCGI
AddHandler fcgid-script .php
FcgidWrapper "C:/wamp64/bin/php/php8.3.6/php-cgi.exe" .php
```

For immediate app-specific PHP settings, copy the selected PHP version's php.ini to the project root, adjust its upload limits, and append `-c C:/wamp64/www/aini-wear/php.ini` inside the FcgidWrapper command. Local php.ini stays outside version control. Keep the app's parent deny rules and verify private paths return 403.

## What is included

- Customer directory; searchable orders; completion/shipping dates, priority, destination and tracking reference.
- Multiple items per order: quantity, size breakdown, fabric/GSM, colors, requirements and multiple reference images.
- Item components: embroidery, rubber, woven, silicone, buttons, labels, logos and custom types, with supplier, quantity, deadline and independent status.
- Configurable step templates and custom item workflows, position/order, assignee, start/due dates, blockers, completion timestamps and progress counts.
- Keyword-based automatic production suggestions, editable before adding; optional AI sequence suggestions.
- Notes, follow-ups, monthly calendar, agenda and overview dashboard.
- Manual shipping rates, atomic CSV/XLS/XLSX imports, active/expired rate filtering and cost/speed comparisons.
- Optional AI drafts: writing, organizing, extracting pasted text, buyer follow-ups and next actions. Reviewed drafts can become notes or production steps.
- Password login, prepared queries, CSRF forms, escaped output, private image storage and MIME/size checks.

## WAMP installation — Windows 11

1. Install 64-bit [Wampserver](https://wampserver.aviatechno.net/) and its listed Visual C++ prerequisites. Start WAMP; wait for the tray icon to turn green. Select **PHP 8.3 or newer** and **MySQL 8.0 or newer**. Tested with PHP 8.3.6 / MySQL 8.3.0. Do not import into an existing unrelated database.
2. Extract the ZIP so the project folder is `C:\wamp64\www\aini-wear`. Its `public`, `app`, `database`, `vendor` and `storage` folders should be directly inside that folder.
3. Enable these PHP extensions in WAMP: `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `zip`, `xml`, `dom`, `xmlreader`, `xmlwriter`, `simplexml`, `iconv`, `ctype`, `filter`, `curl`, `openssl`. Restart WAMP. Use PHP 8.3+ in the terminal too. Set `upload_max_filesize=20M`, `post_max_size=24M`, `memory_limit=512M`, `max_execution_time=180` in the Apache PHP configuration; restart services. Imports allow up to 20 MB / 50,000 data rows (CSV, XLS and XLSX).
4. Open `http://localhost/phpmyadmin`. As a database administrator, import `database/schema.sql`, then `database/seed.sql` into the new `aini_wear` database. The seed contains required default step/component types plus one clearly labeled demo order and fictional shipping rates. Run the seed only once, on the fresh schema.
5. In phpMyAdmin's SQL tab, create a dedicated database login. Replace the example password with your own:

   ```sql
   CREATE USER 'aini_app'@'127.0.0.1' IDENTIFIED BY 'REPLACE_WITH_A_STRONG_PASSWORD';
   GRANT SELECT, INSERT, UPDATE, DELETE ON aini_wear.* TO 'aini_app'@'127.0.0.1';
   ```

6. Copy `config.example.php` to `config.php`. Enter the database host/port/name/user/password. MySQL usually uses port 3306; check WAMP's actual port if you have both MySQL and MariaDB. The app uses Asia/Karachi dates. No password or API key is included in this download.
7. In PowerShell, create the first app login (replace the PHP folder with your selected version):

   ```powershell
   Set-Location C:\wamp64\www\aini-wear
   & 'C:\wamp64\bin\php\php8.3.6\php.exe' bin/create-user.php
   ```

   Enter a display name, username and unique password of at least 12 characters. This terminal prompt displays the password; use a private terminal. Repeat for additional staff. All staff have the same permissions in this MVP.
8. Open **http://localhost/aini-wear/public/** and sign in. Apache must allow `.htaccess` (`AllowOverride All`) for the parent deny/public allow rules. Verify `/aini-wear/config.php`, `/aini-wear/database/schema.sql`, `/aini-wear/storage/` and `/aini-wear/vendor/` return **403**, and the public login works. Do not remove the deny rules to fix a setup problem.

### Preferred: a WAMP virtual host

Point a local virtual host such as `aini-wear.local` at **`C:/wamp64/www/aini-wear/public`**, using WAMP's Add a Virtual Host page/menu. The document root must be the `public` folder. Restart DNS/services as WAMP instructs. The source, SQL, config, tests and images then sit outside the web root. A manual Apache equivalent:

```apache
<VirtualHost *:80>
    ServerName aini-wear.local
    DocumentRoot "C:/wamp64/www/aini-wear/public"
    <Directory "C:/wamp64/www/aini-wear/public">
        AllowOverride All
        Require local
        Options -Indexes
    </Directory>
</VirtualHost>
```

Add `127.0.0.1 aini-wear.local` to the Windows hosts file if WAMP has not done so. No rewrite rules are needed. For local development only, `php -S 127.0.0.1:8080 -t public` also works. Never serve the project root with PHP's built-in server: it ignores `.htaccess`.

## Daily use

1. Add a customer, create an order, then add each product as a separate item.
2. Enter size quantities in plain text (`S:20, M:30...`), detailed requirements and product reference images. Size totals are not automatically reconciled in this MVP.
3. Add components, their due dates and makers. Components and production steps each use pending / in progress / blocked / done.
4. Review built-in suggestions and click **Add reviewed steps**, or add custom steps. Edit position numbers to reorder. Suggested additions preserve already existing names and progress; they do not replace a saved workflow. Template changes affect future suggestions only. Renamed default templates may need manual selection because the built-in rules use the original default names.
5. Set deadlines and assignees; add follow-ups to the order. Review the dashboard and Schedule. The checklist permits independent work and does not enforce dependencies or client approval gates. The order status is set manually, separately from the computed item progress.
6. Record courier/tracking details on the order when shipped.

## Shipping imports and recommendations

Use `public/samples/shipping-rates.csv` (also downloadable on the Shipping page). XLS/XLSX use the first sheet and the identical nine headers, in this exact order:

```text
courier,service,destination,currency,min_kg,max_kg,rate,transit_days,valid_until
```

Use decimal numbers, not currency symbols or thousands separators. `transit_days` is a positive whole number. `valid_until` is optional but its header is required; use YYYY-MM-DD **text cells** for Excel dates. Formulas are not evaluated. Entire imports validate before a transaction saves them; a bad row saves nothing. Exact matching courier/service/destination/currency/weight slabs update the old rate and become active. Duplicate slabs inside one file are rejected. Other saved rates are unchanged. Disable obsolete rates.

Slabs are **minimum exclusive / maximum inclusive**: a 5 kg shipment matches `(0,5]`, not `(5,10]`. Rate is the **flat total** for that slab, not a per-kg amount. Use chargeable weight supplied by the courier, including dimensional weight when relevant. Destination is an exact country/zone string (case insensitive under the database collation). Recommendations use active, unexpired matching rates in one currency; there is no currency conversion. Optional maximum days is a hard filter.

- Cheapest: sort by normalized cost (with shorter transit as a tie breaker).
- Fastest: sort by normalized transit (with lower price as a tie breaker).
- Balanced: minimize `0.5 × normalized cost + 0.5 × normalized transit`; each dimension ranges from 0 to 1 within the matching set. If all rates have the same value, that dimension contributes zero. Adding alternatives can change the balanced ranking.

These are estimates from uploaded rates, not live courier quotes or bookings. Taxes, fuel/remote-area surcharges, pickup, holidays, customs and dispatch date are not calculated. Seed/template rates are fictional examples.

## Optional AI

**ChatGPT subscription:** On the WAMP PC, open **Settings → AI setup → Continue with ChatGPT**. Complete OpenAI sign-in and authorize ChatGPT plan usage. Return to Aini Wear, refresh, select **ChatGPT subscription**, load available models if needed, and save. The official open-source OAuth flow uses a `127.0.0.1` callback, PKCE, signed ID-token validation, private per-staff tokens, and serialized token renewal. The callback is `http://127.0.0.1:<Apache port>/<app public path>/chatgpt.php`; keep this path unchanged for returning sign-ins. Eligible plans and limits are controlled by OpenAI. Keep credit spending disabled in ChatGPT's app usage settings if you want subscription-only usage. See [official plan integration](https://developers.openai.com/siwc/token-sharing-open-source).

**Optional paid API:** Select OpenAI API in Settings and enter an API key and available Responses model. This route requires separate API credits. The app never automatically falls back from the subscription to the API. Both routes use HTTPS and `store=false`; subscription requests also use streaming and omit unsupported output-limit fields.

Saved service/model settings override config.php and live in private `storage/ai-settings.json`, excluded from Git. All logged-in staff can change the service/model in this MVP. Keys are never redisplayed; leave the key field blank to keep the saved key. Each staff login has its own ChatGPT connection in private `storage/chatgpt`, also excluded from Git. Protect both locations in backups. Manage or revoke access at https://chatgpt.com/settings/usage. This integration does not import existing Codex credentials or browser cookies.

For an image containing instructions, select **Read and follow sheet instructions**, attach the image, and generate a draft. Requests have a 45-second provider timeout, a 55-second browser wait limit, an elapsed-time indicator and Cancel wait. No automatic retries occur. Errors restore the Generate button and preserve the selected file. Cancellation stops the browser wait; a request already sent to the provider may still finish and incur usage, but it never applies changes to an order. Existing text tasks remain available.

Open **Help with this order/item** to include its context. Before a call, the interface says that pasted text and linked records will be sent to the provider. Only explicitly selected PDF/JPG/PNG files are sent along with the linked context. Extract can read an uploaded order/product sheet with a vision-capable model, including scanned pages; check uncertain or unreadable details. Other stored files and item images are not sent. Direct assistant uploads are temporary; order-form uploads are saved privately. AI output is plain text, escaped in the UI. No output executes code, sends messages or modifies records automatically. Review and edit before saving a note or sequence. API costs/billing are separate from the app. Without AI, all normal pages and keyword-based workflow suggestions continue to work.

## Structure

```text
public/index.php             Front controller, login guard, private image delivery
public/assets/               Offline CSS and small JS helpers
app/bootstrap.php            Database/session/security initialization
app/functions.php            Shared validation, forms, upload and suggestion helpers
app/actions.php              Authenticated POST actions
app/shipping.php             Excel/CSV import, validation, ranking
app/ai.php                   Optional Responses API adapter
app/pages/                   Small page modules
database/schema.sql          MySQL tables and indexes
database/seed.sql            Default types/templates and demo data
bin/create-user.php          Local CLI staff creation
storage/images/              Private product images
vendor/, composer.lock       Bundled and pinned Excel dependencies
tests/smoke.php              Disposable integration test
```

PhpSpreadsheet supplies XLS/XLSX reading using explicit reader types and row/column limits; see its [reading documentation](https://phpspreadsheet.readthedocs.io/en/latest/topics/reading-files/). Vendor licenses are included. Composer is not required for first installation of this ZIP. To restore/update dependencies, use PHP 8.3+ and Composer from the project root: `composer install --no-dev --prefer-dist`. Review and apply security updates over time.

## Checks, backups and boundaries

See `TEST-REPORT.md` for the checks performed on this package. To rerun integration checks, use a disposable copy with **no config.php**, a local MySQL administrator account supplied through `TEST_DB_USER`, `TEST_DB_PASSWORD`, `TEST_DB_PORT` environment variables, then `php tests/smoke.php`. The test creates and drops only a random `aini_test_...` database, starts a localhost PHP server, writes/removes a temporary config.php, and removes its generated images. Do not run it in a working installation.

Back up the database via phpMyAdmin Export, `storage/images`, `storage/documents`, and your private config.php together. Restore into a matching schema and preserve filenames. There is no backup scheduler, email integration, real-time courier API, accounting, inventory reservation, offline OCR, background job runner, permission roles or audit history in this MVP. All staff can edit all records. Keep WAMP local or on a trusted private network; an internet deployment needs HTTPS, stronger shared login throttling, user/role administration, operational monitoring and a security review.

Common setup issues: wrong MySQL/MariaDB port; PHP CLI version differs from Apache; disabled extensions; storage permissions; POST limit too small; missing vendor folder; `.htaccess` not honored. If AI has a TLS error, download the current CA bundle from https://curl.se/docs/caextract.html, verify its published SHA-256 checksum, and save it as `storage/certs/cacert.pem`. The assistant automatically uses that bundle with certificate verification enabled. Set `curl.cainfo` and `openssl.cafile` to its absolute path in the app's active PHP configuration for other PHP HTTPS requests. Refresh the bundle periodically; never disable certificate verification.

## PDF and image order sheets

New and existing order forms accept PDF/JPG/PNG sheets up to 5 MB each. Saved sheets can be downloaded by signed-in staff and selected for AI extraction. Direct AI uploads are temporary and are not saved with the order. AI never fills or changes records automatically: review the extracted draft and copy it into the fields or save it as a note. Use a model with PDF/image input support. Unreadable details are marked as uncertain.

For an existing installation, import `database/migrations/002_order_files.sql` into its configured database using a database administrator, and create writable `storage/documents`. New installations include this table in schema.sql. File inputs follow [OpenAI's official file-input guide](https://developers.openai.com/api/docs/guides/file-inputs).


Shipping per-kg pricing

New installations include `shipping_rates.rate_basis`. Existing installations must run `database/shipping-per-kg.sql` once before deploying this version. WAMP was migrated in place with existing prices kept as flat totals.

The original nine import headers remain supported. Add an optional tenth header, `rate_basis`, with `flat` or `per_kg`. A per-kg quote multiplies the entered chargeable weight by the unit rate, without rounding weight. Per-kg ranges include both bounds; flat slabs exclude the lower bound. Compare prices using shipment totals, not unit rates. Courier, supplier/service, route, duty status, category and postal zone must have distinct service/destination labels when their tariffs differ. Incremental and conditional surcharges are separate from total or per-kg prices.

Run `php tests/shipping-per-kg.php` to verify legacy defaults, per-kg totals, fractional weights, invalid pricing methods and total-price ranking.
