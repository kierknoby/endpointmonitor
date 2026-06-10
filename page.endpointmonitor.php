<?php
/**
 * EndPoint Monitor page controller.
 */

if (!defined('FREEPBX_IS_AUTH')) {
	die('No direct script access allowed');
}

$content = \FreePBX::Endpointmonitor()->showPage();
?>
<div class="container-fluid">
	<div class="display no-border">
		<?php echo $content; ?>
	</div>
</div>
