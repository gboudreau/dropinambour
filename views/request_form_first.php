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
    <input name="media_type" type="hidden" value="<?php phe($media->media_type) ?>">
    <input name="tmdb_id" type="hidden" value="<?php phe($media->id) ?>">
    <input name="tvdb_id" type="hidden" value="<?php phe($media->tvdb_id) ?>">
    <input name="title" type="hidden" value="<?php phe($media->title) ?>">
    <input name="tags" type="hidden" value="<?php phe($default_tags) ?>">
    <?php if ($media->media_type == 'tv') : ?>
        <input name="title_slug" type="hidden" value="<?php phe($media->titleSlug) ?>">
        <input name="images_json" type="hidden" value="<?php phe(json_encode($media->images)) ?>">
    <?php endif; ?>
    <?php if (!Plex::isServerAdmin()) : ?>
        <input name="path" type="hidden" value="<?php phe($default_path) ?>">
        <?php if (Config::get('RADARR_SIMPLIFIED_QUALITY')) : ?>
            <div class="col-auto">
                <label class="visually-hidden" for="quality-input">Quality</label>
                <select name="quality" class="form-control" id="quality-input">
                    <option value="">Choose one</option>
                    <?php foreach (Config::get('RADARR_SIMPLIFIED_QUALITY', [], Config::GET_OPT_PARSE_AS_JSON) as $id => $name) : ?>
                        <option value="<?php phe($id) ?>" <?php echo_if($id == $default_quality, 'selected') ?>><?php phe($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <input name="quality" type="hidden" value="<?php phe($default_quality) ?>">
        <?php endif; ?>
    <?php else : ?>
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
    <?php endif; ?>
    <?php if ($media->media_type == 'tv') : ?>
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
        <button class="btn btn-primary" type="submit" onclick="disable_button(this); this.form.submit()">Request</button>
    </div>
</form>
