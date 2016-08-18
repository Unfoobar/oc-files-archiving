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
use OCA\Files_External\Service\BackendService;

$l = \OC::$server->getL10N('files_archiving');

// Add archive filter to navigation bar
/*\OCA\Files\App::getNavigationManager()->add([
    "id" => 'archives',
    "appname" => 'files_archiving',
    "script" => 'list.php',
    "order" => 30,
    "name" => $l->t('Archives')
]);*/


if(!\OCP\App::isEnabled('files_external')){
    \OC_App::enable('files_external');
}

\OCP\App::checkAppEnabled('files_archiving');
\OCP\Util::connectHook('OC_Filesystem', 'preSetup', 'OCA\Files_Archiving\Lib\Hooks', 'loadArchiveBackend');