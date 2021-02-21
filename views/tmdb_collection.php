<?php
namespace PommePause\Dropinambour;

// Views variables
/** @var $collection object */
// End of Views variables

$this->layout('/page', ['title' => $collection->name . " | dropinambour - Requests for Plex"]);
?>

<?php $this->push('head') ?>
<link href="<?php phe(Router::getAssetUrl('./css/tmdb_collection.css')) ?>" rel="stylesheet">
<?php $this->end() ?>

<h1><?php phe($collection->name) ?></h1>

<div id="collection_movies">
    <?php $this->insert('media_items', ['medias' => $collection->parts]) ?>
</div>
