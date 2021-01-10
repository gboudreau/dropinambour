<?php
namespace PommePause\Dropinambour;

use PommePause\Dropinambour\ActiveRecord\AvailableMedia;
use \stdClass;

/** @var stdClass $media */

$recommended_medias = TMDB::getRecommendations($media);

if ($media->media_type == 'tv') {
    $show = Sonarr::lookupShow($media->tvdb_id);
    $media->titleSlug = $show->titleSlug;
    $media->images = $show->images;
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

if (!$media->is_available && empty($media->requested)) {
    $paths = $media->media_type == 'movie' ? Radarr::getConfigPaths() : Sonarr::getConfigPaths();
    $profiles = $media->media_type == 'movie' ? Radarr::getQualityProfiles() : Sonarr::getQualityProfiles();
    $default_path = $media->media_type == 'movie' ? Config::getFromDB('RADARR_DEFAULT_PATH') : Config::getFromDB('SONARR_DEFAULT_PATH');
    $default_quality = $media->media_type == 'movie' ? (int) Config::getFromDB('RADARR_DEFAULT_QUALITY') : (int) Config::getFromDB('SONARR_DEFAULT_QUALITY');
    $default_language = $media->media_type == 'movie' ? '' : (int) Config::getFromDB('SONARR_DEFAULT_LANGUAGE');

    $this->start('request-form');
    ?>
    <form class="row gy-2 gx-3 align-items-center" method="post" action="<?php phe(Router::getURL(Router::ACTION_SAVE, Router::SAVE_REQUEST)) ?>">
        <input name="media_type" type="hidden" value="<?php phe($media->media_type) ?>">
        <input name="tmdb_id" type="hidden" value="<?php phe($media->id) ?>">
        <input name="tvdb_id" type="hidden" value="<?php phe($media->tvdb_id) ?>">
        <input name="title" type="hidden" value="<?php phe($media->title) ?>">
        <?php if (Plex::getUserInfos()->homeAdmin) : ?>
            <div class="col-auto">
                <label class="visually-hidden" for="autoSizingInput">Path</label>
                <select name="path" class="form-control">
                    <option value="">Path</option>
                    <?php foreach ($paths as $path) : ?>
                        <option value="<?php phe($path) ?>" <?php echo_if($path == $default_path, 'selected') ?>><?php phe($path) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="visually-hidden" for="autoSizingInput">Quality</label>
                <select name="quality" class="form-control">
                    <option value="">Quality</option>
                    <?php foreach ($profiles as $qp) : ?>
                        <option value="<?php phe($qp->id) ?>" <?php echo_if($qp->id == $default_quality, 'selected') ?>><?php phe($qp->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($media->media_type == 'tv') : ?>
                <input name="title_slug" type="hidden" value="<?php phe($media->titleSlug) ?>">
                <input name="images_json" type="hidden" value="<?php phe(json_encode($media->images)) ?>">
                <div class="col-auto">
                    <label class="visually-hidden" for="autoSizingInput">Language</label>
                    <select name="language" class="form-control">
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
    $this->stop();

    $stats[] = [
        'name'  => "Request",
        'class' => 'request',
        'value_html' => $this->section('request-form'),
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
if (!empty($media->requested)) {
    if (Plex::getUserInfos()->homeAdmin) {
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
        'value'  => first($media->episode_run_time),
        'suffix' => 'minutes'
    ];
}
if (!empty($media->runtime)) {
    $stats[] = [
        'name'   => "Run time",
        'class'  => 'runtime',
        'value'  => $media->runtime,
        'suffix' => 'minutes'
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
        'value' => "$media->first_air_date to $media->last_air_date",
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
    $episodes = array_map(function ($season) { if ($season->name == 'Specials') { return NULL; } return "$season->name ($season->episode_count episodes)"; }, $media->seasons);
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

$this->layout('/page', ['title' => $media->title . " | dropinambour - Requests for Plex"]);

?>

<?php $this->push('head') ?>
<link href="./css/tmdb_media.css" rel="stylesheet">
<link href="./css/tmdb_collection.css" rel="stylesheet">
<?php $this->end() ?>

<div class="<?php phe(AvailableMedia::getClassForMedia($media)) ?>">

    <?php if (!empty($media->backdrop_path)) : ?>
        <div class="backdrop" style="background-image: url(<?php phe(TMDB::getBackdropImageUrl($media->backdrop_path, TMDB::IMAGE_SIZE_BACKDROP_ORIGINAL)) ?>)"></div>
    <?php endif; ?>

    <?php if (!empty($media->poster_path)) : ?>
        <img class="poster col-12 col-md-auto" src="<?php phe(TMDB::getPosterImageUrl($media->poster_path, TMDB::IMAGE_SIZE_POSTER_W342)) ?>">
    <?php endif; ?>

    <h1><?php phe($media->title) ?></h1>

    <h4>Overview</h4>
    <div class="overview"><?php echo $media->overview ?></div>

    <div class="urls_container">
        Links
        <?php foreach ($urls as $s) : $s = (object) $s; ?>
            <span class="url">
            <a href="<?php phe($s->value) ?>" target="_blank" rel="noreferrer"><?php phe($s->link_text ?? $s->name) ?></a>
        </span>
        <?php endforeach; ?>
    </div>

    <div class="mt-4 table-striped">
        <?php foreach ($stats as $s) : $s = (object) $s; ?>
            <div class="row <?php phe($s->class) ?> <?php echo oddOrEvent() ?>">
                <div class="name col col-12 col-md-3 col-lg-2 p-2">
                    <?php phe($s->name) ?>
                </div>
                <div class="col col-12 col-md-auto text-end text-md-start p-2">
                    <?php if (!empty($s->value_html)) : ?>
                        <?php echo $s->value_html ?>
                    <?php elseif ($s->class == 'url' && empty($s->link_text)) : ?>
                        <a href="<?php phe($s->value) ?>" rel="noreferrer"><?php phe($s->link_text ?? $s->name) ?></a>
                    <?php else : ?>
                        <span class="value">
                            <?php
                            if ($s->class == 'url') {
                                ?><a href="<?php phe($s->value) ?>" rel="noreferrer"><?php phe($s->link_text ?? $s->name) ?></a><?php
                            } else {
                                phe($s->value);
                            }
                            ?>
                        </span>
                    <?php endif; ?>
                    <span class="suffix"><?php phe(@$s->suffix) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($recommended_medias)) : ?>
        <h4>Similar <?php phe($media->media_type == 'movie' ? 'movies' : 'shows') ?></h4>
        <div id="recommended_medias">
            <?php $this->insert('media_items', ['medias' => $recommended_medias]) ?>
        </div>
    <?php endif; ?>

    <div class="mb-4"></div>
</div>
