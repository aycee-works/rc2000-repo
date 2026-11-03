<?php
/**
 * config/public-api.php
 * Settings for the public-website endpoints (api/public/).
 * Every value can be overridden in config/local.php using the same key names;
 * you only need to add keys there if you want to change a default.
 *
 *   'public_allowed_origins' => ['http://localhost:3000'],   // browsers from these origins may call the API
 *   'public_rate_limits'     => ['book' => [10, 3600]],      // [max requests, per seconds]
 */

function public_config(): array
{
    $c = $GLOBALS['APP_CONFIG'] ?? [];
    return [
        // Exact origins (scheme + host + port, no trailing slash). Empty = same-origin only.
        // "*" is intentionally NOT supported.
        'allowed_origins' => is_array($c['public_allowed_origins'] ?? null) ? $c['public_allowed_origins'] : [],
        // bucket => [max accepted submissions per IP, window in seconds]
        // Development (XAMPP testing): a generous limit so your own tests never get blocked.
        // Production: 10 submissions per IP per hour per form.
        'rate_limits'     => array_merge(array_fill_keys(['book', 'monetary', 'inquiry'],
            (($c['app_env'] ?? 'development') === 'development') ? [500, 3600] : [10, 3600]), is_array($c['public_rate_limits'] ?? null) ? $c['public_rate_limits'] : []),
    ];
}
