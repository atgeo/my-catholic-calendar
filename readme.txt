=== Kalenda ===
Contributors: georgeskmeid
Tags: catholic, liturgical calendar, liturgy, calendar, litcal
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display the Catholic liturgical calendar on any WordPress theme, powered by the LitCal API.

== Description ==

Kalenda brings the Catholic liturgical calendar to WordPress. It uses the open **LitCal API** to show liturgical celebrations, their rank, liturgical colour and season — for the General Roman Calendar as well as national and diocesan calendars, in multiple languages.

Kalenda is built for modern WordPress:

* **Blocks that work with any theme** — a liturgical calendar block and a "liturgical day" block, server-rendered for speed and SEO and made interactive with the WordPress Interactivity API.
* **A cached REST API** (`kalenda/v1`) so your own themes and plugins can read liturgical data without calling the upstream service directly.
* **Clean, standards-based code** — PSR-4 autoloading, the WordPress Coding Standards and static analysis.

Liturgical data is provided by the [LitCal project](https://litcal.johnromanodorazio.com/) by John Romano D'Orazio.

== Installation ==

1. Upload the `kalenda` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Add a **Kalenda** block to any post or page, or configure defaults under **Settings → Kalenda**.

== External Services ==

Kalenda connects to the public LitCal API to retrieve Catholic liturgical calendar data.

When data is requested, the plugin sends:
* The requested liturgical year.
* The requested year type.
* The requested locale.
* The requested calendar identifier (for national or diocesan calendars, when applicable).

No personal information, user accounts, or site content is transmitted.

Requests are made only when liturgical data is required. Responses are cached locally in WordPress to reduce external requests.

LitCal API:
https://litcal.johnromanodorazio.com/

LitCal API documentation:
https://github.com/Liturgical-Calendar/LiturgicalCalendarAPI

== Frequently Asked Questions ==

= Does this require an account or API key? =

No. Kalenda talks to the public LitCal API. Responses are cached in WordPress so pages stay fast and upstream requests are minimised.

= Which calendars are supported? =

The General Roman Calendar plus every national and diocesan calendar published by the LitCal project, in the locales they provide.

= Does it work with my theme? =

Yes. Blocks are server-rendered with theme-agnostic markup and liturgical colours exposed as CSS custom properties, so any block or classic theme can style them.

== Changelog ==

= 0.1.0 =
* Initial development release: project scaffolding, architecture and tooling.
