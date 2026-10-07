<?php
/**
 * check.php — server-side gate validation + flag reveal.
 *
 * POST JSON: { "type": "channel|length|name|vow|guess", "value": <mixed> }
 * Response:  { "ok": bool, "flag"?: string, "message"?: string }
 *
 * The accepted answers and the flag live ONLY here (server side),
 * so they are no longer visible in the front-end page source.
 */

header('Content-Type: application/json');

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$type  = $input['type']  ?? '';
$value = $input['value'] ?? '';

$ACCEPTED_NAMES = ['barbarik', 'barbareek', 'barbarika'];
$OTHER_NAMES    = ['iravan', 'iravat', 'iravaan'];
$FLAG           = 'kctf{66-65-82-66-65-82-73-75}';

$ok      = false;
$message = '';
$flag    = null;

switch ($type) {
    case 'channel':
        // Any non-empty proof of an out-of-band callback.
        $ok = (trim((string) $value) !== '');
        break;

    case 'length':
        $ok = ((int) $value === 8);
        break;

    case 'name':
        $ok = in_array(strtolower(trim((string) $value)), $ACCEPTED_NAMES, true);
        break;

    case 'vow':
        $ok = ((int) $value === 1);
        break;

    case 'guess':
        $guess = strtolower(trim((string) $value));
        if (in_array($guess, $ACCEPTED_NAMES, true)) {
            $ok   = true;
            $flag = $FLAG;
        } elseif (in_array($guess, $OTHER_NAMES, true)) {
            $message = 'That name belongs to another record in the archive. Look again.';
        } else {
            $message = 'The Oracle does not confirm that name. The evidence points elsewhere.';
        }
        break;

    default:
        $message = 'Unknown gate.';
}

echo json_encode([
    'ok'      => $ok,
    'flag'    => $flag,
    'message' => $message,
]);
