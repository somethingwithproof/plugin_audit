<?php

require_once dirname(__DIR__) . '/audit_functions.php';

$audit_test_realms                = [];
$audit_test_existing_users        = [];
$audit_test_user_realms           = [];
$audit_test_user_query_failure    = false;
$audit_test_realm_query_failure   = false;
$audit_test_object_query_failure  = false;
$audit_test_external_event        = false;
$audit_test_external_events       = false;
$audit_test_external_updates      = [];
$audit_test_config_options        = [];

function api_plugin_user_realm_auth($filename = '') {
	global $audit_test_realms;

	return !empty($audit_test_realms[$filename]);
}

function db_fetch_cell_prepared($sql, $params = []) {
	global $audit_test_existing_users, $audit_test_user_query_failure;

	if ($audit_test_user_query_failure) {
		return false;
	}

	$user_id = (int) ($params[0] ?? 0);

	return in_array($user_id, $audit_test_existing_users, true) ? 1 : 0;
}

function db_fetch_assoc_prepared($sql, $params = []) {
	global $audit_test_user_realms, $audit_test_realm_query_failure, $audit_test_object_query_failure;

	if ($audit_test_realm_query_failure || $audit_test_object_query_failure) {
		return false;
	}

	$user_id   = (int) ($params[0] ?? 0);
	$realm_ids = $audit_test_user_realms[$user_id] ?? [];

	return array_map(function ($realm_id) {
		return ['realm_id' => $realm_id];
	}, $realm_ids);
}

/**
 * @param  string             $sql
 * @return array<mixed>|false
 */
function db_fetch_assoc($sql) {
	global $audit_test_external_events;

	return $audit_test_external_events;
}

/**
 * @param  mixed $value
 * @return int
 */
function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

/**
 * @param  string             $sql
 * @param  array<mixed>       $params
 * @return array<mixed>|false
 */
function db_fetch_row_prepared($sql, $params = []) {
	global $audit_test_external_event;

	return $audit_test_external_event;
}

/**
 * @param  string       $sql
 * @param  array<mixed> $params
 * @return bool
 */
function db_execute_prepared($sql, $params = []) {
	global $audit_test_external_updates;

	$audit_test_external_updates[] = ['sql' => $sql, 'params' => $params];

	return true;
}

/**
 * @param  string $name
 * @return mixed
 */
function read_config_option($name) {
	global $audit_test_config_options;

	return $audit_test_config_options[$name] ?? '';
}

function audit_test_assert_same($expected, $actual, $message) {
	if ($expected !== $actual) {
		fwrite(STDERR, $message . PHP_EOL);
		fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
		fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
		exit(1);
	}
}

audit_test_assert_same(false, audit_user_is_admin(), 'Audit users must not be treated as audit administrators.');
$audit_test_realms['audit_manage.php'] = true;
audit_test_assert_same(true, audit_user_is_admin(), 'Audit plugin administrators must be able to purge.');
$audit_test_realms = [];

$verifier = audit_operation_verifier_for_request('user_admin.php', [
	'action'                     => 'save',
	'id'                         => '4',
	'save_component_realm_perms' => '1',
	'section110'                 => 'on',
	'section106'                 => 'on'
]);
audit_test_assert_same(
	[
		'type'               => 'user_realm_permissions',
		'target_user_id'     => 4,
		'expected_realm_ids' => [106, 110]
	],
	$verifier,
	'User realm permission saves must capture a normalized post-condition verifier.'
);

audit_test_assert_same(
	null,
	audit_operation_verifier_for_request('host.php', ['id' => '4']),
	'Unrelated requests must not receive a user realm verifier.'
);

$invalid_verifier = audit_operation_verifier_for_request('user_admin.php', [
	'id'                         => 'invalid',
	'save_component_realm_perms' => '1'
]);
audit_test_assert_same(
	'invalid',
	$invalid_verifier['type'],
	'Invalid realm permission requests must not be verified as successful.'
);

$audit_test_existing_users = [4];
$audit_test_user_realms    = [4 => [110, 106]];
audit_test_assert_same(
	['outcome' => 'success', 'reason' => 'realm_permissions_verified'],
	audit_verify_operation($verifier),
	'Matching stored realm permissions must produce a verified success outcome.'
);

$audit_test_user_realms = [4 => [106]];
audit_test_assert_same(
	['outcome' => 'failure', 'reason' => 'realm_permissions_mismatch'],
	audit_verify_operation($verifier),
	'Mismatched stored realm permissions must produce a failure outcome.'
);

$audit_test_existing_users = [];
audit_test_assert_same(
	['outcome' => 'failure', 'reason' => 'target_user_not_found'],
	audit_verify_operation($verifier),
	'A missing target user must produce a failure outcome.'
);

$audit_test_existing_users     = [4];
$audit_test_user_query_failure = true;
audit_test_assert_same(
	['outcome' => 'unknown', 'reason' => 'realm_permissions_verification_failed'],
	audit_verify_operation($verifier),
	'A failed target-user query must preserve an unknown outcome.'
);
$audit_test_user_query_failure = false;

$audit_test_existing_users      = [4];
$audit_test_realm_query_failure = true;
audit_test_assert_same(
	['outcome' => 'unknown', 'reason' => 'realm_permissions_verification_failed'],
	audit_verify_operation($verifier),
	'A failed verification query must preserve an unknown outcome.'
);
$audit_test_realm_query_failure = false;

$audit_test_object_query_failure = true;
$object_pages                    = [
	'host.php', 'host_templates.php', 'templates_export.php', 'automation_devices.php',
	'graph_templates.php', 'thold.php', 'data_sources.php', 'data_templates.php',
	'aggregate_templates.php', 'thold_templates.php', 'user_admin.php', 'user_group_admin.php'
];

foreach ($object_pages as $object_page) {
	audit_test_assert_same(
		[],
		json_decode(audit_process_page_data($object_page, '1', ['42']), true),
		'Failed object queries must not add false entries for ' . $object_page . '.'
	);
}
$audit_test_object_query_failure = false;

$request = [
	'username' => 'operator',
	'password' => 'top-secret',
	'nested'   => [
		'api_token'    => 'nested-secret',
		'description'  => '<script>alert(1)</script>',
		'opaque_value' => 'Bearer abcdefghijklmnopqrstuvwxyz'
	]
];

$redacted = audit_redact_sensitive_data($request);

audit_test_assert_same('[REDACTED]', $redacted['password'], 'Top-level passwords must be redacted.');
audit_test_assert_same('[REDACTED]', $redacted['nested']['api_token'], 'Nested tokens must be redacted.');
audit_test_assert_same('[REDACTED]', $redacted['nested']['opaque_value'], 'Secret-shaped values must be redacted even when their key is unknown.');
audit_test_assert_same(
	'<script>alert(1)</script>',
	$redacted['nested']['description'],
	'Non-secret data must remain available for later context-aware output escaping.'
);

$arguments = audit_redact_cli_arguments([
	'cli/example.php',
	'--password=secret-one',
	'--api-token',
	'secret-two',
	'https://user:secret-three@example.com/path',
	'--description=test'
]);

audit_test_assert_same('--password=[REDACTED]', $arguments[1], 'Inline CLI passwords must be redacted.');
audit_test_assert_same('[REDACTED]', $arguments[3], 'Separate CLI secret values must be redacted.');
audit_test_assert_same(
	'https://user:[REDACTED]@example.com/path',
	$arguments[4],
	'Credentials embedded in a URI must be redacted.'
);

$original_backtrack_limit = ini_get('pcre.backtrack_limit');
ini_set('pcre.backtrack_limit', '0');
$failed_redaction      = audit_redact_cli_arguments(['https://user:must-not-leak@example.com/path']);
$failed_inline_cli     = audit_redact_cli_arguments(['--password=must-not-leak']);
$failed_separated_cli  = audit_redact_cli_arguments(['--api-token', 'must-not-leak']);
$failed_post_redaction = audit_redact_sensitive_data([
	'password' => 'must-not-leak',
	'nested'   => ['api_token' => 'must-not-leak'],
]);
$failed_value_redaction = audit_redact_sensitive_value('https://user:must-not-leak@example.com/path');
ini_set('pcre.backtrack_limit', (string) $original_backtrack_limit);
audit_test_assert_same('[REDACTED]', $failed_redaction[0], 'URI redaction failures must fail closed.');
audit_test_assert_same('[REDACTED]', $failed_inline_cli[0], 'Inline CLI key-matching failures must fail closed.');
audit_test_assert_same('[REDACTED]', $failed_separated_cli[0], 'Separated CLI key-matching failures must redact the option.');
audit_test_assert_same('[REDACTED]', $failed_separated_cli[1], 'Separated CLI key-matching failures must redact the value.');
audit_test_assert_same('[REDACTED]', $failed_post_redaction['password'], 'Sensitive key matching failures must fail closed.');
audit_test_assert_same('[REDACTED]', $failed_post_redaction['nested']['api_token'], 'Nested sensitive key matching failures must fail closed.');
audit_test_assert_same('[REDACTED]', $failed_value_redaction, 'Sensitive value matching failures must fail closed.');
audit_test_assert_same('[REDACTED]', audit_syslog_cef_event_field($failed_value_redaction), 'Failed redaction must remain safe through CEF formatting.');

audit_test_assert_same("'=1+1", audit_csv_safe_cell('=1+1'), 'Spreadsheet formulas must be neutralized.');

$deep   = [];
$cursor = &$deep;

for ($i = 0; $i < 15; $i++) {
	$cursor['nested'] = [];
	$cursor           = &$cursor['nested'];
}
unset($cursor);

$bounded_json = audit_json_encode($deep);

if (strpos($bounded_json, 'MAXIMUM DEPTH REACHED') === false) {
	fwrite(STDERR, 'Deep request structures must be bounded.' . PHP_EOL);
	exit(1);
}

$invalid_json = audit_json_decode('{invalid', $json_error);
audit_test_assert_same(null, $invalid_json, 'Malformed JSON must return a controlled null result.');

if ($json_error === null || $json_error === '') {
	fwrite(STDERR, 'Malformed JSON must return a useful error.' . PHP_EOL);
	exit(1);
}

$now    = new DateTimeImmutable('2026-03-09 12:00:00', new DateTimeZone('America/New_York'));
$cutoff = audit_retention_cutoff(1, $now);
audit_test_assert_same(
	'2026-03-08 16:00:00',
	$cutoff->format('Y-m-d H:i:s'),
	'Retention must subtract full UTC days consistently across DST.'
);

audit_test_assert_same(
	'completed',
	audit_request_status(null, 302),
	'Successful redirects must finalize as completed requests.'
);
audit_test_assert_same(
	'failed',
	audit_request_status(['type' => E_ERROR], 200),
	'Fatal errors must finalize as failed requests.'
);

$uuid = audit_uuid_v4();

if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid)) {
	fwrite(STDERR, 'Event identifiers must be RFC 4122 version 4 UUIDs.' . PHP_EOL);
	exit(1);
}

audit_test_assert_same(
	'cacti.user_admin.save',
	audit_event_type_for_request('user_admin.php', 'Save'),
	'Request event types must be normalized for downstream consumers.'
);
audit_test_assert_same(
	'cacti.host.submitted',
	audit_event_type_for_request('host.php', 'none'),
	'Requests without a specific action must use the submitted event verb.'
);

$hash_event = [
	'event_uuid'        => $uuid,
	'correlation_id'    => audit_uuid_v4(),
	'event_type'        => 'cacti.test.completed',
	'user_id'           => 1,
	'action'            => 'test',
	'event_time'        => '2026-07-24 10:00:00',
	'operation_outcome' => 'success',
	'details'           => '{}'
];
$first_hash                      = audit_event_integrity_hash($hash_event);
$hash_event['operation_outcome'] = 'failure';

if ($first_hash === audit_event_integrity_hash($hash_event)) {
	fwrite(STDERR, 'Integrity hashes must change when protected event fields change.' . PHP_EOL);
	exit(1);
}

$external_record = [
	'event_time'  => '2026-07-24 10:00:00',
	'action'      => "Update\nDevice",
	'post'        => '{"id":42}',
	'object_data' => '[]'
];
$json_record = audit_external_log_format($external_record, 'json');
audit_test_assert_same("\n", substr($json_record, -1), 'JSON external records must end with a newline.');
audit_test_assert_same(
	[
		'event_time'  => '2026-07-24 10:00:00',
		'action'      => "Update\nDevice",
		'post'        => ['id' => 42],
		'object_data' => []
	],
	json_decode(trim($json_record), true),
	'JSON external records must expose stored JSON fields as native structures.'
);
audit_test_assert_same(
	'event_time="2026-07-24 10:00:00" action="Update\nDevice" post="{\"id\":42}" object_data="[]"' . "\n",
	audit_external_log_format($external_record, 'text'),
	'Text external records must be single-line key/value data with escaped values.'
);
audit_test_assert_same(
	'{"post":"not-json"}' . "\n",
	audit_external_log_format(['post' => 'not-json'], 'json'),
	'Malformed stored JSON fields must remain available as strings.'
);
audit_test_assert_same(
	$json_record,
	audit_external_log_format($external_record, 'unsupported'),
	'Unknown external formats must safely fall back to JSON.'
);

$temporary_log = tempnam(sys_get_temp_dir(), 'audit-test-');
$delivery      = audit_append_external_log($temporary_log, "test-record\n");
audit_test_assert_same('delivered', $delivery['status'], 'A complete external log write must report delivery.');
audit_test_assert_same("test-record\n", file_get_contents($temporary_log), 'External log content must be complete.');

$audit_test_config_options = [
	'audit_log_external'        => 'on',
	'audit_log_external_path'   => $temporary_log,
	'audit_log_external_format' => 'json'
];
$audit_test_external_event   = false;
$audit_test_external_updates = [];
file_put_contents($temporary_log, '');
audit_deliver_external_event(999);
audit_test_assert_same('', file_get_contents($temporary_log), 'Missing audit events must not create external records.');
audit_test_assert_same([], $audit_test_external_updates, 'Missing audit events must not update delivery status.');

$audit_test_external_event = [];
audit_deliver_external_event(999);
audit_test_assert_same('', file_get_contents($temporary_log), 'Empty audit events must not create external records.');
audit_test_assert_same([], $audit_test_external_updates, 'Empty audit events must not update delivery status.');

$audit_test_external_event = ['id' => 999];
audit_deliver_external_event(999);
audit_test_assert_same('', file_get_contents($temporary_log), 'Events without a request status must not create external records.');
audit_test_assert_same([], $audit_test_external_updates, 'Events without a request status must not update delivery status.');

$audit_test_external_events = false;
audit_retry_external_logs();
audit_test_assert_same('', file_get_contents($temporary_log), 'Failed retry queries must not append external records.');
audit_test_assert_same([], $audit_test_external_updates, 'Failed retry queries must not update delivery status.');

unlink($temporary_log);

print "Security helper tests passed.\n";
