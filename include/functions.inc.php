<?php
defined('CORE_PRIVACY_TOGGLE_PATH') or die('Hacking attempt!');

/**
 * Set up User Control Panel album management (progressive enhancement).
 * Early exit if user owns no albums. Exposes template partial to JS and processes POST.
 */
function cpt_setup_ucp_tabs(): void
{
	global $user, $template;

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
			global $page; $page['infos'][] = l10n('CPT: Limited mode enabled — only albums exclusively containing your photos are listed.');
		}
	}
	if (empty($albums)) { // defensive: if fetch fails, skip enhancement
		return;
	}

		$template->assign('UCP_ALBUMS', $albums);

		// Render partial (will be injected by JS; contains only inner controls)
		// Use set_filename + parse instead of fetch (fetch not available in this env)
		$template->set_filename('cpt_ucp_album_manager', realpath(CORE_PRIVACY_TOGGLE_PATH.'template/ucp_album_manager.tpl'));
		$partial = $template->parse('cpt_ucp_album_manager', true);
		cpt_attach_album_manager_to_profile();
		cpt_inject_album_manager_assets($partial);
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
		cpt_update_album($album_id, array('status' => $target_status));
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
	cpt_inject_album_page_toggle_assets($html);
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

/**
 * Expose the album-page shortcut for themes that skip PLUGIN_INDEX_CONTENT_BEGIN.
 */
function cpt_inject_album_page_toggle_assets(string $html_partial): void
{
	$json = json_encode($html_partial, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT);
	$inline = 'window.CPT_ALBUM_PAGE_HTML = '.$json.';window.CPT_ALBUM_PAGE_ASSETS_READY=1;';

	$css_path = CORE_PRIVACY_TOGGLE_PATH.'template/album_page_toggle.css';
	$css_ver = '';
	if (file_exists($css_path)) { $css_ver = '?v='.filemtime($css_path); }
	$path = CORE_PRIVACY_TOGGLE_PATH.'js/album_page_toggle.js';
	$ver = '';
	if (file_exists($path)) { $ver = '?v='.filemtime($path); }

	global $template;
	if (method_exists($template, 'append')) {
		if (file_exists($css_path)) {
			$template->append('head_elements', '<link rel="stylesheet" href="'.htmlspecialchars($css_path.$css_ver, ENT_QUOTES).'">');
		}
		$template->append('head_elements', '<script>'.$inline.'</script>');
		if (file_exists($path)) {
			$template->append('head_elements', '<script src="'.htmlspecialchars($path.$ver, ENT_QUOTES).'"></script>');
		}
		$template->append('footer_msgs', '<script>window.CPT_ALBUM_PAGE_ASSETS_READY||(function(){'.$inline.'})();</script>');
		if (file_exists($path)) {
			$template->append('footer_msgs', '<script src="'.htmlspecialchars($path.$ver, ENT_QUOTES).'"></script>');
		}
	}
}

/**
 * Attach the album manager as a native Piwigo profile plugin block.
 */
function cpt_attach_album_manager_to_profile(): void
{
	global $template;
	$template_path = realpath(CORE_PRIVACY_TOGGLE_PATH.'template/ucp_album_manager.tpl');
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
		'name' => l10n('My Galleries'),
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

/**
 * Fetch basic album metadata for owner editing.
 */
function cpt_fetch_albums_owned_by(int $user_id): array
{
	$ownership_column = cpt_get_album_ownership_column();
	if ($ownership_column === null) { return []; }
	$albums = [];
	$album_ids = [];
	$query = 'SELECT id, name, comment, status FROM '.CATEGORIES_TABLE.' WHERE '.$ownership_column.' = '.(int)$user_id.' ORDER BY id DESC';
	$result = pwg_query($query);
	if (!$result) { return $albums; }
	while ($row = pwg_db_fetch_assoc($result)) {
		$albums[] = [
			'id' => (int)$row['id'],
			'name' => $row['name'],
			'comment' => $row['comment'],
			'status' => $row['status'],
		];
		$album_ids[(int)$row['id']] = true;
	}

	foreach (cpt_fetch_albums_contributed_exclusive($user_id) as $album) {
		$album_id = (int) $album['id'];
		if (isset($album_ids[$album_id])) {
			continue;
		}

		if (cpt_get_album_explicit_owner_id($album_id) !== null) {
			continue;
		}

		$albums[] = $album;
		$album_ids[$album_id] = true;
	}

	return $albums;
}

/**
 * Fallback: count albums that exclusively contain photos added by this user.
 * Safe alternative when ownership column does not exist.
 */
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
		$albums[] = [
			'id' => (int)$row['id'],
			'name' => $row['name'],
			'comment' => $row['comment'],
			'status' => $row['status'],
		];
	}
	return $albums;
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

	$path = CORE_PRIVACY_TOGGLE_PATH.'js/ucp_tabs.js';
	$ver = '';
	if (file_exists($path)) { $ver = '?v='.filemtime($path); }
	// Fallback: append to head elements (older themes still honor this)
	global $template;
		if (method_exists($template, 'append')) {
			// Head injection
			$template->append('head_elements', '<script>'.$inline.'</script>');
			if (file_exists($path)) {
				$template->append('head_elements', '<script src="'.htmlspecialchars($path.$ver, ENT_QUOTES).'"></script>');
			}
			// Footer fallback injection (safe due to guard var)
			$template->append('footer_msgs', '<script>window.CPT_ASSETS_READY||(function(){'.$inline.'})();</script>');
			if (file_exists($path)) {
				$template->append('footer_msgs', '<script src="'.htmlspecialchars($path.$ver, ENT_QUOTES).'"></script>');
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
		'core_privacy_toggle.albums.update',
		'cpt_ws_update_albums',
		array(
			'payload' => array(),
			'pwg_token' => array(),
		),
		'Update owned albums from AJAX-driven profile pages.'
	);
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
	if ($debug_admin) { global $page; $page['infos'][] = '[CPT debug] album '.$album_id.' ownership check => '.($owns?'true':'false'); }
        if (!$owns) { continue; }

        $updates = [];
        if (isset($fields['name'])) {
            $name = trim($fields['name']);
            if ($name !== '') { $updates['name'] = pwg_db_real_escape_string($name); }
        }
        if (isset($fields['comment'])) {
            $comment = trim($fields['comment']);
            $updates['comment'] = pwg_db_real_escape_string($comment);
        }
        $updates['status'] = array_key_exists('private', $fields) ? 'private' : 'public';

        if (!empty($updates)) {
			if ($debug_admin) { global $page; $page['infos'][] = '[CPT debug] updating album '.$album_id.' fields: '.implode(',', array_keys($updates)); }
            cpt_update_album($album_id, $updates, $debug_admin);
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
	$ownership_column = cpt_get_album_ownership_column();
	if ($ownership_column !== null) {
		$query = 'SELECT 1 FROM '.CATEGORIES_TABLE.' WHERE id='.(int)$album_id.' AND '.$ownership_column.'='.(int)$user_id.' LIMIT 1';
		$result = pwg_query($query);
		if ((bool) pwg_db_fetch_row($result)) {
			return true;
		}

		$explicit_owner_id = cpt_get_album_explicit_owner_id($album_id);
		if ($explicit_owner_id !== null) {
			return false;
		}
	}
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

/**
 * Returns the explicit owner id when the ownership column exists and is set.
 */
function cpt_get_album_explicit_owner_id(int $album_id): ?int
{
	$ownership_column = cpt_get_album_ownership_column();
	if ($ownership_column === null) {
		return null;
	}

	$res = pwg_query('SELECT '.$ownership_column.' AS owner_id FROM '.CATEGORIES_TABLE.' WHERE id='.(int) $album_id.' LIMIT 1');
	if (!$res) {
		return null;
	}

	$row = pwg_db_fetch_assoc($res);
	if (!$row || !isset($row['owner_id']) || $row['owner_id'] === null || $row['owner_id'] === '') {
		return null;
	}

	return (int) $row['owner_id'];
}

/**
 * Low-level update helper. Uses simple dynamic SQL since Piwigo core often builds strings; ensured safe via escaping above.
 */
function cpt_update_album(int $album_id, array $fields, bool $debug=false): void
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
		if (isset($fields['status']) && $old_status !== $fields['status']) {
			// One-shot flag for other sessions to re-evaluate permissions on next request
			$_SESSION['cpt_permissions_changed'] = 1;
			$new_status = $fields['status'];
			if ($new_status === 'private') {
				// Remove any existing explicit permissions first to avoid duplication
				pwg_query('DELETE FROM '.USER_ACCESS_TABLE.' WHERE cat_id='.(int)$album_id);
				// Grant album owner + admin (user_id=1) access
				// Need owner id: try the detected ownership column first, else attempt fallback detection via first image added_by
				$owner_id = null;
				$ownership_column = cpt_get_album_ownership_column();
				if ($ownership_column !== null) {
					$resO = pwg_query('SELECT '.$ownership_column.' AS owner_id FROM '.CATEGORIES_TABLE.' WHERE id='.(int)$album_id.' LIMIT 1');
					if ($resO) { $rowO = pwg_db_fetch_assoc($resO); $owner_id = isset($rowO['owner_id']) ? (int)$rowO['owner_id'] : null; }
				}
				if ($owner_id === null) {
					$resImg = pwg_query('SELECT i.added_by FROM '.IMAGE_CATEGORY_TABLE.' ic INNER JOIN '.IMAGES_TABLE.' i ON i.id=ic.image_id WHERE ic.category_id='.(int)$album_id.' ORDER BY i.id ASC LIMIT 1');
					if ($resImg) { $rowI = pwg_db_fetch_assoc($resImg); if ($rowI) { $owner_id = (int)$rowI['added_by']; } }
				}
				$insValues = [];
				$insValues[] = '(1,'.(int)$album_id.')'; // admin
				if ($owner_id !== null && $owner_id !== 1) { $insValues[] = '('.(int)$owner_id.','.(int)$album_id.')'; }
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

