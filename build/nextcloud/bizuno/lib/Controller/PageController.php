<?php
declare(strict_types=1);

/**
 * Bizuno NextCloud app — main page controller.
 *
 * Single responsibility: render the iframe that hosts Bizuno. The Bizuno
 * server URL comes from an app config value (set by the admin in
 * Settings → Administration → Bizuno ERP). If unconfigured, the template
 * renders an "ask your admin" placeholder instead.
 *
 * Why the explicit CSP rebuild: NextCloud's default Content-Security-Policy
 * disallows framing arbitrary third-party domains. We add the configured
 * Bizuno URL's domain to the allowed-frame list so the iframe loads. If
 * we didn't, the iframe would show a CSP-blocked error in the browser
 * console and a blank page.
 */

namespace OCA\Bizuno\Controller;

use OCA\Bizuno\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IRequest;

class PageController extends Controller {

    public function __construct(
        IRequest $request,
        private readonly IConfig $config,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Render the Bizuno iframe page.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        $bizunoUrl = trim(
            $this->config->getAppValue(Application::APP_ID, 'bizuno_url', '')
        );

        $response = new TemplateResponse(
            Application::APP_ID,
            'main',
            ['bizuno_url' => $bizunoUrl],
        );

        if ($bizunoUrl !== '') {
            $csp = new ContentSecurityPolicy();
            $csp->addAllowedFrameDomain($bizunoUrl);
            // Bizuno's UI opens print/preview windows; allow popups too.
            $csp->addAllowedChildSrcDomain($bizunoUrl);
            $response->setContentSecurityPolicy($csp);
        }

        return $response;
    }
}
