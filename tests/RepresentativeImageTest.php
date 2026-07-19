<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/bootstrap.php';

class RepresentativeImageTest extends TestCase
{
	protected function setUp(): void
	{
		cpt_test_reset_env();
		$GLOBALS['__cpt_force_ownership_column'] = 'community_user';
	}

	public function testOwnerCanSetRepresentativeImageFromSameAlbum()
	{
		cpt_test_set_user(30);
		$albumId = cpt_test_create_community_owned_album(30, 'public', 'Cover Album');
		$imageId = cpt_test_add_image(30);
		cpt_test_link_image($imageId, $albumId);

		$result = cpt_handle_album_form([
			$albumId => [
				'representative_picture_id' => (string) $imageId,
			],
		], 30);

		$this->assertTrue($result);
		$category = cpt_test_get_category($albumId);
		$this->assertSame((string) $imageId, (string) $category['representative_picture_id']);
	}

	public function testOwnerCannotSetRepresentativeImageFromAnotherAlbum()
	{
		cpt_test_set_user(30);
		$albumId = cpt_test_create_community_owned_album(30, 'public', 'Cover Album');
		$otherAlbumId = cpt_test_create_community_owned_album(30, 'public', 'Other Album');
		$imageId = cpt_test_add_image(30);
		cpt_test_link_image($imageId, $otherAlbumId);

		$result = cpt_handle_album_form([
			$albumId => [
				'representative_picture_id' => (string) $imageId,
			],
		], 30);

		$this->assertTrue($result);
		$category = cpt_test_get_category($albumId);
		$this->assertArrayNotHasKey('representative_picture_id', $category);
	}

	public function testOwnerCanClearRepresentativeImage()
	{
		cpt_test_set_user(30);
		$albumId = cpt_test_create_community_owned_album(30, 'public', 'Cover Album');
		$imageId = cpt_test_add_image(30);
		cpt_test_link_image($imageId, $albumId);
		cpt_handle_album_form([
			$albumId => [
				'representative_picture_id' => (string) $imageId,
			],
		], 30);

		$result = cpt_handle_album_form([
			$albumId => [
				'representative_picture_id' => '',
			],
		], 30);

		$this->assertTrue($result);
		$category = cpt_test_get_category($albumId);
		$this->assertSame('NULL', $category['representative_picture_id']);
	}
}