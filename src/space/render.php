<?php
/**
 * Front-end render for the Spacefast block.
 *
 * @package Spacefast_Block
 */

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped output.
echo '<div ' . get_block_wrapper_attributes() . '></div>';
