<?php
defined('CORE_PRIVACY_TOGGLE_PATH') or die('Hacking attempt!');

// Configuration screen intentionally reduced; previous options removed.
// Provide a minimal placeholder for upcoming phases.
$template->assign(array(
  'CPT_FUTURE_TEXT' => 'for future use',
));

$template->set_filename('core_privacy_toggle_content', realpath(CORE_PRIVACY_TOGGLE_PATH . 'admin/template/config.tpl'));
