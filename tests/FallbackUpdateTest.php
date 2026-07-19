<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/bootstrap.php';

class FallbackUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        cpt_test_reset_env();
        // Force absence of ownership column (bypass static cache inside plugin)
        $GLOBALS['__cpt_force_ownership_column'] = false;
    }

    public function testUpdateAllowedForExclusiveContributionAlbumWithoutOwnershipColumn()
    {
        // Create category without user_id column (simulate absence) and link only this user's images
        cpt_test_set_user(30);
        $catId = cpt_next_id('categories');
        $GLOBALS['__cpt_db']['categories'][] = [ 'id'=>$catId, 'name'=>'Orig', 'comment'=>'Old', 'status'=>'public' ];
        $img1 = cpt_test_add_image(30); $img2 = cpt_test_add_image(30);
        cpt_test_link_image($img1, $catId); cpt_test_link_image($img2, $catId);

        // Sanity: ownership count should be 0 (no column)
        $this->assertSame(0, cpt_count_albums_owned_by(30));
        $this->assertSame(1, cpt_count_albums_contributed_exclusive(30));

        $payload = [ $catId => [ 'name'=>'NewName', 'comment'=>'Nuevo', 'private'=>'1' ] ];
        $changed = cpt_handle_album_form($payload, 30);
        $this->assertTrue($changed, 'Update should be applied via fallback ownership');
        $cat = cpt_test_get_category($catId);
        $this->assertSame('NewName', $cat['name']);
        $this->assertSame('Nuevo', $cat['comment']);
        $this->assertSame('private', $cat['status']);
    }

    public function testUpdateDeniedWhenNotExclusiveContributor()
    {
        cpt_test_set_user(31);
        $catId = cpt_next_id('categories');
        $GLOBALS['__cpt_db']['categories'][] = [ 'id'=>$catId, 'name'=>'Shared', 'comment'=>'Mix', 'status'=>'public' ];
        $img1 = cpt_test_add_image(31); $img2 = cpt_test_add_image(99); // mixed contributors
        cpt_test_link_image($img1, $catId); cpt_test_link_image($img2, $catId);
        $payload = [ $catId => [ 'name'=>'Hack', 'comment'=>'Attempt', 'private'=>'1' ] ];
        $changed = cpt_handle_album_form($payload, 31);
        $this->assertFalse($changed, 'Mixed contributor album should not be updatable');
        $cat = cpt_test_get_category($catId);
        $this->assertSame('Shared', $cat['name']);
        $this->assertSame('Mix', $cat['comment']);
        $this->assertSame('public', $cat['status']);
    }
}
