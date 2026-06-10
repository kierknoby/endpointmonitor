<?php

// EndPoint Monitor uninstall hook.

global $db;


try {
        if (class_exists('\FreePBX')) {
                \FreePBX::Job()->remove('endpointmonitor', 'monitor');
        }
} catch (\Throwable $e) {
        if (class_exists('\FreePBX') && method_exists('\FreePBX', 'Log')) {
                \FreePBX::Log()->error('endpointmonitor: failed to remove background job: ' . $e->getMessage());
        }
}

$db->query('DROP TABLE IF EXISTS endpointmonitor_settings');
$db->query('DROP TABLE IF EXISTS endpointmonitor_alert_history');
$db->query('DROP TABLE IF EXISTS endpointmonitor_status_history');
$db->query('DROP TABLE IF EXISTS endpointmonitor_endpoints');
