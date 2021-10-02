<?php
namespace PommePause\Dropinambour;

// Views variables
/** @var $sections object[] */
// End of Views variables

// Template: https://beefree.io/editor/?template=singles-day-streaming

$title = "Newsletter | dropinambour - Requests for Plex";

global $config;
$config = (object) [
    'background_color_header' => '#690375',
    'background_color_body' => '#2c0e37',
    'width' => '680px',
];

$config->width = '1660px';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional //EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:v="urn:schemas-microsoft-com:vml">
<head>
    <title><?php phe($title) ?></title>
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type"/>
    <meta content="width=device-width" name="viewport"/>
    <meta content="IE=edge" http-equiv="X-UA-Compatible"/>
    <title></title>
    <link href="https://fonts.googleapis.com/css?family=Lato" rel="stylesheet" type="text/css"/>
    <style type="text/css">
        body {
            margin:0;
            padding:0;
        }

        table,td,tr {
            vertical-align:top;
            border-collapse:collapse;
        }

        * {
           line-height:inherit;
        }

        a[x-apple-data-detectors=true] {
            color:inherit !important;
            text-decoration:none !important;
        }
    </style>
    <style id="media-query" type="text/css">
        @media (max-width:700px) {
            .block-grid,.col {
               min-width:320px !important;
               max-width:100% !important;
               display:block !important;
            }

            .block-grid {
                width:100% !important;
            }

            .col {
                width:100% !important;
            }

            .col_cont {
                margin:0 auto;
            }

            img.fullwidth,img.fullwidthOnMobile {
               max-width:100% !important;
            }

            .no-stack .col {
               min-width:0 !important;
               display:table-cell !important;
            }

            .no-stack.two-up .col {
                width:50% !important;
            }

            .no-stack .col.num2 {
                width:16.6% !important;
            }

            .no-stack .col.num3 {
                width:25% !important;
            }

            .no-stack .col.num4 {
                width:33% !important;
            }

            .no-stack .col.num5 {
                width:41.6% !important;
            }

            .no-stack .col.num6 {
                width:50% !important;
            }

            .no-stack .col.num7 {
                width:58.3% !important;
            }

            .no-stack .col.num8 {
                width:66.6% !important;
            }

            .no-stack .col.num9 {
                width:75% !important;
            }

            .no-stack .col.num10 {
                width:83.3% !important;
            }

            .video-block {
                max-width:none !important;
            }

            .mobile_hide {
                min-height:0;
                max-height:0;
                max-width:0;
                display:none;
                overflow:hidden;
                font-size:0;
            }

            .desktop_hide {
                display:block !important;
                max-height:none !important;
            }
        }
    </style>
    <style id="menu-media-query" type="text/css">
        @media (max-width:700px) {
            .menu-checkbox[type="checkbox"]~.menu-links {
                display:none !important;
                padding:5px 0;
            }

            .menu-checkbox[type="checkbox"]~.menu-links span.sep {
               display:none;
            }

            .menu-checkbox[type="checkbox"]:checked~.menu-links,
            .menu-checkbox[type="checkbox"]~.menu-trigger {
                display:block !important;
                max-width:none !important;
                max-height:none !important;
                font-size:inherit !important;
            }

            .menu-checkbox[type="checkbox"]~.menu-links>a,
            .menu-checkbox[type="checkbox"]~.menu-links>span.label {
                display:block !important;
                text-align:center;
            }

            .menu-checkbox[type="checkbox"]:checked~.menu-trigger .menu-close {
                display:block !important;
            }

            .menu-checkbox[type="checkbox"]:checked~.menu-trigger .menu-open {
                display:none !important;
            }

            #menukpnybi~div label {
                border-radius: 0% !important;
            }

            #menukpnybi:checked~.menu-links {
               background-color:#000000 !important;
            }

            #menukpnybi:checked~.menu-links a {
                color:#ffffff !important;
            }

            #menukpnybi:checked~.menu-links span {
                color:#ffffff !important;
            }
        }
    </style>
</head>
<body class="clean-body" style="margin:0;padding:0;-webkit-text-size-adjust:100%;background-color:transparent">
<table bgcolor="transparent" cellpadding="0" cellspacing="0" class="nl-container" role="presentation" style="table-layout:fixed;vertical-align:top;min-width:320px;border-spacing:0;border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0;background-color:transparent;width:100%;" valign="top" width="100%">
    <tr style="vertical-align:top;" valign="top">
        <td style="vertical-align:top;" valign="top">
            <?php email_template_block_padding(20, 'transparent') ?>

            <?php email_template_block_header(he(Config::get("PLEX_SERVER_NAME")) . "<br/>newsletter") ?>

            <?php email_template_block_image("https://cdn.pommepause.com/image-home-why-plex-1-6-1440x982.jpg", 30, 30, 30, 30) ?>

            <?php email_template_block_title(he("Paid advertisement"), 30, 30) ?>
            <?php
            email_template_block_paragraphs(
                [
                    "<strong>dropinambour</strong> " . he("allows you to request movies and tv shows to be added onto your favorite Plex server."),
                    he("It automates the part between you asking for something, and that something becoming available on Plex."),
                    he("You really should use it."),
                ],
                10,
                0
            );
            ?>
            <?php email_template_block_one_button(he("GO TO DROPINAMBOUR"), Config::get('BASE_URL'), 20, 20) ?>
            <?php email_template_block_separator() ?>

            <?php foreach ($sections as $section) : ?>
                <?php email_template_block_title(he("New in $section->title"), 30, 30, 30) ?>
                <?php email_template_block_medias($section->medias) ?>
                <?php email_template_block_one_button(he("GO TO " . strtoupper($section->title) . " ON PLEX"), Plex::getUrlForSection($section->id, $section->type), 0, 40) ?>
            <?php endforeach; ?>

            <?php email_template_block_separator() ?>
            <?php email_template_block_title("/ " . he(Config::get("PLEX_SERVER_NAME")) . " newsletter", 23, 20, 30) ?>
            <?php email_template_block_footer(
                    he("This newsletter was sent to you by dropinambour, because it thinks you are cool, and worth the few kilobytes of data, and the few milliseconds that were used to create and send this email."),
                    '<a href="mailto:' . he(Config::get('NEW_REQUESTS_NOTIF_EMAIL')) . '" rel="noopener" style="color: #cfceca;" target="_blank">Unsubscribe</a>') ?>

            <?php email_template_block_padding(20, 'transparent') ?>
        </td>
    </tr>
</table>
</body>
</html>
<?php

function email_template_block_header(string $title) : void {
    global $config;
    ?>
    <div class="dinb_header" style="background-color:transparent">
        <div class="block-grid two-up" style="min-width:320px;max-width:<?php phe($config->width) ?>;overflow-wrap:break-word;word-wrap:break-word;margin:0 auto;background-color:transparent">
            <div style="border-collapse:collapse;display:table;width:100%;background-color:transparent">
            <div class="col num12" style="display:table-cell;vertical-align:top;max-width:<?php phe($config->width) ?>;min-width:320px;width:<?php phe($config->width) ?>;text-align:right;font-family:'Lato',Tahoma,Verdana,Segoe,sans-serif;font-size:14px;padding: 10px;">
                If you have trouble reading this email, view the
                <a href="<?php phe(trim(Config::get('BASE_URL'), '/') . '/' . Router::getURL(Router::ACTION_VIEW, Router::VIEW_NEWSLETTER)) ?>" style="display:inline;text-decoration:none;letter-spacing:undefined; color: #cb429f">
                    web version
                </a>
            </div>
        </div>
        <div class="block-grid two-up" style="min-width:320px;max-width:<?php phe($config->width) ?>;overflow-wrap:break-word;word-wrap:break-word;margin:0 auto;background-color:<?php phe($config->background_color_header) ?>">
            <div style="border-collapse:collapse;display:table;width:100%;background-color:<?php phe($config->background_color_header) ?>">
                <div class="col num6" style="display: table-cell;vertical-align:top;max-width:320px;min-width:336px;width:340px">
                    <div class="col_cont" style="width:100% !important">
                        <div style="padding:25px 0 30px">
                            <table cellpadding="0" cellspacing="0" role="presentation" style="table-layout:fixed;vertical-align:top;border-spacing:0;border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0;" valign="top" width="100%">
                                <tr style="vertical-align:top;" valign="top">
                                    <td align="center" style="vertical-align:top;padding-left:10px;text-align:center;width:100%;" valign="top" width="100%">
                                        <h1 style="color:#f0f0f0;direction:ltr;font-family:'Lato',Tahoma,Verdana,Segoe,sans-serif;font-size:23px;font-weight:normal;letter-spacing:normal;text-align:center;line-height:120%;margin-top:0;margin-bottom:0"><?php echo $title ?></strong></h1>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col num6" style="display: table-cell;vertical-align:top;max-width:320px;min-width:336px;width:340px">
                    <div class="col_cont" style="width:100% !important">
                        <div style="padding:20px 0 30px">
                            <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="table-layout:fixed;vertical-align:top;border-spacing:0;border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0;" valign="top" width="100%">
                                <tr style="vertical-align:top;" valign="top">
                                    <td align="right" style="vertical-align:top;padding-right:10px;text-align:right;font-size:0" valign="top">
                                        <div class="menu-links">
                                            <a href="<?php phe(Config::get('BASE_URL')) ?>" target="_blank" style="padding: 10px;display:inline;color:#f0f0f0;font-family:'Lato',Tahoma,Verdana,Segoe,sans-serif;font-size:14px;text-decoration:none;letter-spacing:undefined">dropinambour</a>
                                            <span class="sep" style="font-size:14px;font-family:'Lato',Tahoma,Verdana,Segoe,sans-serif;color:#f0f0f0">|</span>
                                            <a href="https://app.plex.tv/desktop#!/" target="_blank" style="padding: 10px;display:inline;color:#f0f0f0;font-family:'Lato',Tahoma,Verdana,Segoe,sans-serif;font-size:14px;text-decoration:none;letter-spacing:undefined">Plex</a>
                                            <span class="sep" style="font-size:14px;font-family:'Lato',Tahoma,Verdana,Segoe,sans-serif;color:#f0f0f0">|</span>
                                            <a href="mailto:<?php phe(Config::get('NEW_REQUESTS_NOTIF_EMAIL')) ?>" style="padding: 10px;display:inline;color:#f0f0f0;font-family:'Lato',Tahoma,Verdana,Segoe,sans-serif;font-size:14px;text-decoration:none;letter-spacing:undefined">Contact</a>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function email_template_block_title(string $title, int $font_size = 30, int $padding_top = 5, int $padding_bottom = 5) : void {
    global $config;
    ?>
    <div class="dinb_title" style="background-color:transparent">
        <div class="block-grid" style="min-width:320px;max-width:<?php phe($config->width) ?>;overflow-wrap:break-word;word-wrap:break-word;margin:0 auto;background-color:<?php phe($config->background_color_body) ?>">
            <div style="border-collapse:collapse;display:table;width:100%;background-color:<?php phe($config->background_color_body) ?>">
                <div class="col num12" style="min-width:320px;max-width:<?php phe($config->width) ?>;display:table-cell;vertical-align:top;width:<?php phe($config->width) ?>">
                    <div class="col_cont" style="width:100% !important">
                        <div style="padding:<?php phe($padding_top) ?>px 0 <?php phe($padding_bottom) ?>px">
                            <div style="color:#fefefe;font-family:'Lato',Tahoma,Verdana,Segoe,sans-serif;line-height:1.2;padding:0 10px">
                                <div class="txtTinyMce-wrapper" style="line-height:1.2;font-size:12px;font-family:'Lato',Tahoma,Verdana,Segoe,sans-serif;color:#fefefe;mso-line-height-alt:14px">
                                    <p style="text-align:center;line-height:1.2;font-family:Lato,Tahoma,Verdana,Segoe,sans-serif;font-size:<?php phe($font_size) ?>px;mso-line-height-alt:<?php phe(round($font_size*1.2)) ?>px;margin:0">
                                        <span style="font-size:<?php phe($font_size) ?>px">
                                            <?php echo $title ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function email_template_block_paragraphs(array $texts, int $padding_top = 5, int $padding_bottom = 5) : void {
    global $config;
    ?>
    <div class="dinb_paragraphs" style="background-color:transparent">
        <div class="block-grid" style="min-width:320px;max-width:<?php phe($config->width) ?>;overflow-wrap:break-word;word-wrap:break-word;margin:0 auto;background-color:<?php phe($config->background_color_body) ?>">
            <div style="border-collapse:collapse;display:table;width:100%;background-color:<?php phe($config->background_color_body) ?>">
                <div class="col num12" style="min-width:320px;max-width:<?php phe($config->width) ?>;display:table-cell;vertical-align:top;width:<?php phe($config->width) ?>">
                    <div class="col_cont" style="width:100% !important">
                        <div style="padding: <?php phe($padding_top) ?>px 30px <?php phe($padding_bottom) ?>px;">
                            <div style="color:#ffffff;font-family:'Lato',Tahoma,Verdana,Segoe,sans-serif;line-height:1.2;padding:10px">
                                <div class="txtTinyMce-wrapper" style="line-height:1.2;font-size:12px;font-family:'Lato',Tahoma,Verdana,Segoe,sans-serif;color:#ffffff;mso-line-height-alt:14px">
                                    <?php foreach ($texts as $text) : ?>
                                        <p style="text-align:center;line-height:1.2;font-family:Lato,Tahoma,Verdana,Segoe,sans-serif;font-size:16px;mso-line-height-alt:19px;margin:0"><span style="font-size:16px"><?php echo $text ?></span></p>
                                        <p style="text-align:center;line-height:0.5;mso-line-height-alt:6px;margin:0"> </p>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function email_template_block_padding(int $height, string $bg_color) : void {
    global $config;
    ?>
    <div class="dinb_padding" style="background-color:transparent">
        <div class="block-grid" style="min-width:320px;max-width:<?php phe($config->width) ?>;overflow-wrap:break-word;word-wrap:break-word;margin:0 auto;background-color:<?php phe($bg_color) ?>">
            <div style="border-collapse:collapse;display:table;width:100%;background-color:<?php phe($bg_color) ?>">
                <div class="col num12" style="min-width:320px;max-width:<?php phe($config->width) ?>;display:table-cell;vertical-align:top;width:<?php phe($config->width) ?>">
                    <div class="col_cont" style="width:100% !important">
                        <div style="padding-left:0">
                            <table border="0" cellpadding="0" cellspacing="0" class="divider" role="presentation" style="table-layout:fixed;vertical-align:top;border-spacing:0;border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0;min-width:100%;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;" valign="top" width="100%">
                                <tr style="vertical-align:top;" valign="top">
                                    <td class="divider_inner" style="vertical-align:top;min-width:100%;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;padding:0 10px" valign="top">
                                        <table align="center" border="0" cellpadding="0" cellspacing="0" class="divider_content" height="30" role="presentation" style="table-layout:fixed;vertical-align:top;border-spacing:0;border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0;border-top:0px solid transparent;height:30px;width:100%;" valign="top" width="100%">
                                            <tr style="vertical-align:top;" valign="top">
                                                <td height="<?php phe($height) ?>" style="vertical-align:top;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;" valign="top"><span></span></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function email_template_block_separator() : void {
    global $config;
    ?>
    <div class="dinb_separator" style="background-color:transparent">
        <div class="block-grid" style="min-width:320px;max-width:<?php phe($config->width) ?>;overflow-wrap:break-word;word-wrap:break-word;margin:0 auto;background-color:<?php phe($config->background_color_body) ?>">
            <div style="border-collapse:collapse;display:table;width:100%;background-color:<?php phe($config->background_color_body) ?>">
                <div class="col num12" style="min-width:320px;max-width:<?php phe($config->width) ?>;display:table-cell;vertical-align:top;width:<?php phe($config->width) ?>">
                    <div class="col_cont" style="width:100% !important">
                        <div style="padding:5px 0">
                            <table border="0" cellpadding="0" cellspacing="0" class="divider" role="presentation" style="table-layout:fixed;vertical-align:top;border-spacing:0;border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0;min-width:100%;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;" valign="top" width="100%">
                                <tr style="vertical-align:top;" valign="top">
                                    <td class="divider_inner" style="vertical-align:top;min-width:100%;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;padding: 10px;" valign="top">
                                        <table align="center" border="0" cellpadding="0" cellspacing="0" class="divider_content" role="presentation" style="table-layout:fixed;vertical-align:top;border-spacing:0;border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0;border-top:1px solid #BBBBBB;width:80%;" valign="top" width="80%">
                                            <tr style="vertical-align:top;" valign="top">
                                                <td style="vertical-align:top;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;" valign="top"><span></span></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function email_template_block_image(string $image_url, int $padding_top = 5, int $padding_right = 5, int $padding_bottom = 5, int $padding_left = 5) : void {
    global $config;
    ?>
    <div class="dinb_image" style="background-color:transparent">
        <div class="block-grid" style="min-width:320px;max-width:<?php phe($config->width) ?>;overflow-wrap:break-word;word-wrap:break-word;margin:0 auto;background-color:transparent">
            <div style="border-collapse:collapse;display:table;width:100%;background-color:transparent">
                <div class="col num12" style="min-width:320px;max-width:<?php phe($config->width) ?>;display:table-cell;vertical-align:top;width:<?php phe($config->width) ?>">
                    <div class="col_cont" style="width:100% !important">
                        <div style="padding-left:0">
                            <div align="center" class="img-container center autowidth" style="padding-right:<?php phe($padding_right) ?>px;padding-left:<?php phe($padding_left) ?>px">
                                <div style="font-size:1px;line-height:<?php phe($padding_top) ?>px"> </div>
                                <img align="center" alt="Plex" border="0" class="center autowidth" src="<?php phe($image_url) ?>" style="text-decoration: none;-ms-interpolation-mode: bicubic;height:auto;border:0;width:100%;max-width:620px;display:block;" title="Plex" width="620"/>
                                <div style="font-size:1px;line-height:<?php phe($padding_bottom) ?>px"> </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function email_template_block_one_button(string $button_text, string $url, int $padding_top = 10, int $padding_bottom = 10) : void {
    global $config;
    ?>
    <div class="dinb_button_one" style="background-color:transparent">
        <div class="block-grid" style="min-width:320px;max-width:<?php phe($config->width) ?>;overflow-wrap:break-word;word-wrap:break-word;margin:0 auto;background-color:<?php phe($config->background_color_body) ?>">
            <div style="border-collapse:collapse;display:table;width:100%;background-color:<?php phe($config->background_color_body) ?>">
                <div class="col num12" style="min-width:320px;max-width:<?php phe($config->width) ?>;display:table-cell;vertical-align:top;width:<?php phe($config->width) ?>">
                    <div class="col_cont" style="width:100% !important">
                        <div style="padding: <?php phe($padding_top) ?>px 10px <?php phe($padding_bottom) ?>px;">
                            <div align="center" class="button-container" style="padding: 10px;">
                                <a href="<?php echo $url ?>" style="-webkit-text-size-adjust: none; text-decoration: none;display:inline-block;color:<?php phe($config->background_color_body) ?>;background-color:#cb429f;border-radius: 4px; -webkit-border-radius: 4px; -moz-border-radius: 4px;width:auto;width:auto;padding:5px 0;font-family:'Lato',Tahoma,Verdana,Segoe,sans-serif;text-align:center;mso-border-alt: none;word-break:keep-all;" target="_blank"><span style="padding: 0 30px;font-size:16px;display:inline-block;letter-spacing:undefined"><span style="font-size:16px;line-height:2;mso-line-height-alt:32px"><?php echo $button_text ?></span></span></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function email_template_block_medias(array $medias) : void {
    while (!empty($medias)) {
        $media1 = array_shift($medias);
        $is_new_show = empty($media1->recent_episodes);

        $media2 = FALSE;
        if (!empty($medias)) {
            $is_also_new_show = empty(first($medias)->recent_episodes);
            if (($is_new_show && $is_also_new_show) || (!$is_new_show && !$is_also_new_show)) {
                $media2 = array_shift($medias);
                $is_new_show = $is_also_new_show;
                $is_also_new_show = empty(first($medias)->recent_episodes);
            }
        }

        $poster_html_1 = get_poster_html_for_media($media1);
        if (empty($media2)) {
            $poster_html_2 = '';
        } else {
            $poster_html_2 = get_poster_html_for_media($media2);
        }

        email_template_block_posters_2442($poster_html_1, $poster_html_2);

        if ($is_new_show && @$is_also_new_show === FALSE) {
             email_template_block_title(he("And new episodes of :"), 24, 20, 20);
        }
    }
}

function email_template_block_posters_2442(string $poster_html_1, string $poster_html_2) : void {
    global $config;
    ?>
    <div style="background-color:transparent">
        <div class="block-grid four-up" style="min-width:320px;max-width:<?php phe($config->width) ?>;overflow-wrap:break-word;word-wrap:break-word;margin:0 auto;background-color:<?php phe($config->background_color_body) ?>">
            <div style="border-collapse:collapse;display:table;width:100%;background-color:<?php phe($config->background_color_body) ?>">
                <div class="col num2" style="display: table-cell;vertical-align:top;max-width:320px;min-width:112px;width:113px">
                    <div class="col_cont" style="width:100% !important">
                        <div style="padding-left:0">
                            <table border="0" cellpadding="0" cellspacing="0" class="divider" role="presentation" style="table-layout:fixed;vertical-align:top;border-spacing:0;border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0;min-width:100%;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;" valign="top" width="100%">
                                <tr style="vertical-align:top;" valign="top">
                                    <td class="divider_inner" style="vertical-align:top;min-width:100%;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;padding:0 10px" valign="top">
                                        <table align="center" border="0" cellpadding="0" cellspacing="0" class="divider_content" height="0" role="presentation" style="table-layout:fixed;vertical-align:top;border-spacing:0;border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0;border-top:0px solid transparent;height:0;width:100%;" valign="top" width="100%">
                                            <tr style="vertical-align:top;" valign="top">
                                                <td height="0" style="vertical-align:top;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;" valign="top"><span></span></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col num4" style="display: table-cell;vertical-align:top;max-width:320px;min-width:224px;width:226px">
                    <div class="col_cont" style="width:100% !important">
                        <div style="padding-left:0">
                            <?php echo $poster_html_1 ?>
                        </div>
                    </div>
                </div>
                <div class="col num4" style="display: table-cell;vertical-align:top;max-width:320px;min-width:224px;width:226px">
                    <div class="col_cont" style="width:100% !important">
                        <div style="padding-left:0">
                            <?php echo $poster_html_2 ?>
                        </div>
                    </div>
                </div>
                <div class="col num2" style="display: table-cell;vertical-align:top;max-width:320px;min-width:112px;width:113px">
                    <div class="col_cont" style="width:100% !important">
                        <div style="padding-left:0">
                            <table border="0" cellpadding="0" cellspacing="0" class="divider" role="presentation" style="table-layout:fixed;vertical-align:top;border-spacing:0;border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0;min-width:100%;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;" valign="top" width="100%">
                                <tr style="vertical-align:top;" valign="top">
                                    <td class="divider_inner" style="vertical-align:top;min-width:100%;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;padding: 10px;" valign="top">
                                        <table align="center" border="0" cellpadding="0" cellspacing="0" class="divider_content" height="0" role="presentation" style="table-layout:fixed;vertical-align:top;border-spacing:0;border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0;border-top:0px solid transparent;height:0;width:100%;" valign="top" width="100%">
                                            <tr style="vertical-align:top;" valign="top">
                                                <td height="0" style="vertical-align:top;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;" valign="top"><span></span></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    email_template_block_padding(12, $config->background_color_body);
}

function get_poster_html_for_media(object $media) : string {
    global $config;
    $title = he($media->title);
    $sub_title = '';
    if (!empty($media->details->vote_average)) {
        $sub_title .= round($media->details->vote_average*10) . '% &nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;';
    }
    $sub_title .= he($media->year ?? $media->details->first_air_date ?? '');

    if (!empty($media->recent_episodes)) {
        foreach (array_reverse($media->recent_episodes) as $ep) {
            $title = sprintf("S%02dE%02d", $media->season, $ep->index);
            $sub_title = he($ep->title);
            break;
        }
    }
    ob_start();
    ?>
    <div align="center" class="img-container center autowidth" style="padding-left:0">
        <a href="<?php phe(Plex::geUrlForMediaKey($media->key)) ?>" target="_blank" style="outline:none" tabindex="-1"><img align="center" alt="Poster" border="0" class="center autowidth" src="<?php phe(empty($media->details->poster_path) ? './img/no_poster.png' : TMDB::getPosterImageUrl($media->details->poster_path, TMDB::IMAGE_SIZE_POSTER_W185)) ?>" style="text-decoration: none;-ms-interpolation-mode: bicubic;height:auto;border:0;width:100%;max-width:207px;display:block;" title="Poster" width="207"/></a>
        <div style="font-size:1px;line-height:5px"> </div>
    </div>
    <div class="sections" align="center" class="center autowidth" style="padding-left:0">
        <a href="<?php phe(Plex::geUrlForMediaKey($media->key)) ?>" target="_blank" style="line-height:1.2;font-size:18px;font-family:'Lato',Tahoma,Verdana,Segoe,sans-serif;color:#ffffff;mso-line-height-alt:22px; text-decoration: none">
            <?php echo $title ?>
        </a>
    </div>
    <div class="sections" align="center" class="center autowidth" style="padding-left:0">
        <div style="font-size:1px;line-height:5px"> </div>
        <span style="line-height:1.2;font-size:14px;font-family:'Lato',Tahoma,Verdana,Segoe,sans-serif;color:#ffffff;mso-line-height-alt:17px">
            <?php echo $sub_title ?>
        </span>
    </div>
    <?php
    return ob_get_clean();
}

function email_template_block_footer(string $text, string $link = NULL) : void {
    global $config;
    ?>
    <div class="dinb_footer" style="background-color:transparent">
        <div class="block-grid" style="min-width:320px;max-width:<?php phe($config->width) ?>;overflow-wrap:break-word;word-wrap:break-word;margin:0 auto;background-color:<?php phe($config->background_color_body) ?>">
            <div style="border-collapse:collapse;display:table;width:100%;background-color:<?php phe($config->background_color_header) ?>">
                <div class="col num12" style="min-width:320px;max-width:<?php phe($config->width) ?>;display:table-cell;vertical-align:top;width:<?php phe($config->width) ?>">
                    <div class="col_cont" style="width:100% !important">
                        <div style="padding: 5px 30px;">
                            <div style="color:#cfceca;font-family:'Lato',Tahoma,Verdana,Segoe,sans-serif;line-height:1.8;padding: 10px;">
                                <div class="txtTinyMce-wrapper" style="line-height:1.8;font-size:12px;color:#cfceca;font-family:'Lato',Tahoma,Verdana,Segoe,sans-serif;mso-line-height-alt:22px">
                                    <p style="text-align:center;line-height:1.8;mso-line-height-alt:22px;margin:0"><?php echo $text ?></p>
                                    <?php if (!empty($link)) : ?>
                                        <p style="text-align:center;line-height:1.8;mso-line-height-alt:22px;margin:0"> </p>
                                        <p style="font-size:12px;line-height:1.8;text-align:center;mso-line-height-alt:22px;margin:0"><span style="font-size:12px"><?php echo $link ?></span></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="color:#cfceca;font-family:'Lato',Tahoma,Verdana,Segoe,sans-serif;line-height:1.2;padding: 10px;">
                                <div class="txtTinyMce-wrapper" style="line-height:1.2;font-size:12px;color:#cfceca;font-family:'Lato',Tahoma,Verdana,Segoe,sans-serif;mso-line-height-alt:14px">
                                    <p style="font-size:12px;line-height:1.2;text-align:center;mso-line-height-alt:14px;margin:0"><span style="font-size:12px"><?php echo date('Y') ?> © G</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function email_template_block_2buttons() : void {
    global $config;
    ?>
    <div style="background-color:transparent">
        <div class="block-grid four-up" style="min-width:320px;max-width:<?php phe($config->width) ?>;overflow-wrap:break-word;word-wrap:break-word;margin:0 auto;background-color:<?php phe($config->background_color_body) ?>">
            <div style="border-collapse:collapse;display:table;width:100%;background-color:<?php phe($config->background_color_body) ?>">
                <div class="col num2" style="display: table-cell;vertical-align:top;max-width:320px;min-width:112px;width:113px">
                    <div class="col_cont" style="width:100% !important">
                        <div style="padding: 5px 10px;">
                            <table border="0" cellpadding="0" cellspacing="0" class="divider" role="presentation" style="table-layout:fixed;vertical-align:top;border-spacing:0;border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0;min-width:100%;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;" valign="top" width="100%">
                                <tr style="vertical-align:top;" valign="top">
                                    <td class="divider_inner" style="vertical-align:top;min-width:100%;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;padding: 10px;" valign="top">
                                        <table align="center" border="0" cellpadding="0" cellspacing="0" class="divider_content" height="0" role="presentation" style="table-layout:fixed;vertical-align:top;border-spacing:0;border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0;border-top:0px solid transparent;height:0;width:100%;" valign="top" width="100%">
                                            <tr style="vertical-align:top;" valign="top">
                                                <td height="0" style="vertical-align:top;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;" valign="top"><span></span></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col num4" style="display: table-cell;vertical-align:top;max-width:320px;min-width:224px;width:226px">
                    <div class="col_cont" style="width:100% !important">
                        <div style="padding: 5px 10px 10px">
                            <div align="center" class="button-container" style="padding: 10px;">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col num4" style="display: table-cell;vertical-align:top;max-width:320px;min-width:224px;width:226px">
                    <div class="col_cont" style="width:100% !important">
                        <div style="padding:5px 0 10px">
                            <div align="center" class="button-container" style="padding: 10px;">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col num2" style="display: table-cell;vertical-align:top;max-width:320px;min-width:112px;width:113px">
                    <div class="col_cont" style="width:100% !important">
                        <div style="padding:5px 0">
                            <table border="0" cellpadding="0" cellspacing="0" class="divider" role="presentation" style="table-layout:fixed;vertical-align:top;border-spacing:0;border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0;min-width:100%;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;" valign="top" width="100%">
                                <tr style="vertical-align:top;" valign="top">
                                    <td class="divider_inner" style="vertical-align:top;min-width:100%;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;padding: 10px;" valign="top">
                                        <table align="center" border="0" cellpadding="0" cellspacing="0" class="divider_content" height="0" role="presentation" style="table-layout:fixed;vertical-align:top;border-spacing:0;border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0;border-top:0px solid transparent;height:0;width:100%;" valign="top" width="100%">
                                            <tr style="vertical-align:top;" valign="top">
                                                <td height="0" style="vertical-align:top;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;" valign="top"><span></span></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}
