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

class File implements IFileInfo {

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
    private $mimetype;

    /**
     * @var int
     */
    private $size;

    /**
     * @var string
     */
    private $checksum;

    /**
     * @var int
     */
    private $mtime;

    /**
     * @var int
     */
    private $ctime;

    public function __construct(
        $path,
        $name,
        $mimetype,
        $size,
        $checksum,
        $mtime,
        $ctime
    ) {
        $this->path = $path;
        $this->name = $name;
        $this->mimetype = $mimetype;
        $this->size = $size;
        $this->checksum = $checksum;
        $this->mtime = $mtime;
        $this->ctime = $ctime;
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
        return $this->name;
    }

    /**
     * @return string
     */
    public function getMimetype() {
        return $this->mimetype;
    }

    /**
     * @return int
     */
    public function getSize() {
        return $this->size;
    }

    /**
     * @return string
     */
    public function getChecksum() {
        return $this->checksum;
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
        return false;
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