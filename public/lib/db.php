<?php
/**
 * db.php — SQLite bootstrap + query helpers for the Oracle API.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $path = getenv('DB_PATH') ?: dirname(__DIR__, 2) . '/data/oracle2_ctf.db';
    $dir  = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS drishti_sakshi (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            upnaam   TEXT    NOT NULL,
            vow_flag INTEGER NOT NULL DEFAULT 0
        )"
    );

    $count = (int) $pdo->query("SELECT COUNT(*) FROM drishti_sakshi")->fetchColumn();
    if ($count === 0) {
        $pdo->exec("INSERT INTO drishti_sakshi (upnaam, vow_flag) VALUES ('Barbarik', 1)");
    }

    return $pdo;
}
