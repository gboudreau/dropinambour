<?php

namespace PommePause\Dropinambour {
    class Config {
        public static $email = 'admin@example.com';
        public static function get($key) { return self::$email; }
    }
    class Plex {
        public static $user;
        public static $isAdmin = FALSE;
        public static function getUserInfos() { return self::$user; }
        public static function isServerAdmin() { return self::$isAdmin; }
    }
    class Mailer {
        public static $sent = [];
        public static function sendFromTemplate(...$args) { self::$sent[] = $args; }
    }
}

namespace {
    use PommePause\Dropinambour\ActiveRecord\Request;
    use PommePause\Dropinambour\Config;
    use PommePause\Dropinambour\Mailer;
    use PommePause\Dropinambour\Plex;

    require __DIR__ . '/../classes/ActiveRecord/AbstractActiveRecord.php';
    require __DIR__ . '/../classes/ActiveRecord/Request.php';

    $request = new Request();
    $request->type = 'movie';
    $request->title = 'Example';
    $request->requested_by = 'other <other@example.com>';

    Plex::$user = (object) ['email' => 'ADMIN@example.com'];
    $request->notifyAdminRequestAdded();
    if (count(Mailer::$sent) !== 0) {
        throw new \RuntimeException('Admin should not be notified of their own request');
    }

    Config::$email = 'Admin <admin@example.com>';
    $request->notifyAdminRequestAdded();
    if (count(Mailer::$sent) !== 0) {
        throw new \RuntimeException('Named admin address should not receive its own request');
    }

    Plex::$user = (object) ['email' => 'plex-admin@example.com'];
    Plex::$isAdmin = TRUE;
    $request->notifyAdminRequestAdded();
    if (count(Mailer::$sent) !== 0) {
        throw new \RuntimeException('Plex admin should not be notified even with a different email');
    }

    Plex::$isAdmin = FALSE;
    Plex::$user = (object) ['email' => 'other@example.com'];
    $request->notifyAdminRequestAdded();
    if (count(Mailer::$sent) !== 1 || Mailer::$sent[0][0] !== Config::$email) {
        throw new \RuntimeException('Admin should receive requests from other users');
    }

    Plex::$user = NULL;
    $request->notifyAdminRequestAdded();
    if (count(Mailer::$sent) !== 2) {
        throw new \RuntimeException('Unknown user should not suppress the notification');
    }
    print "Request notification tests passed\n";
}
