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
	$hasPhp8OnlyType = static function (string $contents): bool {
		$withoutComments = '';

		foreach (token_get_all("<?php\n" . $contents) as $token) {
			if (is_array($token)) {
				if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT || $token[0] === T_OPEN_TAG) {
					continue;
				}

				$withoutComments .= $token[1];
			} else {
				$withoutComments .= $token;
			}
		}

		$namedType = '[?\\\\A-Za-z_][\\\\A-Za-z0-9_]*';
		$patterns  = [
			'/\bfunction\s+[A-Za-z_]\w*\s*\([^)]*\bmixed\s+(?:&\s*)?\$[A-Za-z_]\w*/s',
			'/\bfunction\s+[A-Za-z_]\w*\s*\([^)]*' . $namedType . '(?:\|' . $namedType . ')+\s+(?:&\s*)?\$[A-Za-z_]\w*/s',
			'/\)\s*:\s*(?:mixed\b|' . $namedType . '(?:\|' . $namedType . ')+)/s',
		];

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $withoutComments) === 1) {
				return true;
			}
		}

		return false;
	};

	it('keeps PHP 8-only syntax out of runtime files', function () use ($runtimeFiles, $hasPhp8OnlyType) {
		foreach ($runtimeFiles as $relativeFile) {
			$contents = file_get_contents(__DIR__ . '/../../' . $relativeFile);

			expect($contents)->not->toBeFalse()
				->and($hasPhp8OnlyType((string) $contents))->toBeFalse()
				->and($contents)->not->toContain('str_contains(')
				->and($contents)->not->toContain('str_starts_with(')
				->and($contents)->not->toContain('str_ends_with(')
				->and($contents)->not->toContain('?->');
		}
	});

	it('distinguishes PHPDoc and regex text from native declarations', function () use ($hasPhp8OnlyType) {
		expect($hasPhp8OnlyType("/** @param mixed \$value */\nfunction safe(\$value) { return '/a|b/'; }"))->toBeFalse()
			->and($hasPhp8OnlyType('function nativeMixed(mixed $value) {}'))->toBeTrue()
			->and($hasPhp8OnlyType('function nativeUnion($value): int|false {}'))->toBeTrue()
			->and($hasPhp8OnlyType("function url() { return 'https://example.test'; } function nativeMixed(mixed \$value) {}"))->toBeTrue();
	});

	it('keeps the compatibility floor explicit in plugin metadata', function () {
		$info = parse_ini_file(__DIR__ . '/../../INFO', true);

		expect($info['info']['compat'] ?? null)->toBe('1.2.20');
	});

	it('keeps developer-only PHP excluded from the runtime lint sweep', function () {
		$workflow = file_get_contents(__DIR__ . '/../../.github/workflows/code-quality.yml');
		$config   = file_get_contents(__DIR__ . '/../../.phpstan.neon');

		expect($workflow)->not->toBeFalse()
			->and($workflow)->toContain("-path './phpstan' -prune")
			->and($workflow)->toContain("-path './tests' -prune")
			->and($config)->not->toBeFalse()
			->and($config)->toContain('phpstan/stubs/cacti.stub');
	});
});
