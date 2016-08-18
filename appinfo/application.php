<?php
/**
 * ownCloud - files_archiving
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Nikolaj Kopp <nkopp1@gwdg.de>
 * @copyright Nikolaj Kopp 2016
 */

namespace OCA\Files_Archiving\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;

class Application extends App {

    /**
     * @param array $params
     */
    public function __construct($params=[]) {
        parent::__construct('archiving', $params);
        $container = $this->getContainer();

        $container->registerService('Logger', function($c) {
            return $c->query('ServerContainer')->getLogger();
        });
        $container->registerService('UserSession', function($c) {
            return $c->query('ServerContainer')->getUserSession();
        });
        $container->registerService('L10N', function($c) {
            return $c->query('ServerContainer')->getL10N('archiving');
        });
    }

    /**
     * register navigation entry
     */
    public function registerNavigation() {
        $appName = $this->getContainer()->getAppName();
        $server = $this->getContainer()->getServer();

        $server->getNavigationManager()->add([
            'id' => $appName,
            'order' => 10,
            'href' => $server->getURLGenerator()->linkToRoute('archiving.page.index'),
            'icon' => $server->getURLGenerator()->imagePath($appName, 'app.svg'),
            'name' => $server->getL10N($appName)->t('Archiving'),
        ]);
    }
}
