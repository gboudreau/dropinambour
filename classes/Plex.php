<?php

namespace PommePause\Dropinambour;

use Exception;
use PommePause\Dropinambour\Exceptions\PlexException;
use stdClass;

class Plex {

    // Ref: https://forums.plex.tv/t/authenticating-with-plex/609370
    // Ref: https://github.com/Arcanemagus/plex-api/wiki/Plex-Web-API-Overview
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
    public static function getItemMetadata(string $item_key, bool $return_array = FALSE) {
        try {
            $response = static::sendGET($item_key);
        } catch (Exception $ex) {
            Logger::error("Failed to get Plex Metadata for item $item_key: " . $ex->getMessage());
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
        $data = [
            'X-Plex-Product'           => static::getAppName(),
            'X-Plex-Client-Identifier' => static::getClientID(),
            'X-Plex-Token'             => $token
        ];
        $url = 'https://plex.tv/api/users?' . http_build_query($data);
        $response = sendGET($url);
        $response = simplexml_load_string($response);
        $users = [];
        foreach ($response->User as $user) {
            $user = (object) [
                'id' => (int) $user->attributes()['id'],
                'username' => (string) $user->attributes()['username'],
                'email' => (string) $user->attributes()['email'],
                'avatar' => (string) $user->attributes()['thumb'],
            ];
            $users[] = $user;
        }
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
        $data = [
            'code' => $pin->code,
            'X-Plex-Client-Identifier' => static::getClientID(),
        ];
        $response = sendGET('https://plex.tv/api/v2/pins/' . $pin->id . '?' . http_build_query($data), ["Accept: application/json"]);
        $response = json_decode($response);
        if (!empty($response->authToken)) {
            unset($_SESSION['PLEX_PIN']);

            $_SESSION['PLEX_ACCESS_TOKEN'] = $response->authToken;
            $user = Plex::getUserInfos();

            $allowed_to_login = FALSE;
            foreach (Plex::getSharedUsers() as $allowed_user) {
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

    public static function getServerId() : string {
        $response = static::sendGET("/identity");
        return $response->machineIdentifier;
    }

    private static function getAppName() : string {
        return "dropinambour - Requests for Plex";
    }

    private static function getBaseURL() : string {
        return Config::get('PLEX_BASE_URL');
    }

    private static function getClientID() : string {
        $client_id = Config::getFromDB('PLEX_CLIENT_ID');
        if (empty($client_id)) {
            $client_id = 'DINB-' . trim(sendGET('https://ip.danslereseau.com')) . '-' . trim(exec("hostname -f"));
            Config::setInDB('PLEX_CLIENT_ID', $client_id);
        }
        return $client_id;
    }

    protected static $token;
    protected static $user_infos;
    private static function getAccessToken(&$user_infos = NULL) : ?string {
        if (!empty(static::$token)) {
            $user_infos = static::$user_infos;
            return static::$token;
        }
        $token = @$_SESSION['PLEX_ACCESS_TOKEN'];
        if (empty($token)) {
            return NULL;
        }

        $data = [
            'X-Plex-Product'           => static::getAppName(),
            'X-Plex-Client-Identifier' => static::getClientID(),
            'X-Plex-Token'             => $token
        ];
        try {
            $response = sendGET('https://plex.tv/api/v2/user?' . http_build_query($data), ["Accept: application/json"]);
            $user_infos = json_decode($response);
            if (empty($user_infos->uuid)) {
                Logger::error("Invalid Plex auth token: " . $response);
                return NULL;
            }
            static::$token = $token;
            static::$user_infos = $user_infos;
        } catch (Exception $ex) {
            Logger::error("Invalid Plex auth token: " . $ex->getMessage());
            if ($ex->getCode() == 401) {
                unset($_SESSION['PLEX_ACCESS_TOKEN']);
            }
            return NULL;
        }
        return static::$token;
    }

    private static function sendGET($url) {
        $sep = string_contains($url, '?') ? '&' : '?';
        $url .= $sep . "X-Plex-Token=" . urlencode(static::getAccessToken()) . "&X-Plex-Client-Identifier=" . urlencode(static::getClientID());
        try {
            $response = sendGET(static::getBaseURL() . $url, ["Accept: application/json"]);
        } catch (Exception $ex) {
            throw new PlexException($ex->getMessage(), $ex->getCode());
        }
        $response = json_decode($response);
        if (!empty($response->MediaContainer)) {
            return $response->MediaContainer;
        }
        return $response;
    }
}
