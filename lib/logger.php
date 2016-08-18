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

use OCP\ILogger;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

/**
 * Class Logger contains necessary functions for logging
 *
 * @package OCA\Files_Archiving\Lib
 */
class Logger {

    /**
     * @var string
     */
    public $appName;

    /**
     * @var ILogger
     */
    protected $logger;

    /**
     * @var IRequest
     */
    protected $request;

    /**
     * @var IUserSession
     */
    protected $session;

    public function __construct($AppName, ILogger $Logger, IRequest $Request, IUserSession $Session) {
        $this->appName = $AppName;
        $this->logger = $Logger;
        $this->request = $Request;
        $this->session = $Session;
    }

    /**
     * @return string
     */
    public function getUserOrIp() {
        $user = $this->session->getUser();
        if ($user instanceof IUser) {
            return 'user ' . $user->getUID();
        } else {
            return 'IP ' . $this->request->getRemoteAddress();
        }
    }

    /**
     * Logs the given message
     *
     * @param string $type logging type
     * @param string $message log message
     */
    private function log($type, $message) {
        $message .= ' [ACTOR: {actor}]';

        if (\OC::$CLI) {
            $message .= ' [CLI]';
        } else {
            $message .= ' [CLIENT_IP: {ip}]';
            $message .= ' [USER_AGENT: {ua}]';
        }

        $userAgent = $this->request->getHeader('USER_AGENT');
        if(is_null($userAgent)) {
            $userAgent = 'unknown';
        }

        $params = [
            'ip'	=> $this->request->getRemoteAddress(),
            'ua'	=> $userAgent,
            'actor'	=> $this->getUserOrIp(),
            'app'	=> $this->appName,
        ];

        switch($type) {
            case 'emergency':
                $this->logger->emergency($message, $params);
                break;
            case 'alert':
                $this->logger->alert($message, $params);
                break;
            case 'critical':
                $this->logger->critical($message, $params);
                break;
            case 'error':
                $this->logger->error($message, $params);
                break;
            case 'warning':
                $this->logger->warning($message, $params);
                break;
            case 'notice':
                $this->logger->notice($message, $params);
                break;
            case 'info':
                $this->logger->info($message, $params);
                break;
            case 'debug':
                $this->logger->debug($message, $params);
                break;
        }
    }

    public function emergency($message) {
        $this->log('emergency', $message);
    }

    public function alert($message) {
        $this->log('alert', $message);
    }

    public function critical($message) {
        $this->log('critical', $message);
    }

    public function error($message) {
        $this->log('error', $message);
    }

    public function warning($message) {
        $this->log('warning', $message);
    }

    public function notice($message) {
        $this->log('notice', $message);
    }

    public function info($message) {
        $this->log('info', $message);
    }

    public function debug($message) {
        $this->log('debug', $message);
    }
}