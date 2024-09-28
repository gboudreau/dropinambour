<?php

namespace PommePause\Dropinambour;

class TorrentLeech {
    private const BASE_URL = 'https://www.torrentleech.org';

    public static function getPopularMedias() : array {
        $cache_key = 'TL_POPULAR_' . date('Ymd');
        $result = Config::getFromDB($cache_key);
        if (!empty($result)) {
            return (array) json_decode($result);
        }

        $fct_get_results = function ($since_when) {
            $date = urlencode($since_when);
            $url = TorrentLeech::BASE_URL . "/torrents/browse/list/added/$date/orderby/completed/order/desc";
            $header = 'Cookie: tluid=' . Config::get('TL_UID') . '; tlpass=' . Config::get('TL_PASS');
            $response = sendGET($url, [$header], TRUE, 90, TRUE, TRUE);
            $torrent_list = json_decode($response)->torrentList;
            $result = [];
            foreach ($torrent_list as $torrent) {
                if (!empty($torrent->tvmazeID)) {
                    if (preg_match('/^(.+) S\d+E\d+/', $torrent->name, $re)) {
                        $title = $re[1];
                        $title = preg_replace('/ \(20\d\d\)$/', '', $title);
                        $title = preg_replace('/ 20\d\d$/', '', $title);
                        if (!isset($result['shows'][strtolower($title)])) {
                            $tmdbtv_id = TMDB::getIDByShowName($title);
                            $result['shows'][strtolower($title)] = TMDB::getDetailsTV($tmdbtv_id, $_GET['language'] ?? 'en', TRUE, TRUE, 30*24*60*60);
                        }
                    }
                } elseif (!empty($torrent->imdbID)) {
                    if (preg_match('/^(.+) \(?(19\d\d)\)?/', $torrent->name, $re) || preg_match('/^(.+) \(?(20\d\d)\)?/', $torrent->name, $re)) {
                        if (!isset($result['movies'][strtolower($re[1])])) {
                            $tmdb_id = TMDB::getIDByExternalId($torrent->imdbID, 'imdb', $re[1]);
                            $result['movies'][strtolower($re[1])] = TMDB::getDetailsMovie($tmdb_id, $_GET['language'] ?? 'en', TRUE, TRUE, 30*24*60*60);
                        }
                    }
                }
            }
            $result['movies'] = array_values($result['movies'] ?? []);
            $result['shows'] = array_values($result['shows'] ?? []);
            return $result;
        };
        $result1 = $fct_get_results('-2 days');
        $result2 = $fct_get_results('-1 weeks');
        $result3 = $fct_get_results('-6 weeks');

        $result = [];
        $result['movies'] = array_merge($result1['movies'], $result2['movies'], $result3['movies']);
        $result['shows'] = $result2['shows'];

        // Keep only each movie/show once
        $movies = [];
        foreach ($result['movies'] as $media) {
            if (empty($media)) continue;
            $movies[$media->id] = $media;
        }
        $result['movies'] = array_values($movies);
        $shows  = [];
        foreach ($result['shows'] as $media) {
            if (empty($media)) continue;
            $shows[$media->id] = $media;
        }
        $result['shows'] = array_values($shows);

        // Pagination and uniqueness across pages
        $_SESSION['tmdb_suggested_movie_page'] = 0;
        $_SESSION['tmdb_suggested_movie_ids'] = getPropValuesFromArray($result['movies'], 'id');
        $_SESSION['tmdb_suggested_tv_page'] = 0;
        $_SESSION['tmdb_suggested_tv_ids'] = getPropValuesFromArray($result['shows'], 'id');

        // Sorting: available last, requested 2nd, and sort non-requested by popularity
        usort($result['movies'], ['\PommePause\Dropinambour\TMDB', 'sortSuggestedMedias']);
        usort($result['shows'], ['\PommePause\Dropinambour\TMDB', 'sortSuggestedMedias']);

        // Cache result for one day
        Config::setInDB($cache_key, json_encode($result));

        return $result;
    }
}
