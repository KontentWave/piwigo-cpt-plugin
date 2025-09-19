<?php
defined('CORE_PRIVACY_TOGGLE_PATH') or die('Hacking attempt!');

/*
 * There is two ways to use class methods as event handlers:
 *
 * >  add_event_handler('blockmanager_apply', array('CorePrivacyToggleMenu', 'blockmanager_apply'));
 *      in this case the method 'blockmanager_apply' must be a static method of the class 'CorePrivacyToggleMenu'
 *
 * >  $myObj = new CorePrivacyToggleMenu();
 * >  add_event_handler('blockmanager_apply', array(&$myObj, 'blockmanager_apply'));
 *      in this case the method 'blockmanager_apply' must be a public method of the object '$myObj'
 */

class CorePrivacyToggleMenu
{
  /**
   * add link in existing menu
   */
  static function blockmanager_apply1($menu_ref_arr)
  {
    $menu = &$menu_ref_arr[0];

    if (($block = $menu->get_block('mbMenu')) != null)
    {
      $block->data[] = array(
        'URL' => CORE_PRIVACY_TOGGLE_PUBLIC,
        'TITLE' => l10n('Core Privacy Toggle'),
        'NAME' => l10n('Core Privacy Toggle'),
        );
    }
  }

  /**
   * add a new menu block
   */
  static function blockmanager_register_blocks($menu_ref_arr)
  {
    $menu = &$menu_ref_arr[0];

    if ($menu->get_id() == 'menubar')
    {
      // identifier, title, owner
      $menu->register_block(new RegisteredBlock('mbCorePrivacyToggle', l10n('Core Privacy Toggle'), 'Core Privacy Toggle'));
    }
  }

  /**
   * fill the added menu block
   */
  static function blockmanager_apply2($menu_ref_arr)
  {
    $menu = &$menu_ref_arr[0];

    if (($block = $menu->get_block('mbCorePrivacyToggle')) != null)
    {
      $block->set_title(l10n('Core Privacy Toggle'));

      $block->data['link1'] =
        array(
          'URL' => get_absolute_root_url(),
          'TITLE' => l10n('First link'),
          'NAME' => l10n('Link 1'),
          'REL'=> 'rel="nofollow"',
        );

      $block->data['link2'] =
        array(
          'URL' => CORE_PRIVACY_TOGGLE_PUBLIC,
          'TITLE' => l10n('Second link'),
          'NAME' => l10n('Link 2'),
        );

      $block->template = realpath(CORE_PRIVACY_TOGGLE_PATH . 'template/menubar_core_privacy_toggle.tpl');
    }
  }
}
