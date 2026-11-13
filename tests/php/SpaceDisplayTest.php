<?php
/**
 * Tests for Space_Display. The cases mirror src/space/utils/test/display.js so
 * the editor and the front end are pinned to the same behaviour.
 *
 * @package Spacefast_Block
 */

use PHPUnit\Framework\TestCase;
use Spacefast_Block\Space_Display;

/**
 * @covers \Spacefast_Block\Space_Display
 */
final class SpaceDisplayTest extends TestCase {

	/**
	 * @dataProvider availability
	 */
	public function test_is_display_available( $display, $is_frameable, $expected ) {
		$this->assertSame( $expected, Space_Display::is_display_available( $display, $is_frameable ) );
	}

	public function availability() {
		return array(
			'inline framed'       => array( 'inline', true, true ),
			'fullpage framed'     => array( 'fullpage', true, true ),
			'card framed'         => array( 'card', true, true ),
			'link framed'         => array( 'link', true, true ),
			'inline not framed'   => array( 'inline', false, false ),
			'fullpage not framed' => array( 'fullpage', false, false ),
			'card not framed'     => array( 'card', false, true ),
			'link not framed'     => array( 'link', false, true ),
		);
	}

	public function test_effective_display_keeps_the_chosen_mode() {
		$this->assertSame( 'fullpage', Space_Display::effective_display( array( 'display' => 'fullpage' ) ) );
	}

	public function test_effective_display_defaults_to_inline() {
		$this->assertSame( 'inline', Space_Display::effective_display( array() ) );
		$this->assertSame( 'inline', Space_Display::effective_display( array( 'display' => 'bogus' ) ) );
	}

	/**
	 * @dataProvider framed_modes
	 */
	public function test_effective_display_is_a_card_when_not_frameable( $display ) {
		$this->assertSame(
			'card',
			Space_Display::effective_display(
				array(
					'display'     => $display,
					'isFrameable' => false,
				)
			)
		);
	}

	public function framed_modes() {
		return array(
			'inline'   => array( 'inline' ),
			'fullpage' => array( 'fullpage' ),
		);
	}

	public function test_min_height_joins_value_and_unit() {
		$this->assertSame(
			'420px',
			Space_Display::min_height(
				array(
					'display'       => 'inline',
					'minHeight'     => 420,
					'minHeightUnit' => 'px',
				)
			)
		);
	}

	public function test_min_height_defaults_the_unit_to_px() {
		$this->assertSame( '30px', Space_Display::min_height( array( 'minHeight' => 30 ) ) );
		$this->assertSame( '30px', Space_Display::min_height( array( 'minHeight' => 30, 'minHeightUnit' => '' ) ) );
	}

	public function test_min_height_refuses_an_unknown_unit() {
		$this->assertSame( '30px', Space_Display::min_height( array( 'minHeight' => 30, 'minHeightUnit' => 'px;color:red' ) ) );
	}

	public function test_min_height_accepts_decimals_and_vh() {
		$this->assertSame( '12.5vh', Space_Display::min_height( array( 'minHeight' => 12.5, 'minHeightUnit' => 'vh' ) ) );
	}

	public function test_min_height_is_null_when_no_height_is_set() {
		$this->assertNull( Space_Display::min_height( array( 'minHeightUnit' => 'vh' ) ) );
		$this->assertNull( Space_Display::min_height( array( 'minHeight' => 0 ) ) );
		$this->assertNull( Space_Display::min_height( array( 'minHeight' => 'tall' ) ) );
	}

	/**
	 * @dataProvider other_modes
	 */
	public function test_min_height_is_null_outside_inline( $display ) {
		$this->assertNull(
			Space_Display::min_height(
				array(
					'display'       => $display,
					'minHeight'     => 50,
					'minHeightUnit' => 'vh',
				)
			)
		);
	}

	public function other_modes() {
		return array(
			'fullpage' => array( 'fullpage' ),
			'card'     => array( 'card' ),
			'link'     => array( 'link' ),
		);
	}

	public function test_min_height_is_null_for_an_inline_space_that_cannot_be_framed() {
		$this->assertNull(
			Space_Display::min_height(
				array(
					'display'       => 'inline',
					'isFrameable'   => false,
					'minHeight'     => 50,
					'minHeightUnit' => 'vh',
				)
			)
		);
	}

	public function test_min_height_gives_way_to_an_explicit_aspect_ratio() {
		$this->assertNull(
			Space_Display::min_height(
				array(
					'minHeight'     => 50,
					'minHeightUnit' => 'vh',
					'style'         => array( 'dimensions' => array( 'aspectRatio' => '16/9' ) ),
				)
			)
		);
	}

	public function test_min_height_ignores_an_auto_aspect_ratio() {
		$this->assertSame(
			'50vh',
			Space_Display::min_height(
				array(
					'minHeight'     => 50,
					'minHeightUnit' => 'vh',
					'style'         => array( 'dimensions' => array( 'aspectRatio' => 'auto' ) ),
				)
			)
		);
	}

	/**
	 * @dataProvider aspect_ratios
	 */
	public function test_has_aspect_ratio( $attributes, $expected ) {
		$this->assertSame( $expected, Space_Display::has_aspect_ratio( $attributes ) );
	}

	public function aspect_ratios() {
		return array(
			'explicit'         => array( array( 'style' => array( 'dimensions' => array( 'aspectRatio' => '16/9' ) ) ), true ),
			'auto'             => array( array( 'style' => array( 'dimensions' => array( 'aspectRatio' => 'auto' ) ) ), false ),
			'Auto with spaces' => array( array( 'style' => array( 'dimensions' => array( 'aspectRatio' => ' Auto ' ) ) ), false ),
			'no style'         => array( array(), false ),
			'empty'            => array( array( 'style' => array( 'dimensions' => array( 'aspectRatio' => '' ) ) ), false ),
		);
	}

	public function test_private_label_and_cta_fallbacks() {
		$this->assertSame( 'Team only', Space_Display::private_label( array( 'privateLabel' => 'Team only' ), 'Private' ) );
		$this->assertSame( 'Private', Space_Display::private_label( array( 'privateLabel' => '' ), 'Private' ) );
		$this->assertSame( 'Ask Derek', Space_Display::private_cta( array( 'privateCta' => 'Ask Derek' ), 'Request access' ) );
		$this->assertSame( 'Request access', Space_Display::private_cta( array(), 'Request access' ) );
	}

	public function test_link_label_prefers_the_written_label() {
		$this->assertSame(
			'Try it',
			Space_Display::link_label( array( 'linkLabel' => 'Try it' ), 'Open Space', 'Request access' )
		);
	}

	public function test_link_label_falls_back_to_open_space_for_a_public_link() {
		$this->assertSame( 'Open Space', Space_Display::link_label( array( 'isPrivate' => false ), 'Open Space', 'Request access' ) );
	}

	public function test_link_label_falls_back_to_the_private_cta_for_a_private_link() {
		$this->assertSame(
			'Request access',
			Space_Display::link_label( array( 'isPrivate' => true ), 'Open Space', 'Request access' )
		);
		$this->assertSame(
			'Ask Derek',
			Space_Display::link_label(
				array(
					'isPrivate' => true,
					'privateCta' => 'Ask Derek',
				),
				'Open Space',
				'Request access'
			)
		);
	}

	public function test_card_title_falls_back_to_the_hostname() {
		$this->assertSame( 'Hi', Space_Display::card_title( array( 'title' => 'Hi', 'hostname' => 'a.space.fast' ) ) );
		$this->assertSame( 'a.space.fast', Space_Display::card_title( array( 'title' => '', 'hostname' => 'a.space.fast' ) ) );
	}

	public function test_card_image_source() {
		$base = array(
			'imageUrl' => 'https://a.space.fast/og.png',
			'imageId'  => 0,
		);
		$this->assertSame( 'url', Space_Display::card_image_source( $base ) );
		$this->assertSame( 'none', Space_Display::card_image_source( array_merge( $base, array( 'showImage' => false ) ) ) );
		$this->assertSame( 'none', Space_Display::card_image_source( array() ) );
	}

	public function test_a_library_image_wins_over_the_external_one() {
		$this->assertSame(
			'attachment',
			Space_Display::card_image_source(
				array(
					'imageUrl' => 'https://a.space.fast/og.png',
					'imageId'  => 12,
				)
			)
		);
	}

	public function test_a_library_image_shows_without_an_image_url() {
		$this->assertSame( 'attachment', Space_Display::card_image_source( array( 'imageId' => 12 ) ) );
		$this->assertSame(
			'is-display-card has-image-side',
			Space_Display::block_class_names(
				array(
					'display'       => 'card',
					'imageId'       => 12,
					'imagePosition' => 'side',
				)
			)
		);
	}

	public function test_a_private_space_shows_no_image_without_a_library_image() {
		$this->assertSame(
			'none',
			Space_Display::card_image_source(
				array(
					'isPrivate' => true,
					'imageUrl'  => 'https://a.space.fast/og.png',
					'imageId'   => 0,
				)
			)
		);
	}

	public function test_a_private_space_shows_its_library_image() {
		$this->assertSame(
			'attachment',
			Space_Display::card_image_source(
				array(
					'isPrivate' => true,
					'imageUrl'  => 'http://localhost/wp-content/uploads/a.png',
					'imageId'   => 12,
				)
			)
		);
	}

	private function class_attributes( array $overrides = array() ) {
		return array_merge(
			array(
				'display'       => 'inline',
				'isFrameable'   => true,
				'isPrivate'     => false,
				'showImage'     => true,
				'imagePosition' => 'top',
				'imageUrl'      => 'https://a.space.fast/og.png',
				'imageId'       => 0,
			),
			$overrides
		);
	}

	public function test_class_names_name_the_display_mode() {
		$this->assertSame( 'is-display-inline', Space_Display::block_class_names( $this->class_attributes() ) );
	}

	public function test_class_names_name_the_image_position_for_a_card_with_an_image() {
		$this->assertSame(
			'is-display-card has-image-side',
			Space_Display::block_class_names( $this->class_attributes( array( 'display' => 'card', 'imagePosition' => 'side' ) ) )
		);
	}

	public function test_class_names_default_the_image_position_to_top() {
		$attributes = $this->class_attributes( array( 'display' => 'card' ) );
		unset( $attributes['imagePosition'] );
		$this->assertSame( 'is-display-card has-image-top', Space_Display::block_class_names( $attributes ) );
	}

	public function test_class_names_leave_out_the_image_position_for_a_card_without_an_image() {
		$this->assertSame(
			'is-display-card',
			Space_Display::block_class_names( $this->class_attributes( array( 'display' => 'card', 'showImage' => false ) ) )
		);
	}

	public function test_class_names_leave_out_the_image_position_for_other_modes() {
		$this->assertSame(
			'is-display-link',
			Space_Display::block_class_names( $this->class_attributes( array( 'display' => 'link' ) ) )
		);
	}

	public function test_class_names_mark_a_private_space_shown_as_a_card() {
		$this->assertSame(
			'is-display-card is-private',
			Space_Display::block_class_names(
				$this->class_attributes(
					array(
						'display'     => 'inline',
						'isFrameable' => false,
						'isPrivate'   => true,
					)
				)
			)
		);
	}
}
