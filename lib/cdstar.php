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

use Icewind\Streams\CallbackWrapper;
use Icewind\Streams\IteratorDirectory;
use OC\Cache\CappedMemoryCache;
use OC\Files\Filesystem;
use OC\Files\Storage\Common;
use OCP\Files\StorageAuthException;
use OCP\Files\ForbiddenException;
use OCP\Files\NotFoundException;
use OCP\Files\StorageTimeOutException;
use OCP\Files\EntityTooLargeException;
use OCP\Files\StorageInvalidException;
use OCP\Files\StorageBadConfigException;
use OCP\Files\StorageNotAvailableException;
use OCP\Files\StorageConnectionException;

use OCA\Files_Archiving\Lib\Db\ArchiveMapper;

/**
 * Filesystem class to connect to CDSTAR
 */
class CDSTAR extends Common {

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ArchiveMapper
     */
    protected $archiveMapper;

    /**
     * @var CDSTARHelper
     */
    protected $cdstarHelper;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string <username>:<password> base64 encoded
     */
    protected $auth_base64;

    /**
     * @var \OCA\Files_Archiving\Lib\FileInfo[]
     */
    protected $statCache;

    /**
     * CDSTAR constructor
     * @param array $params
     */
    public function __construct($params) {
        $this->username = $params['user'];
        $this->auth_base64 = base64_encode($params['user'] . ":" . $params['password']);

        $this->logger = new Logger('files_archiving', \OC::$server->getLogger(),
            \OC::$server->query('Request'), \OC::$server->getUserSession());

        $this->archiveMapper = new ArchiveMapper(\OC::$server->getDatabaseConnection());

        $this->cdstarHelper = new CDSTARHelper(
            $this->logger,
            $this->archiveMapper,
            $this->username,
            $this->auth_base64
        );
    }

    /**
     * Returns the filesystem id (for ownCloud purposes).
     *
     * @return string the filesystem id
     */
    public function getId() {
        return 'cdstar::' . $this->username . '@' . CDSTARHelper::URL_CDSTAR;
    }

    /**
     * Creates a new directory
     *
     * @param string $path path of new dir
     * @return bool success
     */
    public function mkdir($path) {
        try {
            $res = $this->splitPath($this->cdstarHelper->getArchiveUID($path));
            $archive_name = $res['node'];
            $parent_uid = $this->splitPath($res['path'])['node'];
            $parent_collection = $this->cdstarHelper->getArchiveCollection($parent_uid);

            $res = $this->cdstarHelper->createArchive();
            $uid = $res['uid'];
            $collection = $res['collection'];

            $this->cdstarHelper->setArchiveInfo($uid, $archive_name);
            $this->cdstarHelper->addCollectionToCollection($collection, $parent_collection);

            return true;
        } catch(\Exception $e) {
            return false;
        }
    }

    /**
     * Deletes a directory
     *
     * @param string $path path of dir
     * @return bool success
     */
    public function rmdir($path) {
        try {
            $this->statCache = array();
            $content = $this->dir($this->buildPath($path));
            foreach ($content as $file) {
                if ($file->isDirectory()) {
                    $this->rmdir($path . '/' . $file->getName());
                } else {
                    $this->del($file->getPath());
                }
            }

            $uid = $this->getUID($path);
            $this->deleteArchive($uid);

            return true;
        } catch(\Exception $e) {
            return false;
        }
    }

    /**
     * Returns the content of folder
     *
     * @param string $path path of dir
     * @return bool success
     */
    public function opendir($path) {
        try {
            $files = $this->getFolderContents($path);
        } catch(\Exception $e) {
            return false;
        }
        $names = array_map(function ($info) {
            /** @var \OCA\Files_Archiving\Lib\IFileInfo $info */
            return $info->getName();
        }, $files);
        return IteratorDirectory::wrap($names);
    }

    /**
     * Returns the FileInfo of node
     *
     * @param string $path path of node
     * @return bool|File|Folder
     */
    public function stat($path) {
        try {
            if (!$this->is_dir($path)) {
                $uid = $this->getParentUID($path);
                $id = $this->splitPath($path)['node'];
                $files = $this->cdstarHelper->getFileInfo($uid, $id);

                return new File(
                    $path,
                    $files['id'],
                    $files['content-type'],
                    $files['filesize'],
                    $files['checksum'],
                    $files['last-modified'],
                    $files['created']
                );
            } else {
                $uid = $this->getUID($path);
                $archive = $this->cdstarHelper->getArchiveInfo($uid);

                return new Folder(
                    $path,
                    $archive['name'],
                    $archive['uid'],
                    $archive['last-modified'],
                    $archive['created'],
                    $archive['metadata'],
                    $archive['revision']
                );
            }
        } catch(\Exception $e) {
            return false;
        }
    }

    /**
     * Returns the filetype of node
     *
     * @param string $path path of node
     * @return bool|string
     */
    public function filetype($path) {
        try {
            return $this->getFileInfo($path)->isDirectory() ? 'dir' : 'file';
        } catch(\Exception $e) {
            return false;
        }
    }

    /**
     * Checks if node is creatable. By default, it is not.
     *
     * @param string $path path of node
     * @return bool
     */
    public function isCreatable($path) {
        return false;
    }

    /**
     * Checks if node is readable
     *
     * @param string $path path of node
     * @return bool
     */
    public function isReadable($path) {
        try {
            $info = $this->getFileInfo($path);
            return !$info->isHidden();
        } catch(\Exception $e) {
            return false;
        }
    }

    /**
     * Checks if node is updatable
     *
     * @param string $path path of node
     * @return bool
     */
    public function isUpdatable($path) {
        try {
            $info = $this->getFileInfo($path);
            return !$info->isHidden() && !$info->isReadOnly();
        } catch(\Exception $e) {
            return false;
        }
    }

    /**
     * Checks if node has been updated since $time
     *
     * @param string $path path of node
     * @param int $time
     * @return bool
     */
    public function hasUpdated($path, $time) {
        if (!$path) {
            return true;
        } else {
            $actualTime = $this->filemtime($path);
            return $actualTime > $time;
        }
    }

    /**
     * Returns the last modification time of node
     *
     * @param string $path path of node
     * @return bool|int
     */
    public function filemtime($path) {
        try {
            return $this->getFileInfo($path)->getMTime();
        } catch(\Exception $e) {
            return false;
        }
    }

    /**
     * Checks if node at path exists
     *
     * @param string $path path of node
     * @return bool
     */
    public function file_exists($path) {
        try {
            $this->getFileInfo($path);
            return true;
        } catch(\Exception $e) {
            return false;
        }
    }

    /**
     * Deletes a file or directory
     *
     * @param string $path path of node
     * @return bool
     */
    public function unlink($path) {
        try {
            if ($this->is_dir($path)) {
                return $this->rmdir($path);
            } else {
                $path = $this->buildPath($path);
                unset($this->statCache[$path]);
                $this->del($path);
                return true;
            }
        } catch(\Exception $e) {
            return false;
        }
    }

    /**
     * Returns the fopen resource of file
     *
     * @param string $path path of node
     * @param string $mode fopen mode
     * @return bool|resource
     */
    public function fopen($path, $mode) {
        $fullPath = $this->buildPath($path);
        try {
            switch ($mode) {
                case 'r':
                case 'rb':
                    if (!$this->file_exists($path)) {
                        return false;
                    }
                    return $this->read($fullPath);
                case 'w':
                case 'wb':
                    $source = $this->write($fullPath);
                    return CallBackWrapper::wrap($source, null, null, function () use ($fullPath) {
                        unset($this->statCache[$fullPath]);
                    });
                case 'a':
                case 'ab':
                case 'r+':
                case 'w+':
                case 'wb+':
                case 'a+':
                case 'x':
                case 'x+':
                case 'c':
                case 'c+':
                    //emulate these
                    if (strrpos($path, '.') !== false) {
                        $ext = substr($path, strrpos($path, '.'));
                    } else {
                        $ext = '';
                    }
                    if ($this->file_exists($path)) {
                        if (!$this->isUpdatable($path)) {
                            return false;
                        }
                        $tmpFile = $this->getCachedFile($path);
                    } else {
                        if (!$this->isCreatable(dirname($path))) {
                            return false;
                        }
                        $tmpFile = \OCP\Files::tmpFile($ext);
                    }
                    $source = fopen($tmpFile, $mode);
                    return CallbackWrapper::wrap($source, null, null, function () use ($tmpFile, $fullPath) {
                        unset($this->statCache[$fullPath]);
                        $this->put($tmpFile, $fullPath);
                        unlink($tmpFile);
                    });
            }
            return false;
        } catch(\Exception $e) {
            return false;
        }
    }

    /**
     * Creates a new file without content
     *
     * @param string $path path of new file
     * @param int $time
     * @return bool success
     */
    public function touch($path, $time = null) {
        try {
            if (!$this->file_exists($path)) {
                // TODO: create file
                return true;
            }
            return false;
        } catch(\Exception $e) {
            return false;
        }
    }

    /**
     * Checks if node is an archive
     *
     * @param string $path path of node
     * @return bool
     */
    public function is_dir($path) {
        try {
            $this->getUID($path);
            return true;
        } catch(\Exception $e) {
            return false;
        }
    }
	
	/**
	 * Tests a storage for availability
	 *
	 * @return bool
	 */
	public function test() {
		try {
		    $this->cdstarHelper->listBaseArchives();
            return true;
        } catch(\Exception $e) {
            return false;
        }
	}

    /**
     * Checks the dependencies
     *
     * @return bool
     */
    public static function checkDependencies() {
        return true;
    }

    /**
     * Returns the free storage space of directory in bytes
     *
     * @param string $path path of dir
     * @return int
     */
    public function free_space($path) {
        return 0;
    }

    /**
     * Returns the FileInfo of file or directory
     *
     * @param string $path path of node
     * @return \OCA\Files_Archiving\Lib\FileInfo
     */
    protected function getFileInfo($path) {
        $path = $this->buildPath($path);
        if (!isset($this->statCache[$path])) {
            $this->statCache[$path] = $this->stat($path);
        }
        return $this->statCache[$path];
    }

    /**
     * Returns the content of directory
     *
     * @param string $path path of node
     * @return \OCA\Files_Archiving\Lib\FileInfo[] content
     */
    protected function getFolderContents($path) {
        $path = $this->buildPath($path);
        $files = $this->dir($path);
        foreach ($files as $file) {
            $this->statCache[$path . '/' . $file->getName()] = $file;
        }
        return $files;
    }

    /**
     * Returns the formatted array of FileInfo
     *
     * @param \OCA\Files_Archiving\Lib\FileInfo $info
     * @return array
     */
    protected function formatInfo($info) {
        return array(
            'size' => $info->getSize(),
            'mtime' => $info->getMTime()
        );
    }

    /**
     * Returns the list of archive
     *
     * @param string $path path of node
     * @return array file and folder list
     */
    protected function dir($path) {
        if (is_dir($path)) {
            $uid = $this->getUID($path);
            $content = [];
            $archive_content = $this->cdstarHelper->getArchiveContent($uid);

            foreach ($archive_content['archives'] as $archive) {
                $content[] = new Folder(
                    $path,
                    $archive['name'],
                    $archive['uid'],
                    $archive['last-modified'],
                    $archive['created'],
                    $archive['metadata'],
                    $archive['revision']
                );
            }

            foreach ($archive_content['files'] as $file) {
                $content[] = new File(
                    $path,
                    $file['bitstreamid'],
                    $file['content-type'],
                    $file['filesize'],
                    $file['checksum'],
                    $file['last-modified'],
                    $file['created']
                );
            }

            return $content;
        }
        return null;
    }

    /**
     * Deletes an archive
     *
     * @param string $path path of directory
     * @throws \Exception
     */
    protected function del($path) {
        $parts = explode('/', $path);
        $id = end($parts);
        $uid = $this->getUID(join('/', array_slice($parts, 0, sizeof($parts) - 1)));
        $this->cdstarHelper->deleteFile($id, $uid);
    }

    protected function write($path) {
        //TODO: implement write()
        return null;
    }

    protected function read($path) {
        //TODO: implement read()
        return null;
    }

    protected function getCachedFile($path) {
        //TODO: implement getCachedFile()
        return null;
    }

    protected function put($path) {
        //TODO: implement put()
    }

    /**
     * Returns the archive uid of directory
     *
     * @param string $path path of directory
     * @return string archive uid
     * @throws NotFoundException
     * @throws StorageInvalidException
     */
    protected function getUID($path) {
        return end(explode('/', $this->cdstarHelper->getArchiveUID($path)['uids']));
    }

    /**
     * Returns the archive uid of parent directory
     *
     * @param string $path path of child
     * @return string archive uid
     * @throws NotFoundException
     * @throws StorageInvalidException
     */
    protected function getParentUID($path) {
        $this->cdstarHelper->getArchiveUID($path);
        $path = join('/', array_slice($parts = explode('/', $path), 0, sizeof($parts) - 1));
        $res = $this->getArchiveUID($path);
        prev($res['response']['uids']);
        return end(explode('/', prev($res['response']['uids'])));
    }

    /**
     * Splits the given node path into node name and parent path
     *
     * @param string $path path of node
     * @return array
     */
    protected function splitPath($path) {
        $parts = explode('/', $path);
        if(sizeof($parts) < 2) {
            return ['path' => '/', 'node' => $path];
        }

        $node = end($parts);
        $path = join('/', array_slice($parts, 0, sizeof($parts) - 1));
        return ['path' => $path, 'node' => $node];
    }
}
