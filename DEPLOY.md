# Deployment Guide — Hour of Grace Ministries

## 1. Upload files to cPanel

Upload the full project to your domain root (e.g. `public_html/`):

- All HTML folders (`/`, `/about/`, `/contact/`, etc.)
- `/admin/`, `/api/`, `/includes/`
- `/assets/`, `/css/`, `/js/`, `/data/`
- `/uploads/` (empty folders with write permission)
- `/storage/` (writable for rate limits)
- `.htaccess`, `robots.txt`, `sitemap.xml`, `composer.json`, `composer.lock`

**Do not upload** `includes/config.php` from local dev — it will be created by the installer.

**PHP dependencies:** After uploading, run `composer install --no-dev` on the server (via cPanel Terminal or SSH), or upload your local `vendor/` folder via FTP/File Manager instead of using Git.

**Required graphics:** `/assets/` (logo, flyers) and gallery photos in `/assets/gallery/` — see section 4.

## 2. Set folder permissions

```text
uploads/          755 (775 if uploads fail)
uploads/gallery/  755
uploads/hero/     755
uploads/documents/755
storage/          755
storage/rate-limits/ 755
```

## 3. PHP version & extensions

This site requires **PHP 8.0 or newer** (PHP 8.1+ recommended) with these extensions enabled:

| Extension | Purpose |
|-----------|---------|
| **PDO** | Database connection |
| **pdo_mysql** | MySQL driver |
| **mbstring** | Form text handling |

In cPanel:

1. **MultiPHP Manager** — set your domain to PHP 8.1 or 8.2
2. **Select PHP Version** (or **PHP Extensions**) — tick **PDO**, **pdo_mysql**, and **mbstring**, then Save

If you see `Class "PDO" not found`, the PDO extension is not enabled for your domain's PHP version.

Upload `health.php` temporarily to confirm extensions, then delete it.

## 4. Gallery images (important)

The gallery, hero slider, and several homepage photos use files in **`assets/gallery/`**, not the old WordPress `/wp-content/` URLs.

After uploading the site:

1. **Upload `/assets/`** — contains `logo.png`, bible school flyers, recruitment image, and the `gallery/` folder.
2. **Restore gallery photos** using one of these options:
   - **Option A (easiest):** Visit **`/sync-gallery.php` once** on the live server. It searches your cPanel home folder for old WordPress uploads, copies them into `assets/gallery/`, and fixes database paths. Delete `sync-gallery.php` afterward.
   - **Option B:** Upload the image files directly into `assets/gallery/` via File Manager (filenames must match `data/gallery.json`).
   - **Option C:** Upload photos through **Admin → Gallery** after logging in (saved to `uploads/gallery/`).

If the logo or flyers are missing, confirm the entire **`assets/`** folder was uploaded to the domain root.

## 5. Run the installer

1. Visit `https://hourofgraceministries.org/install.php`
2. **Step 1:** Enter database credentials, site URL, and SMTP settings → Save & test
3. **Step 2:** Create admin username, email, and password → Complete installation
4. **Delete `install.php`** from the server after a successful install

## 6. Admin dashboard

Sign in at `/admin/login.php`

| Section | Purpose |
|---------|---------|
| Dashboard | Overview stats and recent submissions |
| Submissions | View, reply, mark read/unread, delete form entries |
| Mailing List | Add, deactivate, delete subscribers |
| Gallery | Upload, reorder, edit captions, show/hide photos |
| Hero Slider | Manage homepage hero slides separately |
| Online Giving | Stripe/PayPal keys, test connections, view/delete gifts |
| Email Settings | SMTP config, test connection, send test email |
| Account Settings | Change password, set admin email for reset |

### Forgot password

1. Set your admin email in **Account settings**
2. Use **Forgot password** on the login page
3. Reset link is sent via SMTP (must be configured first)

## 7. Payment setup

### Stripe
1. Admin → Online Giving → enable Stripe
2. Add publishable key, secret key, webhook secret
3. In Stripe Dashboard, add webhook: `https://yourdomain.com/api/giving-stripe-webhook.php`
4. Event: `checkout.session.completed`
5. Click **Test Stripe** in admin

### PayPal
1. Create REST app at developer.paypal.com
2. Add Client ID and Secret in admin
3. Use Sandbox mode for testing
4. Click **Test PayPal** in admin

## 8. SMTP (form emails)

Default host: `mail.hourofgraceministries.org` · Port: **465** (SSL)

1. Admin → Email Settings → save credentials
2. Click **Test SMTP connection**
3. Click **Send test email**
4. Submit a contact form on the live site to confirm notifications

## 9. Database migrations (existing installs)

If upgrading an older install, migrations run automatically when you sign in to admin. You can also import manually in phpMyAdmin:

- `sql/migrate-admin-v2.sql` — hero slides + password reset
- `sql/migrate-giving.sql` — if giving table missing
- `sql/migrate-newsletter.sql` — if newsletter table missing

## 10. Security checklist

- [ ] Delete `install.php` after setup
- [ ] Use a strong admin password (8+ characters)
- [ ] Set admin email for password recovery
- [ ] Keep `includes/config.php` out of public access (blocked by `.htaccess`)
- [ ] Configure Stripe webhook signing secret
- [ ] Use HTTPS on production

## 11. Local development note

Live Server (port 5500) serves static HTML only. PHP forms, admin, and payments require the cPanel/PHP server environment.
