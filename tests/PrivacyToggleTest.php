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
}
