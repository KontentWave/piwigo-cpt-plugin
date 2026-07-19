<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/bootstrap.php';

class PrivacyToggleTest extends TestCase
{
    protected function setUp(): void
    {
        cpt_test_reset_env();
        // Ensure direct ownership path active for these tests
        $GLOBALS['__cpt_force_ownership_column'] = true;
    }

    public function testPrivateToggleCreatesUserAccessRows()
    {
        cpt_test_set_user(10); // owner and acting user
        $albumId = cpt_test_create_owned_album(10, 'public', 'Alpha', '');
        $payload = [ $albumId => [ 'name'=>'Alpha', 'comment'=>'', 'private'=>'1' ] ];
        $changed = cpt_handle_album_form($payload, 10);
        $this->assertTrue($changed, 'Update should be applied');
        $album = cpt_test_get_category($albumId);
        $this->assertSame('private', $album['status']);
        $ua = cpt_test_get_user_access($albumId);
        $userIds = array_map(fn($r)=>$r['user_id'], $ua);
        sort($userIds);
        $this->assertSame([1,10], $userIds, 'Admin + owner should have explicit access');
        $this->assertTrue(cpt_test_was_user_cache_purged(), 'User cache should be purged on privacy change to private');
        cpt_test_clear_user_cache_purge_flag();
    }

    public function testPublicToggleRemovesUserAccessRows()
    {
        cpt_test_set_user(11);
        $albumId = cpt_test_create_owned_album(11, 'public', 'Bravo', '');
        // First make it private
        cpt_handle_album_form([ $albumId => [ 'name'=>'Bravo', 'comment'=>'', 'private'=>'1' ] ], 11);
        $this->assertNotEmpty(cpt_test_get_user_access($albumId));
        // Then toggle back to public (absence of private key)
        cpt_handle_album_form([ $albumId => [ 'name'=>'Bravo', 'comment'=>'' ] ], 11);
        $this->assertEmpty(cpt_test_get_user_access($albumId));
        $album = cpt_test_get_category($albumId);
        $this->assertSame('public', $album['status']);
        $this->assertTrue(cpt_test_was_user_cache_purged(), 'User cache should be purged on privacy change back to public');
        cpt_test_clear_user_cache_purge_flag();
    }

    public function testInheritedOwnerGetsPrivateAccessOnDescendantAlbum()
    {
        $GLOBALS['__cpt_force_ownership_column'] = 'community_user';
        cpt_test_set_user(14);

        $root = cpt_test_create_community_owned_album(14, 'public', 'Root', '');
        $child = cpt_test_create_child_album($root, 'public', 'Child', '');

        $changed = cpt_handle_album_form([$child => ['visibility' => 'private']], 14);

        $this->assertTrue($changed);
        $album = cpt_test_get_category($child);
        $this->assertSame('private', $album['status']);
        $ua = cpt_test_get_user_access($child);
        $userIds = array_map(fn($row) => $row['user_id'], $ua);
        sort($userIds);
        $this->assertSame([1, 14], $userIds);
    }

    public function testPrivatizingOwnedRootRecursivelyPrivatizesDescendants()
    {
        $GLOBALS['__cpt_force_ownership_column'] = 'community_user';
        cpt_test_set_user(15);

        $root = cpt_test_create_community_owned_album(15, 'public', 'Root', '');
        $child = cpt_test_create_child_album($root, 'public', 'Child', '');
        $grandchild = cpt_test_create_child_album($child, 'public', 'Grandchild', '');

        $changed = cpt_handle_album_form([$root => ['visibility' => 'private']], 15);

        $this->assertTrue($changed);
        $this->assertSame('private', cpt_test_get_category($root)['status']);
        $this->assertSame('private', cpt_test_get_category($child)['status']);
        $this->assertSame('private', cpt_test_get_category($grandchild)['status']);

        foreach ([$root, $child, $grandchild] as $albumId) {
            $userIds = array_map(fn($row) => $row['user_id'], cpt_test_get_user_access($albumId));
            sort($userIds);
            $this->assertSame([1, 15], $userIds);
        }
    }

    public function testReapplyingPrivateRootRepairsPublicDescendants()
    {
        $GLOBALS['__cpt_force_ownership_column'] = 'community_user';
        cpt_test_set_user(16);

        $root = cpt_test_create_community_owned_album(16, 'private', 'Root', '');
        $child = cpt_test_create_child_album($root, 'public', 'Child', '');

        cpt_update_album($root, ['status' => 'private'], false, [], 16);

        $this->assertSame('private', cpt_test_get_category($root)['status']);
        $this->assertSame('private', cpt_test_get_category($child)['status']);

        foreach ([$root, $child] as $albumId) {
            $userIds = array_map(fn($row) => $row['user_id'], cpt_test_get_user_access($albumId));
            sort($userIds);
            $this->assertSame([1, 16], $userIds);
        }
    }

    public function testInitStyleReconciliationRepairsPrivateRootDescendants()
    {
        $GLOBALS['__cpt_force_ownership_column'] = 'community_user';
        cpt_test_set_user(17);

        $root = cpt_test_create_community_owned_album(17, 'private', 'Root', '');
        $child = cpt_test_create_child_album($root, 'public', 'Child', '');

        $this->assertTrue(cpt_reconcile_private_owner_root_descendants_for_user(17));
        $this->assertSame('private', cpt_test_get_category($root)['status']);
        $this->assertSame('private', cpt_test_get_category($child)['status']);

        foreach ([$root, $child] as $albumId) {
            $userIds = array_map(fn($row) => $row['user_id'], cpt_test_get_user_access($albumId));
            sort($userIds);
            $this->assertSame([1, 17], $userIds);
        }
    }

    public function testMultiAlbumSaveInvalidatesUserCacheExactlyOnce()
    {
        cpt_test_set_user(18);
        $first = cpt_test_create_owned_album(18, 'public', 'First', '');
        $second = cpt_test_create_owned_album(18, 'public', 'Second', '');

        $changed = cpt_handle_album_form([
            $first => ['name' => 'First', 'comment' => '', 'private' => '1'],
            $second => ['name' => 'Second', 'comment' => '', 'private' => '1'],
        ], 18);

        $this->assertTrue($changed);
        $this->assertSame(1, cpt_test_user_cache_invalidation_count(),
            'One save must trigger exactly one cache invalidation, not one per album');
        $this->assertSame([false], cpt_test_user_cache_invalidate_full_flags(),
            'Invalidation must use need_update (full=false), never a TRUNCATE');
    }

    public function testNonPrivacyEditDoesNotInvalidateUserCache()
    {
        cpt_test_set_user(19);
        $albumId = cpt_test_create_owned_album(19, 'public', 'Echo', '');

        $changed = cpt_handle_album_form([
            $albumId => ['name' => 'Echo renamed', 'comment' => 'new text'],
        ], 19);

        $this->assertTrue($changed);
        $this->assertSame('Echo renamed', cpt_test_get_category($albumId)['name']);
        $this->assertSame(0, cpt_test_user_cache_invalidation_count(),
            'Editing name/comment without a privacy transition must not touch the user cache');
    }

    public function testFailedPermissionSyncRollsBackPrivacyTransition()
    {
        cpt_test_set_user(20);
        $albumId = cpt_test_create_owned_album(20, 'public', 'Rollback', '');

        $GLOBALS['__cpt_test_fail_sql_pattern'] = '/^INSERT INTO '.USER_ACCESS_TABLE.'/';
        $ok = cpt_update_album($albumId, ['status' => 'private'], false, ['mode' => 'private', 'shared_user_ids' => []], 20);
        unset($GLOBALS['__cpt_test_fail_sql_pattern']);

        $this->assertFalse($ok, 'Update must report failure when user_access sync fails');
        $this->assertSame('public', cpt_test_get_category($albumId)['status'],
            'Status change must roll back when the user_access INSERT fails — otherwise the owner is locked out');
        $this->assertSame([], cpt_test_get_user_access($albumId));
        cpt_flush_user_cache_invalidation();
        $this->assertSame(0, cpt_test_user_cache_invalidation_count(),
            'A rolled-back transition must not invalidate the user cache');
    }

    public function testFailedDescendantPropagationRollsBackRoot()
    {
        $GLOBALS['__cpt_force_ownership_column'] = 'community_user';
        cpt_test_set_user(21);

        $root = cpt_test_create_community_owned_album(21, 'public', 'Root', '');
        $child = cpt_test_create_child_album($root, 'public', 'Child', '');

        $GLOBALS['__cpt_test_fail_sql_pattern'] = '/^UPDATE '.CATEGORIES_TABLE." SET status='private' WHERE id=".$child.' LIMIT 1$/';
        $ok = cpt_update_album($root, ['status' => 'private'], false, [], 21);
        unset($GLOBALS['__cpt_test_fail_sql_pattern']);

        $this->assertFalse($ok, 'Update must report failure when descendant propagation fails');
        $this->assertSame('public', cpt_test_get_category($root)['status'],
            'Root status must roll back when a descendant update fails — no private root with public descendants');
        $this->assertSame('public', cpt_test_get_category($child)['status']);
        $this->assertSame([], cpt_test_get_user_access($root));
        $this->assertSame([], cpt_test_get_user_access($child));
    }
}
