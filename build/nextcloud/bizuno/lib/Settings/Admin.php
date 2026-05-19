<?php
declare(strict_types=1);

/**
 * Bizuno NextCloud app — admin settings form.
 *
 * Renders the URL-configuration field in Settings → Administration →
 * Bizuno ERP. Saved via OCP.AppConfig.setValue() from inline JS on the
 * admin template (see js/admin.js) — no server-side handler needed
 * because NC ships a generic AppConfig setter endpoint.
 */

namespace OCA\Bizuno\Settings;

use OCA\Bizuno\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

class Admin implements ISettings {

    public function __construct(private readonly IConfig $config) {}

    public function getForm(): TemplateResponse {
        return new TemplateResponse(
            Application::APP_ID,
            'settings/admin',
            [
                'bizuno_url' => $this->config->getAppValue(
                    Application::APP_ID,
                    'bizuno_url',
                    ''
                ),
            ],
        );
    }

    /**
     * Section id (matches AdminSection::getID()) — groups this settings
     * panel under its own sidebar entry in the admin UI.
     */
    public function getSection(): string {
        return 'bizuno';
    }

    /**
     * Display order within the section. Only one panel here so the value
     * doesn't really matter; 50 is a conventional "middle" priority.
     */
    public function getPriority(): int {
        return 50;
    }
}
