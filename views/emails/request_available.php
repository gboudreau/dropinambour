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

<div style="margin-top: 12px">Hi<?php phe(!empty($params->username) ? " $params->username" : "")?>,</div>

<div style="margin-top: 12px">The <?php phe($params->request->media_type) ?> you requested, <?php phe($params->request->title) ?>, has been added to Plex.</div>

<div style="margin-top: 12px">Good day to you.</div>

<div style="margin-top: 12px">- The dropinambour Bot</div>

</body>
</html>
