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
        $user_infos = Plex::getUserInfos();
        if (@$user_infos->homeAdmin) {
            Config::setInDB('PLEX_ACCESS_TOKEN', $_SESSION['PLEX_ACCESS_TOKEN']);
        }

        return $this->response($this->render('/discover'));
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
        return $this->response($this->render('/tmdb_media', ['media' => $media]));
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
        if ($_POST['media_type'] == 'movie') {
            if (empty($_POST['path'])) {
                $_POST['path'] = Config::getFromDB('RADARR_DEFAULT_PATH');
            }
            if (empty($_POST['quality'])) {
                $_POST['quality'] = (int) Config::getFromDB('RADARR_DEFAULT_QUALITY');
            }
            Radarr::addMovie($_POST['tmdb_id'], $_POST['title'], $_POST['quality'], $_POST['path']);
        } elseif ($_POST['media_type'] == 'tv') {
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
        } else {
            Logger::error("Error: unknown media type: " . $_POST['media_type']);
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

            if ($me->homeAdmin && !$is_mine) {
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
        return $this->redirectResponse(Router::getURL(Router::ACTION_VIEW, Router::VIEW_ADMIN_PLEX));
    }

    public function importPlexMedias() : Response {
        AvailableMedia::importAvailableMediasFromPlex(@$_POST['section']);
        return $this->redirectResponse(Router::getURL(Router::ACTION_VIEW, Router::VIEW_ADMIN_PLEX));
    }

    public function viewAdminRadarr() : Response {
        return $this->response($this->render('/admin::radarr'));
    }

    public function saveRadarrSettings() : Response {
        Config::setInDB('RADARR_DEFAULT_PATH', $_POST['path']);
        Config::setInDB('RADARR_DEFAULT_QUALITY', $_POST['quality']);
        return $this->redirectResponse(Router::getURL(Router::ACTION_VIEW, Router::VIEW_ADMIN_RADARR));
    }

    public function importRadarrRequests() : Response {
        Radarr::importAllRequests();
        return $this->redirectResponse(Router::getURL(Router::ACTION_VIEW, Router::VIEW_ADMIN_RADARR));
    }

    public function viewAdminSonarr() : Response {
        return $this->response($this->render('/admin::sonarr'));
    }

    public function saveSonarrSettings() : Response {
        Config::setInDB('SONARR_DEFAULT_PATH', $_POST['path']);
        Config::setInDB('SONARR_DEFAULT_QUALITY', $_POST['quality']);
        Config::setInDB('SONARR_DEFAULT_LANGUAGE', $_POST['language']);
        return $this->redirectResponse(Router::getURL(Router::ACTION_VIEW, Router::VIEW_ADMIN_SONARR));
    }

    public function importSonarrRequests() : Response {
        Sonarr::importAllRequests();
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
