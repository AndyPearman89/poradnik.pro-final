<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class PearTreeLocalModuleApi
{
    public static function registerRoutes(): void
    {
        register_rest_route('peartree-local/v1', '/status', [
            'methods' => 'GET',
            'callback' => [self::class, 'status'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('peartree-local/v1', '/echo', [
            'methods' => 'POST',
            'callback' => [self::class, 'echoMessage'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function status(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'ok' => true,
            'module' => 'peartree-local-module',
            'time' => gmdate('c'),
        ], 200);
    }

    public static function echoMessage(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        $message = sanitize_text_field((string) ($params['message'] ?? ''));

        return new WP_REST_Response([
            'ok' => true,
            'echo' => $message,
        ], 200);
    }
}
