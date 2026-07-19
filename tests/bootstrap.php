<?php
// Minimal PHPUnit bootstrap for Core Privacy Toggle plugin tests
// Provides lightweight mocks of Piwigo globals & loads plugin core logic.

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (!defined('PHPWG_ROOT_PATH')) {
    // Point to repository root (adjust if test runner executed elsewhere)
    define('PHPWG_ROOT_PATH', realpath(__DIR__ . '/../../..') . DIRECTORY_SEPARATOR);
}

// Define essential path constants mimicking Piwigo environment
if (!defined('PWG_LOCAL_DIR')) { define('PWG_LOCAL_DIR', 'local/'); }
if (!defined('PHPWG_PLUGINS_PATH')) { define('PHPWG_PLUGINS_PATH', PHPWG_ROOT_PATH . 'plugins/'); }

// Table prefix (empty for tests by default)
$prefixeTable = 'piwigo_'; // mimic typical prefix to exercise concatenation

// Core table name constants expected by plugin functions
if (!defined('CATEGORIES_TABLE')) { define('CATEGORIES_TABLE', $prefixeTable.'categories'); }
if (!defined('IMAGES_TABLE')) { define('IMAGES_TABLE', $prefixeTable.'images'); }
if (!defined('IMAGE_CATEGORY_TABLE')) { define('IMAGE_CATEGORY_TABLE', $prefixeTable.'image_category'); }
if (!defined('USER_ACCESS_TABLE')) { define('USER_ACCESS_TABLE', $prefixeTable.'user_access'); }
if (!defined('USERS_TABLE')) { define('USERS_TABLE', $prefixeTable.'users'); }
if (!defined('USER_CACHE_CATEGORIES_TABLE')) { define('USER_CACHE_CATEGORIES_TABLE', $prefixeTable.'user_cache_categories'); }
if (!defined('CPT_OWNER_PROFILE_TABLE')) { define('CPT_OWNER_PROFILE_TABLE', $prefixeTable.'cpt_owner_profile'); }
if (!defined('CPT_MUNICIPALITY_TABLE')) { define('CPT_MUNICIPALITY_TABLE', $prefixeTable.'cpt_municipality'); }

// Minimal globals
global $conf, $user, $page;
$conf = $conf ?? [];
$conf['guest_id'] = $conf['guest_id'] ?? 2;
$conf['user_fields'] = $conf['user_fields'] ?? [
    'id' => 'id',
    'username' => 'username',
    'email' => 'email',
];
$user = $user ?? [];
$page = $page ?? ['infos'=>[], 'errors'=>[]];

// Stub translation simply echoes key
if (!function_exists('l10n')) { function l10n($k){ return $k; } }
if (!function_exists('get_themeconf')) {
    function get_themeconf($key) {
        if ($key === 'id') {
            return $GLOBALS['__cpt_test_theme_id'] ?? 'default';
        }

        return null;
    }
}

// Session var check stub
if (!function_exists('pwg_get_session_var')) { function pwg_get_session_var($k){ return $_SESSION[$k] ?? null; } }
if (!function_exists('pwg_set_session_var')) { function pwg_set_session_var($k,$v){ $_SESSION[$k]=$v; } }
if (!function_exists('get_pwg_token')) { function get_pwg_token(){ return $GLOBALS['__cpt_test_pwg_token'] ?? 'test-token'; } }
if (!function_exists('is_a_guest')) { function is_a_guest(){ global $user; return !empty($user['is_guest']); } }
if (!class_exists('PwgError')) {
    class PwgError {
        public int $code;
        public string $message;
        public function __construct(int $code, string $message){ $this->code = $code; $this->message = $message; }
    }
}

// Template stub with minimal API used by plugin
if (!class_exists('CptTestTemplate')) {
    class CptTestTemplate {
        private array $vars = [];
        public array $head_elements = [];
        public array $footer_msgs = [];
        public function assign($k,$v){ $this->vars[$k]=$v; }
        public function set_filename($handle,$path){ $this->vars['__tpl_path__']=$path; }
        public function get_template_vars($key){ return $this->vars[$key] ?? null; }
        public function parse($handle,$return){
            $templatePath = basename((string) ($this->vars['__tpl_path__'] ?? ''));
            if ($templatePath === 'owner_profile_table.tpl') {
                $rows = $this->vars['CPT_OWNER_PROFILE_ROWS'] ?? [];
                $contacts = $this->vars['CPT_OWNER_PROFILE_CONTACTS'] ?? [];
                $availability = $this->vars['CPT_OWNER_PROFILE_AVAILABILITY'] ?? [];
                $html = '<div class="cpt-owner-profile-public">';
                if (!empty($rows)) {
                    $html .= '<table class="cpt-owner-profile-table"><tbody>';
                    foreach ($rows as $row) {
                        $html .= '<tr><th scope="row">'.htmlspecialchars((string) ($row['label'] ?? '')).'</th><td>'.htmlspecialchars((string) ($row['value_text'] ?? '')).'</td></tr>';
                    }
                    $html .= '</tbody></table>';
                }
                if (!empty($contacts)) {
                    $html .= '<div class="cpt-owner-profile-contacts">';
                    foreach ($contacts as $contact) {
                        $html .= '<a class="cpt-owner-profile-contact-link" href="'.htmlspecialchars((string) ($contact['href'] ?? '')).'">'.htmlspecialchars((string) ($contact['label'] ?? '')).'</a>';
                    }
                    $html .= '<div>'.htmlspecialchars((string) ($contacts[0]['display_value'] ?? '')).'</div></div>';
                }
                if (!empty($availability)) {
                    $html .= '<div class="cpt-owner-profile-availability">';
                    foreach ($availability as $row) {
                        $html .= '<div class="cpt-owner-profile-availability-row"><span>'.htmlspecialchars((string) ($row['label'] ?? '')).'</span><span>'.htmlspecialchars((string) ($row['value_text'] ?? '')).'</span></div>';
                    }
                    $html .= '</div>';
                }
                return $html.'</div>';
            }

            // Return a deterministic fake HTML using assigned albums
            $html = '';
            $albums = $this->vars['UCP_ALBUMS'] ?? [];
            foreach ($albums as $a){
                $html .= '<div data-album="'.$a['id'].'">'.htmlspecialchars($a['name']).'</div>';
            }
            return $html ?: '<div>No Albums</div>';
        }
        public function append($slot,$value){
            if ($slot==='head_elements') { $this->head_elements[]=$value; }
            if ($slot==='footer_msgs') { $this->footer_msgs[]=$value; }
            if ($slot!=='head_elements' && $slot!=='footer_msgs') {
                if (!isset($this->vars[$slot]) || !is_array($this->vars[$slot])) {
                    $this->vars[$slot] = [];
                }
                $this->vars[$slot][] = $value;
            }
        }
    }
}
if (!isset($template)) { $template = new CptTestTemplate(); }

// Database emulation (in-memory arrays) -------------------------------------
$GLOBALS['__cpt_db'] = [
    'categories' => [],
    'images' => [],
    'image_category' => [],
    'user_access' => [],
    'user_cache' => [],
    'user_cache_categories' => [],
    'users' => [],
    'owner_profile' => [],
];

function cpt_test_reset_db(){
    $GLOBALS['__cpt_db'] = [
        'categories' => [],
        'images' => [],
        'image_category' => [],
        'user_access' => [],
        'user_cache' => [],
        'user_cache_categories' => [],
        'users' => [],
        'owner_profile' => [],
    ];
}

// Simplistic auto increment helpers
function cpt_next_id($table){
    static $counters = [];
    if (!isset($counters[$table])) { $counters[$table]=1; }
    return $counters[$table]++;
}

// Basic DB API shims used by plugin -----------------------------------------
function pwg_query($sql){
    // Very naive SQL recognizer tailored to queries constructed in functions.inc.php
    $sqlTrim = trim($sql);
    $GLOBALS['__last_query'] = $sqlTrim;
    global $prefixeTable;
    // Failure injection for transaction/rollback tests: any statement matching
    // the regex in __cpt_test_fail_sql_pattern reports failure.
    if (!empty($GLOBALS['__cpt_test_fail_sql_pattern']) && preg_match($GLOBALS['__cpt_test_fail_sql_pattern'], $sqlTrim)) {
        return false;
    }
    // Transaction simulation with snapshot semantics
    if (preg_match('/^(BEGIN|START TRANSACTION)$/i', $sqlTrim)) {
        $GLOBALS['__cpt_db_txn_snapshot'] = $GLOBALS['__cpt_db'];
        return true;
    }
    if (strcasecmp($sqlTrim, 'COMMIT') === 0) {
        unset($GLOBALS['__cpt_db_txn_snapshot']);
        return true;
    }
    if (strcasecmp($sqlTrim, 'ROLLBACK') === 0) {
        if (isset($GLOBALS['__cpt_db_txn_snapshot'])) {
            $GLOBALS['__cpt_db'] = $GLOBALS['__cpt_db_txn_snapshot'];
            unset($GLOBALS['__cpt_db_txn_snapshot']);
        }
        return true;
    }
    // SELECT COUNT(id) FROM categories WHERE <ownership_column> = X
    if (preg_match('/SELECT COUNT\(id\) AS cnt FROM '.CATEGORIES_TABLE.' WHERE ([a-z_]+) = (\d+)/',$sqlTrim,$m)){
        $column = $m[1]; $uid = (int)$m[2]; $cnt=0; foreach($GLOBALS['__cpt_db']['categories'] as $c){ if(($c[$column]??null)===$uid){$cnt++;}} return new ArrayIterator([[ 'cnt'=>$cnt ]]);
    }
    if (preg_match('/SELECT id, name, comment, status FROM '.CATEGORIES_TABLE.' WHERE ([a-z_]+) = (\d+)/',$sqlTrim,$m)){
        $column=$m[1]; $uid=(int)$m[2]; $rows=[]; foreach(array_reverse($GLOBALS['__cpt_db']['categories']) as $c){ if(($c[$column]??null)===$uid){ $rows[]=$c; } } return new ArrayIterator($rows);
    }
    if (preg_match('/SELECT id, name, comment, status FROM '.CATEGORIES_TABLE.' WHERE id=(\d+)/',$sqlTrim,$m)){
        $id=(int)$m[1]; foreach($GLOBALS['__cpt_db']['categories'] as $c){ if($c['id']===$id){ return new ArrayIterator([['id'=>$c['id'],'name'=>$c['name'] ?? null,'comment'=>$c['comment'] ?? null,'status'=>$c['status'] ?? null]]); }} return new ArrayIterator([]);
    }
    if (preg_match('/SELECT id, name, comment, status FROM '.CATEGORIES_TABLE.' ORDER BY id ASC/',$sqlTrim)){
        $rows = $GLOBALS['__cpt_db']['categories'];
        usort($rows, fn($a, $b) => ($a['id'] <=> $b['id']));
        return new ArrayIterator($rows);
    }
    if (preg_match('/SELECT id, uppercats FROM '.CATEGORIES_TABLE.' ORDER BY id ASC/',$sqlTrim)){
        $rows = [];
        foreach ($GLOBALS['__cpt_db']['categories'] as $c) {
            $rows[] = ['id' => $c['id'], 'uppercats' => $c['uppercats'] ?? null];
        }
        usort($rows, fn($a, $b) => ($a['id'] <=> $b['id']));
        return new ArrayIterator($rows);
    }
    if (preg_match('/DESC '.CATEGORIES_TABLE.'/',$sqlTrim)){
        $rows = [];
        foreach (['community_user', 'user_id'] as $column) {
            foreach($GLOBALS['__cpt_db']['categories'] as $c){
                if(array_key_exists($column,$c)){
                    $rows[]=['Field'=>$column];
                    break;
                }
            }
        }
        return new ArrayIterator($rows);
    }
    if (preg_match('/SELECT 1 FROM '.CATEGORIES_TABLE.' WHERE id=(\d+) AND ([a-z_]+)=(\d+)/',$sqlTrim,$m)){
        $id=(int)$m[1];$column=$m[2];$uid=(int)$m[3];foreach($GLOBALS['__cpt_db']['categories'] as $c){ if($c['id']===$id && ($c[$column]??null)===$uid){ return new ArrayIterator([[1]]); }} return new ArrayIterator([]);
    }
    // Fallback ownership single-album contributor query (used in cpt_album_is_owned_by when no ownership column):
    if (preg_match('/SELECT COUNT\(DISTINCT i.added_by\) AS contribs, MIN\(i.added_by\) AS min_by\s+FROM '.IMAGE_CATEGORY_TABLE.' ic\s+INNER JOIN '.IMAGES_TABLE.' i ON i.id = ic.image_id\s+WHERE ic.category_id = (\d+)/', $sqlTrim, $m)) {
        $cid = (int)$m[1];
        $added = [];
        foreach($GLOBALS['__cpt_db']['image_category'] as $ic){
            if($ic['category_id']===$cid){
                foreach($GLOBALS['__cpt_db']['images'] as $img){ if($img['id']===$ic['image_id']){ $added[$img['added_by']]=true; } }
            }
        }
        if (empty($added)) { return new ArrayIterator([[ 'contribs'=>0, 'min_by'=>null ]]); }
        $keys = array_keys($added); sort($keys);
        return new ArrayIterator([[ 'contribs'=>count($added), 'min_by'=>$keys[0] ]]);
    }
    // Generic fallback ownership pattern (in case formatting differs)
    if (str_contains($sqlTrim, 'COUNT(DISTINCT i.added_by) AS contribs') && str_contains($sqlTrim, 'WHERE ic.category_id =')) {
        if (preg_match('/WHERE ic.category_id = (\d+)/', $sqlTrim, $m)) {
            $cid = (int)$m[1];
            $added = [];
            foreach($GLOBALS['__cpt_db']['image_category'] as $ic){
                if($ic['category_id']===$cid){
                    foreach($GLOBALS['__cpt_db']['images'] as $img){ if($img['id']===$ic['image_id']){ $added[$img['added_by']]=true; } }
                }
            }
            if (empty($added)) { return new ArrayIterator([[ 'contribs'=>0, 'min_by'=>null ]]); }
            $keys = array_keys($added); sort($keys);
            return new ArrayIterator([[ 'contribs'=>count($added), 'min_by'=>$keys[0] ]]);
        }
    }
    if (preg_match('/SELECT status FROM '.CATEGORIES_TABLE.' WHERE id=(\d+)/',$sqlTrim,$m)){
        $id=(int)$m[1]; foreach($GLOBALS['__cpt_db']['categories'] as $c){ if($c['id']===$id){ return new ArrayIterator([[ 'status'=>$c['status'] ]]); }} return new ArrayIterator([]);
    }
    if (preg_match('/SELECT representative_picture_id FROM '.CATEGORIES_TABLE.' WHERE id=(\d+)/', $sqlTrim, $m)) {
        $id = (int) $m[1];
        foreach ($GLOBALS['__cpt_db']['categories'] as $c) {
            if ($c['id'] === $id) {
                return new ArrayIterator([[ 'representative_picture_id' => $c['representative_picture_id'] ?? null ]]);
            }
        }
        return new ArrayIterator([]);
    }
    if (preg_match('/UPDATE '.CATEGORIES_TABLE.' SET (.+) WHERE id=(\d+)/s',$sqlTrim,$m)){
        $assignments = $m[1]; $id=(int)$m[2];
        foreach($GLOBALS['__cpt_db']['categories'] as &$c){ if($c['id']===$id){
            foreach(explode(',', $assignments) as $pair){
                if(strpos($pair,'=')!==false){ list($col,$val)=explode('=',$pair,2); $col=trim($col); $val=trim($val, "' "); $c[$col]=$val; }
            }
        }} unset($c); return true;
    }
    if (preg_match('/DELETE FROM '.USER_ACCESS_TABLE.' WHERE cat_id=(\d+)/',$sqlTrim,$m)){
        $cid=(int)$m[1]; $GLOBALS['__cpt_db']['user_access']=array_values(array_filter($GLOBALS['__cpt_db']['user_access'],fn($r)=>$r['cat_id']!==$cid)); return true;
    }
    if (preg_match('/INSERT INTO '.USER_ACCESS_TABLE.' \(user_id, cat_id\) VALUES (.+)/',$sqlTrim,$m)){
        $vals = $m[1]; foreach(explode('),',$vals) as $tuple){ $tuple=trim($tuple,' ()'); if($tuple==='') continue; list($uid,$cid)=array_map('intval', explode(',',$tuple)); $GLOBALS['__cpt_db']['user_access'][]=['user_id'=>$uid,'cat_id'=>$cid]; }
        return true;
    }
    if (preg_match('/SELECT ([a-z_]+) AS owner_id FROM '.CATEGORIES_TABLE.' WHERE id=(\d+)/',$sqlTrim,$m)){
        $column=$m[1]; $id=(int)$m[2]; foreach($GLOBALS['__cpt_db']['categories'] as $c){ if($c['id']===$id){ return new ArrayIterator([[ 'owner_id'=>$c[$column] ?? null ]]); }} return new ArrayIterator([]);
    }
    if (preg_match('/SELECT id, name, file, path, representative_ext FROM '.IMAGES_TABLE.' WHERE id=(\d+)/', $sqlTrim, $m)) {
        $id = (int) $m[1];
        foreach ($GLOBALS['__cpt_db']['images'] as $img) {
            if ($img['id'] === $id) {
                return new ArrayIterator([[$img]]);
            }
        }
        return new ArrayIterator([]);
    }
    if (preg_match('/SELECT i.id, i.name, i.file, i.path, i.representative_ext\s+FROM '.IMAGES_TABLE.' i\s+INNER JOIN '.IMAGE_CATEGORY_TABLE.' ic ON ic.image_id = i.id\s+WHERE ic.category_id = (\d+)\s+ORDER BY i.id ASC/s', $sqlTrim, $m)) {
        $cid = (int) $m[1];
        $rows = [];
        foreach ($GLOBALS['__cpt_db']['image_category'] as $ic) {
            if ($ic['category_id'] !== $cid) {
                continue;
            }
            foreach ($GLOBALS['__cpt_db']['images'] as $img) {
                if ($img['id'] === $ic['image_id']) {
                    $rows[] = [
                        'id' => $img['id'],
                        'name' => $img['name'] ?? null,
                        'file' => $img['file'] ?? null,
                        'path' => $img['path'] ?? null,
                        'representative_ext' => $img['representative_ext'] ?? null,
                    ];
                }
            }
        }
        usort($rows, fn($a, $b) => $a['id'] <=> $b['id']);
        return new ArrayIterator($rows);
    }
    if (preg_match('/SELECT COUNT\(\*\) AS cnt FROM '.IMAGE_CATEGORY_TABLE.' WHERE category_id=(\d+) AND image_id=(\d+)/', $sqlTrim, $m)) {
        $cid = (int) $m[1];
        $iid = (int) $m[2];
        $count = 0;
        foreach ($GLOBALS['__cpt_db']['image_category'] as $ic) {
            if ($ic['category_id'] === $cid && $ic['image_id'] === $iid) {
                $count++;
            }
        }
        return new ArrayIterator([[ 'cnt' => $count ]]);
    }
    if (preg_match('/SELECT uppercats, id_uppercat FROM '.CATEGORIES_TABLE.' WHERE id=(\d+)/', $sqlTrim, $m)) {
        $id = (int) $m[1];
        foreach ($GLOBALS['__cpt_db']['categories'] as $c) {
            if ($c['id'] === $id) {
                return new ArrayIterator([[
                    'uppercats' => $c['uppercats'] ?? null,
                    'id_uppercat' => $c['id_uppercat'] ?? null,
                ]]);
            }
        }
        return new ArrayIterator([]);
    }
    if (preg_match('/SELECT id_uppercat FROM '.CATEGORIES_TABLE.' WHERE id=(\d+)/', $sqlTrim, $m)) {
        $id = (int) $m[1];
        foreach ($GLOBALS['__cpt_db']['categories'] as $c) {
            if ($c['id'] === $id) {
                return new ArrayIterator([[ 'id_uppercat' => $c['id_uppercat'] ?? null ]]);
            }
        }
        return new ArrayIterator([]);
    }
    if (preg_match('/SELECT user_id FROM '.USER_ACCESS_TABLE.' WHERE cat_id=(\d+)/', $sqlTrim, $m)) {
        $cid = (int)$m[1];
        $rows = [];
        foreach ($GLOBALS['__cpt_db']['user_access'] as $access) {
            if ($access['cat_id'] === $cid) {
                $rows[] = ['user_id' => $access['user_id']];
            }
        }
        return new ArrayIterator($rows);
    }
    if (preg_match('/SELECT field_key, value_text, tag_id FROM '.CPT_OWNER_PROFILE_TABLE.' WHERE root_album_id=(\d+) AND owner_user_id=(\d+) ORDER BY field_key ASC/', $sqlTrim, $m)) {
        $rootId = (int) $m[1];
        $ownerId = (int) $m[2];
        $rows = [];
        foreach ($GLOBALS['__cpt_db']['owner_profile'] as $row) {
            if ($row['root_album_id'] === $rootId && $row['owner_user_id'] === $ownerId) {
                $rows[] = [
                    'field_key' => $row['field_key'],
                    'value_text' => $row['value_text'],
                    'tag_id' => $row['tag_id'],
                ];
            }
        }
        usort($rows, fn($a, $b) => strcmp($a['field_key'], $b['field_key']));
        return new ArrayIterator($rows);
    }
    if (preg_match('/DELETE FROM '.CPT_OWNER_PROFILE_TABLE." WHERE root_album_id=(\d+) AND field_key='([^']+)'/", $sqlTrim, $m)) {
        $rootId = (int) $m[1];
        $fieldKey = stripslashes($m[2]);
        $GLOBALS['__cpt_db']['owner_profile'] = array_values(array_filter(
            $GLOBALS['__cpt_db']['owner_profile'],
            fn($row) => !($row['root_album_id'] === $rootId && $row['field_key'] === $fieldKey)
        ));
        return true;
    }
    if (str_starts_with($sqlTrim, 'INSERT INTO '.CPT_OWNER_PROFILE_TABLE.' ')) {
        $prefix = 'INSERT INTO '.CPT_OWNER_PROFILE_TABLE.' (root_album_id, owner_user_id, field_key, value_text, tag_id, updated_at) VALUES (';
        $suffix = ', NOW())';
        if (str_starts_with($sqlTrim, $prefix) && str_ends_with($sqlTrim, $suffix)) {
            $inner = substr($sqlTrim, strlen($prefix), -strlen($suffix));
            $parts = str_getcsv($inner, ',', "'", '\\');
            $parts = array_map('trim', $parts);
            if (count($parts) === 5) {
                $rootId = (int) $parts[0];
                $ownerId = (int) $parts[1];
                $fieldKey = stripslashes($parts[2]);
                $valueText = strtoupper($parts[3]) === 'NULL' ? null : stripslashes($parts[3]);
                $tagId = strtoupper($parts[4]) === 'NULL' ? null : (int) $parts[4];
                $GLOBALS['__cpt_db']['owner_profile'][] = [
                    'id' => cpt_next_id('owner_profile'),
                    'root_album_id' => $rootId,
                    'owner_user_id' => $ownerId,
                    'field_key' => $fieldKey,
                    'value_text' => $valueText,
                    'tag_id' => $tagId,
                ];
                return true;
            }
        }
    }
    if (str_contains($sqlTrim, 'FROM '.USERS_TABLE) && str_contains($sqlTrim, 'ORDER BY username')) {
        $rows = [];
        foreach ($GLOBALS['__cpt_db']['users'] as $u) {
            if (preg_match('/NOT IN \(([^\)]+)\)/', $sqlTrim, $matches)) {
                $excluded = array_map('intval', array_map('trim', explode(',', $matches[1])));
                if (in_array((int) $u['id'], $excluded, true)) {
                    continue;
                }
            }
            $rows[] = ['user_id' => $u['id'], 'username' => $u['username']];
        }
        usort($rows, fn($a, $b) => strcmp($a['username'], $b['username']));
        return new ArrayIterator($rows);
    }
    if (preg_match('/SELECT user_id FROM '.CATEGORIES_TABLE.' WHERE id=(\d+)/',$sqlTrim,$m)){
        $id=(int)$m[1]; foreach($GLOBALS['__cpt_db']['categories'] as $c){ if($c['id']===$id){ return new ArrayIterator([[ 'user_id'=>$c['user_id'] ?? null ]]); }} return new ArrayIterator([]);
    }
    if (preg_match('/SELECT i.added_by FROM '.IMAGE_CATEGORY_TABLE.' ic INNER JOIN '.IMAGES_TABLE.' i ON i.id=ic.image_id WHERE ic.category_id=(\d+)/',$sqlTrim,$m)){
        $cid=(int)$m[1]; foreach($GLOBALS['__cpt_db']['image_category'] as $ic){ if($ic['category_id']===$cid){ foreach($GLOBALS['__cpt_db']['images'] as $img){ if($img['id']===$ic['image_id']){ return new ArrayIterator([[ 'added_by'=>$img['added_by'] ]]); } } } } return new ArrayIterator([]);
    }
    if (preg_match('/DELETE FROM '.$prefixeTable.'user_cache/',$sqlTrim)){
        $GLOBALS['__cpt_db']['user_cache']=[]; $GLOBALS['__cpt_user_cache_purged']=true; return true;
    }
    if (preg_match('/UPDATE '.$prefixeTable."user_cache\s+SET need_update = 'true'/",$sqlTrim)){
        // Front-end fallback path of cpt_invalidate_user_cache()
        $GLOBALS['__cpt_user_cache_purged']=true;
        $GLOBALS['__cpt_user_cache_invalidations'] = ($GLOBALS['__cpt_user_cache_invalidations'] ?? 0) + 1;
        return true;
    }
    if (preg_match("/SHOW TABLES LIKE '([^']+)'/", $sqlTrim, $m)) {
        $table = stripslashes($m[1]);
        if ($table === CPT_OWNER_PROFILE_TABLE) {
            return new ArrayIterator(!empty($GLOBALS['__cpt_owner_profile_table_exists']) ? [[ CPT_OWNER_PROFILE_TABLE ]] : []);
        }
        // Always pretend user_cache table exists so purge logic executes
        return new ArrayIterator([[ $table ]]);
    }
    if (str_starts_with($sqlTrim, 'CREATE TABLE IF NOT EXISTS '.CPT_OWNER_PROFILE_TABLE.' ')) {
        $GLOBALS['__cpt_owner_profile_table_exists'] = true;
        return true;
    }
    // Fallback exclusive contributor queries (rough detection for tests)
    if (str_contains($sqlTrim,'COUNT(*) AS cnt FROM (') && str_contains($sqlTrim,'GROUP BY ic.category_id')) {
        // Iterate categories that have at least one image and check distinct added_by
        $catIds = [];
        foreach($GLOBALS['__cpt_db']['image_category'] as $ic){ $catIds[$ic['category_id']] = true; }
        $uid = null; if(preg_match('/MIN\(i.added_by\) = (\d+)/',$sqlTrim,$mm)){ $uid=(int)$mm[1]; }
        $count=0;
        foreach(array_keys($catIds) as $cid){
            $added=[]; foreach($GLOBALS['__cpt_db']['image_category'] as $ic){ if($ic['category_id']===$cid){ foreach($GLOBALS['__cpt_db']['images'] as $img){ if($img['id']===$ic['image_id']){ $added[$img['added_by']]=true; } } } }
            if(count($added)===1 && isset($added[$uid])){ $count++; }
        }
        return new ArrayIterator([[ 'cnt'=>$count ]]);
    }
    if (str_contains($sqlTrim,'SELECT c.id, c.name, c.comment, c.status') && str_contains($sqlTrim,'GROUP BY ic.category_id')) {
        $uid = null; if(preg_match('/MIN\(i.added_by\) = (\d+)/',$sqlTrim,$mm)){ $uid=(int)$mm[1]; }
        $rows=[]; foreach($GLOBALS['__cpt_db']['categories'] as $c){
            // Evaluate exclusive contribution condition
            $added=[]; foreach($GLOBALS['__cpt_db']['image_category'] as $ic){ if($ic['category_id']===$c['id']){ foreach($GLOBALS['__cpt_db']['images'] as $img){ if($img['id']===$ic['image_id']){ $added[$img['added_by']]=true; } } } }
            if(count($added)===1 && isset($added[$uid])){ $rows[]=$c; }
        }
        return new ArrayIterator($rows);
    }
    return new ArrayIterator([]); // default empty result
}
function pwg_db_fetch_assoc($it){ if($it instanceof ArrayIterator){ if($it->valid()){ $cur=$it->current(); $it->next(); return $cur; } return null; } return null; }
function pwg_db_fetch_row($it){ return pwg_db_fetch_assoc($it); }
function pwg_db_real_escape_string($s){ return addslashes($s); }

// Cache invalidation stubs: record calls so tests can assert on the
// debounced invalidation contract (once per save, always $full === false).
if (!function_exists('invalidate_user_cache')) {
    function invalidate_user_cache($full = true){
        $GLOBALS['__cpt_user_cache_purged'] = true;
        $GLOBALS['__cpt_user_cache_invalidations'] = ($GLOBALS['__cpt_user_cache_invalidations'] ?? 0) + 1;
        $GLOBALS['__cpt_user_cache_invalidate_full_flags'][] = (bool) $full;
    }
}
if (!function_exists('trigger_notify')) { function trigger_notify($e,$arg=null){} }

// -------------------------------------------------------------------------
// Core function & constant stubs required by plugin main.inc.php
// -------------------------------------------------------------------------
if (!defined('EVENT_HANDLER_PRIORITY_NEUTRAL')) { define('EVENT_HANDLER_PRIORITY_NEUTRAL', 0); }
if (!function_exists('get_root_url')) { function get_root_url(){ return '/'; } }
if (!function_exists('get_absolute_root_url')) { function get_absolute_root_url(){ return '/'; } }
if (!function_exists('make_index_url')) { function make_index_url($arr){ return 'index.php'; } }
if (!function_exists('add_event_handler')) {
    function add_event_handler($event, $callback, $priority = 0, $file = null){
        // store handlers for potential future assertions (not currently used)
        $GLOBALS['__cpt_handlers'][$event][] = [ 'cb'=>$callback, 'priority'=>$priority, 'file'=>$file ];
    }
}
if (!function_exists('load_language')) { function load_language($file, $path=''){ return true; } }
if (!function_exists('safe_unserialize')) { function safe_unserialize($v){ if (is_array($v)) return $v; if (is_string($v) && $v !== '') { $r=@unserialize($v); return is_array($r)?$r:[]; } return []; } }

// Load plugin code under test
require_once dirname(__DIR__).'/main.inc.php';

// Helpers for tests ---------------------------------------------------------
function cpt_test_create_owned_album(int $user_id, string $status='public', string $name='Album', string $comment=''): int {
    $id = cpt_next_id('categories');
    $GLOBALS['__cpt_db']['categories'][] = [ 'id'=>$id, 'user_id'=>$user_id, 'name'=>$name, 'comment'=>$comment, 'status'=>$status, 'id_uppercat'=>null, 'uppercats'=>(string) $id ];
    return $id;
}
function cpt_test_create_community_owned_album(int $user_id, string $status='public', string $name='Album', string $comment=''): int {
    $id = cpt_next_id('categories');
    $GLOBALS['__cpt_db']['categories'][] = [ 'id'=>$id, 'community_user'=>$user_id, 'name'=>$name, 'comment'=>$comment, 'status'=>$status, 'id_uppercat'=>null, 'uppercats'=>(string) $id ];
    return $id;
}
function cpt_test_create_child_album(int $parent_id, string $status='public', string $name='Album', string $comment='', array $extra = []): int {
    $id = cpt_next_id('categories');
    $parent = cpt_test_get_category($parent_id);
    $uppercats = isset($parent['uppercats']) && $parent['uppercats'] !== ''
        ? $parent['uppercats'].','.$id
        : $parent_id.','.$id;

    $row = array_merge([
        'id' => $id,
        'name' => $name,
        'comment' => $comment,
        'status' => $status,
        'id_uppercat' => $parent_id,
        'uppercats' => $uppercats,
    ], $extra);

    $GLOBALS['__cpt_db']['categories'][] = $row;
    return $id;
}
function cpt_test_add_image(int $added_by): int {
    $id = cpt_next_id('images');
    $GLOBALS['__cpt_db']['images'][] = [
        'id'=>$id,
        'added_by'=>$added_by,
        'name'=>'Image '.$id,
        'file'=>'image-'.$id.'.jpg',
        'path'=>'./galleries/image-'.$id.'.jpg',
        'representative_ext'=>'jpg',
    ];
    return $id;
}
function cpt_test_create_user(int $id, string $username): void {
    $GLOBALS['__cpt_db']['users'][] = [ 'id' => $id, 'username' => $username ];
}
function cpt_test_link_image(int $image_id, int $category_id): void {
    $GLOBALS['__cpt_db']['image_category'][] = [ 'image_id'=>$image_id, 'category_id'=>$category_id ];
}
function cpt_test_get_category(int $id){ foreach($GLOBALS['__cpt_db']['categories'] as $c){ if($c['id']===$id){ return $c; } } return null; }
function cpt_test_get_user_access(int $cat_id){ return array_values(array_filter($GLOBALS['__cpt_db']['user_access'], fn($r)=>$r['cat_id']===$cat_id)); }

function cpt_test_set_user(int $id, bool $is_admin=false){
    global $user; $user = ['id'=>$id, 'is_guest'=>false, 'status'=>$is_admin?'admin':'normal'];
    if($is_admin){ $_SESSION['is_admin']=true; }
}

function cpt_test_set_guest_user(): void {
    global $user;
    $user = ['id' => 0, 'is_guest' => true, 'status' => 'guest'];
}

function cpt_test_reset_env(){
    cpt_test_reset_db();
    $_SESSION=[];
    global $page; $page=['infos'=>[], 'errors'=>[]];
    global $conf; $conf['core_privacy_toggle_owner_profile_options'] = [];
    global $template; $template = new CptTestTemplate();
    $GLOBALS['__cpt_test_pwg_token'] = 'test-token';
    $GLOBALS['__cpt_owner_profile_table_exists'] = true;
    if (isset($GLOBALS['__cpt_force_ownership_column'])) { unset($GLOBALS['__cpt_force_ownership_column']); }
    if (array_key_exists('__cpt_test_owner_profile_plugin_available', $GLOBALS)) { unset($GLOBALS['__cpt_test_owner_profile_plugin_available']); }
    if (array_key_exists('__cpt_ownership_column_cache', $GLOBALS)) { unset($GLOBALS['__cpt_ownership_column_cache']); }
    unset($GLOBALS['__cpt_user_cache_purged'], $GLOBALS['__cpt_user_cache_dirty']);
    unset($GLOBALS['__cpt_test_fail_sql_pattern'], $GLOBALS['__cpt_db_txn_snapshot']);
    $GLOBALS['__cpt_user_cache_invalidations'] = 0;
    $GLOBALS['__cpt_user_cache_invalidate_full_flags'] = [];
}

function cpt_test_was_user_cache_purged(): bool { return !empty($GLOBALS['__cpt_user_cache_purged']); }
function cpt_test_clear_user_cache_purge_flag(): void { unset($GLOBALS['__cpt_user_cache_purged']); }
function cpt_test_user_cache_invalidation_count(): int { return (int) ($GLOBALS['__cpt_user_cache_invalidations'] ?? 0); }
function cpt_test_user_cache_invalidate_full_flags(): array { return $GLOBALS['__cpt_user_cache_invalidate_full_flags'] ?? []; }
function cpt_test_set_owner_profile_options(string $field_key, array $options): void {
    global $conf;
    $conf['core_privacy_toggle_owner_profile_options'][$field_key] = $options;
}

function cpt_test_set_owner_profile_table_exists(bool $exists): void {
    $GLOBALS['__cpt_owner_profile_table_exists'] = $exists;
    unset($GLOBALS['__cpt_owner_profile_table_exists_checked']);
}

function cpt_test_set_owner_profile_plugin_available(bool $available): void {
	$GLOBALS['__cpt_test_owner_profile_plugin_available'] = $available;
}

class CptTestWsService {
    public array $methods = [];
    public function addMethod($name, $callback, $params, $description): void {
        $this->methods[$name] = [
            'callback' => $callback,
            'params' => $params,
            'description' => $description,
        ];
    }
}
