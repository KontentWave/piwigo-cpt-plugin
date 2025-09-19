<?php
defined('CORE_PRIVACY_TOGGLE_PATH') or die('Hacking attempt!');

// +-----------------------------------------------------------------------+
// | Configuration tab                                                     |
// +-----------------------------------------------------------------------+

// save config
if (isset($_POST['save_config']))
{
  $conf['core_privacy_toggle'] = array(
    'option1' => intval($_POST['option1']),
    'option2' => isset($_POST['option2']),
    'option3' => $_POST['option3'],
    );

  conf_update_param('core_privacy_toggle', $conf['core_privacy_toggle']);
  $page['infos'][] = l10n('Information data registered in database');
}

$select_options = array(
  'one' => l10n('One'),
  'two' => l10n('Two'),
  'three' => l10n('Three'),
  );

// send config to template
$template->assign(array(
  'core_privacy_toggle' => $conf['core_privacy_toggle'],
  'select_options' => $select_options
  ));

// define template file
$template->set_filename('core_privacy_toggle_content', realpath(CORE_PRIVACY_TOGGLE_PATH . 'admin/template/config.tpl'));
