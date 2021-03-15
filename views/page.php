<?php
namespace PommePause\Dropinambour;

use stdClass;

// Views variables
/** @var $title string */
/** @var $nav_active string|null */
// End of Views variables

$breakout_search_form = ( @$nav_active == 'discover' || @$_REQUEST['action'] == 'search' );
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php phe($title) ?></title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:ital,wght@0,300;0,400;0,600;0,700;0,900;1,300;1,400;1,600;1,700;1,900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-giJF6kkoqNQ00vy+HMDP7azOuL0xtbfIcaT9wjKHr8RbDVddVHyTfAAsrekwKmP1" crossorigin="anonymous">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" integrity="sha512-bLT0Qm9VnAYZDflyKcBaQ2gg0hSYNQrJ8RilYldYQ1FxQYoCLtUjuuRuZo+fjqhx/qtq/1itJ0C2ejDxltZVFg==" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js" integrity="sha384-ygbV9kiqUc6oa4msXn9868pTtWMgiQaeYH7/t7LECLbyPA2x65Kgf80OJFdroafW" crossorigin="anonymous"></script>
    <link href="<?php phe(Router::getAssetUrl('./css/styles.css')) ?>" rel="stylesheet">
    <?php echo $this->section('head') ?>
</head>
<body>

<div class="container">

    <?php if (!Plex::needsAuth()) : ?>
        <nav class="navbar navbar-expand-md navbar-light bg-light">
            <div class="container-fluid">
                <a class="navbar-brand" href="./">dropinambour</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link <?php echo_if(@$nav_active == 'discover', 'active') ?>" href="./">Discover</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo_if(@$nav_active == 'requests', 'active') ?>" href="<?php phe(Router::getURL(Router::ACTION_VIEW, Router::VIEW_REQUESTS)) ?>">Requests</a></li>
                        <?php if (Plex::isServerAdmin()) : ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle <?php echo_if(@$nav_active == 'admin', 'active') ?>" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">Admin</a>
                                <ul class="dropdown-menu">
                                    <li class=""><a class="dropdown-item" href="<?php phe(Router::getURL(Router::ACTION_VIEW, Router::VIEW_ADMIN_PLEX)) ?>">Plex</a></li>
                                    <li class=""><a class="dropdown-item" href="<?php phe(Router::getURL(Router::ACTION_VIEW, Router::VIEW_ADMIN_RADARR)) ?>">Radarr</a></li>
                                    <li class=""><a class="dropdown-item" href="<?php phe(Router::getURL(Router::ACTION_VIEW, Router::VIEW_ADMIN_SONARR)) ?>">Sonarr</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <form class="<?php echo_if($breakout_search_form, 'd-none d-md-flex', 'd-flex') ?>" method="get" action="./">
                        <input name="action" type="hidden" value="<?php phe(Router::ACTION_SEARCH) ?>">
                        <input name="query" class="form-control me-2" type="search" placeholder="Movie or TV Show title" value="<?php phe(@$_REQUEST['query']) ?>" aria-label="Search">
                        <?php if (count(to_array(Config::get('LANGUAGES'))) < 2) : ?>
                            <input name="language" type="hidden" value="<?php phe(first(to_array(Config::get('LANGUAGES', ['en'])))) ?>">
                        <?php else : ?>
                            <select name="language" class="form-control small me-2">
                                <?php foreach (to_array(Config::get('LANGUAGES')) as $lang) : ?>
                                    <option value="<?php phe($lang) ?>" <?php echo_if(@$_REQUEST['language'] == $lang, 'selected') ?>><?php phe(strtoupper($lang)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <button class="btn btn-outline-success" type="submit">Search</button>
                    </form>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <?php if ($breakout_search_form) : ?>
        <div class="container-fluid d-md-none">
            <form class="d-flex" method="get" action="./">
                <input name="action" type="hidden" value="<?php phe(Router::ACTION_SEARCH) ?>">
                <input name="query" class="form-control me-2" type="search" placeholder="Movie or TV Show title" value="<?php phe(@$_REQUEST['query']) ?>" aria-label="Search">
                <?php if (count(to_array(Config::get('LANGUAGES'))) < 2) : ?>
                    <input name="language" type="hidden" value="<?php phe(first(to_array(Config::get('LANGUAGES', ['en'])))) ?>">
                <?php else : ?>
                    <select name="language" class="form-control small me-2">
                        <?php foreach (to_array(Config::get('LANGUAGES')) as $lang) : ?>
                            <option value="<?php phe($lang) ?>" <?php echo_if(@$_REQUEST['language'] == $lang, 'selected') ?>><?php phe(strtoupper($lang)) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <button class="btn btn-outline-success" type="submit">Search</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="notifications"></div>

    <?php echo $this->section('content') ?>

</div>

<script type="application/javascript">
    function showAlert(html, is_error) {
        let $alert = $('<div/>')
            .html(html)
            .addClass('alert ' + (is_error ? 'alert-danger' : 'alert-success'));
        $('.notifications').append($alert);
    }
    <?php
        if (!empty($_SESSION['pending_notifications'])) {
            echo implode("\n", $_SESSION['pending_notifications']);
            $_SESSION['pending_notifications'] = [];
        }
    ?>
    function disable_button(btn, text_loading) {
        if (typeof text_loading == 'undefined') {
            text_loading = 'Loading...';
        }
        let $btn = $(btn);
        $btn.prop('disabled', true);
        if ($btn.text() !== '') {
            $btn.data('original-text', $btn.html());
            $btn.text(text_loading);
        } else {
            $btn.data('original-text', $btn.val());
            $btn.val(text_loading);
        }
    }
    function reset_button(btn) {
        let $btn = $(btn);
        $btn.prop('disabled', false);
        if ($btn.text() !== '') {
            $btn.html($btn.data('original-text'));
        } else {
            $btn.val($btn.data('original-text'));
        }
    }
</script>
</body>
</html>
