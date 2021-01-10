<?php

namespace PommePause\Dropinambour;

use PommePause\Dropinambour\ActiveRecord\Request;
use stdClass;

class Sonarr {

    // Ref: https://github.com/Sonarr/Sonarr/wiki/API

    public static function importAllRequests() : int {
        Logger::info("Importing Sonarr requests...");
        $shows = static::getAllShows();
        foreach ($shows as $show) {
            $req = Request::fromSonarrShow($show);
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
        $shows = static::sendGET('/series');
        return $shows;
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

    public static function addShow(int $tvdb_id, string $title, string $title_slug, int $quality_profile_id, int $language_profile_id, string $path, array $images) : stdClass {
        $data = [
            'title'             => $title,
            'tvdbId'            => $tvdb_id,
            'titleSlug'         => $title_slug,
            'qualityProfileId'  => $quality_profile_id,
            'languageProfileId' => $language_profile_id,
            'rootFolderPath'    => $path,
            'images'            => $images,
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
                'searchForMissingEpisodes'     => FALSE,
            ],
        ];
        $show = static::sendPOST('/series', $data);
        $request = Request::fromSonarrShow($show);
        $request->save();
        $request->notifyAdminRequestAdded();
        return $show;
    }

    private static function getBaseURL() : string {
        return Config::get('SONARR_URL');
    }

    private static function sendGET($url) {
        $api_key = Config::get('SONARR_API_KEY');
        $response = sendGET(static::getBaseURL() . $url, ["X-Api-Key: $api_key", "Accept: application/json"]);
        $response = json_decode($response);
        return $response;
    }

    private static function sendPOST($url, $data) {
        $api_key = Config::get('SONARR_API_KEY');
        $response = sendPOST(static::getBaseURL() . $url, $data, ["X-Api-Key: $api_key", "Accept: application/json"], 'application/json');
        $response = json_decode($response);
        return $response;
    }
}
