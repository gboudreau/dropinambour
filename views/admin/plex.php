<?php
namespace PommePause\Dropinambour;

/**
 * @var $selected_sections int[]
 */

$this->layout('/page', ['title' => "Plex | Admin | dropinambour - Requests for Plex", 'nav_active' => 'admin']);
?>

<h1>Plex Admin</h1>

<form method="post" action="<?php phe(Router::getURL(Router::ACTION_SAVE, Router::SAVE_PLEX_SETTINGS)) ?>">
    <h2>Selected sections</h2>
    <ul>
        <?php foreach (Plex::getSections(TRUE) as $section) : ?>
            <li>
                <input type="checkbox" id="selected_section_<?php phe($section->id) ?>" name="selected_sections[]" value="<?php phe($section->id) ?>" <?php echo_if(array_contains($selected_sections, $section->id), 'checked') ?>>
                <label for="selected_section_<?php phe($section->id) ?>"><?php phe($section->type) ?> (<?php phe($section->language) ?>): <?php phe($section->title) ?></label>
            </li>
        <?php endforeach; ?>
    </ul> <button type="submit" class="btn btn-primary">Save selected sections</button>
</form>

<h2>Import</h2>
Import Plex medias from ...
<form method="post" action="<?php phe(Router::getURL(Router::ACTION_IMPORT, Router::IMPORT_PLEX_MEDIAS)) ?>">
    <select name="section">
        <option value="">All sections</option>
        <?php foreach (Plex::getSections() as $section) : ?>
            <option value="<?php phe($section->id) ?>"><?php phe($section->title) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Import</button>
</form>
