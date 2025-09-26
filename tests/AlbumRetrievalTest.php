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
}
