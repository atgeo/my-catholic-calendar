=== My Catholic Calendar ===
Contributors: atgeo
Tags: catholic, liturgical calendar, liturgy, calendar, church
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display the Catholic liturgical calendar in WordPress using blocks.

== Description ==

My Catholic Calendar brings the Catholic liturgical calendar to WordPress. It uses the open LitCal API to display Catholic liturgical celebrations, including their rank and liturgical color, for the General Roman Calendar.

* More blocks coming soon!

Liturgical data is provided by the [LitCal project](https://litcal.johnromanodorazio.com/) by John Romano D'Orazio.

## Source Code

The full source code of this plugin is publicly available on GitHub:

[https://github.com/atgeo/my-catholic-calendar](https://github.com/atgeo/my-catholic-calendar)

== Installation ==

1. Upload the `my-catholic-calendar` folder to `/wp-content/plugins/`, or install it via the **Plugins** screen in WordPress.
2. Activate the plugin through the **Plugins** menu.
3. Add the **Today's Celebrations** block to any post or page from the block inserter. Configure the calendar, language, and heading from the block settings sidebar.

== External Services ==

This plugin connects to the public LitCal API (Liturgical Calendar API) to fetch Catholic liturgical calendar data — the dates and names of liturgical celebrations (solemnities, feasts, memorials) for the General Roman Calendar and, optionally, specific national or diocesan calendars. This is required for the plugin's core function: displaying the correct liturgical calendar on your site.

Each request sends only:
* Liturgical year and year type (civil or liturgical)
* Locale (language for calendar names)
* Calendar identifier (nation or diocese code), when a non-general
  calendar is selected

No personal information, user accounts, visitor data, or site content is ever transmitted. Requests happen only when calendar data is needed and not already cached; responses are cached locally in WordPress to minimize repeat requests.

This service is provided by John Romano D'Orazio as a free, open-source, volunteer-maintained project. At the time of this writing, it does not publish a dedicated Terms of Service or Privacy Policy page. The project's public source code and license are available here:

* Service: https://litcal.johnromanodorazio.com/
* Source code and license: https://github.com/Liturgical-Calendar/LiturgicalCalendarAPI

== Frequently Asked Questions ==

= Does this require an account or API key? =

No. All responses are cached in WordPress, so pages load quickly, and external requests are minimized.

= Which calendars are supported? =

We currently support the General Roman Calendar.

= Does it work with my theme? =

Yes. The block is server-rendered with clean, theme-agnostic HTML markup, so it works in both block themes and classic themes. Liturgical colors are displayed automatically based on the celebration.

== Changelog ==

= 0.1.0 =
* Initial release.
* Added the Today's Celebrations block.
* Added REST API endpoints for liturgical calendar data.

== Support ==

For support, please contact georgesk117@gmail.com.
