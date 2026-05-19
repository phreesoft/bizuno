<?php
/**
 * Bizuno NextCloud app — admin settings template.
 *
 * Variables in scope (set by Admin::getForm):
 *   $_['bizuno_url']  string  current configured Bizuno URL
 *   $l                IL10N   translation helper
 *
 * The URL is persisted via OCP.AppConfig.setValue() from js/admin.js on
 * input change — no form submission, no CSRF dance, no custom endpoint.
 */

use OCA\Bizuno\AppInfo\Application;

script(Application::APP_ID, 'admin');
?>
<section id="bizuno-settings" class="section">
    <h2 class="inlineblock"><?php p($l->t('Bizuno ERP')); ?></h2>

    <p class="settings-hint">
        <?php p($l->t('Set the externally-reachable URL of your Bizuno installation. NextCloud users will see a Bizuno entry in their navigation menu and can launch it from there.')); ?>
    </p>

    <p>
        <label for="bizuno-url"><?php p($l->t('Bizuno URL')); ?>:</label>
        <input type="url"
               id="bizuno-url"
               name="bizuno_url"
               value="<?php p($_['bizuno_url']); ?>"
               placeholder="https://bizuno.example.com/"
               style="width: 420px;">
        <span id="bizuno-url-saved" class="msg success hidden">✓ <?php p($l->t('Saved')); ?></span>
    </p>

    <p class="settings-hint">
        <?php print_unescaped($l->t(
            'Tip: the official Docker image (%s) is the quickest way to spin up a Bizuno server alongside NextCloud.',
            ['<code>ghcr.io/phreesoft/bizuno:latest</code>']
        )); ?>
    </p>

    <p class="settings-hint">
        <?php print_unescaped($l->t(
            'See the %sBizuno docs%s for SSL / reverse-proxy guidance.',
            ['<a href="https://bizuno.com/docs/" target="_blank" rel="noopener">', '</a>']
        )); ?>
    </p>
</section>
