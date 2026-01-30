<?php
/**
 * Raynet Sync Helper Functions
 * 
 * Standalone functions for triggering Raynet sync from form submission.
 * Include this file to add automatic sync after form completion.
 */

require_once __DIR__ . '/Raynet/autoload.php';

use Raynet\RaynetConnector;
use Raynet\RaynetException;

/**
 * Sync a form to Raynet CRM
 * 
 * Call this after a form is submitted (status = 'submitted').
 * Safe to call - will silently fail if Raynet is not configured.
 * 
 * @param array $formData The form data
 * @param string|int $formId The form ID (can be UUID string or int)
 * @param PDO|null $pdo Optional PDO connection for status tracking
 * @return array|null Sync result or null if not configured
 */
function syncFormToRaynet(array $formData, string|int $formId, ?\PDO $pdo = null): ?array
{
    try {
        $connector = RaynetConnector::create($pdo);
        
        // Skip if not configured
        if (!$connector->isConfigured()) {
            error_log("Raynet sync skipped: connector not configured");
            return null;
        }
        
        return $connector->syncForm($formData, $formId);
        
    } catch (RaynetException $e) {
        error_log("Raynet sync failed for form {$formId}: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    } catch (\Exception $e) {
        error_log("Raynet sync unexpected error for form {$formId}: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Unexpected error'
        ];
    }
}

/**
 * Check if Raynet connector is configured
 */
function isRaynetConfigured(): bool
{
    try {
        $connector = RaynetConnector::create();
        return $connector->isConfigured();
    } catch (\Exception $e) {
        return false;
    }
}

/**
 * Queue form for async Raynet sync
 * 
 * Stores the form ID for later batch processing.
 * Useful when you don't want to block form submission.
 */
function queueFormForRaynetSync(int $formId, \PDO $pdo): bool
{
    try {
        // Just ensure sync columns are null so it gets picked up by syncPendingForms
        $stmt = $pdo->prepare("
            UPDATE forms SET 
                raynet_synced_at = NULL,
                raynet_sync_error = NULL
            WHERE id = ? AND status = 'submitted'
        ");
        return $stmt->execute([$formId]);
    } catch (\PDOException $e) {
        error_log("Failed to queue form {$formId} for Raynet sync: " . $e->getMessage());
        return false;
    }
}
