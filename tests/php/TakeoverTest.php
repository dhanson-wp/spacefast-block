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
	 * @return array
	 */
	private function block( $name, array $attrs = array(), array $inner_blocks = array() ) {
		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '',
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

	public function test_finds_a_nested_full_page_block() {
		$attrs  = array(
			'url'     => 'https://nested.space.fast/',
			'display' => 'fullpage',
		);
		$blocks = array(
			$this->block(
				'core/group',
				array(),
				array(
					$this->block( 'core/columns', array(), array( $this->block( 'core/column', array(), array( $this->block( 'spacefast/space', $attrs ) ) ) ) ),
				)
			),
		);

		$this->assertSame( $attrs, Takeover::find_fullpage_block( $blocks ) );
	}

	public function test_returns_the_first_of_two_full_page_blocks_in_document_order() {
		$first  = array(
			'url'     => 'https://first.space.fast/',
			'display' => 'fullpage',
		);
		$second = array(
			'url'     => 'https://second.space.fast/',
			'display' => 'fullpage',
		);
		$blocks = array(
			$this->block( 'core/group', array(), array( $this->block( 'spacefast/space', $first ) ) ),
			$this->block( 'spacefast/space', $second ),
		);

		$this->assertSame( $first, Takeover::find_fullpage_block( $blocks ) );
	}

	public function test_skips_a_full_page_block_that_cannot_be_framed() {
		$not_frameable = array(
			'url'         => 'https://closed.space.fast/',
			'display'     => 'fullpage',
			'isFrameable' => false,
		);
		$frameable     = array(
			'url'     => 'https://open.space.fast/',
			'display' => 'fullpage',
		);

		$this->assertNull( Takeover::find_fullpage_block( array( $this->block( 'spacefast/space', $not_frameable ) ) ) );
		$this->assertSame(
			$frameable,
			Takeover::find_fullpage_block(
				array(
					$this->block( 'spacefast/space', $not_frameable ),
					$this->block( 'spacefast/space', $frameable ),
				)
			)
		);
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
}
