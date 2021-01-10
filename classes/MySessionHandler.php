<?php

namespace PommePause\Dropinambour;

use Delight\Cookie\Cookie;
use Delight\Cookie\Session;

/**
 * Class MySessionHandler
 *
 * Will save the session in the DB.
 *
 * @category  Persistence
 */
class MySessionHandler
{
    public function __construct() {
        session_set_save_handler(
            [$this, 'open'],
            [$this, 'close'],
            [$this, 'read'],
            [$this, 'write'],
            [$this, 'destroy'],
            [$this, 'clean']
        );

        // Enable HttpOnly (and Secure, if using HTTPS), and don't use a wildcard for the domain
        $params = session_get_cookie_params();
        if (!empty($_SERVER['HTTP_HOST'])) {
            $domain = explode(':', $_SERVER['HTTP_HOST'])[0];
        } else {
            $domain = $params['domain'];
        }

        // Session lifetime : 7 days
        ini_set('session.gc_maxlifetime', 7*24*60*60);

        session_set_cookie_params(0, $params['path'], $domain, is_https(), TRUE);

        // Enable SameSite=Lax to prevent CSRF on supported browsers
        Session::start(Cookie::SAME_SITE_RESTRICTION_LAX);
    }

    public function open() {
        return TRUE;
    }

    public function close() {
        return TRUE;
    }

    public function read($id) {
        $q = "SELECT data FROM sessions WHERE id = :session_id";
        $data = DB::getFirstValue($q, $id);
        if ($data) {
            return $data;
        }
        return '';
    }

    public function write($id, $data) {
        $result = static::save($id, $data);
        return ( $result !== FALSE );
    }

    public function destroy($id) {
        $q = "DELETE FROM sessions WHERE id = :session_id";
        $result = DB::execute($q, $id);
        return ( $result !== FALSE );
    }

    public function clean($max) {
        $q = "SELECT id FROM sessions WHERE access_time < :access_time";
        $expired_sessions = DB::getAllValues($q, time() - intval($max));
        foreach ($expired_sessions as $id) {
            $this->destroy($id);
        }
        return TRUE;
    }

    public static function flush() {
        $id = session_id();
        $data = session_encode();
        static::save($id, $data);
    }

    private static function save($id, $data) {
        $q = "REPLACE INTO sessions VALUES (:id, :data, :access_time)";
        $result = DB::execute(
            $q,
            [
                'id' => $id,
                'data' => $data,
                'access_time' => time(),
            ]
        );
        return $result;
    }
}
