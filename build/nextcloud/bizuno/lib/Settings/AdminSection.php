<?php
declare(strict_types=1);

/**
 * Bizuno NextCloud app — admin sidebar section.
 *
 * Creates the "Bizuno ERP" entry in the left sidebar of NextCloud's admin
 * settings page. Click → renders the Admin settings form (see Admin.php).
 *
 * Lives in its own class because NextCloud requires a separate
 * IIconSection implementation; it can't be a method on Admin.
 */

namespace OCA\Bizuno\Settings;

use OCA\Bizuno\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {

    public function __construct(
        private readonly IL10N $l,
        private readonly IURLGenerator $urlGen,
    ) {}

    public function getID(): string {
        return 'bizuno';
    }

    public function getName(): string {
        return $this->l->t('Bizuno ERP');
    }

    /**
     * Where this section appears in the admin sidebar. NC's built-in
     * sections (security, sharing, etc.) use 0..70 so 75 puts us
     * comfortably below them.
     */
    public function getPriority(): int {
        return 75;
    }

    /**
     * Sidebar icon. NextCloud convention: `app-dark.svg` for use on
     * the light-themed admin sidebar (currentColor doesn't help there
     * because the sidebar SVG renderer doesn't theme it).
     */
    public function getIcon(): string {
        return $this->urlGen->imagePath(Application::APP_ID, 'app-dark.svg');
    }
}
