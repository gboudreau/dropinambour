<?php
namespace PommePause\Dropinambour;

use stdClass;

// Views variables
/** @var $search_results stdClass[] */
// End of Views variables

if (!empty($_REQUEST['query'])) {
    $title = "Search results for \"" . $_REQUEST['query'] . "\" | dropinambour - Requests for Plex";
} else {
    $title = "Search | dropinambour - Requests for Plex";
}

$this->layout('/page', ['title' => $title]);
?>

<?php $this->push('head') ?>
<link href="./css/tmdb_collection.css" rel="stylesheet">
<?php $this->end() ?>

<h1 class="mb-3">Search results for "<?php phe(@$_REQUEST['query']) ?>"</h1>

<div id="search_results">
    <?php $this->insert('/media_items', ['medias' => $search_results]) ?>
</div>
