<?php

declare(strict_types=1);

namespace {
	interface BMO {}

	class CI_Email {
		public static $last;
		public $fromArgs = [];
		public $replyToArgs = [];

		public function __construct() { self::$last = $this; }
		public function from($address, $name = '', $returnPath = null) { $this->fromArgs = func_get_args(); }
		public function reply_to($address, $name = '') { $this->replyToArgs = func_get_args(); }
		public function to($recipient) {}
		public function subject($subject) {}
		public function set_mailtype($type) {}
		public function message($message) {}
		public function send() { return true; }
	}
}

namespace FreePBX\modules {
	require_once dirname(__DIR__) . '/Registrationwatch.class.php';

	function email_from_assert($condition, string $message): void {
		if (!$condition) {
			throw new \RuntimeException($message);
		}
	}

	class EmailFromConfigStub {
		private $values;
		public function __construct(array $values) { $this->values = $values; }
		public function get($key) { return isset($this->values[$key]) ? $this->values[$key] : ''; }
	}

	class EmailFromFreePBXStub {
		public $Config;
		public function __construct(array $values) { $this->Config = new EmailFromConfigStub($values); }
	}

	class EmailFromMethodFreePBXStub {
		private $config;
		public function __construct(array $values) { $this->config = new EmailFromConfigStub($values); }
		public function Config() { return $this->config; }
	}

	function resolve_email_from(string $configured, string $brand = 'FreePBX'): array {
		$watch = new Registrationwatch(new EmailFromFreePBXStub([
			'AMPUSERMANEMAILFROM' => $configured,
			'DASHBOARD_FREEPBX_BRAND' => $brand,
		]));
		$method = new \ReflectionMethod($watch, 'resolveNotificationFrom');
		$method->setAccessible(true);
		return $method->invoke($watch);
	}

	email_from_assert(resolve_email_from('asterisk@demodomain.name') === ['address' => 'asterisk@demodomain.name', 'name' => 'FreePBX'], 'bare address should use the existing brand fallback');
	email_from_assert(resolve_email_from('<asterisk@demodomain.name>') === ['address' => 'asterisk@demodomain.name', 'name' => 'FreePBX'], 'angle-bracket address without a display name should use the existing brand fallback');
	email_from_assert(resolve_email_from('PBX-123 <asterisk@demodomain.name>') === ['address' => 'asterisk@demodomain.name', 'name' => 'PBX-123'], 'unquoted display name should be preserved');
	$encodedFreePbx17From = 'PBXSRV28-LON &' . 'lt;asterisk@freepbx.uk&' . 'gt;';
	email_from_assert(resolve_email_from($encodedFreePbx17From) === ['address' => 'asterisk@freepbx.uk', 'name' => 'PBXSRV28-LON'], 'FreePBX 17 HTML-encoded angle brackets should be decoded before parsing');
	email_from_assert(resolve_email_from('JaCoTec TK-System <pbx@mydomain.de>') === ['address' => 'pbx@mydomain.de', 'name' => 'JaCoTec TK-System'], 'display names containing spaces and hyphens should be preserved');
	email_from_assert(resolve_email_from('  PBX-123 <asterisk@demodomain.name>  ') === ['address' => 'asterisk@demodomain.name', 'name' => 'PBX-123'], 'surrounding whitespace should be ignored');
	email_from_assert(resolve_email_from('PBX-123 <asterisk@demodomain.name') === ['address' => '', 'name' => ''], 'malformed angle-bracket input should fail safely');
	email_from_assert(resolve_email_from('PBX <junk> <asterisk@demodomain.name>') === ['address' => '', 'name' => ''], 'angle brackets in the display-name portion should fail safely');
	email_from_assert(resolve_email_from('PBX-123 <<asterisk@demodomain.name>>') === ['address' => '', 'name' => ''], 'multiple nested angle-bracket pairs should fail safely');
	email_from_assert(resolve_email_from('PBX-123 <not-an-email>') === ['address' => '', 'name' => ''], 'invalid extracted address should fail validation');
	email_from_assert(resolve_email_from('') === ['address' => '', 'name' => ''], 'empty setting should fail safely');
	email_from_assert(resolve_email_from("PBX-123 <asterisk@demodomain.name>\r\nBcc: attacker@example.com") === ['address' => '', 'name' => ''], 'CR/LF in the configured From value should fail safely');
	$encodedCrLfFrom = 'PBX-123 &' . 'lt;asterisk@demodomain.name&' . 'gt;&' . '#13;&' . '#10;Bcc: attacker@example.com';
	email_from_assert(resolve_email_from($encodedCrLfFrom) === ['address' => '', 'name' => ''], 'HTML-entity-encoded CR/LF should be decoded and rejected');
	email_from_assert(resolve_email_from('asterisk@demodomain.name', '') === ['address' => 'asterisk@demodomain.name', 'name' => 'Registration Watch'], 'bare address should retain the Registration Watch fallback when the brand is empty');

	$methodWatch = new Registrationwatch(new EmailFromMethodFreePBXStub([
		'AMPUSERMANEMAILFROM' => 'Method PBX <method@example.com>',
		'DASHBOARD_FREEPBX_BRAND' => 'Method Brand',
	]));
	$resolveMethod = new \ReflectionMethod($methodWatch, 'resolveNotificationFrom');
	$resolveMethod->setAccessible(true);
	email_from_assert($resolveMethod->invoke($methodWatch) === ['address' => 'method@example.com', 'name' => 'Method PBX'], 'method-based FreePBX Config()->get() access should resolve the configured From identity');

	$watch = new Registrationwatch(new EmailFromFreePBXStub([
		'AMPUSERMANEMAILFROM' => 'PBX-123 <asterisk@demodomain.name>',
		'DASHBOARD_FREEPBX_BRAND' => 'Ignored Brand',
	]));
	$send = new \ReflectionMethod($watch, 'sendEmail');
	$send->setAccessible(true);
	$result = $send->invoke($watch, 'admin@example.com', 'subject', 'message');
	email_from_assert($result['status'] === true, 'valid resolved From identity should send');
	email_from_assert(\CI_Email::$last->fromArgs === ['asterisk@demodomain.name', 'PBX-123', 'asterisk@demodomain.name'], 'mail send should use the extracted address and configured display name');
	email_from_assert(\CI_Email::$last->replyToArgs === ['asterisk@demodomain.name', 'PBX-123'], 'reply-to should use the same resolved From identity');

	echo "Email From contract tests passed.\n";
}
