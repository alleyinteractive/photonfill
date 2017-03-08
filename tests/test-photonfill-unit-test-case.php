<?php

class Photonfill_Test_Case extends WP_UnitTestCase {

	private $attachment_id;
	private $image_src;
	private $image_path;
	private $photon_url;

	public static function setUpBeforeClass() {
		self::activate_my_photon();
	}

	public function setUp() {
		parent::setUp();
		$this->attachment_id = $this->insert_attachment( null,
			dirname( __FILE__ ) . '/data/alley_placeholder.jpg',
			array(
				'post_title'     => 'Post',
				'post_content'   => 'Post Content',
				'post_date'      => '2014-10-01 17:28:00',
				'post_status'    => 'publish',
				'post_type'      => 'attachment',
			)
		);
		$upload_dir = wp_upload_dir();
		$this->image_src = $upload_dir['url'] . '/alley_placeholder.jpg';
		$this->image_path = $upload_dir['path'] . '/alley_placeholder.jpg';
		$this->photon_url = 'http://cdn.alley.dev/';
	}

	/**
	 * Test that plugin is loaded
	 */
	public function test_photonfill_loaded() {
		$this->assertTrue( class_exists( 'Photonfill' ) );
	}

	/**
	 * Test that dependency is loaded
	 */
	public function test_my_photon_loaded() {
		$this->assertTrue( class_exists( 'My_Photon_Settings' ) );
	}

	/**
	 * Test that dependency is active
	 */
	public function test_my_photon_on() {
		$this->assertTrue( My_Photon_Settings::get( 'active' ) );
		$this->assertTrue( class_exists( 'My_Photon' ) );
	}

	/**
	 * Test that dependency has right url
	 */
	public function test_my_photon_url() {
		$this->assertContains( $this->photon_url, My_Photon_Settings::get( 'base-url' ) );
	}

	/**
	 * Simplest case
	 */
	public function test_that_my_photon_working() {
		$content = wp_get_attachment_image( $this->attachment_id, 'full' );
		$this->assertContains( $this->photon_url, $content );
	}

	/**
	 * Helper function: insert an attachment to test properties of.
	 * From Image Shortcake http://www.github.com/wp-shortcake/image-shortcake
	 *
	 * @param int $parent_post_id
	 * @param str path to image to use
	 * @param array $post_fields Fields, in the format to be sent to `wp_insert_post()`
	 * @return int Post ID of inserted attachment
	 */
	private function insert_attachment( $parent_post_id = 0, $image = null, $post_fields = array() ) {
		$filename = rand_str() . '.jpg';
		$contents = rand_str();
		if ( $image ) {
			// @codingStandardsIgnoreStart
			$filename = basename( $image );
			$contents = file_get_contents( $image );
			// @codingStandardsIgnoreEnd
		}
		$upload = wp_upload_bits( $filename, null, $contents );
		$this->assertTrue( empty( $upload['error'] ) );
		$type = '';
		if ( ! empty( $upload['type'] ) ) {
			$type = $upload['type'];
		} else {
			$mime = wp_check_filetype( $upload['file'] );
			if ( ! empty( $mime ) ) {
				$type = $mime['type'];
			}
		}
		$attachment = wp_parse_args( $post_fields,
			array(
				'post_title' => basename( $upload['file'] ),
				'post_content' => 'Test Attachment',
				'post_type' => 'attachment',
				'post_parent' => $parent_post_id,
				'post_mime_type' => $type,
				'guid' => $upload['url'],
			)
		);
		$id = wp_insert_attachment( $attachment, $upload['file'], $parent_post_id );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		return $id;
	}

	/**
	 * Activate My Photon for testing
	 */
	private static function activate_my_photon() {
		$my_photon_settings = array(
			'base-url'  => 'http://cdn.alley.dev/',
			'active'    => true,
		);
		update_option( 'my-photon', $my_photon_settings );
		activate_plugin( 'my-photon/my-photon.php' );
	}

}
