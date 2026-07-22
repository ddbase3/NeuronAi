<?php declare(strict_types=1);

$pluginDir = dirname(__DIR__);
$packageVendorDir = $argv[1] ?? '';
$embeddedVendorDir = $argv[2] ?? $pluginDir . '/src/Vendor';
$thirdPartyDir = $argv[3] ?? $pluginDir . '/THIRD_PARTY';
if (!is_dir($packageVendorDir) || !is_dir($embeddedVendorDir)) {
	fwrite(
		STDERR,
		"Usage: php generate-third-party.php /package/vendor [/embedded/vendor] [/third-party/output]\n"
	);
	exit(1);
}

$lockFile = __DIR__ . '/composer.lock';
$lock = json_decode((string)file_get_contents($lockFile), true, 512, JSON_THROW_ON_ERROR);
$packages = [];
foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) {
	$packages[(string)$package['name']] = $package;
}

$packageRoles = [
	'guzzlehttp/guzzle' => 'runtime',
	'guzzlehttp/promises' => 'runtime',
	'guzzlehttp/psr7' => 'runtime',
	'inspector-apm/inspector-php' => 'runtime',
	'inspector-apm/neuron-ai' => 'runtime',
	'psr/http-client' => 'runtime',
	'psr/http-factory' => 'runtime',
	'psr/http-message' => 'runtime',
	'ralouphie/getallheaders' => 'runtime',
	'symfony/deprecation-contracts' => 'runtime',
	'symfony/polyfill-php80' => 'platform compatibility dependency',
	'symfony/polyfill-mbstring' => 'standalone BASE3 compatibility'
];
$notEmbedded = [
	'symfony/polyfill-php80' => 'NeuronAi requires PHP 8.1 or newer, so the PHP 8.0 backport is unreachable.'
];

$readmeFile = $pluginDir . '/THIRD_PARTY/README.md';
$readme = is_file($readmeFile) ? (string)file_get_contents($readmeFile) : '';
removeDirectory($thirdPartyDir);
mkdir($thirdPartyDir, 0775, true);
if ($readme !== '') {
	file_put_contents($thirdPartyDir . '/README.md', $readme);
}

$manifestPackages = [];
foreach ($packageRoles as $name => $role) {
	$package = $packages[$name] ?? null;
	if (!is_array($package)) {
		throw new RuntimeException('Package missing from lock: ' . $name);
	}

	$sourceDir = $packageVendorDir . '/' . $name;
	$targetDir = $thirdPartyDir . '/' . $name;
	mkdir($targetDir, 0775, true);
	$licenseFile = findLicenseFile($sourceDir);
	if ($licenseFile === null) {
		throw new RuntimeException('License file not found for package: ' . $name);
	}
	copy($licenseFile, $targetDir . '/LICENSE');

	$entry = [
		'name' => $name,
		'version' => (string)($package['version'] ?? ''),
		'role' => $role,
		'embedded' => !array_key_exists($name, $notEmbedded),
		'license' => array_values((array)($package['license'] ?? [])),
		'source' => $package['source'] ?? null,
		'dist' => $package['dist'] ?? null
	];
	if (isset($notEmbedded[$name])) {
		$entry['not_embedded_reason'] = $notEmbedded[$name];
	}
	$manifestPackages[] = $entry;
}

$manifest = [
	'generated_at' => gmdate('Y-m-d'),
	'scope_prefix' => 'NeuronAi\\Vendor',
	'php_minimum' => '8.1',
	'build_lock_sha256' => hash_file('sha256', $lockFile),
	'vendor_tree_sha256' => hashTree($embeddedVendorDir),
	'upstream' => [
		'package' => 'inspector-apm/neuron-ai',
		'version' => $packages['inspector-apm/neuron-ai']['version'] ?? '',
		'reference' => $packages['inspector-apm/neuron-ai']['source']['reference'] ?? ''
	],
	'packages' => $manifestPackages
];

file_put_contents(
	$thirdPartyDir . '/manifest.json',
	json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL
);

echo 'Third-party manifest generated.' . PHP_EOL;

function findLicenseFile(string $directory): ?string {
	foreach (['LICENSE', 'LICENSE.md', 'LICENSE.txt', 'COPYING'] as $name) {
		$file = $directory . '/' . $name;
		if (is_file($file)) {
			return $file;
		}
	}
	return null;
}

function hashTree(string $directory): string {
	$files = [];
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
	);
	foreach ($iterator as $file) {
		if ($file->isFile()) {
			$files[] = $file->getPathname();
		}
	}
	sort($files, SORT_STRING);
	$context = hash_init('sha256');
	foreach ($files as $file) {
		hash_update($context, substr($file, strlen($directory) + 1));
		hash_update($context, "\0");
		hash_update($context, hash_file('sha256', $file));
		hash_update($context, "\n");
	}
	return hash_final($context);
}

function removeDirectory(string $directory): void {
	if (!is_dir($directory)) {
		return;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ($iterator as $item) {
		$item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
	}
	rmdir($directory);
}
