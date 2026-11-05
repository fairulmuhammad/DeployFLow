<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| DeployFlow configuration
|--------------------------------------------------------------------------
|
| Application-specific settings. Every key maps to a variable documented in
| docs/CONFIGURATION.md; `env()` is only read from config files, never from
| application code. Secret values live here but are never serialized.
|
*/

$allowedHosts = env('REGISTRY_ALLOWED_HOSTS', 'registry.local:5000');
$platformAdmins = env('PLATFORM_ADMIN_EMAILS', '');
$platformAdmins = is_string($platformAdmins) ? $platformAdmins : '';

return [

    'platform_admins' => array_values(array_filter(array_map(
        static fn (string $email): string => trim($email),
        explode(',', $platformAdmins),
    ))),

    'registry' => [
        'driver' => (string) env('REGISTRY_DRIVER', 'local'),
        'host' => (string) env('REGISTRY_HOST', 'registry.local:5000'),
        'repository_prefix' => (string) env('REGISTRY_REPOSITORY_PREFIX', 'deployflow'),
        'username' => env('REGISTRY_USERNAME'),
        'password' => env('REGISTRY_PASSWORD'),
        'push_retries' => (int) env('REGISTRY_PUSH_RETRIES', 3),
        'allowed_hosts' => array_values(array_filter(array_map(
            static fn (string $host): string => trim($host),
            explode(',', is_string($allowedHosts) ? $allowedHosts : 'registry.local:5000'),
        ))),
    ],

    'deploy' => [
        'base_domain' => (string) env('DEPLOY_BASE_DOMAIN', 'apps.deployflow.test'),
        'network_prefix' => (string) env('DEPLOY_NETWORK_PREFIX', 'df-env-'),
        'traefik_dynamic_path' => (string) env('TRAEFIK_DYNAMIC_PATH', '/traefik/dynamic'),
        'traefik_router_entrypoint' => (string) env('TRAEFIK_ROUTER_ENTRYPOINT', 'websecure'),
        'traefik_cert_resolver' => (string) env('TRAEFIK_CERT_RESOLVER', 'letsencrypt'),
        'slot_retention_seconds' => (int) env('DEPLOY_SLOT_RETENTION_SECONDS', 900),
    ],

    'workers' => [
        'bootstrap_token' => env('WORKER_BOOTSTRAP_TOKEN'),
        'secret_ttl_days' => (int) env('WORKER_SECRET_TTL_DAYS', 180),
        'request_max_skew_seconds' => (int) env('WORKER_REQUEST_MAX_SKEW_SECONDS', 300),
        'nonce_ttl_seconds' => (int) env('WORKER_NONCE_TTL_SECONDS', 600),
        'lease_seconds' => (int) env('WORKER_LEASE_SECONDS', 90),
        'heartbeat_seconds' => (int) env('WORKER_HEARTBEAT_SECONDS', 30),
        'offline_after_seconds' => (int) env('WORKER_OFFLINE_AFTER_SECONDS', 60),
    ],

    'limits' => [
        'build_timeout_seconds' => (int) env('BUILD_TIMEOUT_SECONDS', 1200),
        'build_max_timeout_seconds' => (int) env('BUILD_MAX_TIMEOUT_SECONDS', 3600),
        'build_max_context_mb' => (int) env('BUILD_MAX_CONTEXT_MB', 2048),
        'deploy_timeout_seconds' => (int) env('DEPLOY_TIMEOUT_SECONDS', 120),
        'deployment_hard_timeout_seconds' => (int) env('DEPLOYMENT_HARD_TIMEOUT_SECONDS', 3600),
    ],

    'health' => [
        'interval_seconds' => (int) env('HEALTH_INTERVAL_SECONDS', 5),
        'timeout_seconds' => (int) env('HEALTH_TIMEOUT_SECONDS', 2),
        'retries' => (int) env('HEALTH_RETRIES', 12),
        'startup_grace_seconds' => (int) env('HEALTH_STARTUP_GRACE_SECONDS', 10),
    ],

    'logs' => [
        'retention_days' => (int) env('LOG_RETENTION_DAYS', 30),
        'signed_url_ttl' => (int) env('LOG_SIGNED_URL_TTL', 300),
        'chunk_max_bytes' => (int) env('LOG_CHUNK_MAX_BYTES', 65536),
    ],

    'github' => [
        'app_id' => env('GITHUB_APP_ID'),
        'client_id' => env('GITHUB_APP_CLIENT_ID'),
        'client_secret' => env('GITHUB_APP_CLIENT_SECRET'),
        'slug' => env('GITHUB_APP_SLUG'),
        'private_key_path' => env('GITHUB_APP_PRIVATE_KEY_PATH', '/run/secrets/github_app.pem'),
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
        'api_url' => (string) env('GITHUB_API_URL', 'https://api.github.com'),
        'token_cache_ttl' => (int) env('GITHUB_TOKEN_CACHE_TTL', 3000),
        'http_timeout' => (int) env('GITHUB_HTTP_TIMEOUT', 15),
    ],

];
