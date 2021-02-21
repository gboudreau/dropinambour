<?php
namespace PommePause\Dropinambour;

// Views variables
/** @var $search_results object[] */
// End of Views variables

if (!empty($_REQUEST['query'])) {
    $title = "Search results for \"" . $_REQUEST['query'] . "\" | dropinambour - Requests for Plex";
} else {
    $title = "Search | dropinambour - Requests for Plex";
}

$this->layout('/page', ['title' => $title]);
?>

<?php $this->push('head') ?>
<link href="<?php phe(Router::getAssetUrl('./css/tmdb_collection.css')) ?>" rel="stylesheet">
<?php $this->end() ?>

<?php if (empty($search_results)) : ?>
    <div class="alert alert-primary">
        Use the search field above to search for movies or TV shows.
    </div>
<?php else : ?>
    <h1 class="mb-3">Search results for "<?php phe(@$_REQUEST['query']) ?>"</h1>

    <div id="search_results">
        <?php $this->insert('/media_items', ['medias' => $search_results]) ?>
    </div>
<?php endif; ?>
