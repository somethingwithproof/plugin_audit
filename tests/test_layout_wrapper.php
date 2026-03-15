<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | Regression checks for shared layout wrapper routing in audit.php        |
 |                                                                         |
 | Run: php tests/test_layout_wrapper.php                                  |
 +-------------------------------------------------------------------------+
 */

$pass = 0;
$fail = 0;
$events = array();

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

function top_header() {
	global $events;
	$events[] = 'top_header';
}

function bottom_footer() {
	global $events;
	$events[] = 'bottom_footer';
}

require_once __DIR__ . '/../ui_helpers.php';

$events = array();
$runs = 0;

audit_render_with_layout(function () use (&$events, &$runs) {
	$runs++;
	$events[] = 'content';
});

assert_true('layout callback executes once', $runs === 1);
assert_true('layout call order is top->content->bottom', $events === array('top_header', 'content', 'bottom_footer'));

$source = file_get_contents(__DIR__ . '/../audit.php');

assert_true(
	'purge/default actions use shared layout helper',
	substr_count($source, "audit_render_with_layout('audit_log');") >= 2
);
assert_true(
	'audit.php includes ui helper file',
	strpos($source, "include_once('./plugins/audit/ui_helpers.php');") !== false
);

echo "\n";
echo "Results: $pass passed, $fail failed\n";

exit($fail > 0 ? 1 : 0);
