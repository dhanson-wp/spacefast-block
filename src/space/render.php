<?php
/**
 * Front-end render for the Spacefast block. The markup is built in
 * includes/render-helpers.php.
 *
 * @package Spacefast_Block
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- spacefast_block_render() escapes every value it prints.
echo spacefast_block_render( $attributes );
