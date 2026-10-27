=== GlotFrame ===
Contributors: daveshine
Tags: glotpress, translation, layout, blocks
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shape your GlotPress workspace with block headers, footers and flexible layouts.

== Description ==
GlotFrame turns GlotPress into a translation portal with its own identity and a clearer interface.

WordPress block headers and footers, coordinated designs, improved project views and useful statistics help translators find their way. Set it up directly in WordPress without writing custom PHP.

A deckerweb tool for anyone who wants to shape GlotPress more easily and use it more comfortably.

* Build headers and footers with WordPress blocks — Design headers, footers and notice areas in the block editor. Seven starting patterns and five dynamic GlotPress blocks help you build your own layouts.
* A design that fits your identity — Choose boxed or full-width layouts, eight color presets, custom colors and background images. Give your workspace coordinated forms, buttons and readable tables.
* Keep large project collections organized — Search root projects and subprojects and switch between list and grid views. A searchable parent-project selector makes long project lists easier to navigate.
* Fewer steps in everyday translation work — A configurable toolbar links to projects and subprojects. Authorized users can reach the native forms for new projects and subprojects directly from the navigation.
* Use GlotPress as your translation portal — Optionally use GlotPress as the homepage and choose its URL path. Headers, footers and additional content give your portal its own identity.
* See translation progress at a glance — Metrics, charts and project comparisons show the current state. Operators get an overview, while signed-in translators can review their own contributions and selected project groups.
* German with informal or formal address — Choose informal or formal German for the GlotPress interface. The WordPress administration language and actual project translations remain independent.
* Convenient administration, including Multisite — Duplicate layouts, save changes conveniently and use Site Health and the native Command Palette. Each Multisite website keeps its own settings and layouts.

== Installation ==
Upload and activate GlotFrame, activate GlotPress, then open GlotFrame > Settings. Publish block layouts, select them, enable the layout and save.

== Frequently Asked Questions ==

= Where can I find layout patterns and GlotPress data blocks? =
Edit a GlotFrame layout and open the block inserter. Patterns appear under GlotFrame layouts; the five data blocks appear under GlotFrame. For context-dependent previews, choose a project in the block settings. Existing layouts are never replaced automatically.

= Do I need a specific theme or page builder? =
No. GlotFrame supplies its own surrounding layout and uses WordPress presentation blocks. It does not import your active theme’s header or builder templates. Create and edit GlotFrame layouts with the WordPress block editor. Layout content created with Bricks, Elementor or other page builders is not supported. A builder edit button does not imply that its content can be displayed by GlotFrame.

= Why is my layout missing from the selector? =
Only published GlotFrame layouts without password protection can be selected. Drafts and private layouts are never shown to visitors.

= Which blocks can I use? =
Groups, columns, text, headings, images, lists, buttons, spacing, site identity, navigation, social links and details, plus GlotPress project, action and progress blocks. Query, post-content, HTML, shortcode and reusable-content blocks are excluded.

= Does it change translation data or exports? =
No. GlotFrame changes the surrounding HTML layout. Translation permissions, forms, downloads and API responses remain GlotPress’s responsibility.

= How does Multisite work? =
Network activation makes GlotFrame available to every website. Each website keeps its own settings and layouts; there are no network-wide design overrides.

= What happens when I deactivate or delete it? =
Deactivation preserves everything. Deletion preserves settings and layouts by default. Enable the deletion option only if you want to remove this website’s GlotFrame content too. Other plugins and translation data are retained.

Complete guide and FAQ: https://github.com/deckerweb/glotframe/wiki

== Changelog ==

= 1.0.0 =
* New: Initial release: block headers, footers and notice areas; seven layout patterns and five dynamic GlotPress blocks; boxed and full-width layouts with eight color presets, custom colors and backgrounds.
* Improved: Searchable project lists and grids, searchable parent-project selection, clearer translation and glossary tables, configurable toolbar navigation and placement, and optional portal homepage and URL path.
* Misc: Includes German informal and formal interface translations, current operator and personal statistics, layout management shortcuts, Site Health information and contextual WordPress Command Palette actions. Requires WordPress 6.4, PHP 8.3 and active GlotPress 4.x for workspace features; settings and layouts remain available when GlotPress is absent.

