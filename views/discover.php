<?php
namespace PommePause\Dropinambour;

// Views variables
// End of Views variables

$this->layout('/page', ['title' => "Discover | dropinambour - Requests for Plex", 'nav_active' => 'discover']);

$_SESSION['tmdb_suggested_tv_page'] = 0;

if (Config::get('TL_UID')) {
    $result = TorrentLeech::getPopularMedias();
    $tv_medias = $result['shows'];
    $movie_medias = $result['movies'];
} else {
//if (empty($tv_medias) || empty($movie_medias)) {
    $tv_medias = TMDB::getSuggestedShows();
    $movie_medias = TMDB::getSuggestedMovies();
}
?>

<?php $this->push('head') ?>
<link href="<?php phe(Router::getAssetUrl('./css/tmdb_collection.css')) ?>" rel="stylesheet">
<?php $this->end() ?>

<div id="suggested_movies" class="suggested">
    <h1 class="text-center">Popular, Now Playing &amp; Upcoming Movies</h1>
    <?php $this->start('cards-suffix') ?>
    <div class="col">
        <div style="width: 185px; padding-top: 140px; margin-bottom: 140px" class="card load-more">
            <button class="btn btn-outline-success" onclick="loadMoreSuggestedMovies(this);return false">Load more...</button>
        </div>
    </div>
    <?php $this->stop() ?>
    <?php $this->insert('/media_items', ['medias' => $movie_medias, 'suffix' => $this->section('cards-suffix')]) ?>
</div>

<div id="suggested_shows" class="suggested">
    <h1 class="text-center">Popular, Trending &amp; Now Playing TV Shows</h1>
    <?php $this->start('cards-suffix') ?>
    <div class="col">
        <div style="width: 185px; padding-top: 140px; margin-bottom: 140px" class="card load-more">
            <button class="btn btn-outline-success" onclick="loadMoreSuggestedShows(this);return false">Load more...</button>
        </div>
    </div>
    <?php $this->stop() ?>
    <?php $this->insert('/media_items', ['medias' => $tv_medias, 'suffix' => $this->section('cards-suffix')]) ?>
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
