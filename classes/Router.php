<?php

namespace PommePause\Dropinambour;

use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;
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

    public const RETURN_VALUE_ASSET = 'viewAsset';

    /**
     * Create a URL that will either show or do something.
     *
     * @param string $action One of Router::ACTION_* constants
     * @param string $what   (Optional) One of Router::VIEW_*, Router::SAVE_*, Router::IMPORT_* or Router::AJAX_* constants
     * @param array  $params (Optional) Key-value parameters used to qualify the requested view or action.
     *
     * @return string URL
     */
    #[Pure]
    public static function getURL(
        #[ExpectedValues(values: [self::ACTION_VIEW, self::ACTION_SAVE, self::ACTION_IMPORT, self::ACTION_AJAX, self::ACTION_SEARCH, self::ACTION_CRON, self::ACTION_REMOVE])] string $action,
        #[ExpectedValues(values: ['', self::VIEW_MEDIA, self::VIEW_COLLECTION, self::VIEW_REQUESTS, self::VIEW_ADMIN_PLEX, self::VIEW_ADMIN_RADARR, self::VIEW_ADMIN_SONARR, self::SAVE_REQUEST, self::SAVE_PLEX_SETTINGS, self::SAVE_RADARR_SETTINGS, self::SAVE_SONARR_SETTINGS, self::IMPORT_PLEX_MEDIAS, self::IMPORT_RADARR_REQUESTS, self::IMPORT_SONARR_REQUESTS, self::REMOVE_REQUEST, self::AJAX_MORE_MOVIES, self::AJAX_MORE_SHOWS, self::AJAX_CHECK_LOGIN])] string $what = '',
        array $params = []
    ) : string {
        return "./?action=$action" . (!empty($what) ? "&what=$what" : "") . (!empty($params) ? "&" . http_build_query($params) : "");
    }

    /**
     * Find the correct method of AppController to call, based on the query parameters of the Request.
     *
     * @param Request $request Request received from client (browser).
     *
     * @return string Method name to call on an AppController instance.
     */
    #[Pure]
    public static function getRouteForRequest(Request $request) : string {
        if (!empty($request->getBaseUrl()) || $request->getPathInfo() != "/") {
            // Static assets (CSS, images, etc.)
            return static::RETURN_VALUE_ASSET;
        }

        // Depending on the value of the 'action' query parameter, we'll either return 'actionWhat' (eg. 'viewMedia', 'saveRequest') or just action (eg. 'search', 'cron')
        // Fallback to view the home page
        $action = $request->query->get('action');

        // Unless this is a cron request (which use the token saved in the DB), verify that the user is logged in.
        // Otherwise, re-direct to the login page.
        if ($action != static::ACTION_CRON && $request->query->get('what') != static::AJAX_CHECK_LOGIN) {
            if (Plex::needsAuth()) {
                return 'viewRoot';
            }
        }

        return match ($action) {
            static::ACTION_VIEW,
            static::ACTION_SAVE,
            static::ACTION_IMPORT,
            static::ACTION_AJAX,
            static::ACTION_REMOVE,
                => $action . ucfirst($request->query->get('what')),
            static::ACTION_SEARCH,
            static::ACTION_CRON,
                => $action,
            default => 'viewRoot',
        };
    }

    #[Pure]
    public static function getAssetUrl(string $file, bool $add_content_hash = TRUE) : string {
        return $file . ($add_content_hash ? "?h=" . md5_file($file) : '');
    }
}
