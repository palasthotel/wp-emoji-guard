#!/bin/sh
# Stages public/ in build/emoji-guard/ - exactly what is deployed to WordPress.org -
# and zips it to emoji-guard.zip in the project root.
#
# The build directory is left in place on purpose: the release workflow rsyncs from
# it into the SVN checkout, so the zip and the SVN trunk are byte-identical.
#
# The plugin is a single PHP file plus translations: nothing to compile or install.
set -e

PLUGIN_SLUG="emoji-guard"
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
PROJECT_PATH=$(cd "$SCRIPT_DIR/.." && pwd)
BUILD_PATH="$PROJECT_PATH/build"
DEST_PATH="$BUILD_PATH/$PLUGIN_SLUG"

if [ ! -f "$PROJECT_PATH/public/plugin.php" ]; then
  echo "public/plugin.php is missing - is this the right directory?" >&2
  exit 1
fi

echo "Generating build directory..."
rm -rf "$BUILD_PATH"
mkdir -p "$DEST_PATH"

echo "Syncing files..."
# -L resolves symlinks into real files: the Swiss translations link to the German
# one, and wordpress.org discards symlinks when it builds the download.
rsync -rL "$PROJECT_PATH/public/" "$DEST_PATH/"

echo "Generating zip file..."
cd "$BUILD_PATH"
zip -q -r "${PLUGIN_SLUG}.zip" "$PLUGIN_SLUG/"
mv "${PLUGIN_SLUG}.zip" "$PROJECT_PATH/"

echo "${PLUGIN_SLUG}.zip file generated!"
echo "Build done!"
