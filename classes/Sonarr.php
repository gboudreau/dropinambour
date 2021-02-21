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

    public static function getLanguageProfiles() : array {
        $profiles = static::sendGET('/languageProfile');
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

    public static function addShow(int $tvdb_id, string $title, string $title_slug, int $quality_profile_id, int $language_profile_id, string $path, ?array $images) : stdClass {
        if (empty($title_slug)) {
            $title_slug = $tvdb_id;
        }
        $data = [
            'title'             => $title,
            'tvdbId'            => $tvdb_id,
            'titleSlug'         => $title_slug,
            'qualityProfileId'  => $quality_profile_id,
            'languageProfileId' => $language_profile_id,
            'rootFolderPath'    => $path,
            'images'            => $images ?? [],
            'monitored'         => TRUE,
            'seasonFolder'      => TRUE,
            'seasons' => [
                (object) [
                    'seasonNumber' => 1,
                    'monitored'    => TRUE,
                ],
            ],
            'addOptions' => (object) [
                'monitor'                      => 'firstSeason',
                'searchForCutoffUnmetEpisodes' => FALSE,
                'searchForMissingEpisodes'     => TRUE,
            ],
        ];
        $show = static::sendPOST('/series', $data);
        $request = Request::fromSonarrShow($show);
        $request->save();
        $request->notifyAdminRequestAdded(1);
        return $show;
    }

    public static function addSeason(int $sonarr_id, int $season_number) : stdClass {
        $show = static::getShow($sonarr_id);
        foreach ($show->seasons as $season) {
            if ($season->seasonNumber == $season_number) {
                $season->monitored = TRUE;
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
            static::sendPOST("/episode/monitor", ['episodeIds' => $episodes_to_monitor, 'monitored' => TRUE], 'PUT');
        }

        static::sendCommand('SeasonSearch', ['seriesId' => $sonarr_id, 'seasonNumber' => $season_number]);

        $request = Request::fromSonarrShow($show);
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
        return Config::get('SONARR_URL');
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
