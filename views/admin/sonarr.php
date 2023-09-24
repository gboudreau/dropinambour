<?php
namespace PommePause\Dropinambour;

/**
 * @var $selected_sections int[]
 */

$this->layout('/page', ['title' => "Sonarr | Admin | dropinambour - Requests for Plex", 'nav_active' => 'admin']);
?>

<h1>Sonarr</h1>

<form method="post" action="<?php phe(Router::getURL(Router::ACTION_SAVE, Router::SAVE_SONARR_SETTINGS)) ?>">
    Default path: <select name="path">
        <?php foreach (Sonarr::getConfigPaths() as $path) : ?>
            <option value="<?php phe($path) ?>" <?php echo_if(Config::getFromDB('SONARR_DEFAULT_PATH') == $path, 'selected') ?>><?php phe($path) ?></option>
        <?php endforeach; ?>
    </select><br/>

    Default quality: <select name="quality">
        <?php foreach (Sonarr::getQualityProfiles() as $qp) : ?>
            <option value="<?php phe($qp->id) ?>" <?php echo_if(Config::getFromDB('SONARR_DEFAULT_QUALITY') == $qp->id, 'selected') ?>><?php phe($qp->name) ?></option>
        <?php endforeach; ?>
    </select><br/>

    Default language: <select name="language">
        <?php foreach (Sonarr::getLanguageProfiles() as $lp) : ?>
            <option value="<?php phe($lp->id) ?>" <?php echo_if(Config::getFromDB('SONARR_DEFAULT_LANGUAGE') == $lp->id, 'selected') ?>><?php phe($lp->name) ?></option>
        <?php endforeach; ?>
    </select><br/>

    <button type="submit" class="btn btn-primary">Save Defaults</button>
</form>

<h2>Import</h2>
<form method="post" action="<?php phe(Router::getURL(Router::ACTION_IMPORT, Router::IMPORT_SONARR_REQUESTS)) ?>">
    <button type="submit" class="btn btn-primary">Import requests from Sonarr</button>
</form>
