<?php
// Copy this file to config.php and fill in your Hostinger MySQL credentials.
// IMPORTANT: Do not commit your real config.php to a public repo.

return [
    'db' => [
        'host' => 'localhost',
        'name' => 'YOUR_DB_NAME',
        'user' => 'YOUR_DB_USER',
        'pass' => 'YOUR_DB_PASSWORD',
        'charset' => 'utf8mb4',
    ],

    // Used only for one-time setup endpoint.
    // Set a long random string, then keep it secret.
    'setupToken' => 'CHANGE_ME_TO_A_LONG_RANDOM_STRING',

    // Initial admin accounts created by setup.php (only if no admins exist).
    'initialAdmins' => [
        ['username' => 'admin1', 'password' => 'CHANGE_ME_ADMIN_PASSWORD_1'],
        ['username' => 'admin2', 'password' => 'CHANGE_ME_ADMIN_PASSWORD_2'],
    ],

    // Only these admins can manage admin permissions and create new admins with restricted access.
    // Set to the two "master" admins (case-insensitive).
    'superAdmins' => ['Jabir', 'Ahmed'],

    // If true, the quiz API requires users to log in using a password (access code)
    // created by an admin. Recommended for Hostinger production.
    'requireUserAuth' => false,

    // Encryption key used to store user access codes (passwords) so admins can reveal/reset them later.
    // Recommended: generate 32 random bytes and base64-encode them.
    // Example (Linux/macOS): `openssl rand -base64 32`
    // Example (Windows PowerShell): `[Convert]::ToBase64String((1..32 | ForEach-Object {Get-Random -Max 256}))`
    'accessCodeKey' => 'CHANGE_ME_BASE64_32_BYTES',

    // Restrict which subject JSON files can be imported.
    'importFiles' => [
        'COMS' => __DIR__ . '/../data/COMS_extracted.json',
        'HPL'  => __DIR__ . '/../data/HPL_extracted.json',
        'OPS'  => __DIR__ . '/../data/OPS_extracted.json',
        'RNAV' => __DIR__ . '/../data/RNAV_extracted.json',
        'FPL'  => __DIR__ . '/../data/FPL_extracted.json',
    ],
];
