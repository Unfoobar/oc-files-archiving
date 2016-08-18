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
use OCP\AppFramework\Db\Mapper;
use OCP\IDb;
use OCP\IDBConnection;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

class ArchiveMapper extends Mapper {

    public function __construct(IDBConnection $Db) {
        parent::__construct($Db, 'files_archiving_archives', '\OCA\Files_Archiving\Lib\Db\Archive');
    }

    /**
     * Deletes an entity from the table
     *
     * @param Entity $archive the archive that should be deleted
     * @return Archive the deleted archive
     */
    public function delete(Entity $archive) {
        $sql = 'DELETE FROM `' . $this->tableName . '` WHERE `object_id` = ?';
        $stmt = $this->execute($sql, [$archive->getObjectId()]);
        $stmt->closeCursor();
        return $archive;
    }

    /**
     * Updates an entity from the table
     *
     * @param Entity $archive the archive that should be updated
     * @return Archive the updated archive
     */
    public function update(Entity $archive) {
        $sql = 'UPDATE `' . $this->tableName . '` SET `name` = ? WHERE `object_id` = ?';
        $stmt = $this->execute($sql, [$archive->getName(), $archive->getObjectId()]);
        $stmt->closeCursor();
        return $archive;
    }

    /**
     * Returns the entity with given objectId
     *
     * @param string $objectId CDSTAR object id
     * @return Archive
     * @throws DoesNotExistException if the item does not exist
     * @throws MultipleObjectsReturnedException if more than one item exist
     */
    public function getByObjectId($objectId) {
        $sql = 'SELECT `object_id`, `name` FROM `' .
            $this->tableName . '` WHERE `object_id` = ?';
        return $this->findEntity($sql, [$objectId]);
    }

    /**
     * Returns the default name of archives
     *
     * @return string default name
     */
    public function getArchiveDefaultName() {
        return 'archive_' . date("Y-m-d_H-i-s");
    }
}