<?php

namespace PommePause\Dropinambour;

use PommePause\Dropinambour\ActiveRecord\Request;
use stdClass;

class Radarr
{

    // Ref: https://radarr.video/docs/api/

    public static function importAllRequests() : int {
        Logger::info("Importing Radarr requests...");
        $movies = static::getAllMovies();
        $existing_requests = Request::getAllMovieRequests();
        foreach ($movies as $movie) {
            $req = @$existing_requests["tmdb:$movie->tmdbId"];
            if ($req) {
                $req->updateFromRadarrMovie($movie);
            } else {
                $req = Request::fromRadarrMovie($movie);
            }
            $req->save();
        }
        Logger::info("Done importing Radarr requests.");
        return count($movies);
    }

    public static function getAllMovies() : array {
        return static::sendGET('/movie');
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

    private static function getDefault($media) : stdClass {
        $defaults = Config::getFromDB('RADARR_DEFAULTS', ['language=en' => ''], Config::GET_OPT_PARSE_AS_JSON);
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

    public static function addMovie(int $tmdb_id, string $title, int $quality_profile_id, string $path, ?string $tags = '') : stdClass {
        if (empty($tags)) {
            $tags = '';
        }
        $existing_tags = static::getAllTags();
        $existing_tag_labels = array_map('strtolower', getPropValuesFromArray($existing_tags, 'label'));
        $tags = array_map('strtolower', array_map('trim', explode(',', $tags)));
        $tag_ids = [];
        foreach ($tags as $tag_name) {
            $pos = array_search($tag_name, $existing_tag_labels);
            if ($pos !== FALSE) {
                $tag_ids[] = getPropValuesFromArray($existing_tags, 'id')[$pos];
            }
        }
        $data = [
            'title'               => $title,
            'tmdbId'              => $tmdb_id,
            'qualityProfileId'    => $quality_profile_id,
            'rootFolderPath'      => $path,
            'minimumAvailability' => 'announced',
            'monitored'           => TRUE,
            'addOptions'          => (object) ['searchForMovie' => TRUE],
            'tags'                => $tag_ids,
        ];
        $movie = static::sendPOST('/movie', $data);
        $request = Request::fromRadarrMovie($movie);
        $request->save();
        $request->notifyAdminRequestAdded();
        return $movie;
    }

    public static function getAllTags() : array {
        $tags = static::sendGET('/tag');
        $fct_sort = function ($q1, $q2) {
            return strtolower($q1->label) <=> strtolower($q2->label);
        };
        usort($tags, $fct_sort);
        return $tags;
    }

    private static function getBaseURL() : string {
        return Config::get('RADARR_URL');
    }

    private static function sendGET($url) {
        $api_key = Config::get('RADARR_API_KEY');
        $sep = string_contains($url, '?') ? '&' : '?';
        $url .= $sep . "apikey=" . urlencode($api_key);
        Logger::debug("Radarr::sendGET($url)");
        $response = sendGET(static::getBaseURL() . $url, ["Accept: application/json"]);
        $response = json_decode($response);
        return $response;
    }

    private static function sendPOST($url, $data) {
        $api_key = Config::get('RADARR_API_KEY');
        $sep = string_contains($url, '?') ? '&' : '?';
        $url .= $sep . "apikey=" . urlencode($api_key);
        Logger::debug("Radarr::sendPOST($url, " . json_encode($data) . ")");
        $response = sendPOST(static::getBaseURL() . $url, $data, ["Accept: application/json"], 'application/json');
        $response = json_decode($response);
        return $response;
    }
}
