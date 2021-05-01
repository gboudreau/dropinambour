<?php
namespace PommePause\Dropinambour;

use PommePause\Dropinambour\ActiveRecord\Request;

/** @var Request[] $requests_mine */
/** @var Request[] $requests_others */
/** @var Request[] $requests_filled */

$this->layout('/page', ['title' => "Requests | dropinambour - Requests for Plex", 'nav_active' => 'requests']);

function echo_title_sort($title) : void {
    $sort_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-sort-alpha-down" viewBox="0 0 16 16">
      <path fill-rule="evenodd" d="M10.082 5.629 9.664 7H8.598l1.789-5.332h1.234L13.402 7h-1.12l-.419-1.371h-1.781zm1.57-.785L11 2.687h-.047l-.652 2.157h1.351z"/>
      <path d="M12.96 14H9.028v-.691l2.579-3.72v-.054H9.098v-.867h3.785v.691l-2.567 3.72v.054h2.645V14zM4.5 2.5a.5.5 0 0 0-1 0v9.793l-1.146-1.147a.5.5 0 0 0-.708.708l2 1.999.007.007a.497.497 0 0 0 .7-.006l2-2a.5.5 0 0 0-.707-.708L4.5 12.293V2.5z"/>
    </svg>';
    $sort_by_param = str_replace(' ', '_', strtolower($title));
    if (@$_REQUEST['sort_by'] == $sort_by_param) {
        phe($title);
        echo " $sort_icon";
    } else {
        echo "<a href='" . Router::getURL(Router::ACTION_VIEW, Router::VIEW_REQUESTS, ['sort_by' => $sort_by_param]) . "'>" . he($title) . "</a>";
    }
}
?>

<?php $this->push('head') ?>
<link href="./css/requests.css" rel="stylesheet">
<?php $this->end() ?>

<?php foreach (["My Requests" => $requests_mine, "Others' Requests" => $requests_others, "Filled Requests" => $requests_filled] as $title => $requests) : ?>
    <h1 style="text-align: center"><?php phe($title) ?></h1>

    <?php if (empty($requests) && $title == 'My Requests') : ?>
        <div class="alert alert-primary" role="alert">
            You didn't request anything yet.<br/>
            Use the <a href="./">Discover</a> page, or the search field, to find a movie or TV show you'd like to be added on Plex.
        </div>
        <?php continue; ?>
    <?php endif; ?>

    <?php if (empty($requests)) continue; ?>

    <?php $show_buttons = $title != "Filled Requests" && (Plex::isServerAdmin() || $title == "My Requests") ?>

    <table id="requests_list" class="table table-striped" style="width: fit-content; margin: 0 auto">
        <thead>
        <tr>
            <th class="text-center" style="width: 80px"><?php echo_title_sort('Type') ?></th>
            <th class="text-center" style="width: 90px">Status</th>
            <th><?php echo_title_sort('Title') ?></th>
            <?php if (!empty(first($requests)->requested_by->username)) : ?>
                <th><?php echo_title_sort('Requested By') ?></th>
            <?php endif; ?>
            <th class="text-center" style="width: 130px"><?php echo_title_sort('Release Date') ?></th>
            <th style="<?php echo_if($show_buttons, 'width: 100px') ?>"></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($requests as $request) : ?>
            <tr>
                <td class="text-center"><?php phe($request->type == 'show' ? 'TV Show' : 'Movie') ?></td>
                <td class="text-center <?php phe(!empty($request->filled_when) ? 'available' : 'requested') ?>"><?php phe(!empty($request->filled_when) ? 'Available' : 'Pending') ?></td>
                <td>
                    <?php if (!empty($request->tmdb_id ?? $request->tmdbtv_id)) : ?>
                        <a href="<?php phe(Router::getURL(Router::ACTION_VIEW, Router::VIEW_MEDIA, [($request->type == 'show' ? 'tv' : 'movie') => $request->tmdb_id ?? $request->tmdbtv_id ?? NULL])) ?>"><?php phe($request->title) ?></a>
                    <?php else : ?>
                        <?php phe($request->title) ?>
                    <?php endif; ?>
                </td>
                <?php if (!empty(first($requests)->requested_by->username)) : ?>
                    <td><?php phe($request->requested_by->username) ?></td>
                <?php endif; ?>
                <td class="text-center">
                    <?php phe($request->details->release_date ?? $request->details->first_air_date ?? $request->details->next_episode_to_air->air_date ?? '') ?>
                </td>
                <td>
                    <?php if ($show_buttons) : ?>
                        <form method="post" action="<?php phe(Router::getURL(Router::ACTION_REMOVE, Router::REMOVE_REQUEST, ['id' => $request->id])) ?>" onsubmit="<?php phe("if(!confirm(" . json_encode("Are you sure that you want to remove the request for \"$request->title\" ?") . ")) return false;") ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z"></path>
                                </svg>
                                <span class="d-none d-md-inline">Remove</span>
                            </button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endforeach; ?>
