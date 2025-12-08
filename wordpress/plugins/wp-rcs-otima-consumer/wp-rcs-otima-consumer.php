<?php
/**
 * Plugin Name: RCS Ótima Consumer
 * Description: Consumidor da API RCS Ótima com suporte a templates, imagens e agendamento
 * Version: 2.0.0
 * Author: Daniel Cayres
 */

if (!defined('ABSPATH')) exit;

class WP_RCS_OTIMA_CONSUMER {

    private $table;
    private $api_base_url;
    private $authorization_token;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'envios_pendentes';

        $static = Api_Consumer_Manager_V3::get_static_credentials();
        $this->api_base_url = $static['rcs_base_url'] ?? 'https://services.otima.digital/';
        $this->authorization_token = $static['rcs_token'] ?? '';
        
        add_action('rest_api_init', [$this, 'routes']);
        add_action('rcs_otima_scheduled_event', [$this, 'run_scheduled_rcs_dispatch'], 10, 2);
    }

    public function routes() {
        register_rest_route('rcs_disparo/v1', '/(?P<agendamento_id>[^/]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'rcs_disparo'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
    }

    /**
     * Função executada em segundo plano pelo WP-Cron
     */
    public function run_scheduled_rcs_dispatch($agendamento_id, $dispatch_data) {
        try {
            $now = new DateTime('now', new DateTimeZone('UTC'));
        } catch (Exception $e) {
            error_log('Erro ao criar DateTime no cron RCS: ' . $e->getMessage());
            $now = new DateTime();
        }

        $endpoint = $dispatch_data['endpoint'];
        $payload = $dispatch_data['payload'];
        $full_url = $this->api_base_url . $endpoint;

        // Envia para API RCS Ótima
        $response = wp_remote_post($full_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'authorization' => $this->authorization_token
            ],
            'body' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timeout' => 90
        ]);

        if (is_wp_error($response)) {
            $error_message = 'Erro no envio RCS: ' . $response->get_error_message();
            $this->update_db('erro_envio_rcs', $error_message, $now, $agendamento_id);
            error_log($error_message);
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code === 200) {
                $this->update_db('enviado', 'Mensagens RCS enviadas com sucesso: ' . $response_body, $now, $agendamento_id);
            } else {
                $this->update_db('erro_envio_rcs', "Erro HTTP {$response_code}: {$response_body}", $now, $agendamento_id);
            }
        }
    }

    public function rcs_disparo(\WP_REST_Request $request) {
        global $wpdb;

        try {
            $now = new DateTime('now', new DateTimeZone('UTC'));
        } catch (Exception $e) {
            error_log('Erro ao criar DateTime RCS: ' . $e->getMessage());
            return new WP_Error('datetime_error', 'Erro ao processar data/hora', ['status' => 500]);
        }

        $agendamento_id = $request->get_param('agendamento_id');
        if (empty($agendamento_id)) {
            return new WP_Error('invalid_agendamento', 'ID de agendamento ausente', ['status' => 400]);
        }

        // Busca dados locais COM API KEY
        $local_api = home_url('/wp-json/agendamentos/v1/pendentes/' . urlencode($agendamento_id));
        $response = wp_remote_get($local_api, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-KEY' => get_option('acm_master_api_key')
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            $this->update_db('erro_envio', 'Erro na API de consulta', $now, $agendamento_id);
            return new WP_Error('Erro_Local_API', 'Erro na API de consulta: ' . $response->get_error_message(), ['status' => 500]);
        }

        $dados = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($dados) || !is_array($dados)) {
            $this->update_db('erro_envio', 'Nenhum JSON foi coletado', $now, $agendamento_id);
            return new WP_Error('JSON_Error', 'Nenhum JSON foi coletado', ['status' => 204]);
        }

        if (!isset($dados[0]['idgis_ambiente'])) {
            $this->update_db('erro_envio', 'Campo idgis_ambiente não encontrado', $now, $agendamento_id);
            return new WP_Error('missing_field', 'Campo idgis_ambiente não encontrado', ['status' => 400]);
        }

        $id_regua = $dados[0]['idgis_ambiente'];
        $credentials = $this->get_rcs_credentials($id_regua);

        if (is_wp_error($credentials)) {
            return $credentials;
        }

        // Busca o template RCS associado (se houver)
        $template_config = $this->get_template_config($agendamento_id);

        // Determina o tipo de envio e monta o payload
        if ($template_config && isset($template_config['template_code'])) {
            // Envio com TEMPLATE
            $dispatch_data = $this->prepare_template_dispatch($dados, $credentials, $template_config, $now);
        } elseif ($template_config && isset($template_config['has_media'])) {
            // Envio com DOCUMENTO/IMAGEM
            $dispatch_data = $this->prepare_document_dispatch($dados, $credentials, $template_config, $now);
        } else {
            // Envio de TEXTO SIMPLES
            $dispatch_data = $this->prepare_text_dispatch($dados, $credentials, $now);
        }

        if (is_wp_error($dispatch_data)) {
            return $dispatch_data;
        }

        // Agenda o envio para 15 segundos
        wp_schedule_single_event(time() + 15, 'rcs_otima_scheduled_event', [
            $agendamento_id,
            $dispatch_data
        ]);

        $this->update_db('agendado', 'Envio RCS agendado para 15 segundos', $now, $agendamento_id);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Envio RCS agendado para 15 segundos',
            'total_messages' => count($dispatch_data['payload']['messages']),
            'type' => $dispatch_data['type']
        ], 202);
    }

    /**
     * Busca credenciais do RCS no API Manager
     */
    private function get_rcs_credentials($idgis_ambiente) {
        $credentials = Api_Consumer_Manager_V3::get_provider_credentials('rcs', $idgis_ambiente);
        
        if (!$credentials) {
            return new WP_Error('INVALID_IDGIS', 'IDGIS Ambiente não encontrado no API Manager', ['status' => 404]);
        }
        
        return $credentials; // Retorna ['broker_code' => 'xxx', 'customer_code' => 'yyy']
    }

    /**
     * Prepara envio de texto simples
     */
    private function prepare_text_dispatch($dados, $credentials, $now) {
        $messages = [];
        
        foreach ($dados as $dado) {
            if (isset($dado['telefone']) && isset($dado['mensagem'])) {
                $messages[] = [
                    'phone' => $dado['telefone'],
                    'document' => $dado['idcob_contrato'] ?? '',
                    'message' => $dado['mensagem'],
                    'date' => $now->format('Y-m-d H:i:s')
                ];
            }
        }

        if (empty($messages)) {
            return new WP_Error('no_messages', 'Nenhuma mensagem válida para enviar', ['status' => 400]);
        }

        // Limita a 1000 mensagens por requisição
        $messages = array_slice($messages, 0, 1000);

        return [
            'type' => 'text',
            'endpoint' => '/v1/rcs/bulk/message/text',
            'payload' => [
                'broker_code' => $credentials['broker_code'],
                'customer_code' => $credentials['customer_code'],
                'messages' => $messages
            ]
        ];
    }

    /**
     * Prepara envio com template
     */
    private function prepare_template_dispatch($dados, $credentials, $template_config, $now) {
        $messages = [];
        
        foreach ($dados as $dado) {
            if (isset($dado['telefone'])) {
                $messages[] = [
                    'phone' => $dado['telefone'],
                    'document' => $dado['idcob_contrato'] ?? '',
                    'template_code' => $template_config['template_code'],
                    'variables' => $this->extract_template_variables($dado),
                    'date' => $now->format('Y-m-d H:i:s')
                ];
            }
        }

        if (empty($messages)) {
            return new WP_Error('no_messages', 'Nenhuma mensagem válida para enviar', ['status' => 400]);
        }

        return [
            'type' => 'template',
            'endpoint' => '/v1/rcs/bulk/message/template',
            'payload' => [
                'broker_code' => $credentials['broker_code'],
                'customer_code' => $credentials['customer_code'],
                'messages' => $messages
            ]
        ];
    }

    /**
     * Prepara envio com documento/imagem
     */
    private function prepare_document_dispatch($dados, $credentials, $template_config, $now) {
        $messages = [];
        
        foreach ($dados as $dado) {
            if (isset($dado['telefone'])) {
                $messages[] = [
                    'phone' => $dado['telefone'],
                    'document' => $dado['idcob_contrato'] ?? '',
                    'message' => $dado['mensagem'] ?? '',
                    'file_url' => $template_config['file_url'],
                    'file_type' => $template_config['file_type'],
                    'file_name' => $template_config['file_name'],
                    'date' => $now->format('Y-m-d H:i:s')
                ];
            }
        }

        if (empty($messages)) {
            return new WP_Error('no_messages', 'Nenhuma mensagem válida para enviar', ['status' => 400]);
        }

        return [
            'type' => 'document',
            'endpoint' => '/v1/rcs/bulk/message/document',
            'payload' => [
                'broker_code' => $credentials['broker_code'],
                'customer_code' => $credentials['customer_code'],
                'messages' => $messages
            ]
        ];
    }

    /**
     * Extrai variáveis do template dos dados
     */
    private function extract_template_variables($dado) {
        return [
            'nome' => $dado['nome'] ?? '',
            'telefone' => $dado['telefone'] ?? '',
            'contrato' => $dado['idcob_contrato'] ?? '',
            'cpf_cnpj' => $dado['CPF_CNPJ'] ?? '',
            'mensagem' => $dado['mensagem'] ?? ''
        ];
    }

    /**
     * Busca configuração do template RCS
     */
    private function get_template_config($agendamento_id) {
        // Implementar busca por templates associados ao agendamento
        // Por enquanto, retorna null (envio de texto simples)
        return null;
    }

    public function update_db($field_status, $field_api, $field_date, $where_filter) {
        global $wpdb;

        if ($field_date instanceof DateTime) {
            $field_date = $field_date->format('Y-m-d H:i:s');
        }

        $table_name = $this->table;

        $result = $wpdb->update(
            $table_name,
            [
                'status' => $field_status,
                'resposta_api' => $field_api,
                'data_disparo' => $field_date
            ],
            [
                'agendamento_id' => $where_filter
            ],
            [
                '%s', // formato para status
                '%s', // formato para resposta_api
                '%s'  // formato para data_disparo
            ],
            [
                '%s' // formato para agendamento_id
            ]
        );

        if ($result === false) {
            error_log("RCS: Erro ao atualizar banco de dados: " . $wpdb->last_error);
        }

        return $result;
    }

    public function check_api_key(\WP_REST_Request $request) {
        return Api_Consumer_Manager_V3::check_api_key($request);
    }
}

/**
 * Classe para gerenciar meta boxes de templates RCS
 */
class RCS_Template_Meta_Boxes {

    public function __construct() {
        add_action('init', [$this, 'register_message_template_cpt']);
        add_action('add_meta_boxes', [$this, 'add_rcs_meta_boxes']);
        add_action('save_post', [$this, 'save_rcs_meta_boxes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function register_message_template_cpt() {
        $labels = [
            'name' => 'Templates de Mensagem',
            'singular_name' => 'Template de Mensagem',
            'menu_name' => 'Templates RCS',
            'add_new' => 'Novo Template',
            'add_new_item' => 'Adicionar Novo Template',
            'edit_item' => 'Editar Template',
            'new_item' => 'Novo Template',
            'view_item' => 'Ver Template',
            'search_items' => 'Buscar Templates',
            'not_found' => 'Nenhum template encontrado',
            'not_found_in_trash' => 'Nenhum template na lixeira'
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-format-chat',
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => ['title', 'editor'],
            'has_archive' => false,
            'show_in_rest' => false
        ];

        register_post_type('message_template', $args);
    }

    public function add_rcs_meta_boxes() {
        add_meta_box(
            'rcs_template_config',
            'Configuração RCS',
            [$this, 'render_rcs_meta_box'],
            'message_template',
            'normal',
            'high'
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        global $post;
        if (!$post || $post->post_type !== 'message_template') {
            return;
        }

        wp_enqueue_media();
    }

    public function render_rcs_meta_box($post) {
        wp_nonce_field('rcs_template_meta_box', 'rcs_template_meta_box_nonce');

        $template_code = get_post_meta($post->ID, '_rcs_template_code', true);
        $has_media = get_post_meta($post->ID, '_rcs_has_media', true);
        $file_url = get_post_meta($post->ID, '_rcs_file_url', true);
        $file_type = get_post_meta($post->ID, '_rcs_file_type', true);
        $file_name = get_post_meta($post->ID, '_rcs_file_name', true);
        $fallback_sms = get_post_meta($post->ID, '_rcs_fallback_sms', true);
        ?>
        <div class="rcs-template-config">
            <p class="description">Configure o comportamento RCS para este template</p>

            <table class="form-table">
                <tr>
                    <th><label for="rcs_template_code">Código do Template RCS (Opcional)</label></th>
                    <td>
                        <input type="text" id="rcs_template_code" name="rcs_template_code" 
                               value="<?php echo esc_attr($template_code); ?>" 
                               class="regular-text" placeholder="Ex: TEMPLATE_COBRANCA_001">
                        <p class="description">Se este template usa um template pré-aprovado na Ótima, informe o código aqui</p>
                    </td>
                </tr>

                <tr>
                    <th><label for="rcs_has_media">Enviar com Mídia?</label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="rcs_has_media" name="rcs_has_media" 
                                   value="1" <?php checked($has_media, '1'); ?>>
                            Sim, este template inclui imagem/documento
                        </label>
                    </td>
                </tr>

                <tr id="rcs_media_config" style="<?php echo $has_media === '1' ? '' : 'display:none;'; ?>">
                    <th><label>Configurar Arquivo</label></th>
                    <td>
                        <div style="margin-bottom:15px;">
                            <input type="text" id="rcs_file_url" name="rcs_file_url" 
                                   value="<?php echo esc_attr($file_url); ?>" 
                                   class="regular-text" placeholder="URL da imagem/documento">
                            <button type="button" class="button" id="rcs_upload_file_btn">
                                📎 Escolher Arquivo
                            </button>
                        </div>

                        <div style="margin-bottom:10px;">
                            <label>Tipo do Arquivo:</label><br>
                            <select id="rcs_file_type" name="rcs_file_type">
                                <option value="image/jpeg" <?php selected($file_type, 'image/jpeg'); ?>>JPEG</option>
                                <option value="image/png" <?php selected($file_type, 'image/png'); ?>>PNG</option>
                                <option value="image/gif" <?php selected($file_type, 'image/gif'); ?>>GIF</option>
                                <option value="application/pdf" <?php selected($file_type, 'application/pdf'); ?>>PDF</option>
                                <option value="video/mp4" <?php selected($file_type, 'video/mp4'); ?>>MP4</option>
                            </select>
                        </div>

                        <div>
                            <label>Nome do Arquivo:</label><br>
                            <input type="text" id="rcs_file_name" name="rcs_file_name" 
                                   value="<?php echo esc_attr($file_name); ?>" 
                                   class="regular-text" placeholder="Ex: boleto.pdf">
                        </div>

                        <?php if ($file_url && strpos($file_type, 'image') === 0): ?>
                        <div style="margin-top:15px;">
                            <img src="<?php echo esc_url($file_url); ?>" 
                                 style="max-width:200px;border:1px solid #ddd;padding:5px;">
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th><label for="rcs_fallback_sms">Mensagem Fallback (SMS)</label></th>
                    <td>
                        <textarea id="rcs_fallback_sms" name="rcs_fallback_sms" 
                                  rows="3" class="large-text"><?php echo esc_textarea($fallback_sms); ?></textarea>
                        <p class="description">Mensagem a ser enviada via SMS se o RCS falhar</p>
                    </td>
                </tr>
            </table>
        </div>

        <style>
            .rcs-template-config{background:#f9f9f9;padding:15px;border-radius:5px;margin-top:10px}
            .rcs-template-config .form-table th{width:200px;padding:15px 10px}
            .rcs-template-config .form-table td{padding:15px 10px}
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Toggle media config
            $('#rcs_has_media').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#rcs_media_config').slideDown();
                } else {
                    $('#rcs_media_config').slideUp();
                }
            });

            // WordPress Media Uploader
            $('#rcs_upload_file_btn').on('click', function(e) {
                e.preventDefault();
                
                var mediaUploader = wp.media({
                    title: 'Escolher Arquivo para RCS',
                    button: { text: 'Usar este arquivo' },
                    multiple: false
                });

                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#rcs_file_url').val(attachment.url);
                    $('#rcs_file_name').val(attachment.filename);
                    
                    // Auto-detect mime type
                    if (attachment.mime) {
                        $('#rcs_file_type').val(attachment.mime);
                    }
                });

                mediaUploader.open();
            });
        });
        </script>
        <?php
    }

    public function save_rcs_meta_boxes($post_id) {
        if (!isset($_POST['rcs_template_meta_box_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['rcs_template_meta_box_nonce'], 'rcs_template_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Salva template code
        if (isset($_POST['rcs_template_code'])) {
            update_post_meta($post_id, '_rcs_template_code', sanitize_text_field($_POST['rcs_template_code']));
        }

        // Salva has media
        $has_media = isset($_POST['rcs_has_media']) ? '1' : '0';
        update_post_meta($post_id, '_rcs_has_media', $has_media);

        // Salva file config
        if (isset($_POST['rcs_file_url'])) {
            update_post_meta($post_id, '_rcs_file_url', esc_url_raw($_POST['rcs_file_url']));
        }
        if (isset($_POST['rcs_file_type'])) {
            update_post_meta($post_id, '_rcs_file_type', sanitize_text_field($_POST['rcs_file_type']));
        }
        if (isset($_POST['rcs_file_name'])) {
            update_post_meta($post_id, '_rcs_file_name', sanitize_text_field($_POST['rcs_file_name']));
        }

        // Salva fallback SMS
        if (isset($_POST['rcs_fallback_sms'])) {
            update_post_meta($post_id, '_rcs_fallback_sms', sanitize_textarea_field($_POST['rcs_fallback_sms']));
        }
    }
}

// Inicializa as classes
new WP_RCS_OTIMA_CONSUMER();
new RCS_Template_Meta_Boxes();