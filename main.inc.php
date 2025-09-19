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
define('CORE_PRIVACY_TOGGLE_TABLE',   $prefixeTable . 'core_privacy_toggle');
define('CORE_PRIVACY_TOGGLE_ADMIN',   get_root_url() . 'admin.php?page=plugin-' . CORE_PRIVACY_TOGGLE_ID);
define('CORE_PRIVACY_TOGGLE_PUBLIC',  get_absolute_root_url() . make_index_url(array('section' => 'core_privacy_toggle')) . '/');
define('CORE_PRIVACY_TOGGLE_DIR',     PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'core_privacy_toggle/');



// +-----------------------------------------------------------------------+
// | Add event handlers                                                    |
// +-----------------------------------------------------------------------+
// init the plugin
add_event_handler('init', 'core_privacy_toggle_init');

/*
 * this is the common way to define event functions: create a new function for each event you want to handle
 */
if (defined('IN_ADMIN'))
{
  // file containing all admin handlers functions
  $admin_file = CORE_PRIVACY_TOGGLE_PATH . 'include/admin_events.inc.php';

  // admin plugins menu link
  add_event_handler('get_admin_plugin_menu_links', 'core_privacy_toggle_admin_plugin_menu_links',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);

  // new tab on photo page
  add_event_handler('tabsheet_before_select', 'core_privacy_toggle_tabsheet_before_select',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);

  // new prefiler in Batch Manager
  add_event_handler('get_batch_manager_prefilters', 'core_privacy_toggle_add_batch_manager_prefilters',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);
  add_event_handler('perform_batch_manager_prefilters', 'core_privacy_toggle_perform_batch_manager_prefilters',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);

  // new action in Batch Manager
  add_event_handler('loc_end_element_set_global', 'core_privacy_toggle_loc_end_element_set_global',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);
  add_event_handler('element_set_global_action', 'core_privacy_toggle_element_set_global_action',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);
}
else
{
  // file containing all public handlers functions
  $public_file = CORE_PRIVACY_TOGGLE_PATH . 'include/public_events.inc.php';

  // add a public section
  add_event_handler('loc_end_section_init', 'core_privacy_toggle_loc_end_section_init',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $public_file);
  add_event_handler('loc_end_index', 'core_privacy_toggle_loc_end_page',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $public_file);

  // add button on album and photos pages
  add_event_handler('loc_end_index', 'core_privacy_toggle_add_button',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $public_file);
  add_event_handler('loc_end_picture', 'core_privacy_toggle_add_button',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $public_file);

  // prefilter on photo page
  add_event_handler('loc_end_picture', 'core_privacy_toggle_loc_end_picture',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $public_file);
}

// file containing API function
$ws_file = CORE_PRIVACY_TOGGLE_PATH . 'include/ws_functions.inc.php';

// add API function
add_event_handler('ws_add_methods', 'core_privacy_toggle_ws_add_methods',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $ws_file);


/*
 * event functions can also be wrapped in a class
 */

// file containing the class for menu handlers functions
$menu_file = CORE_PRIVACY_TOGGLE_PATH . 'include/menu_events.class.php';

// add item to existing menu (EVENT_HANDLER_PRIORITY_NEUTRAL+10 is for compatibility with Advanced Menu Manager plugin)
add_event_handler('blockmanager_apply', array('CorePrivacyToggleMenu', 'blockmanager_apply1'),
  EVENT_HANDLER_PRIORITY_NEUTRAL+10, $menu_file);

// add a new menu block (the declaration must be done every time, in order to be able to manage the menu block in "Menus" screen and Advanced Menu Manager)
add_event_handler('blockmanager_register_blocks', array('CorePrivacyToggleMenu', 'blockmanager_register_blocks'),
  EVENT_HANDLER_PRIORITY_NEUTRAL, $menu_file);
add_event_handler('blockmanager_apply', array('CorePrivacyToggleMenu', 'blockmanager_apply2'),
  EVENT_HANDLER_PRIORITY_NEUTRAL, $menu_file);

// NOTE: blockmanager_apply1() and blockmanager_apply2() can (must) be merged


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
  $conf['core_privacy_toggle'] = safe_unserialize($conf['core_privacy_toggle']);
}
