#!/usr/bin/env bash
#
# Packages the addon into dist/moloni_on.zip, ready to drop into a WHMCS
# install at modules/addons/moloni_on. Portable replacement for the PT
# plugin's build.bat.
#
# Usage:
#   ./build.sh                # fresh prod (no-dev) install, then package (default)
#   ./build.sh --install      # same as default (kept for backwards compat)
#   ./build.sh --skip-install # reuse an existing vendor/ known to be prod-only
#
set -euo pipefail

MODULE="moloni_on"
DIST="dist"
ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

# Ship production dependencies only: dev tooling (PHPUnit, phpcs) must never end
# up in the package. A fresh --no-dev install is therefore the DEFAULT — reusing
# whatever is in vendor/ risked bundling dev deps left there by `composer
# install`. Pass --skip-install to opt out when vendor/ is already prod-only.
if [ "${1:-}" = "--skip-install" ]; then
    if [ ! -d vendor ]; then
        echo "error: --skip-install given but vendor/ is missing" >&2
        exit 1
    fi
else
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
if command -v zip >/dev/null 2>&1; then
    zip -rq "$DIST/$MODULE.zip" "$MODULE"
elif command -v python3 >/dev/null 2>&1; then
    # Fallback when the `zip` binary isn't installed (e.g. minimal WSL).
    python3 -c "import shutil,sys; shutil.make_archive(sys.argv[1], 'zip', root_dir='.', base_dir=sys.argv[2])" "$DIST/$MODULE" "$MODULE"
else
    echo "error: need either 'zip' or 'python3' to package the module" >&2
    exit 1
fi
rm -rf "$MODULE"

echo "Built $DIST/$MODULE.zip"
