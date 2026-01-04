<?php
// system_functions.php

/**
 * Get system setting value
 */
function getSystemSetting($conn, $key, $default = null) {
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = :key LIMIT 1");
        $stmt->execute(['key' => $key]);
        $result = $stmt->fetch();
        
        return $result ? $result['setting_value'] : $default;
    } catch(PDOException $e) {
        return $default;
    }
}

/**
 * Update system setting
 */
function updateSystemSetting($conn, $key, $value, $user_id = null) {
    try {
        $stmt = $conn->prepare("
            UPDATE system_settings 
            SET setting_value = :value, 
                updated_by = :user_id,
                last_updated = CURRENT_TIMESTAMP
            WHERE setting_key = :key
        ");
        
        return $stmt->execute([
            'value' => $value,
            'key' => $key,
            'user_id' => $user_id
        ]);
    } catch(PDOException $e) {
        return false;
    }
}

/**
 * Get all system settings as array
 */
function getAllSystemSettings($conn) {
    try {
        $stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings");
        $settings = [];
        
        while($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return $settings;
    } catch(PDOException $e) {
        return [];
    }
}

/**
 * Get current academic info
 */
function getCurrentAcademicInfo($conn) {
    return [
        'school_year' => getSystemSetting($conn, 'current_school_year', '2025-2026'),
        'quarter' => getSystemSetting($conn, 'current_quarter', '2nd Quarter'),
        'school_name' => getSystemSetting($conn, 'school_name', 'Creative Dreams School')
    ];
}
?>