<?php

namespace PommePause\Dropinambour;

use Exception;

class TheTVDB {

    public static function getEpisodesForSeason($id, int $season_number) : ?array {
        $cache_file = sys_get_temp_dir() . "/thetvdb_{$id}_S{$season_number}.json";
        if (file_exists($cache_file) && filemtime($cache_file) > time()-24*60*60) {
            return json_decode(file_get_contents($cache_file));
        }
        try {
            $url = "/series/$id/episodes/official?season=$season_number";
            $response = static::sendGET($url);
            $season_episodes = $response->data->episodes;
            file_put_contents($cache_file, json_encode($season_episodes));
            return $season_episodes;
        } catch (Exception $ex) {
            Logger::error("Failed to load season details on TheTVDB, for ID $id, season #$season_number: " . $ex->getMessage());
        }
        return NULL;
    }

    /* Pragma mark - API */

    private static function getBaseURL() : string {
        return 'https://api4.thetvdb.com/v4';
    }

    private static function getBearerToken() : string {
        $token = Config::getFromDB('THETVDB_TOKEN');
        if (!empty($token)) {
            return $token;
        }
        $data = [
            'apikey' => 'e0994ee2-e89d-4d28-850b-9e6f5e52fc50', // dropinambour project
            'pin'    => Config::get('THETVDB_SUBSCRIPTION_PIN'),
        ];
        $response = static::sendPOST('/login', $data);
        $token = $response->data->token;
        Config::setInDB('THETVDB_TOKEN', $token);
        return $token;
    }

    private static function sendPOST($url, $data) {
        $headers = ["Accept: application/json"];
        if ($url != '/login') {
            $headers[] = "Authorization: Bearer " . static::getBearerToken();
        }
        Logger::debug("TheTVDB::sendPOST($url)");
        $response = sendPOST(static::getBaseURL() . $url, $data, $headers, "application/json;charset=utf-8");
        return json_decode($response);
    }

    private static function sendGET($url) {
        $headers = ["Accept: application/json", "Content-type: application/json;charset=utf-8", "Authorization: Bearer " . static::getBearerToken()];
        Logger::debug("TheTVDB::sendGET($url)");
        $response = sendGET(static::getBaseURL() . $url, $headers);
        return json_decode($response);
    }
}
