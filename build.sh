#!/usr/bin/env bash
#
# Packages the addon into dist/moloni_on.zip, ready to drop into a WHMCS
# install at modules/addons/moloni_on. Portable replacement for the PT
# plugin's build.bat.
#
# Usage:
#   ./build.sh            # reuse existing vendor/ if present
#   ./build.sh --install  # force a fresh prod (no-dev) composer install
#
set -euo pipefail

MODULE="moloni_on"
DIST="dist"
ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

# Prod dependencies (no dev tooling in the shipped package).
if [ ! -d vendor ] || [ "${1:-}" = "--install" ]; then
    composer install --no-dev --prefer-dist --no-progress --optimize-autoloader
fi

rm -rf "$DIST" "$MODULE"
mkdir -p "$MODULE"

# Files/dirs that make up the shipped module.
cp moloni_on.php hooks.php composer.json "$MODULE"/
[ -f LICENSE.md ] && cp LICENSE.md "$MODULE"/

for dir in src templates public lang vendor; do
    [ -d "$dir" ] && cp -R "$dir" "$MODULE"/
done

mkdir -p "$DIST"
zip -rq "$DIST/$MODULE.zip" "$MODULE"
rm -rf "$MODULE"

echo "Built $DIST/$MODULE.zip"
