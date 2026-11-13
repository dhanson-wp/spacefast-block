<?php
/**
 * PHPUnit bootstrap. Pure PHP classes only; no WordPress is loaded.
 *
 * @package Spacefast_Block
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-space-url.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-space-display.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-space-metadata.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-api-client.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-connection.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-space-lookup.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-takeover.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-space-relay.php';
