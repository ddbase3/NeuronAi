# Dependency isolation

## Requirement

NeuronAi must run in both environments:

- ILIAS, which already contains its own Composer dependency graph;
- standalone BASE3, which may not contain Guzzle, PSR HTTP interfaces,
  Inspector or Mbstring.

Using the host version when available and the bundled version otherwise would
make runtime behavior depend on autoload order and package compatibility. The
plugin therefore does not use that pattern.

## Scoped runtime

All embedded class-based dependencies are rewritten during the vendor build to
start with `NeuronAi\Vendor`:

- `NeuronAI\...` -> `NeuronAi\Vendor\NeuronAI\...`
- `GuzzleHttp\...` -> `NeuronAi\Vendor\GuzzleHttp\...`
- `Psr\Http\...` -> `NeuronAi\Vendor\Psr\Http\...`
- `Inspector\...` -> `NeuronAi\Vendor\Inspector\...`
- the bundled Symfony Mbstring implementation is scoped as well.

The normal ILIAS or BASE3 class mapper loads these files from the plugin. No
second Composer autoloader is registered at runtime.

## Function-only dependencies

Composer packages can declare files containing functions. Class-map discovery
cannot load them automatically. `VendorBootstrap` therefore requires a small,
explicit list:

- Mbstring compatibility functions;
- scoped Symfony `trigger_deprecation`;
- `getallheaders` fallback;
- Guzzle function compatibility file.

The global Mbstring and `getallheaders` functions use `function_exists` guards
and are only added when the host has no native implementation. The underlying
classes remain private to NeuronAi.

## Why the bundled packages are always included

The packages are present even if ILIAS currently ships compatible versions.
This is required for standalone BASE3 and makes the plugin's tested dependency
set independent of future ILIAS upgrades.

## Verification

`build/build-vendor.sh` performs these checks:

1. the Composer lock contains only the reviewed runtime package set;
2. PHP-Scoper rewrites the selected sources;
3. no original foreign namespaces remain in the generated runtime;
4. every generated PHP file passes `php -l`;
5. third-party licenses and exact package metadata are regenerated.
