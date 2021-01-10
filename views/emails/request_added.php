<?php
namespace PommePause\Dropinambour;

use stdClass;

// Views variables
/** @var $params stdClass */
// End of Views variables

?>
<!DOCTYPE html>
<html lang="en">
<body>

<div style="margin-top: 12px">Hi Admin,</div>

<div style="margin-top: 12px">
    <?php if (!empty($params->season_number)) : ?>
        <?php phe(sprintf("%s added a request for the season %d of \"%s\".", $params->request->requested_by, $params->season_number, $params->request->title)) ?>
    <?php else : ?>
        <?php phe(sprintf("%s added a request for the %s \"%s\".", $params->request->requested_by, $params->request->media_type, $params->request->title)) ?>
    <?php endif; ?>
</div>

<div style="margin-top: 12px">Good day to you.</div>

<div style="margin-top: 12px">- The dropinambour Bot</div>

</body>
</html>
