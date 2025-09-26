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
}
