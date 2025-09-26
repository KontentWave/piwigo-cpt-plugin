<?php
// Minimal PHPUnit bootstrap for Core Privacy Toggle plugin tests
// Provides lightweight mocks of Piwigo globals & loads plugin core logic.

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (!defined('PHPWG_ROOT_PATH')) {
    // Point to repository root (adjust if test runner executed elsewhere)
    define('PHPWG_ROOT_PATH', realpath(__DIR__ . '/../../../..') . DIRECTORY_SEPARATOR);
}

// Define essential path constants mimicking Piwigo environment
if (!defined('PWG_LOCAL_DIR')) { define('PWG_LOCAL_DIR', 'local/'); }
if (!defined('PHPWG_PLUGINS_PATH')) { define('PHPWG_PLUGINS_PATH', PHPWG_ROOT_PATH . 'albums/plugins/'); }

// Table prefix (empty for tests by default)
$prefixeTable = 'piwigo_'; // mimic typical prefix to exercise concatenation

// Core table name constants expected by plugin functions
if (!defined('CATEGORIES_TABLE')) { define('CATEGORIES_TABLE', $prefixeTable.'categories'); }
if (!defined('IMAGES_TABLE')) { define('IMAGES_TABLE', $prefixeTable.'images'); }
if (!defined('IMAGE_CATEGORY_TABLE')) { define('IMAGE_CATEGORY_TABLE', $prefixeTable.'image_category'); }
if (!defined('USER_ACCESS_TABLE')) { define('USER_ACCESS_TABLE', $prefixeTable.'user_access'); }

// Minimal globals
global $conf, $user, $page;
$conf = $conf ?? [];
$user = $user ?? [];
$page = $page ?? ['infos'=>[], 'errors'=>[]];

// Stub translation simply echoes key
if (!function_exists('l10n')) { function l10n($k){ return $k; } }

// Session var check stub
if (!function_exists('pwg_get_session_var')) { function pwg_get_session_var($k){ return $_SESSION[$k] ?? null; } }
if (!function_exists('pwg_set_session_var')) { function pwg_set_session_var($k,$v){ $_SESSION[$k]=$v; } }

// Template stub with minimal API used by plugin
if (!class_exists('CptTestTemplate')) {
    class CptTestTemplate {
        private array $vars = [];
        public array $head_elements = [];
        public array $footer_msgs = [];
        public function assign($k,$v){ $this->vars[$k]=$v; }
        public function set_filename($handle,$path){ $this->vars['__tpl_path__']=$path; }
        public function parse($handle,$return){
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
];

function cpt_test_reset_db(){
    $GLOBALS['__cpt_db'] = [
        'categories' => [],
        'images' => [],
        'image_category' => [],
        'user_access' => [],
        'user_cache' => [],
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
    // SELECT COUNT(id) FROM categories WHERE user_id = X
    if (preg_match('/SELECT COUNT\(id\) AS cnt FROM '.CATEGORIES_TABLE.' WHERE user_id = (\d+)/',$sqlTrim,$m)){
        $uid = (int)$m[1]; $cnt=0; foreach($GLOBALS['__cpt_db']['categories'] as $c){ if(($c['user_id']??null)===$uid){$cnt++;}} return new ArrayIterator([[ 'cnt'=>$cnt ]]);
    }
    if (preg_match('/SELECT id, name, comment, status FROM '.CATEGORIES_TABLE.' WHERE user_id = (\d+)/',$sqlTrim,$m)){
        $uid=(int)$m[1]; $rows=[]; foreach(array_reverse($GLOBALS['__cpt_db']['categories']) as $c){ if(($c['user_id']??null)===$uid){ $rows[]=$c; } } return new ArrayIterator($rows);
    }
    if (preg_match('/DESC '.CATEGORIES_TABLE.'/',$sqlTrim)){
        // Simulate presence of user_id column when at least one category with that key exists
        $has = false; foreach($GLOBALS['__cpt_db']['categories'] as $c){ if(array_key_exists('user_id',$c)){ $has=true; break; } }
        $rows = $has ? [['Field'=>'user_id']] : [];
        return new ArrayIterator($rows);
    }
    if (preg_match('/SELECT 1 FROM '.CATEGORIES_TABLE.' WHERE id=(\d+) AND user_id=(\d+)/',$sqlTrim,$m)){
        $id=(int)$m[1];$uid=(int)$m[2];foreach($GLOBALS['__cpt_db']['categories'] as $c){ if($c['id']===$id && ($c['user_id']??null)===$uid){ return new ArrayIterator([[1]]); }} return new ArrayIterator([]);
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
    if (preg_match('/UPDATE '.CATEGORIES_TABLE.' SET (.+) WHERE id=(\d+)/',$sqlTrim,$m)){
        $assignments = $m[1]; $id=(int)$m[2];
        foreach($GLOBALS['__cpt_db']['categories'] as &$c){ if($c['id']===$id){
            foreach(explode(',', $assignments) as $pair){
                if(strpos($pair,'=')!==false){ list($col,$val)=explode('=',$pair,2); $col=trim($col); $val=trim($val, "' "); $c[$col]=$val; }
            }
            if (str_contains($assignments, "status='")) { $GLOBALS['__cpt_user_cache_purged']=true; }
        }} unset($c); return true;
    }
    if (preg_match('/DELETE FROM '.USER_ACCESS_TABLE.' WHERE cat_id=(\d+)/',$sqlTrim,$m)){
        $cid=(int)$m[1]; $GLOBALS['__cpt_db']['user_access']=array_values(array_filter($GLOBALS['__cpt_db']['user_access'],fn($r)=>$r['cat_id']!==$cid)); return true;
    }
    if (preg_match('/INSERT INTO '.USER_ACCESS_TABLE.' \(user_id, cat_id\) VALUES (.+)/',$sqlTrim,$m)){
        $vals = $m[1]; foreach(explode('),',$vals) as $tuple){ $tuple=trim($tuple,' ()'); if($tuple==='') continue; list($uid,$cid)=array_map('intval', explode(',',$tuple)); $GLOBALS['__cpt_db']['user_access'][]=['user_id'=>$uid,'cat_id'=>$cid]; }
        return true;
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
    if (preg_match('/SHOW TABLES LIKE/', $sqlTrim)) {
        // Always pretend user_cache table exists so purge logic executes
        return new ArrayIterator([[ 'user_cache' ]]);
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

// Cache invalidation no-ops
if (!function_exists('invalidate_user_cache')) { function invalidate_user_cache(){} }
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
    $GLOBALS['__cpt_db']['categories'][] = [ 'id'=>$id, 'user_id'=>$user_id, 'name'=>$name, 'comment'=>$comment, 'status'=>$status ];
    return $id;
}
function cpt_test_add_image(int $added_by): int {
    $id = cpt_next_id('images');
    $GLOBALS['__cpt_db']['images'][] = [ 'id'=>$id, 'added_by'=>$added_by ];
    return $id;
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

function cpt_test_reset_env(){
    cpt_test_reset_db();
    $_SESSION=[];
    global $page; $page=['infos'=>[], 'errors'=>[]];
    if (isset($GLOBALS['__cpt_force_ownership_column'])) { unset($GLOBALS['__cpt_force_ownership_column']); }
}

function cpt_test_was_user_cache_purged(): bool { return !empty($GLOBALS['__cpt_user_cache_purged']); }
function cpt_test_clear_user_cache_purge_flag(): void { unset($GLOBALS['__cpt_user_cache_purged']); }
