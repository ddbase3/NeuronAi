<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of NeuronAi for BASE3 Framework.
 *
 * NeuronAi integrates the Neuron AI agent runtime with AssistantFoundation.
 * It ships an isolated, reproducible Neuron AI runtime for ILIAS and
 * standalone BASE3 installations.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/neuronai
 * https://github.com/ddbase3/NeuronAi
 **********************************************************************/

namespace NeuronAi;

/**
 * Loads function-only dependencies that cannot be discovered by class maps.
 */
final class VendorBootstrap {

	private static bool $initialized = false;

	public static function init(): void {
		if (PHP_VERSION_ID < 80100) {
			throw new \RuntimeException('NeuronAi requires PHP 8.1 or newer.');
		}
		if (!function_exists('mb_strlen') && !extension_loaded('iconv')) {
			throw new \RuntimeException('NeuronAi requires mbstring or iconv.');
		}

		if (self::$initialized) {
			return;
		}
		self::$initialized = true;

		require_once __DIR__ . '/Vendor/Support/mbstring.php';
		require_once __DIR__ . '/Vendor/Support/trigger_deprecation.php';
		require_once __DIR__ . '/Vendor/Support/getallheaders.php';
		require_once __DIR__ . '/Vendor/GuzzleHttp/functions_include.php';
	}
}
