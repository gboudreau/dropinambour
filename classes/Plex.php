<?php

namespace PommePause\Dropinambour;

use Exception;
use PommePause\Dropinambour\Exceptions\PlexException;
use stdClass;

class Plex
{

    // Ref: https://forums.plex.tv/t/authenticating-with-plex/609370
    // Ref: https://github.com/Arcanemagus/plex-api/wiki
    // Ref: https://github.com/pkkid/python-plexapi/blob/master/plexapi/library.py

    public const SECTION_RECENTLY_ADDED = -1;

    public static function getSections(bool $list_all_sections = FALSE) : array {
        $enabled_sections = Config::getFromDB('PLEX_SECTIONS', [], Config::GET_OPT_PARSE_AS_JSON);
        $response = static::sendGET("/library/sections");
        $sections = [];
        foreach ($response->Directory as $d) {
            if (!$list_all_sections && !array_contains($enabled_sections, $d->key)) {
                continue;
            }
            if ($d->type != 'show' && $d->type != 'movie') {
                continue;
            }
            if ($d->language == 'xn') {
                continue;
            }
            $sections[] = (object) [
                'id' => (int) $d->key,
                'title' => $d->title,
                'type' => $d->type,
                'agent' => $d->agent,
                'language' => $d->language,
            ];
        }
        return $sections;
    }

    public static function searchSection(int $section_id, ?string $title = NULL, ?string $sort = NULL) : array {
        $args = [];
        if (!empty($title)) {
            $args['title'] = $title;
        }
        if (!empty($sort)) {
            $args['sort'] = $sort;
        }
        $response = static::sendGET("/library/sections/$section_id/all?" . http_build_query($args));
        $results = [];
        foreach ($response->Metadata as $md) {
            $results[] = (object) [
                'type' => $md->type,
                'title' => $md->title,
                'year' => !empty($md->year) ? (int) $md->year : NULL,
                'key' => $md->key,
                'parentTitle' => @$md->parentTitle,
                'parentKey' => @$md->parentKey,
                'section' => (object) [
                    'id' => $response->librarySectionID,
                    'name' => $response->librarySectionTitle,
                ],
                'guid' => $md->guid,
                'addedAt' => $md->addedAt,
            ];
        }
        return $results;
    }

    public static function getSectionItems($section_id) : array {
        $items = [];
        $results = static::searchSection($section_id, NULL, "addedAt:desc");
        Logger::info("Plex section $section_id contains " . count($results) . " items.");
        foreach ($results as $result) {
            $items[] = $result;
        }
        return $items;
    }

    public static function getAllSectionsItems() : array {
        $items = [];
        foreach (static::getSections() as $section) {
            $results = static::getSectionItems($section->id);
            $items = array_merge($items, $results);
        }
        return $items;
    }

    /**
     * @param string $item_key     Key of the metadata to fetch.
     * @param bool   $return_array Return an array when TRUE, otherwise return an object.
     *
     * @return stdClass|array|null
     */
    public static function getItemMetadata(string $item_key, bool $return_array = FALSE) : stdClass|array|NULL {
        try {
            $response = static::sendGET($item_key);
        } catch (Exception $ex) {
            Logger::warning("Failed to get Plex Metadata for item $item_key: " . $ex->getMessage());
            return NULL;
        }
        if ($return_array) {
            return $response->Metadata;
        } else {
            return first($response->Metadata);
        }
    }

    public static function getRecentlyAddedItems() : array {
        $items = [];

        $response = static::sendGET("/library/recentlyAdded");
        foreach ($response->Metadata as $md) {
            $items[] = (object) [
                'type' => $md->type,
                'title' => $md->title,
                'year' => !empty($md->year) ? (int) $md->year : NULL,
                'ratingKey' => $md->ratingKey,
                'key' => $md->key,
                'parentTitle' => @$md->parentTitle,
                'parentKey' => @$md->parentKey,
                'section' => (object) [
                    'id' => $md->librarySectionID,
                    'name' => $md->librarySectionTitle,
                ],
                'guid' => $md->guid,
                'addedAt' => $md->addedAt,
            ];
        }

        return $items;
    }

    public const HUB_TYPE_MOVIES = 1;
    public const HUB_TYPE_TVSHOWS = 2;
    public static function getRecentlyAddedHubItems(int $type) : array {
        $items = [];

        $response = static::sendGET("/hubs/home/recentlyAdded?type=$type");
        foreach ($response->Metadata as $md) {
            $items[] = (object) [
                'type' => $md->type,
                'title' => $md->title,
                'year' => !empty($md->year) ? (int) $md->year : NULL,
                'ratingKey' => $md->ratingKey,
                'key' => $md->key,
                'parentTitle' => @$md->parentTitle,
                'parentKey' => @$md->parentKey,
                'section' => (object) [
                    'id' => $md->librarySectionID,
                    'name' => $md->librarySectionTitle,
                ],
                'guid' => $md->guid,
                'addedAt' => $md->addedAt,
            ];
        }

        return $items;
    }

    public static function getSectionNameById($section_id) : ?string {
        $q = "SELECT name FROM sections WHERE id = :id";
        $name = DB::getFirstValue($q, $section_id, DB::GET_OPT_CACHED);
        if (empty($name)) {
            $name = NULL;
        }
        return NULL;
    }

    public static function getSharedUsers() : array {
        $token = Config::getFromDB('PLEX_ACCESS_TOKEN');
        if (empty($token)) {
            return [];
        }

        $this_server_id = Plex::getServerId();

        // Is not available as JSON :|
        $response = static::sendGET('/users', 'https://plex.tv/api', 0, $token, FALSE);
        $response = simplexml_load_string($response);
        $users = [];
        foreach ($response->User as $user) {
            // Is this user allowed to use this Plex server?
            $allowed_on_server = FALSE;
            foreach ($user->Server as $server) {
                if ($server->attributes()['machineIdentifier'] == $this_server_id) {
                    $allowed_on_server = TRUE;
                    break;
                }
            }
            if (!$allowed_on_server) {
                continue;
            }
            $user = (object) [
                'id' => (int) $user->attributes()['id'],
                'username' => (string) $user->attributes()['username'],
                'email' => (string) $user->attributes()['email'],
                'avatar' => (string) $user->attributes()['thumb'],
            ];
            $users[] = $user;
        }

        // Return admin user too!
        $user_token = $_SESSION['PLEX_ACCESS_TOKEN'];
        $_SESSION['PLEX_ACCESS_TOKEN'] = $token;
        $users[] = static::getUserInfos();
        $_SESSION['PLEX_ACCESS_TOKEN'] = $user_token;

        return $users;
    }

    public static function needsAuth() : bool {
        return ( static::getAccessToken() === NULL );
    }

    public static function getAuthURL() : string {
        // Generate PIN
        $data = [
            'strong' => 'true',
            'X-Plex-Product' => static::getAppName(),
            'X-Plex-Client-Identifier' => static::getClientID(),
        ];
        $response = sendPOST('https://plex.tv/api/v2/pins', $data, ["Accept: application/json"]);
        $response = json_decode($response);
        $_SESSION['PLEX_PIN'] = $response;

        // Build Auth URL
        $data = [
            'clientID'                     => static::getClientID(),
            'code'                         => $response->code,
            'context[device][product]'     => static::getAppName(),
            'context[device][environment]' => 'bundled',
            'context[device][layout]'      => 'desktop',
            'context[device][platform]'    => 'Web',
        ];
        return "https://app.plex.tv/auth#?" . http_build_query($data);
    }

    public static function checkAuthPIN() : bool {
        $pin = @$_SESSION['PLEX_PIN'];
        if (empty($pin)) {
            return FALSE;
        }

        try {
            $response = static::sendGET("/v2/pins/$pin->id?code=$pin->code", 'https://plex.tv/api', 0, static::ACCESS_TOKEN_SKIP);
        } catch (Exception $ex) {
            unset($_SESSION['PLEX_PIN']);
            return FALSE;
        }

        if (!empty($response->authToken)) {
            unset($_SESSION['PLEX_PIN']);

            $_SESSION['PLEX_ACCESS_TOKEN'] = $response->authToken;
            $user = Plex::getUserInfos();

            $allowed_users = Plex::getSharedUsers();
            $allowed_to_login = FALSE;
            if (empty($allowed_users)) {
                // First login; always allow
                $allowed_to_login = TRUE;
            }
            foreach ($allowed_users as $allowed_user) {
                if ($allowed_user->id == $user->id) {
                    $allowed_to_login = TRUE;
                    break;
                }
            }
            if (!$allowed_to_login) {
                unset($_SESSION['PLEX_ACCESS_TOKEN']);
                Logger::warning("Unauthorized user tried to login: $user->username (ID $user->id, $user->email)");
                return FALSE;
            }

            return TRUE;
        }
        return FALSE;
    }

    public static function getUserInfos() : ?stdClass {
        $token = static::getAccessToken($user_infos);
        if (!empty($token)) {
            return $user_infos;
        }
        return NULL;
    }

    public static function isServerAdmin() : bool {
        $this_server_id = Plex::getServerId();
        foreach (static::getServers() as $server) {
            if ($server->machineIdentifier == $this_server_id && $server->owned) {
                return TRUE;
            }
        }
        return FALSE;
    }

    public static function geUrlForMediaKey(string $key) : string {
        $server_uuid = Plex::getServerId();
        foreach (static::getServers() as $server) {
            if ($server->machineIdentifier == $server_uuid) {
                if (empty($server->address) || empty($server->port)) {
                    // Remote Access is disabled
                    $lan_address = first(explode(' ', $server->localAddresses));
                    return "http://$lan_address:32400/web/index.html#!/server/$server_uuid/details?key=" . urlencode($key);
                }
                return "https://app.plex.tv/desktop#!/server/$server_uuid/details?key=" . urlencode($key);
            }
        }
        return '#NA';
    }

    public static function getUrlForSection(int $section, string $type) : string {
        $server_uuid = Plex::getServerId();
        foreach (static::getServers() as $server) {
            if ($server->machineIdentifier == $server_uuid) {
                if (empty($server->address) || empty($server->port)) {
                    // Remote Access is disabled
                    $lan_address = first(explode(' ', $server->localAddresses));
                    return "http://$lan_address:32400/web/index.html#!/server/$server_uuid/details?key=" . urlencode($key);
                }
                if ($type == 'movie') {
                    return "https://app.plex.tv/desktop#!/media/$server_uuid/com.plexapp.plugins.library?source=$section&filters=unwatched%3D1&sort=addedAt%3Adesc&pageType=list&key=%2Flibrary%2Fsections%2F$section%2Fall%3Ftype%3D1&context=content.library";
                }
                return "https://app.plex.tv/desktop#!/media/$server_uuid/com.plexapp.plugins.library?source=$section&filters=unwatchedLeaves%3D1&sort=episode.addedAt%3Adesc&limit=&pageType=list&key=%2Flibrary%2Fsections%2F$section%2Fall%3Ftype%3D2&context=content.library";
            }
        }
        return '#NA';
    }

    public static function getDevices() {
        return static::sendGET('/devices.json', 'https://plex.tv');
    }

    public static function getServers(bool $include_local_only = TRUE) {
        // Is not available as JSON :|
        $response = static::sendGET('/pms/servers.xml' . ($include_local_only ? '?includeLite=1' : ''), 'https://plex.tv', 30*60, NULL, FALSE);
        $response = simplexml_load_string($response);
        $servers = [];
        foreach ($response->Server as $server) {
            $s = [];
            foreach ($server->attributes() as $prop => $value) {
                $value = (string) $value;
                if (is_numeric($value)) {
                    if (is_float($value+0)) {
                        $s[$prop] = (float) $value;
                    } else {
                        $s[$prop] = (int) $value;
                    }
                } else {
                    $s[$prop] = (string) $value;
                }
            }
            $servers[] = (object) $s;
        }
        return $servers;
    }

    public static function updateRecentlyAddedSeasonsTitles() : void {
        $items = array_merge(Plex::getRecentlyAddedItems(), Plex::getRecentlyAddedHubItems(Plex::HUB_TYPE_TVSHOWS));
        foreach ($items as $item) {
            if (string_begins_with($item->guid, 'plex://season/')) {
                $plex_season = Plex::getItemMetadata(str_replace('/children', '', $item->key));
            } elseif (string_begins_with($item->guid, 'plex://episode/')) {
                $plex_season = Plex::getItemMetadata($item->parentKey);
            } else {
                // Movie or ...
                continue;
            }

            if (empty($plex_season->parentKey)) {
                continue;
            }

            $show_details = Plex::getItemMetadata($plex_season->parentKey);
            $season_number = $plex_season->index;
            $num_plex_episodes = $plex_season->leafCount;

            if ($show_details->title == 'Bébéatrice') {
                continue;
            }

            Logger::info("  - Season $season_number of $show_details->title currently has $num_plex_episodes episodes in Plex. Season title: $plex_season->title");

            $tvdb_id = FALSE;
            $tmdbtv_id = FALSE;
            foreach ($show_details->Guid ?? [] as $guid) {
                if (string_begins_with($guid->id, 'tmdb://')) {
                    $tmdbtv_id = (int) substr($guid->id, 7);
                    break;
                }
                if (string_begins_with($guid->id, 'tvdb://')) {
                    $tvdb_id = (int) substr($guid->id, 7);
                }
            }
            if (!$tmdbtv_id) {
                $tmdbtv_id = TMDB::getIDByExternalId($tvdb_id, 'tvdb', $show_details->title);
                if (!$tmdbtv_id) {
                    continue;
                }
            }

            $season_details = TMDB::getDetailsTVSeason($tmdbtv_id, $season_number);
            if (!$season_details) {
                Logger::info("    Failed to load TV show season details on TMDB, for TV ID $tmdbtv_id, season $season_number. Skipping.");
                continue;
            }
            $num_total_eps = count($season_details->episodes ?? []);
            if ($num_total_eps == 0) {
                Logger::info("    TMDB says: season $season_number has $num_total_eps episodes. Skipping.");
                continue;
            }
            $last_ep = last($season_details->episodes);
            $last_ep_date = $last_ep->air_date;

            if ($num_plex_episodes < $num_total_eps || $num_plex_episodes > $num_total_eps) {
                // TMDB is crap for future episodes; it never lists episodes with TBA title or date
                // So let's use TheTVDB to count episodes (when possible)
                if (empty($tvdb_id)) {
                    $q = "SELECT tvdb_id FROM tmdb_external_ids WHERE tmdbtv_id = :id";
                    $tvdb_id = DB::getFirstValue($q, $tmdbtv_id);
                }
                if (!empty($tvdb_id)) {
                    $season_episodes = TheTVDB::getEpisodesForSeason($tvdb_id, $season_number);
                    if (!empty($season_episodes)) {
                        $num_total_eps = count($season_episodes);
                        $last_ep = last($season_episodes);
                        $last_ep_date = $last_ep->aired;
                    }
                }
            }

            Logger::info("    TMDB says: season $season_number has $num_total_eps episodes; ends on $last_ep_date.");

            if ($season_number === 0) {
                $modified_season_name = $season_details->name;
            } else {
                $modified_season_name = "Season $season_number";
                if ($season_details->name != $modified_season_name) {
                    $modified_season_name = str_ireplace([' one', ' two', ' three', ' four', ' five', ' six', ' seven', ' eight', ' nine', ' ten'], [' 1', ' 2', ' 3', ' 4', ' 5', ' 6', ' 7', ' 8', ' 9', ' 10'], $season_details->name);
                    if ($season_details->name != $modified_season_name) {
                        $modified_season_name = sprintf("S%02d %s", $season_number, $season_details->name);
                    }
                }
                if (strtotime($last_ep_date) > strtotime('-6 months') || empty($last_ep_date)) {
                    $end_when = FALSE;
                    if ($num_plex_episodes >= $num_total_eps) {
                        $end_when = 'Ended';
                    } elseif (empty($last_ep_date)) {
                        $end_when = 'Ends ...';
                    } elseif (strtotime($last_ep_date) > strtotime('-1 year')) {
                        $end_when = 'Ends ' . date('M-j', strtotime($last_ep_date));
                    }
                    if ($end_when) {
                        $modified_season_name .= " - $num_total_eps Eps - $end_when";
                    }
                }
            }

            Logger::info("    New season name: $modified_season_name");

            if ($modified_season_name != $plex_season->title) {
                Logger::info("    Updating season name in Plex.");
                Plex::setSeasonTitle($item->section->id, $plex_season->ratingKey, $modified_season_name);
            }
        }
    }

    public static function setSeasonTitle(int $section, int $season_media_id, string $new_season_title) {
        $data = [
            'type' => 3, // ?
            'id' => $season_media_id,
            'includeExternalMedia' => 1,
            'title.value' => $new_season_title,
            'title.locked' => 1,
        ];
        return static::sendPOST("/library/sections/$section/all", $data, 'PUT');
    }

    private static function getServerId() : string {
        $response = static::sendGET("/identity", NULL, 5*60);
        return $response->machineIdentifier;
    }

    private static function getAppName() : string {
        return "dropinambour - Requests for Plex";
    }

    private static function getBaseURL() : string {
        return Config::get('PLEX_BASE_URL');
    }

    private static string $_client_id;
    private static function getClientID() : string {
        if (empty(static::$_client_id)) {
            static::$_client_id = (string) Config::getFromDB('PLEX_CLIENT_ID');
            if (empty($client_id)) {
                static::$_client_id = 'DINB-' . trim(sendGET('https://ip.danslereseau.com')) . '-' . trim(exec("hostname -f"));
                Config::setInDB('PLEX_CLIENT_ID', static::$_client_id);
            }
        }
        return static::$_client_id;
    }

    private static function getAccessToken(&$user_infos = NULL) : ?string {
        $token = @$_SESSION['PLEX_ACCESS_TOKEN'];
        if (empty($token)) {
            return NULL;
        }

        try {
            $user_infos = static::sendGET('/v2/user', 'https://plex.tv/api', static::CACHE_DURING_REQUEST, $token);
            if (empty($user_infos->uuid)) {
                Logger::error("Invalid Plex auth token: " . json_encode($user_infos));
                return NULL;
            }
        } catch (Exception $ex) {
            Logger::error("Invalid Plex auth token: " . $ex->getMessage());
            if ($ex->getCode() == 401) {
                unset($_SESSION['PLEX_ACCESS_TOKEN']);
            }
            return NULL;
        }
        return $token;
    }

    private const ACCESS_TOKEN_SKIP = 'dont_send_access_token';
    private const CACHE_DURING_REQUEST = 9876;
    private static function sendGET($url, ?string $base_url = NULL, int $use_cache_with_timeout = 0, ?string $access_token = NULL, bool $decode_json = TRUE) {
        $data = [
            'X-Plex-Client-Identifier' => static::getClientID(),
            'X-Plex-Product' => static::getAppName(),
        ];
        if ($access_token !== static::ACCESS_TOKEN_SKIP) {
            if ($access_token == NULL) {
                $access_token = static::getAccessToken();
            }
            if (empty($access_token)) {
                throw new PlexException("Empty access token");
            }
            $data['X-Plex-Token'] = $access_token;
        }
        $sep = string_contains($url, '?') ? '&' : '?';
        $url .= $sep . http_build_query($data);

        if (empty($base_url)) {
            $base_url = static::getBaseURL();
        }

        if (!empty($use_cache_with_timeout)) {
            if ($use_cache_with_timeout == static::CACHE_DURING_REQUEST) {
                $cache = $_REQUEST['plex_api_cache'] ?? [];
            } else {
                $cache = $_SESSION['plex_api_cache'] ?? [];
            }
            if (isset($cache["$base_url$url"]) && $cache["$base_url$url"]['expires'] > time()) {
                Logger::debug("Plex::sendGET($base_url$url) : using cached value (" . ($use_cache_with_timeout == static::CACHE_DURING_REQUEST ? "expires with request" : " (expires " . date('Y-m-d H:i:s', $cache["$base_url$url"]['expires'])) . ")");
                $response = $cache["$base_url$url"]['response'];
            }
        }

        if (empty($response)) {
            try {
                Logger::debug("Plex::sendGET($base_url$url)");
                $response = sendGET($base_url . $url, ["Accept: application/json"]);
            } catch (Exception $ex) {
                throw new PlexException($ex->getMessage(), $ex->getCode());
            }

            if (!empty($use_cache_with_timeout)) {
                $cache["$base_url$url"] = [
                    'expires' => time() + $use_cache_with_timeout,
                    'response' => $response,
                ];
                if ($use_cache_with_timeout == static::CACHE_DURING_REQUEST) {
                    $_REQUEST['plex_api_cache'] = $cache;
                } else {
                    $_SESSION['plex_api_cache'] = $cache;
                }
            }
        }

        if (!$decode_json) {
            return $response;
        }

        $result = json_decode($response);
        if ($result === NULL && $response !== 'null') {
            // response is not JSON; return it as text
            return $response;
        }
        if (!empty($result->MediaContainer)) {
            return $result->MediaContainer;
        }
        return $result;
    }

    private static function sendPOST($url, array $data, $method = 'POST', ?string $base_url = NULL, int $use_cache_with_timeout = 0, ?string $access_token = NULL, bool $decode_json = TRUE) {
        $data_url = [];
        $data_url['X-Plex-Client-Identifier'] = static::getClientID();
        $data_url['X-Plex-Product'] = static::getAppName();
        if ($access_token !== static::ACCESS_TOKEN_SKIP) {
            if ($access_token == NULL) {
                $access_token = static::getAccessToken();
            }
            if (empty($access_token)) {
                throw new PlexException("Empty access token");
            }
            $data_url['X-Plex-Token'] = $access_token;
        }
        if ($method !== 'POST') {
            $data_url = array_merge($data_url, $data);
        }
        $sep = string_contains($url, '?') ? '&' : '?';
        $url .= $sep . http_build_query($data_url);

        if (empty($base_url)) {
            $base_url = static::getBaseURL();
        }

        if (!empty($use_cache_with_timeout)) {
            if ($use_cache_with_timeout == static::CACHE_DURING_REQUEST) {
                $cache = $_REQUEST['plex_api_cache'] ?? [];
            } else {
                $cache = $_SESSION['plex_api_cache'] ?? [];
            }
            if (isset($cache["$base_url$url"]) && $cache["$base_url$url"]['expires'] > time()) {
                Logger::debug("Plex::sendGET($base_url$url) : using cached value (" . ($use_cache_with_timeout == static::CACHE_DURING_REQUEST ? "expires with request" : " (expires " . date('Y-m-d H:i:s', $cache["$base_url$url"]['expires'])) . ")");
                $response = $cache["$base_url$url"]['response'];
            }
        }

        if (empty($response)) {
            try {
                Logger::debug("Plex::sendPOST($method $base_url$url)");
                $response = sendPOST($base_url . $url, $method === 'POST' ? $data : [], ["Accept: application/json"], NULL, $method);
            } catch (Exception $ex) {
                throw new PlexException($ex->getMessage(), $ex->getCode());
            }

            if (!empty($use_cache_with_timeout)) {
                $cache["$base_url$url"] = [
                    'expires' => time() + $use_cache_with_timeout,
                    'response' => $response,
                ];
                if ($use_cache_with_timeout == static::CACHE_DURING_REQUEST) {
                    $_REQUEST['plex_api_cache'] = $cache;
                } else {
                    $_SESSION['plex_api_cache'] = $cache;
                }
            }
        }

        if (!$decode_json) {
            return $response;
        }

        $result = json_decode($response);
        if ($result === NULL && $response !== 'null') {
            // response is not JSON; return it as text
            return $response;
        }
        if (!empty($result->MediaContainer)) {
            return $result->MediaContainer;
        }
        return $result;
    }
}
