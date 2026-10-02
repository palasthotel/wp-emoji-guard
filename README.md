# Emoji Guard (WordPress-Plugin)

Emoji Guard warns administrators when a database migration has broken the site's emojis.
It is available on [WordPress.org](https://wordpress.org/plugins/emoji-guard/).

## Why

Emojis are what a careless migration loses first. A dump written with the wrong
character set turns them into question marks; a search and replace that does not adjust
the lengths in serialized strings leaves the stored value unreadable. Either is easy to
miss until it shows up in published content.

## How it works

On activation the plugin stores a reference value, `🛡🦸‍♂️`, in three places:

| Table | Where | Catches |
|---|---|---|
| `wp_options` | option `_emoji_guard_validation`, serialized | a wrong character set, broken serialization |
| `wp_posts` | `post_content` of the one post of type `emoji_guard` | content tables copied with a wrong character set |
| `wp_postmeta` | meta `_emoji_guard_validation` of that post, serialized | broken serialization in meta, content tables not migrated |

The post type is not public, has no admin screen, is not exported and is not in REST or
search. Sites updating from 1.0 get the post on the first admin request; only a post that
existed and later went missing counts as a finding.

On every admin screen the plugin compares the stored values with the expected one - read
from the database itself, not through the object cache, which could otherwise still hold
the values from before a database was replaced. If one differs, users with
`manage_options` see a warning naming the table and both values, or saying that the
stored one is missing or unreadable, and a button that stores the reference again once
the migration is sorted out. The same check is a direct test in Site Health
(`emoji_guard`), with the same button; the notice stays off that screen.

Deleting the plugin (`uninstall.php`) removes both options and the post, on every site
of a network.

The plugin changes no other data and repairs nothing.

| Hook | Type | Purpose |
|---|---|---|
| `emoji_guard_value` | filter | the reference value; receives and returns a string |

## Repository layout

`public/` is exactly what ships to wordpress.org; everything else is repository-only.
`plugin.php` in the root is a development wrapper that loads `public/`, so the whole
repository can be symlinked into `wp-content/plugins` during development.

Releases are cut by release-please from conventional commits and deployed to the
wordpress.org SVN by GitHub Actions — see [.github/WORKFLOWS.md](.github/WORKFLOWS.md).
Contribution rules and the local setup are in [CONTRIBUTING.md](CONTRIBUTING.md).

## License

GPL-3.0-or-later, see [LICENSE](LICENSE).
