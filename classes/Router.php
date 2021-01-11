<?php

namespace PommePause\Dropinambour;

use Symfony\Component\HttpFoundation\Request;

class Router
{
    public const ACTION_VIEW     = 'view';
    public const ACTION_SAVE     = 'save';
    public const ACTION_IMPORT   = 'import';
    public const ACTION_AJAX     = 'ajax';
    public const ACTION_SEARCH   = 'search';
    public const ACTION_CRON     = 'cron';
    public const ACTION_REMOVE   = 'remove';

    public const VIEW_MEDIA        = 'media';
    public const VIEW_COLLECTION   = 'collection';
    public const VIEW_REQUESTS     = 'requests';
    public const VIEW_ADMIN_PLEX   = 'adminPlex';
    public const VIEW_ADMIN_RADARR = 'adminRadarr';
    public const VIEW_ADMIN_SONARR = 'adminSonarr';

    public const SAVE_REQUEST         = 'request';
    public const SAVE_PLEX_SETTINGS   = 'plexSettings';
    public const SAVE_RADARR_SETTINGS = 'radarrSettings';
    public const SAVE_SONARR_SETTINGS = 'sonarrSettings';

    public const IMPORT_PLEX_MEDIAS     = 'plexMedias';
    public const IMPORT_RADARR_REQUESTS = 'radarrRequests';
    public const IMPORT_SONARR_REQUESTS = 'sonarrRequests';

    public const REMOVE_REQUEST = 'request';

    public const AJAX_MORE_MOVIES = 'moreMovies';
    public const AJAX_MORE_SHOWS  = 'moreShows';
    public const AJAX_CHECK_LOGIN = 'checkLogin';

    /**
     * Create a URL that will either show or do something.
     *
     * @param string $action One of Router::ACTION_* constants
     * @param string $what   (Optional) One of Router::VIEW_*, Router::SAVE_*, Router::IMPORT_* or Router::AJAX_* constants
     * @param array  $params (Optional) Key-value parameters used to qualify the requested view or action.
     *
     * @return string URL
     */
    public static function getURL(string $action, string $what = '', $params = []) : string {
        return "./?action=$action" . (!empty($what) ? "&what=$what" : "") . (!empty($params) ? "&" . http_build_query($params) : "");
    }

    /**
     * Find the correct method of AppController to call, based on the query parameters of the Request.
     *
     * @param Request $request Request received from client (browser).
     *
     * @return string Method name to call on an AppController instance.
     */
    public static function getRouteForRequest(Request $request) : string {
        // Depending on the value of the 'action' query parameter, we'll either return 'actionWhat' (eg. 'viewMedia', 'saveRequest') or just action (eg. 'search', 'cron')
        // Fallback to view the home page
        $action = $request->query->get('action');
        switch ($action) {
        case static::ACTION_VIEW:
        case static::ACTION_SAVE:
        case static::ACTION_IMPORT:
        case static::ACTION_AJAX:
        case static::ACTION_REMOVE:
            return $action . ucfirst($request->query->get('what'));
        case static::ACTION_SEARCH:
        case static::ACTION_CRON:
            return $action;
        default:
            return 'viewRoot';
        }
    }
}
