<?php

namespace PommePause\Dropinambour;

use Exception;
use JetBrains\PhpStorm\ArrayShape;
use PommePause\Dropinambour\ActiveRecord\AvailableMedia;
use PommePause\Dropinambour\ActiveRecord\Request;
use PommePause\Dropinambour\Exceptions\PlexException;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

class AppController extends AbstractController
{
    /* pragma mark - Webpages: home, media, collection, search */

    public function viewRoot() : Response {
        if (Plex::needsAuth()) {
            if (Plex::checkAuthPIN()) {
                // Login succeeded, reload page
                sleep(1);
                Logger::info("PIN auth succeeded. Refreshing page.");
                return $this->redirectResponse('./');
            } else {
                // Show login page
                return $this->response($this->render('/login'));
            }
        }

        if (Plex::isServerAdmin()) {
            Config::setInDB('PLEX_ACCESS_TOKEN', $_SESSION['PLEX_ACCESS_TOKEN']);
        }

        return $this->response($this->render('/discover'));
    }

    public function ajaxCheckLogin() : Response {
        return $this->jsonResponse(['login_success' => (!Plex::needsAuth() || Plex::checkAuthPIN())]);
    }

    public function ajaxMoreMovies() : Response {
        $movies = TMDB::getMoreSuggestedMovies();
        return $this->response($this->render('/media_items', ['medias' => $movies]));
    }

    public function ajaxMoreShows() : Response {
        $shows = TMDB::getMoreSuggestedShows();
        return $this->response($this->render('/media_items', ['medias' => $shows]));
    }

    public function viewMedia() : Response {
        $lang = $this->getQueryParam('language');
        if ($this->getQueryParam('tv')) {
            $media = TMDB::getDetailsTV($this->getQueryParam('tv'), $lang);
        } elseif ($this->getQueryParam('movie')) {
            $media = TMDB::getDetailsMovie($this->getQueryParam('movie'), $lang);
        } else {
            Logger::error("Missing ID in viewMedia()");
            $this->error("400 Bad Request", "Missing ID in viewMedia()");
        }

        $recommended_medias = TMDB::getRecommendations($media, $lang);

        $media->container_class = AvailableMedia::getClassForMedia($media);

        if ($media->media_type == 'tv' && !empty($media->tvdb_id)) {
            $show = Sonarr::lookupShow($media->tvdb_id);
            if ($show) {
                $media->titleSlug = $show->titleSlug;
                $media->images = $show->images;
            }
        }

        $avail_value = $media->is_available ? 'Yes' : 'No';
        if ($media->media_type == 'tv' && ($media->is_available === 'partially' || string_contains(implode(', ', $media->available_seasons ?? []), 'partial'))) {
            // List which seasons are available, and which aren't
            $avail_value .= ": " . implode(', ', $media->available_seasons);
            if (!empty($media->not_available_seasons)) {
                if (!empty($media->available_seasons)) {
                    $avail_value .= "</span> <span class='value'>";
                }
                $avail_value .= 'No: ' . implode(', ', $media->not_available_seasons);
            }
        }

        $stats = [];
        $stats[] = [
            'name'  => "Available on Plex",
            'class' => 'availability',
            'value_html' => '<span class="value ' . ($media->is_available ? 'available' : '') . '">' . $avail_value . '</span>',
        ];

        if (!empty($media->requested)) {
            if (Plex::isServerAdmin()) {
                $stats[] = [
                    'name'  => "Requested by",
                    'class' => 'requested_by',
                    'value' => $media->requested->requested_by->username,
                ];
            }
            if (!$media->requested->monitored) {
                $stats[] = [
                    'name' => "Monitored",
                    'class' => 'requested_by',
                    'value' => 'No',
                ];
            }
        }

        if ($media->media_type == 'tv') {
            $episode_counts = getPropValuesFromArray($media->seasons, 'episode_count');
            $total_episodes = array_sum($episode_counts);
        }

        if (!$media->is_available && empty($media->requested)) {
            $request_form_html = $this->render('request_form_first', static::getRequestFormData($media));
            $stats[] = [
                'name'  => "Request",
                'class' => 'request',
                'value_html' => $request_form_html,
            ];
        } elseif ($media->media_type == 'tv' && ($media->is_available !== TRUE || string_contains(@$media->status, 'Returning')) && $media->status != 'In Production' && $total_episodes > 0 && @$media->requested->monitored_by != 'none') {
            $request_form_html = $this->render('request_form_new_season', static::getRequestFormData($media));
            $stats[] = [
                'name'  => "Request a season",
                'class' => 'request',
                'value_html' => $request_form_html,
            ];
        }

        if ($media->is_available && !empty($media->plex_url)) {
            $stats[] = [
                'name'  => "Open on Plex",
                'class' => 'url',
                'value'     => $media->plex_url,
                'link_text' => 'Click to open on Plex',
            ];
        }
        if (!empty($media->vote_average)) {
            $stats[] = [
                'name'   => "Votes",
                'class'  => 'vote',
                'value'  => round($media->vote_average*10),
                'suffix' => '%',
            ];
        }
        if (!empty($media->episode_run_time)) {
            $stats[] = [
                'name'   => "Run time",
                'class'  => 'runtime',
                'value'  => minutes_to_human(first($media->episode_run_time)),
            ];
        }
        if (!empty($media->runtime)) {
            $stats[] = [
                'name'   => "Run time",
                'class'  => 'runtime',
                'value'  => minutes_to_human($media->runtime),
            ];
        }

        if (!empty($media->release_date)) {
            $stats[] = [
                'name'  => "Release date",
                'class' => 'date',
                'value' => $media->release_date,
            ];
        }
        if (!empty($media->first_air_date) && strtotime($media->first_air_date) < time()) {
            $stats[] = [
                'name'  => "Aired",
                'class' => 'date',
                'value' => $media->first_air_date . (!empty($media->last_air_date) && $media->last_air_date != $media->first_air_date ? " to $media->last_air_date" : ""),
            ];
        }
        if (!empty($media->next_episode_to_air)) {
            $stats[] = [
                'name'  => "Next episode",
                'class' => 'date',
                'value' => is_object($media->next_episode_to_air) ? sprintf("S%02dE%02d", $media->next_episode_to_air->season_number, $media->next_episode_to_air->episode_number) . " on {$media->next_episode_to_air->air_date}" : $media->next_episode_to_air,
            ];
        }
        if (!empty($media->status)) {
            $stats[] = [
                'name'  => "Status",
                'class' => 'status',
                'value' => $media->status,
            ];
        }
        if (!empty($media->seasons)) {
            $fct_map_seasons_eps = function ($season) use ($media) {
                if ($season->name == 'Specials') {
                    return NULL;
                }
                if (preg_match('/Season (\d+)/', $season->name, $re)) {
                    $season->name = sprintf('S%02d', $re[1]);
                }
                return "$season->name ($season->episode_count ep)";
            };
            $episodes = array_map($fct_map_seasons_eps, $media->seasons);
            if (count($episodes) == 1 && first($episodes) == "Season 1 (1)") {
                $episodes = ['N/A'];
            }
            $stats[] = [
                'name'   => "Episodes",
                'class'  => 'episodes',
                'value'  => trim(implode(', ', $episodes), ', '),
            ];
        }
        if (!empty($media->genres)) {
            $stats[] = [
                'name'   => "Genres",
                'class'  => 'genres',
                'value'  => implode(', ', getPropValuesFromArray($media->genres, 'name')),
            ];
        }
        if (!empty($media->original_language)) {
            $stats[] = [
                'name'   => "Original Language",
                'class'  => 'lang',
                'value'  => lang_from_code($media->original_language),
            ];
        }
        if (!empty($media->networks)) {
            $stats[] = [
                'name'   => "Network",
                'class'  => 'networks',
                'value'  => implode(', ', getPropValuesFromArray($media->networks, 'name')),
            ];
        }
        if (!empty($media->created_by)) {
            $stats[] = [
                'name'   => "Created by",
                'class'  => 'created_by',
                'value'  => implode(', ', getPropValuesFromArray($media->created_by, 'name')),
            ];
        }
        if (!empty($media->belongs_to_collection)) {
            $stats[] = [
                'name'      => "Collection",
                'class'     => 'url',
                'value'     => Router::getURL(Router::ACTION_VIEW, Router::VIEW_COLLECTION, ['collection' => $media->belongs_to_collection->id]),
                'link_text' => $media->belongs_to_collection->name,
            ];
        }

        $urls = [];
        if (!empty($media->homepage)) {
            $urls[] = [
                'name'   => "Homepage",
                'value'  => $media->homepage,
            ];
        }
        if (!empty($media->tvdb_id)) {
            $urls[] = [
                'name'   => "TheTVDB",
                'value'  => "https://www.thetvdb.com/?id=$media->tvdb_id&tab=series",
            ];
        }
        if (!empty($media->imdb_id)) {
            $urls[] = [
                'name'   => "IMDB",
                'value'  => "https://www.imdb.com/title/$media->imdb_id/",
            ];
        }
        if (!empty($media->media_type == 'tv')) {
            $urls[] = [
                'name'   => "TheMovieDB",
                'value'  => "https://www.themoviedb.org/tv/$media->id",
            ];
        } else {
            $urls[] = [
                'name'   => "TheMovieDB",
                'value'  => "https://www.themoviedb.org/movie/$media->id",
            ];
        }
        foreach ($media->videos?->results ?? [] as $video) {
            if ($video->site == 'YouTube') {
                $urls[] = [
                    'name'   => "YouTube: $video->type",
                    'value'  => "https://youtu.be/$video->key",
                ];
            }
        }

        $stats = array_map('to_object', $stats);
        $urls = array_map('to_object', $urls);

        return $this->response($this->render('/tmdb_media', ['media' => $media, 'recommended_medias' => $recommended_medias, 'stats' => $stats, 'urls' => $urls]));
    }

    #[ArrayShape(['media' => "object", 'paths' => "array", 'profiles' => "array", 'default_path' => "string", 'default_quality' => "int", 'default_language' => "int|string", 'default_tags' => "string"])]
    private static function getRequestFormData(stdClass $media) : array {
        return [
            'media' => $media,
            'paths' => $media->media_type == 'movie' ? Radarr::getConfigPaths() : Sonarr::getConfigPaths(),
            'profiles' => $media->media_type == 'movie' ? Radarr::getQualityProfiles() : Sonarr::getQualityProfiles(),
            'default_path' => $media->media_type == 'movie' ? Radarr::getDefaultPath($media) : Config::getFromDB('SONARR_DEFAULT_PATH'),
            'default_quality' => $media->media_type == 'movie' ? (int) Radarr::getDefaultQuality($media) : (int) Config::getFromDB('SONARR_DEFAULT_QUALITY'),
            'default_language' => $media->media_type == 'movie' ? '' : (int) Config::getFromDB('SONARR_DEFAULT_LANGUAGE'),
            'default_tags' => $media->media_type == 'movie' ? Radarr::getDefaultTags($media) : '',
        ];
    }

    public function viewCollection() : Response {
        $collection = TMDB::getDetailsCollection($this->getQueryParam('collection'));
        return $this->response($this->render('/tmdb_collection', ['collection' => $collection]));
    }

    public function search() : Response {
        if ($this->getQueryParam('query')) {
            $search_results = TMDB::searchMulti($this->getQueryParam('query'), $this->getQueryParam('language'));
            $this->addData(['search_results' => $search_results]);
        }
        return $this->response($this->render('/search'));
    }

    public function saveRequest() : Response {
        if (!empty($_POST['req_id'])) {
            // Add a season to an existing request
            $request = Request::getOne($_POST['req_id']);

            if (empty($request)) {
                $this->showError("Error: request ID {$_POST['req_id']} not found.");
            } else {
                Sonarr::addSeason($request->external_id, $_POST['season']);
                $this->showAlert(sprintf("Added request for S%02d of \"$request->title\".", $_POST['season']));
            }
        } elseif ($_POST['media_type'] == 'movie') {
            Radarr::addMovie($_POST['tmdb_id'], $_POST['title'], $_POST['quality'], $_POST['path'], $_POST['tags']);
            $this->showAlert("Added request for \"{$_POST['title']}\" movie.");
        } elseif ($_POST['media_type'] == 'tv') {
            $tvdb_id = $_POST['tvdb_id'];
            if (!empty($tvdb_id)) {
                try {
                    Sonarr::addShow($_POST['tmdb_id'], $_POST['tvdb_id'], $_POST['title'], $_POST['title_slug'], $_POST['quality'], $_POST['language'], $_POST['path'], $_POST['season'], json_decode($_POST['images_json']));
                } catch (Exception $ex) {
                    // Can happen, for example, when the serie exists on TheTVDB, but has no English translation; eg. https://www.thetvdb.com/?id=395619&tab=series
                    Logger::error("Failed to add request on Sonarr; will create request unmonitored. Exception: " . $ex->getMessage());
                    $tvdb_id = NULL;
                }
            }
            if (empty($tvdb_id)) {
                $show = TMDB::getDetailsTV($_POST['tmdb_id']);
                $request = Request::fromTMDBShow($show);
                $request->tmdbtv_id = $_POST['tmdb_id'];
                $request->save();
                $request->notifyAdminRequestAdded($_POST['season'] ?? 1);
            }
            $this->showAlert("Added request for \"{$_POST['title']}\" TV show.");
        } else {
            $this->showError("Error: unknown media type: " . $_POST['media_type']);
        }
        return $this->redirectResponse(Router::getURL(Router::ACTION_VIEW, Router::VIEW_MEDIA, [$_POST['media_type'] => $_POST['tmdb_id']]));
    }

    public function viewRequests() : Response {
        $requests = [];
        $requests = array_merge($requests, Request::getAllMovieRequests(TRUE));
        $requests = array_merge($requests, Request::getAllShowRequests(TRUE));

        $requests_mine   = [];
        $requests_others = [];
        $requests_filled = [];
        $me = Plex::getUserInfos();
        foreach ($requests as $request) {
            $is_mine = $request->requested_by->username == $me->username;

            if (!Plex::isServerAdmin() && !$is_mine) {
                // Hide the 'requested by' info from non-admin users
                $request->requested_by = (object) ['username' => '', 'email' => ''];
            }

            if (!empty($request->filled_when)) {
                $requests_filled[] = $request;
            } elseif ($is_mine) {
                $requests_mine[] = $request;
            } else {
                $requests_others[] = $request;
            }
        }
        if (empty($_REQUEST['sort_by'])) {
            $_REQUEST['sort_by'] = $_SESSION['sort_by'] ?? 'title';
        }
        $_SESSION['sort_by'] = $_REQUEST['sort_by'];
        $fct_sort = function ($r1, $r2, $order = 'asc') {
            $val1 = $val2 = NULL;
            if ($_REQUEST['sort_by'] == 'type') {
                $val1 = $r1->type;
                $val2 = $r2->type;
            } elseif ($_REQUEST['sort_by'] == 'requested_by') {
                $val1 = $r1->requested_by->username;
                $val2 = $r2->requested_by->username;
            } elseif ($_REQUEST['sort_by'] == 'release_date') {
                $val1 = ($r1->filled_when ?? $r1->details->release_date ?? $r1->details->first_air_date ?? $r1->details->next_episode_to_air->air_date ?? '');
                if (empty($val1)) {
                    $val1 = 9999;
                }
                $val2 = ($r2->filled_when ?? $r2->details->release_date ?? $r2->details->first_air_date ?? $r2->details->next_episode_to_air->air_date ?? '');
                if (empty($val2)) {
                    $val2 = 9999;
                }
            }
            if ($val1 == $val2) {
                $val1 = $r1->title;
                $val2 = $r2->title;
            }
            if ($order == 'asc') {
                return $val1 <=> $val2;
            }
            return $val2 <=> $val1;
        };
        $fct_sort_desc = function ($r1, $r2) use ($fct_sort) {
            return $fct_sort($r1, $r2, 'desc');
        };
        usort($requests_mine, $fct_sort);
        usort($requests_others, $fct_sort);
        usort($requests_filled, $fct_sort_desc);

        return $this->response($this->render('/requests', ['requests_mine' => $requests_mine, 'requests_others' => $requests_others, 'requests_filled' => $requests_filled]));
    }

    public function removeRequest() : Response {
        $me = Plex::getUserInfos();

        $request = Request::getOne($this->getQueryParam('id'));
        if (empty($request)) {
            Logger::error("User $me->username tried to deleted an unknown request (ID " . $this->getQueryParam('id') . ").");
            die("Error: This request does not exist!");
        }

        if (!Plex::isServerAdmin() && $request->requested_by->username != $me->username) {
            Logger::error("User $me->username tried to deleted a request that isn't hers/his (ID $request->id).");
            die("Error: This request is not yours!");
        }

        $q = "UPDATE requests SET hidden = 1 WHERE id = :req_id";
        DB::execute($q, $request->id);

        $request->notifyAdminRequestRemoved();

        $this->showAlert("Removed request for \"$request->title\"");

        return $this->redirectResponse(Router::getURL(Router::ACTION_VIEW, Router::VIEW_REQUESTS));
    }

    public function viewNewsletter() : Response {
        $when = $_REQUEST['when'] ?? date('Y-m-d');

        $newsletter_html = $this->getNewsletterContent($when);

        if (@$_REQUEST['send'] === 'y') {
            Mailer::send('guillaume@danslereseau.com', "What's new on " . Config::get('PLEX_NAME') . " this week", NULL, $newsletter_html);
            //Mailer::send('lpauger@netlift.me', "What's new on " . Config::get('PLEX_NAME') . " this week", NULL, $newsletter_html);
            //Mailer::send('vickie@netlift.me', "What's new on " . Config::get('PLEX_NAME') . " this week", NULL, $newsletter_html);
            //Mailer::send('christine@netlift.me', "What's new on " . Config::get('PLEX_NAME') . " this week", NULL, $newsletter_html);
            //Mailer::send('solime@hors-piste.ca', "What's new on " . Config::get('PLEX_NAME') . " this week", NULL, $newsletter_html);
        }

        return $this->response($newsletter_html);
    }

    private function getNewsletterContent($when) : string {
        $sections = Plex::getSections();
        $since_when = "DATE_SUB(:when, INTERVAL 1 MONTH)";
        $until_when = "DATE_ADD(:when, INTERVAL 1 DAY)";
        foreach ($sections as $k => $section) {
            $q = "SELECT m.title, m.year, m.key, m.guid, IFNULL(g.source_id, g2.source_id) AS tmdb_id, IFNULL(tc.details, tc2.details) AS details
                    FROM available_medias m
                    LEFT JOIN available_medias_guids g ON (g.media_id = m.id AND g.source = 'tmdb')
                    LEFT JOIN tmdb_cache tc ON (tc.tmdb_id = g.source_id)
                    LEFT JOIN available_medias_guids g2 ON (g2.media_id = m.id AND g2.source = 'tmdbtv')
                    LEFT JOIN tmdb_cache tc2 ON (tc2.tmdbtv_id = g2.source_id)
                   WHERE m.added_when BETWEEN $since_when AND $until_when
                     AND m.section_id = :section
                   GROUP BY m.key
                   ORDER BY m.added_when DESC";
            $medias = DB::getAll($q, ['when' => $when, 'section' => $section->id]);

            if ($section->type == 'show') {
                $q = "SELECT m.title, m.year, m.key, m.guid, g.source_id AS tmdb_id, tc.details, eps.recent_episodes, eps.season
                        FROM available_medias m
                        JOIN available_episodes eps ON (eps.media_id = m.id)
                        LEFT JOIN available_medias_guids g ON (g.media_id = m.id AND g.source = 'tmdbtv')
                        LEFT JOIN tmdb_cache tc ON (tc.tmdbtv_id = g.source_id)                
                       WHERE FROM_UNIXTIME(eps.most_recent_episode_at) BETWEEN $since_when AND $until_when
                         AND m.section_id = :section
                         AND m.type = 'show'
                       GROUP BY m.key
                       ORDER BY m.added_when DESC, eps.season DESC";
                $medias_eps = DB::getAll($q, ['when' => $when, 'section' => $section->id]);

                $already_found_tmdb_id = getPropValuesFromArray($medias, 'tmdb_id');
                foreach ($medias_eps as $media_eps) {
                    if (!array_contains($already_found_tmdb_id, $media_eps->tmdb_id)) {
                        $media_eps->recent_episodes = json_decode($media_eps->recent_episodes);
                        $medias[] = $media_eps;
                    }
                }
            }

            foreach ($medias as $i => $media) {
                if (!empty($media->details)) {
                    $media->details = json_decode($media->details);
                } elseif (!empty($media->tmdb_id)) {
                    if ($section->type == 'movie') {
                        $media->details = TMDB::getDetailsMovie($media->tmdb_id, NULL, FALSE, TRUE);
                    } else {
                        $media->details = TMDB::getDetailsTV($media->tmdb_id, NULL, FALSE, TRUE);
                    }
                }

                if (empty($media->details)) {
                    unset($medias[$i]);
                }
            }
            $section->medias = array_values($medias);
            if (empty($medias)) {
                unset($sections[$k]);
            }
        }

        return preg_replace('/\s+/', ' ', $this->render('/newsletter', ['sections' => array_values($sections)]));
    }

    /* pragma mark - Admin pages: Plex, Radarr, Sonarr, cron */

    public function viewAdminPlex() : Response {
        $selected_sections = getPropValuesFromArray(Plex::getSections(FALSE), 'id');
        return $this->response($this->render('/admin::plex', ['selected_sections' => $selected_sections]));
    }

    public function savePlexSettings() : Response {
        $selected_sections = $_POST['selected_sections'];
        $selected_sections = array_map('intval', $selected_sections);
        Config::setInDB('PLEX_SECTIONS', $selected_sections);
        $this->showAlert("Saved settings for Plex.");
        return $this->redirectResponse(Router::getURL(Router::ACTION_VIEW, Router::VIEW_ADMIN_PLEX));
    }

    public function importPlexMedias() : Response {
        if (empty($_POST['section'])) {
            $_POST['section'] = NULL;
        }
        $num = AvailableMedia::importAvailableMediasFromPlex($_POST['section']);
        $this->showAlert("Imported $num movies or shows from Plex.");
        return $this->redirectResponse(Router::getURL(Router::ACTION_VIEW, Router::VIEW_ADMIN_PLEX));
    }

    public function viewAdminRadarr() : Response {
        return $this->response($this->render('/admin::radarr'));
    }

    public function saveRadarrSettings() : Response {
        $defaults = Config::getFromDB('RADARR_DEFAULTS', (object) [], Config::GET_OPT_PARSE_AS_JSON);
        $when = $_POST['when'];
        $defaults->{$when} = (object) [
            'path' => $_POST['path'],
            'quality' => $_POST['quality'],
            'tags' => $_POST['tags'],
        ];
        Config::setInDB('RADARR_DEFAULTS', $defaults);
        $this->showAlert("Saved settings for Radarr.");
        return $this->redirectResponse(Router::getURL(Router::ACTION_VIEW, Router::VIEW_ADMIN_RADARR));
    }

    public function importRadarrRequests() : Response {
        $num = Radarr::importAllRequests();
        $this->showAlert("Imported $num movies requests from Radarr.");
        return $this->redirectResponse(Router::getURL(Router::ACTION_VIEW, Router::VIEW_ADMIN_RADARR));
    }

    public function viewAdminSonarr() : Response {
        return $this->response($this->render('/admin::sonarr'));
    }

    public function saveSonarrSettings() : Response {
        Config::setInDB('SONARR_DEFAULT_PATH', $_POST['path']);
        Config::setInDB('SONARR_DEFAULT_QUALITY', $_POST['quality']);
        Config::setInDB('SONARR_DEFAULT_LANGUAGE', $_POST['language']);
        $this->showAlert("Saved settings for Sonarr.");
        return $this->redirectResponse(Router::getURL(Router::ACTION_VIEW, Router::VIEW_ADMIN_SONARR));
    }

    public function importSonarrRequests() : Response {
        $num = Sonarr::importAllRequests();
        $this->showAlert("Imported $num movies requests from Sonarr.");
        return $this->redirectResponse(Router::getURL(Router::ACTION_VIEW, Router::VIEW_ADMIN_SONARR));
    }

    public function cron() : Response {
        Logger::info("Executing cron...");

        // Use Plex admin' access token
        $_SESSION['PLEX_ACCESS_TOKEN'] = Config::getFromDB('PLEX_ACCESS_TOKEN');

        try {
            if ((date('Hi') >= 300 && date('Hi') < 305) || @$_REQUEST['daily'] == 'y') {
                AvailableMedia::importAvailableMediasFromPlex();
            } else {
                AvailableMedia::importRecentMediasFromPlex();
            }
            Radarr::importAllRequests();
            Sonarr::importAllRequests();
        } catch (PlexException $ex) {
            Logger::error("Caught exception while trying to import available medias From Plex: " . $ex->getMessage());
        } catch (Exception $ex) {
            Logger::error("Caught exception while trying to import available medias and requests: " . $ex->getMessage());
        }

        if (date('Hi') < 5) {
            // Daily update of discover page
            Logger::info("Updating discover (home) page content...");
            $this->render('/discover');
        }

        Logger::info("Done executing cron.");

        // Return empty response when OK
        return $this->response('');
    }
}
