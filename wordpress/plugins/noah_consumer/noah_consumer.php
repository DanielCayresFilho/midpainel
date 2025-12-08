<?php
/**
 * Plugin Name: Noah
 * Description: Noah API consumer
 * Version: 2.0
 * Author: Daniel Cayres
 */

if (!defined('ABSPATH')) exit;

class WP_NOAH_CONSUMER {

    private $table; 

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'envios_pendentes';
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes() {
        register_rest_route('noah_disparo/v1', '/(?P<agendamento_id>[^/]+)', [
            'methods'  => 'POST',
            'callback' => [$this, 'noah_disparar_para_fornecedor'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
    }

    public function noah_disparar_para_fornecedor(\WP_REST_Request $request) {
        global $wpdb;

        $agendamento_id = $request->get_param('agendamento_id');
        if (empty($agendamento_id)) {
            return new WP_Error('invalid_agendamento', 'ID de agendamento ausente.', ['status' => 400]);
        }

        // 1) Busca dados no seu endpoint local
        $local_api = home_url("/wp-json/agendamentos/v1/pendentes/" . urlencode($agendamento_id)) . '';

        $response = wp_remote_get($local_api, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
                'X-API-KEY' => get_option('acm_master_api_key')
            ],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('erro_local_api', 'Erro ao buscar dados: ' . $response->get_error_message(), ['status' => 500]);
        }

        $dados = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($dados) || !is_array($dados)) {
            return new WP_Error('sem_dados', 'Nenhum dado pendente encontrado.', ['status' => 204]);
        }

        $id_regua = $dados[0]['idgis_ambiente'];

        $new_data_way = ["name" => $agendamento_id, "data" => $dados];

        $infos_url = $this->wp_noah_urls($id_regua);

        if(is_wp_error($infos_url)) {
            return new WP_Error('ID_ERROR', 'Fornecer um id valido', ['status' => 400]);
        }
        
        $post_response = wp_remote_post("{$infos_url[0]}/contacts", [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => "INTEGRATION {$infos_url[1]}",
            ],
            'body'    => wp_json_encode($new_data_way, JSON_UNESCAPED_UNICODE),
            'timeout' => 30,
        ]);

        if (is_wp_error($post_response)) {
            return new WP_Error('erro_envio', 'Erro ao enviar para fornecedor: ' . $post_response->get_error_message(), ['status' => 502]);
        }

        $api_status   = wp_remote_retrieve_response_code($post_response);
        $api_body_raw = wp_remote_retrieve_body($post_response);
        $api_body     = $this->truncate_text($api_body_raw, 65000); // evita estourar TEXT
        $agora        = current_time('mysql');

        // 3) ATUALIZAÇÃO EM LOTE:
        // Marca todas as linhas pendentes desse agendamento como enviadas,
        // guarda a resposta da API e o datetime do disparo.
        // -> Ajuste os nomes das colunas abaixo conforme criou (ex.: resposta_api, data_disparo, status)
        $update_data = [
            'resposta_api' => $api_body,
            'data_disparo' => $agora,
            'status'       => ($api_status >= 200 && $api_status < 300) ? 'enviado' : 'erro_envio',
        ];
        $where = [
            'agendamento_id' => $agendamento_id,
            'status'         => 'pendente',
        ];

        // WordPress não tem update em massa com múltiplas linhas nativamente,
        // mas $wpdb->update com WHERE vai atingir todas as linhas que casarem a condição.
        $updated = $this->update_where($this->table, $update_data, $where);

        // Retorno
        $retorno = json_decode($api_body_raw, true);
        return [
            'agendamento'        => $agendamento_id,
            'enviados_payload'   => count($dados),         // quantos mandamos
            'linhas_atualizadas' => $updated,              // quantas linhas no banco viraram "enviado" (ou "erro_envio")
            'status_api'         => $api_status,
            'resposta_fornecedor'=> $retorno ?: $api_body_raw,
        ];
    }

    /**
     * Atualiza várias linhas com um WHERE arbitrário.
     * (Helper porque $wpdb->update aceita apenas igualdade; aqui montamos um prepare manual.)
     */
    private function update_where($table, array $data, array $where) {
        global $wpdb;

        // Monta SET
        $set_parts = [];
        $values    = [];
        foreach ($data as $col => $val) {
            $set_parts[] = "`$col` = %s";
            $values[]    = $val;
        }
        $set_sql = implode(', ', $set_parts);

        // Monta WHERE (igualdade simples col = %s)
        $where_parts = [];
        foreach ($where as $col => $val) {
            $where_parts[] = "`$col` = %s";
            $values[]      = $val;
        }
        $where_sql = implode(' AND ', $where_parts);

        $sql = "UPDATE `$table` SET $set_sql WHERE $where_sql";
        $prepared = $wpdb->prepare($sql, $values);
        $wpdb->query($prepared);

        if ($wpdb->last_error) {
            error_log('NOAH UPDATE ERROR: ' . $wpdb->last_error);
        }

        return $wpdb->rows_affected; // quantas linhas foram atualizadas
    }

    private function wp_noah_urls($id_regua) {
    $credentials = Api_Consumer_Manager_V3::get_provider_credentials('noah', $id_regua);
    
    if (!$credentials) {
        return new WP_Error('ERROR_ID', 'IDGIS Ambiente não encontrado no API Manager', ['status' => 400]);
    }
    
    return [$credentials['url'], $credentials['token']];
}

    public function check_api_key(\WP_REST_Request $request) {
    return Api_Consumer_Manager_V3::check_api_key($request);
}

    /**
     * Evita gravar respostas gigantes no banco (coluna TEXT).
     */
    private function truncate_text($text, $limit = 65000) {
        if (!is_string($text)) return '';
        return (strlen($text) > $limit) ? substr($text, 0, $limit) : $text;
    }
}

new WP_NOAH_CONSUMER();