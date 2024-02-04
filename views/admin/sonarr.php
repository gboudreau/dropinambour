<?php
namespace PommePause\Dropinambour;

/**
 * @var $selected_sections int[]
 */

$this->layout('/page', ['title' => "Sonarr | Admin | dropinambour - Requests for Plex", 'nav_active' => 'admin']);
?>

<h1>Sonarr</h1>

<?php
$whens = [];
foreach (Config::get('SONARR_CUSTOM_DEFAULTS', ['language=en' => ''], Config::GET_OPT_PARSE_AS_JSON) as $value => $text) {
    $whens[$value] = $text;
}

$defaults = Config::getFromDB('SONARR_DEFAULTS', [], Config::GET_OPT_PARSE_AS_JSON);
?>
<?php foreach ($whens as $when_value => $when_text) : ?>
    <form method="post" action="<?php phe(Router::getURL(Router::ACTION_SAVE, Router::SAVE_SONARR_SETTINGS)) ?>" class="mb-3">
        <div><label>When: <strong><?php phe($when_text) ?></strong></label></div>
        <input name="when" value="<?php phe($when_value) ?>" type="hidden">
        Default path: <select name="path">
            <?php foreach (Sonarr::getConfigPaths() as $path) : ?>
                <option value="<?php phe($path) ?>" <?php echo_if(@$defaults->{$when_value}->path == $path, 'selected') ?>><?php phe($path) ?></option>
            <?php endforeach; ?>
        </select><br/>

        Default quality: <select name="quality">
            <?php foreach (Sonarr::getQualityProfiles() as $qp) : ?>
                <option value="<?php phe($qp->id) ?>" <?php echo_if(@$defaults->{$when_value}->quality == $qp->id, 'selected') ?>><?php phe($qp->name) ?></option>
            <?php endforeach; ?>
        </select><br/>

        Tag(s): <input name="tags" type="text" value="<?php phe(@$defaults->{$when_value}->tags) ?>"><br/>

        <button type="submit" class="btn btn-primary">Save Defaults</button>
    </form>
<?php endforeach; ?>

<h2>Import</h2>
<form method="post" action="<?php phe(Router::getURL(Router::ACTION_IMPORT, Router::IMPORT_SONARR_REQUESTS)) ?>">
    <button type="submit" class="btn btn-primary">Import requests from Sonarr</button>
</form>
