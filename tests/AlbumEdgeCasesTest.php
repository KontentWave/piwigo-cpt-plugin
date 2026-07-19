<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/bootstrap.php';

class AlbumEdgeCasesTest extends TestCase
{
    protected function setUp(): void
    {
        cpt_test_reset_env();
    }

    public function testBlankNameIgnoredRetainsOriginal()
    {
        cpt_test_set_user(40);
        $aid = cpt_test_create_owned_album(40, 'public', 'KeepMe', 'Desc');
        $payload = [ $aid => [ 'name'=>'   ', 'comment'=>'NewDesc' ] ];
        cpt_handle_album_form($payload, 40);
        $cat = cpt_test_get_category($aid);
        $this->assertSame('KeepMe', $cat['name'], 'Original name should remain when blank submitted');
        $this->assertSame('NewDesc', $cat['comment']);
    }

    public function testWhitespaceOnlyCommentBecomesEmptyString()
    {
        cpt_test_set_user(41);
        $aid = cpt_test_create_owned_album(41, 'public', 'Album', 'Old');
        $payload = [ $aid => [ 'name'=>'Album', 'comment'=>'    ' ] ];
        cpt_handle_album_form($payload, 41);
        $cat = cpt_test_get_category($aid);
        $this->assertSame('', $cat['comment']);
    }

    public function testLongUtf8DescriptionPersists()
    {
        cpt_test_set_user(42);
        $aid = cpt_test_create_owned_album(42, 'public', 'A', '');
        $long = str_repeat('Ω多字🙂', 50); // multi-byte repeated
        $payload = [ $aid => [ 'name'=>'A', 'comment'=>$long ] ];
        cpt_handle_album_form($payload, 42);
        $cat = cpt_test_get_category($aid);
        $this->assertSame($long, $cat['comment']);
    }
}
