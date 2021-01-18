<?php

namespace PommePause\Dropinambour;

use Exception;
use PommePause\Dropinambour\ActiveRecord\AvailableMedia;
use PommePause\Dropinambour\ActiveRecord\Request;
use PommePause\Dropinambour\Exceptions\PlexException;
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
        if ($this->getQueryParam('tv')) {
            $media = TMDB::getDetailsTV($this->getQueryParam('tv'));
        } elseif ($this->getQueryParam('movie')) {
            $media = TMDB::getDetailsMovie($this->getQueryParam('movie'));
        } else {
            Logger::error("Missing ID in viewMedia()");
            $this->error("400 Bad Request", "Missing ID in viewMedia()");
        }

        $recommended_medias = TMDB::getRecommendations($media);

        $media->container_class = AvailableMedia::getClassForMedia($media);

        if ($media->media_type == 'tv' && !empty($media->tvdb_id)) {
            $show = Sonarr::lookupShow($media->tvdb_id);
            if ($show) {
                $media->titleSlug = $show->titleSlug;
                $media->images = $show->images;
            }
        }

        $avail_suffix = '';
        if ($media->media_type == 'tv' && ($media->is_available === 'partially' || string_contains(implode(', ', $media->available_seasons ?? []), 'partial'))) {
            // List which seasons are available, and which aren't
            $avail_suffix = ": " . implode(', ', $media->available_seasons);
            if (!empty($media->not_available_seasons)) {
                if (!empty($media->available_seasons)) {
                    $avail_suffix .= ";";
                }
                $avail_suffix .= ' No: ' . implode(', ', $media->not_available_seasons);
            }
        }

        $stats = [];
        $stats[] = [
            'name'  => "Available on Plex",
            'class' => 'availability',
            'value' => $media->is_available ? 'Yes' . $avail_suffix : 'No',
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

        $paths = $media->media_type == 'movie' ? Radarr::getConfigPaths() : Sonarr::getConfigPaths();
        $profiles = $media->media_type == 'movie' ? Radarr::getQualityProfiles() : Sonarr::getQualityProfiles();
        $default_path = $media->media_type == 'movie' ? Config::getFromDB('RADARR_DEFAULT_PATH') : Config::getFromDB('SONARR_DEFAULT_PATH');
        $default_quality = $media->media_type == 'movie' ? (int) Config::getFromDB('RADARR_DEFAULT_QUALITY') : (int) Config::getFromDB('SONARR_DEFAULT_QUALITY');
        $default_language = $media->media_type == 'movie' ? '' : (int) Config::getFromDB('SONARR_DEFAULT_LANGUAGE');

        if ($media->media_type == 'tv') {
            $episode_counts = getPropValuesFromArray($media->seasons, 'episode_count');
            $total_episodes = array_sum($episode_counts);
        }

        if (!$media->is_available && empty($media->requested)) {
            ob_start();
            ?>
            <form class="row gy-2 gx-3 align-items-center" method="post" action="<?php phe(Router::getURL(Router::ACTION_SAVE, Router::SAVE_REQUEST)) ?>">
                <input name="media_type" type="hidden" value="<?php phe($media->media_type) ?>">
                <input name="tmdb_id" type="hidden" value="<?php phe($media->id) ?>">
                <input name="tvdb_id" type="hidden" value="<?php phe($media->tvdb_id) ?>">
                <input name="title" type="hidden" value="<?php phe($media->title) ?>">
                <?php if ($media->media_type == 'tv') : ?>
                    <input name="title_slug" type="hidden" value="<?php phe($media->titleSlug) ?>">
                    <input name="images_json" type="hidden" value="<?php phe(json_encode($media->images)) ?>">
                <?php endif; ?>
                <?php if (Plex::isServerAdmin()) : ?>
                    <div class="col-auto">
                        <label class="visually-hidden" for="path-input">Path</label>
                        <select name="path" class="form-control" id="path-input">
                            <option value="">Path</option>
                            <?php foreach ($paths as $path) : ?>
                                <option value="<?php phe($path) ?>" <?php echo_if($path == $default_path, 'selected') ?>><?php phe($path) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="visually-hidden" for="quality-input">Quality</label>
                        <select name="quality" class="form-control" id="quality-input">
                            <option value="">Quality</option>
                            <?php foreach ($profiles as $qp) : ?>
                                <option value="<?php phe($qp->id) ?>" <?php echo_if($qp->id == $default_quality, 'selected') ?>><?php phe($qp->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($media->media_type == 'tv') : ?>
                        <div class="col-auto">
                            <label class="visually-hidden" for="language-input">Language</label>
                            <select name="language" class="form-control" id="language-input">
                                <option value="">Language</option>
                                <?php foreach (Sonarr::getLanguageProfiles() as $lp) : ?>
                                    <option value="<?php phe($lp->id) ?>" <?php echo_if($lp->id == $default_language, 'selected') ?>><?php phe($lp->name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="col-auto">
                    <button class="btn btn-primary" type="submit">Request</button>
                </div>
            </form>
            <?php
            $request_form_html = ob_get_clean();

            $stats[] = [
                'name'  => "Request",
                'class' => 'request',
                'value_html' => $request_form_html,
            ];
        } elseif ($media->media_type == 'tv' && ($media->is_available !== TRUE || string_contains(@$media->status, 'Returning')) && $media->status != 'In Production' && $total_episodes > 0 && @$media->requested->monitored_by != 'none') {
            ob_start();
            ?>
            <form class="row gy-2 gx-3 align-items-center" method="post" action="<?php phe(Router::getURL(Router::ACTION_SAVE, Router::SAVE_REQUEST)) ?>">
                <input name="req_id" type="hidden" value="<?php phe(@$media->requested->id) ?>">
                <input name="media_type" type="hidden" value="<?php phe($media->media_type) ?>">
                <input name="tmdb_id" type="hidden" value="<?php phe($media->id) ?>">
                <input name="tvdb_id" type="hidden" value="<?php phe($media->tvdb_id) ?>">
                <input name="title" type="hidden" value="<?php phe($media->title) ?>">
                <input name="title_slug" type="hidden" value="<?php phe(@$media->titleSlug) ?>">
                <input name="images_json" type="hidden" value="<?php phe(json_encode($media->images ?? [])) ?>">
                <div class="col-auto">
                    <label class="visually-hidden" for="season-input">Season</label>
                    <select name="season" class="form-control" id="season-input" required onchange="$(this).closest('form').find('button').prop('disabled', $(this).val() === '');">
                        <option value="">Choose a season</option>
                        <?php foreach ($media->seasons as $season) : ?>
                            <option value="<?php phe(@$season->is_available === TRUE || @$season->monitored || @$season->episode_count == 0 ? '' : $season->season_number) ?>">
                                <?php phe($season->name) ?>
                                <?php if (@$season->is_available === TRUE) { phe(' (already available)'); } elseif (@$season->monitored) { phe(' (already requested)'); } elseif (@$season->episode_count == 0) { phe(' (empty)'); } elseif (@$season->is_available === 'partially') { phe(' (partial)'); } ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (Plex::isServerAdmin() && empty($media->requested)) : ?>
                    <div class="col-auto">
                        <label class="visually-hidden" for="path-input">Path</label>
                        <select name="path" class="form-control" id="path-input">
                            <option value="">Path</option>
                            <?php foreach ($paths as $path) : ?>
                                <option value="<?php phe($path) ?>" <?php echo_if($path == $default_path, 'selected') ?>><?php phe($path) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="visually-hidden" for="quality-input">Quality</label>
                        <select name="quality" class="form-control" id="quality-input">
                            <option value="">Quality</option>
                            <?php foreach ($profiles as $qp) : ?>
                                <option value="<?php phe($qp->id) ?>" <?php echo_if($qp->id == $default_quality, 'selected') ?>><?php phe($qp->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="visually-hidden" for="language-input">Language</label>
                        <select name="language" class="form-control" id="language-input">
                            <option value="">Language</option>
                            <?php foreach (Sonarr::getLanguageProfiles() as $lp) : ?>
                                <option value="<?php phe($lp->id) ?>" <?php echo_if($lp->id == $default_language, 'selected') ?>><?php phe($lp->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="col-auto">
                    <button class="btn btn-primary" type="submit" disabled>Request</button>
                </div>
            </form>
            <?php
            $request_form_html = ob_get_clean();

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
            $episodes = array_map(function ($season) { if ($season->name == 'Specials') { return NULL; } if (preg_match('/Season (\d+)/', $season->name, $re)) { $season->name = sprintf('S%02d', $re[1]); } return "$season->name ($season->episode_count ep)"; }, $media->seasons);
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

        $stats = array_map('to_object', $stats);
        $urls = array_map('to_object', $urls);

        return $this->response($this->render('/tmdb_media', ['media' => $media, 'recommended_medias' => $recommended_medias, 'stats' => $stats, 'urls' => $urls]));
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
            if (empty($_POST['path'])) {
                $_POST['path'] = Config::getFromDB('RADARR_DEFAULT_PATH');
            }
            if (empty($_POST['quality'])) {
                $_POST['quality'] = (int) Config::getFromDB('RADARR_DEFAULT_QUALITY');
            }
            Radarr::addMovie($_POST['tmdb_id'], $_POST['title'], $_POST['quality'], $_POST['path']);
            $this->showAlert("Added request for \"{$_POST['title']}\" movie.");
        } elseif ($_POST['media_type'] == 'tv') {
            if (empty($_POST['tvdb_id'])) {
                $show = TMDB::getDetailsTV($_POST['tmdb_id']);
                $request = Request::fromTMDBShow($show);
                $request->save();
                $request->notifyAdminRequestAdded(1);
            } else {
                if (empty($_POST['path'])) {
                    $_POST['path'] = Config::getFromDB('SONARR_DEFAULT_PATH');
                }
                if (empty($_POST['quality'])) {
                    $_POST['quality'] = (int) Config::getFromDB('SONARR_DEFAULT_QUALITY');
                }
                if (empty($_POST['language'])) {
                    $_POST['language'] = (int) Config::getFromDB('SONARR_DEFAULT_LANGUAGE');
                }
                Sonarr::addShow($_POST['tvdb_id'], $_POST['title'], $_POST['title_slug'], $_POST['quality'], $_POST['language'], $_POST['path'], json_decode($_POST['images_json']));
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

        $requests_mine = [];
        $requests_others = [];
        $me = Plex::getUserInfos();
        foreach ($requests as $request) {
            $is_mine = $request->requested_by->username == $me->username;

            if (Plex::isServerAdmin() && !$is_mine) {
                // Keep the requested by info
            } else {
                $request->requested_by = (object) ['username' => '', 'email' => ''];
            }

            if ($is_mine) {
                $requests_mine[] = $request;
            } else {
                $requests_others[] = $request;
            }
        }

        return $this->response($this->render('/requests', ['requests_mine' => $requests_mine, 'requests_others' => $requests_others]));
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

    public function viewAsset() : Response {
        $request = $this->request();
        if (empty($request->getBaseUrl()) || !file_exists('.' . $request->getBaseUrl())) {
            return $this->response("404 Not Found", 404);
        }
        if (string_begins_with($request->getBaseUrl(), '/css/') || string_begins_with($request->getBaseUrl(), '/img/')) {
            if (string_begins_with($request->getBaseUrl(), '/css/')) {
                header('Content-type: text/css');
            } else {
                $ext = last(explode('.', $request->getBaseUrl()));
                $ext = str_replace(['jpg'], ['jpeg'], $ext);
                header('Content-type: image/' . $ext);
            }
            readfile('.' . $request->getBaseUrl());
            exit();
        }
        return $this->response("404 Not Found", 404);
    }

    /* pragma mark - Admin pages: Plex, Radarr, Sonarr, cron */

    public function viewAdminPlex() : Response {
        //var_dump(Plex::getSharedUsers());
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
        Config::setInDB('RADARR_DEFAULT_PATH', $_POST['path']);
        Config::setInDB('RADARR_DEFAULT_QUALITY', $_POST['quality']);
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
            if (date('Hi') >= 300 && date('Hi') < 305) {
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
        Logger::info("Done executing cron.");

        // Return empty response when OK
        return $this->response('');
    }
}
