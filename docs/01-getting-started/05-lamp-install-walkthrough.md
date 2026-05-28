---
title: LAMP / LEMP Install Walkthrough
category: Getting Started
order: 5
status: published
audience: [admin]
last-updated: 2026-05-28
related:
  - 01-getting-started/02-three-ways-to-run-it.md
  - 01-getting-started/04-docker-install-walkthrough.md
since: 7.3.0
---

# LAMP / LEMP Install Walkthrough

A complete, copy-pasteable walkthrough for installing **standalone**
Bizuno directly on a Linux + Apache (or Nginx) + MySQL/MariaDB + PHP
host. No Docker, no WordPress — just the core library served by your
own web server. This is the right path when:

- You have a **VPS, dedicated server, or shared-hosting account** and
  want Bizuno running on its own (sub)domain.
- You're comfortable creating a database and pointing a vhost at a
  directory, or your host gives you cPanel/Plesk to do it.
- You **don't** also run WordPress on the same site (if you do, the
  [WordPress plugin path](./06-wordpress-install-walkthrough.md) is
  less work — single login, shared DB).

End state: `https://your.hostname/` serves Bizuno, PHP-FPM behind
Apache or Nginx, schema created in MySQL/MariaDB, TLS terminated by
the web server. Roughly 20 minutes on a fresh box.

> **Note on the installer:** you do **not** hand-edit a config file in
> this path. You create an empty database + user, then the browser-based
> installer writes `portalCFG.php` for you — generating a unique
> `BIZUNO_KEY` and `BIZUNO_BIZID` and storing your DB credentials. Skip
> ahead to [Phase 5](#phase-5--run-the-browser-installer) to see it.

## What you'll need before you start

1. **A Linux host** with shell access — Debian/Ubuntu used below;
   RHEL/Rocky/Alma work the same with `dnf` instead of `apt`. Shared
   hosting with cPanel/Plesk works too — skip the package-install
   phases and use the panel's UI for the vhost + database.
2. **PHP 8.2+** (8.0 is the hard floor the app enforces, but Composer
   resolves dependencies against 8.2). PHP-FPM recommended.
3. **MySQL 5.6+ / MariaDB 10.2+**.
4. **Composer** — or use a pre-bundled release zip (see Phase 3B).
5. **A hostname** with DNS pointed at the server, and a **TLS cert**
   (Let's Encrypt via certbot is fine).

## Phase 1 — Install the stack

### 1.1 Apache + PHP-FPM (the "LAMP" path)

```bash
sudo apt update
sudo apt install -y apache2 \
    php php-fpm php-mysql php-mbstring php-curl php-zip php-gd \
    php-xml php-bcmath \
    mariadb-server unzip git curl
```

The PHP extensions above match what Bizuno's pre-flight check
requires — `mbstring`, `pdo_mysql`, `json`, `curl`, `openssl`,
`zip`, `gd`. (`json` and `openssl` are compiled into modern PHP, so
they aren't separate packages.) If any are missing the installer
shows a friendly "setup required" page listing exactly which.

Enable the Apache modules Bizuno's `.htaccess` and PHP-FPM need:

```bash
sudo a2enmod rewrite proxy_fcgi setenvif
sudo a2enconf php8.2-fpm        # adjust version to your PHP
sudo systemctl restart apache2
```

> **Nginx (LEMP) instead?** Install `nginx` + `php-fpm` rather than
> `apache2`, and translate the `.htaccess` HTTPS redirect into a
> server block (see [the Nginx note](#appendix-nginx-server-block)
> at the end). Everything else is identical.

### 1.2 Secure MariaDB

```bash
sudo mysql_secure_installation
```

Set a root password, remove anonymous users and the test database,
disallow remote root. Defaults are fine for the rest.

## Phase 2 — Create the database

Bizuno needs an **empty** database and a user with full rights on it.
The installer creates all tables; you just provision the shell.

```bash
sudo mysql
```

```sql
CREATE DATABASE bizuno CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'bizuno'@'localhost' IDENTIFIED BY 'use-a-strong-password-here';
GRANT ALL PRIVILEGES ON bizuno.* TO 'bizuno'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Keep the database name, user, and password handy — you'll type them
into the installer in Phase 5.

> `utf8mb4` matters: Bizuno stores user-entered names, notes, and
> product descriptions that routinely include emoji and non-Latin
> characters. The older `utf8` (3-byte) charset truncates them.

## Phase 3 — Get the Bizuno code + dependencies

Pick **one** of A or B.

### 3A. Composer (recommended — always current)

```bash
cd /var/www
sudo git clone https://github.com/phreesoft/bizuno.git
cd bizuno
sudo composer install --no-dev --optimize-autoloader
```

`composer install` fetches `vendor/` (PHPMailer, phpseclib, FPDI/tFPDF,
picqer/php-barcode-generator, webauthn-lib, authorizenet). The
`--no-dev` flag skips the docs-only Parsedown dependency.

> **Don't have Composer?** `curl -sS https://getcomposer.org/installer | php`
> then `sudo mv composer.phar /usr/local/bin/composer`. Or use 3B.

### 3B. Pre-built release zip (no Composer)

Download a release from
[github.com/phreesoft/bizuno/releases](https://github.com/phreesoft/bizuno/releases) —
these bundle `vendor/` already — and unzip into `/var/www/bizuno`.

### 3.1 Ownership + permissions

Apache/PHP-FPM needs to read the tree, write `portalCFG.php` once
during install, and read/write the data directory:

```bash
sudo chown -R www-data:www-data /var/www/bizuno
sudo find /var/www/bizuno -type d -exec chmod 755 {} \;
sudo find /var/www/bizuno -type f -exec chmod 644 {} \;
```

> The installer writes `portalCFG.php` into the web root on first run,
> so that directory must be writable by the PHP user **at install
> time**. After install you can tighten it back to `644` /
> non-writable — the file is only rewritten on re-install.

## Phase 4 — Web server vhost + TLS

### 4.1 Apache vhost

```apache
# /etc/apache2/sites-available/bizuno.conf
<VirtualHost *:80>
    ServerName bizuno.your-domain.com
    DocumentRoot /var/www/bizuno

    <Directory /var/www/bizuno>
        AllowOverride All        # required — Bizuno ships an .htaccess
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/bizuno-error.log
    CustomLog ${APACHE_LOG_DIR}/bizuno-access.log combined
</VirtualHost>
```

```bash
sudo a2ensite bizuno
sudo apache2ctl configtest && sudo systemctl reload apache2
```

`AllowOverride All` is required so Bizuno's shipped `.htaccess`
(the HTTPS-redirect rules) is honored.

### 4.2 TLS via Let's Encrypt

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d bizuno.your-domain.com
```

Certbot rewrites the vhost to add the `:443` block and an
http→https redirect. Combined with Bizuno's own `.htaccess`
redirect, every request lands on HTTPS.

## Phase 5 — Run the browser installer

Open `https://bizuno.your-domain.com/` in a browser.

Because no `portalCFG.php` exists yet, Bizuno boots from
`portalCFG-sample.php`, detects it can't connect to a real database,
and serves the **install wizard**:

1. **Database** — name, user, password (the ones you created in
   Phase 2). The wizard tests the connection before proceeding.
2. **Admin user** — email + password. This becomes your first login
   (role: admin).
3. **Business title** and **base currency**.
4. **Click Install.**

On submit, Bizuno:

- Tests the DB credentials.
- Writes `portalCFG.php` to the web root, generating a **unique
  `BIZUNO_KEY`** (PII/password encryption) and **`BIZUNO_BIZID`**, and
  recording your DB creds. The shipped default key is never used.
- Creates the full schema in your database.
- Creates the admin contact + login and logs you in.
- Reloads to the Bizuno dashboard.

You're now running standalone Bizuno.

> **Back up `BIZUNO_KEY` immediately.** Open the freshly written
> `/var/www/bizuno/portalCFG.php` and copy the `BIZUNO_KEY` value into
> a password manager. It encrypts stored PII — lose it and encrypted
> data can't be recovered.

### 5.1 Lock the data directory out of the webroot (recommended)

By default `BIZUNO_DATA` is `DOCUMENT_ROOT/data/` — uploads, cache, and
backups sit under the web root. For a hardened install, move it (and
optionally the PHP source) outside any directory Apache serves, then
point the path constants at the new location in `portalCFG.php`:

```php
define('BIZUNO_DATA', '/var/www/bizuno-private/data/');
```

See the path-constant comments at the top of `portalCFG-sample.php`
(`BIZUNO_FS_LIBRARY` / `BIZUNO_FS_ASSETS` / `BIZUNO_DATA`) for the full
out-of-webroot pattern.

## Common gotchas

| Symptom | Cause | Fix |
|---|---|---|
| "Setup required" page listing missing PHP extensions | A required ext isn't loaded | `sudo apt install php-mbstring php-mysql php-curl php-zip php-gd && sudo systemctl restart php8.2-fpm apache2` |
| "Dependencies not installed" page | `vendor/` is missing — you skipped Composer | Run `composer install --no-dev` in the web root, or use a pre-bundled release zip (Phase 3B) |
| Installer errors: config file not writable | PHP user can't write `portalCFG.php` | `sudo chown www-data:www-data /var/www/bizuno` and ensure the dir is writable during install |
| "Invalid DB credentials" in the wizard | DB user lacks rights, or wrong host | Re-check the `GRANT`; host is `localhost` for a same-box MariaDB |
| `.htaccess` rules ignored, no HTTPS redirect | `AllowOverride` not set | Set `AllowOverride All` in the `<Directory>` block and reload Apache |
| Infinite http↔https redirect loop behind a proxy/LB | Apache sees `HTTPS=off` from the proxy | Bizuno's `.htaccess` already checks `X-Forwarded-Proto`; make sure your proxy sets it to `https` |
| Blank white page, 500 error | PHP-FPM not wired to Apache, or a fatal in the error log | `sudo a2enconf php8.2-fpm`; check `/var/log/apache2/bizuno-error.log` |

## Day-2: backups + updates

### Back up the three things that matter

```bash
# 1. The database
mysqldump -u bizuno -p bizuno | gzip > bizuno-db-$(date +%F).sql.gz

# 2. The data directory (uploads, attachments, biz-instance-key.php)
tar -czf bizuno-data-$(date +%F).tar.gz -C /var/www/bizuno data

# 3. portalCFG.php (contains BIZUNO_KEY — guard it)
cp /var/www/bizuno/portalCFG.php ~/bizuno-portalCFG-$(date +%F).php
```

`biz-instance-key.php` inside the data directory signs session
cookies; `BIZUNO_KEY` in `portalCFG.php` encrypts PII. Back up both.

### Update Bizuno

```bash
cd /var/www/bizuno
sudo -u www-data git pull
sudo -u www-data composer install --no-dev --optimize-autoloader
```

If the new version changes the schema, Bizuno runs migrations
automatically on the next request. `portalCFG.php` is left untouched.

## Appendix: Nginx server block

For LEMP, replace the `.htaccess` HTTPS redirect with a server block
and pass PHP to FPM:

```nginx
server {
    listen 80;
    server_name bizuno.your-domain.com;
    return 301 https://$host$request_uri;
}
server {
    listen 443 ssl;
    server_name bizuno.your-domain.com;
    root /var/www/bizuno;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/bizuno.your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/bizuno.your-domain.com/privkey.pem;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param HTTP_X_FORWARDED_PROTO https;   # so Bizuno emits https URLs
    }
}
```

The `HTTP_X_FORWARDED_PROTO https` param is the Nginx equivalent of
the `.htaccess` proxy check — without it Bizuno emits `http://` URLs
behind TLS and redirects break.

## See also

- [Three Ways to Run It](./02-three-ways-to-run-it.md) — pick the right path
- [Docker Install Walkthrough](./04-docker-install-walkthrough.md) — containerized alternative
- [WordPress Install Walkthrough](./06-wordpress-install-walkthrough.md) — run inside WP admin instead
- [First-hour walkthrough](./03-first-hour-walkthrough.md) — what to do once you're logged in
- [Administration → Backup and Restore](../05-administration/03-backup-and-restore.md)
