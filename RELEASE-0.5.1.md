# Independent Builder 0.5.1 — Shared Global Design System

## What changed
- Added `includes/class-ib-global-styles.php`.
- Shared settings are stored once in the WordPress option `ib_global_design_system`.
- Added `GET` and `POST /wp-json/independent-builder/v1/global-styles` endpoints.
- Reading requires `edit_pages`; saving requires `manage_options`.
- The editor now has global colours, typography, spacing, border and shadow controls.
- Saving the system changes all Independent Builder-managed pages on the site.
- Frontend CSS variables are generated from the shared settings and loaded only on singular pages containing a builder document.
- Existing v0.4 document-level styles remain a rendering fallback until a site-wide design system is saved.

## Install/update
1. Back up the database and current plugin directory.
2. Commit the v0.4 code in Git before updating.
3. Deactivate Independent Builder.
4. Replace the `independent-builder` plugin folder with this release.
5. Reactivate the plugin.
6. Open a builder page and click an empty area in the canvas to show Site-wide design system controls.
7. Adjust values and click Save site design system.
8. Open a second builder-managed page in a separate tab and check that the same global tokens are active.

## Storage
Option key: `ib_global_design_system`.
The option is stored with autoload disabled because it is only needed on builder-managed pages. The REST save callback checks `manage_options` before updating it.

## REST API
- GET `/wp-json/independent-builder/v1/global-styles`: available to users with `edit_pages`.
- POST `/wp-json/independent-builder/v1/global-styles`: available to users with `manage_options`; body is `{ "settings": { ... } }`.

## Compatibility behaviour
If the global option has never been saved, the renderer continues to use legacy `settings.globalStyles` values embedded in v0.4 documents. The editor loads those legacy values as a migration suggestion. Saving them through the new panel creates the shared option. Once configured, shared site-wide values take precedence.

## Important limitations
- The typography dropdown uses a controlled allowlist of system fonts; no remote font provider is connected.
- The design system does not yet supply a font upload facility.
- Spacing tokens are emitted as CSS variables. A container's padding can reference a token using, for example, `var(--ib-space-lg)`.
- This is a development release. Validate on Local/staging before production use.
