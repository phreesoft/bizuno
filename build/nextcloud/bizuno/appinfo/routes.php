<?php
declare(strict_types=1);

/**
 * Bizuno NextCloud app — route table.
 *
 * NextCloud's app framework reads this file at request time. Each entry
 * maps a URL pattern to a controller#method pair. The route name is
 * referenced from info.xml's <navigation><route> element ("bizuno.page.index")
 * — the format is "<app-id>.<controller>.<method>" where <controller> is
 * the controller class name lower-cased minus the "Controller" suffix.
 *
 * For this launcher app there's only one user-facing route: the page that
 * renders the Bizuno iframe. Settings pages register themselves through
 * the ISettings interface, not through routes.
 */
return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
    ],
];
