<?php

namespace PommePause\Dropinambour\ActiveRecord;

use Exception;
use PommePause\Dropinambour\DB;
use PommePause\Dropinambour\DBQueryBuilder;
use PommePause\Dropinambour\Logger;
use PommePause\Dropinambour\Plex;
use PommePause\Dropinambour\TMDB;

class AvailableMedia extends AbstractActiveRecord
{
    public const TABLE_NAME = 'available_medias';

    public $id;
    public $title;
    public $year;
    public $type;
    public $key;
    public $section_id;
    public $guid;
    public $added_when;

    public function save(?DBQueryBuilder $builder = NULL) : bool {
        $result = parent::save($builder);
        if (empty($this->id)) {
            $q = "SELECT id FROM available_medias WHERE section_id = :section_id AND guid = :guid";
            $this->id = DB::getFirstValue($q, ['section_id' => $this->section_id, 'guid' => $this->guid]);
        }
        return $result;
    }

    public static function fromPlexItem($plex_item) : self {
        $m = new self();

        if ($plex_item->type == 'season') {
            // Remove season number from GUID
            $plex_item->guid = preg_replace('@(com.plexapp.agents.thetvdb://\d+)/\d+(\?lang=.*)$@', '\1\2', $plex_item->guid);

            // Convert seasons to TV shows
            $plex_item->key = $plex_item->parentKey;
            $plex_item->title = $plex_item->parentTitle;
            $plex_item->type = 'show';
        }

        // For TV shows, remove the /children from the end of the key
        $plex_item->key = preg_replace('@/children$@', '', $plex_item->key);

        $m->title = $plex_item->title;
        $m->year = $plex_item->year;
        $m->type = $plex_item->type;
        $m->key = $plex_item->key;
        $m->section_id = $plex_item->section->id;
        $m->guid = $plex_item->guid;
        $m->added_when = date('Y-m-d H:i:s', $plex_item->addedAt);
        return $m;
    }

    public static function importRecentMediasFromPlex() {
        static::importAvailableMediasFromPlex(Plex::SECTION_RECENTLY_ADDED);
    }

    public static function importAvailableMediasFromPlex(?int $section = NULL) {
        Logger::info("Importing available medias from Plex...");
        DB::startTransaction();

        try {
            if (empty($section)) {
                DB::execute("TRUNCATE available_medias");
                DB::execute("TRUNCATE available_medias_guids");
                DB::execute("TRUNCATE available_episodes");

                $items = Plex::getAllSectionsItems();
            } elseif ($section == Plex::SECTION_RECENTLY_ADDED) {
                $items = Plex::getRecentlyAddedItems();
            } else {
                $items = Plex::getSectionItems($section);
            }

            $sections = [];
            foreach ($items as $item) {
                $sections[$item->section->id] = $item->section;
            }
            foreach ($sections as $section) {
                $q = "INSERT IGNORE INTO sections SET id = :id, name =:name";
                DB::insert($q, ['id' => $section->id, 'name' => $section->name]);
            }

            $open_movie_requests = Request::getOpenMovieRequests();
            $open_show_requests = Request::getOpenShowRequests();

            foreach ($items as $item) {
                $media = AvailableMedia::fromPlexItem($item);
                $media->save();
                Logger::info("  - Added $media->type '$media->title' (media ID $media->id)");
                $media->importGUIDs();

                if ($media->type == 'show') {
                    // Import episodes
                    $season_mds = Plex::getItemMetadata($item->key . '/children', TRUE);
                    foreach ($season_mds as $season_md) {
                        $season = $season_md->index;
                        $num_episodes = $season_md->leafCount;
                        $q = "INSERT INTO available_episodes SET media_id = :media_id, season = :season, episodes = :episodes ON DUPLICATE KEY UPDATE episodes = VALUES(episodes)";
                        DB::insert($q, ['media_id' => $media->id, 'season' => $season, 'episodes' => $num_episodes]);
                    }
                }

                if (!empty($media->tmdb_id) && isset($open_movie_requests[$media->tmdb_id])) {
                    $req = $open_movie_requests[$media->tmdb_id];
                    if (empty($req->filled_when)) {
                        $req->filled_when = date('Y-m-d H:i:s');
                        $req->save();
                        $req->notifyIfFilled();
                    }
                }
                if (!empty($media->tvdb_id) && isset($open_show_requests[$media->tvdb_id])) {
                    $req = $open_show_requests[$media->tvdb_id];
                    if (empty($req->filled_when)) {
                        $req->filled_when = date('Y-m-d H:i:s');
                        $req->save();
                        $req->notifyIfFilled();
                    }
                }
            }

            DB::commitTransaction();
            Logger::info("Done importing available medias from Plex.");
        } catch (Exception $ex) {
            DB::rollbackTransaction();
            throw $ex;
        }
    }

    private function importGUIDs() {
        if ($this->type != 'movie') {
            if (preg_match('@thetvdb://(\d+)@', $this->guid, $re)) {
                $q = "INSERT INTO available_medias_guids SET media_id = :media_id, source = :source, source_id = :id ON DUPLICATE KEY UPDATE source_id = VALUES(source_id)";
                DB::insert($q, ['media_id' => $this->id, 'source' => 'tvdb', 'id' => $re[1]]);
                $this->tvdb_id = (int) $re[1];
                Logger::info("    - Added GUIDs for show '$this->title': TVDB ID = $this->tvdb_id");
            } elseif (preg_match('@themoviedb://(\d+)@', $this->guid, $re)) {
                // Show is matched to TheMovieDB; need to use TMDB API to get (at least) the TVDB ID

                $all_guids = [];

                $this->tmdbtv_id = (int) $re[1];
                $all_guids[] = "TMDBTV ID = $this->tmdbtv_id";
                $q = "INSERT INTO available_medias_guids SET media_id = :media_id, source = :source, source_id = :id ON DUPLICATE KEY UPDATE source_id = VALUES(source_id)";
                DB::insert($q, ['media_id' => $this->id, 'source' => 'tmdbtv', 'id' => $re[1]]);

                $external_ids = TMDB::getShowExternalIDs($this->tmdbtv_id);
                if (!empty($external_ids->imdb_id)) {
                    $this->imdb_id = $external_ids->imdb_id;
                    $all_guids[] = "IMDB ID = $this->imdb_id";
                    $q = "INSERT INTO available_medias_guids SET media_id = :media_id, source = :source, source_id = :id ON DUPLICATE KEY UPDATE source_id = VALUES(source_id)";
                    DB::insert($q, ['media_id' => $this->id, 'source' => 'imdb', 'id' => $this->imdb_id]);
                }
                if (!empty($external_ids->tvdb_id)) {
                    $this->tvdb_id = $external_ids->tvdb_id;
                    $all_guids[] = "TVDB ID = $this->tvdb_id";
                    $q = "INSERT INTO available_medias_guids SET media_id = :media_id, source = :source, source_id = :id ON DUPLICATE KEY UPDATE source_id = VALUES(source_id)";
                    DB::insert($q, ['media_id' => $this->id, 'source' => 'tvdb', 'id' => $this->tvdb_id]);
                }

                Logger::info("    - Added GUIDs for show '$this->title': " . implode(', ', $all_guids));
            } else {
                $section_name = Plex::getSectionNameById($this->section_id);
                Logger::warning("Plex show \"$this->title\" (key = $this->key) in section {$section_name} (ID = {$this->section_id}) has no GUID. Use TheTVDB agent for this section to fix this.");
            }
            return;
        }

        $md = Plex::getItemMetadata($this->key);
        if (empty($md)) {
            Logger::error("Couldn't load metadata for $this->title ($this->year).");
            return;
        }
        if (!isset($md->Guid)) {
            $section_name = Plex::getSectionNameById($this->section_id);
            Logger::warning("Plex movie \"$this->title\" (key = $this->key) in section {$section_name} (ID = {$this->section_id}) has no GUID. Use 'Fix match...' in Plex to fix.");
            return;
        }

        $q = "DELETE FROM available_medias_guids WHERE media_id = :media_id";
        DB::execute($q, $this->id);

        $all_guids = [];
        $q = "INSERT INTO available_medias_guids SET media_id = :media_id, source = :source, source_id = :id ON DUPLICATE KEY UPDATE source_id = VALUES(source_id)";
        foreach ($md->Guid as $guid) {
            if (!preg_match('@(.+)://(.+)$@', $guid->id, $re)) {
                Logger::error("Invalid GUID: $guid");
            }
            if ($re[1] == 'tvdb') {
                // TheTVDB Movie ID
                $re[1] = 'tvdbm';
            }
            $this->{$re[1].'_id'} = $re[2];
            DB::insert($q, ['media_id' => $this->id, 'source' => $re[1], 'id' => $re[2]]);
            $all_guids[] = strtoupper($re[1]) . " ID = $re[2]";
        }
        Logger::info("    - Added GUIDs for movie '$this->title': " . implode(', ', $all_guids));
    }

    public static function getAllTMDBIDs() : array {
        $q = "SELECT source_id FROM available_medias_guids WHERE source = 'tmdb'";
        return DB::getAllValues($q, [], 'integer');
    }

    public static function getAllIMDBIDs() : array {
        $q = "SELECT source_id FROM available_medias_guids WHERE source = 'imdb'";
        return DB::getAllValues($q);
    }

    public static function getAllTVDBIDs() : array {
        $q = "SELECT media_id, source_id FROM available_medias_guids WHERE source = 'tvdb'";
        return getPropValuesFromArray(DB::getAll($q, [], 'media_id'), 'source_id', TRUE);
    }

    public static function getClassForMedia($media) : string {
        if ($media->is_available === 'partially') {
            $class = 'available_partially';
        } elseif (!empty($media->is_available)) {
            $class = 'available';
        } elseif (!empty($media->requested)) {
            $class = 'requested';
        } else {
            $class = 'na';
        }
        return $class;
    }
}
