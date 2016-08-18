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

namespace OCA\Files_Archiving\Lib;

use OCA\windows_network_drive\Lib\Auth\GlobalAuth;
use OCA\windows_network_drive\Lib\Auth\LoginCredentials;
use OCA\windows_network_drive\Lib\Auth\UserProvided;

class Hooks {
    public static function loadArchiveBackend() {
        if (class_exists('OC_Mount_Config')) {
            $l = \OC::$server->getL10N('files_archiving');
            $password = new \OCA\Files_External\Lib\Auth\Password\Password($l);
            $backend = new fs_backend\CDSTAR($l, $password);
            $service = \OC::$server->getStoragesBackendService();
            $service->registerBackend($backend);

            $session = \OC::$server->getSession();
            $credentialsManager = \OC::$server->getCredentialsManager();
            $loginAuth = new LoginCredentials($l, $session, $credentialsManager);
            $userAuth = new UserProvided($l, $credentialsManager);
            $globalAuth = new GlobalAuth($l, $credentialsManager);

            $service->registerAuthMechanisms([
                $loginAuth,
                $userAuth,
                $globalAuth
            ]);
        }
    }
}

