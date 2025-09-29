<?php
/**
 * Core Privacy Toggle - Admin configuration page
 * Phase 1: Minimal admin interface - main functionality works through User Control Panel
 */

defined('CORE_PRIVACY_TOGGLE_PATH') or die('Hacking attempt!');

global $template, $page, $conf;

// Simple config page - no tabs needed for Phase 1
include(CORE_PRIVACY_TOGGLE_PATH . 'admin/config.php');

// Template vars
$template->assign(array(
  'CORE_PRIVACY_TOGGLE_PATH'=> CORE_PRIVACY_TOGGLE_PATH,
  'CORE_PRIVACY_TOGGLE_ABS_PATH'=> realpath(CORE_PRIVACY_TOGGLE_PATH),
  'CORE_PRIVACY_TOGGLE_ADMIN' => CORE_PRIVACY_TOGGLE_ADMIN,
  ));

// Send page content
$template->assign_var_from_handle('ADMIN_CONTENT', 'core_privacy_toggle_content');
