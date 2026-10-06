<?php

return [
    'enabled' => (bool) env('DESTRUCTIVE_ADMIN_TOOLS_ENABLED', false),
    'require_snapshot' => (bool) env('DESTRUCTIVE_ADMIN_TOOLS_REQUIRE_SNAPSHOT', true),
    'token_minutes' => 5,
    'max_rows' => 50000,
    'max_archive_bytes' => 104857600,
    'max_expanded_bytes' => 268435456,
    'max_entries' => 10000,
    'disk' => 'local',
    'directory' => 'development-data',
];
