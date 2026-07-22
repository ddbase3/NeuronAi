#!/usr/bin/env bash
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
VENDOR_DIR="${1:-$PLUGIN_DIR/src/Vendor}"

php "$PLUGIN_DIR/build/validate-lock.php"
php "$PLUGIN_DIR/build/verify-vendor.php" "$VENDOR_DIR"
find "$VENDOR_DIR" -type f -name '*.php' -print0 \
	| xargs -0 -n1 php -l >/dev/null

echo "Vendor PHP syntax OK: $VENDOR_DIR"
