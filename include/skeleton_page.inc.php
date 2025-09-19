<?php
defined('CORE_PRIVACY_TOGGLE_PATH') or die('Hacking attempt!');

global $page, $template, $conf, $user, $tokens, $pwg_loaded_plugins;


# DO SOME STUFF HERE... or not !


$template->assign(array(
  // this is useful when having big blocks of text which must be translated
  // prefer separated HTML files over big lang.php files
  'INTRO_CONTENT' => load_language('intro.html', CORE_PRIVACY_TOGGLE_PATH, array('return'=>true)),
  'CORE_PRIVACY_TOGGLE_PATH' => CORE_PRIVACY_TOGGLE_PATH,
  'CORE_PRIVACY_TOGGLE_ABS_PATH' => realpath(CORE_PRIVACY_TOGGLE_PATH).'/',
  ));

$template->set_filename('core_privacy_toggle_page', realpath(CORE_PRIVACY_TOGGLE_PATH . 'template/core_privacy_toggle_page.tpl'));
$template->assign_var_from_handle('CONTENT', 'core_privacy_toggle_page');
