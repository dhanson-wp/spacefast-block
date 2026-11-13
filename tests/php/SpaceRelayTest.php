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
	const SIG  = '0123456789abcdef';
	const SEG  = 'a.space.fast~0123456789abcdef';
	const PAGE = 'https://a.space.fast/docs/page.html';

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
		$this->assertSame( $case['expected'], Space_Relay::page_relay_url( $case['relayBase'], $case['url'], $case['startPath'], $case['signature'] ) );
	}

	/**
	 * @dataProvider relay_cases
	 */
	public function test_relay_url_matches_shared_cases( array $case ) {
		$this->assertSame( $case['expected'], Space_Relay::relay_url( $case['relayBase'], $case['kind'], $case['url'], $case['signature'] ) );
	}

	public function test_csp_is_the_sandbox_list() {
		$this->assertSame( 'sandbox allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads', Space_Relay::CSP );
		$this->assertSame( 'allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads', Space_Relay::SANDBOX );
		$this->assertStringNotContainsString( 'allow-same-origin', Space_Relay::CSP );
		$this->assertStringNotContainsString( 'allow-top-navigation', Space_Relay::CSP );
	}

	// Signed hosts.

	public function test_host_signature_is_a_keyed_16_character_hash() {
		$sig = Space_Relay::host_signature( 'a.space.fast', 'secret' );
		$this->assertSame( substr( hash_hmac( 'md5', 'spacefast-relay|a.space.fast', 'secret' ), 0, 16 ), $sig );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{16}$/', $sig );
		$this->assertNotSame( $sig, Space_Relay::host_signature( 'b.space.fast', 'secret' ) );
		$this->assertNotSame( $sig, Space_Relay::host_signature( 'a.space.fast', 'other secret' ) );
	}

	public function test_signed_segment_round_trips() {
		$segment = Space_Relay::signed_segment( 'A.Space.Fast', 'secret' );
		$this->assertSame( 'a.space.fast~' . Space_Relay::host_signature( 'a.space.fast', 'secret' ), $segment );
		$this->assertSame( 'a.space.fast', Space_Relay::verified_host( $segment, 'secret' ) );
	}

	/**
	 * @dataProvider refused_segments
	 */
	public function test_verified_host_refuses( $segment ) {
		$this->assertNull( Space_Relay::verified_host( $segment, 'secret' ) );
	}

	public function refused_segments() {
		$good = Space_Relay::host_signature( 'a.space.fast', 'secret' );
		return array(
			'unsigned'          => array( 'a.space.fast' ),
			'empty signature'   => array( 'a.space.fast~' ),
			'wrong signature'   => array( 'a.space.fast~' . str_repeat( '0', 16 ) ),
			'other host'        => array( 'b.space.fast~' . $good ),
			'other secret'      => array( 'a.space.fast~' . Space_Relay::host_signature( 'a.space.fast', 'nope' ) ),
			'truncated'         => array( 'a.space.fast~' . substr( $good, 0, 15 ) ),
			'uppercase host'    => array( 'A.space.fast~' . $good ),
			'two tildes'        => array( 'a.space.fast~' . $good . '~x' ),
			'empty'             => array( '' ),
		);
	}

	// Upstream URLs.

	public function test_upstream_url_for_home_page() {
		$this->assertSame( 'https://a.space.fast/', Space_Relay::upstream_url( 'a.space.fast', '' ) );
	}

	public function test_upstream_url_joins_the_path() {
		$this->assertSame( 'https://a.space.fast/docs/a.html', Space_Relay::upstream_url( 'a.space.fast', 'docs/a.html' ) );
	}

	public function test_upstream_url_re_encodes_a_decoded_path() {
		$this->assertSame( 'https://a.space.fast/my%20file%25.css', Space_Relay::upstream_url( 'a.space.fast', 'my file%.css' ) );
		$this->assertSame( 'https://a.space.fast/caf%C3%A9/', Space_Relay::upstream_url( 'a.space.fast', "caf\xC3\xA9/" ) );
		$this->assertSame( 'https://a.space.fast/a%3Fb', Space_Relay::upstream_url( 'a.space.fast', 'a?b' ) );
	}

	/**
	 * @dataProvider refused_upstreams
	 */
	public function test_upstream_url_refuses( $host, $path ) {
		$this->assertNull( Space_Relay::upstream_url( $host, $path ) );
	}

	public function refused_upstreams() {
		return array(
			'dot dot'            => array( 'a.space.fast', '../x' ),
			'dot dot inside'     => array( 'a.space.fast', 'a/../x' ),
			'dot'                => array( 'a.space.fast', 'a/./x' ),
			'at sign'            => array( 'a.space.fast', 'a@b/' ),
			'backslash'          => array( 'a.space.fast', 'a\\b' ),
			'control character'  => array( 'a.space.fast', "a\x01b" ),
			'empty segment'      => array( 'a.space.fast', 'a//b' ),
			'leading slash'      => array( 'a.space.fast', '/etc' ),
			'host with port'     => array( 'a.space.fast:8443', '' ),
			'host with userinfo' => array( 'u@a.space.fast', '' ),
			'host with path'     => array( 'a.space.fast/x', '' ),
			'ip host'            => array( '127.0.0.1', '' ),
			'localhost'          => array( 'localhost', '' ),
			'empty host'         => array( '', '' ),
		);
	}

	// Redirects.

	public function test_page_redirect_to_trailing_slash_is_followed() {
		$this->assertSame( 'https://a.space.fast/docs/', Space_Relay::page_redirect_target( 'https://a.space.fast/docs', '/docs/' ) );
		$this->assertSame( 'https://a.space.fast/docs/', Space_Relay::page_redirect_target( 'https://a.space.fast/docs', 'https://a.space.fast/docs/' ) );
		$this->assertSame( 'https://a.space.fast/docs/', Space_Relay::page_redirect_target( 'https://a.space.fast/docs', 'docs/' ) );
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
			'adds a query'    => array( 'https://a.space.fast/docs', '/docs/?x=1' ),
			'empty query'     => array( 'https://a.space.fast/docs', '/docs/?' ),
			'unsafe path'     => array( 'https://a.space.fast/a@b', '/a@b/' ),
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
	public function test_link_is_relayed( $rel, $as_type, $expected ) {
		$this->assertSame( $expected, Space_Relay::is_relayed_link( $rel, $as_type ) );
	}

	public function link_rules() {
		return array(
			'stylesheet'      => array( 'stylesheet', '', true ),
			'alternate sheet' => array( 'Alternate  Stylesheet', '', true ),
			'preload font'    => array( 'preload', 'font', true ),
			'preload style'   => array( 'preload', 'style', true ),
			'preload script'  => array( 'preload', 'SCRIPT', true ),
			'preload image'   => array( 'preload', 'image', false ),
			'modulepreload'   => array( 'modulepreload', '', true ),
			'icon'            => array( 'icon', '', false ),
			'canonical'       => array( 'canonical', '', false ),
			'prefetch'        => array( 'prefetch', 'script', false ),
			'empty'           => array( '', '', false ),
		);
	}

	public function test_only_module_scripts_are_relayed() {
		$this->assertTrue( Space_Relay::is_relayed_script( 'module' ) );
		$this->assertTrue( Space_Relay::is_relayed_script( ' Module ' ) );
		$this->assertFalse( Space_Relay::is_relayed_script( '' ) );
		$this->assertFalse( Space_Relay::is_relayed_script( 'text/javascript' ) );
	}

	public function test_html_asset_url_relays_the_page_host_only() {
		$doc = 'https://a.space.fast/docs/';
		$this->assertSame( self::BASE . 'asset/' . self::SEG . '/docs/app.css', Space_Relay::html_asset_url( $doc, self::PAGE, 'app.css', self::BASE, self::SIG ) );
		$this->assertSame( self::BASE . 'asset/' . self::SEG . '/assets/app.js', Space_Relay::html_asset_url( $doc, self::PAGE, '/assets/app.js?v=1', self::BASE, self::SIG ) );
		$this->assertNull( Space_Relay::html_asset_url( $doc, self::PAGE, 'https://fonts.googleapis.com/css2?family=X', self::BASE, self::SIG ) );
		$this->assertNull( Space_Relay::html_asset_url( $doc, self::PAGE, 'https://b.space.fast/app.css', self::BASE, self::SIG ) );
		$this->assertNull( Space_Relay::html_asset_url( $doc, self::PAGE, 'data:text/css,a{}', self::BASE, self::SIG ) );
		$this->assertNull( Space_Relay::html_asset_url( $doc, self::PAGE, 'my file.css', self::BASE, self::SIG ) );
	}

	public function test_html_asset_url_ignores_a_base_on_another_host() {
		$evil = 'https://evil.com/';
		$this->assertNull( Space_Relay::html_asset_url( $evil, self::PAGE, 'x.css', self::BASE, self::SIG ) );
		$this->assertNull( Space_Relay::html_asset_url( $evil, self::PAGE, 'https://evil.com/x.css', self::BASE, self::SIG ) );
		$this->assertSame( self::BASE . 'asset/' . self::SEG . '/x.css', Space_Relay::html_asset_url( $evil, self::PAGE, 'https://a.space.fast/x.css', self::BASE, self::SIG ) );
	}

	// CSS rewriting.

	private static function css( string $css, string $url = 'https://a.space.fast/css/site.css' ): string {
		return Space_Relay::rewrite_css( $css, $url, self::BASE, self::SIG );
	}

	public function test_rewrite_css_relays_same_host_fonts() {
		$css      = '@font-face{src:url("fonts/a.woff2") format("woff2"),url(\'../b.woff\'),url(c.ttf),url( d.otf ),url(e.eot?#iefix)}';
		$expected = '@font-face{src:url("' . self::BASE . 'asset/' . self::SEG . '/css/fonts/a.woff2") format("woff2"),'
			. 'url("' . self::BASE . 'asset/' . self::SEG . '/b.woff"),'
			. 'url("' . self::BASE . 'asset/' . self::SEG . '/css/c.ttf"),'
			. 'url("' . self::BASE . 'asset/' . self::SEG . '/css/d.otf"),'
			. 'url("' . self::BASE . 'asset/' . self::SEG . '/css/e.eot")}';
		$this->assertSame( $expected, self::css( $css ) );
	}

	public function test_rewrite_css_makes_other_same_host_urls_absolute() {
		$this->assertSame(
			'.a{background:url("https://a.space.fast/css/img/a.png")}.b{background:url("https://a.space.fast/b.svg#x")}',
			self::css( '.a{background:url(img/a.png)}.b{background:url("/b.svg#x")}' )
		);
	}

	public function test_rewrite_css_relays_imports() {
		$this->assertSame(
			'@import "' . self::BASE . 'asset/' . self::SEG . '/css/base.css";'
			. '@import url("' . self::BASE . 'asset/' . self::SEG . '/css/theme.css") screen;'
			. '@import "' . self::BASE . 'asset/' . self::SEG . '/x.css";',
			self::css( '@import "base.css";@import url(theme.css) screen;@import \'/x.css\';' )
		);
	}

	public function test_rewrite_css_leaves_data_and_other_hosts_alone() {
		$css = '.a{background:url(data:image/png;base64,AAAA)}@font-face{src:url(https://cdn.example.com/f.woff2)}@import "https://cdn.example.com/x.css";.f{filter:url(#f)}';
		$this->assertSame( $css, self::css( $css ) );
	}

	public function test_rewrite_css_is_case_insensitive() {
		$this->assertSame(
			'@font-face{src:url("' . self::BASE . 'asset/' . self::SEG . '/A.WOFF2")}',
			self::css( '@font-face{src:URL(A.WOFF2)}', 'https://a.space.fast/' )
		);
	}

	public function test_rewrite_css_leaves_comments_alone() {
		$this->assertSame(
			"/* url(a.woff) don't @import \"x.css\"; */.a{background:url(\"https://a.space.fast/b.png\")}",
			self::css( "/* url(a.woff) don't @import \"x.css\"; */.a{background:url(b.png)}", 'https://a.space.fast/' )
		);
	}

	public function test_rewrite_css_handles_escaped_quotes() {
		$this->assertSame(
			'.a{background:url("https://a.space.fast/a%22b.png")}.b{background:url("https://a.space.fast/it\'s.png")}',
			self::css( '.a{background:url("a\\"b.png")}.b{background:url(\'it\\\'s.png\')}', 'https://a.space.fast/' )
		);
	}

	public function test_rewrite_css_makes_image_set_strings_absolute() {
		$this->assertSame(
			'.a{background-image:image-set("https://a.space.fast/css/x.png" 1x, url("https://a.space.fast/css/y.png") 2x)}'
			. '.b{background-image:-webkit-image-set("https://a.space.fast/css/z.png" 1x, "https://cdn.example.com/w.png" 2x)}',
			self::css( '.a{background-image:image-set("x.png" 1x, url(y.png) 2x)}.b{background-image:-webkit-image-set(\'z.png\' 1x, "https://cdn.example.com/w.png" 2x)}' )
		);
	}

	public function test_rewrite_css_never_relays_another_host() {
		$this->assertSame(
			'@font-face{src:url("https://b.space.fast/f.woff2")}@import "https://b.space.fast/x.css";',
			self::css( '@font-face{src:url("https://b.space.fast/f.woff2")}@import "https://b.space.fast/x.css";' )
		);
	}

	public function test_rewrite_css_for_a_page_with_a_base_elsewhere_relays_only_the_page_host() {
		$this->assertSame(
			'@font-face{src:url(f.woff2),url("' . self::BASE . 'asset/' . self::SEG . '/g.woff2")}',
			Space_Relay::rewrite_css( '@font-face{src:url(f.woff2),url(https://a.space.fast/g.woff2)}', 'https://evil.com/', self::BASE, self::SIG, self::PAGE )
		);
	}

	// Caching and limits.

	public function test_cache_key_has_the_transient_prefix_and_depends_on_kind_host_and_path() {
		$key = Space_Relay::cache_key( self::BASE, 'page', 'a.space.fast', '' );
		$this->assertStringStartsWith( 'spacefast_block_relay_', $key );
		$this->assertLessThanOrEqual( 172, strlen( $key ) );
		$this->assertNotSame( $key, Space_Relay::cache_key( self::BASE, 'page', 'a.space.fast', 'x' ) );
		$this->assertNotSame( $key, Space_Relay::cache_key( self::BASE, 'asset', 'a.space.fast', '' ) );
		$this->assertNotSame( $key, Space_Relay::cache_key( self::BASE, 'page', 'b.space.fast', '' ) );
	}

	public function test_cache_admit_adds_until_full() {
		$index = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$index = Space_Relay::cache_admit( $index, "k$i", 1300, 1000, 3 );
			$this->assertIsArray( $index );
		}
		$this->assertSame( array( 'k0' => 1300, 'k1' => 1300, 'k2' => 1300 ), $index );
		$this->assertNull( Space_Relay::cache_admit( $index, 'k3', 1300, 1000, 3 ) );
	}

	public function test_cache_admit_renews_a_known_key_when_full() {
		$index = array( 'k0' => 1100, 'k1' => 1100 );
		$this->assertSame( array( 'k0' => 1400, 'k1' => 1100 ), Space_Relay::cache_admit( $index, 'k0', 1400, 1000, 2 ) );
	}

	public function test_cache_admit_drops_expired_and_malformed_entries() {
		$index = array( 'old' => 900, 'k1' => 1100, 'bad' => 'x', 3 => 1200 );
		$this->assertSame( array( 'k1' => 1100, 'k2' => 1300 ), Space_Relay::cache_admit( $index, 'k2', 1300, 1000, 2 ) );
	}

	public function test_budget_allows_up_to_the_limit_per_window() {
		$state = null;
		for ( $i = 0; $i < 3; $i++ ) {
			$state = Space_Relay::budget_take( $state, 1000 + $i, 3, 60 );
			$this->assertIsArray( $state );
		}
		$this->assertSame( array( 'start' => 1000, 'count' => 3 ), $state );
		$this->assertNull( Space_Relay::budget_take( $state, 1059, 3, 60 ) );
	}

	public function test_budget_starts_a_new_window() {
		$this->assertSame( array( 'start' => 1060, 'count' => 1 ), Space_Relay::budget_take( array( 'start' => 1000, 'count' => 3 ), 1060, 3, 60 ) );
	}

	public function test_budget_treats_bad_state_as_new() {
		$this->assertSame( array( 'start' => 5, 'count' => 1 ), Space_Relay::budget_take( 'junk', 5, 3, 60 ) );
		$this->assertSame( array( 'start' => 5, 'count' => 1 ), Space_Relay::budget_take( array( 'start' => 99, 'count' => 1 ), 5, 3, 60 ) );
	}

	public function test_budget_of_zero_allows_nothing() {
		$this->assertNull( Space_Relay::budget_take( null, 5, 0, 60 ) );
	}

	// Output.

	public function test_emit_sends_status_headers_and_body() {
		$sent    = array();
		$payload = array(
			'status'  => 404,
			'headers' => array( 'Content-Security-Policy' => Space_Relay::CSP ),
			'body'    => 'x',
		);
		$this->assertTrue(
			Space_Relay::emit(
				$payload,
				false,
				static function ( $status ) use ( &$sent ) {
					$sent[] = "status $status";
				},
				static function ( $line ) use ( &$sent ) {
					$sent[] = $line;
				},
				static function ( $body ) use ( &$sent ) {
					$sent[] = "body $body";
				}
			)
		);
		$this->assertSame( array( 'status 404', 'Content-Security-Policy: ' . Space_Relay::CSP, 'body x' ), $sent );
	}

	public function test_emit_sends_nothing_when_headers_already_went() {
		$sent = array();
		$fail = static function ( $value ) use ( &$sent ) {
			$sent[] = $value;
		};
		$this->assertFalse(
			Space_Relay::emit(
				array(
					'status'  => 200,
					'headers' => array( 'Content-Type' => 'text/html' ),
					'body'    => '<script>alert(1)</script>',
				),
				true,
				$fail,
				$fail,
				$fail
			)
		);
		$this->assertSame( array(), $sent );
	}

	// Reporter.

	public function test_compact_script_strips_comments_and_indentation() {
		$js = "/**\n * Doc.\n */\n/* eslint-disable x */\n( function () {\n\t// A note.\n\tvar a = 'http://x/*y*/'; // trailing stays\n\n\tvar re = /\\/\\//;\n} )();\n";
		$this->assertSame( "( function () {\nvar a = 'http://x/*y*/'; // trailing stays\nvar re = /\\/\\//;\n} )();", Space_Relay::compact_script( $js ) );
	}

	public function test_compact_reporter_is_small_and_comment_free() {
		$compact = Space_Relay::compact_script( (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/relay-reporter.js' ) );
		$this->assertLessThan( 6000, strlen( $compact ) );
		$this->assertDoesNotMatchRegularExpression( '#^\s*(/\*|\*|//)#m', $compact );
		$this->assertStringNotContainsString( '</script', $compact );
	}
}
