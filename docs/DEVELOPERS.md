# GlotFrame integration structure

[Deutsch](DEVELOPERS-de.md)

GlotFrame uses WordPress and GlotPress APIs. It only supplies `header.php` and `footer.php` via `gp_tmpl_load_locations`; explicitly supplied GlotPress template directories take precedence. The inner views, permissions and routes belong to GlotPress.

- `Settings`: complete defaults, normalized site-scoped schema and Settings API validation. Stored option: `glotframe_settings`, schema 5. Existing version-one settings inherit safe defaults for new options. Layout IDs are resolved on the current website. Unrecognized input keys are discarded.
- `Layouts`: administrative `glotframe_layout` post type, editor block policy and bounded presentation rendering. Supported nested blocks are checked again during output. The administrative REST controller restricts document access to `manage_options`.
- `Shell`: template selection, request-local regions, asset preparation, head integration and scoped design output. Layouts are rendered before the head to collect block assets. GlotPress's head/footer hooks and the WordPress head/body/footer hooks remain available.
- `Admin`: settings, selectors, local help and accessible changelog dialog.
- `Components`: pinned updater registration and host package identity, version and platform validation. Shared component runtime files are maintained in their respective projects.

The package has no custom database tables or cron jobs. The Library owns its own shared records and cleanup. GlotFrame layout posts and settings survive deactivation and default deletion. Explicit cleanup is evaluated per website and includes revisions, trash and auto-drafts. It never deletes GlotPress content.

Settings migrations must preserve existing user choices. Keep schema changes explicit and test upgrades from the actual preceding package. Presentation changes should not alter API, downloads or the native translation editor's form contracts. Do not rely on non-public class methods as stable third-party APIs.

`Routing` filters the GlotPress base path and redirects only eligible homepage GET/HEAD requests. It preserves WordPress Reading settings. GlotPress refreshes its rewrite rules when the generated base rules change. `Navigation` uses one project query and the native `gp_projects` filters, bounds subproject depth and respects the current website. `Presets` owns named color palettes, luminance calculations and semantic control/status tokens.
