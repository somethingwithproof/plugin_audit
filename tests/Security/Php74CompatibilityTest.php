<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('the advertised PHP 7.4 runtime contract', function () {
	$runtimeFiles = [
		'audit.php',
		'audit_functions.php',
		'audit_syslog.php',
		'index.php',
		'setup.php',
	];

	it('keeps PHP 8-only native types out of runtime files', function () use ($runtimeFiles) {
		foreach ($runtimeFiles as $relativeFile) {
			$contents = file_get_contents(__DIR__ . '/../../' . $relativeFile);

			expect($contents)->not->toBeFalse()
				->and($contents)->not->toMatch('/\bmixed\s+[&]?\$/')
				->and($contents)->not->toMatch('/:\s*(?:mixed|[^\s{]+\|[^\s{]+)/');
		}
	});

	it('keeps the compatibility floor explicit in plugin metadata', function () {
		$info = parse_ini_file(__DIR__ . '/../../INFO', true);

		expect($info['info']['compat'] ?? null)->toBe('1.2.20');
	});
});
