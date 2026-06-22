<?php
defined('CORE_PRIVACY_TOGGLE_PATH') or die('Hacking attempt!');

require_once __DIR__.'/profile_fields.inc.php';

if (!defined('CORE_PRIVACY_TOGGLE_PUBLIC')) {
	define('CORE_PRIVACY_TOGGLE_PUBLIC', get_root_url().'plugins/'.basename(dirname(__DIR__)).'/');
}

/**
 * Set up User Control Panel album management (progressive enhancement).
 * Early exit if user owns no albums. Exposes template partial to JS and processes POST.
 */
function cpt_setup_ucp_tabs(): void
{
	global $user, $template;
	$limited_mode_notice = null;

	// must have authenticated user context
	if (empty($user['id'])) {
		return;
	}
	$user_id = (int)$user['id'];

	// Process POST early within the profile lifecycle (now canonical location)
	if (isset($_POST['cpt_album_marker']) && !empty($_POST['cpt_album']) && is_array($_POST['cpt_album'])) {
		if (cpt_handle_album_form($_POST['cpt_album'], $user_id)) {
			global $page;
			$page['infos'][] = l10n('Your changes have been saved.');
		}
	}


	$owned_count = cpt_count_albums_owned_by($user_id);
	if ($owned_count === 0) {
		// If categories.user_id is missing, we can operate in a limited mode where
		// the user may edit albums that exclusively contain their photos.
		if (cpt_has_album_ownership_column() === false) {
			$owned_count = cpt_count_albums_contributed_exclusive($user_id);
			if ($owned_count === 0) {
				// Admin hint about missing ownership mapping
				if (pwg_get_session_var('is_admin')) {
					global $page; $page['infos'][] = l10n('CPT: Ownership column not detected; falling back to albums exclusively containing user photos.');
				}
				return; // still nothing to enhance
			}
		} else {
			return; // nothing to enhance; keeps baseline profile intact
		}
	}


	$albums = cpt_fetch_albums_owned_by($user_id);
	if (empty($albums) && cpt_has_album_ownership_column() === false) {
		// Try fallback fetch (exclusive contribution albums only)
		$albums = cpt_fetch_albums_contributed_exclusive($user_id);
		if (!empty($albums) && !pwg_get_session_var('is_admin')) {
			// Inform user about limited mode once per request
			$limited_mode_notice = l10n('CPT: Limited mode enabled — only albums exclusively containing your photos are listed.');
			global $page; $page['infos'][] = $limited_mode_notice;
		}
	}
	if (empty($albums)) { // defensive: if fetch fails, skip enhancement
		return;
	}

		$template->assign('UCP_ALBUMS', $albums);
		$template->assign('CPT_SHAREABLE_USERS', cpt_get_shareable_user_options($user_id));
		$template->assign('UCP_OWNER_PROFILE', cpt_get_owner_profile_editor_data($user_id));
		$template->assign('CPT_LIMITED_MODE_NOTICE', $limited_mode_notice);

		// Render partial (will be injected by JS; contains only inner controls)
		// Use set_filename + parse instead of fetch (fetch not available in this env)
		$template->set_filename('cpt_ucp_album_manager', realpath(CORE_PRIVACY_TOGGLE_PATH.'template/ucp_album_manager.tpl'));
		$partial = $template->parse('cpt_ucp_album_manager', true);
		cpt_attach_album_manager_to_profile();
		cpt_attach_owner_profile_to_profile();
		cpt_inject_album_manager_assets($partial);
}

/**
 * Register album-page assets early enough for the page header/footer loaders.
 */
function cpt_prepare_album_page_toggle(): void
{
	global $template, $user;

	$category = cpt_get_current_album_page_category();
	if ($category === null) {
		return;
	}

	$album_id = (int) $category['id'];
	$user_id = (int) ($user['id'] ?? 0);
	$owns_album = $user_id > 0 && cpt_album_is_owned_by($album_id, $user_id);
	$has_public_profile = cpt_get_owner_profile_public_data_for_album($album_id) !== null;
	if (!$owns_album && !$has_public_profile) {
		return;
	}

	$css_path = CORE_PRIVACY_TOGGLE_PATH.'template/album_page_toggle.css';
	if (file_exists($css_path) && isset($template->cssLoader)) {
		$template->func_combine_css(array(
			'id' => 'cpt-album-page-toggle',
			'path' => 'plugins/'.CORE_PRIVACY_TOGGLE_ID.'/template/album_page_toggle.css',
			'version' => filemtime($css_path),
			'order' => 10,
		));
	}

	$script_path = CORE_PRIVACY_TOGGLE_PATH.'js/album_page_toggle.js';
	if (file_exists($script_path) && isset($template->scriptLoader)) {
		$template->func_combine_script(array(
			'id' => 'cpt-album-page-toggle',
			'path' => 'plugins/'.CORE_PRIVACY_TOGGLE_ID.'/js/album_page_toggle.js',
			'load' => 'footer',
			'version' => filemtime($script_path),
		));
	}
}

/**
 * Process the album-page quick toggle form before index.php renders.
 */
function cpt_handle_album_page_toggle(): void
{
	global $page, $user;

	if (empty($user['id']) || empty($_POST['cpt_album_quick_toggle'])) {
		return;
	}

	$category = cpt_get_current_album_page_category();
	if ($category === null) {
		return;
	}

	if (get_pwg_token() !== (string) ($_POST['pwg_token'] ?? '')) {
		$page['errors'][] = l10n('Invalid security token');
		return;
	}

	$album_id = (int) $category['id'];
	$user_id = (int) $user['id'];
	if (!cpt_album_is_owned_by($album_id, $user_id)) {
		return;
	}

	$target_status = (string) ($_POST['cpt_album_status'] ?? '');
	if (!in_array($target_status, array('public', 'private'), true)) {
		return;
	}

	$current_status = cpt_get_album_status($album_id) ?? (string) ($category['status'] ?? 'public');
	if ($current_status !== $target_status) {
		cpt_update_album($album_id, array('status' => $target_status), false, array(), $user_id);
		$_SESSION['page_infos'][] = l10n('Album privacy updated.');
	}

	redirect(duplicate_index_url());
}

/**
 * Render a compact privacy toggle directly on owned album pages.
 */
function cpt_attach_album_page_toggle(): void
{
	global $page, $template, $user;

	if (empty($user['id'])) {
		return;
	}

	$category = cpt_get_current_album_page_category();
	if ($category === null) {
		return;
	}

	$album_id = (int) $category['id'];
	$user_id = (int) $user['id'];
	if (!cpt_album_is_owned_by($album_id, $user_id)) {
		return;
	}

	$is_private = (string) ($category['status'] ?? 'public') === 'private';
	$template->assign('CPT_ALBUM_TOGGLE_ACTION', duplicate_index_url());
	$template->assign('CPT_ALBUM_IS_PRIVATE', $is_private);
	$template->assign('CPT_ALBUM_TOGGLE_TARGET_STATUS', $is_private ? 'public' : 'private');
	$template->assign(
		'CPT_ALBUM_TOGGLE_STATUS_TEXT',
		$is_private ? l10n('This album is currently private.') : l10n('This album is currently public.')
	);
	$template->assign('PWG_TOKEN', get_pwg_token());

	$template->set_filename('cpt_album_page_toggle', realpath(CORE_PRIVACY_TOGGLE_PATH.'template/album_page_toggle.tpl'));
	$html = $template->parse('cpt_album_page_toggle', true);
	cpt_append_index_content_begin($html);
	cpt_inject_album_page_assets($html);
}

function cpt_attach_owner_profile_to_album_page(): void
{
	global $template;

	$category = cpt_get_current_album_page_category();
	if ($category === null) {
		return;
	}

	$profile = cpt_get_owner_profile_public_data_for_album((int) $category['id']);
	if ($profile === null) {
		return;
	}

	$template->assign('CPT_OWNER_PROFILE_ROWS', $profile['rows']);
	$template->set_filename('cpt_owner_profile_table', realpath(CORE_PRIVACY_TOGGLE_PATH.'template/owner_profile_table.tpl'));
	$html = $template->parse('cpt_owner_profile_table', true);
	$template->assign('CPT_OWNER_PROFILE_TABLE', $html);

	if (!cpt_theme_uses_album_page_js_profile_placement()) {
		cpt_append_index_content_begin($html);
	}
	cpt_inject_album_page_assets($html);
}

/**
 * Returns the current category context when the public page is a single album view.
 */
function cpt_get_current_album_page_category(): ?array
{
	global $page;

	if (($page['section'] ?? null) !== 'categories') {
		return null;
	}

	if (empty($page['category']) || !is_array($page['category']) || empty($page['category']['id'])) {
		return null;
	}

	if (!empty($page['combined_categories'])) {
		return null;
	}

	return $page['category'];
}

/**
 * Append HTML to the standard public-page plugin slot when available.
 */
function cpt_append_index_content_begin(string $html): void
{
	global $template;

	$existing = method_exists($template, 'get_template_vars')
		? $template->get_template_vars('PLUGIN_INDEX_CONTENT_BEGIN')
		: null;

	$template->assign(
		'PLUGIN_INDEX_CONTENT_BEGIN',
		(is_string($existing) ? $existing : '').$html
	);
}

function cpt_theme_uses_album_page_js_profile_placement(): bool
{
	if (!function_exists('get_themeconf')) {
		return false;
	}

	return get_themeconf('id') === 'bootstrap_darkroom';
}

/**
 * Expose the album-page shortcut for themes that skip PLUGIN_INDEX_CONTENT_BEGIN.
 */
function cpt_inject_album_page_assets(string $html_partial): void
{
	$json = json_encode($html_partial, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT);
	$inline = 'window.CPT_ALBUM_PAGE_HTML = (typeof window.CPT_ALBUM_PAGE_HTML === "string" ? window.CPT_ALBUM_PAGE_HTML : "") + '.$json.';window.CPT_ALBUM_PAGE_ASSETS_READY=1;';

	global $template;
	if (isset($template->scriptLoader)) {
		$template->scriptLoader->add_inline($inline, array('cpt-album-page-toggle'));
		return;
	}

	if (method_exists($template, 'append')) {
		$template->append('footer_msgs', '<script>'.$inline.'</script>');
	}
}

/**
 * Attach the album manager as a native Piwigo profile plugin block.
 */
function cpt_attach_album_manager_to_profile(): void
{
	cpt_attach_profile_block('template/ucp_album_manager.tpl', l10n('My Galleries'));
}

function cpt_attach_owner_profile_to_profile(): void
{
	if (empty(cpt_get_owner_profile_editor_data((int) ($GLOBALS['user']['id'] ?? 0)))) {
		return;
	}

	cpt_attach_profile_block('template/ucp_owner_profile.tpl', l10n('My Profile'));
}

function cpt_attach_profile_block(string $relative_template_path, string $block_name): void
{
	global $template;

	$template_path = realpath(CORE_PRIVACY_TOGGLE_PATH.$relative_template_path);
	if ($template_path === false) {
		return;
	}

	$existing_blocks = method_exists($template, 'get_template_vars') ? $template->get_template_vars('PLUGINS_PROFILE') : null;
	if (is_array($existing_blocks)) {
		foreach ($existing_blocks as $block) {
			if (is_array($block) && ($block['template'] ?? null) === $template_path) {
				return;
			}
		}
	}

	$block = array(
		'name' => $block_name,
		'desc' => '',
		'standard_show_save' => false,
		'template' => $template_path,
	);

	if (method_exists($template, 'append')) {
		$template->append('PLUGINS_PROFILE', $block);
		return;
	}

	$blocks = is_array($existing_blocks) ? $existing_blocks : array();
	$blocks[] = $block;
	$template->assign('PLUGINS_PROFILE', $blocks);
}

/**
 * Count albums owned by user. Ownership prefers current Community plugin's
 * community_user column, with legacy user_id support retained.
 */
function cpt_count_albums_owned_by(int $user_id): int
{
	return count(cpt_fetch_albums_owned_by($user_id));
}

function cpt_build_album_editor_row(array $row, int $owner_user_id): array
{
	$album_id = (int) $row['id'];
	$shared_users = cpt_get_album_shared_user_ids($album_id, $owner_user_id);
	$representative = cpt_get_album_representative_details($album_id);

	return [
		'id' => $album_id,
		'name' => $row['name'],
		'comment' => $row['comment'],
		'status' => $row['status'],
		'visibility' => cpt_get_album_visibility_mode($album_id, $owner_user_id),
		'shared_users' => $shared_users,
		'shared_user_lookup' => array_fill_keys($shared_users, true),
		'representative_picture_id' => $representative['id'],
		'representative_label' => $representative['label'],
		'representative_src' => $representative['src'],
	];
}

/**
 * Fetch basic album metadata for owner editing.
 */
function cpt_fetch_albums_owned_by(int $user_id): array
{
	$ownership_column = cpt_get_album_ownership_column();
	if ($ownership_column === null) { return []; }
	$albums = [];
	$album_ids = [];
	$query = 'SELECT id, name, comment, status FROM '.CATEGORIES_TABLE.' ORDER BY id ASC';
	$result = pwg_query($query);
	if (!$result) { return $albums; }
	while ($row = pwg_db_fetch_assoc($result)) {
		$album_id = (int)$row['id'];
		$effective_owner_id = cpt_get_album_effective_owner_id($album_id);
		if ($effective_owner_id !== (int) $user_id) {
			if ($effective_owner_id !== null || !cpt_album_has_exclusive_contributor($album_id, $user_id)) {
				continue;
			}
			$effective_owner_id = (int) $user_id;
		}

		if (isset($album_ids[$album_id])) {
			continue;
		}

		$albums[] = cpt_build_album_editor_row($row, $effective_owner_id);
		$album_ids[$album_id] = true;
	}
	usort($albums, 'cpt_compare_album_tree_order');

	return $albums;
}

function cpt_compare_album_tree_order(array $left, array $right): int
{
	$left_key = cpt_get_album_tree_sort_key((int) $left['id']);
	$right_key = cpt_get_album_tree_sort_key((int) $right['id']);

	if ($left_key === $right_key) {
		return ((int) $left['id']) <=> ((int) $right['id']);
	}

	return strcmp($left_key, $right_key);
}

function cpt_get_album_tree_sort_key(int $album_id): string
{
	static $cache = [];

	if (isset($cache[$album_id])) {
		return $cache[$album_id];
	}

	$path = array_merge(cpt_get_album_ancestor_ids($album_id), array($album_id));
	$cache[$album_id] = implode('.', array_map(
		static function ($path_id): string {
			return str_pad((string) (int) $path_id, 10, '0', STR_PAD_LEFT);
		},
		$path
	));

	return $cache[$album_id];
}
function cpt_count_albums_contributed_exclusive(int $user_id): int
{
	$sql = 'SELECT COUNT(*) AS cnt FROM (
		SELECT ic.category_id
		FROM '.IMAGE_CATEGORY_TABLE.' ic
		INNER JOIN '.IMAGES_TABLE.' i ON i.id = ic.image_id
		GROUP BY ic.category_id
		HAVING COUNT(DISTINCT i.added_by) = 1 AND MIN(i.added_by) = '.(int)$user_id.'
	) t';
	$res = pwg_query($sql);
	if (!$res) { return 0; }
	$row = pwg_db_fetch_assoc($res);
	return (int)($row['cnt'] ?? 0);
}

/**
 * Fallback: fetch albums that exclusively contain photos added by this user.
 */
function cpt_fetch_albums_contributed_exclusive(int $user_id): array
{
	$albums = [];
	$sql = 'SELECT c.id, c.name, c.comment, c.status
		FROM '.CATEGORIES_TABLE.' c
		WHERE c.id IN (
			SELECT ic.category_id
			FROM '.IMAGE_CATEGORY_TABLE.' ic
			INNER JOIN '.IMAGES_TABLE.' i ON i.id = ic.image_id
			GROUP BY ic.category_id
			HAVING COUNT(DISTINCT i.added_by) = 1 AND MIN(i.added_by) = '.(int)$user_id.'
		)
		ORDER BY c.id DESC';
	$res = pwg_query($sql);
	if (!$res) { return $albums; }
	while ($row = pwg_db_fetch_assoc($res)) {
		$albums[] = cpt_build_album_editor_row($row, (int) $user_id);
	}
	return $albums;
}

function cpt_get_album_representative_picture_id(int $album_id): ?int
{
	$result = pwg_query('SELECT representative_picture_id FROM '.CATEGORIES_TABLE.' WHERE id='.(int) $album_id.' LIMIT 1');
	if (!$result) {
		return null;
	}

	$row = pwg_db_fetch_assoc($result);
	if (!$row || !isset($row['representative_picture_id']) || $row['representative_picture_id'] === null || $row['representative_picture_id'] === '') {
		return null;
	}

	return (int) $row['representative_picture_id'];
}

function cpt_get_album_representative_details(int $album_id): array
{
	$representative_id = cpt_get_album_representative_picture_id($album_id);
	if ($representative_id === null) {
		return [
			'id' => null,
			'label' => l10n('No cover image selected.'),
			'src' => '',
		];
	}

	$result = pwg_query('SELECT id, name, file, path, representative_ext FROM '.IMAGES_TABLE.' WHERE id='.(int) $representative_id.' LIMIT 1');
	if (!$result) {
		return [
			'id' => $representative_id,
			'label' => l10n('Current cover image is unavailable.'),
			'src' => '',
		];
	}

	$row = pwg_db_fetch_assoc($result);
	if (!$row) {
		return [
			'id' => $representative_id,
			'label' => l10n('Current cover image is unavailable.'),
			'src' => '',
		];
	}

	return [
		'id' => (int) $row['id'],
		'label' => cpt_get_album_image_label($row),
		'src' => cpt_get_album_image_square_src($row),
	];
}

function cpt_fetch_album_representative_options(int $album_id, int $user_id): array
{
	if (!cpt_album_is_owned_by($album_id, $user_id)) {
		return [];
	}

	$options = [];
	$result = pwg_query('SELECT i.id, i.name, i.file, i.path, i.representative_ext
		FROM '.IMAGES_TABLE.' i
		INNER JOIN '.IMAGE_CATEGORY_TABLE.' ic ON ic.image_id = i.id
		WHERE ic.category_id = '.(int) $album_id.'
		ORDER BY i.id ASC');
	if (!$result) {
		return $options;
	}

	while ($row = pwg_db_fetch_assoc($result)) {
		$options[] = [
			'id' => (int) $row['id'],
			'label' => cpt_get_album_image_label($row),
			'src' => cpt_get_album_image_square_src($row),
		];
	}

	return $options;
}

function cpt_get_album_image_label(array $image): string
{
	$name = trim((string) ($image['name'] ?? ''));
	$file = trim((string) ($image['file'] ?? ''));
	if ($name !== '') {
		return $file !== '' ? $name.' ('.$file.')' : $name;
	}
	if ($file !== '') {
		return $file;
	}
	return '#'.(int) ($image['id'] ?? 0);
}

function cpt_get_album_image_square_src(array $image): string
{
	if (!class_exists('DerivativeImage') || !class_exists('ImageStdParams') || !defined('IMG_SQUARE')) {
		return '';
	}

	try {
		return (string) \DerivativeImage::url(\ImageStdParams::get_by_type(IMG_SQUARE), $image);
	} catch (\Throwable $throwable) {
		return '';
	}
}
function cpt_update_album_representative(int $album_id, ?int $image_id, int $user_id): bool
{
	if (!cpt_album_is_owned_by($album_id, $user_id)) {
		return false;
	}

	if ($image_id !== null) {
		$membership = pwg_query('SELECT COUNT(*) AS cnt FROM '.IMAGE_CATEGORY_TABLE.' WHERE category_id='.(int) $album_id.' AND image_id='.(int) $image_id);
		if (!$membership) {
			return false;
		}

		$row = pwg_db_fetch_assoc($membership);
		if ((int) ($row['cnt'] ?? 0) === 0) {
			return false;
		}
	}

	$value_sql = $image_id === null ? 'NULL' : (string) (int) $image_id;
	$result = pwg_query('UPDATE '.CATEGORIES_TABLE.' SET representative_picture_id='.$value_sql.' WHERE id='.(int) $album_id.' LIMIT 1');
	if (!$result) {
		return false;
	}

	if (defined('USER_CACHE_CATEGORIES_TABLE')) {
		pwg_query('UPDATE '.USER_CACHE_CATEGORIES_TABLE.' SET user_representative_picture_id = NULL WHERE cat_id='.(int) $album_id);
	}

	return true;
}

function cpt_get_shareable_user_options(int $owner_user_id): array
{
	global $conf;

	$guest_id = isset($conf['guest_id']) ? (int) $conf['guest_id'] : 0;
	$options = [];
	$query = 'SELECT '.USERS_TABLE.'.'.$conf['user_fields']['id'].' AS user_id, '.USERS_TABLE.'.'.$conf['user_fields']['username'].' AS username FROM '.USERS_TABLE.' WHERE '.USERS_TABLE.'.'.$conf['user_fields']['id'].' NOT IN (1,'.(int) $owner_user_id;
	if ($guest_id > 0) {
		$query .= ','.$guest_id;
	}
	$query .= ') ORDER BY username';
	$result = pwg_query($query);
	if (!$result) {
		return $options;
	}
	while ($row = pwg_db_fetch_assoc($result)) {
		$options[(int) $row['user_id']] = (string) $row['username'];
	}
	return $options;
}

function cpt_get_album_shared_user_ids(int $album_id, int $owner_user_id): array
{
	$shared_user_ids = [];
	$result = pwg_query('SELECT user_id FROM '.USER_ACCESS_TABLE.' WHERE cat_id='.(int) $album_id);
	if (!$result) {
		return $shared_user_ids;
	}
	while ($row = pwg_db_fetch_assoc($result)) {
		$user_id = (int) ($row['user_id'] ?? 0);
		if ($user_id <= 0 || $user_id === 1 || $user_id === $owner_user_id) {
			continue;
		}
		$shared_user_ids[] = $user_id;
	}
	sort($shared_user_ids);
	return $shared_user_ids;
}

function cpt_get_album_visibility_mode(int $album_id, int $owner_user_id): string
{
	$status = cpt_get_album_status($album_id) ?? 'public';
	if ($status !== 'private') {
		return 'public';
	}

	$shared_user_ids = cpt_get_album_shared_user_ids($album_id, $owner_user_id);
	return empty($shared_user_ids) ? 'private' : 'shared';
}

/**
 * Inject JS assets: expose HTML partial + enqueue enhancement script (to be created separately).
 */
function cpt_inject_album_manager_assets(string $html_partial): void
{
	// Safely JSON encode for embedding (will be parsed by JS)
	$json = json_encode($html_partial, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT);
	$legend = json_encode(l10n('My Galleries'), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT);
	$save_success = json_encode(l10n('Your changes have been saved.'), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT);
	$save_error = json_encode(l10n('An error has occurred.'), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT);
	$inline = 'window.CPT_ALBUM_HTML = '.$json.';window.CPT_I18N_MY_GALLERIES='.$legend.';window.CPT_I18N_SAVE_SUCCESS='.$save_success.';window.CPT_I18N_SAVE_ERROR='.$save_error.';window.CPT_ASSETS_READY=1;';

	$css_path = CORE_PRIVACY_TOGGLE_PATH.'template/ucp_album_manager.css';
	$css_url = CORE_PRIVACY_TOGGLE_PUBLIC.'template/ucp_album_manager.css';
	$css_ver = '';
	if (file_exists($css_path)) { $css_ver = '?v='.filemtime($css_path); }
	$path = CORE_PRIVACY_TOGGLE_PATH.'js/ucp_tabs.js';
	$url = CORE_PRIVACY_TOGGLE_PUBLIC.'js/ucp_tabs.js';
	$ver = '';
	if (file_exists($path)) { $ver = '?v='.filemtime($path); }
	// Fallback: append to head elements (older themes still honor this)
	global $template;
		if (method_exists($template, 'append')) {
			// Head injection
			if (file_exists($css_path)) {
				$template->append('head_elements', '<link rel="stylesheet" href="'.htmlspecialchars($css_url.$css_ver, ENT_QUOTES).'">');
			}
			$template->append('head_elements', '<script>'.$inline.'</script>');
			if (file_exists($path)) {
				$template->append('head_elements', '<script src="'.htmlspecialchars($url.$ver, ENT_QUOTES).'"></script>');
			}
			// Footer fallback injection (safe due to guard var)
			$template->append('footer_msgs', '<script>window.CPT_ASSETS_READY||(function(){'.$inline.'})();</script>');
			if (file_exists($path)) {
				$template->append('footer_msgs', '<script src="'.htmlspecialchars($url.$ver, ENT_QUOTES).'"></script>');
			}
		}
}

/**
 * Register webservice methods used by theme-specific AJAX profile pages.
 */
function cpt_add_ws_methods($arr): void
{
	$service = &$arr[0];
	$service->addMethod(
		'core_privacy_toggle.album.images',
		'cpt_ws_get_album_images',
		array(
			'album_id' => array(),
			'pwg_token' => array(),
		),
		'Load representative-image choices for one owned album.'
	);
	$service->addMethod(
		'core_privacy_toggle.albums.update',
		'cpt_ws_update_albums',
		array(
			'payload' => array(),
			'pwg_token' => array(),
		),
		'Update owned albums from AJAX-driven profile pages.'
	);
	$service->addMethod(
		'core_privacy_toggle.owner_profile.update',
		'cpt_ws_update_owner_profile',
		array(
			'payload' => array(),
			'pwg_token' => array(),
		),
		'Update the owner public profile from AJAX-driven profile pages.'
	);
}

function cpt_ws_get_album_images($params, &$service)
{
	global $user;

	if (get_pwg_token() !== $params['pwg_token']) {
		return new \PwgError(403, 'Invalid security token');
	}

	if (empty($user['id']) || is_a_guest()) {
		return new \PwgError(401, 'Access denied');
	}

	$album_id = (int) ($params['album_id'] ?? 0);
	if ($album_id <= 0 || !cpt_album_is_owned_by($album_id, (int) $user['id'])) {
		return new \PwgError(403, 'Access denied');
	}

	return [
		'album_id' => $album_id,
		'current' => cpt_get_album_representative_details($album_id),
		'images' => cpt_fetch_album_representative_options($album_id, (int) $user['id']),
	];
}

/**
 * AJAX endpoint for theme-driven profile pages that do not submit profile.php.
 */
function cpt_ws_update_albums($params, &$service)
{
	global $user;

	if (get_pwg_token() !== $params['pwg_token']) {
		return new \PwgError(403, 'Invalid security token');
	}

	if (empty($user['id']) || is_a_guest()) {
		return new \PwgError(401, 'Access denied');
	}

	$payload = json_decode(stripslashes((string)($params['payload'] ?? '')), true);
	if (!is_array($payload)) {
		return new \PwgError(400, 'Invalid album payload');
	}

	if (!cpt_handle_album_form($payload, (int)$user['id'])) {
		return new \PwgError(400, 'No album changes were applied');
	}

	return l10n('Your changes have been saved.');
}

function cpt_ws_update_owner_profile($params, &$service)
{
	global $user;

	if (get_pwg_token() !== ($params['pwg_token'] ?? null)) {
		return new \PwgError(403, 'Invalid security token');
	}

	if (empty($user['id']) || is_a_guest()) {
		return new \PwgError(401, 'Access denied');
	}

	$payload = json_decode(stripslashes((string) ($params['payload'] ?? '')), true);
	if (!is_array($payload)) {
		return new \PwgError(400, 'Invalid owner profile payload');
	}

	if (!cpt_update_owner_profile($payload, (int) $user['id'])) {
		return new \PwgError(400, 'No owner profile changes were applied');
	}

	return l10n('Your public profile has been saved.');
}

/**
 * Process submitted album edit form.
 * Payload structure: [album_id => [name=>..., comment=>..., private=>"1"]]
 */
function cpt_handle_album_form(array $payload, int $user_id): bool
{
    $updated_any = false;
	$debug_admin = CPT_DEBUG && pwg_get_session_var('is_admin');
    foreach ($payload as $raw_id => $fields) {
        if (!is_array($fields)) { continue; }
        $album_id = (int)$raw_id;
        if ($album_id <= 0) { continue; }
        $owns = cpt_album_is_owned_by($album_id, $user_id);
		if ($debug_admin) { global $page; $page['infos'][] = '[CPT debug] album '.$album_id.' ownership rule => '.cpt_get_album_ownership_rule_for_user($album_id, $user_id); }
        if (!$owns) { continue; }

		$updates = [];
		$permission_options = [];
        if (isset($fields['name'])) {
            $name = trim($fields['name']);
            if ($name !== '') { $updates['name'] = pwg_db_real_escape_string($name); }
        }
        if (isset($fields['comment'])) {
            $comment = trim($fields['comment']);
            $updates['comment'] = pwg_db_real_escape_string($comment);
        }

		$representative_requested = array_key_exists('representative_picture_id', $fields);
		$representative_picture_id = null;
		if ($representative_requested) {
			$raw_representative = trim((string) ($fields['representative_picture_id'] ?? ''));
			$representative_picture_id = $raw_representative === '' ? null : (int) $raw_representative;
		}

		$visibility = 'public';
		if (isset($fields['visibility']) && in_array($fields['visibility'], array('public', 'private', 'shared'), true)) {
			$visibility = $fields['visibility'];
		} elseif (array_key_exists('private', $fields)) {
			$visibility = 'private';
		}

		$shareable_options = cpt_get_shareable_user_options($user_id);
		$selected_shared_user_ids = [];
		if (!empty($fields['shared_users']) && is_array($fields['shared_users'])) {
			foreach ($fields['shared_users'] as $shared_user_id) {
				$shared_user_id = (int) $shared_user_id;
				if ($shared_user_id > 0 && isset($shareable_options[$shared_user_id])) {
					$selected_shared_user_ids[$shared_user_id] = $shared_user_id;
				}
			}
		}

		if ($visibility === 'shared') {
			$selected_shared_user_ids = array_values($selected_shared_user_ids);
			$updates['status'] = 'private';
			$permission_options['mode'] = empty($selected_shared_user_ids) ? 'private' : 'shared';
			$permission_options['shared_user_ids'] = $selected_shared_user_ids;
		} elseif ($visibility === 'private') {
			$updates['status'] = 'private';
			$permission_options['mode'] = 'private';
			$permission_options['shared_user_ids'] = [];
		} else {
			$updates['status'] = 'public';
			$permission_options['mode'] = 'public';
			$permission_options['shared_user_ids'] = [];
		}

        if (!empty($updates)) {
			if ($debug_admin) { global $page; $page['infos'][] = '[CPT debug] updating album '.$album_id.' fields: '.implode(',', array_keys($updates)); }
			cpt_update_album($album_id, $updates, $debug_admin, $permission_options, $user_id);
            $updated_any = true;
        }

		if ($representative_requested && cpt_update_album_representative($album_id, $representative_picture_id, $user_id)) {
			$updated_any = true;
		}
    }
    return $updated_any;
}

/**
 * Check album ownership using the detected ownership column or fallback heuristic.
 */
function cpt_album_is_owned_by(int $album_id, int $user_id): bool
{
	return cpt_get_album_ownership_rule_for_user($album_id, $user_id) !== 'denied';
}

function cpt_get_album_ownership_rule_for_user(int $album_id, int $user_id): string
{
	$direct_owner_id = cpt_get_album_direct_owner_id($album_id);
	if ($direct_owner_id !== null) {
		return $direct_owner_id === (int) $user_id ? 'direct' : 'denied';
	}

	$effective_owner_id = cpt_get_album_effective_owner_id($album_id);
	if ($effective_owner_id !== null) {
		return $effective_owner_id === (int) $user_id ? 'ancestor' : 'denied';
	}

	return cpt_album_has_exclusive_contributor($album_id, $user_id) ? 'exclusive_contributor' : 'denied';
}

function cpt_album_has_exclusive_contributor(int $album_id, int $user_id): bool
{
	$sql = 'SELECT COUNT(DISTINCT i.added_by) AS contribs, MIN(i.added_by) AS min_by
		FROM '.IMAGE_CATEGORY_TABLE.' ic
		INNER JOIN '.IMAGES_TABLE.' i ON i.id = ic.image_id
		WHERE ic.category_id = '.(int)$album_id;
	$res = pwg_query($sql);
	if (!$res) { return false; }
	$row = pwg_db_fetch_assoc($res);
	if (!$row) { return false; }
	return ((int)($row['contribs'] ?? 0) === 1 && (int)($row['min_by'] ?? -1) === (int)$user_id);
}

function cpt_get_album_effective_owner_id(int $album_id): ?int
{
	$direct_owner_id = cpt_get_album_direct_owner_id($album_id);
	if ($direct_owner_id !== null) {
		return $direct_owner_id;
	}

	foreach (array_reverse(cpt_get_album_ancestor_ids($album_id)) as $ancestor_id) {
		$ancestor_owner_id = cpt_get_album_direct_owner_id($ancestor_id);
		if ($ancestor_owner_id !== null) {
			return $ancestor_owner_id;
		}
	}

	return null;
}

function cpt_get_album_direct_owner_id(int $album_id): ?int
{
	static $cache = [];

	if (array_key_exists($album_id, $cache)) {
		return $cache[$album_id];
	}

	$ownership_column = cpt_get_album_ownership_column();
	if ($ownership_column === null) {
		$cache[$album_id] = null;
		return null;
	}

	$res = pwg_query('SELECT '.$ownership_column.' AS owner_id FROM '.CATEGORIES_TABLE.' WHERE id='.(int) $album_id.' LIMIT 1');
	if (!$res) {
		$cache[$album_id] = null;
		return null;
	}

	$row = pwg_db_fetch_assoc($res);
	if (!$row || !isset($row['owner_id']) || $row['owner_id'] === null || $row['owner_id'] === '') {
		$cache[$album_id] = null;
		return null;
	}

	$cache[$album_id] = (int) $row['owner_id'];
	return $cache[$album_id];
}

function cpt_get_album_ancestor_ids(int $album_id): array
{
	static $cache = [];

	if (isset($cache[$album_id])) {
		return $cache[$album_id];
	}

	$result = pwg_query('SELECT uppercats, id_uppercat FROM '.CATEGORIES_TABLE.' WHERE id='.(int) $album_id.' LIMIT 1');
	if ($result) {
		$row = pwg_db_fetch_assoc($result);
		if (!empty($row['uppercats'])) {
			$path_ids = array_values(array_filter(array_map('intval', explode(',', (string) $row['uppercats']))));
			if (!empty($path_ids) && end($path_ids) === $album_id) {
				array_pop($path_ids);
			}
			$cache[$album_id] = $path_ids;
			return $cache[$album_id];
		}

		if (isset($row['id_uppercat']) && $row['id_uppercat'] !== null && $row['id_uppercat'] !== '') {
			$ancestors = [];
			$current_parent_id = (int) $row['id_uppercat'];
			while ($current_parent_id > 0) {
				array_unshift($ancestors, $current_parent_id);
				$current_parent_id = cpt_get_album_parent_id($current_parent_id) ?? 0;
			}
			$cache[$album_id] = $ancestors;
			return $cache[$album_id];
		}
	}

	$cache[$album_id] = [];
	return $cache[$album_id];
}

function cpt_get_album_parent_id(int $album_id): ?int
{
	static $cache = [];

	if (array_key_exists($album_id, $cache)) {
		return $cache[$album_id];
	}

	$result = pwg_query('SELECT id_uppercat FROM '.CATEGORIES_TABLE.' WHERE id='.(int) $album_id.' LIMIT 1');
	if (!$result) {
		$cache[$album_id] = null;
		return null;
	}

	$row = pwg_db_fetch_assoc($result);
	if (!$row || !isset($row['id_uppercat']) || $row['id_uppercat'] === null || $row['id_uppercat'] === '') {
		$cache[$album_id] = null;
		return null;
	}

	$cache[$album_id] = (int) $row['id_uppercat'];
	return $cache[$album_id];
}

function cpt_album_is_descendant_of_owned_root(int $album_id, int $user_id): bool
{
	$direct_owner_id = cpt_get_album_direct_owner_id($album_id);
	if ($direct_owner_id !== null) {
		return false;
	}

	foreach (array_reverse(cpt_get_album_ancestor_ids($album_id)) as $ancestor_id) {
		$ancestor_owner_id = cpt_get_album_direct_owner_id($ancestor_id);
		if ($ancestor_owner_id !== null) {
			return $ancestor_owner_id === (int) $user_id;
		}
	}

	return false;
}

function cpt_get_effective_owner_root_album_id_for_album(int $album_id): ?int
{
	$effective_owner_id = cpt_get_album_effective_owner_id($album_id);
	if ($effective_owner_id === null) {
		return null;
	}

	$path_ids = array_merge(cpt_get_album_ancestor_ids($album_id), [$album_id]);
	foreach ($path_ids as $path_id) {
		if (cpt_get_album_direct_owner_id((int) $path_id) === $effective_owner_id) {
			return (int) $path_id;
		}
	}

	return null;
}

function cpt_get_effective_owner_root_album_id_for_user(int $user_id): ?int
{
	$albums = cpt_fetch_albums_owned_by($user_id);
	foreach ($albums as $album) {
		$root_album_id = cpt_get_effective_owner_root_album_id_for_album((int) $album['id']);
		if ($root_album_id !== null && cpt_get_album_direct_owner_id($root_album_id) === (int) $user_id) {
			return $root_album_id;
		}
	}

	return null;
}

function cpt_get_effective_owner_root_album_data(int $user_id): ?array
{
	$root_album_id = cpt_get_effective_owner_root_album_id_for_user($user_id);
	if ($root_album_id === null) {
		return null;
	}

	$result = pwg_query('SELECT id, name, comment, status FROM '.CATEGORIES_TABLE.' WHERE id='.(int) $root_album_id.' LIMIT 1');
	if (!$result) {
		return null;
	}

	$row = pwg_db_fetch_assoc($result);
	return is_array($row) ? $row : null;
}

function cpt_fetch_owner_profile_rows(int $root_album_id, int $owner_user_id): array
{
	$rows = [];
	if (!cpt_ensure_owner_profile_table()) {
		return $rows;
	}

	$result = pwg_query('SELECT field_key, value_text, tag_id FROM '.CPT_OWNER_PROFILE_TABLE.' WHERE root_album_id='.(int) $root_album_id.' AND owner_user_id='.(int) $owner_user_id.' ORDER BY field_key ASC');
	if (!$result) {
		return $rows;
	}

	while ($row = pwg_db_fetch_assoc($result)) {
		$field_key = (string) ($row['field_key'] ?? '');
		if ($field_key === '') {
			continue;
		}
		$rows[$field_key] = [
			'field_key' => $field_key,
			'value_text' => isset($row['value_text']) ? (string) $row['value_text'] : null,
			'tag_id' => isset($row['tag_id']) && $row['tag_id'] !== null && $row['tag_id'] !== '' ? (int) $row['tag_id'] : null,
		];
	}

	return $rows;
}

function cpt_get_owner_profile_table_schema_sql(): string
{
	return 'CREATE TABLE IF NOT EXISTS '.CPT_OWNER_PROFILE_TABLE." (
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
)";
}

function cpt_owner_profile_table_exists(): bool
{
	if (array_key_exists('__cpt_owner_profile_table_exists', $GLOBALS)) {
		return (bool) $GLOBALS['__cpt_owner_profile_table_exists'];
	}

	$result = pwg_query("SHOW TABLES LIKE '".pwg_db_real_escape_string(CPT_OWNER_PROFILE_TABLE)."'");
	$exists = (bool) ($result && pwg_db_fetch_row($result));
	$GLOBALS['__cpt_owner_profile_table_exists'] = $exists;

	return $exists;
}

function cpt_ensure_owner_profile_table(): bool
{
	if (cpt_owner_profile_table_exists()) {
		return true;
	}

	$result = pwg_query(cpt_get_owner_profile_table_schema_sql());
	if ($result === false) {
		return false;
	}

	$GLOBALS['__cpt_owner_profile_table_exists'] = true;
	return true;
}

function cpt_get_owner_profile_editor_data(int $user_id): ?array
{
	if (!cpt_ensure_owner_profile_table()) {
		return null;
	}

	$root_album = cpt_get_effective_owner_root_album_data($user_id);
	if (empty($root_album['id'])) {
		return null;
	}

	$root_album_id = (int) $root_album['id'];
	$rows = cpt_fetch_owner_profile_rows($root_album_id, $user_id);
	$schema = cpt_get_owner_profile_field_schema();
	$fields = [];

	foreach ($schema as $field_key => $definition) {
		$field_type = (string) ($definition['type'] ?? 'text');
		$field_row = $rows[$field_key] ?? null;
		$field = [
			'key' => (string) $field_key,
			'label' => (string) ($definition['label'] ?? $field_key),
			'type' => $field_type,
			'max_length' => isset($definition['max_length']) ? (int) $definition['max_length'] : null,
			'value_text' => is_array($field_row) ? (string) ($field_row['value_text'] ?? '') : '',
			'tag_id' => is_array($field_row) && !empty($field_row['tag_id']) ? (int) $field_row['tag_id'] : 0,
			'options' => [],
		];

		if ($field_type === 'controlled') {
			$options = cpt_get_owner_profile_controlled_options((string) $field_key);
			if (empty($options)) {
				continue;
			}

			$field['options'] = $options;
		}

		$fields[] = $field;
	}

	if (empty($fields)) {
		return null;
	}

	return [
		'root_album_id' => $root_album_id,
		'root_album_name' => (string) ($root_album['name'] ?? ''),
		'fields' => $fields,
	];
}

function cpt_get_owner_profile_public_data_for_album(int $album_id): ?array
{
	if ($album_id <= 0 || !cpt_ensure_owner_profile_table()) {
		return null;
	}

	$root_album_id = cpt_get_effective_owner_root_album_id_for_album($album_id);
	if ($root_album_id === null) {
		return null;
	}

	$owner_user_id = cpt_get_album_effective_owner_id($root_album_id);
	if ($owner_user_id === null) {
		return null;
	}

	$saved_rows = cpt_fetch_owner_profile_rows($root_album_id, $owner_user_id);
	if (empty($saved_rows)) {
		return null;
	}

	$rows = [];
	foreach (cpt_get_owner_profile_field_schema() as $field_key => $definition) {
		if (empty($saved_rows[$field_key]['value_text'])) {
			continue;
		}

		$rows[] = [
			'key' => (string) $field_key,
			'label' => (string) ($definition['label'] ?? $field_key),
			'value_text' => (string) $saved_rows[$field_key]['value_text'],
		];
	}

	if (empty($rows)) {
		return null;
	}

	return [
		'root_album_id' => (int) $root_album_id,
		'owner_user_id' => (int) $owner_user_id,
		'rows' => $rows,
	];
}

function cpt_normalize_owner_profile_text(string $field_key, string $value): string
{
	$definition = cpt_get_owner_profile_field_definition($field_key);
	$normalized = trim($value);
	$max_length = isset($definition['max_length']) ? (int) $definition['max_length'] : 255;
	if ($max_length <= 0) {
		return $normalized;
	}

	if (function_exists('mb_substr')) {
		return mb_substr($normalized, 0, $max_length);
	}

	return substr($normalized, 0, $max_length);
}

function cpt_validate_owner_profile_field(string $field_key, array $field_payload): ?array
{
	$definition = cpt_get_owner_profile_field_definition($field_key);
	if ($definition === null) {
		return null;
	}

	if (($definition['type'] ?? 'text') === 'controlled') {
		$tag_id = isset($field_payload['tag_id']) ? (int) $field_payload['tag_id'] : 0;
		if ($tag_id <= 0) {
			return ['delete' => true];
		}

		$options = cpt_get_owner_profile_controlled_options($field_key);
		if (!isset($options[$tag_id])) {
			return null;
		}

		return [
			'delete' => false,
			'tag_id' => $tag_id,
			'value_text' => (string) $options[$tag_id],
		];
	}

	$value = cpt_normalize_owner_profile_text($field_key, (string) ($field_payload['value_text'] ?? ''));
	if ($value === '') {
		return ['delete' => true];
	}

	return [
		'delete' => false,
		'tag_id' => null,
		'value_text' => $value,
	];
}

function cpt_validate_owner_profile_payload(array $payload, int $user_id): array
{
	$root_album_id = (int) ($payload['root_album_id'] ?? 0);
	if ($root_album_id <= 0) {
		return [];
	}

	if (cpt_get_effective_owner_root_album_id_for_album($root_album_id) !== $root_album_id) {
		return [];
	}

	if (cpt_get_album_effective_owner_id($root_album_id) !== (int) $user_id) {
		return [];
	}

	$fields_payload = $payload['fields'] ?? null;
	if (!is_array($fields_payload)) {
		return [];
	}

	$validated_fields = [];
	foreach ($fields_payload as $field_key => $field_payload) {
		if (!is_array($field_payload)) {
			$field_payload = ['value_text' => (string) $field_payload];
		}

		$validated = cpt_validate_owner_profile_field((string) $field_key, $field_payload);
		if ($validated === null) {
			continue;
		}

		$validated_fields[(string) $field_key] = $validated;
	}

	return [
		'root_album_id' => $root_album_id,
		'owner_user_id' => (int) $user_id,
		'fields' => $validated_fields,
	];
}

function cpt_save_owner_profile(int $root_album_id, int $owner_user_id, array $fields): bool
{
	if ($root_album_id <= 0 || $owner_user_id <= 0 || empty($fields)) {
		return false;
	}

	if (!cpt_ensure_owner_profile_table()) {
		return false;
	}

	if (cpt_get_effective_owner_root_album_id_for_album($root_album_id) !== $root_album_id) {
		return false;
	}

	if (cpt_get_album_effective_owner_id($root_album_id) !== (int) $owner_user_id) {
		return false;
	}

	$updated_any = false;
	foreach ($fields as $field_key => $field_data) {
		if (cpt_get_owner_profile_field_definition((string) $field_key) === null) {
			continue;
		}

		pwg_query("DELETE FROM ".CPT_OWNER_PROFILE_TABLE." WHERE root_album_id=".(int) $root_album_id." AND field_key='".pwg_db_real_escape_string((string) $field_key)."'");
		$updated_any = true;

		if (!empty($field_data['delete'])) {
			continue;
		}

		$value_sql = isset($field_data['value_text']) && $field_data['value_text'] !== null
			? "'".pwg_db_real_escape_string((string) $field_data['value_text'])."'"
			: 'NULL';
		$tag_sql = isset($field_data['tag_id']) && $field_data['tag_id'] !== null
			? (string) (int) $field_data['tag_id']
			: 'NULL';

		$sql = sprintf(
			"INSERT INTO %s (root_album_id, owner_user_id, field_key, value_text, tag_id, updated_at) VALUES (%d, %d, '%s', %s, %s, NOW())",
			CPT_OWNER_PROFILE_TABLE,
			(int) $root_album_id,
			(int) $owner_user_id,
			pwg_db_real_escape_string((string) $field_key),
			$value_sql,
			$tag_sql
		);
		$result = pwg_query($sql);
		$updated_any = $updated_any || (bool) $result;
	}

	return $updated_any;
}

function cpt_update_owner_profile(array $payload, int $user_id): bool
{
	$validated = cpt_validate_owner_profile_payload($payload, $user_id);
	if (empty($validated['fields']) || empty($validated['root_album_id']) || empty($validated['owner_user_id'])) {
		return false;
	}

	return cpt_save_owner_profile((int) $validated['root_album_id'], (int) $validated['owner_user_id'], $validated['fields']);
}

/**
 * Returns the explicit owner id when the ownership column exists and is set.
 */
function cpt_get_album_explicit_owner_id(int $album_id): ?int
{
	return cpt_get_album_direct_owner_id($album_id);
}

/**
 * Low-level update helper. Uses simple dynamic SQL since Piwigo core often builds strings; ensured safe via escaping above.
 */
function cpt_update_album(int $album_id, array $fields, bool $debug=false, array $permission_options = [], ?int $owner_user_id = null): void
{
	if (empty($fields)) { return; }
	$old_status = null;
	if (isset($fields['status'])) {
		// Fetch previous status to detect transition
		$resPrev = pwg_query('SELECT status FROM '.CATEGORIES_TABLE.' WHERE id='.(int)$album_id.' LIMIT 1');
		if ($resPrev) { $r = pwg_db_fetch_assoc($resPrev); $old_status = $r['status'] ?? null; }
	}
	$assignments = [];
	foreach ($fields as $col => $val) {
		$assignments[] = $col . "='" . pwg_db_real_escape_string($val) . "'";
	}
	$sql = 'UPDATE '.CATEGORIES_TABLE.' SET '.implode(',', $assignments).' WHERE id='.(int)$album_id.' LIMIT 1';
	$result = pwg_query($sql);
	if ($debug) { global $page; $page['infos'][] = '[CPT debug] SQL: '.htmlspecialchars($sql).' result='.($result?'ok':'fail'); }
	if ($result) {
		// Invalidate user-related caches so visibility changes propagate quickly
		if (function_exists('invalidate_user_cache')) { invalidate_user_cache(); }
		if (function_exists('trigger_notify')) { trigger_notify('invalidate_user_cache'); }

		// Synchronize permissions for private/public transitions
		if (isset($fields['status']) && ($old_status !== $fields['status'] || !empty($permission_options))) {
			// One-shot flag for other sessions to re-evaluate permissions on next request
			$_SESSION['cpt_permissions_changed'] = 1;
			$new_status = $fields['status'];
			$mode = $permission_options['mode'] ?? ($new_status === 'private' ? 'private' : 'public');
			$shared_user_ids = [];
			if (!empty($permission_options['shared_user_ids']) && is_array($permission_options['shared_user_ids'])) {
				$shared_user_ids = array_values(array_unique(array_map('intval', $permission_options['shared_user_ids'])));
			}
			if ($new_status === 'private') {
				// Remove any existing explicit permissions first to avoid duplication
				pwg_query('DELETE FROM '.USER_ACCESS_TABLE.' WHERE cat_id='.(int)$album_id);
				// Grant album owner + admin (user_id=1) access
				$owner_id = $owner_user_id;
				if ($owner_id === null) {
					$owner_id = cpt_get_album_effective_owner_id($album_id);
				}
				if ($owner_id === null) {
					$resImg = pwg_query('SELECT i.added_by FROM '.IMAGE_CATEGORY_TABLE.' ic INNER JOIN '.IMAGES_TABLE.' i ON i.id=ic.image_id WHERE ic.category_id='.(int)$album_id.' ORDER BY i.id ASC LIMIT 1');
					if ($resImg) { $rowI = pwg_db_fetch_assoc($resImg); if ($rowI) { $owner_id = (int)$rowI['added_by']; } }
				}
				$allowed_user_ids = [1];
				if ($owner_id !== null && $owner_id > 0) {
					$allowed_user_ids[] = (int) $owner_id;
				}
				if ($mode === 'shared') {
					$allowed_user_ids = array_merge($allowed_user_ids, $shared_user_ids);
				}
				$allowed_user_ids = array_values(array_unique(array_filter($allowed_user_ids, fn($id) => (int) $id > 0)));

				$insValues = [];
				foreach ($allowed_user_ids as $allowed_user_id) {
					$insValues[] = '('.(int) $allowed_user_id.','.(int)$album_id.')';
				}
				if (!empty($insValues)) {
					$permSql = 'INSERT INTO '.USER_ACCESS_TABLE.' (user_id, cat_id) VALUES '.implode(',', $insValues);
					pwg_query($permSql);
					if ($debug) { global $page; $page['infos'][] = '[CPT debug] Permissions synced: '.htmlspecialchars($permSql); }
				}
			} elseif ($new_status === 'public') {
				// Public albums should not carry user-specific access rows (unless custom), remove our minimal set
				pwg_query('DELETE FROM '.USER_ACCESS_TABLE.' WHERE cat_id='.(int)$album_id);
				if ($debug) { global $page; $page['infos'][] = '[CPT debug] Cleared user_access rows for now-public album '.$album_id; }
			}
			if (function_exists('trigger_notify')) { trigger_notify('CPT_after_privacy_change', $album_id); }
			// Purge cached per-user access rows so permission recalculation occurs
			cpt_purge_user_cache();
		}
	}
}

/**
 * Fetch the current status for a single album.
 */
function cpt_get_album_status(int $album_id): ?string
{
	$result = pwg_query('SELECT status FROM '.CATEGORIES_TABLE.' WHERE id='.(int) $album_id.' LIMIT 1');
	if (!$result) {
		return null;
	}

	$row = pwg_db_fetch_assoc($result);
	if (!$row || !isset($row['status'])) {
		return null;
	}

	return (string) $row['status'];
}

/**
 * Detect which album ownership column exists on categories table.
 * Community 16 uses community_user; older code may still use user_id.
 */
function cpt_get_album_ownership_column(): ?string
{
	// Test harness override (non-production). If set, bypass detection cost & static cache.
	if (isset($GLOBALS['__cpt_force_ownership_column'])) {
		if (is_string($GLOBALS['__cpt_force_ownership_column'])) {
			return $GLOBALS['__cpt_force_ownership_column'];
		}
		return $GLOBALS['__cpt_force_ownership_column'] ? 'user_id' : null;
	}
	if (array_key_exists('__cpt_ownership_column_cache', $GLOBALS)) {
		return $GLOBALS['__cpt_ownership_column_cache'];
	}
	$GLOBALS['__cpt_ownership_column_cache'] = null;
	$res = pwg_query('DESC '.CATEGORIES_TABLE);
	if ($res) {
		$columns = array();
		while ($row = pwg_db_fetch_assoc($res)) {
			if (isset($row['Field'])) {
				$columns[] = $row['Field'];
			}
		}
		if (in_array('community_user', $columns, true)) {
			$GLOBALS['__cpt_ownership_column_cache'] = 'community_user';
		} elseif (in_array('user_id', $columns, true)) {
			$GLOBALS['__cpt_ownership_column_cache'] = 'user_id';
		}
	}
	return $GLOBALS['__cpt_ownership_column_cache'];
}

function cpt_has_album_ownership_column(): bool
{
	return cpt_get_album_ownership_column() !== null;
}

/**
 * Purge the user cache table to force permission recalculation.
 * This table stores precomputed accessible/forbidden categories per user.
 * We delete rows (instead of TRUNCATE for portability) whenever an album privacy
 * state changes so non-owner sessions immediately reflect new visibility.
 */
function cpt_purge_user_cache(): void
{
	global $prefixeTable;
	if (empty($prefixeTable)) { return; }
	$table = $prefixeTable.'user_cache';
	// Verify table exists (avoid warnings on installs lacking it)
	$check = pwg_query("SHOW TABLES LIKE '".pwg_db_real_escape_string($table)."'");
	if (!$check || !pwg_db_fetch_row($check)) { return; }
			pwg_query('DELETE FROM '.$table);
}

