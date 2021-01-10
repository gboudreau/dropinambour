<?php
namespace PommePause\Dropinambour;

// Views variables
// End of Views variables

$this->layout('/page', ['title' => "Discover | dropinambour - Requests for Plex"]);
?>

<?php $this->push('head') ?>
<link href="./css/tmdb_collection.css" rel="stylesheet">
<?php $this->end() ?>

<h1>Popular, Now Playing &amp; Upcoming Movies</h1>
<div id="suggested_movies">
    <?php $this->start('cards-suffix') ?>
    <div class="col">
        <div style="width: 185px; padding-top: 140px; margin-bottom: 140px" class="card load-more">
            <button class="btn btn-outline-success" onclick="loadMoreSuggestedMovies(this);return false">Load more...</button>
        </div>
    </div>
    <?php $this->stop() ?>
    <?php $this->insert('/media_items', ['medias' => TMDB::getSuggestedMovies(), 'suffix' => $this->section('cards-suffix')]) ?>
</div>

<h1>Popular, Trending &amp; Now Playing TV Shows</h1>
<div id="suggested_shows">
    <?php $this->start('cards-suffix') ?>
    <div class="col">
        <div style="width: 185px; padding-top: 140px; margin-bottom: 140px" class="card load-more">
            <button class="btn btn-outline-success" onclick="loadMoreSuggestedShows(this);return false">Load more...</button>
        </div>
    </div>
    <?php $this->stop() ?>
    <?php $this->insert('/media_items', ['medias' => TMDB::getSuggestedShows(), 'suffix' => $this->section('cards-suffix')]) ?>
</div>

<script type="application/javascript">
    function loadMoreSuggestedMovies(button) {
        $(button).prop('disabled', true).data('text-before', $(button).text()).text('Loading...');
        $.ajax({
            method: 'GET',
            url: <?php echo json_encode(Router::getURL(Router::ACTION_AJAX, Router::AJAX_MORE_MOVIES)) ?>,
        }).done(function (data) {
            $('#suggested_movies .row')
                .append($(data).children())
                .append($('#suggested_movies .row .load-more').parent().detach());
            $(button).prop('disabled', false).text($(button).data('text-before'));
        });
    }
    function loadMoreSuggestedShows(button) {
        $(button).prop('disabled', true).data('text-before', $(button).text()).text('Loading...');
        $.ajax({
            method: 'GET',
            url: <?php echo json_encode(Router::getURL(Router::ACTION_AJAX, Router::AJAX_MORE_SHOWS)) ?>,
        }).done(function (data) {
            $('#suggested_shows .row')
                .append($(data).children())
                .append($('#suggested_shows .row .load-more').parent().detach());
            $(button).prop('disabled', false).text($(button).data('text-before'));
        });
    }
</script>
