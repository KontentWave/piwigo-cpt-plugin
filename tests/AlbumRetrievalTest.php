<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/bootstrap.php';

class AlbumRetrievalTest extends TestCase
{
    protected function setUp(): void
    {
        cpt_test_reset_env();
    }

    public function testOwnedAlbumsRetrievedWhenOwnershipColumnPresent()
    {
        // Arrange: create owned albums
        cpt_test_set_user(5);
        $a1 = cpt_test_create_owned_album(5, 'public', 'First');
        $a2 = cpt_test_create_owned_album(5, 'private', 'Second');
        // Act
        $count = cpt_count_albums_owned_by(5);
        $albums = cpt_fetch_albums_owned_by(5);
        // Assert
        $this->assertSame(2, $count, 'Should count two owned albums');
        $ids = array_map(fn($r)=>$r['id'], $albums);
        sort($ids);
        $this->assertSame([$a1,$a2], $ids);
    }

    public function testOwnedAlbumsRetrievedWhenCommunityOwnershipColumnPresent()
    {
        $GLOBALS['__cpt_force_ownership_column'] = 'community_user';
        cpt_test_set_user(6);
        $a1 = cpt_test_create_community_owned_album(6, 'public', 'Community First');
        $a2 = cpt_test_create_community_owned_album(6, 'private', 'Community Second');

        $count = cpt_count_albums_owned_by(6);
        $albums = cpt_fetch_albums_owned_by(6);

        $this->assertSame(2, $count, 'Should count two community-owned albums');
        $ids = array_map(fn($r)=>$r['id'], $albums);
        sort($ids);
        $this->assertSame([$a1,$a2], $ids);
        $this->assertTrue(cpt_album_is_owned_by($a1, 6), 'Community ownership should authorize album updates');
    }

    public function testFallbackExclusiveContributionWhenNoOwnershipColumn()
    {
        // Simulate absence of user_id column by creating categories without user_id key
        cpt_test_set_user(7);
        // Add categories without user_id
        $cid1 = cpt_next_id('categories');
        $GLOBALS['__cpt_db']['categories'][] = [ 'id'=>$cid1, 'name'=>'Solo', 'comment'=>'', 'status'=>'public' ];
        $img = cpt_test_add_image(7);
        cpt_test_link_image($img, $cid1);
        // Another category with mixed contributors -> should not count
        $cid2 = cpt_next_id('categories');
        $GLOBALS['__cpt_db']['categories'][] = [ 'id'=>$cid2, 'name'=>'Mixed', 'comment'=>'', 'status'=>'public' ];
        $imgA = cpt_test_add_image(7); $imgB = cpt_test_add_image(8);
        cpt_test_link_image($imgA, $cid2); cpt_test_link_image($imgB, $cid2);

        $countOwned = cpt_count_albums_owned_by(7); // expect 0 because no ownership column
        $countFallback = cpt_count_albums_contributed_exclusive(7);
        $albums = cpt_fetch_albums_contributed_exclusive(7);

        $this->assertSame(0, $countOwned, 'Ownership count should be zero when no column');
        $this->assertSame(1, $countFallback, 'Fallback should detect single exclusive album');
        $this->assertCount(1, $albums);
        $this->assertSame($cid1, $albums[0]['id']);
    }

    public function testNullOwnershipFallsBackToExclusiveContributorWhenColumnExists()
    {
        $GLOBALS['__cpt_force_ownership_column'] = 'community_user';
        cpt_test_set_user(4);

        $direct = cpt_test_create_community_owned_album(4, 'public', 'Direct owner album');

        $fallback = cpt_next_id('categories');
        $GLOBALS['__cpt_db']['categories'][] = [
            'id' => $fallback,
            'community_user' => null,
            'name' => 'Fallback owner album',
            'comment' => '',
            'status' => 'public',
        ];
        $img = cpt_test_add_image(4);
        cpt_test_link_image($img, $fallback);

        $otherOwned = cpt_next_id('categories');
        $GLOBALS['__cpt_db']['categories'][] = [
            'id' => $otherOwned,
            'community_user' => 9,
            'name' => 'Other owner album',
            'comment' => '',
            'status' => 'public',
        ];
        $otherImg = cpt_test_add_image(4);
        cpt_test_link_image($otherImg, $otherOwned);

        $count = cpt_count_albums_owned_by(4);
        $albums = cpt_fetch_albums_owned_by(4);
        $ids = array_map(fn($r) => $r['id'], $albums);
        sort($ids);

        $this->assertSame(2, $count, 'Should count direct ownership plus null-owner fallback album');
        $this->assertSame([$direct, $fallback], $ids);
        $this->assertTrue(cpt_album_is_owned_by($fallback, 4), 'Null ownership should authorize fallback owner');
        $this->assertFalse(cpt_album_is_owned_by($otherOwned, 4), 'Explicit ownership by another user must win over fallback');
    }

    public function testInheritedOwnershipIncludesDescendantsInTreeOrder()
    {
        $GLOBALS['__cpt_force_ownership_column'] = 'community_user';
        cpt_test_set_user(12);

        $root = cpt_test_create_community_owned_album(12, 'public', 'Root');
        $childA = cpt_test_create_child_album($root, 'public', 'Child A');
        $childB = cpt_test_create_child_album($root, 'public', 'Child B');
        $grandchild = cpt_test_create_child_album($childA, 'public', 'Grandchild');

        $albums = cpt_fetch_albums_owned_by(12);
        $ids = array_map(fn($row) => $row['id'], $albums);

        $this->assertSame([$root, $childA, $grandchild, $childB], $ids);
        $this->assertTrue(cpt_album_is_owned_by($childA, 12));
        $this->assertTrue(cpt_album_is_owned_by($grandchild, 12));
        $this->assertSame(12, cpt_get_album_effective_owner_id($grandchild));
    }

    public function testExplicitChildOwnerBlocksInheritedOwnership()
    {
        $GLOBALS['__cpt_force_ownership_column'] = 'community_user';
        cpt_test_set_user(12);

        $root = cpt_test_create_community_owned_album(12, 'public', 'Root');
        $child = cpt_test_create_child_album($root, 'public', 'Child', '', ['community_user' => 13]);

        $this->assertFalse(cpt_album_is_owned_by($child, 12));
        $this->assertTrue(cpt_album_is_owned_by($child, 13));
        $this->assertSame(13, cpt_get_album_effective_owner_id($child));
        $this->assertSame('denied', cpt_get_album_ownership_rule_for_user($child, 12));
        $this->assertSame('direct', cpt_get_album_ownership_rule_for_user($child, 13));
    }
}
