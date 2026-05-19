<?php
declare(strict_types=1);

/**
 * Bizuno NextCloud app — bootstrap.
 *
 * NextCloud's app framework auto-discovers this class via PSR-4 autoload
 * (`OCA\Bizuno\AppInfo\Application` ⇒ lib/AppInfo/Application.php) and
 * invokes register()/boot() at the appropriate point in the request
 * lifecycle.
 *
 * For this launcher app we have nothing to register — controllers and
 * settings are resolved automatically via dependency injection from their
 * type-hinted constructors, the route comes from appinfo/routes.php, and
 * the navigation entry comes from appinfo/info.xml. The class still has
 * to exist (NextCloud's app loader looks for it), but it can be empty.
 */

namespace OCA\Bizuno\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {

    public const APP_ID = 'bizuno';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    /**
     * Services and event listeners get registered here. Nothing yet —
     * placeholder for future SSO bridge / file-attachment hooks.
     */
    public function register(IRegistrationContext $context): void {
        // intentionally empty
    }

    /**
     * Runs after every other app has registered. Place to fire one-time
     * boot logic; we don't need any.
     */
    public function boot(IBootContext $context): void {
        // intentionally empty
    }
}
