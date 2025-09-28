<?php
/**
 * This is the main administration page, if you have only one admin page you can put
 * directly its code here or using the tabsheet system like bellow
 */

defined('CORE_PRIVACY_TOGGLE_PATH') or die('Hacking attempt!');

global $template, $page, $conf;

// Phase 1 cleanup: remove legacy Welcome tab and always show configuration placeholder.
// We keep structure minimal; future phases can reintroduce tabs if needed.
$page['tab'] = 'config';

// directly include the config page (no tabsheet)
include(CORE_PRIVACY_TOGGLE_PATH . 'admin/config.php');

// template vars
$template->assign(array(
  'CORE_PRIVACY_TOGGLE_PATH'=> CORE_PRIVACY_TOGGLE_PATH, // used for images, scripts, ... access
  'CORE_PRIVACY_TOGGLE_ABS_PATH'=> realpath(CORE_PRIVACY_TOGGLE_PATH), // used for template inclusion (Smarty needs a real path)
  'CORE_PRIVACY_TOGGLE_ADMIN' => CORE_PRIVACY_TOGGLE_ADMIN,
  ));

// send page content
$template->assign_var_from_handle('ADMIN_CONTENT', 'core_privacy_toggle_content');
