<?php
/**
 * Plugin Name: GlotFrame
 * Plugin URI: https://github.com/deckerweb/glotframe
 * Description: Shape your GlotPress workspace with block headers, footers and flexible layouts.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.3
 * Author: David Decker – DECKERWEB
 * Author URI: https://github.com/deckerweb
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: glotframe
 * Domain Path: /languages/
 * Update URI: https://github.com/deckerweb/glotframe
 * GitHub Plugin URI: https://github.com/deckerweb/glotframe
 *
 * Copyright © 2017–2026 David Decker – DECKERWEB.
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @package GlotFrame
 */

defined( 'ABSPATH' ) || exit;
define( 'GLOTFRAME_FILE', __FILE__ );
define( 'GLOTFRAME_VERSION', '1.0.0' );
define( 'GLOTFRAME_REPOSITORY', 'https://github.com/deckerweb/glotframe' );
require_once __DIR__ . '/includes/deckerweb-plugin-library/bootstrap.php';
deckerweb_library_register_v2( __FILE__, array(), __DIR__ . '/includes/deckerweb-plugin-library' );
foreach ( array( 'icons', 'localization', 'statistics', 'blocks', 'presets', 'settings', 'routing', 'navigation', 'layout-rest-controller', 'layouts', 'layout-admin', 'shell', 'admin-chrome', 'admin', 'components', 'site-health', 'commands' ) as $glotframe_module ) {
	require_once __DIR__ . '/includes/class-' . $glotframe_module . '.php';
}
\Deckerweb\GlotFrame\Layouts::register_hooks();
\Deckerweb\GlotFrame\Layout_Admin::register_hooks();
\Deckerweb\GlotFrame\Shell::register_hooks();
\Deckerweb\GlotFrame\Admin::register_hooks();
\Deckerweb\GlotFrame\Components::register_hooks();

\Deckerweb\GlotFrame\Routing::register_hooks();
\Deckerweb\GlotFrame\Navigation::register_hooks();

\Deckerweb\GlotFrame\Blocks::register_hooks();

\Deckerweb\GlotFrame\Localization::register_hooks();
\Deckerweb\GlotFrame\Statistics::register_hooks();

\Deckerweb\GlotFrame\Admin_Chrome::register_hooks();

\Deckerweb\GlotFrame\Site_Health::register_hooks();
\Deckerweb\GlotFrame\Commands::register_hooks();
