# Security Fixes — Plain-English Walkthrough

This document explains every change made during the security hardening of the
e-commerce + IDS project. Each entry is written so you can explain it out loud
in a presentation or report defense without needing to read the code.

---

## How the fixes are organized

Two new shared helper files were added so the same protection is applied
consistently everywhere, instead of being copy-pasted (and forgotten) per page:

- **`auth_check.php`** — one reusable "is this person a logged-in admin?" check.
- **`csrf.php`** — one reusable anti-forgery token system for forms.

The original vulnerabilities mostly came from *missing* a check on one page while
having it on another. Centralizing the checks is what prevents that class of bug.

---

## CRITICAL fixes

### 1. `reset_admin.php` — public admin password reset (now disabled)
**The problem:** Opening this file in a browser reset the admin password to
"1234" and printed it on screen. No login required. Anyone could take over the
admin account in one click.
**The fix:** The dangerous code was removed; the file now returns "Not found."
Password resets should be done by a developer in phpMyAdmin or a local script,
never through a public web page.

### 2. `process_checkout.php` — SQL injection
**The problem:** The order was saved by gluing user input straight into the SQL
query text. A crafted name or address could inject arbitrary SQL commands.
**The fix:** Rewritten to use *prepared statements* — the input is sent to the
database separately from the query, so it can never be treated as a command.
The IDS (`ids.php`) is now also included so this endpoint is monitored.

### 3. `update_orders.php` & `get_orders.php` — no authentication
**The problem:** Any anonymous visitor could change any order's status or read
the entire order list. Neither file checked who was logged in.
**The fix:** Both now call `require_admin_json()` at the top, so only a
logged-in admin can use them. `update_orders.php` also validates the status
against an allowlist.

### 4. `products.php` — add products with no authentication
**The problem:** The "add product" handler had no login check, so anyone could
add products and upload files to the server.
**The fix:** Added the admin check, CSRF protection, and safe file-upload
validation (see High #10). Also removed `display_errors`, which had been leaking
PHP internals.

### 5. `ids.php` — IDS completely bypassable via fake IP headers
**The problem:** The IDS decided "who you are" by trusting the
`X-Forwarded-For` / `CF-Connecting-IP` request headers. Those can be faked by
the attacker on every request, so they could change their apparent IP each time
and dodge every ban and rate limit. The IDS protected nothing.
**The fix:** Those headers are now only trusted if the request actually arrives
through a proxy we explicitly list as trusted. On a normal XAMPP setup (no
proxy) the IDS uses `REMOTE_ADDR` — the real connection IP that the attacker
cannot spoof.

---

## HIGH fixes

### 6. `test.php` — database structure exposed (now disabled)
**The problem:** This diagnostic page printed the database name, all table
names, and row counts to any visitor — a free map of the database.
**The fix:** Code removed; file now returns "Not found."

### 7. `checkout.js` — stored XSS via cart data
**The problem:** Cart item names and image paths were inserted into the page
using `innerHTML` string templates. If a product name contained HTML/JavaScript,
it would execute in the victim's browser.
**The fix:** The cart rows are now built with `createElement` and all text is
set via `textContent`, which the browser always renders as plain text and never
as code.

### 8. `load_products.js` — XSS via onclick injection
**The problem:** Product names were HTML-escaped but then placed inside an inline
`onclick="addToCart('...')"`. The browser decodes HTML entities before running
the JavaScript, so a name like `O'Brien` could break out of the string and inject
code.
**The fix:** Product cards are now built with `createElement`, text is set with
`textContent`, and the click handler is attached in JavaScript — the product data
is passed as real values, never embedded in a string of code.

### 9. `login.php` & `register.php` — session fixation
**The problem:** After login, the session ID was not changed. An attacker who
plants a known session ID in the victim's browser before they log in can then
ride that same session as the now-authenticated user.
**The fix:** `session_regenerate_id(true)` is called the moment login (or
registration) succeeds, so a fresh, unknown session ID is issued. `register.php`
also now calls `session_start()` at the very top of the file.

### 10. `add_product.php` & `products.php` — unsafe file upload
**The problem:** Uploads were validated only by `getimagesize()` (bypassable
with a file that has an image header but contains PHP code) — or in
`products.php`, not validated at all.
**The fix:** Uploads are now checked by their *real* MIME type (`finfo_file`),
limited to an allowlist of image types, capped at 5 MB, and saved under a
randomly generated filename — so an uploaded `evil.php` can never be executed.

---

## MEDIUM fixes

### 11. No CSRF protection (all forms)
**The problem:** Login, register, add-product, and unblock-IP forms had no
anti-forgery token, so a malicious page could silently submit them on behalf of
a logged-in admin.
**The fix:** Every form now includes a secret per-session token via
`csrf_field()`, and every POST handler verifies it with `csrf_verify()` before
acting.

### 12. `login.php` — user enumeration
**The problem:** Different error messages ("User not found" vs "Invalid
password") let an attacker discover which emails exist in the system.
**The fix:** A single generic message — "Invalid email or password." — is now
shown for every failed login.

### 13. `db_connect.php` — leaked database error details
**The problem:** On a connection failure the raw MySQL error was sent to the
browser, leaking server version and configuration.
**The fix:** The real error is now written to the server log via `error_log()`,
and the user only sees a generic "try again later" message.

### 14. `ids.php` — unescaped output in block pages
**The problem:** The IP address and block reason were printed into the HTML
warning pages without escaping.
**The fix:** All values printed into those pages now go through
`htmlspecialchars()` via a small `e()` helper.

### 15. `fetch_products.php` — open CORS
**The problem:** `Access-Control-Allow-Origin: *` allowed any website on the
internet to read the product API.
**The fix:** Restricted to `http://localhost` (change to your real domain when
you deploy).

---

## LOW / informational fixes

### 16. `login.php` — no lockout on repeated failed logins (noted)
A slow brute-force (one guess every few seconds) won't trip the rate limiter.
*Recommended next step:* track failed attempts per email/IP and lock out after
several failures. (Left as a recommendation — needs a new DB column.)

### 17. `register.php` — no password strength requirement
**The fix:** Registration now requires a password of at least 8 characters,
enforced on the server (client-side checks alone can be bypassed).

### 18. `admin_dashboard.js` — hardcoded localhost URLs
**The problem:** Fetch calls pointed at `http://localhost/project/...`, which
breaks on any other machine or folder name.
**The fix:** Replaced with relative paths (`../backend/php/...`).

### 19. `checkout.php` — no IDS coverage
**The fix:** Now includes `ids.php` and calculates the order total on the server
rather than trusting the client.

---

## Quick reference: which files changed

| File | What changed |
|------|--------------|
| `auth_check.php` | **NEW** — shared admin login guard |
| `csrf.php` | **NEW** — shared anti-forgery token helper |
| `reset_admin.php` | Disabled (was critical) |
| `test.php` | Disabled (was leaking DB structure) |
| `db_connect.php` | Stopped leaking error details |
| `ids.php` | Fixed IP-spoofing bypass + escaped output |
| `login.php` | Session fixation, user enumeration, CSRF |
| `register.php` | Session placement, CSRF, password rules |
| `process_checkout.php` | SQL injection fixed, IDS added |
| `update_orders.php` | Admin auth + status allowlist |
| `get_orders.php` | Admin auth |
| `products.php` | Auth, safe uploads, CSRF, removed display_errors |
| `add_product.php` | Auth ordering, safe uploads |
| `checkout.php` | IDS added, server-side total |
| `fetch_products.php` | Restricted CORS |
| `users.php` | Uses shared auth helper |
| `admin.php` | Uses shared auth helper, escaped output |
| `security_dashboard.php` | Shared auth helper + CSRF on unblock |
| `load_products.js` | XSS fix (createElement/textContent) |
| `checkout.js` | XSS fix (createElement/textContent) |
| `admin_dashboard.js` | Relative URLs instead of hardcoded localhost |

---

## One thing left for you to do

Because the admin password may currently be the weak "1234" from the old reset
script, **set a strong admin password** before showing this to anyone. You can
do that directly in phpMyAdmin: update the `password` column for your admin user
with a value produced by PHP's `password_hash()`.
