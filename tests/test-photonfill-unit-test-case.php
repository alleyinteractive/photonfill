<?php

class Photonfill_Test_Case extends WP_UnitTestCase {

	private $attachment_id;
	private $image_src;
	private $image_path;

	public function setUp() {
		parent::setUp();
		$this->attachment_id = $this->insert_attachment( null,
			dirname( __FILE__ ) . '/data/alley_placeholder.png',
			array(
				'post_title'     => 'Post',
				'post_content'   => 'Post Content',
				'post_date'      => '2014-10-01 17:28:00',
				'post_status'    => 'publish',
				'post_type'      => 'attachment',
			)
		);
		$upload_dir = wp_upload_dir();
		$this->image_src = $upload_dir['url'] . '/alley_placeholder.png';
		$this->image_path = $upload_dir['path'] . '/alley_placeholder.png';
		add_filter( 'photonfill_image_sizes', array( $this, 'photonfill_readme_image_stack' ) );
	}

	/**
	 * Test that plugin is loaded
	 * Note this is different from being "active"
	 */
	public function test_photonfill_loaded() {
		$this->assertTrue( class_exists( 'Photonfill' ) );
	}

	/**
	 * Test that dependency is loaded
	 * Note this is different from being "active"
	 */
	public function test_my_photon_loaded() {
		$this->assertTrue( class_exists( 'My_Photon_Settings' ) );
	}

	/**
	 * Simplest case
	 */
	public function test_with_default_image_sizes() {
		$attachment_id = $this->attachment_id;
		$upload_dir = wp_upload_dir();
		$content = wp_get_attachment_image( $attachment_id, 'full' );
		$expected_src_attr = $upload_dir['url'] . '/alley_placeholder.png';
		$this->assertContains( '<img class="size-medium alignleft" src="http://example.com/example.jpg" />', $content );
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
			if ( $mime ) {
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
	 * Add the image sizes and breakpoints as defined in readme
	 */
	public function photonfill_readme_image_stack( $image_stack ) {
		return array(
			'featured-full' => array(
				'xl'  => array( 'width' => 1920, 'height' => 560, 'default' => true, 'callback' => 'top_down_crop' ),
				'l'   => array( 'width' => 1040, 'height' => 500, 'callback' => 'top_down_crop' ),
				's-m' => array( 'width' => 800, 'height' => 450, 'callback' => 'top_down_crop' ),
				'xs'  => array( 'width' => 480, 'height' => 270, 'callback' => 'top_down_crop' ),
			),
			'featured-large' => array(
				'xl' => array( 'width' => 1260, 'height' => 550, 'default' => true, 'callback' => 'top_down_crop' ),
				'l'  => array( 'width' => 1260, 'height' => 550, 'callback' => 'top_down_crop' ),
				'm'  => array( 'width' => 1260, 'height' => 550, 'callback' => 'top_down_crop' ),
				's'  => array( 'width' => 800, 'height' => 450, 'callback' => 'top_down_crop' ),
				'xs' => array( 'width' => 480, 'height' => 270, 'callback' => 'top_down_crop' ),
			),
			'featured-medium' => array(
				'xl' => array( 'width' => 620, 'height' => 349, 'default' => true ),
				'l'  => array( 'width' => 620, 'height' => 349 ),
				'm'  => array( 'width' => 620, 'height' => 349 ),
				's'  => array( 'width' => 800, 'height' => 450 ),
				'xs' => array( 'width' => 480, 'height' => 270 ),
			),
			'featured-thumb' => array(
				'xl' => array( 'width' => 400, 'height' => 225, 'default' => true ),
				'l'  => array( 'width' => 400, 'height' => 225 ),
				'm'  => array( 'width' => 400, 'height' => 225 ),
				's'  => array( 'width' => 800, 'height' => 450 ),
				'xs' => array( 'width' => 480, 'height' => 270 ),
			),
		);
	}

}
