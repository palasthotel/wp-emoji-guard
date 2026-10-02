# Emoji Guard (WordPress-Plugin)

Emoji Guard warns administrators when a database migration has broken the site's emojis.
It is available on [WordPress.org](https://wordpress.org/plugins/emoji-guard/).

## Why

Emojis are what a careless migration loses first. A dump written with the wrong
character set turns them into question marks; a search and replace that does not adjust
the lengths in serialized strings leaves the stored value unreadable. Either is easy to
miss until it shows up in published content.

## How it works

On activation the plugin stores a reference value, `🛡🦸‍♂️`, as a serialized array in the
option `_emoji_guard_validation`. On every admin screen it compares the stored value with
the expected one. If they differ, users with `manage_options` see a warning that names
both values - or says that the stored one is missing or unreadable, which is what a
damaged serialization looks like - and a button that stores the reference value again
once the migration is sorted out.

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
