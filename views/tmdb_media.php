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
<link href="./css/tmdb_media.css" rel="stylesheet">
<link href="./css/tmdb_collection.css" rel="stylesheet">
<?php $this->end() ?>

<div class="<?php phe($media->container_class) ?> mb-4">

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
