<?php
namespace PommePause\Dropinambour;

use PommePause\Dropinambour\ActiveRecord\Request;

/** @var Request[] $requests_mine */
/** @var Request[] $requests_others */

$this->layout('/page', ['title' => "Requests | dropinambour - Requests for Plex"]);
?>

<?php $this->push('head') ?>
<link href="./css/requests.css" rel="stylesheet">
<?php $this->end() ?>

<?php foreach (["My Requests" => $requests_mine, "Others' Requests" => $requests_others] as $title => $requests) : ?>
    <?php if (empty($requests)) { continue; } ?>

    <h1><?php phe($title) ?></h1>

    <table id="requests_list" class="table table-striped">
        <thead>
        <tr>
            <th>Type</th>
            <th>Status</th>
            <th>Title</th>
            <?php if (!empty(first($requests)->requested_by->username)) : ?>
                <th>Requested By</th>
            <?php endif; ?>
            <th>Requested When</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($requests as $request) : ?>
            <tr>
                <td><?php phe($request->monitored_by == 'sonarr' ? 'TV Show' : 'Movie') ?></td>
                <td class="<?php phe(!empty($request->filled_when) ? 'available' : 'requested') ?>"><?php phe(!empty($request->filled_when) ? 'Available' : 'Pending') ?></td>
                <td>
                    <?php if (!empty($request->tmdb_id ?? $request->tmdbtv_id)) : ?>
                        <a href="<?php phe(Router::getURL(Router::ACTION_VIEW, Router::VIEW_MEDIA, [($request->monitored_by == 'sonarr' ? 'tv' : 'movie') => $request->tmdb_id ?? $request->tmdbtv_id ?? NULL])) ?>"><?php phe($request->title) ?></a>
                    <?php else : ?>
                        <?php phe($request->title) ?>
                    <?php endif; ?>
                </td>
                <?php if (!empty(first($requests)->requested_by->username)) : ?>
                    <td><?php phe($request->requested_by->username) ?></td>
                <?php endif; ?>
                <td><?php phe($request->added_when) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endforeach; ?>
