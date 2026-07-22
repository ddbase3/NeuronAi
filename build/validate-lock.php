<?php declare(strict_types=1);

$buildDir = __DIR__;
$lockFile = $buildDir . '/composer.lock';
$composerFile = $buildDir . '/composer.json';

$lock = json_decode((string)file_get_contents($lockFile), true, 512, JSON_THROW_ON_ERROR);
$composer = json_decode((string)file_get_contents($composerFile), true, 512, JSON_THROW_ON_ERROR);

$expectedRuntimePackages = [
	'guzzlehttp/guzzle',
	'guzzlehttp/promises',
	'guzzlehttp/psr7',
	'inspector-apm/inspector-php',
	'inspector-apm/neuron-ai',
	'psr/http-client',
	'psr/http-factory',
	'psr/http-message',
	'ralouphie/getallheaders',
	'symfony/deprecation-contracts',
	'symfony/polyfill-php80'
];
$expectedBuildSupportPackages = [
	'symfony/polyfill-mbstring'
];

$runtimePackages = array_map(
	static fn(array $package): string => (string)$package['name'],
	$lock['packages'] ?? []
);
$devPackages = array_map(
	static fn(array $package): string => (string)$package['name'],
	$lock['packages-dev'] ?? []
);

sort($expectedRuntimePackages, SORT_STRING);
sort($runtimePackages, SORT_STRING);
if ($runtimePackages !== $expectedRuntimePackages) {
	fwrite(STDERR, "Unexpected runtime dependency set.\nExpected:\n");
	fwrite(STDERR, implode("\n", $expectedRuntimePackages) . "\nActual:\n");
	fwrite(STDERR, implode("\n", $runtimePackages) . "\n");
	exit(1);
}

foreach ($expectedBuildSupportPackages as $package) {
	if (!in_array($package, $devPackages, true)) {
		fwrite(STDERR, 'Missing reviewed build support package: ' . $package . PHP_EOL);
		exit(1);
	}
}

$requiredVersion = trim((string)($composer['require']['inspector-apm/neuron-ai'] ?? ''));
if ($requiredVersion === '' || preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $requiredVersion) !== 1) {
	fwrite(STDERR, "Neuron AI must be pinned to one exact semantic version.\n");
	exit(1);
}

$lockedVersion = '';
foreach ($lock['packages'] ?? [] as $package) {
	if (($package['name'] ?? '') === 'inspector-apm/neuron-ai') {
		$lockedVersion = ltrim((string)($package['version'] ?? ''), 'v');
		break;
	}
}
if ($lockedVersion !== $requiredVersion) {
	fwrite(STDERR, 'Neuron AI composer.json and lock version differ: ' . $requiredVersion . ' / ' . $lockedVersion . PHP_EOL);
	exit(1);
}

echo 'Lock validation OK: Neuron AI ' . $lockedVersion . PHP_EOL;
