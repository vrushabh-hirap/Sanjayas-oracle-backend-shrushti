<?php
/**
 * query.php — the vulnerable registry search.
 *
 * POST JSON: { "q": "<player input>" }
 * Response:  { "rows": [] }   (always empty — the Oracle never speaks)
 *
 * This mirrors the original challenge: the concatenated query is the
 * injection point, but the door returns nothing. Kept for authenticity.
 */

require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json');

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$q = (string) ($input['q'] ?? '');

// Preserve the original (weak) filter.
$blocked = ['UNION', 'OR 1=1', 'OR 1 =1', 'OR 1= 1'];
$q = str_ireplace($blocked, '****', $q);

$rows = [];

if ($q !== '') {
    try {
        $pdo = db();
        // Intentionally concatenated (vulnerable) — matches the challenge.
        $stmt = $pdo->query("SELECT upnaam FROM drishti_sakshi WHERE upnaam = '$q'");
        // The Oracle never returns rows through this door.
        $rows = [];
    } catch (\Throwable $e) {
        // Silent by design: no errors, no rows.
        $rows = [];
    }
}

echo json_encode(['rows' => $rows]);
