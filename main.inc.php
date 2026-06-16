<?php
/*
Plugin Name: Core Privacy Toggle
Version: 1.0.0
Description: Toggle core privacy options in Piwigo.
Author: Your Name
Author URI: https://cores.sk
*/

/**
 * This is the main file of the plugin, called by Piwigo in "include/common.inc.php" line 137.
 * At this point of the code, Piwigo is not completely initialized, so nothing should be done directly
 * except define constants and event handlers (see http://piwigo.org/doc/doku.php?id=dev:plugins)
 */

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');


if (basename(dirname(__FILE__)) != 'core_privacy_toggle')
{
  add_event_handler('init', 'core_privacy_toggle_error');
  function core_privacy_toggle_error()
  {
    global $page;
    $page['errors'][] = 'Core Privacy Toggle folder name is incorrect, uninstall the plugin and rename it to "core_privacy_toggle"';
  }
  return;
}


// +-----------------------------------------------------------------------+
// | Define plugin constants                                               |
// +-----------------------------------------------------------------------+
global $prefixeTable;

define('CORE_PRIVACY_TOGGLE_ID',      basename(dirname(__FILE__)));
define('CORE_PRIVACY_TOGGLE_PATH' ,   PHPWG_PLUGINS_PATH . CORE_PRIVACY_TOGGLE_ID . '/');
define('CORE_PRIVACY_TOGGLE_ADMIN',   get_root_url() . 'admin.php?page=plugin-' . CORE_PRIVACY_TOGGLE_ID);
// Debug flag (set to true only during development)
if (!defined('CPT_DEBUG')) { define('CPT_DEBUG', false); }

// Ensure core helper functions loaded (needed for early profile hook)
require_once CORE_PRIVACY_TOGGLE_PATH . 'include/functions.inc.php';



// +-----------------------------------------------------------------------+
// | Add event handlers                                                    |
// +-----------------------------------------------------------------------+
// init the plugin
add_event_handler('init', 'core_privacy_toggle_init');

// user profile enhancement (UCP) - album management tabs (progressive enhancement)
add_event_handler('loc_begin_profile', 'cpt_setup_ucp_tabs');
add_event_handler('loc_begin_index', 'cpt_handle_album_page_toggle');
add_event_handler('loc_end_index', 'cpt_attach_album_page_toggle');
add_event_handler('ws_add_methods', 'cpt_add_ws_methods');
// Safety net: if early POST handling somehow missed (theme workflow), run a late check

/*
 * this is the common way to define event functions: create a new function for each event you want to handle
 */
if (defined('IN_ADMIN'))
{
    // No admin demo hooks retained (privacy toggle UCP is front-end only for Phase 1).
}
  // Removed: public page, menu blocks, demo webservice, batch manager examples.


/**
 * plugin initialization
 *   - check for upgrades
 *   - unserialize configuration
 *   - load language
 */
function core_privacy_toggle_init()
{
  global $conf;

  // load plugin language file
  load_language('plugin.lang', CORE_PRIVACY_TOGGLE_PATH);

  // prepare plugin configuration
  $conf['core_privacy_toggle'] = isset($conf['core_privacy_toggle'])
    ? safe_unserialize($conf['core_privacy_toggle'])
    : array();

  // One-shot permission visibility cache bust flag: if set, invalidate then remove
  if (!empty($_SESSION['cpt_permissions_changed'])) {
    if (function_exists('invalidate_user_cache')) { invalidate_user_cache(); }
    unset($_SESSION['cpt_permissions_changed']);
  }
}
