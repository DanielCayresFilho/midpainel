<?php
/**
 * Gerenciamento de requisições AJAX
 * VERSÃO CORRIGIDA - Busca iscas compatíveis corretamente
 */

if (!defined('ABSPATH')) exit;

class Campaign_Manager_Ajax {
    
    public function __construct() {
        add_action('wp_ajax_cm_get_filters', [$this, 'get_filters']);
        add_action('wp_ajax_cm_get_count', [$this, 'get_count']);
        add_action('wp_ajax_cm_schedule_campaign', [$this, 'schedule_campaign']);
        add_action('wp_ajax_cm_get_compatible_baits', [$this, 'get_compatible_baits']);
        add_action('wp_ajax_cm_get_template_content', [$this, 'get_template_content']);
    }
    
    /**
     * Retorna filtros disponíveis para uma tabela
     */
    public function get_filters() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        
        $table_name = isset($_POST['table_name']) ? sanitize_text_field($_POST['table_name']) : '';
        
        if (empty($table_name)) {
            wp_send_json_error('Nome da tabela não fornecido');
        }
        
        $filters = Campaign_Manager_Filters::get_filterable_columns($table_name);
        
        if (is_wp_error($filters)) {
            wp_send_json_error($filters->get_error_message());
        }
        
        wp_send_json_success($filters);
    }
    
    /**
     * Conta registros com filtros aplicados
     */
    public function get_count() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        
        $table_name = isset($_POST['table_name']) ? sanitize_text_field($_POST['table_name']) : '';
        $filters_json = isset($_POST['filters']) ? stripslashes($_POST['filters']) : '[]';
        $filters = json_decode($filters_json, true);
        
        if (empty($table_name)) {
            wp_send_json_error('Nome da tabela não fornecido');
        }
        
        $count = Campaign_Manager_Filters::count_records($table_name, $filters);
        
        wp_send_json_success($count);
    }
    
    /**
     * 🎯 CORRIGIDO: Busca iscas compatíveis com os filtros
     */
    public function get_compatible_baits() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        
        $table_name = isset($_POST['table_name']) ? sanitize_text_field($_POST['table_name']) : '';
        $filters_json = isset($_POST['filters']) ? stripslashes($_POST['filters']) : '[]';
        $filters = json_decode($filters_json, true);
        
        error_log("=== BUSCA DE ISCAS COMPATÍVEIS ===");
        error_log("Tabela: {$table_name}");
        error_log("Filtros: " . print_r($filters, true));
        
        if (empty($table_name)) {
            error_log("❌ Tabela vazia");
            wp_send_json_success(['count' => 0, 'details' => []]);
        }
        
        global $wpdb;
        
        // 🎯 CORREÇÃO 1: Busca TODOS os IDGIS únicos da tabela (não apenas filtrados)
        // Isso garante que se há registros com IDGIS 364, as iscas com 364 apareçam
        $sql = "SELECT DISTINCT `IDGIS_AMBIENTE` 
                FROM `{$table_name}` 
                WHERE `IDGIS_AMBIENTE` IS NOT NULL 
                AND `IDGIS_AMBIENTE` != 0 
                AND `IDGIS_AMBIENTE` != ''";
        
        error_log("SQL para buscar IDGIS: {$sql}");
        
        $all_idgis = $wpdb->get_col($sql);
        
        if (empty($all_idgis)) {
            error_log("❌ Nenhum IDGIS encontrado na tabela");
            wp_send_json_success(['count' => 0, 'details' => []]);
        }
        
        error_log("✅ IDGIS encontrados na tabela: " . implode(', ', $all_idgis));
        
        // Cria array associativo para busca rápida
        $idgis_found = array();
        foreach ($all_idgis as $idgis) {
            $idgis_found[intval($idgis)] = true;
        }
        
        // Busca todas as iscas ativas
        $all_baits = Campaign_Manager_Baits::get_active_baits();
        
        if (empty($all_baits)) {
            error_log("❌ Nenhuma isca ativa cadastrada");
            wp_send_json_success(['count' => 0, 'details' => []]);
        }
        
        error_log("✅ Total de iscas ativas: " . count($all_baits));
        
        // Conta iscas compatíveis e guarda detalhes
        $compatible_count = 0;
        $compatible_details = [];
        
        foreach ($all_baits as $bait) {
            $bait_idgis = intval($bait['idgis_ambiente']);
            
            error_log("Verificando isca: {$bait['nome']} (IDGIS: {$bait_idgis})");
            
            if (isset($idgis_found[$bait_idgis])) {
                $compatible_count++;
                $compatible_details[] = [
                    'nome' => $bait['nome'],
                    'telefone' => $bait['telefone'],
                    'idgis' => $bait_idgis
                ];
                error_log("  ✅ COMPATÍVEL!");
            } else {
                error_log("  ❌ IDGIS {$bait_idgis} não encontrado na tabela");
            }
        }
        
        error_log("=== RESULTADO ===");
        error_log("Iscas compatíveis: {$compatible_count}");
        error_log("Detalhes: " . print_r($compatible_details, true));
        
        wp_send_json_success([
            'count' => $compatible_count,
            'details' => $compatible_details
        ]);
    }
    
    /**
     * Agenda uma nova campanha
     */
    public function schedule_campaign() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        global $wpdb;
        
        // Recebe dados
        $table_name = isset($_POST['table_name']) ? sanitize_text_field($_POST['table_name']) : '';
        $filters_json = isset($_POST['filters']) ? stripslashes($_POST['filters']) : '[]';
        $filters = json_decode($filters_json, true);
        $providers_config_json = isset($_POST['providers_config']) ? stripslashes($_POST['providers_config']) : '{}';
        $providers_config = json_decode($providers_config_json, true);
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $record_limit = isset($_POST['record_limit']) ? intval($_POST['record_limit']) : 0;
        $exclude_recent_phones = isset($_POST['exclude_recent_phones']) ? intval($_POST['exclude_recent_phones']) : 1; // Default: ativado
        
        // Validações
        if (empty($table_name) || empty($providers_config) || empty($template_id)) {
            wp_send_json_error('Dados da campanha inválidos.');
        }
        
        // Busca template
        $message_post = get_post($template_id);
        if (!$message_post || $message_post->post_type !== 'message_template') {
            wp_send_json_error('Template de mensagem inválido.');
        }
        $message_content = $message_post->post_content;
        
        error_log("Campaign Manager AJAX - Buscando registros da tabela: {$table_name}");
        
        $records = Campaign_Manager_Filters::get_filtered_records($table_name, $filters, $record_limit);
        
        if (empty($records)) {
            wp_send_json_error('Nenhum registro encontrado com os filtros aplicados.');
        }
        
        error_log("Campaign Manager AJAX - Total de registros encontrados: " . count($records));
        
        // 🎣 ADICIONA ISCAS ATIVAS (apenas com IDGIS compatível)
        $all_baits = Campaign_Manager_Baits::get_active_baits();
        $idgis_found = array();
        
        // Descobre quais IDGIS existem nos registros filtrados
        foreach ($records as $record) {
            if (!empty($record['idgis_ambiente'])) {
                $idgis_found[$record['idgis_ambiente']] = true;
            }
        }
        
        $baits_count = 0;
        
        foreach ($all_baits as $bait) {
            // Só adiciona se o IDGIS da isca existe nos registros
            if (isset($idgis_found[$bait['idgis_ambiente']])) {
                $records[] = [
                    'telefone' => $bait['telefone'],
                    'nome' => $bait['nome'] . ' [ISCA]',
                    'idgis_ambiente' => $bait['idgis_ambiente'],
                    'idcob_contrato' => 0,
                    'cpf_cnpj' => ''
                ];
                $baits_count++;
            }
        }
        
        if ($baits_count > 0) {
            error_log("Campaign Manager AJAX - Adicionando {$baits_count} iscas compatíveis");
            error_log("Campaign Manager AJAX - Total com iscas: " . count($records));
        }
        
        // Distribui entre provedores
        $distributed_records = $this->distribute_records($records, $providers_config);
        
        $agendamento_base_id = current_time('YmdHis');
        $total_inserted = 0;
        $total_skipped = 0;
        $distribution_summary = [];
        $target_table = $wpdb->prefix . 'envios_pendentes';
        $current_user_id = get_current_user_id();
        
        foreach ($distributed_records as $provider_data) {
            $provider = $provider_data['provider'];
            $provider_records = $provider_data['records'];
            
            $prefix = strtoupper(substr($provider, 0, 1));
            $agendamento_id = $prefix . $agendamento_base_id;
            
            $inserted_count = 0;
            $skipped_count = 0;
            
            error_log("Campaign Manager AJAX - Processando provedor: {$provider} com " . count($provider_records) . " registros");
            
            foreach ($provider_records as $record) {
                // Extrai e normaliza telefone
                $telefone_normalizado = $this->extract_phone($record);
                
                // 🚫 VERIFICA: Não pode enviar mensagem dois dias seguidos para o mesmo telefone (se a opção estiver ativa)
                if ($exclude_recent_phones && $this->has_received_message_recently($telefone_normalizado)) {
                    error_log("Campaign Manager AJAX - ⏭️ Telefone {$telefone_normalizado} já recebeu mensagem recentemente. Pulando...");
                    $skipped_count++;
                    $total_skipped++;
                    continue;
                }
                
                // Aplica mapeamento IDGIS
                $idgis_original = intval($record['idgis_ambiente'] ?? 0);
                $idgis_mapeado = $idgis_original;
                
                if ($idgis_original > 0 && class_exists('CM_IDGIS_Mapper')) {
                    $idgis_mapeado = CM_IDGIS_Mapper::get_mapped_idgis(
                        $table_name,
                        $provider,
                        $idgis_original
                    );
                    
                    if ($idgis_mapeado != $idgis_original) {
                        error_log("Campaign Manager AJAX - ✅ MAPEAMENTO APLICADO: {$idgis_original} → {$idgis_mapeado} (Provedor: {$provider})");
                    }
                }
                
                // Substitui placeholders na mensagem
                $mensagem_final = $this->replace_placeholders($message_content, $record);
                
                // Monta dados para inserção
                $insert_data = [
                    'telefone' => $telefone_normalizado,
                    'nome' => $record['nome'] ?? '',
                    'idgis_ambiente' => $idgis_mapeado,
                    'idcob_contrato' => intval($record['idcob_contrato'] ?? 0),
                    'cpf_cnpj' => $record['cpf_cnpj'] ?? '',
                    'mensagem' => $mensagem_final,
                    'fornecedor' => $provider,
                    'agendamento_id' => $agendamento_id,
                    'status' => 'pendente_aprovacao',
                    'current_user_id' => $current_user_id,
                    'valido' => 1,
                    'data_cadastro' => current_time('mysql')
                ];
                
                $insert_result = $wpdb->insert(
                    $target_table,
                    $insert_data,
                    ['%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s']
                );
                
                if ($insert_result !== false) {
                    $inserted_count++;
                } else {
                    error_log('Campaign Manager AJAX - Erro ao inserir: ' . $wpdb->last_error);
                }
            }
            
            $total_inserted += $inserted_count;
            $distribution_summary[] = "{$provider}: {$inserted_count}";
            
            if ($skipped_count > 0) {
                error_log("Campaign Manager AJAX - Total inserido para {$provider}: {$inserted_count} | Pulados: {$skipped_count}");
            } else {
                error_log("Campaign Manager AJAX - Total inserido para {$provider}: {$inserted_count}");
            }
        }
        
        if ($total_inserted === 0) {
            wp_send_json_error('Erro ao inserir registros no banco de dados.');
        }
        
        $distribution_text = implode(' | ', $distribution_summary);
        $limit_message = $record_limit > 0 ? " (limitado a {$record_limit} registros)" : "";
        $baits_message = $baits_count > 0 ? " | 🎣 {$baits_count} iscas" : "";
        $skipped_message = '';
        if ($exclude_recent_phones && $total_skipped > 0) {
            $skipped_message = " | ⏭️ {$total_skipped} telefones excluídos (já receberam mensagem recentemente)";
        }
        
        wp_send_json_success([
            'message' => "Campanha agendada! {$total_inserted} clientes distribuídos: {$distribution_text}{$limit_message}{$baits_message}{$skipped_message}",
            'agendamento_id' => $agendamento_base_id,
            'records_inserted' => $total_inserted,
            'records_skipped' => $total_skipped,
            'exclusion_enabled' => $exclude_recent_phones
        ]);
    }
    
    /**
     * Distribui registros entre provedores
     */
    private function distribute_records($records, $providers_config) {
        $total_records = count($records);
        $distribution_mode = $providers_config['mode'] ?? 'split';
        $providers = $providers_config['providers'] ?? [];
        
        // Modo: enviar para todos
        if ($distribution_mode === 'all') {
            $result = [];
            foreach ($providers as $provider) {
                $result[] = [
                    'provider' => $provider,
                    'records' => $records
                ];
            }
            return $result;
        }
        
        // Modo: dividir por porcentagem
        $percentages = $providers_config['percentages'] ?? [];
        $result = [];
        
        // Normaliza porcentagens
        $total_percent = array_sum($percentages);
        if ($total_percent != 100 && $total_percent > 0) {
            foreach ($percentages as $provider => $percent) {
                $percentages[$provider] = ($percent / $total_percent) * 100;
            }
        }
        
        shuffle($records);
        
        $start_index = 0;
        foreach ($providers as $i => $provider) {
            $percent = $percentages[$provider] ?? (100 / count($providers));
            $count = round(($percent / 100) * $total_records);
            
            // Último provedor recebe o resto
            if ($i === count($providers) - 1) {
                $count = $total_records - $start_index;
            }
            
            $provider_records = array_slice($records, $start_index, $count);
            
            if (!empty($provider_records)) {
                $result[] = [
                    'provider' => $provider,
                    'records' => $provider_records
                ];
            }
            
            $start_index += $count;
        }
        
        return $result;
    }
    
    /**
     * Extrai telefone do registro
     */
    private function extract_phone($record) {
        $phone = $record['telefone'] ?? '';
        
        // Remove caracteres não numéricos
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove código do país se tiver (55)
        if (strlen($phone) > 11 && substr($phone, 0, 2) === '55') {
            $phone = substr($phone, 2);
        }
        
        return $phone;
    }
    
    /**
     * Retorna o conteúdo completo de um template de mensagem
     */
    public function get_template_content() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        
        if (empty($template_id)) {
            wp_send_json_error('ID do template não fornecido');
        }
        
        $message_post = get_post($template_id);
        
        if (!$message_post || $message_post->post_type !== 'message_template') {
            wp_send_json_error('Template não encontrado');
        }
        
        wp_send_json_success([
            'content' => $message_post->post_content,
            'title' => $message_post->post_title
        ]);
    }
    
    /**
     * 🚫 Verifica se o telefone já recebeu mensagem no dia anterior ou hoje
     * Retorna true se já recebeu, false caso contrário
     */
    private function has_received_message_recently($telefone) {
        global $wpdb;
        
        if (empty($telefone)) {
            return false;
        }
        
        $envios_table = $wpdb->prefix . 'envios_pendentes';
        
        // Data de ontem (início do dia) - verifica se enviou ontem ou hoje
        $ontem = date('Y-m-d 00:00:00', strtotime('-1 day'));
        
        // Verifica se existe algum registro com este telefone que tenha:
        // - data_cadastro ou data_disparo entre ontem e agora
        // Isso evita enviar mensagem dois dias seguidos para o mesmo telefone
        $query = $wpdb->prepare(
            "SELECT COUNT(*) 
            FROM {$envios_table} 
            WHERE telefone = %s 
            AND (
                (data_cadastro >= %s AND data_cadastro < NOW())
                OR (data_disparo >= %s AND data_disparo IS NOT NULL)
            )
            LIMIT 1",
            $telefone,
            $ontem,
            $ontem
        );
        
        $count = $wpdb->get_var($query);
        
        return $count > 0;
    }
    
    /**
     * Substitui placeholders na mensagem
     */
    private function replace_placeholders($message, $record) {
        if (empty($message)) {
            return '';
        }
        
        $placeholders = [
            '{nome}' => $record['nome'] ?? '',
            '{cpf}' => $record['cpf_cnpj'] ?? '',
            '{cnpj}' => $record['cpf_cnpj'] ?? '',
            '{telefone}' => $this->extract_phone($record),
            '{idgis}' => $record['idgis_ambiente'] ?? '',
            '{contrato}' => $record['idcob_contrato'] ?? '',
            '{data}' => date('d/m/Y'),
        ];
        
        foreach ($placeholders as $placeholder => $value) {
            $message = str_replace($placeholder, $value, $message);
        }
        
        return $message;
    }
}

// Inicializa
new Campaign_Manager_Ajax();