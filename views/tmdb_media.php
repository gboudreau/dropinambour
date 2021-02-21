<?php
namespace PommePause\Dropinambour;

use PommePause\Dropinambour\ActiveRecord\AvailableMedia;
use \stdClass;

/** @var $media stdClass  */
/** @var $recommended_medias stdClass[] */
/** @var $stats stdClass[] */
/** @var $urls stdClass[] */

$this->layout('/page', ['title' => $media->title . " | dropinambour - Requests for Plex"]);
?>

<?php $this->push('head') ?>
<link href="<?php phe(Router::getAssetUrl('./css/tmdb_media.css')) ?>" rel="stylesheet">
<link href="<?php phe(Router::getAssetUrl('./css/tmdb_collection.css')) ?>" rel="stylesheet">
<?php $this->end() ?>

<div class="<?php phe($media->container_class) ?> mb-4">

    <?php if (!empty($media->backdrop_path)) : ?>
        <div class="backdrop" style="background-image: url(<?php phe(TMDB::getBackdropImageUrl($media->backdrop_path, TMDB::IMAGE_SIZE_BACKDROP_ORIGINAL)) ?>)"></div>
    <?php endif; ?>

    <div class="row justify-content-center top-section">
        <?php if (!empty($media->poster_path)) : ?>
            <div class="col col-auto pe-2 pe-md-3">
                <img class="this poster" src="<?php phe(TMDB::getPosterImageUrl($media->poster_path, TMDB::IMAGE_SIZE_POSTER_W342)) ?>" alt="Poster">
            </div>
        <?php endif; ?>
        <div class="col col-12 col-md-5 col-lg-7 col-xl-8 mt-4 mt-md-0 ps-3 ps-md-2 pe-3 pe-md-0">
            <div class="sidepanel">
                <h1><?php phe($media->title) ?></h1>

                <h4>Overview</h4>
                <div class="overview"><?php echo $media->overview ?></div>

                <div class="urls_container">
                    <!-- Source: https://icons.getbootstrap.com/icons/link-45deg/ -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-link-45deg" viewBox="0 0 16 16">
                        <path d="M4.715 6.542L3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1.001 1.001 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.88 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4.018 4.018 0 0 1-.128-1.287z"/>
                        <path d="M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.55a2 2 0 0 1 2.83 2.83l-.793.792c.112.42.155.855.128 1.287l1.372-1.372a3 3 0 0 0-4.243-4.243L6.586 4.672z"/>
                    </svg>
                    <?php foreach ($urls as $s) : ?>
                        <span class="url">
                        <a href="<?php phe($s->value) ?>" target="_blank" rel="noreferrer"><?php phe($s->link_text ?? $s->name) ?></a>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 stats-table">
        <?php foreach ($stats as $s) : ?>
            <div class="row <?php phe($s->class) ?> <?php echo oddOrEven() ?>">
                <div class="name col col-12 col-md-3 col-lg-2 p-2">
                    <?php phe($s->name) ?>
                </div>
                <div class="col col-12 col-md-auto text-end text-md-start p-2">
                    <?php if (!empty($s->value_html)) : ?>
                        <?php echo $s->value_html ?>
                    <?php else : ?>
                        <span class="value">
                            <?php
                            if ($s->class == 'url') {
                                ?><a href="<?php phe($s->value) ?>" rel="noreferrer" target="_blank"><?php phe($s->link_text ?? $s->name) ?></a><?php
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

</div>
