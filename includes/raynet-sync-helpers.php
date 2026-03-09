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

/**
 * Check for duplicates and sync form to Raynet only if safe.
 *
 * Called after GDPR confirmation. If a duplicate company is found in Raynet
 * (matched by IČO, extId, etc.), the form is NOT synced automatically.
 * Instead it is flagged as 'pending_approval' for an admin to review.
 *
 * @param array      $formData  The form data
 * @param string|int $formId    The form ID
 * @param \PDO       $pdo       Database connection
 * @return array Result with keys: status ('synced'|'pending_approval'|'error'), details
 */
function checkAndSyncFormToRaynet(array $formData, string|int $formId, \PDO $pdo): array
{
    try {
        $connector = RaynetConnector::create($pdo);

        if (!$connector->isConfigured()) {
            error_log("Raynet checkAndSync skipped: connector not configured");
            return ['status' => 'error', 'error' => 'Raynet connector is not configured'];
        }

        // --- Pre-flight duplicate check ---
        $checker = new \Raynet\RaynetDuplicateChecker($connector->getClient());
        $company = $connector->company();

        // Parse form data the same way RaynetCompany does
        $parsed = $formData;
        if (isset($formData['form_data'])) {
            $decoded = is_string($formData['form_data'])
                ? json_decode($formData['form_data'], true)
                : (is_array($formData['form_data']) ? $formData['form_data'] : null);
            if ($decoded) {
                $parsed = array_merge($formData, $decoded);
            }
        }

        $extId = "energyforms:{$formId}";
        $existing = $checker->findExistingCompany($extId, [
            'ico'       => $parsed['ico'] ?? null,
            'taxNumber' => $parsed['dic'] ?? null,
            'name'      => $parsed['companyName'] ?? null,
        ]);

        if ($existing) {
            // --- Check admin setting: should we force-sync duplicates? ---
            $forceDuplicates = false;
            $notifyEmail = '';
            try {
                // Check if settings table exists first
                $tableCheck = $pdo->query("SHOW TABLES LIKE 'settings'");
                if ($tableCheck->rowCount() > 0) {
                    $settingsStmt = $pdo->prepare("SELECT `key`, `value` FROM settings WHERE `key` IN ('raynet_force_duplicates', 'raynet_duplicate_notify_email')");
                    $settingsStmt->execute();
                    foreach ($settingsStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                        if ($row['key'] === 'raynet_force_duplicates') $forceDuplicates = ($row['value'] === '1');
                        if ($row['key'] === 'raynet_duplicate_notify_email') $notifyEmail = $row['value'] ?? '';
                    }
                } else {
                    error_log("Raynet checkAndSync: settings table does not exist yet – using defaults");
                }
            } catch (\Exception $e) {
                error_log("Raynet checkAndSync: failed to read duplicate settings: " . $e->getMessage());
            }

            // Fallback: if no notification email configured, use info@electree.cz
            if (empty($notifyEmail)) {
                $notifyEmail = 'info@electree.cz';
                error_log("Raynet checkAndSync: no notification email configured, using fallback: {$notifyEmail}");
            }

            if ($forceDuplicates) {
                // Admin chose to auto-sync even when duplicates exist – update existing company
                error_log("Raynet checkAndSync: duplicate found for form {$formId} but force_duplicates is ON – syncing anyway");
                $result = $connector->syncForm($formData, $formId);

                if ($result['success']) {
                    $stmt = $pdo->prepare("UPDATE forms SET raynet_sync_status = 'synced' WHERE id = ?");
                    $stmt->execute([$formId]);
                    return [
                        'status'     => 'synced',
                        'company_id' => $result['company_id'],
                        'person_id'  => $result['person_id'],
                    ];
                }

                $stmt = $pdo->prepare("UPDATE forms SET raynet_sync_status = 'error' WHERE id = ?");
                $stmt->execute([$formId]);
                return ['status' => 'error', 'error' => $result['error'] ?? 'Unknown sync error'];
            }

            // --- Not forcing: flag for manual approval and notify admin ---
            $matchInfo = [
                'raynet_company_id' => $existing['id'],
                'matched_by'        => $existing['matched_by'],
                'company_name'      => $existing['data']['name'] ?? '',
            ];

            $errorMsg = 'PENDING_APPROVAL: Nalezena existující firma v Raynet'
                . ' (shoda: ' . $existing['matched_by'] . ', ID: ' . $existing['id'] . ')';

            $stmt = $pdo->prepare("
                UPDATE forms SET
                    raynet_sync_status = 'pending_approval',
                    raynet_sync_error  = ?
                WHERE id = ?
            ");
            $stmt->execute([$errorMsg, $formId]);

            error_log("Raynet checkAndSync: duplicate found for form {$formId} – matched by {$existing['matched_by']}, Raynet ID {$existing['id']}");

            // Send notification email to admin
            sendDuplicateNotificationEmail($notifyEmail, $formId, $parsed, $existing);

            return [
                'status'  => 'pending_approval',
                'message' => $errorMsg,
                'match'   => $matchInfo,
            ];
        }

        // --- No duplicate found – safe to create new company ---
        $result = $connector->syncForm($formData, $formId);

        if ($result['success']) {
            // Update sync status
            $stmt = $pdo->prepare("UPDATE forms SET raynet_sync_status = 'synced' WHERE id = ?");
            $stmt->execute([$formId]);

            return [
                'status'     => 'synced',
                'company_id' => $result['company_id'],
                'person_id'  => $result['person_id'],
            ];
        }

        // Sync attempted but failed
        $stmt = $pdo->prepare("UPDATE forms SET raynet_sync_status = 'error' WHERE id = ?");
        $stmt->execute([$formId]);

        return [
            'status' => 'error',
            'error'  => $result['error'] ?? 'Unknown sync error',
        ];

    } catch (RaynetException $e) {
        error_log("Raynet checkAndSync failed for form {$formId}: " . $e->getMessage());

        try {
            $stmt = $pdo->prepare("
                UPDATE forms SET
                    raynet_sync_status = 'error',
                    raynet_sync_error  = ?
                WHERE id = ?
            ");
            $stmt->execute([$e->getMessage(), $formId]);
        } catch (\Exception $dbErr) {
            error_log("Failed to update sync error: " . $dbErr->getMessage());
        }

        return ['status' => 'error', 'error' => $e->getMessage()];

    } catch (\Exception $e) {
        error_log("Raynet checkAndSync unexpected error for form {$formId}: " . $e->getMessage());

        try {
            $stmt = $pdo->prepare("
                UPDATE forms SET
                    raynet_sync_status = 'error',
                    raynet_sync_error  = ?
                WHERE id = ?
            ");
            $stmt->execute([$e->getMessage(), $formId]);
        } catch (\Exception $dbErr) {
            error_log("Failed to update sync error: " . $dbErr->getMessage());
        }

        return ['status' => 'error', 'error' => 'Unexpected error'];
    }
}

/**
 * Send notification email to admin about a duplicate company found during sync.
 */
function sendDuplicateNotificationEmail(string $email, string|int $formId, array $formData, array $existing): void
{
    $syncUrl = "https://ed.electree.cz/admin-sync.php";
    $companyName = $formData['companyName'] ?? 'Neznámá firma';
    $ico = $formData['ico'] ?? 'N/A';
    $matchedBy = $existing['matched_by'] ?? '?';
    $raynetId = $existing['id'] ?? '?';
    $raynetName = $existing['data']['name'] ?? '';

    $subject = "Raynet sync – duplicitní firma pro formulář #{$formId}";
    $body = "
        <h2>Nalezena duplicitní firma při synchronizaci</h2>
        <p>Při automatické synchronizaci formuláře <strong>#{$formId}</strong> do Raynet CRM byla nalezena existující firma.</p>

        <h3>Formulář:</h3>
        <ul>
            <li><strong>ID:</strong> " . htmlspecialchars((string)$formId) . "</li>
            <li><strong>Firma:</strong> " . htmlspecialchars($companyName) . "</li>
            <li><strong>IČO:</strong> " . htmlspecialchars($ico) . "</li>
        </ul>

        <h3>Existující firma v Raynet:</h3>
        <ul>
            <li><strong>Raynet ID:</strong> " . htmlspecialchars((string)$raynetId) . "</li>
            <li><strong>Název:</strong> " . htmlspecialchars($raynetName) . "</li>
            <li><strong>Shoda:</strong> " . htmlspecialchars($matchedBy) . "</li>
        </ul>

        <p>Synchronizace byla pozastavena a čeká na vaše manuální schválení.</p>
        <p style='margin: 20px 0;'>
            <a href='" . htmlspecialchars($syncUrl) . "' style='background-color: #ea580c; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                Otevřít správu synchronizace
            </a>
        </p>
    ";

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: noreply@electree.cz',
        'Reply-To: info@electree.cz',
    ];

    $sent = mail($email, $subject, $body, implode("\r\n", $headers));
    if (!$sent) {
        error_log("Failed to send duplicate notification email to {$email} for form {$formId}");
    } else {
        error_log("Duplicate notification email sent to {$email} for form {$formId}");
    }
}
