<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/bootstrap.php';

class AlbumFieldPersistenceTest extends TestCase
{
    protected function setUp(): void
    {
        cpt_test_reset_env();
    }

    public function testNameAndCommentUtf8Persistence()
    {
        cpt_test_set_user(22);
        $albumId = cpt_test_create_owned_album(22, 'public', 'Init', 'Old');
        $payload = [ $albumId => [ 'name'=>'Nouveau 🗂️', 'comment'=>'Descripción π 测试', 'private'=>'1' ] ];
        cpt_handle_album_form($payload, 22);
        $album = cpt_test_get_category($albumId);
        $this->assertSame('Nouveau 🗂️', stripslashes($album['name']));
        $this->assertSame('Descripción π 测试', stripslashes($album['comment']));
        $this->assertSame('private', $album['status']);
    }
}
