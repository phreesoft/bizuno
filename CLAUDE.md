# Bizuno

Open-source ERP and accounting application. This repository is **public** on
GitHub and is published as releases (zip, Docker image, NextCloud app,
Packagist), so everything committed here is visible to anyone.

## Keep client-specific work out of this repo

- Only general-purpose code belongs here. Customizations for a specific
  business (`myExt/` modules, supplier or marketplace integrations,
  storefront plugins, branded report templates) live in a separate private
  client repository.
- Never commit a client's name, logo, addresses, contact emails, bank or
  account numbers, API keys, hostnames or customer data: not in code, report
  templates, docs, comments, commit messages or PR descriptions.
- Default report templates in `src/locale/en_US/reports/` must stay neutral.
  Use the `%company%` token for the business name, leave logo fields empty
  (the `CImg` field renders the business's own logo), and leave bank details
  blank.
- Use generic examples in docs (`example-client`, `example.com`).
- If a client needs something new, add a generic hook or extension point
  here and put the client-specific implementation in the private repository.
- Commits use the GitHub no-reply author address
  (`phreesoft@users.noreply.github.com`), not a company email.

## Layout

- `src/`: the application (controllers, model, view, locale, portal).
- `build/`: release variants (`zip/`, `docker/`, `nextcloud/`), built by
  `.github/workflows/release.yml` on `v*` tags.
- `docs/`: user and admin documentation (Markdown, read on GitHub).
- `src/VERSION`: the release version; the release workflow checks it
  against the tag.
- Database upgrade steps are in `src/controllers/bizuno/install/upgrade.php`.
  Number new steps above `BIZUNO_UPGRADE_GATE` and bump that constant.

The WordPress plugin (`bizuno-accounting`) is retired. Do not reintroduce a
WordPress.org build or publishing step.
