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

<form class="row gy-2 gx-3 align-items-center" method="post" action="<?php phe(Router::getURL(Router::ACTION_SAVE, Router::SAVE_REQUEST)) ?>">
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
                <option value="<?php phe(@$season->is_available === TRUE || @$season->monitored || @$season->episode_count == 0 ? '' : $season->season_number) ?>">
                    <?php phe($season->name) ?>
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
    <?php if (Plex::isServerAdmin() && empty($media->requested)) : ?>
        <div class="col-auto">
            <label class="visually-hidden" for="path-input">Path</label>
            <select name="path" class="form-control" id="path-input">
                <option value="">Path</option>
                <?php foreach ($paths as $path) : ?>
                    <option value="<?php phe($path) ?>" <?php echo_if($path == $default_path, 'selected') ?>><?php phe($path) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <label class="visually-hidden" for="quality-input">Quality</label>
            <select name="quality" class="form-control" id="quality-input">
                <option value="">Quality</option>
                <?php foreach ($profiles as $qp) : ?>
                    <option value="<?php phe($qp->id) ?>" <?php echo_if($qp->id == $default_quality, 'selected') ?>><?php phe($qp->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <label class="visually-hidden" for="language-input">Language</label>
            <select name="language" class="form-control" id="language-input">
                <option value="">Language</option>
                <?php foreach (Sonarr::getLanguageProfiles() as $lp) : ?>
                    <option value="<?php phe($lp->id) ?>" <?php echo_if($lp->id == $default_language, 'selected') ?>><?php phe($lp->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <div class="col-auto">
        <button class="btn btn-primary" type="submit" disabled onclick="disable_button(this); this.form.submit()">Request</button>
    </div>
</form>
