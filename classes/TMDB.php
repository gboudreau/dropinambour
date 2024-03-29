<?php

namespace PommePause\Dropinambour;

use Exception;
use PommePause\Dropinambour\ActiveRecord\AvailableMedia;
use PommePause\Dropinambour\ActiveRecord\Request;
use stdClass;

class TMDB {

    // Ref: https://developers.themoviedb.org/3/getting-started/introduction
    // Ref: https://developers.themoviedb.org/3/

    protected static stdClass $config;

    /* Pragma mark - Images */

    public const IMAGE_SIZE_POSTER_W42 = 0;
    public const IMAGE_SIZE_POSTER_W154 = 1;
    public const IMAGE_SIZE_POSTER_W185 = 2;
    public const IMAGE_SIZE_POSTER_W342 = 3;
    public const IMAGE_SIZE_POSTER_W500 = 4;
    public const IMAGE_SIZE_POSTER_W780 = 5;
    public const IMAGE_SIZE_POSTER_ORIGINAL = 6;

    public const IMAGE_SIZE_BACKDROP_W300 = 0;
    public const IMAGE_SIZE_BACKDROP_W780 = 1;
    public const IMAGE_SIZE_BACKDROP_W1280 = 2;
    public const IMAGE_SIZE_BACKDROP_ORIGINAL = 3;

    public static function getConfig() : stdClass {
        if (empty($_SESSION['tmdb_config'])) {
            $response = static::sendGET("/configuration");
            $_SESSION['tmdb_config'] = $response;
        }
        return $_SESSION['tmdb_config'];
    }

    public static function getPosterImageUrl(string $path, int $size) : string {
        return static::getImageUrl($path, 'poster', $size);
    }

    public static function getBackdropImageUrl(string $path, int $size) : string {
        return static::getImageUrl($path, 'backdrop', $size);
    }

    private static function getImageUrl(string $path, string $type, int $size) : string {
        if (empty(static::$config)) {
            static::$config = static::getConfig();
        }
        return static::$config->images->secure_base_url . static::$config->images->{$type . "_sizes"}[$size] . $path;
    }

    /* Pragma mark - Search */

    public static function searchMulti(string $query, ?string $language = NULL) : array {
        if (preg_match('/tvdb[_id]*\s*[:=]\s*(\d+)/', $query, $re)) {
            $response = (object) ['results' => []];
            $details = static::getDetailsByExternalId($re[1], 'tvdb');
            if (!empty($details)) {
                $response->results[] = $details;
            }
        } elseif (preg_match('/imdb[_id]*\s*[:=]\s*(tt\d+)/', $query, $re)) {
            $response = (object) ['results' => []];
            $details = static::getDetailsByExternalId($re[1], 'imdb');
            if (!empty($details)) {
                $response->results[] = $details;
            }
        } else {
            if (empty($language)) {
                $language = first(Config::get('LANGUAGES', 'en'));
            }
            $args = [
                'language' => $language,
                'query'    => $query,
            ];
            $response = static::sendGET("/search/multi?" . http_build_query($args));
            // Let's remove Person results
            foreach ($response->results as $i => $media) {
                if (@$media->media_type == 'person') {
                    unset($response->results[$i]);
                }
            }
            $response->results = array_map([self::class, 'nameToTitle'], $response->results);
        }
        static::addAvailability($response->results);
        return $response->results;
    }

    public static function getRecommendations($media, ?string $language = NULL) : array {
        // https://developers.themoviedb.org/3/movies/get-movie-details
        // https://developers.themoviedb.org/3/tv/get-tv-recommendations
        try {
            $url = "/$media->media_type/$media->id/recommendations" . (!empty($language) ? '?language=' . urlencode($language) : '');
            $response = static::sendGET($url);
            $results = $response->results;
            $results = array_map([self::class, 'nameToTitle'], $results);
            $fct_name = ($media->media_type == 'tv' ? 'addMediaTypeTV' : 'addMediaTypeMovie');
            $results = array_map([self::class, $fct_name], $results);
            static::addAvailability($results);
            usort($results, [static::class, 'sortByVote']);
            return $results;
        } catch (Exception $ex) {
            Logger::error("Failed to load $media->media_type recommendations on TMDB, for ID $media->id: " . $ex->getMessage());
        }
        return [];
    }

    public static function getIDByShowName(string $title) : int|bool {
        $q = "SELECT tmdbtv_id FROM tmdb_cache WHERE name = :title";
        $tmdb_id = DB::getFirstValue($q, $title);
        if (!$tmdb_id) {
            $search_results = TMDB::searchMulti($title);
            if ($search_results) {
                foreach ($search_results as $search_result) {
                    if ($search_result->media_type == 'tv') {
                        $tmdb_id = $search_result->id;

                        TMDB::getDetailsTV($tmdb_id, 'en', FALSE);

                        $q = "UPDATE tmdb_cache SET name = :title WHERE tmdbtv_id = :id";
                        DB::execute($q, ['id' => $tmdb_id, 'title' => $title]);
                        break;
                    }
                }
            }
        }
        return $tmdb_id;
    }

    public static function getIDByExternalId(string $id, string $source, ?string $title) : int|bool {
        if ($source == 'imdb') {
            $external_id_field = 'imdb_id';
            $tmdb_id_field = 'tmdb_id';
        } elseif ($source == 'tvdb') {
            $external_id_field = 'tvdb_id';
            $tmdb_id_field = 'tmdbtv_id';
        } else {
            return FALSE;
        }
        $q = "SELECT $tmdb_id_field FROM tmdb_external_ids WHERE $external_id_field = :external_id";
        $tmdb_id = DB::getFirstValue($q, $id);
        if (!$tmdb_id) {
            $tmdb_media = TMDB::getDetailsByExternalId($id, $source);
            if ($tmdb_media) {
                $tmdb_id = $tmdb_media->id;
            } else {
                $search_results = TMDB::searchMulti($title);
                if ($search_results) {
                    $tmdb_id = first($search_results)->id;
                }
            }
            if ($tmdb_id) {
                $q = "UPDATE tmdb_external_ids SET $external_id_field = :external_id WHERE $tmdb_id_field = :tmdb_id AND $external_id_field = '0'";
                DB::execute($q, ['tmdb_id' => $tmdb_id, 'external_id' => $id]);
            }
        }
        return $tmdb_id;
    }

    /**
     * @param string $id     External ID
     * @param string $source 'imdb' or 'tvdb'
     *
     * @return stdClass|null
     */
    public static function getDetailsByExternalId(string $id, string $source) : ?stdClass {
        // https://developers.themoviedb.org/3/find/find-by-id
        try {
            $response = static::sendGET("/find/$id?external_source={$source}_id");
            if (!empty($response->movie_results)) {
                $response->movie_results = array_map([self::class, 'addMediaTypeMovie'], $response->movie_results);
                static::addExternalIDs($response->movie_results);
                return first($response->movie_results);
            }
            if (!empty($response->tv_results)) {
                $response->tv_results = array_map([self::class, 'nameToTitle'], $response->tv_results);
                $response->tv_results = array_map([self::class, 'addMediaTypeTV'], $response->tv_results);
                static::addExternalIDs($response->tv_results);
                return first($response->tv_results);
            }
            return NULL;
        } catch (Exception $ex) {
            Logger::error("Failed to find details from $source = $id on TMDB: " . $ex->getMessage());
        }
        return NULL;
    }

    /* Pragma mark - Movie */

    public static function getDetailsMovie($id, string $language, bool $add_availability = TRUE, bool $use_cache = FALSE, int $cache_timeout = 24*60*60) : ?stdClass {
        if ($use_cache) {
            $q = "SELECT details, last_updated FROM tmdb_cache WHERE tmdb_id = :id AND language = :lang";
            $cache = DB::getFirst($q, ['id' => $id, 'lang' => $language]);
            if ($cache) {
                if (strtotime($cache->last_updated) >= time()-$cache_timeout) {
                    $response = json_decode($cache->details);
                }
            }
        }

        // https://developers.themoviedb.org/3/movie/get-movie-details
        try {
            if (empty($response)) {
                $url = "/movie/$id?append_to_response=videos,external_ids" . (!empty($language) ? '&language=' . urlencode($language) : '');
                $response = static::sendGET($url);
                if ($use_cache) {
                    // Save in cache
                    $q = "DELETE FROM tmdb_cache WHERE tmdb_id = :id AND language = :lang ; INSERT INTO tmdb_cache SET tmdb_id = :id, language = :lang, details = :details, last_updated = NOW()";
                    DB::insert($q, ['id' => $id, 'lang' => $language, 'details' => json_encode($response)]);
                }
            }
            static::addMediaTypeMovie($response);
            $medias = [$response];
            if ($add_availability) {
                static::addAvailability($medias);
            } else {
                static::addExternalIDs($medias);
            }
            $response = first($medias);
            return $response;
        } catch (Exception $ex) {
            Logger::error("Failed to load movie details on TMDB, for ID $id: " . $ex->getMessage());
        }
        return NULL;
    }

    public static function getMovieExternalIDs($id) : ?stdClass {
        // https://developers.themoviedb.org/3/movies/get-movie-external-ids
        try {
            $response = static::sendGET("/movie/$id/external_ids");
            return $response;
        } catch (Exception $ex) {
            Logger::error("Failed to load movie external_ids on TMDB, for ID $id: " . $ex->getMessage());
        }
        return NULL;
    }

    public static function getSuggestedMovies(int $page = 1) : array {
        if ($page == 1) {
            $_SESSION['tmdb_suggested_movie_ids'] = [];
        }

        // Trending/popular movies on pages 3+ are not really interesting... Show upcoming movies past page 2
        if ($page <= 2) {
            $movies = static::getPopularMovies($page);
        } else {
            $movies = static::getUpcomingMovies($page);
        }

        $movies_ = [];
        foreach ($movies as $movie) {
            if (array_contains($_SESSION['tmdb_suggested_movie_ids'], $movie->id)) {
                continue;
            }
            if (empty($movie->poster_path)) {
                Logger::info("Empty poster found for $movie->title (ID $movie->id).");
            }
            $movies_[$movie->id] = $movie;
        }
        $movies = $movies_;
        unset($movies_);

        static::addAvailability($movies);

        usort($movies, ['\PommePause\Dropinambour\TMDB', 'sortSuggestedMedias']);

        $_SESSION['tmdb_suggested_movie_page'] = $page;
        $_SESSION['tmdb_suggested_movie_ids'] = array_merge($_SESSION['tmdb_suggested_movie_ids'], getPropValuesFromArray($movies, 'id'));

        return $movies;
    }

    public static function sortSuggestedMedias($m1, $m2) : int {
        // Available last, then requested, then ...
        if ($m1->is_available && !$m2->is_available) {
            return 1;
        }
        if (!$m1->is_available && $m2->is_available) {
            return -1;
        }
        if ($m1->requested && !$m2->requested) {
            return 1;
        }
        if (!$m1->requested && $m2->requested) {
            return -1;
        }
        if (!empty($m1->release_date)) {
            // Sort movies by date desc
            return strtotime($m2->release_date) <=> strtotime($m1->release_date);
        }
        // Sort TV shows by vote desc
        return $m2->vote_average <=> $m1->vote_average;
    }

    public static function getMoreSuggestedMovies() : array {
        return static::getSuggestedMovies($_SESSION['tmdb_suggested_movie_page'] + 1);
    }

    public static function getPopularMovies(int $page = 1, string $lang = 'en') : array {
        // https://developers.themoviedb.org/3/movies/get-popular-movies
        $response = static::sendGET("/movie/popular?page=$page&language=$lang");
        $response->results = array_map([self::class, 'addMediaTypeMovie'], $response->results);
        return $response->results;
    }

    public static function getTrendingMovies(int $page = 1, string $lang = 'en') : array {
        // https://developers.themoviedb.org/3/trending/get-trending
        $response = static::sendGET("/trending/movie/week?page=$page&language=$lang");
        $response->results = array_map([self::class, 'addMediaTypeMovie'], $response->results);
        return $response->results;
    }

    public static function getNowPlayingMovies(int $page = 1, string $lang = 'en') : array {
        // https://developers.themoviedb.org/3/movies/get-now-playing
        $response = static::sendGET("/movie/now_playing?page=$page&language=$lang");
        $response->results = array_map([self::class, 'addMediaTypeMovie'], $response->results);
        return $response->results;
    }

    public static function getUpcomingMovies(int $page = 1, string $lang = 'en') : array {
        // https://developers.themoviedb.org/3/movies/get-upcoming
        $response = static::sendGET("/movie/upcoming?page=$page&language=$lang");
        $response->results = array_map([self::class, 'addMediaTypeMovie'], $response->results);
        return $response->results;
    }

    public static function getDetailsCollection($id) : ?stdClass {
        // https://developers.themoviedb.org/3/collections/get-collection-details
        try {
            $response = static::sendGET("/collection/$id");
            $response->parts = array_map([self::class, 'addMediaTypeMovie'], $response->parts);
            static::addAvailability($response->parts);
            return $response;
        } catch (Exception $ex) {
            Logger::error("Failed to load collection details on TMDB, for ID $id: " . $ex->getMessage());
        }
        return NULL;
    }

    /* Pragma mark - TV Show */

    public static function getDetailsTVSeason($id, int $season_number) : ?stdClass {
        $cache_file = sys_get_temp_dir() . "/tmdbtv_{$id}_S{$season_number}.json";
        if (file_exists($cache_file) && filemtime($cache_file) > time()-24*60*60) {
            return json_decode(file_get_contents($cache_file));
        }

        // https://developers.themoviedb.org/3/tv-seasons/get-tv-season-details
        try {
            $url = "/tv/$id/season/$season_number";
            $season_details = static::sendGET($url);
            file_put_contents($cache_file, json_encode($season_details));
            return $season_details;
        } catch (Exception $ex) {
            Logger::error("Failed to load season details on TMDB, for ID $id, season #$season_number: " . $ex->getMessage());
        }
        return NULL;
    }

    public static function getDetailsTV($id, string $language, bool $add_availability = TRUE, bool $use_cache = TRUE, int $cache_timeout = 24*60*60) : ?stdClass {
        if ($use_cache) {
            $q = "SELECT details, last_updated FROM tmdb_cache WHERE tmdbtv_id = :id AND language = :lang";
            $cache = DB::getFirst($q, ['id' => $id, 'lang' => $language]);
            if ($cache) {
                if (strtotime($cache->last_updated) >= time()-$cache_timeout) {
                    $response = json_decode($cache->details);
                }
            }
        }
        // https://developers.themoviedb.org/3/tv/get-tv-details
        try {
            if (empty($response)) {
                $url = "/tv/$id?append_to_response=videos,external_ids" . (!empty($language) ? '&language=' . urlencode($language) : '');
                $response = static::sendGET($url);
                if ($use_cache) {
                    // Save in cache
                    $q = "DELETE FROM tmdb_cache WHERE tmdbtv_id = :id AND language = :lang ; INSERT INTO tmdb_cache SET tmdbtv_id = :id, language = :lang, details = :details, name = :title, last_updated = NOW()";
                    DB::insert($q, ['id' => $id, 'lang' => $language, 'details' => json_encode($response), 'title' => $response->name]);
                }
            }
            static::nameToTitle($response);
            static::addMediaTypeTV($response);
            $medias = [$response];
            if ($add_availability) {
                static::addAvailability($medias);
            } else {
                static::addExternalIDs($medias);
            }
            $response = first($medias);
            return $response;
        } catch (Exception $ex) {
            Logger::error("Failed to load show details on TMDB, for ID $id: " . $ex->getMessage());
        }
        return NULL;
    }

    public static function getShowExternalIDs($id) : ?stdClass {
        // https://developers.themoviedb.org/3/tv/get-tv-external-ids
        try {
            return static::sendGET("/tv/$id/external_ids");
        } catch (Exception $ex) {
            Logger::error("Failed to load show external_ids on TMDB, for ID $id: " . $ex->getMessage());
        }
        return NULL;
    }

    public static function getSuggestedShows(int $page = 1) : array {
        if ($page == 1) {
            $_SESSION['tmdb_suggested_tv_ids'] = [];
        }

        $shows = static::getPopularShows($page);

        $shows_ = [];
        foreach ($shows as $show) {
            if (array_contains($_SESSION['tmdb_suggested_tv_ids'], $show->id)) {
                continue;
            }
            if (empty($show->poster_path)) {
                Logger::info("Empty poster found for $show->title (ID $show->id).");
            }
            $shows_[$show->id] = $show;
        }
        $shows = $shows_;
        unset($shows_);

        static::addAvailability($shows);

        usort($shows, ['\PommePause\Dropinambour\TMDB', 'sortSuggestedMedias']);

        $_SESSION['tmdb_suggested_tv_page'] = $page;
        $_SESSION['tmdb_suggested_tv_ids'] = array_merge($_SESSION['tmdb_suggested_tv_ids'], getPropValuesFromArray($shows, 'id'));

        return $shows;
    }

    public static function getMoreSuggestedShows() : array {
        return static::getSuggestedShows($_SESSION['tmdb_suggested_tv_page'] + 1);
    }

    public static function getPopularShows(int $page = 1, string $lang = 'en') : array {
        // https://developers.themoviedb.org/3/movies/get-popular-movies
        $response = static::sendGET("/tv/popular?page=$page&language=$lang");
        $response->results = array_map([self::class, 'nameToTitle'], $response->results);
        $response->results = array_map([self::class, 'addMediaTypeTV'], $response->results);
        return $response->results;
    }

    public static function getNowPlayingShows(int $page = 1, string $lang = 'en') : array {
        // https://developers.themoviedb.org/3/trending/get-trending
        $response = static::sendGET("/tv/on_the_air?page=$page&language=$lang");
        $response->results = array_map([self::class, 'nameToTitle'], $response->results);
        $response->results = array_map([self::class, 'addMediaTypeTV'], $response->results);
        return $response->results;
    }

    public static function getTopRatedShows(int $page = 1, string $lang = 'en') : array {
        // https://developers.themoviedb.org/3/trending/get-trending
        $response = static::sendGET("/tv/top_rated?page=$page&language=$lang");
        $response->results = array_map([self::class, 'nameToTitle'], $response->results);
        $response->results = array_map([self::class, 'addMediaTypeTV'], $response->results);
        return $response->results;
    }

    /* Pragma mark - API */

    private static function getBaseURL() : string {
        return 'https://api.themoviedb.org/3';
    }

    private static function getBearerToken() : string {
        return 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiI0YzM4YzE5OTU1NGQ5ZTEzOGFiNTcxMzAzYWM0MWE1YyIsInN1YiI6IjRiYzg4OTM1MDE3YTNjMGY5MjAwMGEwMSIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.VR3nyr5ZdLsYPqf5hq_uIJVaWDIw3l0ja7X9c8CdmdQ';
    }

    private static function sendGET($url) {
        $token = static::getBearerToken();
        Logger::debug("TMDB::sendGET($url)");
        $response = sendGET(static::getBaseURL() . $url, ["Accept: application/json", "Authorization: Bearer $token", "Content-Type: application/json;charset=utf-8"]);
        $response = json_decode($response);
        return $response;
    }

    /* Pragma mark - Internal functions */

    private static function sortByVote($media1, $media2) {
        return ($media2->vote_average ?? 0) <=> ($media1->vote_average ?? 0);
    }

    private static function addExternalIDs(&$medias) : void {
        if (empty($medias)) {
            return;
        }

        $medias_by_id = [];
        $shows = [];
        $movies = [];
        foreach ($medias as $media) {
            $medias_by_id[$media->media_type . $media->id] = $media;
            if ($media->media_type == 'tv') {
                $shows[(int)$media->id] = $media;
            } else {
                $movies[(int)$media->id] = $media;
            }
        }

        // Load known external IDs from our own DB
        if (!empty($movies)) {
            $tmdb_ids = array_keys($movies);
            $q = "SELECT * FROM tmdb_external_ids WHERE tmdb_id IN (:ids)";
            $guids = DB::getAll($q, ['ids' => $tmdb_ids]);
            foreach ($guids as $guid) {
                if (isset($medias_by_id['movie'.$guid->tmdb_id])) {
                    $medias_by_id['movie'.$guid->tmdb_id]->imdb_id = $guid->imdb_id;
                    $medias_by_id['movie'.$guid->tmdb_id]->tvdb_id = $guid->tvdb_id;
                }
            }
        }
        if (!empty($shows)) {
            $tmdb_ids = array_keys($shows);
            $q = "SELECT * FROM tmdb_external_ids WHERE tmdbtv_id IN (:ids)";
            $guids = DB::getAll($q, ['ids' => $tmdb_ids]);
            foreach ($guids as $guid) {
                if (isset($medias_by_id['tv'.$guid->tmdbtv_id])) {
                    $medias_by_id['tv' . $guid->tmdbtv_id]->imdb_id = $guid->imdb_id;
                    $medias_by_id['tv' . $guid->tmdbtv_id]->tvdb_id = $guid->tvdb_id;
                }
            }
        }

        // Fetch missing IMDB IDs from TMDB API
        foreach ($medias_by_id as $media) {
            if ($media->media_type == 'tv') {
                $pk = 'tmdbtv_id';
            } else {
                $pk = 'tmdb_id';
            }

            if (@$media->imdb_id === NULL || @$media->tvdb_id === NULL) {
                if (isset($media->external_ids)) {
                    $external_ids = $media->external_ids;
                } else {
                    if ($media->media_type == 'tv') {
                        // TV Show
                        Logger::info("Loading show external IDs from TMDB API, to get IMDB/TVDB ID: $media->title (ID $media->id)");
                        $external_ids = TMDB::getShowExternalIDs($media->id);
                    } else {
                        // Movie
                        Logger::info("Loading movie external IDs from TMDB API, to get IMDB/TVDB ID: $media->title (ID $media->id)");
                        $external_ids = TMDB::getMovieExternalIDs($media->id);
                    }
                }

                if (!empty($external_ids->imdb_id)) {
                    $media->imdb_id = $external_ids->imdb_id;
                } else {
                    $media->imdb_id = 0;
                    if ($media->media_type == 'movie') {
                        Logger::warning("Empty IMDB ID found for $media->title (ID $media->id)");
                    }
                }
                if (!empty($external_ids->tvdb_id)) {
                    $media->tvdb_id = $external_ids->tvdb_id;
                } else {
                    $media->tvdb_id = 0;
                    if ($media->media_type == 'tv') {
                        Logger::warning("Empty TVDB ID found for $media->title (ID $media->id)");
                    }
                }
            }

            $q = "INSERT INTO tmdb_external_ids SET $pk = :tmdb_id, imdb_id = :imdb_id, tvdb_id = :tvdb_id ON DUPLICATE KEY UPDATE imdb_id = VALUES(imdb_id), tvdb_id = VALUES(tvdb_id)";
            DB::insert($q, ['tmdb_id' => $media->id, 'imdb_id' => @$media->imdb_id, 'tvdb_id' => $media->tvdb_id]);
        }

        $medias = array_values($medias_by_id);
    }

    private static function addAvailability(&$medias) : void {
        if (empty($medias)) {
            return;
        }
        static::addExternalIDs($medias);

        $medias_by_id = [];
        $shows = [];
        $movies = [];
        foreach ($medias as $media) {
            $medias_by_id[$media->media_type . $media->id] = $media;
            if ($media->media_type == 'tv') {
                $shows[(int)$media->id] = $media;
            } else {
                $movies[(int)$media->id] = $media;
            }
        }

        if (!empty($shows)) {
            $requests = Request::getAllShowRequests(return_dupes: TRUE);
            foreach ($shows as $media) {
                $media->requested = $requests["tvdb:$media->tvdb_id"] ?? $requests["tmdb:$media->id"] ?? NULL;
            }

            $available_tvdb_ids = AvailableMedia::getAllTVDBIDs();
            $available_tmdb_ids = AvailableMedia::getAllTMDBTVIDs();
            foreach ($shows as $key => $media) {
                $media->is_available = !empty($media->tvdb_id) && array_contains($available_tvdb_ids, $media->tvdb_id) || array_contains($available_tmdb_ids, $media->id);
                if ($media->is_available) {
                    if (count($medias_by_id) == 1) {
                        $q = "SELECT m.* FROM available_medias m JOIN available_medias_guids ids ON (ids.media_id = m.id) WHERE (ids.source = 'tvdb' AND ids.source_id = :tvdb_id) OR (ids.source = 'tmdbtv' AND ids.source_id = :tmdbtv_id)";
                        $plex_media = DB::getFirst($q, ['tvdb_id' => $media->tvdb_id, 'tmdbtv_id' => $media->id]);
                        $media->plex_url = Plex::geUrlForMediaKey($plex_media->key);
                    }

                    // Check which seasons are available (completely, or partially), and which seasons are not

                    if (empty($media->seasons)) {
                        // Search result don't include all the details we need to identify completely/partially available shows
                        $full_media = static::getDetailsTV($media->id, $_GET['language'] ?? 'en', FALSE);
                        $full_media->is_available = $media->is_available;
                        $full_media->requested = $media->requested;
                        $media = $full_media;
                        $shows[$key] = $media;
                    }

                    $local_media_id = array_search($media->tvdb_id, $available_tvdb_ids);
                    if (empty($local_media_id)) {
                        $local_media_id = array_search($media->id, $available_tmdb_ids);
                    }
                    $q = "SELECT * FROM available_episodes WHERE media_id = :media_id";
                    $available_seasons = DB::getAll($q, $local_media_id, 'season');

                    if (count($medias_by_id) == 1 && !empty($media->requested)) {
                        $q = "SELECT season, monitored FROM requested_episodes WHERE request_id = :req_id";
                        $monitored_seasons = DB::getAll($q, $media->requested->id, 'season');
                        $monitored_seasons = getPropValuesFromArray($monitored_seasons, 'monitored', TRUE);
                    }

                    $media->available_seasons = [];
                    $media->not_available_seasons = [];
                    foreach ($media->seasons ?? [] as $season) {
                        if ($season->season_number == 0) {
                            // We don't really care for specials...
                            continue;
                        }
                        $season->monitored = $monitored_seasons[$season->season_number] ?? FALSE;

                        // Sometimes, $season->episode_count is too high (larger than $media->last_episode_to_air->episode_number); fix that:
                        if ($season->season_number == @$media->last_episode_to_air->season_number && $season->episode_count > $media->last_episode_to_air->episode_number) {
                            $season->episode_count = $media->last_episode_to_air->episode_number;
                        }

                        if (@$available_seasons[$season->season_number]->episodes >= $season->episode_count && $season->episode_count > 0) {
                            // All episodes are available for this season
                            $season->is_available = TRUE;
                            $media->available_seasons[] = sprintf("S%02d", $season->season_number);
                        } elseif (@$available_seasons[$season->season_number]->episodes > 0) {
                            // Only some episodes are available for this season
                            $season->is_available = 'partially';
                            $media->available_seasons[] = sprintf("S%02d (partial)", $season->season_number);
                            if (@$media->next_episode_to_air->season_number == $season->season_number) {
                                // Next episode to air = this season; it's normal that we don't have it completely yet
                            } else {
                                $media->is_available = 'partially';
                            }
                        } else {
                            // No episodes are available for this season
                            $season->is_available = FALSE;
                            if (@$media->next_episode_to_air->season_number == $season->season_number && $media->next_episode_to_air->episode_number == 1) {
                                // Next episode to air = this season; it's normal that we don't have it yet
                            } elseif ($season->episode_count > 0) {
                                $media->not_available_seasons[] = sprintf("S%02d", $season->season_number);
                                $media->is_available = 'partially';
                            }
                        }
                    }
                }
            }

            foreach ($shows as $media) {
                $medias_by_id['tv' . $media->id] = $media;
            }
        }

        if (!empty($movies)) {
            $requests = Request::getAllMovieRequests();
            foreach ($movies as $media) {
                $media->requested = $requests["tmdb:$media->id"] ?? NULL;
            }

            $available_tmdb_ids = AvailableMedia::getAllTMDBIDs();
            $available_imdb_ids = AvailableMedia::getAllIMDBIDs();
            foreach ($movies as $media) {
                $media->is_available = array_contains($available_tmdb_ids, $media->id) || (!empty($media->imdb_id) && array_contains($available_imdb_ids, $media->imdb_id));

                if ($media->is_available && count($medias_by_id) == 1) {
                    $q = "SELECT m.* FROM available_medias m JOIN available_medias_guids ids ON (ids.media_id = m.id) WHERE (ids.source = 'tmdb' AND ids.source_id = :tmdb_id) OR (ids.source = 'imdb' AND ids.source_id = :imdb_id)";
                    $plex_media = DB::getFirst($q, ['tmdb_id' => $media->id ?? -1, 'imdb_id' => $media->imdb_id ?? -1]);
                    $media->plex_url = Plex::geUrlForMediaKey($plex_media->key);
                }
            }

            foreach ($movies as $media) {
                $medias_by_id['movie' . $media->id] = $media;
            }
        }

        $medias = array_values($medias_by_id);
    }

    private static function nameToTitle($show) {
        if (!empty($show->name)) {
            $show->title = $show->name;
        }
        return $show;
    }

    private static function addMediaTypeTV($show) {
        $show->media_type = 'tv';
        return $show;
    }

    private static function addMediaTypeMovie($show) {
        $show->media_type = 'movie';
        return $show;
    }
}
