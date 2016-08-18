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

interface IFileInfo {
    
    /**
     * @return string
     */
    public function getPath();

    /**
     * @return string
     */
    public function getName();

    /**
     * @return string
     */
    public function getUID();

    /**
     * @return int
     */
    public function getSize();

    /**
     * @return int
     */
    public function getMTime();

    /**
     * @return int
     */
    public function getCTime();

    /**
     * @return bool
     */
    public function isDirectory();

    /**
     * @return bool
     */
    public function isReadOnly();

    /**
     * @return bool
     */
    public function isHidden();

    /**
     * @return bool
     */
    public function isSystem();

    /**
     * @return bool
     */
    public function isArchived();
}
