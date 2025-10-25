<?php

namespace PommePause;

use stdClass;

define('DEBUGSQL', FALSE);

$CONFIG = new stdClass();

$CONFIG->DB_ENGINE = 'mysql';
$CONFIG->DB_HOST   = '172.17.0.1';
$CONFIG->DB_PORT   =  3306;
$CONFIG->DB_USER   = 'dinb_user';
$CONFIG->DB_PWD    = ''; // Choose something random!
$CONFIG->DB_NAME   = 'dinb';

$CONFIG->SYSTEM_TIMEZONE = 'America/New_York'; // PHP format; ref: http://php.net/manual/en/timezones.php
// If your DB is already using the same timezone as the above, no need to set a MYSQL_TIMEZONE
// $CONFIG->MYSQL_TIMEZONE  = 'America/New_York'; // MySQL format; ref: SELECT tzn.Name, GROUP_CONCAT(DISTINCT t.Abbreviation) AS abbreviations FROM mysql.time_zone_name tzn LEFT JOIN mysql.time_zone_transition_type t ON (t.Time_zone_id = tzn.Time_zone_id) GROUP BY tzn.Time_zone_id

// Your Plex server
$CONFIG->PLEX_BASE_URL = 'http://172.17.0.1:32400';

// Only Radarr v3 is supported
$CONFIG->RADARR_API_URL = 'http://192.168.155.88:7878/api/v3';
$CONFIG->RADARR_API_KEY = '3a0000000000000000000000000000c8';
// Link used in email notifications:
$CONFIG->RADARR_BASE_URL = 'http://192.168.155.88:7878';

// Only Sonarr v4 is supported
$CONFIG->SONARR_API_URL = 'http://192.168.155.88:8989/api/v3';
$CONFIG->SONARR_API_KEY = 'd1000000000000000000000000000044';
// Link used in email notifications:
$CONFIG->SONARR_BASE_URL = 'http://192.168.155.88:8989';

// Used as available option for search
$CONFIG->LANGUAGES = ['en', 'fr'];

// Brevo API key to send emails
$CONFIG->BREVO_API_KEY            = 'xkeysib-...';

$CONFIG->EMAIL_NOTIF_FROM_NAME    = 'dropinambour';
$CONFIG->EMAIL_NOTIF_FROM_ADDRESS = 'admin@something.com';

// When new requests are made, this email will receive notifications
$CONFIG->NEW_REQUESTS_NOTIF_EMAIL = 'admin@something.com';

// Make sure this file is writable by the user running the web server
$CONFIG->LOG_FILE = '/var/log/dropinambour.log';
$CONFIG->LOG_LEVEL = 'INFO'; // One of: DEBUG, INFO, WARN, ERROR

// URL to access your dropinambour install remotely; used in the newsletter
$CONFIG->BASE_URL = 'https://drop.in.nam.bour-url.you';

// The name of your server; used in the newsletter "[name here] newsletter"
$CONFIG->PLEX_SERVER_NAME = "Guillaume's Plex";

// Subscribe to TheTVDB to get a PIN: https://thetvdb.com/dashboard/account/subscription
$CONFIG->THETVDB_SUBSCRIPTION_PIN = '12AB3CDE';

// (Optional) Used when a remove host is protected by Cloudflare
//$CONFIG->FLARESOLVERR_URL = 'http://127.0.0.1:8191/v1';
