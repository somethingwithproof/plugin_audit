<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | Regression checks for prepared DB helper migration in audit plugin      |
 |                                                                         |
 | Run: php tests/test_prepared_statements.php                             |
 +-------------------------------------------------------------------------+
 */

$pass = 0;
$fail = 0;

function assert_true($label, $value) {
	global $pass, $fail;

	if ($value) {
		echo "PASS  $label\n";
		$pass++;
	} else {
		echo "FAIL  $label\n";
		$fail++;
	}
}

$setup_contents = file_get_contents(__DIR__ . '/../setup.php');
$audit_contents = file_get_contents(__DIR__ . '/../audit.php');

assert_true(
	'setup.php uses prepared plugin version lookup',
	preg_match('/db_fetch_cell_prepared\s*\(\s*\'SELECT version\s+FROM plugin_config\s+WHERE directory = \?/s', $setup_contents) === 1
);
assert_true(
	'setup.php uses prepared plugin config updates',
	preg_match_all('/db_execute_prepared\s*\(\s*\'UPDATE plugin_config/s', $setup_contents) >= 2
);
assert_true(
	'setup.php uses prepared retention purge delete',
	preg_match('/db_execute_prepared\s*\(\s*\'DELETE FROM audit_log\s+WHERE event_time < FROM_UNIXTIME\(\?\)/s', $setup_contents) === 1
);
assert_true(
	'setup.php dependency check references audit_log consistently',
	preg_match('/db_table_exists\s*\(\s*[\'"]audit_log[\'"]/', $setup_contents) === 1
	&& preg_match('/SHOW CREATE TABLE\s+audit_log/i', $setup_contents) === 1
	&& preg_match('/\b(?:alert_log|autid_log)\b/i', $setup_contents) === 0
);
assert_true(
	'audit.php uses prepared page/user selector queries',
	preg_match_all('/db_fetch_assoc_prepared\s*\(\s*\'SELECT DISTINCT (?:page|user_id)/s', $audit_contents) >= 2
);
assert_true(
	'audit.php uses prepared count and event fetch queries',
	preg_match('/db_fetch_cell_prepared\s*\(\s*"SELECT\s+COUNT\(\*\)/s', $audit_contents) === 1 &&
	preg_match_all('/db_fetch_assoc_prepared\s*\(\s*"SELECT audit_log\.\*, user_auth\.username/s', $audit_contents) >= 2
);
assert_true(
	'audit.php has no raw db_fetch_cell/db_fetch_assoc calls',
	preg_match('/\bdb_fetch_(?:cell|assoc)\s*\(/', $audit_contents) === 0
);

echo "\n";
echo "Results: $pass passed, $fail failed\n";

exit($fail > 0 ? 1 : 0);
