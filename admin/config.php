<?php
defined('CORE_PRIVACY_TOGGLE_PATH') or die('Hacking attempt!');

// Admin configuration placeholder for Core Privacy Toggle
// Phase 1: No configuration needed - the plugin works through User Control Panel integration
$template->assign(array(
  'CPT_STATUS' => 'Core Privacy Toggle (CPT) is active and working through the User Control Panel.',
  'CPT_PHASE_INFO' => 'Phase 1: UCP Album Management is fully implemented. No admin configuration required.',
));

$template->set_filename('core_privacy_toggle_content', realpath(CORE_PRIVACY_TOGGLE_PATH . 'admin/template/config.tpl'));
