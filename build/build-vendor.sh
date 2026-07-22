#!/usr/bin/env bash
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
BUILD_DIR="$PLUGIN_DIR/build/.vendor-build"
SOURCE_DIR="$BUILD_DIR/vendor"
SCOPE_INPUT="$BUILD_DIR/scope-input"
GENERATED_DIR="$BUILD_DIR/generated-vendor"
GENERATED_THIRD_PARTY="$BUILD_DIR/generated-third-party"
OUTPUT_DIR="$PLUGIN_DIR/src/Vendor"
THIRD_PARTY_DIR="$PLUGIN_DIR/THIRD_PARTY"
VENDOR_BACKUP="$BUILD_DIR/vendor-backup"
THIRD_PARTY_BACKUP="$BUILD_DIR/third-party-backup"
SWAP_STARTED=0
COMMITTED=0

cleanup() {
	local status=$?
	trap - EXIT
	if [[ $COMMITTED -eq 0 && $SWAP_STARTED -eq 1 ]]; then
		rm -rf "$OUTPUT_DIR" "$THIRD_PARTY_DIR"
		if [[ -e "$VENDOR_BACKUP" ]]; then
			mv "$VENDOR_BACKUP" "$OUTPUT_DIR"
		fi
		if [[ -e "$THIRD_PARTY_BACKUP" ]]; then
			mv "$THIRD_PARTY_BACKUP" "$THIRD_PARTY_DIR"
		fi
	fi
	rm -rf "$BUILD_DIR"
	exit "$status"
}
trap cleanup EXIT

if ! command -v composer >/dev/null 2>&1; then
	echo 'Composer is required to rebuild the embedded NeuronAi runtime.' >&2
	exit 1
fi

php "$PLUGIN_DIR/build/validate-lock.php"

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR" "$SCOPE_INPUT" "$GENERATED_DIR"
cp "$PLUGIN_DIR/build/composer.json" "$BUILD_DIR/composer.json"
cp "$PLUGIN_DIR/build/composer.lock" "$BUILD_DIR/composer.lock"

(
	cd "$BUILD_DIR"
	composer install --no-interaction --no-progress --prefer-dist
)

mkdir -p \
	"$SCOPE_INPUT/NeuronAI" \
	"$SCOPE_INPUT/Inspector" \
	"$SCOPE_INPUT/GuzzleHttp/Promise" \
	"$SCOPE_INPUT/GuzzleHttp/Psr7" \
	"$SCOPE_INPUT/Psr/Http/Client" \
	"$SCOPE_INPUT/Psr/Http/Message" \
	"$SCOPE_INPUT/Symfony/Polyfill/Mbstring" \
	"$SCOPE_INPUT/Support"

cp -a "$SOURCE_DIR/inspector-apm/neuron-ai/src/." "$SCOPE_INPUT/NeuronAI/"
cp -a "$SOURCE_DIR/inspector-apm/inspector-php/src/." "$SCOPE_INPUT/Inspector/"
cp -a "$SOURCE_DIR/guzzlehttp/guzzle/src/." "$SCOPE_INPUT/GuzzleHttp/"
cp -a "$SOURCE_DIR/guzzlehttp/promises/src/." "$SCOPE_INPUT/GuzzleHttp/Promise/"
cp -a "$SOURCE_DIR/guzzlehttp/psr7/src/." "$SCOPE_INPUT/GuzzleHttp/Psr7/"
cp -a "$SOURCE_DIR/psr/http-client/src/." "$SCOPE_INPUT/Psr/Http/Client/"
cp -a "$SOURCE_DIR/psr/http-message/src/." "$SCOPE_INPUT/Psr/Http/Message/"
cp -a "$SOURCE_DIR/psr/http-factory/src/." "$SCOPE_INPUT/Psr/Http/Message/"
cp "$SOURCE_DIR/symfony/polyfill-mbstring/Mbstring.php" "$SCOPE_INPUT/Symfony/Polyfill/Mbstring/Mbstring.php"
cp -a "$SOURCE_DIR/symfony/polyfill-mbstring/Resources" "$SCOPE_INPUT/Symfony/Polyfill/Mbstring/"
cp "$SOURCE_DIR/symfony/deprecation-contracts/function.php" "$SCOPE_INPUT/Support/trigger_deprecation.php"

NEURONAI_SCOPE_INPUT="$SCOPE_INPUT" php "$SOURCE_DIR/humbug/php-scoper/bin/php-scoper" add-prefix \
	--config="$PLUGIN_DIR/build/scoper.inc.php" \
	--output-dir="$GENERATED_DIR" \
	--force \
	--no-interaction

cp "$SOURCE_DIR/ralouphie/getallheaders/src/getallheaders.php" "$GENERATED_DIR/Support/getallheaders.php"
cp "$SOURCE_DIR/symfony/polyfill-mbstring/bootstrap80.php" "$GENERATED_DIR/Support/mbstring.php"
sed -i 's/use Symfony\\Polyfill\\Mbstring as p;/use NeuronAi\\Vendor\\Symfony\\Polyfill\\Mbstring as p;/' "$GENERATED_DIR/Support/mbstring.php"

php "$PLUGIN_DIR/build/verify-vendor.php" "$GENERATED_DIR"
find "$GENERATED_DIR" -type f -name '*.php' -print0 \
	| xargs -0 -n1 php -l >/dev/null
php "$PLUGIN_DIR/build/generate-third-party.php" \
	"$SOURCE_DIR" \
	"$GENERATED_DIR" \
	"$GENERATED_THIRD_PARTY"

SWAP_STARTED=1
if [[ -e "$OUTPUT_DIR" ]]; then
	mv "$OUTPUT_DIR" "$VENDOR_BACKUP"
fi
if [[ -e "$THIRD_PARTY_DIR" ]]; then
	mv "$THIRD_PARTY_DIR" "$THIRD_PARTY_BACKUP"
fi
mv "$GENERATED_DIR" "$OUTPUT_DIR"
mv "$GENERATED_THIRD_PARTY" "$THIRD_PARTY_DIR"
COMMITTED=1
rm -rf "$VENDOR_BACKUP" "$THIRD_PARTY_BACKUP"

"$PLUGIN_DIR/build/verify-vendor.sh"
echo 'NeuronAi vendor runtime rebuilt successfully.'
