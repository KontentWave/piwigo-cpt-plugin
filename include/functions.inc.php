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
		cpt_handle_album_form($_POST['cpt_album'], $user_id);
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
		// Add hidden marker server-side (non-JS fallback) so submission still detectable
		$partial_with_marker = $partial . '<input type="hidden" name="cpt_album_marker" value="1" />';
		cpt_inject_album_manager_assets($partial_with_marker);
}

/**
 * Count albums owned by user. Ownership relies on Community plugin adding user_id to categories.
 */
function cpt_count_albums_owned_by(int $user_id): int
{
	if (!cpt_has_album_ownership_column()) { return 0; }
	$query = 'SELECT COUNT(id) AS cnt FROM '.CATEGORIES_TABLE.' WHERE user_id = '.(int)$user_id; // relies on Community plugin
	$result = pwg_query($query);
	if (!$result) { return 0; }
	$row = pwg_db_fetch_assoc($result);
	return (int)($row['cnt'] ?? 0);
}

/**
 * Fetch basic album metadata for owner editing.
 */
function cpt_fetch_albums_owned_by(int $user_id): array
{
	if (!cpt_has_album_ownership_column()) { return []; }
	$albums = [];
	$query = 'SELECT id, name, comment, status FROM '.CATEGORIES_TABLE.' WHERE user_id = '.(int)$user_id.' ORDER BY id DESC';
	$result = pwg_query($query);
	if (!$result) { return $albums; }
	while ($row = pwg_db_fetch_assoc($result)) {
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
	$inline = 'window.CPT_ALBUM_HTML = '.$json.';window.CPT_I18N_MY_GALLERIES='.$legend.';window.CPT_ASSETS_READY=1;';

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
 * Check album ownership using Community plugin's user_id field or fallback heuristic.
 */
function cpt_album_is_owned_by(int $album_id, int $user_id): bool
{
	if (cpt_has_album_ownership_column()) {
		$query = 'SELECT 1 FROM '.CATEGORIES_TABLE.' WHERE id='.(int)$album_id.' AND user_id='.(int)$user_id.' LIMIT 1';
		$result = pwg_query($query);
		return (bool)pwg_db_fetch_row($result);
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
				// Need owner id: try categories.user_id if exists, else attempt fallback detection via first image added_by
				$owner_id = null;
				if (cpt_has_album_ownership_column()) {
					$resO = pwg_query('SELECT user_id FROM '.CATEGORIES_TABLE.' WHERE id='.(int)$album_id.' LIMIT 1');
					if ($resO) { $rowO = pwg_db_fetch_assoc($resO); $owner_id = isset($rowO['user_id']) ? (int)$rowO['user_id'] : null; }
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
 * Detect if Community plugin ownership column exists on categories table.
 */
function cpt_has_album_ownership_column(): bool
{
	static $cache = null;
	// Test harness override (non-production). If set, bypass detection cost & static cache.
	if (isset($GLOBALS['__cpt_force_ownership_column'])) {
		return (bool)$GLOBALS['__cpt_force_ownership_column'];
	}
	if ($cache !== null) { return $cache; }
	$cache = false;
	$res = pwg_query('DESC '.CATEGORIES_TABLE);
	if ($res) {
		while ($row = pwg_db_fetch_assoc($res)) {
			if (isset($row['Field']) && $row['Field'] === 'user_id') { $cache = true; break; }
		}
	}
	return $cache;
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

