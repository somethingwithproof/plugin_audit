<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Verify migrated files use prepared DB helpers exclusively.
 * Catches regressions where raw db_execute/db_fetch_* calls creep back in.
 */

describe('prepared statement consistency in audit', function () {
	it('keeps the audit purge delete on the prepared helper', function () {
		$contents = file_get_contents(__DIR__ . '/../../audit.php');

		expect($contents)->not->toBeFalse();

		$matched = preg_match('/function audit_purge\(\): void \{(?<body>.*?)\n\}/s', (string) $contents, $matches);
		$body    = $matched === 1 && isset($matches['body']) ? $matches['body'] : '';

		expect($matched)->toBe(1)
			->and($body)->toContain('db_execute_prepared("DELETE FROM audit_log')
			->and($body)->not->toMatch('/\bdb_execute\s*\(/');
	});

	it('documents database helper usage in all plugin files', function () {
		$targetFiles = [
		'audit.php',
		'audit_functions.php',
		'audit_syslog.php',
		'setup.php',
		];

		foreach ($targetFiles as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			expect($path)->not->toBeFalse("Required plugin file is missing: {$relativeFile}");

			$contents = file_get_contents($path);

			expect($contents)->not->toBeFalse("Unable to read {$relativeFile}");
			expect(preg_match('/\b(?:db_execute|db_fetch_(?:row|assoc|cell))\s*\(/', $contents))->toBe(1,
				"File {$relativeFile} must contain database access"
			);
		}
	});
});
