<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/bootstrap.php';

class AlbumUpdateSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        cpt_test_reset_env();
    }

    public function testUnauthorizedAlbumUpdateIgnored()
    {
        // Owner is user 3
        $ownerId = 3; $intruderId = 4;
        $albumId = cpt_test_create_owned_album($ownerId, 'public', 'Orig', 'Desc');
        // Intruder session
        cpt_test_set_user($intruderId);
        $payload = [ $albumId => [ 'name'=>'Hacked', 'comment'=>'Evil', 'private'=>'1' ] ];
        $result = cpt_handle_album_form($payload, $intruderId);
        $this->assertFalse($result, 'No updates should be applied');
        $album = cpt_test_get_category($albumId);
        $this->assertSame('Orig', $album['name']);
        $this->assertSame('Desc', $album['comment']);
        $this->assertSame('public', $album['status']);
    }

    public function testUnknownAlbumColumnIsRejectedBeforeWrite()
    {
        $albumId = cpt_test_create_owned_album(3, 'public', 'Orig', 'Desc');

        $result = cpt_update_album($albumId, [
            'name' => 'Changed',
            'bogus' => 'nope',
        ]);

        $this->assertFalse($result);
        $album = cpt_test_get_category($albumId);
        $this->assertSame('Orig', $album['name']);
        $this->assertSame('Desc', $album['comment']);

        $logs = cpt_test_log_messages();
        $this->assertSame('ERROR', $logs[array_key_last($logs)]['level']);
        $this->assertStringContainsString('unknown column bogus', $logs[array_key_last($logs)]['message']);
    }
}
