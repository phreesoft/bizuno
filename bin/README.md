# Bizuno tooling

Small CLI helpers that live with the code but aren't loaded at request time.

## `bizuno-update.php`

Auto-updater. Pulls the latest **production** release of `phreesoft/bizuno` from
GitHub (the `releases/latest` endpoint excludes drafts and pre-releases), backs
up the current program library next to itself, swaps the new files into place
with an atomic directory rename, and flags the business cache for a rebuild so
schema migrations run on the next web request. Run from the install root:

```bash
php bin/bizuno-update.php --check          # report installed vs latest, change nothing
php bin/bizuno-update.php --apply           # update to the latest release (prompts to confirm)
php bin/bizuno-update.php --apply --yes      # ...without the prompt
php bin/bizuno-update.php --version=7.4.5 --apply
php bin/bizuno-update.php --cron             # apply a UI-queued update if pending, else exit 0 quietly
```

This is a command-line alternative to the in-browser updater at **Settings →
Bizuno → Software Updates** (which applies updates directly, in-request, behind
a progress modal with the site held in maintenance mode). Use this script when
you prefer SSH, want to script/cron updates, or the web user can't write to the
library's parent directory.

`--cron` applies a `data/updates/pending.json` marker if one is present and
otherwise exits 0 quietly, so it's safe to schedule:

```cron
*/15 * * * * cd /path/to/install && php bin/bizuno-update.php --cron
```

The previous library is kept as `<lib>.old-<timestamp>` for instant rollback;
on any failure during the swap the original is restored automatically.
