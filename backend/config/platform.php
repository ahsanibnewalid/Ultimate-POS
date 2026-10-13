<?php

return [
    // Comma-separated, case-insensitive allowlist. Never infer platform-admin status
    // from a tenant's ordinary "admin" role.
    'admin_emails' => array_values(array_filter(array_map(
        static fn ($email) => strtolower(trim($email)),
        explode(',', (string) env('PLATFORM_ADMIN_EMAILS', ''))
    ))),
];
