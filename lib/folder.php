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

class Folder implements IFileInfo {

    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $uid;

    /**
     * @var int
     */
    private $mtime;

    /**
     * @var int
     */
    private $ctime;

    /**
     * @var string
     */
    private $metadata;

    /**
     * @var string
     */
    private $revision;

    public function __construct(
        $path,
        $name,
        $uid,
        $mtime,
        $ctime,
        $metadata,
        $revision
    ) {
        $this->path = $path;
        $this->name = $name;
        $this->uid = $uid;
        $this->mtime = $mtime;
        $this->ctime = $ctime;
        $this->metadata = $metadata;
        $this->revision = $revision;
    }

    /**
     * @return string
     */
    public function getPath() {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getUID() {
        return $this->uid;
    }

    /**
     * @return int
     */
    public function getSize() {
        return -1;
    }

    /**
     * @return int
     */
    public function getMTime() {
        return $this->mtime;
    }

    /**
     * @return int
     */
    public function getCTime() {
        return $this->ctime;
    }

    /**
     * @return bool
     */
    public function isDirectory() {
        return true;
    }

    /**
     * @return bool
     */
    public function isReadOnly() {
        return true;
    }

    /**
     * @return bool
     */
    public function isHidden() {
        return false;
    }

    /**
     * @return bool
     */
    public function isSystem() {
        return false;
    }

    /**
     * @return bool
     */
    public function isArchived() {
        return true;
    }
}