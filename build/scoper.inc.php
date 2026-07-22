<?php declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

$input = getenv('NEURONAI_SCOPE_INPUT');
if (!is_string($input) || trim($input) === '' || !is_dir($input)) {
	throw new RuntimeException('NEURONAI_SCOPE_INPUT must point to the prepared scope input directory.');
}

return [
	'prefix' => 'NeuronAi\\Vendor',
	'finders' => [
		Finder::create()
			->files()
			->in($input)
	]
];
