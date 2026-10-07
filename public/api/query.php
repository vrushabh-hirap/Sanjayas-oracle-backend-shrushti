<?php
/**
 * query.php — the vulnerable registry search (standalone OOB-SQLi edition).
 *
 * POST JSON: { "q": "<player input>" }
 * Response:  { "rows": [] }   (always empty — the Oracle never speaks)
 *
 * The door returns nothing in-band, exactly like the original challenge.
 * BUT: if the payload contains an out-of-band exfiltration host (a UNC path
 * like  \\len-<domain>\a  — the shape xp_dirtree would use), this endpoint
 * actually reaches out to that host over DNS, carrying the value the payload
 * asks for. That is the only way data leaves the Oracle.
 *
 * Supported payload intents (detected from the SQL-ish body):
 *   - SUBSTRING(upnaam,N,1)  ->  posN-<char>.<domain>
 *   - LEN(upnaam)            ->  len-<length>.<domain>
 *   - vow_flag               ->  vow-<0|1>.<domain>
 *   - anything else          ->  confirm.<domain>
 */

require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json');

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$q = (string) ($input['q'] ?? '');

// Preserve the original (weak, cosmetic) filter.
$blocked = ['UNION', 'OR 1=1', 'OR 1 =1', 'OR 1= 1'];
$q = str_ireplace($blocked, '****', $q);

/**
 * Extract the exfiltration host from the payload.
 * Prefers a host found inside a UNC path (\\...\  — what xp_dirtree uses),
 * falls back to any hostname-like token if no UNC path is present.
 */
function oob_extract_host(string $payload): ?string
{
    $ignore = ['master.dbo', 'master.dbo.xp_dirtree', 'xp_dirtree', 'dbo'];

    $candidates = [];

    // 1) Prefer text captured from a UNC path: \\<capture>\
    if (preg_match_all('/\\\\+([^\\\\]+)/', $payload, $unc)) {
        foreach ($unc[1] as $chunk) {
            if (preg_match_all('/([a-z0-9][a-z0-9.-]*\.[a-z]{2,})/i', $chunk, $m)) {
                foreach ($m[1] as $c) {
                    $candidates[] = $c;
                }
            }
        }
    }

    // 2) Fall back to scanning the whole payload.
    if (empty($candidates)) {
        if (preg_match_all('/([a-z0-9][a-z0-9.-]*\.[a-z]{2,})/i', $payload, $m)) {
            foreach ($m[1] as $c) {
                $candidates[] = $c;
            }
        }
    }

    $best = null;
    $bestParts = 0;
    foreach ($candidates as $cand) {
        $c = strtolower(rtrim($cand, '.'));
        if ($c === '' || strpos($c, '<') !== false || strpos($c, '>') !== false) {
            continue;
        }
        if (in_array($c, $ignore, true)) {
            continue;
        }
        $parts = count(explode('.', $c));
        if ($parts > $bestParts) {
            $bestParts = $parts;
            $best = $c;
        }
    }

    return $best;
}

/**
 * If the extracted host already begins with the label the player wrote
 * (e.g. "confirm.", "vow-1."), strip it so we don't duplicate it.
 */
function oob_strip_label(string $host): string
{
    $parts = explode('.', $host);
    if (count($parts) <= 2) {
        return $host;
    }
    if (preg_match('/^(confirm|len-.+|pos\d+-.+|vow-.+)$/i', $parts[0])) {
        array_shift($parts);
    }
    return implode('.', $parts);
}

/**
 * Fire the out-of-band lookup. DNS is the primary channel (matches xp_dirtree);
 * an HTTP attempt is added as a redundancy for listeners that log both.
 */
function oob_fire(string $hostname): void
{
    if (getenv('OOB_DEBUG')) {
        error_log('[OOB] firing ' . $hostname);
    }

    // DNS exfiltration — the listener sees this subdomain.
    @gethostbyname($hostname);

    // Best-effort HTTP beacon (Burp/Collaborator & Interactsh log this too).
    $ctx = stream_context_create([
        'http' => ['timeout' => 2, 'method' => 'GET', 'ignore_errors' => true],
    ]);
    @file_get_contents('http://' . $hostname . '/', false, $ctx);
    if (getenv('OOB_DEBUG')) {
        error_log('[OOB] beacon done ' . $hostname);
    }
}

$rows = [];

if ($q !== '') {
    try {
        $pdo = db();
        // Read the hidden record (server-side only — never returned).
        $row  = $pdo->query("SELECT upnaam, vow_flag FROM drishti_sakshi LIMIT 1")->fetch();
        $name = $row ? (string) $row['upnaam'] : '';
        $vow  = $row ? (int) $row['vow_flag'] : 0;

        $domain = oob_extract_host($q);

        if ($domain !== null) {
            if (preg_match('/SUBSTRING\s*\(\s*upnaam\s*,\s*(\d+)/i', $q, $m)) {
                $pos   = (int) $m[1];
                $char  = substr($name, $pos - 1, 1);
                $label = 'pos' . $pos . '-' . $char;
            } elseif (stripos($q, 'LEN(') !== false || stripos($q, 'LEN (') !== false) {
                $label = 'len-' . strlen($name);
            } elseif (stripos($q, 'vow_flag') !== false) {
                $label = 'vow-' . $vow;
            } else {
                $label = 'confirm';
            }

            $domain = oob_strip_label($domain);
            oob_fire($label . '.' . $domain);
        }
    } catch (\Throwable $e) {
        // Silent by design: no errors, no rows.
    }
}

echo json_encode(['rows' => $rows]);
