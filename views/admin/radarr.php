<?php
namespace PommePause\Dropinambour;

// Views variables
/** @var $selected_sections int[] */
// End of Views variables

$this->layout('/page', ['title' => "Radarr | Admin | dropinambour - Requests for Plex"]);
?>

<h1>Radarr</h1>

<form method="post" action="<?php phe(Router::getURL(Router::ACTION_SAVE, Router::SAVE_RADARR_SETTINGS)) ?>">
    Default path: <select name="path">
        <?php foreach (Radarr::getConfigPaths() as $path) : ?>
            <option value="<?php phe($path) ?>" <?php echo_if(Config::getFromDB('RADARR_DEFAULT_PATH') == $path, 'selected') ?>><?php phe($path) ?></option>
        <?php endforeach; ?>
    </select><br/>

    Default quality: <select name="quality">
        <?php foreach (Radarr::getQualityProfiles() as $qp) : ?>
            <option value="<?php phe($qp->id) ?>" <?php echo_if(Config::getFromDB('RADARR_DEFAULT_QUALITY') == $qp->id, 'selected') ?>><?php phe($qp->name) ?></option>
        <?php endforeach; ?>
    </select><br/>

    <button type="submit" class="btn btn-primary">Save Defaults</button>
</form>

<h2>Import</h2>
<form method="post" action="<?php phe(Router::getURL(Router::ACTION_IMPORT, Router::IMPORT_RADARR_REQUESTS)) ?>">
    <button type="submit" class="btn btn-primary">Import requests from Radarr</button>
</form>
