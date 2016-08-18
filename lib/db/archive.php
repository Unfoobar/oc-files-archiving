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

namespace OCA\Files_Archiving\Lib\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Class Archive describes an CDSTAR object
 *
 * @package OCA\Files_Archiving\Lib
 *
 * @method string getObjectId()
 * @method void setObjectId(string $value)
 * @method string getName()
 * @method void setName(string $value)
 */
class Archive extends Entity {

    protected $objectId;
    protected $name;

    public function __construct() {

    }
}