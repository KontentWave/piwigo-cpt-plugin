<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

/**
 * This class is used to expose maintenance methods to the plugins manager
 * It must extends PluginMaintain and be named "PLUGINID_maintain"
 * where PLUGINID is the directory name of your plugin.
 */
class core_privacy_toggle_maintain extends PluginMaintain
{
  function __construct($plugin_id)
  {
    parent::__construct($plugin_id);
  }

  /**
   * Plugin installation
   *
   * Perform here all needed step for the plugin installation such as create default config,
   * add database tables, add fields to existing tables, create local folders...
   */
  function install($plugin_version, &$errors=array())
  {
    $query = '
CREATE TABLE IF NOT EXISTS '.CPT_OWNER_PROFILE_TABLE.' (
  id int(11) NOT NULL AUTO_INCREMENT,
  root_album_id int(11) NOT NULL,
  owner_user_id int(11) NOT NULL,
  field_key varchar(64) NOT NULL,
  value_text text DEFAULT NULL,
  tag_id int(11) DEFAULT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY root_field (root_album_id, field_key),
  KEY owner_user_id (owner_user_id),
  KEY tag_id (tag_id)
)';

    if (!pwg_query($query)) {
      $errors[] = 'CPT: failed to create owner profile table';
    }
  }

  /**
   * Plugin activation
   *
   * This function is triggered after installation, by manual activation or after a plugin update
   * for this last case you must manage updates tasks of your plugin in this function
   */
  function activate($plugin_version, &$errors=array())
  {
  }

  /**
   * Plugin deactivation
   *
   * Triggered before uninstallation or by manual deactivation
   */
  function deactivate()
  {
  }

  /**
   * Plugin (auto)update
   *
   * This function is called when Piwigo detects that the registered version of
   * the plugin is older than the version exposed in main.inc.php
   * Thus it's called after a plugin update from admin panel or a manual update by FTP
   */
  function update($old_version, $new_version, &$errors=array())
  {
    // I (mistic100) chosed to handle install and update in the same method
    // you are free to do otherwize
    $this->install($new_version, $errors);
  }

  /**
   * Plugin uninstallation
   *
   * Perform here all cleaning tasks when the plugin is removed
   * you should revert all changes made in 'install'
   */
  function uninstall()
  {
    pwg_query('DROP TABLE IF EXISTS '.CPT_OWNER_PROFILE_TABLE);
  }
}