<?php
/**
 * Remove duplicate resident applications and resident records.
 *
 * Usage (browser):
 *   /Barangay219/remove-duplicates.php?confirm=1
 *
 * Usage (CLI):
 *   php remove-duplicates.php --confirm
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/config/database.php';

$isCli = (php_sapi_name() === 'cli');
$confirmed = $isCli
    ? in_array('--confirm', $argv ?? [], true)
    : (($_GET['confirm'] ?? '') === '1');

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
}

function out($message) {
    global $isCli;
    if ($isCli) {
        echo $message . PHP_EOL;
    } else {
        echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "<br>\n";
    }
}

function normalizeName($value) {
    $value = strtolower(trim((string)$value));
    return preg_replace('/\s+/', ' ', $value);
}

function normalizeDigits($value) {
    return preg_replace('/\D+/', '', (string)$value);
}

function normalizeAlphaNum($value) {
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', (string)$value));
}

function hasTable($db, $table) {
    $quotedTable = $db->getConnection()->quote($table);
    $row = $db->fetchOne("SHOW TABLES LIKE {$quotedTable}");
    return !empty($row);
}

function hasColumn($db, $table, $column) {
    $row = $db->fetchOne(
        'SELECT COUNT(*) AS cnt FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
        [$table, $column]
    );
    return !empty($row) && (int)$row['cnt'] > 0;
}

function appStatusPriority($status) {
    if ($status === 'approved') return 3;
    if ($status === 'pending') return 2;
    if ($status === 'rejected') return 1;
    return 0;
}

function residentStatusPriority($status) {
    if ($status === 'active') return 2;
    if ($status === 'inactive') return 1;
    return 0;
}

if (!$isCli) {
    echo "<!doctype html><html><head><meta charset=\"utf-8\"><title>Duplicate Cleanup</title></head><body><pre>";
}

out('Duplicate cleanup started...');
out('Mode: ' . ($confirmed ? 'EXECUTE' : 'DRY RUN'));

if (!$confirmed) {
    out('No changes will be made unless confirm=1 (browser) or --confirm (CLI) is provided.');
}

$db = Database::getInstance();

$summary = [
    'applications_groups' => 0,
    'applications_removed' => 0,
    'applications_logs_repointed' => 0,
    'residents_groups' => 0,
    'residents_removed' => 0,
    'refs_users_updated' => 0,
    'refs_households_updated' => 0,
    'refs_certificates_updated' => 0,
    'refs_complaints_updated' => 0,
    'refs_document_requests_updated' => 0
];

$hasApplicationAudit = hasTable($db, 'application_audit_log');
$hasUsers = hasTable($db, 'users') && hasColumn($db, 'users', 'resident_id');
$hasHouseholds = hasTable($db, 'households') && hasColumn($db, 'households', 'family_head_id');
$hasCertificates = hasTable($db, 'certificate_requests') && hasColumn($db, 'certificate_requests', 'resident_id');
$hasComplaints = hasTable($db, 'complaints') && hasColumn($db, 'complaints', 'resident_id');
$hasDocumentRequests = hasTable($db, 'document_requests') && hasColumn($db, 'document_requests', 'resident_id');

try {
    $db->beginTransaction();

    // 1) Deduplicate resident_applications.
    if (hasTable($db, 'resident_applications')) {
        out('Scanning resident_applications...');

        $apps = $db->fetchAll(
            'SELECT id, application_ref, first_name, last_name, birth_date, mobile_number, valid_id_type, valid_id_number, record_status, created_at
             FROM resident_applications
             ORDER BY created_at DESC, id DESC'
        );

        $groups = [];
        foreach ($apps as $app) {
            $normId = normalizeAlphaNum($app['valid_id_number'] ?? '');
            $idType = strtolower(trim((string)($app['valid_id_type'] ?? '')));
            if ($idType !== '' && $normId !== '') {
                $key = 'id|' . $idType . '|' . $normId;
            } else {
                $first = normalizeName($app['first_name'] ?? '');
                $last = normalizeName($app['last_name'] ?? '');
                $birth = (string)($app['birth_date'] ?? '');
                $mobile = normalizeDigits($app['mobile_number'] ?? '');
                if ($first === '' || $last === '' || $birth === '' || $mobile === '') {
                    continue;
                }
                $key = 'person|' . $first . '|' . $last . '|' . $birth . '|' . $mobile;
            }
            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }
            $groups[$key][] = $app;
        }

        foreach ($groups as $key => $rows) {
            if (count($rows) < 2) {
                continue;
            }

            $summary['applications_groups']++;

            usort($rows, static function ($a, $b) {
                $pa = appStatusPriority($a['record_status'] ?? '');
                $pb = appStatusPriority($b['record_status'] ?? '');
                if ($pa !== $pb) return $pb <=> $pa;
                $ca = strtotime((string)($a['created_at'] ?? '1970-01-01'));
                $cb = strtotime((string)($b['created_at'] ?? '1970-01-01'));
                if ($ca !== $cb) return $cb <=> $ca;
                return ((int)$b['id']) <=> ((int)$a['id']);
            });

            $keep = $rows[0];
            out('Application duplicate group: keep ID ' . $keep['id'] . ' (' . $keep['application_ref'] . '), remove ' . (count($rows) - 1));

            for ($i = 1; $i < count($rows); $i++) {
                $dup = $rows[$i];
                $dupId = (int)$dup['id'];
                $keepId = (int)$keep['id'];

                if ($confirmed && $hasApplicationAudit) {
                    $moved = $db->query('UPDATE application_audit_log SET application_id = ? WHERE application_id = ?', [$keepId, $dupId])->rowCount();
                    $summary['applications_logs_repointed'] += $moved;
                }

                if ($confirmed) {
                    $db->query('DELETE FROM resident_applications WHERE id = ?', [$dupId]);
                }
                $summary['applications_removed']++;
            }
        }
    } else {
        out('Skipping resident_applications: table not found.');
    }

    // 2) Deduplicate residents.
    if (hasTable($db, 'residents')) {
        out('Scanning residents...');

        $residents = $db->fetchAll(
            'SELECT id, first_name, last_name, birth_date, contact_number, email, status, updated_at, created_at
             FROM residents
             ORDER BY updated_at DESC, id DESC'
        );

        $groups = [];
        foreach ($residents as $resident) {
            $first = normalizeName($resident['first_name'] ?? '');
            $last = normalizeName($resident['last_name'] ?? '');
            $birth = (string)($resident['birth_date'] ?? '');
            $contact = normalizeDigits($resident['contact_number'] ?? '');
            $email = strtolower(trim((string)($resident['email'] ?? '')));

            if ($first === '' || $last === '' || $birth === '') {
                continue;
            }

            $qualifier = $contact !== '' ? ('m|' . $contact) : ($email !== '' ? ('e|' . $email) : '');
            if ($qualifier === '') {
                continue;
            }

            $key = $first . '|' . $last . '|' . $birth . '|' . $qualifier;
            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }
            $groups[$key][] = $resident;
        }

        foreach ($groups as $key => $rows) {
            if (count($rows) < 2) {
                continue;
            }

            $summary['residents_groups']++;

            $rowsWithScore = [];
            foreach ($rows as $row) {
                $id = (int)$row['id'];
                $score = 0;

                if ($hasUsers) {
                    $score += (int)$db->fetchOne('SELECT COUNT(*) AS c FROM users WHERE resident_id = ?', [$id])['c'] * 100;
                }
                if ($hasHouseholds) {
                    $score += (int)$db->fetchOne('SELECT COUNT(*) AS c FROM households WHERE family_head_id = ?', [$id])['c'] * 50;
                }
                if ($hasCertificates) {
                    $score += (int)$db->fetchOne('SELECT COUNT(*) AS c FROM certificate_requests WHERE resident_id = ?', [$id])['c'] * 20;
                }
                if ($hasComplaints) {
                    $score += (int)$db->fetchOne('SELECT COUNT(*) AS c FROM complaints WHERE resident_id = ?', [$id])['c'] * 10;
                }
                if ($hasDocumentRequests) {
                    $score += (int)$db->fetchOne('SELECT COUNT(*) AS c FROM document_requests WHERE resident_id = ?', [$id])['c'] * 10;
                }

                $score += residentStatusPriority($row['status'] ?? '') * 5;
                $score += (int)$id;

                $row['_score'] = $score;
                $rowsWithScore[] = $row;
            }

            usort($rowsWithScore, static function ($a, $b) {
                if ($a['_score'] !== $b['_score']) return $b['_score'] <=> $a['_score'];
                $ua = strtotime((string)($a['updated_at'] ?? '1970-01-01'));
                $ub = strtotime((string)($b['updated_at'] ?? '1970-01-01'));
                if ($ua !== $ub) return $ub <=> $ua;
                return ((int)$b['id']) <=> ((int)$a['id']);
            });

            $keep = $rowsWithScore[0];
            $keepId = (int)$keep['id'];
            out('Resident duplicate group: keep ID ' . $keepId . ', remove ' . (count($rowsWithScore) - 1));

            for ($i = 1; $i < count($rowsWithScore); $i++) {
                $dup = $rowsWithScore[$i];
                $dupId = (int)$dup['id'];

                if ($confirmed && $hasUsers) {
                    $moved = $db->query('UPDATE users SET resident_id = ? WHERE resident_id = ?', [$keepId, $dupId])->rowCount();
                    $summary['refs_users_updated'] += $moved;
                }
                if ($confirmed && $hasHouseholds) {
                    $moved = $db->query('UPDATE households SET family_head_id = ? WHERE family_head_id = ?', [$keepId, $dupId])->rowCount();
                    $summary['refs_households_updated'] += $moved;
                }
                if ($confirmed && $hasCertificates) {
                    $moved = $db->query('UPDATE certificate_requests SET resident_id = ? WHERE resident_id = ?', [$keepId, $dupId])->rowCount();
                    $summary['refs_certificates_updated'] += $moved;
                }
                if ($confirmed && $hasComplaints) {
                    $moved = $db->query('UPDATE complaints SET resident_id = ? WHERE resident_id = ?', [$keepId, $dupId])->rowCount();
                    $summary['refs_complaints_updated'] += $moved;
                }
                if ($confirmed && $hasDocumentRequests) {
                    $moved = $db->query('UPDATE document_requests SET resident_id = ? WHERE resident_id = ?', [$keepId, $dupId])->rowCount();
                    $summary['refs_document_requests_updated'] += $moved;
                }

                if ($confirmed) {
                    $db->query('DELETE FROM residents WHERE id = ?', [$dupId]);
                }

                $summary['residents_removed']++;
            }
        }
    } else {
        out('Skipping residents: table not found.');
    }

    if ($confirmed) {
        $db->commit();
        out('Transaction committed.');
    } else {
        $db->rollback();
        out('Dry run complete. Transaction rolled back.');
    }
} catch (Exception $e) {
    try {
        $db->rollback();
    } catch (Exception $ignored) {
        // Ignore rollback failures.
    }
    out('ERROR: ' . $e->getMessage());

    if (!$isCli) {
        echo '</pre></body></html>';
    }
    exit(1);
}

out('--- Summary ---');
out('Duplicate application groups: ' . $summary['applications_groups']);
out('Duplicate applications removed: ' . $summary['applications_removed']);
out('Application audit logs repointed: ' . $summary['applications_logs_repointed']);
out('Duplicate resident groups: ' . $summary['residents_groups']);
out('Duplicate residents removed: ' . $summary['residents_removed']);
out('Updated users.resident_id refs: ' . $summary['refs_users_updated']);
out('Updated households.family_head_id refs: ' . $summary['refs_households_updated']);
out('Updated certificate_requests.resident_id refs: ' . $summary['refs_certificates_updated']);
out('Updated complaints.resident_id refs: ' . $summary['refs_complaints_updated']);
out('Updated document_requests.resident_id refs: ' . $summary['refs_document_requests_updated']);

if ($confirmed) {
    out('Duplicate cleanup finished successfully.');
} else {
    out('To apply changes: run with confirm=1 or --confirm.');
}

if (!$isCli) {
    echo '</pre></body></html>';
}
