<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | End-to-end wiring checks for audit security fixes.                      |
 |                                                                         |
 | Run: php tests/e2e/test_security_wiring.php                             |
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

$source = file_get_contents(__DIR__ . '/../../audit.php');

assert_true(
	'user_id filter uses integer normalization before prepared SQL binding',
	substr_count($source, "get_filter_request_var('user_id')") === 2
);

assert_true(
	'event_page filter documents string binding',
	substr_count($source, 'event_page is a sanitized page basename') === 2
);

assert_true(
	'detail popup escapes field names and values',
	strpos($source, "html_escape(\$field)") !== false &&
	strpos($source, "audit_render_value(\$content)") !== false
);

assert_true(
	'record data json is escaped before rendering',
	strpos($source, "html_escape(json_encode(\$record, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE))") !== false
);

echo "\n";
echo "Results: $pass passed, $fail failed\n";

exit($fail > 0 ? 1 : 0);
