<?php

namespace PommePause\Dropinambour;

use PommePause\Dropinambour\ActiveRecord\Request;
use stdClass;

class Radarr {

    // Ref: https://radarr.video/docs/api/

    public static function importAllRequests() : int {
        Logger::info("Importing Radarr requests...");
        $movies = static::getAllMovies();
        foreach ($movies as $movie) {
            $req = Request::fromRadarrMovie($movie);
            $req->save();
        }
        Logger::info("Done importing Radarr requests.");
        return count($movies);
    }

    public static function getAllMovies() : array {
        $movies = static::sendGET('/movie');
        return $movies;
    }

    public static function getQualityProfiles() : array {
        $profiles = static::sendGET('/qualityProfile');
        $fct_sort = function ($q1, $q2) {
            return strtolower($q1->name) <=> strtolower($q2->name);
        };
        usort($profiles, $fct_sort);
        return $profiles;
    }

    public static function getConfigPaths() : array {
        $requests = Request::getAllMovieRequests();
        $paths = array_unique(array_map('dirname', getPropValuesFromArray($requests, 'path')));
        sort($paths);
        return $paths;
    }

    public static function addMovie(int $tmdb_id, string $title, int $quality_profile_id, string $path) : stdClass {
        $data = [
            'title'               => $title,
            'tmdbId'              => $tmdb_id,
            'qualityProfileId'    => $quality_profile_id,
            'rootFolderPath'      => $path,
            'minimumAvailability' => 'announced',
        ];
        $movie = static::sendPOST('/movie', $data);
        $request = Request::fromRadarrMovie($movie);
        $request->save();
        $request->notifyAdminRequestAdded();
        return $movie;
    }

    private static function getBaseURL() : string {
        return Config::get('RADARR_URL');
    }

    private static function sendGET($url) {
        $api_key = Config::get('RADARR_API_KEY');
        $sep = string_contains($url, '?') ? '&' : '?';
        $url .= $sep . "apikey=" . urlencode($api_key);
        $response = sendGET(static::getBaseURL() . $url, ["Accept: application/json"]);
        $response = json_decode($response);
        return $response;
    }

    private static function sendPOST($url, $data) {
        $api_key = Config::get('RADARR_API_KEY');
        $sep = string_contains($url, '?') ? '&' : '?';
        $url .= $sep . "apikey=" . urlencode($api_key);
        $response = sendPOST(static::getBaseURL() . $url, $data, ["Accept: application/json"], 'application/json');
        $response = json_decode($response);
        return $response;
    }
}
