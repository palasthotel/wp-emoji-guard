# CI/CD Workflows

The four workflows in `.github/workflows/` call the shared ones in
[palasthotel/github-workflows](https://github.com/palasthotel/github-workflows). How
they work, every input and what to do when a deploy fails is described there, in
[docs/wp-plugin.md](https://github.com/palasthotel/github-workflows/blob/main/docs/wp-plugin.md).

What is specific to this plugin:

| | |
|---|---|
| wordpress.org slug | `emoji-guard` |
| version file | `package.json` (`release-type: node`) - keep it, release-please and the scripts read the version there |
| build step | none |
| SVN | the 1.0.0 trunk held the Swiss translations as symlinks; the shared deploy turns them into files |
