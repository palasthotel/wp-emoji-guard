=== Emoji Guard ===
Contributors: palasthotel, edwardbock, mkernel, janaeggebrecht
Donate link: https://palasthotel.de/
Tags: emoji, migration, utf8mb4, database, integrity
Requires at least: 4.0
Tested up to: 7.1.2
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Warns administrators when a database migration has broken the site's emojis.

== Description ==

Emojis are the first thing a careless database migration loses: a dump written in the wrong character set turns them into question marks, and a search and replace that does not adjust serialized string lengths makes the stored data unreadable.

Emoji Guard keeps a small reference value with emojis in the database - in the options table and, in a hidden post, in the posts and post meta tables, where your content lives. On every admin screen it compares those values with what they should be, and if one differs, administrators get a warning that says which table is affected, what was expected and what was found. The same check appears as a test under Tools > Site Health. Once the cause is fixed, the button in the warning stores the reference value again.

The plugin does not touch any other data and does not repair anything - it tells you that the migration needs another look before you notice it in your content. Deleting the plugin removes its reference values and the hidden post.

The reference value can be changed with the `emoji_guard_value` filter.

== Installation ==

1. Install the plugin from Plugins > Add New, or upload `emoji-guard.zip` there
1. Activate it - this stores the reference value
1. After a migration, open any admin screen: a warning appears if the emojis did not survive

== Frequently Asked Questions ==

= Who sees the warning? =

Users who can manage options, which on a default site means administrators. The result is also listed under Tools > Site Health.

= What is the hidden post? =

A post of the private type `emoji_guard` that carries the reference value in the posts and post meta tables. It is not public, has no admin screen, is not exported and does not show up in search or the REST API. It catches migrations that copied only some tables, or damaged the content tables but not the options.

= The warning appeared on a site I did not migrate. =

Then something else rewrote the database - a backup restore, a plugin converting tables, or a search and replace. Check a few posts with emojis; if they are fine, store the reference value again with the button.

== Changelog ==

= 1.0.1 =
**Bug Fixes**
* show the integrity warning to administrators only (d73c40d)
* translate the integrity warning (10900f3)

= 1.0.0 =
First release
