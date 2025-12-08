<?php
/**
 * Plugin Name: Unique_Endpoint
 * Description: Endpoint único para consumo das APIS (incluindo RCS)
 * Version: 1.1.0
 * Author: Daniel Cayres
 */

if (!defined('ABSPATH')) exit;

class wp_unique_endpoint {

    public function __construct() {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes() {
        register_rest_route('unique/v1', '/(?P<agendamento_id>[^/]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'unique_endpoint'],
            'permission_callback' => [$this, 'check_api_key'] // ✅ CORRIGIDO
        ]);
    }

    public function check_api_key(\WP_REST_Request $request) {
        return Api_Consumer_Manager_V3::check_api_key($request);
    }

    public function unique_endpoint(\WP_REST_Request $param) {
        $agendamento_id = $param->get_param('agendamento_id');

        if (empty($agendamento_id)) {
            return new WP_Error('invalid_agendamento', 'Necessário passar um agendamento_id válido', ['status' => 400]);
        }

        $target_api_slug = null;
        
        // Determina o provedor baseado no prefixo do agendamento_id
        if (str_starts_with($agendamento_id, 'N')) {
            $target_api_slug = 'noah_disparo';
        } elseif (str_starts_with($agendamento_id, 'G')) {
            $target_api_slug = 'gosac_disparo';
        } elseif (str_starts_with($agendamento_id, 'C')) {
            $target_api_slug = 'cda_disparo';
        } elseif (str_starts_with($agendamento_id, 'S')) {
            $target_api_slug = 'salesforce';
        } elseif (str_starts_with($agendamento_id, 'R')) {
            $target_api_slug = 'rcs_disparo';
        }

        if (!$target_api_slug) {
            return new WP_Error('unknown_provider', 'Agendamento ID prefix does not match a known provider.', ['status' => 400]);
        }

        $request_url = home_url("/wp-json/{$target_api_slug}/v1/" . urlencode($agendamento_id));

        // Faz uma requisição POST interna para o plugin worker correto
        $post_endpoint = wp_remote_post($request_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-API-KEY' => get_option('acm_master_api_key')
            ],
            'timeout' => 90
        ]);

        if (is_wp_error($post_endpoint)) {
            return new WP_Error('ENDPOINT_ERROR', 'Error forwarding request: ' . $post_endpoint->get_error_message(), ['status' => 500]);
        }

        $response_code = wp_remote_retrieve_response_code($post_endpoint);
        $response_body = json_decode(wp_remote_retrieve_body($post_endpoint), true);
        
        return new WP_REST_Response($response_body, $response_code);
    }
}
new wp_unique_endpoint();