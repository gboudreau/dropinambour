<?php
namespace PommePause\Dropinambour;

use stdClass;

/** @var stdClass   $media */
/** @var string     $default_tags */
/** @var string     $default_path */
/** @var string     $default_quality */
/** @var string     $default_language */
/** @var string[]   $paths */
/** @var stdClass[] $profiles */
?>

<form class="row gy-2 gx-3 align-items-center" method="post" action="<?php phe(Router::getURL(Router::ACTION_SAVE, Router::SAVE_REQUEST)) ?>&language=<?= @$_GET['language'] ?>">
    <input name="req_id" type="hidden" value="<?php phe(@$media->requested->id) ?>">
    <input name="media_type" type="hidden" value="<?php phe($media->media_type) ?>">
    <input name="tmdb_id" type="hidden" value="<?php phe($media->id) ?>">
    <input name="tvdb_id" type="hidden" value="<?php phe($media->tvdb_id) ?>">
    <input name="title" type="hidden" value="<?php phe($media->title) ?>">
    <input name="title_slug" type="hidden" value="<?php phe(@$media->titleSlug) ?>">
    <input name="images_json" type="hidden" value="<?php phe(json_encode($media->images ?? [])) ?>">
    <div class="col-auto">
        <label class="visually-hidden" for="season-input">Season</label>
        <select name="season" class="form-control" id="season-input" required onchange="$(this).closest('form').find('button').prop('disabled', $(this).val() === '');">
            <option value="">Choose a season</option>
            <?php foreach ($media->seasons as $season) : ?>
                <option value="<?php phe(@$season->is_available === TRUE || @$season->monitored || @$season->episode_count == 0 || @$season->is_available === 'partially' ? '' : $season->season_number) ?>">
                    <?php
                    if (preg_match('/Season (\d+)/', $season->name) || $season->name == 'Specials') {
                        phe($season->name);
                    } else {
                        phe(sprintf('[S%02d] %s', $season->season_number, $season->name));
                    }
                    ?>
                    <?php if (@$season->is_available === TRUE) : ?>
                        (already available)
                    <?php elseif (@$season->monitored) : ?>
                        (already requested)
                    <?php elseif (@$season->episode_count == 0) : ?>
                        (empty)
                    <?php elseif (@$season->is_available === 'partially') : ?>
                        (partial)
                    <?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <input name="path" type="hidden" value="<?php phe($default_path) ?>">
    <input name="quality" type="hidden" value="<?php phe($default_quality) ?>">
    <input name="language" type="hidden" value="<?php phe($default_language) ?>">
    <div class="col-auto">
        <button class="btn btn-primary" type="submit" disabled onclick="disable_button(this); this.form.submit()">Request</button>
    </div>
</form>
