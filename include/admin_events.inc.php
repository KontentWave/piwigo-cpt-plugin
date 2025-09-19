<?php
defined('CORE_PRIVACY_TOGGLE_PATH') or die('Hacking attempt!');

/**
 * admin plugins menu link
 */
function core_privacy_toggle_admin_plugin_menu_links($menu)
{
  $menu[] = array(
    'NAME' => l10n('Core Privacy Toggle'),
    'URL' => CORE_PRIVACY_TOGGLE_ADMIN,
    );

  return $menu;
}

/**
 * add a tab on photo properties page
 */
function core_privacy_toggle_tabsheet_before_select($sheets, $id)
{
  if ($id == 'photo')
  {
    $sheets['core_privacy_toggle'] = array(
      'caption' => l10n('Core Privacy Toggle'),
      'url' => CORE_PRIVACY_TOGGLE_ADMIN.'-photo&amp;image_id='.$_GET['image_id'],
      );
  }

  return $sheets;
}

/**
 * add a prefilter to the Batch Downloader
 */
function core_privacy_toggle_add_batch_manager_prefilters($prefilters)
{
  $prefilters[] = array(
    'ID' => 'core_privacy_toggle',
    'NAME' => l10n('Core Privacy Toggle'),
    );

  return $prefilters;
}

/**
 * perform added prefilter
 */
function core_privacy_toggle_perform_batch_manager_prefilters($filter_sets, $prefilter)
{
  if ($prefilter == 'core_privacy_toggle')
  {
    $query = '
SELECT id
  FROM '.IMAGES_TABLE.'
  ORDER BY RAND()
  LIMIT 20
;';
    $filter_sets[] = query2array($query, null, 'id');
  }

  return $filter_sets;
}

/**
 * add an action to the Batch Manager
 */
function core_privacy_toggle_loc_end_element_set_global()
{
  global $template;

  /*
    CONTENT is optional
    for big contents it is advised to use a template file

    $template->set_filename('core_privacy_toggle_batchmanager_action', realpath(CORE_PRIVACY_TOGGLE_PATH.'template/batchmanager_action.tpl'));
    $content = $template->parse('core_privacy_toggle_batchmanager_action', true);
   */
  $template->append('element_set_global_plugins_actions', array(
    'ID' => 'core_privacy_toggle',
    'NAME' => l10n('Core Privacy Toggle'),
    'CONTENT' => '<label><input type="checkbox" name="check_core_privacy_toggle"> '.l10n('Check me!').'</label>',
    ));
}

/**
 * perform added action
 */
function core_privacy_toggle_element_set_global_action($action, $collection)
{
  global $page;

  if ($action == 'core_privacy_toggle')
  {
    if (empty($_POST['check_core_privacy_toggle']))
    {
      $page['warnings'][] = l10n('Nothing appened, but you didn\'t check the box!');
    }
    else
    {
      $page['infos'][] = l10n('Nothing appened, but you checked the box!');
    }
  }
}
