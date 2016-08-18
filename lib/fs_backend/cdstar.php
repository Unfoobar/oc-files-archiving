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

namespace OCA\Files_Archiving\Lib\fs_backend;

use OCP\IL10N;
use OCA\Files_External\Lib\Backend\Backend;
use OCA\Files_External\Lib\DefinitionParameter;
use OCA\Files_External\Lib\Auth\AuthMechanism;
use OCA\Files_External\Service\BackendService;
use OCA\Files_External\Lib\LegacyDependencyCheckPolyfill;
use OCA\Files_External\Lib\Auth\Password\Password;

class CDSTAR extends Backend {

    use LegacyDependencyCheckPolyfill;

    public function __construct(IL10N $l, Password $legacyAuth) {
        $this
            ->setIdentifier('files_archiving')
            ->addIdentifierAlias('\OCA\Files_Archiving\Lib\CDSTAR')
            ->setStorageClass('\OCA\Files_Archiving\Lib\CDSTAR')
            ->setText('CDSTAR')
            ->addParameters([
                // all parameters handled in auth mechanism
            ])
            ->addAuthScheme(AuthMechanism::SCHEME_PASSWORD)
            ->setLegacyAuthMechanism($legacyAuth)
        ;
    }
}
