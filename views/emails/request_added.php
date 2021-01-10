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

<div style="margin-top: 12px"><?php phe($params->request->requested_by) ?> added a request for the <?php phe($params->request->media_type) ?> "<?php phe($params->request->title) ?>".</div>

<div style="margin-top: 12px">Good day to you.</div>

<div style="margin-top: 12px">- The dropinambour Bot</div>

</body>
</html>
