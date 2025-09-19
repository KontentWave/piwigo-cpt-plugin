<?php
defined('CORE_PRIVACY_TOGGLE_PATH') or die('Hacking attempt!');

/**
 * detect current section
 */
function core_privacy_toggle_loc_end_section_init()
{
  global $tokens, $page, $conf;

  if ($tokens[0] == 'core_privacy_toggle')
  {
    $page['section'] = 'core_privacy_toggle';

    // section_title is for breadcrumb, title is for page <title>
    $page['section_title'] = '<a href="'.get_absolute_root_url().'">'.l10n('Home').'</a>'.$conf['level_separator'].'<a href="'.CORE_PRIVACY_TOGGLE_PUBLIC.'">'.l10n('Core Privacy Toggle').'</a>';
    $page['title'] = l10n('Core Privacy Toggle');

    $page['body_id'] = 'theCorePrivacyTogglePage';
    $page['is_external'] = true; // inform Piwigo that you are on a new page
  }
}

/**
 * include public page
 */
function core_privacy_toggle_loc_end_page()
{
  global $page, $template;

  if (isset($page['section']) and $page['section']=='core_privacy_toggle')
  {
    include(CORE_PRIVACY_TOGGLE_PATH . 'include/core_privacy_toggle_page.inc.php');
  }
}

/*
 * button on album and photos pages
 */
function core_privacy_toggle_add_button()
{
  global $template;

  $template->assign('CORE_PRIVACY_TOGGLE_PATH', CORE_PRIVACY_TOGGLE_PATH);
  $template->set_filename('core_privacy_toggle_button', realpath(CORE_PRIVACY_TOGGLE_PATH.'template/my_button.tpl'));
  $button = $template->parse('core_privacy_toggle_button', true);

  if (script_basename()=='index')
  {
    $template->add_index_button($button, BUTTONS_RANK_NEUTRAL);
  }
  else
  {
    $template->add_picture_button($button, BUTTONS_RANK_NEUTRAL);
  }
}

/**
 * add a prefilter on photo page
 */
function core_privacy_toggle_loc_end_picture()
{
  global $template;

  $template->set_prefilter('picture', 'core_privacy_toggle_picture_prefilter');
}

function core_privacy_toggle_picture_prefilter($content)
{
  $search = '{if $display_info.author and isset($INFO_AUTHOR)}';
  $replace = '
<div id="Core Privacy Toggle" class="imageInfo">
  <dt>{\'Core Privacy Toggle\'|@translate}</dt>
  <dd style="color:orange;">{\'Piwigo rocks\'|@translate}</dd>
</div>
';

  return str_replace($search, $replace.$search, $content);
}
