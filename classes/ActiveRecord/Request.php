<?php

namespace PommePause\Dropinambour\ActiveRecord;

use PommePause\Dropinambour\Config;
use PommePause\Dropinambour\DB;
use PommePause\Dropinambour\DBQueryBuilder;
use PommePause\Dropinambour\Logger;
use PommePause\Dropinambour\Mailer;
use PommePause\Dropinambour\Plex;
use PommePause\Dropinambour\Sonarr;
use PommePause\Dropinambour\TMDB;

class Request extends AbstractActiveRecord
{
    public const TABLE_NAME = 'requests';

    public $id;
    public $external_id;
    public $monitored_by; // sonarr, radarr, none
    public $type; // movie or show
    public $requested_by;
    public $quality_profile;
    public $language_profile;
    public $path;
    public $title;
    public $monitored;
    public $imdb_id;
    public $tmdb_id;
    public $tvdb_id;
    public $tmdbtv_id;
    public $added_when;
    public $filled_when;
    public $notified_when;

    protected function skipParamOnDuplicateKey(string $param_name) : bool {
        return ( $param_name == 'requested_by' );
    }

    protected function skipParamOnSave(string $param_name) : bool {
        return ( $param_name == 'media_type' );
    }

    protected function setQueryParameterRequested_by($builder, $value) {
        if (is_object($value)) {
            $builder->set('requested_by', "$value->username <$value->email>");
        } else {
            $builder->set('requested_by', $value);
        }
    }

    public function save(?DBQueryBuilder $builder = NULL, bool $update_if_exists = TRUE) : bool {
        if (empty($this->id)) {
            $q = "SELECT id FROM requests WHERE monitored_by = :monitored_by AND external_id = :external_id";
            $this->id = DB::getFirstValue($q, ['monitored_by' => $this->monitored_by, 'external_id' => $this->external_id]);
        }
        if (empty($this->id)) {
            return parent::save($builder, FALSE);
        }
        return $this->update($this->id);
    }

    public function notifyAdminRequestAdded(?int $season_number = NULL) : void {
        $this->media_type = ($this->type == 'show' ? 'TV show' : 'movie');
        Mailer::sendFromTemplate(Config::get('NEW_REQUESTS_NOTIF_EMAIL'), "New request: \"$this->title\"", 'request_added', ['request' => $this, 'season_number' => $season_number]);
    }

    public function notifyAdminRequestRemoved() : void {
        $this->media_type = ($this->type == 'show' ? 'TV show' : 'movie');
        Mailer::sendFromTemplate(Config::get('NEW_REQUESTS_NOTIF_EMAIL'), "Request removed: \"$this->title\"", 'request_removed', ['request' => $this]);
    }

    public function notifyIsFilled() : void {
        $this->reload(); // Get .notified_when from DB
        if (!empty($this->notified_when)) {
            // Already notified;
            return;
        }

        // Request has been filled; send notification
        if ($this->type == 'movie') {
            $this->media_type = 'movie';
        } else {
            $this->media_type = 'TV show';
        }
        Logger::info("Sending notification about filled request for $this->media_type \"$this->title\".");
        Mailer::sendFromTemplate($this->requested_by->email, "Your request for \"$this->title\" is now available", 'request_available', ['request' => $this]);
        $this->notified_when = date('Y-m-d H:i:s');
        $this->save();
    }

    public static function fromRadarrMovie($movie) : self {
        $m = new self();
        $m->external_id = $movie->id;
        $m->monitored_by = 'radarr';
        $m->type = 'movie';
        $m->requested_by = Plex::getUserInfos()->username . ' <' . Plex::getUserInfos()->email . '>';
        $m->tmdb_id = $movie->tmdbId ?? NULL;
        $m->updateFromRadarrMovie($movie);
        return $m;
    }

    public function updateFromRadarrMovie($movie) : void {
        $this->quality_profile = $movie->qualityProfileId;
        $this->path = $movie->path;
        $this->title = $movie->title;
        $this->monitored = $movie->monitored;
        $this->imdb_id = $movie->imdbId ?? NULL;
        $this->added_when = date('Y-m-d H:i:s', strtotime($movie->added));
    }

    public static function fromSonarrShow($show) : self {
        $m = new self();
        $m->external_id = $show->id;
        $m->monitored_by = 'sonarr';
        $m->type = 'show';
        $m->requested_by = Plex::getUserInfos()->username . ' <' . Plex::getUserInfos()->email . '>';
        $m->tvdb_id = $show->tvdbId ?? NULL;
        $m->updateFromSonarShow($show);
        return $m;
    }

    public function updateFromSonarShow($show) : void {
        $this->quality_profile = $show->qualityProfileId;
        $this->language_profile = $show->languageProfileId;
        $this->path = $show->path;
        $this->title = $show->title;
        $this->monitored = $show->monitored;
        $this->imdb_id = $show->imdbId ?? NULL;
        $this->added_when = date('Y-m-d H:i:s', strtotime($show->added));
    }

    public function addSeasonsFromSonarrShow($show) : void {
        foreach ($show->seasons as $season) {
            if ($season->monitored && $season->statistics->totalEpisodeCount > $season->statistics->episodeCount) {
                // Some missing episodes; is it because they are not monitored?
                $episodes = Sonarr::getShowEpisodes($show->id);
                foreach ($episodes as $episode) {
                    if ($episode->seasonNumber == $season->seasonNumber && !$episode->monitored) {
                        $season->monitored = FALSE;
                        break;
                    }
                }
            }

            $q = "INSERT INTO requested_episodes SET request_id = :req_id, season = :season, monitored = :monitored, episodes = :episodes ON DUPLICATE KEY UPDATE monitored = VALUES(monitored), episodes = VALUES(episodes)";
            $params = ['req_id' => $this->id, 'season' => $season->seasonNumber, 'monitored' => $season->monitored, 'episodes' => $season->statistics->totalEpisodeCount ?? NULL];
            DB::insert($q, $params);
        }
    }

    public static function fromTMDBShow($show) : self {
        $m = new self();
        $m->external_id = $show->id;
        $m->monitored_by = 'none';
        $m->type = 'show';
        $m->requested_by = Plex::getUserInfos()->username . ' <' . Plex::getUserInfos()->email . '>';
        $m->tvdb_id = NULL;
        $m->title = $show->title;
        $m->monitored = FALSE;
        $m->imdb_id = empty($show->imdb_id) ? NULL : $show->imdb_id;
        $m->added_when = date('Y-m-d H:i:s');
        return $m;
    }

    public static function getOne($value, ?string $key = NULL, ?DBQueryBuilder $builder = NULL, int $options = 0) : static|FALSE {
        $request = parent::getOne($value, $key, $builder, $options);
        return static::postProcessRequestRowFromDB($request);
    }

    /**
     * @return self[]
     */
    public static function getAllMovieRequests(bool $order_by_name = FALSE) : array {
        $q = "SELECT r.*, c.details FROM requests r LEFT JOIN tmdb_cache c ON (c.tmdb_id = r.tmdb_id) WHERE r.type = 'movie' AND NOT r.hidden";
        if ($order_by_name) {
            $q .= " ORDER BY r.title";
        }
        return static::getRequestsFromQuery($q);
    }

    /**
     * @return self[]
     */
    public static function getAllShowRequests(bool $order_by_name = FALSE) : array {
        $q = "SELECT r.*, IFNULL(r.tmdbtv_id, IF(ids.tmdbtv_id <= 0, 0, ids.tmdbtv_id)) AS tmdbtv_id, c.details
                FROM requests r
                LEFT JOIN tmdb_external_ids ids ON (ids.tvdb_id = r.tvdb_id)
                LEFT JOIN tmdb_cache c ON (c.tmdbtv_id = r.tmdbtv_id)
               WHERE r.type = 'show'
                 AND NOT r.hidden
               GROUP BY r.id";
        if ($order_by_name) {
            $q .= " ORDER BY r.title";
        }
        return static::getRequestsFromQuery($q);
    }

    /**
     * @return self[]
     */
    public static function getOpenMovieRequests() : array {
        $q = "SELECT r.* FROM requests r WHERE r.type = 'movie' AND r.notified_when IS NULL AND NOT r.hidden";
        return static::getRequestsFromQuery($q);
    }

    /**
     * @return self[]
     */
    public static function getOpenShowRequests() : array {
        $q = "SELECT r.*
                FROM requests r
               WHERE r.type = 'show'
                 AND r.notified_when IS NULL
                 AND NOT r.hidden";
        return static::getRequestsFromQuery($q);
    }

    private static function postProcessRequestRowFromDB(self $row) : self {
        if (preg_match('/^(.*) <(.*)>$/', $row->requested_by, $re)) {
            $row->requested_by = (object) [
                'username' => $re[1],
                'email'    => $re[2],
            ];
        } else {
            Logger::error("Invalid value for requests.requested_by: $row->requested_by");
        }
        if (!empty($row->details) && is_string($row->details)) {
            $row->details = json_decode($row->details);
        } else {
            if (!empty($row->tmdb_id)) {
                $row->details = TMDB::getDetailsMovie($row->tmdb_id, NULL, FALSE, TRUE);
            } elseif (!empty($row->tmdbtv_id)) {
                $row->details = TMDB::getDetailsTV($row->tmdbtv_id, NULL, FALSE, TRUE);
            }
        }
        if ($row->monitored_by == 'sonarr' && @$row->tmdbtv_id === NULL && property_exists($row, 'tmdbtv_id')) {
            $tmdb_media = TMDB::getDetailsByExternalId($row->tvdb_id, 'tvdb');
            if (empty($tmdb_media) && !empty($row->imdb_id)) {
                $tmdb_media = TMDB::getDetailsByExternalId($row->imdb_id, 'imdb');
                if ($tmdb_media && empty($tmdb_media->tvdb_id)) {
                    $q = "INSERT INTO tmdb_external_ids SET tmdbtv_id = :tmdb_id, tvdb_id = :tvdb_id ON DUPLICATE KEY UPDATE tvdb_id = VALUES(tvdb_id)";
                    DB::insert($q, ['tmdb_id' => $tmdb_media->id, 'tvdb_id' => $row->tvdb_id]);
                }
            }
            if (!empty($tmdb_media)) {
                $row->tmdbtv_id = $tmdb_media->id;
            } else {
                $q = "INSERT INTO tmdb_external_ids SET tmdbtv_id = :tmdb_id, imdb_id = :imdb_id, tvdb_id = :tvdb_id ON DUPLICATE KEY UPDATE tvdb_id = VALUES(tvdb_id)";
                DB::insert($q, ['tmdb_id' => -$row->tvdb_id, 'imdb_id' => 0, 'tvdb_id' => $row->tvdb_id]);
            }
        }
        return $row;
    }

    /**
     * @return self[]
     */
    private static function getRequestsFromQuery(string $query) : array {
        $rows = static::getAll($query);
        /** @var $rows self[] */
        foreach ($rows as $k => $row) {
            unset($rows[$k]);
            $row = static::postProcessRequestRowFromDB($row);
            if ($row->type == 'movie') {
                $rows["tmdb:$row->tmdb_id"] = $row;
            } else {
                if (!empty($row->tvdb_id)) {
                    $rows["tvdb:$row->tvdb_id"] = $row;
                } else {
                    $rows["tmdb:$row->external_id"] = $row;
                }
            }
        }
        return $rows;
    }
}
