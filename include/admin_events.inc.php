<?php
defined('CORE_PRIVACY_TOGGLE_PATH') or die('Hacking attempt!');

/**
 * Admin plugin menu link - minimal implementation for Core Privacy Toggle
 */
function core_privacy_toggle_admin_plugin_menu_links($menu)
{
  $menu[] = array(
    'NAME' => l10n('Core Privacy Toggle'),
    'URL' => CORE_PRIVACY_TOGGLE_ADMIN,
    );

  return $menu;
}

// Skeleton admin events removed - CPT Phase 1 works through User Control Panel only
// Removed: photo tab, batch manager prefilters, batch manager actions
