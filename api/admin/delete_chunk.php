<?php
/**
 * POST { id }
 * Deletes a chunk by its ID.
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';

require_admin_feature('questions');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$id = (int)($body['id'] ?? 0);

if ($id <= 0) {
    json_error('Missing chunk id', 400);
}

$pdo = db();

$del = $pdo->prepare('DELETE FROM subject_chunks WHERE id = ?');
$del->execute([$id]);

json_response(['ok' => true, 'deleted' => $del->rowCount() > 0]);
