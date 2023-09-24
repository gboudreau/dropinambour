<?php
namespace PommePause\Dropinambour;

use stdClass;

// Views variables
/** @var $params stdClass */
// End of Views variables

?>
<!DOCTYPE html>
<html lang="en">
<body>

<div style="margin-top: 12px">Hi Admin,</div>

<div style="margin-top: 12px">
    <?php if (!empty($params->season_number)) : ?>
        <?php phe(sprintf("%s added a request for the season %d of \"%s\" (%s).", $params->request->requested_by, $params->season_number, $params->request->title, $params->lang)) ?>
    <?php else : ?>
        <?php phe(sprintf("%s added a request for the %s \"%s\" (%s).", $params->request->requested_by, $params->request->media_type, $params->request->title, $params->lang)) ?>
    <?php endif; ?>
</div>

<div style="margin-top: 12px">
    <?php
    if (!empty($params->request->tmdb_id)) {
        $url = Router::getURL(Router::ACTION_VIEW, Router::VIEW_MEDIA, ['movie' => $params->request->tmdb_id]);
        $monitor_url = trim(Config::get('RADARR_BASE_URL'), '/') . "/movie/" . urlencode($params->request->tmdb_id);
    } else {
        $url = Router::getURL(Router::ACTION_VIEW, Router::VIEW_MEDIA, ['tv' => $params->request->tmdbtv_id]);
        $monitor_url = trim(Config::get('SONARR_BASE_URL'), '/') . "/add/new?term=" . urlencode($params->request->title);
    }
    $url = trim(Config::get('BASE_URL'), '/') . '/' . $url;
    ?>
    Links: <a href="<?php phe($url) ?>" target="_blank">Dropinambour</a>
    <?php if ($params->request->monitored_by != 'none') : ?>
        | <a href="<?php phe($monitor_url) ?>" target="_blank"><?php phe(ucfirst($params->request->monitored_by)) ?></a>
    <?php endif; ?>
    | <a href="https://app.plex.tv/desktop#!/search?query=<?php phe(urlencode($params->request->title)) ?>" target="_blank">Plex</a>
</div>

<?php if ($params->request->monitored_by == 'none') : ?>
    <div style="margin-top: 12px">
        <strong>Of note: This TV show is unmonitored, since it couldn't be added to Sonarr. You'll need to manually add it, or find it somewhere else.</strong>
    </div>
<?php endif; ?>

<div style="margin-top: 12px">Good day to you.</div>

<div style="margin-top: 12px">- The dropinambour Bot</div>

</body>
</html>
