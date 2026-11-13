<?php
/**
 * Tests for Space_Relay.
 *
 * @package Spacefast_Block
 */

use PHPUnit\Framework\TestCase;
use Spacefast_Block\Space_Relay;

/**
 * @covers \Spacefast_Block\Space_Relay
 */
final class SpaceRelayTest extends TestCase {

	const BASE = 'http://example.test/wp-json/spacefast-block/v1/';

	/**
	 * The cases shared with src/space/utils/test/relay.js.
	 *
	 * @param string $group Fixture group.
	 * @return array
	 */
	private static function shared_cases( string $group ): array {
		$cases = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/relay-urls.json' ), true );
		$named = array();
		foreach ( $cases[ $group ] as $case ) {
			$named[ $case['name'] ] = array( $case );
		}
		return $named;
	}

	public function page_relay_cases() {
		return self::shared_cases( 'pageRelayUrl' );
	}

	public function relay_cases() {
		return self::shared_cases( 'relayUrl' );
	}

	/**
	 * @dataProvider page_relay_cases
	 */
	public function test_page_relay_url_matches_shared_cases( array $case ) {
		$this->assertSame( $case['expected'], Space_Relay::page_relay_url( $case['relayBase'], $case['url'], $case['startPath'] ) );
	}

	/**
	 * @dataProvider relay_cases
	 */
	public function test_relay_url_matches_shared_cases( array $case ) {
		$this->assertSame( $case['expected'], Space_Relay::relay_url( $case['relayBase'], $case['kind'], $case['url'] ) );
	}

	public function test_csp_is_the_sandbox_list() {
		$this->assertSame( 'sandbox allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads', Space_Relay::CSP );
		$this->assertSame( 'allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads', Space_Relay::SANDBOX );
		$this->assertStringNotContainsString( 'allow-same-origin', Space_Relay::CSP );
		$this->assertStringNotContainsString( 'allow-top-navigation', Space_Relay::CSP );
	}

	// Upstream URLs.

	public function test_upstream_url_for_home_page() {
		$this->assertSame( 'https://a.space.fast/', Space_Relay::upstream_url( 'a.space.fast', '', '' ) );
	}

	public function test_upstream_url_joins_path_and_query() {
		$this->assertSame( 'https://a.space.fast/docs/a.html?x=1', Space_Relay::upstream_url( 'A.space.fast', 'docs/a.html', 'x=1' ) );
	}

	public function test_upstream_url_re_encodes_a_decoded_path() {
		$this->assertSame( 'https://a.space.fast/my%20file%25.css', Space_Relay::upstream_url( 'a.space.fast', 'my file%.css', '' ) );
		$this->assertSame( 'https://a.space.fast/caf%C3%A9/', Space_Relay::upstream_url( 'a.space.fast', "caf\xC3\xA9/", '' ) );
	}

	/**
	 * @dataProvider refused_upstreams
	 */
	public function test_upstream_url_refuses( $host, $path, $query ) {
		$this->assertNull( Space_Relay::upstream_url( $host, $path, $query ) );
	}

	public function refused_upstreams() {
		return array(
			'dot dot'            => array( 'a.space.fast', '../x', '' ),
			'dot dot inside'     => array( 'a.space.fast', 'a/../x', '' ),
			'dot'                => array( 'a.space.fast', 'a/./x', '' ),
			'at sign'            => array( 'a.space.fast', 'a@b/', '' ),
			'backslash'          => array( 'a.space.fast', 'a\\b', '' ),
			'control character'  => array( 'a.space.fast', "a\x01b", '' ),
			'empty segment'      => array( 'a.space.fast', 'a//b', '' ),
			'leading slash'      => array( 'a.space.fast', '/etc', '' ),
			'bad query'          => array( 'a.space.fast', 'a', 'x=<y>' ),
			'at sign in query'   => array( 'a.space.fast', 'a', 'x=@y' ),
			'host with port'     => array( 'a.space.fast:8443', '', '' ),
			'host with userinfo' => array( 'u@a.space.fast', '', '' ),
			'host with path'     => array( 'a.space.fast/x', '', '' ),
			'ip host'            => array( '127.0.0.1', '', '' ),
			'localhost'          => array( 'localhost', '', '' ),
			'empty host'         => array( '', '', '' ),
		);
	}

	public function test_forwarded_query_drops_only_rest_route() {
		$this->assertSame( 'x=1&y=2', Space_Relay::forwarded_query( 'rest_route=%2Fspacefast-block%2Fv1%2Fpage%2Fa&x=1&y=2' ) );
		$this->assertSame( 'x=1', Space_Relay::forwarded_query( 'x=1' ) );
		$this->assertSame( '', Space_Relay::forwarded_query( 'rest_route=/x' ) );
		$this->assertSame( '', Space_Relay::forwarded_query( '' ) );
	}

	// Redirects.

	public function test_page_redirect_to_trailing_slash_is_followed() {
		$this->assertSame( 'https://a.space.fast/docs/', Space_Relay::page_redirect_target( 'https://a.space.fast/docs', '/docs/' ) );
		$this->assertSame( 'https://a.space.fast/docs/?x=1', Space_Relay::page_redirect_target( 'https://a.space.fast/docs?x=1', 'https://a.space.fast/docs/?x=1' ) );
	}

	/**
	 * @dataProvider refused_redirects
	 */
	public function test_page_redirect_elsewhere_is_refused( $from, $location ) {
		$this->assertNull( Space_Relay::page_redirect_target( $from, $location ) );
	}

	public function refused_redirects() {
		return array(
			'other path'      => array( 'https://a.space.fast/docs', '/other/' ),
			'other host'      => array( 'https://a.space.fast/docs', 'https://b.space.fast/docs/' ),
			'http'            => array( 'https://a.space.fast/docs', 'http://a.space.fast/docs/' ),
			'already slashed' => array( 'https://a.space.fast/docs/', '/docs//' ),
			'empty'           => array( 'https://a.space.fast/docs', '' ),
		);
	}

	// Content types.

	/**
	 * @dataProvider asset_types
	 */
	public function test_asset_content_types( $upstream, $expected ) {
		$this->assertSame( $expected, Space_Relay::asset_content_type( $upstream ) );
	}

	public function asset_types() {
		return array(
			'css with charset' => array( 'text/css; charset=utf-8', 'text/css; charset=utf-8' ),
			'css upper'        => array( 'TEXT/CSS', 'text/css' ),
			'text javascript'  => array( 'text/javascript', 'text/javascript' ),
			'app javascript'   => array( 'application/javascript; charset=UTF-8', 'application/javascript; charset=UTF-8' ),
			'woff2'            => array( 'font/woff2', 'font/woff2' ),
			'app font'         => array( 'application/font-woff', 'application/font-woff' ),
			'eot'              => array( 'application/vnd.ms-fontobject', 'application/vnd.ms-fontobject' ),
			'bad charset'      => array( 'text/css; charset="x y"', 'text/css' ),
			'png'              => array( 'image/png', null ),
			'svg'              => array( 'image/svg+xml', null ),
			'html'             => array( 'text/html', null ),
			'json'             => array( 'application/json', null ),
			'empty'            => array( '', null ),
			'font prefix only' => array( 'font/', null ),
		);
	}

	public function test_page_content_type_is_html_only() {
		$this->assertSame( 'text/html; charset=utf-8', Space_Relay::page_content_type( 'text/html; charset=utf-8' ) );
		$this->assertSame( 'text/html', Space_Relay::page_content_type( 'Text/HTML' ) );
		$this->assertNull( Space_Relay::page_content_type( 'application/xhtml+xml' ) );
		$this->assertNull( Space_Relay::page_content_type( 'text/plain' ) );
		$this->assertNull( Space_Relay::page_content_type( '' ) );
	}

	public function test_is_css() {
		$this->assertTrue( Space_Relay::is_css( 'text/css; charset=utf-8' ) );
		$this->assertFalse( Space_Relay::is_css( 'text/javascript' ) );
	}

	// URL resolution.

	/**
	 * @dataProvider resolutions
	 */
	public function test_resolve_url( $base, $ref, $expected ) {
		$this->assertSame( $expected, Space_Relay::resolve_url( $base, $ref ) );
	}

	public function resolutions() {
		$base = 'https://a.space.fast/docs/page.html?q=1';
		return array(
			'relative'          => array( $base, 'style.css', 'https://a.space.fast/docs/style.css' ),
			'dot slash'         => array( $base, './style.css', 'https://a.space.fast/docs/style.css' ),
			'parent'            => array( $base, '../fonts/a.woff2', 'https://a.space.fast/fonts/a.woff2' ),
			'too many parents'  => array( $base, '../../../a.css', 'https://a.space.fast/a.css' ),
			'root relative'     => array( $base, '/a/b.css?v=2', 'https://a.space.fast/a/b.css?v=2' ),
			'absolute'          => array( $base, 'https://cdn.example.com/x.css', 'https://cdn.example.com/x.css' ),
			'protocol relative' => array( $base, '//cdn.example.com/x.css', 'https://cdn.example.com/x.css' ),
			'query only'        => array( $base, '?v=3', 'https://a.space.fast/docs/page.html?v=3' ),
			'whitespace'        => array( $base, "  a.css\n", 'https://a.space.fast/docs/a.css' ),
			'keeps fragment'    => array( $base, 'f.eot?#iefix', 'https://a.space.fast/docs/f.eot?#iefix' ),
			'data'              => array( $base, 'data:font/woff2;base64,AAAA', null ),
			'javascript'        => array( $base, 'javascript:alert(1)', null ),
			'fragment only'     => array( $base, '#x', null ),
			'empty'             => array( $base, '', null ),
			'directory base'    => array( 'https://a.space.fast/', 'a/b.css', 'https://a.space.fast/a/b.css' ),
		);
	}

	public function test_base_href_is_the_page_directory() {
		$this->assertSame( 'https://a.space.fast/', Space_Relay::base_href( 'https://a.space.fast/', null ) );
		$this->assertSame( 'https://a.space.fast/docs/', Space_Relay::base_href( 'https://a.space.fast/docs/page.html?x=1', null ) );
		$this->assertSame( 'https://a.space.fast/docs/', Space_Relay::base_href( 'https://a.space.fast/docs/', null ) );
	}

	public function test_base_href_resolves_an_existing_base() {
		$this->assertSame( 'https://a.space.fast/app/', Space_Relay::base_href( 'https://a.space.fast/docs/page.html', '/app/' ) );
		$this->assertSame( 'https://a.space.fast/docs/', Space_Relay::base_href( 'https://a.space.fast/docs/page.html', 'javascript:x' ) );
	}

	// HTML decisions.

	/**
	 * @dataProvider link_rules
	 */
	public function test_link_is_relayed( $rel, $as, $expected ) {
		$this->assertSame( $expected, Space_Relay::is_relayed_link( $rel, $as ) );
	}

	public function link_rules() {
		return array(
			'stylesheet'         => array( 'stylesheet', '', true ),
			'alternate sheet'    => array( 'Alternate  Stylesheet', '', true ),
			'preload font'       => array( 'preload', 'font', true ),
			'preload style'      => array( 'preload', 'style', true ),
			'preload script'     => array( 'preload', 'SCRIPT', true ),
			'preload image'      => array( 'preload', 'image', false ),
			'modulepreload'      => array( 'modulepreload', '', true ),
			'icon'               => array( 'icon', '', false ),
			'canonical'          => array( 'canonical', '', false ),
			'prefetch'           => array( 'prefetch', 'script', false ),
			'empty'              => array( '', '', false ),
		);
	}

	public function test_only_module_scripts_are_relayed() {
		$this->assertTrue( Space_Relay::is_relayed_script( 'module' ) );
		$this->assertTrue( Space_Relay::is_relayed_script( ' Module ' ) );
		$this->assertFalse( Space_Relay::is_relayed_script( '' ) );
		$this->assertFalse( Space_Relay::is_relayed_script( 'text/javascript' ) );
	}

	public function test_html_asset_url_relays_same_host_only() {
		$doc = 'https://a.space.fast/docs/';
		$this->assertSame( self::BASE . 'asset/a.space.fast/docs/app.css', Space_Relay::html_asset_url( $doc, 'app.css', self::BASE ) );
		$this->assertSame( self::BASE . 'asset/a.space.fast/assets/app.js?v=1', Space_Relay::html_asset_url( $doc, '/assets/app.js?v=1', self::BASE ) );
		$this->assertNull( Space_Relay::html_asset_url( $doc, 'https://fonts.googleapis.com/css2?family=X', self::BASE ) );
		$this->assertNull( Space_Relay::html_asset_url( $doc, 'https://b.space.fast/app.css', self::BASE ) );
		$this->assertNull( Space_Relay::html_asset_url( $doc, 'data:text/css,a{}', self::BASE ) );
		$this->assertNull( Space_Relay::html_asset_url( $doc, 'my file.css', self::BASE ) );
	}

	// CSS rewriting.

	public function test_rewrite_css_relays_same_host_fonts() {
		$css      = '@font-face{src:url("fonts/a.woff2") format("woff2"),url(\'../b.woff\'),url(c.ttf),url( d.otf ),url(e.eot?#iefix)}';
		$expected = '@font-face{src:url("' . self::BASE . 'asset/a.space.fast/css/fonts/a.woff2") format("woff2"),'
			. 'url("' . self::BASE . 'asset/a.space.fast/b.woff"),'
			. 'url("' . self::BASE . 'asset/a.space.fast/css/c.ttf"),'
			. 'url("' . self::BASE . 'asset/a.space.fast/css/d.otf"),'
			. 'url("' . self::BASE . 'asset/a.space.fast/css/e.eot")}';
		$this->assertSame( $expected, Space_Relay::rewrite_css( $css, 'https://a.space.fast/css/site.css', self::BASE ) );
	}

	public function test_rewrite_css_makes_other_same_host_urls_absolute() {
		$css = '.a{background:url(img/a.png)}.b{background:url("/b.svg#x")}';
		$this->assertSame(
			'.a{background:url("https://a.space.fast/css/img/a.png")}.b{background:url("https://a.space.fast/b.svg#x")}',
			Space_Relay::rewrite_css( $css, 'https://a.space.fast/css/site.css', self::BASE )
		);
	}

	public function test_rewrite_css_relays_imports() {
		$css = '@import "base.css";@import url(theme.css) screen;@import \'/x.css\';';
		$this->assertSame(
			'@import "' . self::BASE . 'asset/a.space.fast/css/base.css";'
			. '@import url("' . self::BASE . 'asset/a.space.fast/css/theme.css") screen;'
			. '@import "' . self::BASE . 'asset/a.space.fast/x.css";',
			Space_Relay::rewrite_css( $css, 'https://a.space.fast/css/site.css', self::BASE )
		);
	}

	public function test_rewrite_css_leaves_data_and_other_hosts_alone() {
		$css = '.a{background:url(data:image/png;base64,AAAA)}@font-face{src:url(https://cdn.example.com/f.woff2)}@import "https://cdn.example.com/x.css";.f{filter:url(#f)}';
		$this->assertSame( $css, Space_Relay::rewrite_css( $css, 'https://a.space.fast/css/site.css', self::BASE ) );
	}

	public function test_rewrite_css_is_case_insensitive() {
		$this->assertSame(
			'@font-face{src:url("' . self::BASE . 'asset/a.space.fast/A.WOFF2")}',
			Space_Relay::rewrite_css( '@font-face{src:URL(A.WOFF2)}', 'https://a.space.fast/', self::BASE )
		);
	}

	public function test_cache_key_has_the_transient_prefix_and_depends_on_every_input() {
		$key = Space_Relay::cache_key( self::BASE, 'https://a.space.fast/' );
		$this->assertStringStartsWith( 'spacefast_block_relay_', $key );
		$this->assertLessThanOrEqual( 172, strlen( $key ) );
		$this->assertNotSame( $key, Space_Relay::cache_key( self::BASE, 'https://a.space.fast/x' ) );
		$this->assertNotSame( $key, Space_Relay::cache_key( 'http://other.test/?rest_route=/spacefast-block/v1/', 'https://a.space.fast/' ) );
	}
}
