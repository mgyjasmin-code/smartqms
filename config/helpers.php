<?php
/**
 * SmartQMS compatibility helper aggregator.
 *
 * Existing entry points continue loading this file through config.php while
 * implementations live in focused procedural modules.
 */

require_once __DIR__ . '/../modules/shared/http.php';
require_once __DIR__ . '/../modules/shared/schema_compat.php';
require_once __DIR__ . '/../modules/shared/security.php';
require_once __DIR__ . '/../modules/shared/security_events.php';
require_once __DIR__ . '/../modules/shared/validation.php';
require_once __DIR__ . '/../modules/shared/activity.php';
require_once __DIR__ . '/../modules/shared/settings.php';
require_once __DIR__ . '/../modules/shared/assets.php';
require_once __DIR__ . '/../modules/shared/database.php';
require_once __DIR__ . '/../modules/integrations/provider.php';
require_once __DIR__ . '/../modules/integrations/supabase_client.php';
require_once __DIR__ . '/../modules/integrations/supabase_auth.php';
require_once __DIR__ . '/../modules/integrations/catalog_gateway.php';
require_once __DIR__ . '/../modules/integrations/queue_gateway.php';
require_once __DIR__ . '/../modules/integrations/admin_gateway.php';
require_once __DIR__ . '/../modules/queue/queue_queries.php';
require_once __DIR__ . '/../modules/queue/customer_booking.php';
require_once __DIR__ . '/../modules/queue/service_availability.php';
require_once __DIR__ . '/../modules/queue/prediction.php';
require_once __DIR__ . '/../modules/queue/arrival_service.php';
require_once __DIR__ . '/../modules/service_window/window_queries.php';
