<?php
/**
 * Bizuno NextCloud app — main page template.
 *
 * Variables in scope (set by PageController::index):
 *   $_['bizuno_url']  string  externally-reachable Bizuno URL, may be empty
 *   $l                IL10N   translation helper
 *
 * Two render paths:
 *   1. URL configured  → full-window sandboxed iframe of Bizuno
 *   2. URL unconfigured → emptycontent placeholder with admin instructions
 */

use OCA\Bizuno\AppInfo\Application;

style(Application::APP_ID, 'main');
?>
<?php if (empty($_['bizuno_url'])): ?>
    <div id="app-content">
        <div id="emptycontent" class="emptycontent">
            <div class="icon-settings-dark"></div>
            <h2><?php p($l->t('Bizuno not yet configured')); ?></h2>
            <p>
                <?php p($l->t('Ask your NextCloud administrator to set the Bizuno server URL in Settings → Administration → Bizuno ERP.')); ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <iframe id="bizuno-frame"
            src="<?php p($_['bizuno_url']); ?>"
            sandbox="allow-forms allow-popups allow-popups-to-escape-sandbox allow-scripts allow-same-origin allow-downloads allow-modals"
            allow="clipboard-read; clipboard-write; fullscreen"
            title="<?php p($l->t('Bizuno ERP')); ?>"
            referrerpolicy="strict-origin-when-cross-origin">
    </iframe>
<?php endif; ?>
