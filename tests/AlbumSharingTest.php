<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/bootstrap.php';

class AlbumSharingTest extends TestCase
{
    protected function setUp(): void
    {
        cpt_test_reset_env();
        cpt_test_create_user(1, 'user-one');
        cpt_test_create_user(4, 'owner');
        cpt_test_create_user(5, 'alice');
        cpt_test_create_user(6, 'bob');
        cpt_test_create_user(7, 'webmaster', 'admin');
    }

    public function testSharedVisibilityAddsSelectedUsersToAlbumAccess()
    {
        $GLOBALS['__cpt_force_ownership_column'] = 'community_user';
        $albumId = cpt_test_create_community_owned_album(4, 'public', 'Shared Album');

        $result = cpt_handle_album_form([
            $albumId => [
                'name' => 'Shared Album',
                'comment' => 'Shared state',
                'visibility' => 'shared',
                'shared_users' => ['5', '6'],
            ],
        ], 4);

        $this->assertTrue($result);
        $category = cpt_test_get_category($albumId);
        $this->assertSame('private', $category['status']);

        $access = cpt_test_get_user_access($albumId);
        usort($access, fn($a, $b) => $a['user_id'] <=> $b['user_id']);
        $ids = array_column($access, 'user_id');
        $this->assertSame([1, 4, 5, 6], $ids);
    }

    public function testSharedVisibilityWithoutUsersFallsBackToPrivate()
    {
        $GLOBALS['__cpt_force_ownership_column'] = 'community_user';
        $albumId = cpt_test_create_community_owned_album(4, 'public', 'Private Fallback');

        $result = cpt_handle_album_form([
            $albumId => [
                'visibility' => 'shared',
                'shared_users' => [],
            ],
        ], 4);

        $this->assertTrue($result);
        $category = cpt_test_get_category($albumId);
        $this->assertSame('private', $category['status']);

        $access = cpt_test_get_user_access($albumId);
        usort($access, fn($a, $b) => $a['user_id'] <=> $b['user_id']);
        $ids = array_column($access, 'user_id');
        $this->assertSame([1, 4], $ids);
    }

    public function testShareableUserOptionsExcludeOwnerAndAdmin()
    {
        cpt_test_set_user(4);

        $options = cpt_get_shareable_user_options(4);

        $this->assertStringContainsString(USER_INFOS_TABLE, $GLOBALS['__last_query']);
        $this->assertSame([
            5 => 'alice',
            6 => 'bob',
        ], $options);
    }

    public function testSharedVisibilityUsesInheritedOwnerForDescendantAlbum()
    {
        $GLOBALS['__cpt_force_ownership_column'] = 'community_user';
        $root = cpt_test_create_community_owned_album(4, 'public', 'Root');
        $child = cpt_test_create_child_album($root, 'public', 'Child');

        $result = cpt_handle_album_form([
            $child => [
                'visibility' => 'shared',
                'shared_users' => ['5'],
            ],
        ], 4);

        $this->assertTrue($result);
        $category = cpt_test_get_category($child);
        $this->assertSame('private', $category['status']);

        $access = cpt_test_get_user_access($child);
        usort($access, fn($a, $b) => $a['user_id'] <=> $b['user_id']);
        $ids = array_column($access, 'user_id');
        $this->assertSame([1, 4, 5], $ids);
    }

    public function testCustomWebmasterIdGetsImplicitAccessInsteadOfUserOne()
    {
        cpt_test_set_webmaster_id(7);
        $GLOBALS['__cpt_force_ownership_column'] = 'community_user';
        $albumId = cpt_test_create_community_owned_album(4, 'public', 'Custom Webmaster');

        $result = cpt_handle_album_form([
            $albumId => [
                'visibility' => 'shared',
                'shared_users' => ['5'],
            ],
        ], 4);

        $this->assertTrue($result);
        $access = cpt_test_get_user_access($albumId);
        usort($access, fn($a, $b) => $a['user_id'] <=> $b['user_id']);
        $ids = array_column($access, 'user_id');
        $this->assertSame([4, 5, 7], $ids);
        $this->assertNotContains(1, $ids);
    }

    public function testSharedRootPropagatesSelectedUsersToDescendants()
    {
        $GLOBALS['__cpt_force_ownership_column'] = 'community_user';
        $root = cpt_test_create_community_owned_album(4, 'public', 'Root');
        $child = cpt_test_create_child_album($root, 'public', 'Child');

        $result = cpt_handle_album_form([
            $root => [
                'visibility' => 'shared',
                'shared_users' => ['5'],
            ],
        ], 4);

        $this->assertTrue($result);
        $this->assertSame('private', cpt_test_get_category($root)['status']);
        $this->assertSame('private', cpt_test_get_category($child)['status']);

        foreach ([$root, $child] as $albumId) {
            $access = cpt_test_get_user_access($albumId);
            usort($access, fn($a, $b) => $a['user_id'] <=> $b['user_id']);
            $this->assertSame([1, 4, 5], array_column($access, 'user_id'));
        }
    }

    public function testShareableUserOptionsExcludeConfiguredWebmasterButNotOrdinaryUserOne()
    {
        cpt_test_set_webmaster_id(7);
        cpt_test_set_user(4);

        $options = cpt_get_shareable_user_options(4);

        $this->assertSame([
            5 => 'alice',
            6 => 'bob',
            1 => 'user-one',
        ], $options);
    }
}