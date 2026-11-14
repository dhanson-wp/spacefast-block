<?php
/**
 * Tests for the pure part of Takeover: finding the block that takes over a page
 * in parse_blocks() output.
 *
 * @package Spacefast_Block
 */

use PHPUnit\Framework\TestCase;
use Spacefast_Block\Takeover;

/**
 * @covers \Spacefast_Block\Takeover
 */
final class TakeoverTest extends TestCase {

	/**
	 * A parsed block, shaped like parse_blocks() output.
	 *
	 * @param string|null $name         Block name; null for freeform HTML between blocks.
	 * @param array       $attrs        Attributes.
	 * @param array       $inner_blocks Inner blocks.
	 * @param string      $inner_html   The block's own HTML.
	 * @return array
	 */
	private function block( $name, array $attrs = array(), array $inner_blocks = array(), $inner_html = '' ) {
		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => $inner_html,
			'innerContent' => array(),
		);
	}

	public function test_finds_a_top_level_full_page_block() {
		$attrs  = array(
			'url'     => 'https://a.space.fast/',
			'display' => 'fullpage',
		);
		$blocks = array(
			$this->block( 'core/paragraph' ),
			$this->block( null ),
			$this->block( 'spacefast/space', $attrs ),
		);

		$this->assertSame( $attrs, Takeover::find_fullpage_block( $blocks ) );
	}

	public function test_a_full_page_block_inside_a_wrapper_does_not_take_over() {
		$attrs  = array(
			'url'     => 'https://nested.space.fast/',
			'display' => 'fullpage',
		);
		$blocks = array(
			$this->block( 'core/group', array(), array( $this->block( 'spacefast/space', $attrs ) ) ),
		);

		$this->assertNull( Takeover::find_fullpage_block( $blocks ) );
	}

	public function test_a_full_page_block_next_to_another_block_does_not_take_over() {
		$attrs = array(
			'url'     => 'https://a.space.fast/',
			'display' => 'fullpage',
		);

		$this->assertNull(
			Takeover::find_fullpage_block(
				array(
					$this->block( 'core/paragraph', array(), array(), '<p>Hello</p>' ),
					$this->block( 'spacefast/space', $attrs ),
				)
			)
		);
		$this->assertNull(
			Takeover::find_fullpage_block(
				array(
					$this->block( 'spacefast/space', $attrs ),
					$this->block( 'core/image' ),
				)
			)
		);
	}

	public function test_two_full_page_blocks_do_not_take_over() {
		$first  = array(
			'url'     => 'https://first.space.fast/',
			'display' => 'fullpage',
		);
		$second = array(
			'url'     => 'https://second.space.fast/',
			'display' => 'fullpage',
		);
		$blocks = array(
			$this->block( 'spacefast/space', $first ),
			$this->block( 'spacefast/space', $second ),
		);

		$this->assertNull( Takeover::find_fullpage_block( $blocks ) );
	}

	public function test_a_space_that_is_not_full_page_next_to_a_full_page_block_stops_it_taking_over() {
		$blocks = array(
			$this->block( 'spacefast/space', array( 'url' => 'https://inline.space.fast/' ) ),
			$this->block(
				'spacefast/space',
				array(
					'url'     => 'https://full.space.fast/',
					'display' => 'fullpage',
				)
			),
		);

		$this->assertNull( Takeover::find_fullpage_block( $blocks ) );
	}

	public function test_skips_a_full_page_block_that_cannot_be_framed() {
		$not_frameable = array(
			'url'         => 'https://closed.space.fast/',
			'display'     => 'fullpage',
			'isFrameable' => false,
		);

		$this->assertNull( Takeover::find_fullpage_block( array( $this->block( 'spacefast/space', $not_frameable ) ) ) );
	}

	public function test_returns_null_when_no_block_takes_over() {
		$blocks = array(
			$this->block( 'core/paragraph' ),
			$this->block( 'spacefast/space', array( 'url' => 'https://inline.space.fast/' ) ),
			$this->block( 'spacefast/space', array( 'display' => 'card' ) ),
			$this->block( 'core/group', array(), array( $this->block( 'core/image' ) ) ),
		);

		$this->assertNull( Takeover::find_fullpage_block( $blocks ) );
		$this->assertNull( Takeover::find_fullpage_block( array() ) );
	}

	public function test_ignores_other_blocks_that_use_the_display_attribute() {
		$blocks = array( $this->block( 'core/other', array( 'display' => 'fullpage' ) ) );

		$this->assertNull( Takeover::find_fullpage_block( $blocks ) );
	}

	public function test_skips_a_full_page_block_with_no_url() {
		$blocks = array( $this->block( 'spacefast/space', array( 'display' => 'fullpage' ) ) );

		$this->assertNull( Takeover::find_fullpage_block( $blocks ) );
	}

	public function test_skips_a_full_page_block_with_an_invalid_url() {
		$invalid = array(
			array(
				'url'     => 'not a url',
				'display' => 'fullpage',
			),
			array(
				'url'     => 'http://insecure.space.fast/',
				'display' => 'fullpage',
			),
		);

		foreach ( $invalid as $attrs ) {
			$this->assertNull( Takeover::find_fullpage_block( array( $this->block( 'spacefast/space', $attrs ) ) ) );
		}
	}

	public function test_a_block_that_cannot_render_still_counts_as_another_block() {
		$valid  = array(
			'url'     => 'https://valid.space.fast/',
			'display' => 'fullpage',
		);
		$blocks = array(
			$this->block(
				'spacefast/space',
				array(
					'url'     => '',
					'display' => 'fullpage',
				)
			),
			$this->block( 'spacefast/space', $valid ),
		);

		$this->assertNull( Takeover::find_fullpage_block( $blocks ) );
	}

	public function test_skips_a_full_page_block_on_a_host_that_is_not_verified() {
		$blocks = array(
			$this->block(
				'spacefast/space',
				array(
					'url'     => 'https://example.com/',
					'display' => 'fullpage',
				)
			),
		);

		$this->assertNull( Takeover::find_fullpage_block( $blocks ) );
		$this->assertNull( Takeover::find_fullpage_block( $blocks, array( 'other.example.com' => 1 ) ) );
	}

	public function test_finds_a_full_page_block_on_a_verified_host() {
		$attrs  = array(
			'url'     => 'https://spaces.example.com/',
			'display' => 'fullpage',
		);
		$blocks = array( $this->block( 'spacefast/space', $attrs ) );

		$this->assertSame( $attrs, Takeover::find_fullpage_block( $blocks, array( 'spaces.example.com' => 1 ) ) );
	}

	public function test_empty_paragraphs_and_blank_freeform_content_around_the_block_are_ignored() {
		$attrs  = array(
			'url'     => 'https://a.space.fast/',
			'display' => 'fullpage',
		);
		$blocks = array(
			$this->block( 'core/paragraph', array(), array(), '<p></p>' ),
			$this->block( null, array(), array(), "\n\n" ),
			$this->block( 'spacefast/space', $attrs ),
			$this->block( 'core/paragraph', array(), array(), '<p></p>' ),
		);

		$this->assertSame( $attrs, Takeover::find_fullpage_block( $blocks ) );
	}

	public function test_an_unverified_host_does_not_take_over_even_with_a_verified_one_beside_it() {
		$blocks = array(
			$this->block(
				'spacefast/space',
				array(
					'url'     => 'https://example.com/',
					'display' => 'fullpage',
				)
			),
			$this->block(
				'spacefast/space',
				array(
					'url'     => 'https://later.space.fast/',
					'display' => 'fullpage',
				)
			),
		);

		$this->assertNull( Takeover::find_fullpage_block( $blocks ) );
	}

	public function test_empty_content_has_no_takeover() {
		$this->assertNull( Takeover::find_fullpage_block( array() ) );
	}

	public function test_content_for_page_without_page_breaks_is_the_whole_content() {
		$this->assertSame( 'one', Takeover::content_for_page( 'one', 1 ) );
		$this->assertSame( 'one', Takeover::content_for_page( 'one', 3 ) );
	}

	public function test_content_for_page_picks_the_current_page() {
		$content = "first\n<!--nextpage-->\nsecond<!--nextpage-->third";

		$this->assertSame( 'first', Takeover::content_for_page( $content, 1 ) );
		$this->assertSame( 'second', Takeover::content_for_page( $content, 2 ) );
		$this->assertSame( 'third', Takeover::content_for_page( $content, 3 ) );
	}

	public function test_content_for_page_treats_zero_as_the_first_page_and_clamps_past_the_end() {
		$content = 'first<!--nextpage-->second';

		$this->assertSame( 'first', Takeover::content_for_page( $content, 0 ) );
		$this->assertSame( 'second', Takeover::content_for_page( $content, 9 ) );
	}

	public function test_content_for_page_handles_page_break_blocks_and_a_leading_break() {
		$content = "<!--nextpage-->a\n\n<!-- wp:nextpage -->\n<!--nextpage-->\n<!-- /wp:nextpage -->\n\nb";

		// Same order as WP_Query::generate_postdata(): newlines around a bare break
		// collapse first, then the block delimiters are dropped.
		$this->assertSame( "a\n\n", Takeover::content_for_page( $content, 1 ) );
		$this->assertSame( "\n\nb", Takeover::content_for_page( $content, 2 ) );
	}
}
