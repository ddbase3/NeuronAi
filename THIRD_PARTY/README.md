# Third-party runtime

This directory records the exact packages embedded under `src/Vendor`.

- `manifest.json` is generated from `build/composer.lock`.
- Package license files are copied during `build/build-vendor.sh`.
- The generated PHP code is namespace-scoped under `NeuronAi\Vendor`.
- The normal ILIAS or BASE3 autoloader loads the scoped classes.

Do not edit generated vendor classes or copied license metadata manually.
