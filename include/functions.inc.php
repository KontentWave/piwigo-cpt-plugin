<?php
defined('CORE_PRIVACY_TOGGLE_PATH') or die('Hacking attempt!');

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
		$template->assign('CPT_LIMITED_MODE_NOTICE', $limited_mode_notice);

		// Render partial (will be injected by JS; contains only inner controls)
		// Use set_filename + parse instead of fetch (fetch not available in this env)
		$template->set_filename('cpt_ucp_album_manager', realpath(CORE_PRIVACY_TOGGLE_PATH.'template/ucp_album_manager.tpl'));
		$partial = $template->parse('cpt_ucp_album_manager', true);
		cpt_attach_album_manager_to_profile();
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
	if ($user_id <= 0 || !cpt_album_is_owned_by($album_id, $user_id)) {
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
	$should_reconcile_descendants = $current_status === $target_status
		&& cpt_should_propagate_private_status_to_descendants($album_id, array('status' => $target_status), array(), $user_id);
	if ($current_status !== $target_status || $should_reconcile_descendants) {
		if (cpt_update_album($album_id, array('status' => $target_status), false, array(), $user_id)) {
			cpt_flush_user_cache_invalidation();
			$_SESSION['page_infos'][] = l10n('Album privacy updated.');
		} else {
			$_SESSION['page_errors'][] = l10n('Album privacy update failed. No changes were saved.');
		}
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
	$effective_owner_root_album_id = cpt_get_effective_owner_root_album_id_for_album($album_id);

	return [
		'id' => $album_id,
		'name' => $row['name'],
		'comment' => $row['comment'],
		'status' => $row['status'],
		'is_effective_owner_root' => $effective_owner_root_album_id !== null && $effective_owner_root_album_id === $album_id,
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

function cpt_get_webmaster_user_id(): int
{
	global $conf;

	return isset($conf['webmaster_id']) ? (int) $conf['webmaster_id'] : 1;
}

function cpt_get_shareable_user_options(int $owner_user_id): array
{
	global $conf;

	$guest_id = isset($conf['guest_id']) ? (int) $conf['guest_id'] : 0;
	$webmaster_id = cpt_get_webmaster_user_id();
	$options = [];
	$query = 'SELECT '.USERS_TABLE.'.'.$conf['user_fields']['id'].' AS user_id, '.USERS_TABLE.'.'.$conf['user_fields']['username'].' AS username FROM '.USERS_TABLE.' LEFT JOIN '.USER_INFOS_TABLE.' ON '.USER_INFOS_TABLE.'.user_id = '.USERS_TABLE.'.'.$conf['user_fields']['id'].' WHERE '.USERS_TABLE.'.'.$conf['user_fields']['id'].' NOT IN ('.(int) $webmaster_id.','.(int) $owner_user_id;
	if ($guest_id > 0) {
		$query .= ','.$guest_id;
	}
	$query .= ") AND (".USER_INFOS_TABLE.".status IS NULL OR ".USER_INFOS_TABLE.".status NOT IN ('admin','webmaster')) ORDER BY username";
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
	$webmaster_id = cpt_get_webmaster_user_id();
	$shared_user_ids = [];
	$result = pwg_query('SELECT user_id FROM '.USER_ACCESS_TABLE.' WHERE cat_id='.(int) $album_id);
	if (!$result) {
		return $shared_user_ids;
	}
	while ($row = pwg_db_fetch_assoc($result)) {
		$user_id = (int) ($row['user_id'] ?? 0);
		if ($user_id <= 0 || $user_id === $webmaster_id || $user_id === $owner_user_id) {
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

function cpt_get_album_permission_options(int $album_id, int $owner_user_id): array
{
	$visibility = cpt_get_album_visibility_mode($album_id, $owner_user_id);
	if ($visibility === 'shared') {
		return [
			'mode' => 'shared',
			'shared_user_ids' => cpt_get_album_shared_user_ids($album_id, $owner_user_id),
		];
	}

	if ($visibility === 'private') {
		return [
			'mode' => 'private',
			'shared_user_ids' => [],
		];
	}

	return [
		'mode' => 'public',
		'shared_user_ids' => [],
	];
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

	// Piwigo adds slashes to request values in common.inc before WS dispatch.
	$payload = json_decode(stripslashes((string)($params['payload'] ?? '')), true);
	if (!is_array($payload)) {
		return new \PwgError(400, 'Invalid album payload');
	}

	if (!cpt_handle_album_form($payload, (int)$user['id'])) {
		return new \PwgError(400, 'No album changes were applied');
	}

	return l10n('Your changes have been saved.');
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
			if ($name !== '') { $updates['name'] = $name; }
        }
        if (isset($fields['comment'])) {
            $comment = trim($fields['comment']);
			$updates['comment'] = $comment;
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
			if (cpt_update_album($album_id, $updates, $debug_admin, $permission_options, $user_id)) {
				$updated_any = true;
			} else {
				global $page;
				$page['errors'][] = l10n('Album privacy update failed. No changes were saved.').' (#'.$album_id.')';
			}
        }

		if ($representative_requested && cpt_update_album_representative($album_id, $representative_picture_id, $user_id)) {
			$updated_any = true;
		}
    }
    // Single invalidation for the whole submit (no-op when no privacy change)
    cpt_flush_user_cache_invalidation();
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

function cpt_get_descendant_album_ids(int $album_id): array
{
	$result = pwg_query('SELECT id, uppercats FROM '.CATEGORIES_TABLE.' ORDER BY id ASC');
	if (!$result) {
		return [];
	}

	$descendant_ids = [];
	while ($row = pwg_db_fetch_assoc($result)) {
		$candidate_id = (int) ($row['id'] ?? 0);
		if ($candidate_id <= 0 || $candidate_id === $album_id || empty($row['uppercats'])) {
			continue;
		}

		$path_ids = array_values(array_filter(array_map('intval', explode(',', (string) $row['uppercats']))));
		if (in_array($album_id, array_slice($path_ids, 0, -1), true)) {
			$descendant_ids[] = $candidate_id;
		}
	}

	return $descendant_ids;
}

function cpt_should_propagate_private_status_to_descendants(int $album_id, array $fields, array $permission_options = [], ?int $owner_user_id = null): bool
{
	$status = $fields['status'] ?? null;
	if (!in_array($status, ['private', 'public'], true)) {
		return false;
	}

	$mode = $permission_options['mode'] ?? ($status === 'private' ? 'private' : 'public');
	if (!in_array($mode, ['private', 'shared', 'public'], true)) {
		return false;
	}

	$direct_owner_id = cpt_get_album_direct_owner_id($album_id);
	if ($direct_owner_id === null) {
		return false;
	}

	if ($owner_user_id !== null && $direct_owner_id !== (int) $owner_user_id) {
		return false;
	}

	return cpt_get_effective_owner_root_album_id_for_album($album_id) === $album_id;
}

function cpt_sync_album_visibility_permissions(int $album_id, string $new_status, array $permission_options = [], ?int $owner_user_id = null, bool $debug = false): ?bool
{
	$current_user_ids = cpt_get_album_access_user_ids($album_id);
	$allowed_user_ids = cpt_get_target_album_access_user_ids($album_id, $new_status, $permission_options, $owner_user_id);

	if ($new_status === 'private') {
		if ($allowed_user_ids === $current_user_ids) {
			// Access rows already match the target set — nothing to write.
			return false;
		}

		if (!pwg_query('DELETE FROM '.USER_ACCESS_TABLE.' WHERE cat_id='.(int) $album_id)) {
			return null;
		}
		$insValues = [];
		foreach ($allowed_user_ids as $allowed_user_id) {
			$insValues[] = '('.(int) $allowed_user_id.','.(int) $album_id.')';
		}
		if (!empty($insValues)) {
			$permSql = 'INSERT INTO '.USER_ACCESS_TABLE.' (user_id, cat_id) VALUES '.implode(',', $insValues);
			if (!pwg_query($permSql)) {
				return null;
			}
			if ($debug) {
				global $page;
				$page['infos'][] = '[CPT debug] Permissions synced: '.htmlspecialchars($permSql);
			}
		}
		return true;
	}

	if ($new_status === 'public') {
		if (empty($current_user_ids)) {
			return false;
		}
		if (!pwg_query('DELETE FROM '.USER_ACCESS_TABLE.' WHERE cat_id='.(int) $album_id)) {
			return null;
		}
		if ($debug) {
			global $page;
			$page['infos'][] = '[CPT debug] Cleared user_access rows for now-public album '.$album_id;
		}
		return true;
	}

	return false;
}

function cpt_get_target_album_access_user_ids(int $album_id, string $new_status, array $permission_options = [], ?int $owner_user_id = null): array
{
	if ($new_status !== 'private') {
		return [];
	}

	$mode = $permission_options['mode'] ?? 'private';
	$shared_user_ids = [];
	if (!empty($permission_options['shared_user_ids']) && is_array($permission_options['shared_user_ids'])) {
		$shared_user_ids = array_values(array_unique(array_map('intval', $permission_options['shared_user_ids'])));
	}

	$owner_id = $owner_user_id;
	if ($owner_id === null) {
		$owner_id = cpt_get_album_effective_owner_id($album_id);
	}
	if ($owner_id === null) {
		$resImg = pwg_query('SELECT i.added_by FROM '.IMAGE_CATEGORY_TABLE.' ic INNER JOIN '.IMAGES_TABLE.' i ON i.id=ic.image_id WHERE ic.category_id='.(int) $album_id.' ORDER BY i.id ASC LIMIT 1');
		if ($resImg) {
			$rowI = pwg_db_fetch_assoc($resImg);
			if ($rowI) {
				$owner_id = (int) $rowI['added_by'];
			}
		}
	}

	$allowed_user_ids = [cpt_get_webmaster_user_id()];
	if ($owner_id !== null && $owner_id > 0) {
		$allowed_user_ids[] = (int) $owner_id;
	}
	if ($mode === 'shared') {
		$allowed_user_ids = array_merge($allowed_user_ids, $shared_user_ids);
	}

	$allowed_user_ids = array_values(array_unique(array_filter($allowed_user_ids, fn($id) => (int) $id > 0)));
	sort($allowed_user_ids);

	return $allowed_user_ids;
}

/**
 * Current user ids holding a user_access row for the album, sorted.
 */
function cpt_get_album_access_user_ids(int $album_id): array
{
	$user_ids = [];
	$result = pwg_query('SELECT user_id FROM '.USER_ACCESS_TABLE.' WHERE cat_id='.(int) $album_id);
	if ($result) {
		while ($row = pwg_db_fetch_assoc($result)) {
			$user_ids[] = (int) $row['user_id'];
		}
	}
	sort($user_ids);
	return $user_ids;
}

function cpt_private_root_has_nonprivate_descendants(int $album_id): bool
{
	foreach (cpt_get_descendant_album_ids($album_id) as $descendant_album_id) {
		if (cpt_get_album_status((int) $descendant_album_id) !== 'private') {
			return true;
		}
	}

	return false;
}

function cpt_reconcile_private_owner_root_descendants_for_user(int $user_id): bool
{
	if ($user_id <= 0) {
		return false;
	}

	$reconciled = false;
	foreach (cpt_fetch_albums_owned_by($user_id) as $album) {
		$album_id = (int) ($album['id'] ?? 0);
		if ($album_id <= 0 || empty($album['is_effective_owner_root'])) {
			continue;
		}

		if (cpt_get_album_status($album_id) !== 'private') {
			continue;
		}

		if (!cpt_private_root_has_nonprivate_descendants($album_id)) {
			continue;
		}

		if (cpt_update_album($album_id, ['status' => 'private'], false, cpt_get_album_permission_options($album_id, $user_id), $user_id)) {
			$reconciled = true;
		}
	}

	cpt_flush_user_cache_invalidation();

	return $reconciled;
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
function cpt_update_album(int $album_id, array $fields, bool $debug=false, array $permission_options = [], ?int $owner_user_id = null): bool
{
	if (empty($fields)) { return false; }
	$allowed_columns = ['name' => true, 'comment' => true, 'status' => true];
	foreach (array_keys($fields) as $field_name) {
		if (!isset($allowed_columns[$field_name])) {
			cpt_log_message('error', 'album update rejected for album '.$album_id.': unknown column '.$field_name);
			return false;
		}
	}
	$old_status = null;
	$privacy_target_album_ids = [$album_id];
	$is_privacy_update = isset($fields['status']);
	if ($is_privacy_update) {
		// Fetch previous status to detect transition
		$resPrev = pwg_query('SELECT status FROM '.CATEGORIES_TABLE.' WHERE id='.(int)$album_id.' LIMIT 1');
		if ($resPrev) { $r = pwg_db_fetch_assoc($resPrev); $old_status = $r['status'] ?? null; }
		if (cpt_should_propagate_private_status_to_descendants($album_id, $fields, $permission_options, $owner_user_id)) {
			$privacy_target_album_ids = array_merge($privacy_target_album_ids, cpt_get_descendant_album_ids($album_id));
		}
	}
	$snapshot = $is_privacy_update ? cpt_capture_album_update_snapshot($album_id, $fields, $privacy_target_album_ids) : null;
	if ($is_privacy_update && $snapshot === null) {
		cpt_log_message('error', 'privacy update aborted for album '.$album_id.': failed to capture pre-write snapshot');
		return false;
	}
	$assignments = [];
	foreach ($fields as $col => $val) {
		$assignments[] = $col . "='" . pwg_db_real_escape_string($val) . "'";
	}
	$sql = 'UPDATE '.CATEGORIES_TABLE.' SET '.implode(',', $assignments).' WHERE id='.(int)$album_id.' LIMIT 1';

	$result = pwg_query($sql);
	if ($debug) { global $page; $page['infos'][] = '[CPT debug] SQL: '.htmlspecialchars($sql).' result='.($result?'ok':'fail'); }
	if (!$result) {
		return cpt_update_album_fail($snapshot, $album_id, 'album row update failed');
	}
	if (!cpt_album_fields_match($album_id, $fields)) {
		return cpt_update_album_fail($snapshot, $album_id, 'album row verification failed');
	}

	if (!$is_privacy_update) {
		return true;
	}

	if (count($privacy_target_album_ids) > 1) {
		$escaped_status = pwg_db_real_escape_string($fields['status']);
		foreach ($privacy_target_album_ids as $target_album_id) {
			if ($target_album_id === $album_id) {
				continue;
			}

			$descendant_sql = 'UPDATE '.CATEGORIES_TABLE." SET status='".$escaped_status."' WHERE id=".(int) $target_album_id.' LIMIT 1';
			if (!pwg_query($descendant_sql)) {
				return cpt_update_album_fail($snapshot, $album_id, 'descendant propagation failed for album '.$target_album_id);
			}
			if (cpt_get_album_status($target_album_id) !== (string) $fields['status']) {
				return cpt_update_album_fail($snapshot, $album_id, 'descendant verification failed for album '.$target_album_id);
			}
			if ($debug) {
				global $page;
				$page['infos'][] = '[CPT debug] Propagated privacy SQL: '.htmlspecialchars($descendant_sql);
			}
		}
	}

	// Synchronize permissions for private/public transitions
	$permissions_changed = false;
	if ($old_status !== $fields['status'] || !empty($permission_options) || count($privacy_target_album_ids) > 1) {
		$new_status = $fields['status'];
		foreach ($privacy_target_album_ids as $target_album_id) {
			$target_permission_options = $new_status === 'private'
				? $permission_options
				: ['mode' => 'public', 'shared_user_ids' => []];
			$sync_result = cpt_sync_album_visibility_permissions($target_album_id, $new_status, $target_permission_options, $owner_user_id, $debug);
			if ($sync_result === null) {
				return cpt_update_album_fail($snapshot, $album_id, 'user_access sync failed for album '.$target_album_id);
			}
			if (!cpt_album_access_matches($target_album_id, cpt_get_target_album_access_user_ids($target_album_id, $new_status, $target_permission_options, $owner_user_id))) {
				return cpt_update_album_fail($snapshot, $album_id, 'user_access verification failed for album '.$target_album_id);
			}
			if ($sync_result === true) {
				$permissions_changed = true;
			}
		}
	}

	if ($old_status !== $fields['status'] || $permissions_changed) {
		if (function_exists('trigger_notify')) { trigger_notify('CPT_after_privacy_change', $album_id); }
		// Defer cache invalidation: callers flush once after the complete save
		// (audit P4/S5 — one invalidation per request, not per album).
		cpt_mark_user_cache_dirty();
	}

	return true;
}

/**
	* Shared failure path for cpt_update_album(): restore the captured snapshot
	* and invalidate caches if restoration itself cannot be verified.
 */
function cpt_update_album_fail(?array $snapshot, int $album_id, string $reason): bool
{
	if ($snapshot !== null) {
		$restored = cpt_restore_album_update_snapshot($snapshot);
		if (!$restored) {
			cpt_log_message('critical', 'privacy update restore failed for album '.$album_id.': '.$reason);
			cpt_mark_user_cache_dirty();
			cpt_flush_user_cache_invalidation();
			return false;
		}
		cpt_log_message('error', 'privacy update aborted for album '.$album_id.': '.$reason.' (snapshot restored)');
		return false;
	}
	cpt_log_message('error', 'privacy update aborted for album '.$album_id.': '.$reason);
	return false;
}

/**
	* Capture the current root fields, descendant statuses, and user_access rows
	* before a multi-step privacy transition on non-transactional tables.
 */
function cpt_capture_album_update_snapshot(int $album_id, array $fields, array $privacy_target_album_ids): ?array
{
	$root_state = cpt_get_album_editable_state($album_id);
	if ($root_state === null) {
		return null;
	}

	$root_fields = [];
	foreach (array_keys($fields) as $field_name) {
		if (array_key_exists($field_name, $root_state)) {
			$root_fields[$field_name] = $root_state[$field_name];
		}
	}

	$statuses = [];
	$access_rows = [];
	foreach (array_values(array_unique(array_map('intval', $privacy_target_album_ids))) as $target_album_id) {
		if ($target_album_id <= 0) {
			continue;
		}
		$status = cpt_get_album_status($target_album_id);
		if ($status === null) {
			return null;
		}
		$statuses[$target_album_id] = $status;
		$access_rows[$target_album_id] = cpt_get_album_access_user_ids($target_album_id);
	}

	return [
		'album_id' => $album_id,
		'root_fields' => $root_fields,
		'statuses' => $statuses,
		'access_rows' => $access_rows,
	];
}

function cpt_restore_album_update_snapshot(array $snapshot): bool
{
	$album_id = (int) ($snapshot['album_id'] ?? 0);
	if ($album_id <= 0) {
		return false;
	}

	$root_fields = is_array($snapshot['root_fields'] ?? null) ? $snapshot['root_fields'] : [];
	if (!empty($root_fields)) {
		$restore_assignments = [];
		foreach ($root_fields as $field_name => $field_value) {
			$restore_assignments[] = $field_name."='".pwg_db_real_escape_string((string) $field_value)."'";
		}
		$restore_sql = 'UPDATE '.CATEGORIES_TABLE.' SET '.implode(',', $restore_assignments).' WHERE id='.(int) $album_id.' LIMIT 1';
		if (!pwg_query($restore_sql) || !cpt_album_fields_match($album_id, $root_fields)) {
			return false;
		}
	}

	$statuses = is_array($snapshot['statuses'] ?? null) ? $snapshot['statuses'] : [];
	foreach ($statuses as $target_album_id => $status) {
		$target_album_id = (int) $target_album_id;
		if ($target_album_id === $album_id && array_key_exists('status', $root_fields)) {
			continue;
		}

		$restore_status_sql = 'UPDATE '.CATEGORIES_TABLE." SET status='".pwg_db_real_escape_string((string) $status)."' WHERE id=".$target_album_id.' LIMIT 1';
		if (!pwg_query($restore_status_sql) || cpt_get_album_status($target_album_id) !== (string) $status) {
			return false;
		}
	}

	$access_rows = is_array($snapshot['access_rows'] ?? null) ? $snapshot['access_rows'] : [];
	foreach ($access_rows as $target_album_id => $user_ids) {
		if (!cpt_restore_album_access_user_ids((int) $target_album_id, is_array($user_ids) ? $user_ids : [])) {
			return false;
		}
	}

	return cpt_verify_album_update_snapshot($snapshot);
}

function cpt_verify_album_update_snapshot(array $snapshot): bool
{
	$album_id = (int) ($snapshot['album_id'] ?? 0);
	if ($album_id <= 0) {
		return false;
	}

	$root_fields = is_array($snapshot['root_fields'] ?? null) ? $snapshot['root_fields'] : [];
	if (!cpt_album_fields_match($album_id, $root_fields)) {
		return false;
	}

	foreach (($snapshot['statuses'] ?? []) as $target_album_id => $status) {
		if (cpt_get_album_status((int) $target_album_id) !== (string) $status) {
			return false;
		}
	}

	foreach (($snapshot['access_rows'] ?? []) as $target_album_id => $user_ids) {
		if (!cpt_album_access_matches((int) $target_album_id, is_array($user_ids) ? $user_ids : [])) {
			return false;
		}
	}

	return true;
}

function cpt_restore_album_access_user_ids(int $album_id, array $user_ids): bool
{
	if (!pwg_query('DELETE FROM '.USER_ACCESS_TABLE.' WHERE cat_id='.(int) $album_id)) {
		return false;
	}

	$user_ids = array_values(array_unique(array_map('intval', $user_ids)));
	sort($user_ids);
	if (!empty($user_ids)) {
		$insert_values = [];
		foreach ($user_ids as $user_id) {
			$insert_values[] = '('.$user_id.','.(int) $album_id.')';
		}
		if (!pwg_query('INSERT INTO '.USER_ACCESS_TABLE.' (user_id, cat_id) VALUES '.implode(',', $insert_values))) {
			return false;
		}
	}

	return cpt_album_access_matches($album_id, $user_ids);
}

function cpt_album_fields_match(int $album_id, array $expected_fields): bool
{
	if (empty($expected_fields)) {
		return true;
	}

	$current = cpt_get_album_editable_state($album_id);
	if ($current === null) {
		return false;
	}

	foreach ($expected_fields as $field_name => $expected_value) {
		if (!array_key_exists($field_name, $current) || (string) $current[$field_name] !== (string) $expected_value) {
			return false;
		}
	}

	return true;
}

function cpt_get_album_editable_state(int $album_id): ?array
{
	$result = pwg_query('SELECT id, name, comment, status FROM '.CATEGORIES_TABLE.' WHERE id='.(int) $album_id.' LIMIT 1');
	if (!$result) {
		return null;
	}

	$row = pwg_db_fetch_assoc($result);
	if (!$row) {
		return null;
	}

	return [
		'name' => (string) ($row['name'] ?? ''),
		'comment' => (string) ($row['comment'] ?? ''),
		'status' => (string) ($row['status'] ?? ''),
	];
}

function cpt_album_access_matches(int $album_id, array $expected_user_ids): bool
{
	$expected_user_ids = array_values(array_unique(array_map('intval', $expected_user_ids)));
	sort($expected_user_ids);

	return cpt_get_album_access_user_ids($album_id) === $expected_user_ids;
}

function cpt_log_message(string $level, string $message): void
{
	$normalized_level = strtoupper(trim($level));
	if (!isset($GLOBALS['__cpt_log_messages']) || !is_array($GLOBALS['__cpt_log_messages'])) {
		$GLOBALS['__cpt_log_messages'] = [];
	}
	$GLOBALS['__cpt_log_messages'][] = [
		'level' => $normalized_level,
		'message' => $message,
	];
	error_log('[core_privacy_toggle]['.$normalized_level.'] '.$message);
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
 * Mark the per-user permission cache as needing invalidation.
 *
 * cpt_update_album() calls this instead of wiping the cache directly; the
 * caller that owns the save loop flushes once via
 * cpt_flush_user_cache_invalidation() so a multi-album submit performs a
 * single invalidation instead of one gallery-wide wipe per album (P4/S5).
 */
function cpt_mark_user_cache_dirty(): void
{
	$GLOBALS['__cpt_user_cache_dirty'] = true;
}

/**
 * Flush a pending cache invalidation (no-op when nothing changed).
 * Call once after the complete save, never inside per-album loops.
 */
function cpt_flush_user_cache_invalidation(): void
{
	if (empty($GLOBALS['__cpt_user_cache_dirty'])) {
		return;
	}
	$GLOBALS['__cpt_user_cache_dirty'] = false;
	cpt_invalidate_user_cache();
}

/**
 * Flag every user's cached visibility for lazy recomputation.
 *
 * A public<->private change affects the cached visibility of every normal
 * user, so per-user targeting is insufficient here. Uses core
 * invalidate_user_cache(false) when loaded (admin context): it sets
 * need_update='true' so each user's cache rebuilds lazily on their next
 * request. NEVER call it with defaults — $full=true TRUNCATEs both
 * user_cache and user_cache_categories. The core function lives in
 * admin/include/functions.php and is undefined on front-end paths (profile
 * page, quick toggle, web service), so issue the equivalent SQL there.
 */
function cpt_invalidate_user_cache(): void
{
	if (function_exists('invalidate_user_cache')) {
		invalidate_user_cache(false);
		return;
	}
	global $prefixeTable;
	$table = defined('USER_CACHE_TABLE') ? USER_CACHE_TABLE : $prefixeTable.'user_cache';
	pwg_query('UPDATE '.$table." SET need_update = 'true'");
	if (function_exists('trigger_notify')) {
		trigger_notify('invalidate_user_cache', false);
	}
}

