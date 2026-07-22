=== My Catholic Calendar ===
Contributors: atgeo
Tags: catholic, liturgical calendar, liturgy, calendar, church
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display the Catholic liturgical calendar on WordPress using a native block, powered by the LitCal API.

== Description ==

My Catholic Calendar brings the Catholic liturgical calendar to WordPress. It uses the open LitCal API to show Catholic liturgical celebrations, including their rank and liturgical colour, for the General Roman Calendar.

* **Day block** — shows today's liturgical celebration(s).

* More blocks coming soon!

Liturgical data is provided by the [LitCal project](https://litcal.johnromanodorazio.com/) by John Romano D'Orazio.

== Installation ==

1. Upload the `my-catholic-calendar` folder to `/wp-content/plugins/`, or install it via the **Plugins** screen in WordPress.
2. Activate the plugin through the **Plugins** menu.
3. Add the Day block to any post or page from the block inserter. Configure the calendar, language, and heading from the block settings sidebar.

== External Services ==

My Catholic Calendar connects to the public LitCal API to fetch Catholic liturgical calendar data.

When data is requested, the plugin sends only:
* Liturgical year
* Year type
* Locale
* Calendar identifier (when supported)

No personal information, user accounts, or site content is ever transmitted. Requests are made only when needed, and responses are cached locally in WordPress.

* LitCal API: https://litcal.johnromanodorazio.com/
* API Documentation: https://github.com/Liturgical-Calendar/LiturgicalCalendarAPI

== Frequently Asked Questions ==

= Does this require an account or API key? =

No. All responses are cached in WordPress, so pages load quickly, and external requests are minimized.

= Which calendars are supported? =

We currently support the General Roman Calendar.

= Does it work with my theme? =

Yes. The block is server-rendered with clean, theme-agnostic HTML markup, so it works in both block themes and classic themes. Liturgical colours are displayed automatically based on the celebration.

== Changelog ==

= 0.1.0 =
* Initial release.
* Added the Day block for displaying today's liturgical celebrations.
* Added REST API endpoints for liturgical calendar data.
