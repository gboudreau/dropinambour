<?php
namespace PommePause\Dropinambour;

use PommePause\Dropinambour\ActiveRecord\AvailableMedia;
use stdClass;

// Views variables
/** @var $medias stdClass[] */
/** @var $suffix string|null */
// End of Views variables

?>

<div class="row row-cols-auto g-4 justify-content-center movie_box_container">
    <?php foreach ($medias ?? [] as $media) : ?>
        <div class="col">
            <a class="card movie_box <?php phe(AvailableMedia::getClassForMedia($media)) ?>" href="<?php phe(Router::getURL(Router::ACTION_VIEW, Router::VIEW_MEDIA, [$media->media_type => $media->id, 'language' => (array_contains(Config::get('LANGUAGES', ['en']), @$media->original_language) ? $media->original_language : NULL)])) ?>">
                <img class="card-img-top poster" src="<?php phe(empty($media->poster_path) ? './img/no_poster.png' : TMDB::getPosterImageUrl($media->poster_path, TMDB::IMAGE_SIZE_POSTER_W185)) ?>" alt="Poster">
                <div class="card-body">
                    <h5 class="card-title"><?php phe($media->title) ?></h5>
                    <?php if (!empty($media->vote_average)) : ?>
                        <p class="card-text vote"><?php echo round($media->vote_average*10) ?>%</p>
                    <?php endif; ?>
                    <?php if (!empty($media->release_date)) : ?>
                        <p class="card-text date"><?php echo date(date('Y', strtotime($media->release_date)) >= date('Y')-1 ? 'Y-m-d' : 'Y', strtotime($media->release_date)) ?></p>
                    <?php endif; ?>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
    <?php echo @$suffix ?>
</div>
