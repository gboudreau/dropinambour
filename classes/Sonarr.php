<?php

namespace PommePause\Dropinambour;

use PommePause\Dropinambour\ActiveRecord\Request;
use stdClass;

class Sonarr {

    // Ref: https://github.com/Sonarr/Sonarr/wiki/API

    public static function importAllRequests() : int {
        Logger::info("Importing Sonarr requests...");
        $shows = static::getAllShows();
        $existing_requests = Request::getAllShowRequests();
        foreach ($shows as $show) {
            $req = @$existing_requests["tvdb:$show->tvdbId"];
            if ($req) {
                $req->updateFromSonarShow($show);
            } else {
                $req = Request::fromSonarrShow($show);
            }
            $req->save();
            $req->addSeasonsFromSonarrShow($show);
        }
        Logger::info("Done importing Sonarr requests.");
        return count($shows);
    }

    public static function lookupShow(int $tvdb_id) : ?stdClass {
        $shows = static::sendGET('/series/lookup?term=tvdb%3A' . $tvdb_id);
        return empty($shows) ? NULL : first($shows);
    }

    public static function getAllShows() : array {
        return static::sendGET('/series');
    }

    public static function getQualityProfiles() : array {
        $profiles = static::sendGET('/qualityprofile');
        $fct_sort = function ($q1, $q2) {
            return strtolower($q1->name) <=> strtolower($q2->name);
        };
        usort($profiles, $fct_sort);
        return $profiles;
    }

    public static function getConfigPaths() : array {
        $requests = Request::getAllShowRequests();
        $paths = array_unique(array_map('dirname', getPropValuesFromArray($requests, 'path')));
        if (empty($paths)) {
            $paths = static::sendGET('/rootfolder');
            $paths = array_unique(getPropValuesFromArray($paths, 'path'));
        }
        sort($paths);
        return $paths;
    }

    private static function getDefault($media) : stdClass {
        $defaults = Config::getFromDB('SONARR_DEFAULTS', ['language=en' => ''], Config::GET_OPT_PARSE_AS_JSON);
        foreach ($defaults as $when => $default) {
            $when = explode('=', $when);
            if ($when[0] == 'language' && @$media->original_language == $when[1]) {
                return $default;
            }
            if ($when[0] == 'genre') {
                foreach ($media->genres ?? [] as $genre) {
                    if (strtolower($genre->name) == strtolower($when[1])) {
                        return $default;
                    }
                }
            }
        }
        return $defaults->default;
    }

    public static function getDefaultPath($media) : string {
        $default = static::getDefault($media);
        return $default->path ?? '';
    }

    public static function getDefaultQuality($media) : string {
        $default = static::getDefault($media);
        return $default->quality ?? '';
    }

    public static function getDefaultTags($media) : string {
        $default = static::getDefault($media);
        return $default->tags ?? '';
    }

    public static function addShow(int $tmdbtv_id, int $tvdb_id, string $title, string $title_slug, int $quality_profile_id, string $path, int $season, ?array $images) : stdClass {
        if (empty($title_slug)) {
            $title_slug = $tvdb_id;
        }
        $monitored = Config::get('SONARR_MONITOR_ON_REQ', TRUE);
        $data = [
            'title'             => $title,
            'tvdbId'            => $tvdb_id,
            'titleSlug'         => $title_slug,
            'qualityProfileId'  => $quality_profile_id,
            'rootFolderPath'    => $path,
            'images'            => $images ?? [],
            'monitored'         => $monitored,
            'seasonFolder'      => TRUE,
            'seasons' => [
                (object) [
                    'seasonNumber' => $season,
                    'monitored'    => $monitored,
                ],
            ],
            'addOptions' => (object) [
                'monitor'                      => $monitored && $season == 1 ? 'firstSeason' : 'none',
                'searchForCutoffUnmetEpisodes' => FALSE,
                'searchForMissingEpisodes'     => $monitored,
            ],
        ];

        $qualities = Config::get('SONARR_SIMPLIFIED_QUALITY', [], Config::GET_OPT_PARSE_AS_JSON);
        $quality = $qualities[$quality_profile_id] ?? findPropValueInArray(Sonarr::getQualityProfiles(), 'id', $quality_profile_id, 'name', 'unknown');

        $show = static::sendPOST('/series', $data);
        if ($season > 1) {
            static::addSeason($show->id, $tmdbtv_id, $season);
        }
        $request = Request::fromSonarrShow($show);
        $request->tmdbtv_id = $tmdbtv_id;
        $request->save();
        $request->notifyAdminRequestAdded($season, $quality);
        return $show;
    }

    public static function addSeason(int $sonarr_id, int $tmdbtv_id, int $season_number) : stdClass {
        $monitored = Config::get('SONARR_MONITOR_ON_REQ', TRUE);
        $show = static::getShow($sonarr_id);
        foreach ($show->seasons as $season) {
            if ($season->seasonNumber == $season_number) {
                $season->monitored = $monitored;
                break;
            }
        }
        $show = static::sendPOST("/series/$sonarr_id", $show, 'PUT');

        // Might need to turn on monitoring for specific episodes
        $episodes_to_monitor = [];
        $episodes = static::getShowEpisodes($sonarr_id);
        foreach ($episodes as $episode) {
            if ($episode->seasonNumber == $season_number && !$episode->monitored) {
                $episodes_to_monitor[] = $episode->id;
            }
        }
        if (!empty($episodes_to_monitor)) {
            static::sendPOST("/episode/monitor", ['episodeIds' => $episodes_to_monitor, 'monitored' => $monitored], 'PUT');
        }

        static::sendCommand('SeasonSearch', ['seriesId' => $sonarr_id, 'seasonNumber' => $season_number]);

        $request = Request::fromSonarrShow($show);
        $request->tmdbtv_id = $tmdbtv_id;
        $request->save();
        $request->addSeasonsFromSonarrShow($show);
        $request->notifyAdminRequestAdded($season_number);

        return $show;
    }

    public static function getShow(int $sonarr_id) : stdClass {
        return static::sendGET("/series/$sonarr_id");
    }

    public static function getShowEpisodes(int $sonarr_id) : array {
        return static::sendGET("/episode?seriesId=$sonarr_id");
    }

    public static function sendCommand(string $command_name, array $command_options = []) {
        $command_options['name'] = $command_name;
        return static::sendPOST('/command', $command_options);
    }

    private static function getBaseURL() : string {
        return Config::get('SONARR_API_URL');
    }

    private static function sendGET($url) {
        $api_key = Config::get('SONARR_API_KEY');
        Logger::debug("Sonarr::sendGET($url)");
        $response = sendGET(static::getBaseURL() . $url, ["X-Api-Key: $api_key", "Accept: application/json"]);
        $response = json_decode($response);
        return $response;
    }

    private static function sendPOST($url, $data, string $method = 'POST') {
        $api_key = Config::get('SONARR_API_KEY');
        Logger::debug("Sonarr::sendPOST($url, " . json_encode($data) . ")");
        $response = sendPOST(static::getBaseURL() . $url, $data, ["X-Api-Key: $api_key", "Accept: application/json"], 'application/json', $method);
        $response = json_decode($response);
        return $response;
    }
}
