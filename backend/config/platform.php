<?php

return [
    // Preferred bootstrap: allowlist exact account IDs after the operator has registered.
    'admin_user_ids' => array_values(array_filter(array_map(
        static fn ($id) => (int) trim($id),
        explode(',', (string) env('PLATFORM_ADMIN_USER_IDS', ''))
    ))),
    // Email allowlisting is accepted only for accounts with verified email addresses.
    'admin_emails' => array_values(array_filter(array_map(
        static fn ($email) => strtolower(trim($email)),
        explode(',', (string) env('PLATFORM_ADMIN_EMAILS', ''))
    ))),
];
