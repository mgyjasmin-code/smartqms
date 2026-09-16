<?php
/**
 * Copy this file to config/supabase.local.php for local development only.
 * Environment variables override every value in this file.
 *
 * Never commit the service-role key. The publishable key is browser-safe only
 * when the corresponding Supabase tables are protected by RLS.
 */
return [
    'provider' => 'local', // local, shadow, or supabase
    'url' => 'https://your-project.supabase.co',
    'publishable_key' => '',
    'service_role_key' => '',
    'jwt_audience' => 'authenticated',
    'request_timeout_seconds' => 5,
];
