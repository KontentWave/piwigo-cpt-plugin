<?php
defined('CORE_PRIVACY_TOGGLE_PATH') or die('Hacking attempt!');

// +-----------------------------------------------------------------------+
// | Home tab                                                              |
// +-----------------------------------------------------------------------+

// send variables to template
$template->assign(array(
  'core_privacy_toggle' => $conf['core_privacy_toggle'],
  'INTRO_CONTENT' => load_language('intro.html', CORE_PRIVACY_TOGGLE_PATH, array('return'=>true)),
  ));

// define template file
$template->set_filename('core_privacy_toggle_content', realpath(CORE_PRIVACY_TOGGLE_PATH . 'admin/template/home.tpl'));
