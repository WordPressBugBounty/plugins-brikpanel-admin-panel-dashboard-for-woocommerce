<?php
/**
 * Visitor country list (generated, do not edit).
 *
 * Built 2026-10-25 by tools/build-ip-countries.php from DB-IP's free "IP to
 * Country Lite" database, 2026-10 edition (CC BY 4.0, https://db-ip.com).
 * The two sorted text files beside this one hold the list:
 *
 *   brikpanel-ip-countries-v4.txt  one line per IPv4 range: its first address
 *                                  as 8 hex digits, a space, the country code
 *   brikpanel-ip-countries-v6.txt  one line per run of IPv6 /32 blocks: the
 *                                  block's first 32 bits as 8 hex digits, a
 *                                  space, the country most of the block holds
 *
 * A line holds until the next one starts. Every line is 12 bytes, so the
 * lookup reads a line by its position instead of loading the file. "ZZ"
 * means no country (private, reserved or unassigned addresses).
 * Read by brikpanel_ip_country().
 *
 * @package BrikPanel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'built'   => '2026-10-25',
	'source'  => 'DB-IP IP to Country Lite 2026-10',
	'license' => 'CC BY 4.0',
	'sha256'  => '097426b8ddae89157d444a59ac1847e873f7943c32d52becc4371c8b0273af80',
	'v4'      => 362122,
	'v6'      => 92615,
];
