<?php declare(strict_types=1);

$pluginDir = dirname(__DIR__);
$vendorDir = $argv[1] ?? $pluginDir . '/src/Vendor';
if (!is_dir($vendorDir)) {
	fwrite(STDERR, 'Generated vendor directory is missing: ' . $vendorDir . PHP_EOL);
	exit(1);
}

$requiredFiles = [
	'NeuronAI/Agent/Agent.php',
	'NeuronAI/Providers/AIProviderInterface.php',
	'GuzzleHttp/Client.php',
	'Psr/Http/Message/RequestInterface.php',
	'Symfony/Polyfill/Mbstring/Mbstring.php',
	'Support/mbstring.php',
	'Support/trigger_deprecation.php',
	'Support/getallheaders.php'
];
foreach ($requiredFiles as $file) {
	if (!is_file($vendorDir . '/' . $file)) {
		fwrite(STDERR, 'Required generated file is missing: ' . $file . PHP_EOL);
		exit(1);
	}
}

$foreignNamespacePattern = '~(?<!NeuronAi\\\\Vendor\\\\)(?:NeuronAI|GuzzleHttp|Psr\\\\Http|Inspector|Symfony\\\\Polyfill\\\\Mbstring)\\\\~';
$ignoredTokens = [
	T_COMMENT,
	T_DOC_COMMENT,
	T_CONSTANT_ENCAPSED_STRING,
	T_ENCAPSED_AND_WHITESPACE
];

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($vendorDir, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
	if (!$file->isFile() || $file->getExtension() !== 'php') {
		continue;
	}

	$content = (string)file_get_contents($file->getPathname());
	$executableCode = '';
	foreach (token_get_all($content) as $token) {
		if (is_array($token)) {
			if (in_array($token[0], $ignoredTokens, true)) {
				$executableCode .= str_repeat("\n", substr_count($token[1], "\n"));
				continue;
			}
			$executableCode .= $token[1];
			continue;
		}
		$executableCode .= $token;
	}

	if (preg_match($foreignNamespacePattern, $executableCode, $matches, PREG_OFFSET_CAPTURE) === 1) {
		$offset = $matches[0][1];
		$line = substr_count(substr($executableCode, 0, $offset), "\n") + 1;
		fwrite(STDERR, 'Unscoped foreign namespace in ' . $file->getPathname() . ':' . $line . PHP_EOL);
		exit(1);
	}
}

echo 'Scoped vendor verification OK: ' . $vendorDir . PHP_EOL;
