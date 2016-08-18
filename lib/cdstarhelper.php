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

use OCP\AppFramework\Http;
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
 * Class CDSTARHelper contains necessary functions for communication with CDSTAR
 *
 * @package OCA\Files_Archiving\Lib
 */
class CDSTARHelper {

    const URL_CDSTAR = 'https://cdstar-prod04.gwdg.de/dev/null'; // CDSTAR public test instance
    const PATH_TMP_DOWNLOAD = 'apps/files_archiving/tmp/download/';
    const PATH_TMP_UPLOAD = 'apps/files_archiving/tmp/upload/';

    /**
     * @var ArchiveMapper
     */
    private $archiveMapper;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string <username>:<password> base64 encoded
     */
    private $auth_base64;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * CDSTARHelper constructor
     *
     * @param Logger $Logger
     * @param ArchiveMapper $ArchiveMapper
     * @param string $Username
     * @param string $Auth_base64
     * @throws StorageAuthException
     */
    public function __construct(Logger $Logger, ArchiveMapper $ArchiveMapper, $Username, $Auth_base64) {
        $this->logger = $Logger;
        $this->archiveMapper = $ArchiveMapper;
        $this->username = $Username;
        $this->auth_base64 = $Auth_base64;

        if(strlen($Username) == 0 || strlen($Auth_base64) == 0) {
            throw new StorageAuthException();
        }
    }

    /**
     * Writes the message to log file if logger is initialized
     *
     * @param string $type logging type
     * @param string $message log message
     */
    private function log($type, $message) {
        if($this->logger === null) {
            return;
        }

        switch($type) {
            case 'info':
                $this->logger->info($message);
                break;
            case 'error':
                $this->logger->error($message);
                break;
        }
    }

    /**
     * Returns the OC files exception of http code
     *
     * @param int $http_code
     * @return StorageAuthException | ForbiddenException | NotFoundException | StorageTimeOutException | EnityTooLargeException | StorageInvalidException | StorageBadConfigException | StorageNotAvailableException | StorageConnectionException
     */
    private function getException($http_code) {
        switch($http_code) {
            case HTTP::STATUS_OK:
            case HTTP::STATUS_CREATED:
                return null;
            case HTTP::STATUS_UNAUTHORIZED:
                return new StorageAuthException($http_code);
            case HTTP::STATUS_FORBIDDEN:
            case HTTP::STATUS_METHOD_NOT_ALLOWED:
            case HTTP::STATUS_UNSUPPORTED_MEDIA_TYPE:
            case HTTP::STATUS_CONFLICT:
                return new ForbiddenException($http_code);
            case HTTP::STATUS_NOT_FOUND:
                return new NotFoundException($http_code);
            case HTTP::STATUS_REQUEST_TIMEOUT:
                return new StorageTimeOutException($http_code);
            case HTTP::STATUS_REQUEST_ENTITY_TOO_LARGE:
                return new EntityTooLargeException($http_code);
            case HTTP::STATUS_INTERNAL_SERVER_ERROR:
                return new StorageInvalidException($http_code);
            case HTTP::STATUS_BAD_GATEWAY:
                return new StorageBadConfigException($http_code);
            case HTTP::STATUS_SERVICE_UNAVAILABLE:
                return new StorageNotAvailableException($http_code);
            default:
                return new StorageConnectionException($http_code);
        }
    }

    /**
     * Returns the basic authorization header
     *
     * @return array
     */
    private function getAuthHeader() {
        return ['Authorization: Basic ' . $this->auth_base64];
    }

    /**
     * Executes an http request to CDSTAR server and returns the response
     *
     * @param string $route CDSTAR service route
     * @param string $method http method
     * @param array $headers http headers
     * @param string $payload http request payload
     * @return array array which contains request curl info and response text
     */
    private function request($route, $method, $headers, $payload) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, CDSTARHelper::URL_CDSTAR . $route);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $output = curl_exec($ch);

        $res = [
            'info' => curl_getinfo($ch),
            'output' => $output,
        ];

        curl_close($ch);

        return $res;
    }

    /**
     * Executes a file download from CDSTAR archive
     *
     * @param string $route CDSTAR service route
     * @param string $headers http headers
     * @param string $dest destination
     * @return array
     * @throws EnityTooLargeException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws StorageAuthException
     * @throws StorageBadConfigException
     * @throws StorageConnectionException
     * @throws StorageInvalidException
     * @throws StorageNotAvailableException
     * @throws StorageTimeOutException
     */
    public function fileDownload($route, $headers, $dest) {
        $fp = fopen(CDSTARHelper::PATH_TMP_DOWNLOAD . $dest, 'w');
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, CDSTARHelper::URL_CDSTAR . $route);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FILE, $fp);

        curl_exec($ch);

        if(curl_getinfo($ch)['http_code'] !== 200) {
            throw $this->getException(curl_getinfo($ch)['http_code']);
        }

        curl_close($ch);
        fclose($fp);

        return PHP_EOL;
    }

    /**
     * Executes a file upload from tmp dir or ownCloud
     * storage to CDSTAR archive
     *
     * @param string $route CDSTAR service route
     * @param string $headers http headers
     * @param string $file file path
     * @return array
     * @throws EnityTooLargeException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws StorageAuthException
     * @throws StorageBadConfigException
     * @throws StorageConnectionException
     * @throws StorageInvalidException
     * @throws StorageNotAvailableException
     * @throws StorageTimeOutException
     */
    public function fileUpload($route, $headers, $file) {
        $ch = curl_init();

        if($file instanceof \OCP\Files\Node) { // upload from owncloud storage
            $filename = $file->getName();
            $path = $file->getPath();
            $fullpath = realpath($path);
            $mimetype = $file->getMimetype();
            $filesize = $file->getSize();
            $contents = $file->getContent();
        } else { // upload from app temporary upload folder
            $filename = $file;
            $path = CDSTARHelper::PATH_TMP_UPLOAD . $filename;
            $fullpath = realpath($path);
            $mimetype = mime_content_type($fullpath);
            $filesize = filesize($path);
            $contents = fread(fopen($path, "r"), $filesize);
        }

        $headers[] = 'Content-Type: ' . $mimetype;

        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_URL, CDSTARHelper::URL_CDSTAR . $route);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $contents);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $output = curl_exec($ch);

        if(curl_getinfo($ch)['http_code'] !== 201) {
            throw $this->getException(curl_getinfo($ch)['http_code']);
        }

        $res = [
            'info' => curl_getinfo($ch),
            'output' => $output,
        ];

        curl_close($ch);

        return $res;
    }

    /**
     * Returns a list of all base archives (CDSTAR objects) with current user as owner
     *
     * @return array
     * @throws EnityTooLargeException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws StorageAuthException
     * @throws StorageBadConfigException
     * @throws StorageConnectionException
     * @throws StorageInvalidException
     * @throws StorageNotAvailableException
     * @throws StorageTimeOutException
     */
    public function listBaseArchives() {
        $operation = 'List base archives';

        $search_request = '{"query_string":{"query":"' . $this->username . '"}}';
        $headers = array_merge($this->getAuthHeader(), [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($search_request)
        ]);

        $res = $this->request('/search/', 'POST', $headers, $search_request);

        // parse response
        if($res['info']['http_code'] === 200) {
            $output = json_decode($res['output'], true);

            $count = 0;
            $objects = [];

            foreach($output['hits'] as $hit) {
                if($hit['type'] === 'object' &&
                    !$this->isObjectCollection($hit['uid']) &&
                    $this->isBaseArchive($hit['uid'])) {

                    $objects[] = ['uid' => $hit['uid']];
                    $count++;
                }
            }

            $this->logger->info($operation . ' of user ' . $this->username);

            return ['count' => $count, 'archives' => $objects];
        } else {
            $this->logger->error($operation . ' failed: CDSTAR request returned status code ' . $res['info']['http_code']);

            throw $this->getException($res['info']['http_code']);
        }
    }

    /**
     * Creates an archive (CDSTAR object) and returns its uid
     *
     * @return array
     * @throws EnityTooLargeException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws StorageAuthException
     * @throws StorageBadConfigException
     * @throws StorageConnectionException
     * @throws StorageInvalidException
     * @throws StorageNotAvailableException
     * @throws StorageTimeOutException
     */
    public function createArchive() {
        $operation = 'Create new archive';

        $headers = $this->getAuthHeader();

        $res = $this->request('/objects/', 'POST', $headers, null);

        // parse response
        if($res['info']['http_code'] === 201) {
            $output = json_decode($res['output'], true);

            if($output['ok']) {

                // create and set parent collection
                $collectionId = $this->createCollection();
                $this->setArchiveCollection($output['uid'], $collectionId);

                return ['uid' => $output['uid'], 'collection' => $collectionId];
            } else {
                $this->log('error', $operation . ' failed: CDSTAR request returned \'ok\' = false');

                throw new StorageInvalidException();
            }
        } else {
            $this->log('error', $operation . ' failed: CDSTAR request returned status code ' . $res['info']['http_code']);

            throw $this->getException($res['info']['http_code']);
        }
    }

    /**
     * Deletes an archive (CDSTAR object)
     *
     * @param string $uid uid of archive (CDSTAR object)
     * @throws EnityTooLargeException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws StorageAuthException
     * @throws StorageBadConfigException
     * @throws StorageConnectionException
     * @throws StorageInvalidException
     * @throws StorageNotAvailableException
     * @throws StorageTimeOutException
     */
    public function deleteArchive($uid) {
        $operation = 'Delete archive \'' . $uid . '\'';

        $headers = $this->getAuthHeader();

        $res = $this->request('/objects/' . $uid, 'DELETE', $headers, null);

        // parse response
        if($res['info']['http_code'] === 204) {
            $this->log('info', $operation);
        } else {
            $this->log('error', $operation . ' failed: CDSTAR request returned status code ' . $res['info']['http_code']);

            throw $this->getException($res['info']['http_code']);
        }
    }

    /**
     * Deletes all archives (CDSTAR objects)
     *
     * @return int number of deleted archives
     * @throws EnityTooLargeException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws StorageAuthException
     * @throws StorageBadConfigException
     * @throws StorageConnectionException
     * @throws StorageInvalidException
     * @throws StorageNotAvailableException
     * @throws StorageTimeOutException
     */
    public function deleteAllArchives() {
        $operation = 'Delete all archives';

        $search_request = '{"query_string":{"query":"' . $this->username . '"}}';
        $headers = array_merge($this->getAuthHeader(), [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($search_request)
        ]);

        $res = $this->request('/search/', 'POST', $headers, $search_request);

        // parse response
        if($res['info']['http_code'] === 200) {
            $output = json_decode($res['output'], true);

            $count = 0;

            foreach ($output['hits'] as $hit) {
                $this->deleteArchive($hit['uid']);
                $count++;
            }

            $this->log('info', $operation . ' of user ' . $this->username);

            return $count;
        } else {
            $this->log('error', $operation . ' failed: CDSTAR request returned status code ' . $res['info']['http_code']);

            throw $this->getException($res['info']['http_code']);
        }
    }

    /**
     * Returns the content of given archive (CDSTAR object)
     *
     * @param string $uid uid of archive (CDSTAR object)
     * @return array
     * @throws EnityTooLargeException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws StorageAuthException
     * @throws StorageBadConfigException
     * @throws StorageConnectionException
     * @throws StorageInvalidException
     * @throws StorageNotAvailableException
     * @throws StorageTimeOutException
     */
    public function getArchiveContent($uid) {
        $operation = 'Get archive content of \'' . $uid . '\'';

        $headers = $this->getAuthHeader();

        // load contained archives
        $parent = $this->getArchiveCollection($uid);
        $archives = [];

        if($parent !== null) {

            foreach($this->getArchivesInCollection($parent) as $archive) {
                $info = $this->getArchiveInfo($archive);

                $archives[] = [
                    'id' => $info['uid'],
                    'name' => $info['name'],
                    'contenttype' => "gwdg/archive",
                    'lastmodified' => $info['last-modified'],
                ];
            }
        }

        // load contained files
        $res = $this->request('/objects/' . $uid, 'GET', $headers, null);

        // parse response
        if($res['info']['http_code'] === 200) {
            $output = json_decode($res['output'], true);

            $bitstreams = [];

            foreach($output['bitstream'] as $bitstream) {
                $bitstreams[] = [
                    'id' => $bitstream['bitstreamid'],
                    'contenttype' => $bitstream['content-type'],
                    'filesize' => $bitstream['filesize'],
                    'created' => $bitstream['created'],
                    'lastmodified' => $bitstream['last-modified'],
                ];
            }

            $this->log('info', $operation);


            return ['archives' => $archives, 'files' => $bitstreams];
        } else {
            $this->log('error', $operation . ' failed: CDSTAR request returned status code ' . $res['info']['http_code']);

            throw $this->getException($res['info']['http_code']);
        }
    }

    /**
     * Deletes an file (CDSTAR bitstream)
     *
     * @param string $uid uid of archive (CDSTAR object)
     * @param string $id id of file (CDSTAR bitstream)
     * @throws EnityTooLargeException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws StorageAuthException
     * @throws StorageBadConfigException
     * @throws StorageConnectionException
     * @throws StorageInvalidException
     * @throws StorageNotAvailableException
     * @throws StorageTimeOutException
     */
    public function deleteFile($uid, $id) {
        $operation = 'Delete file \'' . $uid . '/' . $id . '\'';

        $headers = $this->getAuthHeader();

        $res = $this->request('/bitstreams/' . $uid . '/' . $id, 'DELETE', $headers, null);

        // parse response
        if($res['info']['http_code'] === 204) {
            $this->log('info', $operation);
        } else {
            $this->log('error', $operation . ' failed: CDSTAR request returned status code ' . $res['info']['http_code']);

            throw $this->getException($res['info']['http_code']);
        }
    }

    /**
     * Returns the uid of an archive
     *
     * @param string $path ownCloud path of archive
     * @return array
     * @throws NotFoundException
     * @throws StorageInvalidException
     */
    public function getArchiveUID($path) {
        $operation = 'Get archive uid of \'' . $path . '\'';

        $parts = explode("/", $path);

        if(sizeof($parts) < 1) {
            $this->log('error', $operation . ' failed: Path is too short');

            throw new StorageInvalidException(Http::STATUS_BAD_REQUEST);
        }

        $uids = [sizeof($parts)];
        $uids[0] = $parts[0]; // base archive

        for($i = 0; $i < sizeof($parts) - 1; $i++) {
            $collection = $this->getArchiveCollection($uids[$i]);
            $children = $this->getArchivesInCollection($collection);

            foreach($children as $child) {
                try {
                    $archive = $this->archiveMapper->getByObjectId($child);
                    $name = $archive->getName();
                    if($name === $parts[$i + 1]) {
                        $uids[$i + 1] = $archive->getObjectId();
                    }
                } catch(\Exception $e) {
                    $this->log('error', $operation . ' failed: Archive \'' . $child . '\' not found');

                    throw new NotFoundException(Http::STATUS_NOT_FOUND);
                }
            }

            if(!isset($uids[$i + 1])) {
                $this->log('error', $operation . ' failed: Archive \'' . $parts[$i + 1] . '\' is not a child of \'' . $parts[$i] . '\'');

                throw new StorageInvalidException(Http::STATUS_BAD_REQUEST);
            }
        }

        return ['path' => $path, 'uids' => join('/', $uids)];
    }

    /**
     * Sets the archive information of object
     *
     *
     * @param string $uid uid of archive (CDSTAR object)
     * @param string $name new name of archive
     * @return array
     * @throws NotFoundException
     */
    public function setArchiveInfo($uid, $name) {
        $operation = 'Set archive info of \'' . $uid . '\'';

        try {
            $archive = $this->archiveMapper->getByObjectId($uid);
            $exists = true;
        } catch(\Exception $e) {
            $archive = new Archive();
            $archive->setObjectId($uid);
            $exists = false;
        }

        $archive->setName($name);

        try {
            if($exists) {
                $this->archiveMapper->update($archive);

                $this->log('info', $operation);
            } else {
                $this->archiveMapper->insert($archive);

                $this->log('info', $operation . ': Archive not found => insert new object');
            }

            return ['uid' => $uid, 'name' => $name];
        } catch(\Exception $e) {
            $this->log('error', $operation . ' failed');

            throw new NotFoundException(Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * Returns information about an archive (CDSTAR object)
     *
     * @param string $uid uid of archive (CDSTAR object)
     * @return array
     * @throws EnityTooLargeException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws StorageAuthException
     * @throws StorageBadConfigException
     * @throws StorageConnectionException
     * @throws StorageInvalidException
     * @throws StorageNotAvailableException
     * @throws StorageTimeOutException
     */
    public function getArchiveInfo($uid) {
        $operation = 'Get archive info of \'' . $uid . '\'';

        // load info from database
        try {
            $archive = $this->archiveMapper->getByObjectId($uid);
            $name = $archive->getName();

            $this->log('info', $operation);
        } catch(\Exception $e) {
            $name = '';

            $this->log('info', $operation . ': Archive not found => use default values');
        }

        // load info from CDSTAR
        $headers = $this->getAuthHeader();
        $res = $this->request('/objects/' . $uid, 'GET', $headers, null);

        // parse response
        if($res['info']['http_code'] === 200) {
            $output = json_decode($res['output'], true);

            // save received info
            $revision = $output['revision'];
            $type = $output['type'];
            $created = $output['created'];
            $lastmodified = $output['last-modified'];
            $metadata = $output['metadata'];

            $this->log('info', $operation);

            return [
                'name' => $name,
                'uid' => $uid,
                'last-modified' => $lastmodified,
                'created' => $created,
                'metadata' => $metadata,
                'revision' => $revision
            ];
        } else {
            $this->log('error', $operation . ' failed: CDSTAR request returned status code ' . $res['info']['http_code']);

            throw $this->getException($res['info']['http_code']);
        }
    }

    /**
     * Returns information about a file (CDSTAR bitstream)
     *
     * @param string $uid uid of archive (CDSTAR object)
     * @param string $id id of file (CDSTAR bitstream)
     * @return array
     * @throws EnityTooLargeException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws StorageAuthException
     * @throws StorageBadConfigException
     * @throws StorageConnectionException
     * @throws StorageInvalidException
     * @throws StorageNotAvailableException
     * @throws StorageTimeOutException
     */
    public function getFileInfo($uid, $id) {
        $operation = 'Get file info of \'' . $uid . '/' . $id . '\'';

        $headers = $this->getAuthHeader();
        $res = $this->request('/bitstreams/' . $uid . '/' . $id, 'GET', $headers, null);

        // parse response
        if($res['info']['http_code'] === 200) {
            $output = json_decode($res['output'], true);

            // save received info
            $content_type = $output['content-type'];
            $filesize = $output['filesize'];
            $checksum = $output['checksum'];
            $lastmodified = $output['last-modified'];
            $created = $output['created'];

            $this->log('info', $operation);

            return [
                'id' => $id,
                'content-type' => $content_type,
                'filesize' => $filesize,
                'checksum' => $checksum,
                'last-modified' => $lastmodified,
                'created' => $created
            ];
        } else {
            $this->log('error', $operation . ' failed: CDSTAR request returned status code ' . $res['info']['http_code']);

            throw $this->getException($res['info']['http_code']);
        }
    }

    /**
     * Returns a list of all collections with current user as owner
     *
     * @return array
     * @throws EnityTooLargeException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws StorageAuthException
     * @throws StorageBadConfigException
     * @throws StorageConnectionException
     * @throws StorageInvalidException
     * @throws StorageNotAvailableException
     * @throws StorageTimeOutException
     */
    private function listCollections() {
        $operation = 'List collections';

        $search_request = '{"query_string":{"query":"' . $this->username . '"}}';
        $headers = array_merge($this->getAuthHeader(), [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($search_request)
        ]);

        $res = $this->request('/search/', 'POST', $headers, $search_request);

        // parse response
        if($res['info']['http_code'] === 200) {
            $output = json_decode($res['output'], true);

            $collections = [];

            foreach($output['hits'] as $hit) {
                if($hit['type'] === 'object' && $this->isObjectCollection($hit['uid'])) {
                    $collections[] = $hit['uid'];
                }
            }

            $this->log('info', $operation);

            return $collections;
        } else {
            $this->log('error', $operation . ' failed: CDSTAR request returned status code ' . $res['info']['http_code']);

            throw $this->getException($res['info']['http_code']);
        }
    }

    /**
     * Returns a boolean value if the given object is collection
     *
     * @param string $uid CDSTAR object uid
     * @return boolean
     * @throws EnityTooLargeException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws StorageAuthException
     * @throws StorageBadConfigException
     * @throws StorageConnectionException
     * @throws StorageInvalidException
     * @throws StorageNotAvailableException
     * @throws StorageTimeOutException
     */
    private function isObjectCollection($uid) {
        $operation = 'Check if object \'' . $uid . '\' is collection';

        $headers = $this->getAuthHeader();

        $res = $this->request('/objects/' . $uid, 'GET', $headers, null);

        // parse response
        if($res['info']['http_code'] === 200) {
            $output = json_decode($res['output'], true);

            $this->log('info', $operation . ' \'' . $output['uid'] . '\'');

            return ($output['type'] === 'COLLECTION');
        } else {
            $this->log('error', $operation . ' failed: CDSTAR request returned status code ' . $res['info']['http_code']);

            throw $this->getException($res['info']['http_code']);
        }
    }

    /**
     * Returns boolean value whether given archive is a base archive
     *
     * @param string $uid uid of archive (CDSTAR object)
     * @return bool archive is base archive
     */
    public function isBaseArchive($uid) {
        $operation = 'Check if object \'' . $uid . '\' is base archive';

        $parent = $this->getArchiveCollection($uid);

        // if there's no parent collection, treat like base archive
        if($parent === null) {
            return true;
        }

        $collections = $this->listCollections();

        $this->log('info', $operation);

        foreach($collections as $collection) {

            $objects = $this->getObjectsInCollection($collection);

            for($i = 1; $i < sizeof($objects); $i++) {
                if($objects[$i] === $parent) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Creates a new collection and returns its uid
     *
     * @return string
     * @throws EnityTooLargeException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws StorageAuthException
     * @throws StorageBadConfigException
     * @throws StorageConnectionException
     * @throws StorageInvalidException
     * @throws StorageNotAvailableException
     * @throws StorageTimeOutException
     */
    private function createCollection() {
        $operation = 'Create new collection';

        $headers = $this->getAuthHeader();

        $res = $this->request('/objects?type=collection', 'POST', $headers, null);

        // parse response
        if($res['info']['http_code'] === 201) {
            $output = json_decode($res['output'], true);

            if($output['ok']) {
                $this->log('info', $operation . ' \'' . $output['uid'] . '\'');

                return $output['uid'];
            } else {
                $this->log('error', $operation . ' failed: CDSTAR request returned \'ok\' = false');

                throw new StorageInvalidException();
            }
        } else {
            $this->log('error', $operation . ' failed: CDSTAR request returned status code ' . $res['info']['http_code']);

            throw $this->getException($res['info']['http_code']);
        }
    }

    /**
     * Returns child archive of given collection
     *
     * @param string $collectionId CDSTAR collection uid
     * @return string
     * @throws EnityTooLargeException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws StorageAuthException
     * @throws StorageBadConfigException
     * @throws StorageConnectionException
     * @throws StorageInvalidException
     * @throws StorageNotAvailableException
     * @throws StorageTimeOutException
     */
    public function getCollectionArchive($collectionId) {
        $operation = 'Get child archive of collection \'' . $collectionId . '\'';

        $objects = $this->getObjectsInCollection($collectionId);

        if($objects !== null) {
            $this->log('info', $operation);

            return $objects[0];
        }

        $this->log('error', $operation . ' failed');

        throw new NotFoundException(HTTP::STATUS_NOT_FOUND);
    }

    /**
     * Returns the parent collection of given archive
     *
     * @param string $uid archive (CDSTAR object) id
     * @return string
     * @throws NotFoundException
     */
    public function getArchiveCollection($uid) {
        $operation = 'Get parent collection of archive \'' . $uid . '\'';

        $collections = $this->listCollections();
        foreach($collections as $collection) {
            if($this->getCollectionArchive($collection) === $uid) {
                $this->log('info', $operation);

                return $collection;
            }
        }

        $this->log('error', $operation . ' failed: Cannot find correct collection');

        throw new NotFoundException(HTTP::STATUS_NOT_FOUND);
    }

    /**
     * Sets the child archive of collection and parent collection of archive.
     *
     * @param string $uid archive (CDSTAR object) uid
     * @param string $collectionId collection uid
     * @throws EnityTooLargeException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws StorageAuthException
     * @throws StorageBadConfigException
     * @throws StorageConnectionException
     * @throws StorageInvalidException
     * @throws StorageNotAvailableException
     * @throws StorageTimeOutException
     * @throws InvalidContentException
     */
    public function setArchiveCollection($uid, $collectionId) {
        $operation = 'Set collection \'' . $collectionId . '\' of archive \'' . $uid . '\'';

        $headers = $this->getAuthHeader();

        // check if collection is empty
        $res = $this->request('/objects/' . $collectionId, 'GET', $headers, null);
        if($res['info']['http_code'] === 200) {
            $output = json_decode($res['output'], true);

            $empty = empty($output['collection']);
        } else {
            $this->log('error', $operation . ' failed: Unable to check if collection is empty. CDSTAR request returned status code ' . $res['info']['http_code']);

            throw $this->getException($res['info']['http_code']);
        }
        if(!$empty) {
            $this->log('error', $operation . ' failed: Collection is not empty');

            throw new InvalidContentException();
        }

        // update CDSTAR collection
        $this->setObjectsInCollection($collectionId, [$uid]);

        $this->log('info', $operation);
    }

    /**
     * Updates the containing objects in given collection
     *
     * @param string $collectionId collection uid
     * @param array $collections array of archives
     * @throws EnityTooLargeException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws StorageAuthException
     * @throws StorageBadConfigException
     * @throws StorageConnectionException
     * @throws StorageInvalidException
     * @throws StorageNotAvailableException
     * @throws StorageTimeOutException
     */
    private function setObjectsInCollection($collectionId, $collections) {
        $operation = 'Set objects in collection \'' . $collectionId . '\'';

        $json_data = json_encode($collections);
        $headers = array_merge($this->getAuthHeader(), [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_data)
        ]);

        $res = $this->request('/collections/' . $collectionId, 'PUT', $headers, $json_data);
        if($res['info']['http_code'] === 201) {
            $this->log('info', $operation);
        } else {
            $this->log('error', $operation . ' failed: CDSTAR request returned status code ' . $res['info']['http_code']);

            throw $this->getException($res['info']['http_code']);
        }
    }

    /**
     * Returns the containing objects in given collection
     *
     * @param string $collectionId collection uid
     * @return array
     * @throws EnityTooLargeException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws StorageAuthException
     * @throws StorageBadConfigException
     * @throws StorageConnectionException
     * @throws StorageInvalidException
     * @throws StorageNotAvailableException
     * @throws StorageTimeOutException
     */
    private function getObjectsInCollection($collectionId) {
        $operation = 'Get archives in collection \'' . $collectionId . '\'';

        $headers = $this->getAuthHeader();

        $res = $this->request('/collections/' . $collectionId, 'GET', $headers, null);
        if($res['info']['http_code'] === 200) {
            $this->log('info', $operation);

            return json_decode($res['output'], true);
        } else {
            $this->log('error', $operation . ' failed: CDSTAR request returned status code ' . $res['info']['http_code']);

            throw $this->getException($res['info']['http_code']);
        }
    }

    /**
     * Adds a collection to a collection
     *
     * @param string $collectionId collection to add
     * @param string $parentId parent collection
     * @throws EnityTooLargeException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws StorageAuthException
     * @throws StorageBadConfigException
     * @throws StorageConnectionException
     * @throws StorageInvalidException
     * @throws StorageNotAvailableException
     * @throws StorageTimeOutException
     */
    public function addCollectionToCollection($collectionId, $parentId) {
        $operation = 'Add parent collection of archive \''  .$collectionId . '\' to collection \'' . $parentId . '\'';

        $objects = $this->getObjectsInCollection($parentId);
        if($objects !== null) {
            $objects[] = $collectionId;

            $this->setObjectsInCollection($parentId, $objects);

            $this->log('info', $operation);
        } else {
            $this->log('error', $operation . ' failed: Unable to get archives in collection');

            throw new StorageConnectionException();
        }
    }

    /**
     * Removes a collection from a collection
     *
     * @param string $collectionId collection to remove
     * @param string $parentId parent collection
     * @throws EnityTooLargeException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws StorageAuthException
     * @throws StorageBadConfigException
     * @throws StorageConnectionException
     * @throws StorageInvalidException
     * @throws StorageNotAvailableException
     * @throws StorageTimeOutException
     */
    public function removeCollectionFromCollection($collectionId, $parentId) {
        $operation = 'Remove parent collection \'' . $collectionId . '\' from collection \'' . $parentId . '\'';

        $objects = $this->getObjectsInCollection($parentId);
        if($objects !== null) {
            $objects = array_diff($objects, [$collectionId]);

            $this->setObjectsInCollection($parentId, $objects);

            $this->log('info', $operation);
        } else {
            $this->log('error', $operation . ' failed: Unable to get archives in collection');

            throw new StorageConnectionException();
        }
    }

    /**
     * Returns in collection contained archives
     *
     * @param string $collectionId collection uid
     * @return array
     * @throws EnityTooLargeException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws StorageAuthException
     * @throws StorageBadConfigException
     * @throws StorageConnectionException
     * @throws StorageInvalidException
     * @throws StorageNotAvailableException
     * @throws StorageTimeOutException
     */
    public function getArchivesInCollection($collectionId) {
        $operation = 'Get archives in collection \'' . $collectionId . '\'';

        $archives = [];
        $objects = $this->getObjectsInCollection($collectionId);

        if($objects !== null) {
            for($i = 1; $i < sizeof($objects); $i++) {
                $archives[] = ($this->isObjectCollection($objects[$i])) ? $this->getCollectionArchive($objects[$i]) : $objects[$i];
            }

            $this->log('info', $operation);

            return $archives;
        }

        $this->log('error', $operation . ' failed');

        throw new NotFoundException(HTTP::STATUS_NOT_FOUND);
    }
}