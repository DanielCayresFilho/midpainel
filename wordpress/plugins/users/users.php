<?php
/**
 * Plugin Name:       Relatório de Envios Pendentes Pro
 * Description:       Página de relatório avançada com status detalhados, download CSV geral e paginação
 * Version:           3.0
 * Author:            Daniel Cayres
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retorna nomes de tabelas utilizadas no relatório.
 *
 * @return array
 */
function rep_get_table_names() {
    global $wpdb;

    return [
        'envios'   => $wpdb->prefix . 'envios_pendentes',
        'users'    => $wpdb->prefix . 'users',
        'ambiente' => 'NOME_AMBIENTE',
    ];
}

/**
 * Sanitiza filtros vindos de $_GET/$_POST.
 *
 * @param array $source
 * @return array
 */
function rep_collect_filters(array $source) {
    $source = wp_unslash($source);

    $sanitize_date = static function ($value) {
        $value = sanitize_text_field($value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    };

    return [
        'filter_user'         => isset($source['filter_user']) ? sanitize_text_field($source['filter_user']) : '',
        'filter_fornecedor'   => isset($source['filter_fornecedor']) ? sanitize_text_field($source['filter_fornecedor']) : '',
        'filter_ambiente'     => isset($source['filter_ambiente']) ? sanitize_text_field($source['filter_ambiente']) : '',
        'filter_agendamento'  => isset($source['filter_agendamento']) ? sanitize_text_field($source['filter_agendamento']) : '',
        'filter_idgis'        => isset($source['filter_idgis']) ? absint($source['filter_idgis']) : 0,
        'filter_date_start'   => !empty($source['filter_date_start']) ? $sanitize_date($source['filter_date_start']) : '',
        'filter_date_end'     => !empty($source['filter_date_end']) ? $sanitize_date($source['filter_date_end']) : '',
    ];
}

/**
 * Constrói cláusulas WHERE compartilhadas entre consultas.
 *
 * @param array $filters
 * @return string
 */
function rep_build_where_sql(array $filters) {
    global $wpdb;

    $where = ['1=1'];

    if (!empty($filters['filter_user'])) {
        $where[] = $wpdb->prepare('E.display_name LIKE %s', '%' . $wpdb->esc_like($filters['filter_user']) . '%');
    }
    if (!empty($filters['filter_fornecedor'])) {
        $where[] = $wpdb->prepare('P.fornecedor LIKE %s', '%' . $wpdb->esc_like($filters['filter_fornecedor']) . '%');
    }
    if (!empty($filters['filter_ambiente'])) {
        $where[] = $wpdb->prepare('T.NOME_AMBIENTE LIKE %s', '%' . $wpdb->esc_like($filters['filter_ambiente']) . '%');
    }
    if (!empty($filters['filter_agendamento'])) {
        $where[] = $wpdb->prepare('P.agendamento_id LIKE %s', '%' . $wpdb->esc_like($filters['filter_agendamento']) . '%');
    }
    if (!empty($filters['filter_date_start'])) {
        $where[] = $wpdb->prepare('CAST(P.data_cadastro AS DATE) >= %s', $filters['filter_date_start']);
    }
    if (!empty($filters['filter_date_end'])) {
        $where[] = $wpdb->prepare('CAST(P.data_cadastro AS DATE) <= %s', $filters['filter_date_end']);
    }
    if (!empty($filters['filter_idgis'])) {
        $where[] = $wpdb->prepare('P.idgis_ambiente = %d', $filters['filter_idgis']);
    }

    return implode(' AND ', $where);
}

/**
 * Conta o total de agrupamentos paginados.
 *
 * @param string $where_sql
 * @return int
 */
function rep_count_grouped_records($where_sql) {
    global $wpdb;
    $tables = rep_get_table_names();

    $query = "
        SELECT COUNT(DISTINCT CONCAT(
            CAST(P.data_cadastro AS DATE), '-', P.current_user_id, '-', P.fornecedor, '-', P.agendamento_id, '-', P.idgis_ambiente
        )) AS total
        FROM {$tables['envios']} P
        LEFT JOIN {$tables['users']} E ON E.ID = P.current_user_id
        LEFT JOIN {$tables['ambiente']} T ON T.IDGIS_AMBIENTE = P.idgis_ambiente
        WHERE {$where_sql}
    ";

    return (int) $wpdb->get_var($query);
}

/**
 * Retorna totais gerais por status.
 *
 * @param string $where_sql
 * @return object|null
 */
function rep_fetch_status_totals($where_sql) {
    global $wpdb;
    $tables = rep_get_table_names();

    $query = "
        SELECT
            SUM(CASE WHEN P.status = 'Enviado' THEN 1 ELSE 0 END) AS total_enviado,
            SUM(CASE WHEN P.status = 'pendente_aprovacao' THEN 1 ELSE 0 END) AS total_pendente_aprovacao,
            SUM(CASE WHEN P.status = 'agendado_mkc' THEN 1 ELSE 0 END) AS total_agendado_mkc,
            SUM(CASE WHEN P.status = 'pendente' THEN 1 ELSE 0 END) AS total_pendente,
            SUM(CASE WHEN P.status = 'negado' THEN 1 ELSE 0 END) AS total_negado
        FROM {$tables['envios']} P
        LEFT JOIN {$tables['users']} E ON E.ID = P.current_user_id
        LEFT JOIN {$tables['ambiente']} T ON T.IDGIS_AMBIENTE = P.idgis_ambiente
        WHERE {$where_sql}
    ";

    return $wpdb->get_row($query);
}

/**
 * Consulta dados agrupados com paginação, totais e contagem.
 *
 * @param array $filters
 * @param int   $page
 * @param int   $per_page
 * @return array
 */
function rep_fetch_grouped_dataset(array $filters, $page = 1, $per_page = 25) {
    global $wpdb;
    $tables = rep_get_table_names();

    $page = max(1, (int) $page);
    $per_page = max(10, (int) $per_page);
    $offset = ($page - 1) * $per_page;

    $where_sql = rep_build_where_sql($filters);

    $query = "
        SELECT
            CAST(P.data_cadastro AS DATE) AS DATA,
            E.display_name AS USUARIO,
            P.fornecedor AS FORNECEDOR,
            P.agendamento_id AS AGENDAMENTO_ID,
            T.NOME_AMBIENTE,
            P.idgis_ambiente,
            SUM(CASE WHEN P.status = 'Enviado' THEN 1 ELSE 0 END) AS QTD_ENVIADO,
            SUM(CASE WHEN P.status = 'pendente_aprovacao' THEN 1 ELSE 0 END) AS QTD_PENDENTE_APROVACAO,
            SUM(CASE WHEN P.status = 'agendado_mkc' THEN 1 ELSE 0 END) AS QTD_AGENDADO_MKC,
            SUM(CASE WHEN P.status = 'pendente' THEN 1 ELSE 0 END) AS QTD_PENDENTE,
            SUM(CASE WHEN P.status = 'negado' THEN 1 ELSE 0 END) AS QTD_NEGADO
        FROM {$tables['envios']} P
        LEFT JOIN {$tables['users']} E ON E.ID = P.current_user_id
        LEFT JOIN {$tables['ambiente']} T ON T.IDGIS_AMBIENTE = P.idgis_ambiente
        WHERE {$where_sql}
        GROUP BY
            E.user_nicename,
            T.NOME_AMBIENTE,
            P.fornecedor,
            P.agendamento_id,
            P.idgis_ambiente,
            CAST(P.data_cadastro AS DATE)
        ORDER BY DATA DESC
        LIMIT {$per_page} OFFSET {$offset}
    ";

    $totals = rep_fetch_status_totals($where_sql);
    if (!$totals) {
        $totals = (object) [
            'total_enviado'             => 0,
            'total_pendente_aprovacao'  => 0,
            'total_agendado_mkc'        => 0,
            'total_pendente'            => 0,
            'total_negado'              => 0,
        ];
    }

    return [
        'rows'           => $wpdb->get_results($query),
        'totals'         => $totals,
        'total_records'  => rep_count_grouped_records($where_sql),
        'where_sql'      => $where_sql,
    ];
}

// Adiciona página no admin
function rep_adicionar_pagina_relatorio_admin() {
    add_menu_page(
        'Relatório de Envios',
        'Relatório Envios',
        'manage_options',
        'relatorio_envios_pendentes',
        'rep_renderizar_pagina_relatorio',
        'dashicons-chart-area',
        21
    );
}
add_action('admin_menu', 'rep_adicionar_pagina_relatorio_admin');

// AJAX: Contar envios 1X1 por carteira
function rep_ajax_get_1x1_stats() {
    check_ajax_referer('rep_nonce', 'nonce');
    
    global $wpdb;
    $table_eventos = $wpdb->prefix . 'eventos_envios';
    
    $results = $wpdb->get_results(
        "SELECT carteira, COUNT(*) as total 
         FROM {$table_eventos} 
         WHERE tipo = '1X1' 
         GROUP BY carteira 
         ORDER BY total DESC",
        ARRAY_A
    );
    
    $total_1x1 = 0;
    foreach ($results as $row) {
        $total_1x1 += $row['total'];
    }
    
    wp_send_json_success([
        'total' => $total_1x1,
        'carteiras' => $results
    ]);
}
add_action('wp_ajax_rep_get_1x1_stats', 'rep_ajax_get_1x1_stats');

// AJAX: Contar registros para paginação
function rep_ajax_count_records() {
    check_ajax_referer('rep_nonce', 'nonce');

    $filters = rep_collect_filters($_POST);
    $where_sql = rep_build_where_sql($filters);

    $total = rep_count_grouped_records($where_sql);
    
    wp_send_json_success(['total' => $total]);
}
add_action('wp_ajax_rep_count_records', 'rep_ajax_count_records');

// AJAX: Buscar dados paginados
function rep_ajax_get_data() {
    check_ajax_referer('rep_nonce', 'nonce');
    
    $filters = rep_collect_filters($_POST);
    $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
    $per_page = isset($_POST['per_page']) ? (int) $_POST['per_page'] : 25;

    $dataset = rep_fetch_grouped_dataset($filters, $page, $per_page);
    
    wp_send_json_success([
        'data' => $dataset['rows'],
        'totals' => $dataset['totals'],
        'total_records' => $dataset['total_records'],
    ]);
}
add_action('wp_ajax_rep_get_data', 'rep_ajax_get_data');

// Download CSV de campanha específica (mantido do original)
function rep_exportar_csv_agendamento() {
    if (!current_user_can('manage_options')) {
        wp_die('Sem permissão.');
    }

    if (!isset($_REQUEST['agendamento_id']) || empty($_REQUEST['agendamento_id'])) {
        wp_die('Agendamento ID não fornecido.');
    }

    if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'rep_csv_download')) {
        wp_die('Requisição inválida.');
    }

    $request = wp_unslash($_REQUEST);
    $agendamento_id = sanitize_text_field($request['agendamento_id']);
    
    global $wpdb;
    $table_envios = $wpdb->prefix . 'envios_pendentes';
    
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_envios} WHERE agendamento_id = %s ORDER BY id ASC",
        $agendamento_id
    ), ARRAY_A);

    if (empty($results)) {
        wp_die('Nenhum registro encontrado para este agendamento.');
    }

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="agendamento_' . $agendamento_id . '_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    $headers = array_keys($results[0]);
    fputcsv($output, $headers, ';');

    foreach ($results as $row) {
        fputcsv($output, $row, ';');
    }

    fclose($output);
    exit;
}
add_action('admin_post_rep_download_csv', 'rep_exportar_csv_agendamento');

// NOVO: Download CSV Geral com Filtros
function rep_exportar_csv_geral() {
    if (!current_user_can('manage_options')) {
        wp_die('Sem permissão.');
    }

    if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'rep_csv_download')) {
        wp_die('Requisição inválida.');
    }

    global $wpdb;
    $tables = rep_get_table_names();
    $filters = rep_collect_filters($_GET);
    $where_sql = rep_build_where_sql($filters);
    
    $query = "
        SELECT
            P.id,
            CAST(P.data_cadastro AS DATE) AS data,
            E.display_name AS usuario,
            P.agendamento_id,
            P.fornecedor,
            T.NOME_AMBIENTE AS ambiente,
            P.idgis_ambiente,
            P.telefone,
            P.nome AS nome_cliente,
            P.status,
            P.cpf_cnpj,
            P.idcob_contrato,
            P.data_disparo
        FROM {$tables['envios']} P
        LEFT JOIN {$tables['users']} E ON E.ID = P.current_user_id
        LEFT JOIN {$tables['ambiente']} T ON T.IDGIS_AMBIENTE = P.idgis_ambiente
        WHERE {$where_sql}
        ORDER BY P.data_cadastro DESC
    ";
    
    $results = $wpdb->get_results($query, ARRAY_A);

    if (empty($results)) {
        wp_die('Nenhum registro encontrado com os filtros aplicados.');
    }

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_geral_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    $headers = array_keys($results[0]);
    fputcsv($output, $headers, ';');

    foreach ($results as $row) {
        fputcsv($output, $row, ';');
    }

    fclose($output);
    exit;
}
add_action('admin_post_rep_download_csv_geral', 'rep_exportar_csv_geral');

// CSS e renderização da página
function rep_adicionar_css_admin() {
    $screen = get_current_screen();
    if (!$screen || 'toplevel_page_relatorio_envios_pendentes' !== $screen->id) {
        return;
    }
    ?>
    <style>
        :root {
            --primary: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --dark: #1e293b;
            --light: #f8fafc;
            --border: #e2e8f0;
        }
        .wrap { background: var(--light); margin: 0 -20px 0 -2px; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .rep-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 35px 40px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .rep-header h1 { margin: 0; font-size: 28px; font-weight: 600; border: none; padding: 0; color: white; }
        .rep-container { max-width: 1600px; margin: 0 auto; padding: 30px 40px; }
        
        /* Cards de Estatísticas */
        .rep-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .rep-stat-card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); border-left: 4px solid; transition: transform 0.2s; }
        .rep-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
        .rep-stat-card.success { border-color: var(--success); }
        .rep-stat-card.warning { border-color: var(--warning); }
        .rep-stat-card.danger { border-color: var(--danger); }
        .rep-stat-card.info { border-color: var(--info); }
        .rep-stat-card.primary { border-color: var(--primary); }
        .rep-stat-label { font-size: 13px; color: #64748b; margin-bottom: 8px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .rep-stat-value { font-size: 32px; font-weight: 700; color: var(--dark); line-height: 1; }
        
        /* Filtros e Ações */
        .rep-actions-bar { background: white; border-radius: 12px; padding: 20px 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .rep-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 16px; }
        .rep-filter-input { padding: 10px 16px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; transition: all 0.2s; width: 100%; }
        .rep-filter-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
        .rep-actions { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between; }
        .rep-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; }
        .rep-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .rep-btn-primary { background: var(--primary); color: white; }
        .rep-btn-success { background: var(--success); color: white; }
        .rep-btn-secondary { background: #6b7280; color: white; }
        .rep-per-page { display: flex; align-items: center; gap: 8px; }
        .rep-per-page select { padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; }
        
        /* Tabela */
        .rep-table-card { background: white; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); overflow: hidden; }
        .rep-table-wrapper { overflow-x: auto; }
        .rep-table { width: 100%; border-collapse: collapse; }
        .rep-table thead th { background: var(--light); padding: 16px 16px; text-align: left; font-weight: 600; color: var(--dark); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid var(--border); white-space: nowrap; }
        .rep-table tbody tr { border-bottom: 1px solid var(--border); transition: background 0.2s; }
        .rep-table tbody tr:hover { background: rgba(99, 102, 241, 0.03); }
        .rep-table tbody td { padding: 14px 16px; color: #475569; font-size: 14px; }
        .rep-badge { display: inline-block; padding: 4px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .rep-badge-success { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .rep-badge-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .rep-badge-danger { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .rep-badge-info { background: rgba(59, 130, 246, 0.1); color: var(--info); }
        .rep-number { font-weight: 600; font-size: 15px; text-align: center; display: block; }
        .rep-agendamento-wrapper { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .rep-agendamento-id { font-family: 'Courier New', monospace; font-size: 12px; font-weight: 600; color: #475569; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; }
        .rep-btn-csv { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: #10b981; color: white; text-decoration: none; border-radius: 6px; font-size: 11px; font-weight: 600; transition: all 0.2s; border: none; cursor: pointer; }
        .rep-btn-csv:hover { background: #059669; color: white; transform: translateY(-1px); }
        
        /* Paginação */
        .rep-pagination { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; background: white; border-top: 1px solid var(--border); }
        .rep-pagination-info { color: #64748b; font-size: 14px; }
        .rep-pagination-buttons { display: flex; gap: 8px; }
        .rep-pagination-buttons button { padding: 8px 16px; border: 1px solid var(--border); background: white; border-radius: 6px; cursor: pointer; font-size: 14px; transition: all 0.2s; }
        .rep-pagination-buttons button:hover:not(:disabled) { background: var(--primary); color: white; border-color: var(--primary); }
        .rep-pagination-buttons button:disabled { opacity: 0.5; cursor: not-allowed; }
        .rep-pagination-buttons button.active { background: var(--primary); color: white; border-color: var(--primary); }
        
        /* Loading */
        .rep-loading { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .rep-spinner { display: inline-block; width: 40px; height: 40px; border: 4px solid var(--border); border-top-color: var(--primary); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* Responsivo */
        @media (max-width: 768px) {
            .rep-header { padding: 24px; }
            .rep-container { padding: 20px; }
            .rep-filters { grid-template-columns: 1fr; }
            .rep-actions { flex-direction: column; align-items: stretch; }
        }
    </style>
    <?php
}
add_action('admin_head', 'rep_adicionar_css_admin');

function rep_renderizar_pagina_relatorio() {
    ?>
    <div class="wrap">
        <div class="rep-header">
            <h1>📊 Relatório de Envios Pro</h1>
        </div>

        <div class="rep-container">
            <!-- Cards de Estatísticas -->
            <div class="rep-stats" id="stats-container">
                <div class="rep-stat-card success">
                    <div class="rep-stat-label">✅ Enviados</div>
                    <div class="rep-stat-value" id="stat-enviado">-</div>
                </div>
                <div class="rep-stat-card info">
                    <div class="rep-stat-label">⏳ Pend. Aprovação</div>
                    <div class="rep-stat-value" id="stat-pendente-aprovacao">-</div>
                </div>
                <div class="rep-stat-card warning">
                    <div class="rep-stat-label">📅 Agendado MKC</div>
                    <div class="rep-stat-value" id="stat-agendado-mkc">-</div>
                </div>
                <div class="rep-stat-card primary">
                    <div class="rep-stat-label">⏸️ Pendente</div>
                    <div class="rep-stat-value" id="stat-pendente">-</div>
                </div>
                <div class="rep-stat-card danger">
                    <div class="rep-stat-label">❌ Recusados</div>
                    <div class="rep-stat-value" id="stat-negado">-</div>
                </div>
                <div class="rep-stat-card" style="border-color: #8b5cf6; cursor: pointer;" id="card-1x1">
                    <div class="rep-stat-label">📞 Envios 1X1</div>
                    <div class="rep-stat-value" id="stat-1x1">-</div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 8px;" id="stat-1x1-detail">Clique para detalhes</div>
                </div>
            </div>

            <!-- Barra de Filtros e Ações -->
            <div class="rep-actions-bar">
                <div class="rep-filters">
                    <input type="text" id="filterUser" class="rep-filter-input" placeholder="🔍 Filtrar usuário">
                    <input type="text" id="filterFornecedor" class="rep-filter-input" placeholder="🔍 Filtrar fornecedor">
                    <input type="text" id="filterAmbiente" class="rep-filter-input" placeholder="🔍 Filtrar ambiente">
                    <input type="text" id="filterAgendamento" class="rep-filter-input" placeholder="🔍 Filtrar agendamento ID">
                    <input type="number" id="filterIdgis" class="rep-filter-input" placeholder="🔍 Filtrar IDGIS">
                    <input type="date" id="filterDateStart" class="rep-filter-input" placeholder="Data Início">
                    <input type="date" id="filterDateEnd" class="rep-filter-input" placeholder="Data Fim">
                </div>
                
                <div class="rep-actions">
                    <div style="display: flex; gap: 12px;">
                        <button id="btnApplyFilters" class="rep-btn rep-btn-primary">
                            🔍 Aplicar Filtros
                        </button>
                        <button id="btnClearFilters" class="rep-btn rep-btn-secondary">
                            🔄 Limpar
                        </button>
                    </div>
                    
                    <div class="rep-per-page">
                        <label for="perPageSelect">Por página:</label>
                        <select id="perPageSelect">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    
                    <button id="btnDownloadCSV" class="rep-btn rep-btn-success">
                        📥 Download CSV Geral
                    </button>
                </div>
            </div>

            <!-- Tabela -->
            <div class="rep-table-card">
                <div class="rep-table-wrapper">
                    <table class="rep-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Usuário</th>
                                <th>Agendamento ID</th>
                                <th>Fornecedor</th>
                                <th>Ambiente</th>
                                <th style="text-align: center;">✅ Enviado</th>
                                <th style="text-align: center;">⏳ Pend. Apr.</th>
                                <th style="text-align: center;">📅 Agend. MKC</th>
                                <th style="text-align: center;">⏸️ Pendente</th>
                                <th style="text-align: center;">❌ Recusado</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr>
                                <td colspan="10" class="rep-loading">
                                    <div class="rep-spinner"></div>
                                    <p>Carregando dados...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginação -->
                <div class="rep-pagination">
                    <div class="rep-pagination-info">
                        Mostrando <span id="showing-start">0</span> até <span id="showing-end">0</span> de <span id="total-records">0</span> registros
                    </div>
                    <div class="rep-pagination-buttons" id="pagination-buttons">
                        <!-- Botões gerados via JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php $rep_csv_nonce = wp_create_nonce('rep_csv_download'); ?>
    <script>
    (function() {
        'use strict';
        
        const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        const nonce = '<?php echo wp_create_nonce('rep_nonce'); ?>';
        const csvEndpoint = '<?php echo esc_url(admin_url('admin-post.php')); ?>';
        const csvNonce = '<?php echo esc_js($rep_csv_nonce); ?>';
        
        let currentPage = 1;
        let perPage = 25;
        let totalRecords = 0;
        let currentFilters = {};
        
        // Elementos
        const tableBody = document.getElementById('tableBody');
        const paginationButtons = document.getElementById('pagination-buttons');
        const perPageSelect = document.getElementById('perPageSelect');
        const btnApplyFilters = document.getElementById('btnApplyFilters');
        const btnClearFilters = document.getElementById('btnClearFilters');
        const btnDownloadCSV = document.getElementById('btnDownloadCSV');
        
        // Filtros
        const filterUser = document.getElementById('filterUser');
        const filterFornecedor = document.getElementById('filterFornecedor');
        const filterAmbiente = document.getElementById('filterAmbiente');
        const filterAgendamento = document.getElementById('filterAgendamento');
        const filterIdgis = document.getElementById('filterIdgis');
        const filterDateStart = document.getElementById('filterDateStart');
        const filterDateEnd = document.getElementById('filterDateEnd');
        
        // Estatísticas
        const statEnviado = document.getElementById('stat-enviado');
        const statPendenteAprovacao = document.getElementById('stat-pendente-aprovacao');
        const statAgendadoMkc = document.getElementById('stat-agendado-mkc');
        const statPendente = document.getElementById('stat-pendente');
        const statNegado = document.getElementById('stat-negado');
        const stat1x1 = document.getElementById('stat-1x1');
        const stat1x1Detail = document.getElementById('stat-1x1-detail');
        const card1x1 = document.getElementById('card-1x1');
        
        let carteiras1x1Data = [];
        
        // Event Listeners
        btnApplyFilters.addEventListener('click', applyFilters);
        btnClearFilters.addEventListener('click', clearFilters);
        btnDownloadCSV.addEventListener('click', downloadCSV);
        card1x1.addEventListener('click', show1x1Details);
        perPageSelect.addEventListener('change', function() {
            perPage = parseInt(this.value);
            currentPage = 1;
            loadData();
        });
        
        // Enter nos filtros aplica
        [filterUser, filterFornecedor, filterAmbiente, filterAgendamento, filterIdgis, filterDateStart, filterDateEnd].forEach(el => {
            if (el) {
                el.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        applyFilters();
                    }
                });
            }
        });
        
        function applyFilters() {
            currentFilters = {
                filter_user: filterUser.value.trim(),
                filter_fornecedor: filterFornecedor.value.trim(),
                filter_ambiente: filterAmbiente.value.trim(),
                filter_agendamento: filterAgendamento.value.trim(),
                filter_idgis: filterIdgis.value.trim(),
                filter_date_start: filterDateStart.value,
                filter_date_end: filterDateEnd.value
            };
            currentPage = 1;
            loadData();
        }
        
        function clearFilters() {
            filterUser.value = '';
            filterFornecedor.value = '';
            filterAmbiente.value = '';
            filterAgendamento.value = '';
            filterIdgis.value = '';
            filterDateStart.value = '';
            filterDateEnd.value = '';
            currentFilters = {};
            currentPage = 1;
            loadData();
        }
        
        function downloadCSV() {
            const params = new URLSearchParams({
                action: 'rep_download_csv_geral',
                _wpnonce: csvNonce
            });
            
            Object.entries(currentFilters).forEach(([key, value]) => {
                if (value) {
                    params.append(key, value);
                }
            });

            window.location.href = `${csvEndpoint}?${params.toString()}`;
        }
        
        function show1x1Details() {
            if (!carteiras1x1Data || carteiras1x1Data.length === 0) {
                alert('Nenhum dado de envios 1X1 disponível.');
                return;
            }
            
            let message = 'Detalhes dos Envios 1X1 por Carteira:\n\n';
            carteiras1x1Data.forEach(item => {
                message += `${item.carteira}: ${formatNumber(item.total)} envios\n`;
            });
            
            alert(message);
        }
        
        async function load1x1Stats() {
            try {
                const formData = new FormData();
                formData.append('action', 'rep_get_1x1_stats');
                formData.append('nonce', nonce);
                
                const response = await fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const total = result.data.total || 0;
                    carteiras1x1Data = result.data.carteiras || [];
                    
                    stat1x1.textContent = formatNumber(total);
                    
                    if (carteiras1x1Data.length > 0) {
                        const topCarteira = carteiras1x1Data[0];
                        stat1x1Detail.textContent = `Top: ${topCarteira.carteira} (${formatNumber(topCarteira.total)})`;
                    } else {
                        stat1x1Detail.textContent = 'Nenhum envio 1X1';
                    }
                }
            } catch (error) {
                console.error('Erro ao carregar estatísticas 1X1:', error);
                stat1x1.textContent = '0';
                stat1x1Detail.textContent = 'Erro ao carregar';
            }
        }
        
        function formatNumber(num) {
            const value = Number(num);
            return new Intl.NumberFormat('pt-BR').format(Number.isFinite(value) ? value : 0);
        }
        
        function formatDate(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr + 'T00:00:00');
            return date.toLocaleDateString('pt-BR');
        }
        
        async function loadData() {
            tableBody.innerHTML = '<tr><td colspan="10" class="rep-loading"><div class="rep-spinner"></div><p>Carregando dados...</p></td></tr>';
            
            try {
                // Busca dados
                const formData = new FormData();
                formData.append('action', 'rep_get_data');
                formData.append('nonce', nonce);
                formData.append('page', currentPage);
                formData.append('per_page', perPage);
                
                // Adiciona filtros
                Object.keys(currentFilters).forEach(key => {
                    if (currentFilters[key]) {
                        formData.append(key, currentFilters[key]);
                    }
                });
                
                const response = await fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.data || 'Erro ao carregar dados');
                }
                
                const data = result.data.data || [];
                const totals = result.data.totals || {};
                totalRecords = result.data.total_records || 0;
                
                // Atualiza estatísticas
                statEnviado.textContent = formatNumber(totals.total_enviado);
                statPendenteAprovacao.textContent = formatNumber(totals.total_pendente_aprovacao);
                statAgendadoMkc.textContent = formatNumber(totals.total_agendado_mkc);
                statPendente.textContent = formatNumber(totals.total_pendente);
                statNegado.textContent = formatNumber(totals.total_negado);
                
                // Renderiza tabela
                renderTable(data);
                
                // Renderiza paginação
                renderPagination();
                
            } catch (error) {
                console.error('Erro:', error);
                tableBody.innerHTML = '<tr><td colspan="10" class="rep-loading"><p style="color: #ef4444;">❌ Erro ao carregar dados: ' + error.message + '</p></td></tr>';
            }
        }
        
        function renderTable(data) {
            if (!data || data.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="10" class="rep-loading"><p>📭 Nenhum registro encontrado</p></td></tr>';
                return;
            }
            
            tableBody.innerHTML = '';
            
            data.forEach(row => {
                const tr = document.createElement('tr');
                
                const usuario = row.USUARIO || 'Sem usuário';
                const agendamento = row.AGENDAMENTO_ID || 'N/A';
                const ambiente = row.NOME_AMBIENTE || 'Sem ambiente';
                const hasAgendamento = agendamento && agendamento !== 'N/A';
                
                // URL para CSV da campanha
                const csvUrl = hasAgendamento
                    ? `${csvEndpoint}?action=rep_download_csv&agendamento_id=${encodeURIComponent(agendamento)}&_wpnonce=${csvNonce}`
                    : '';
                
                tr.innerHTML = `
                    <td>${formatDate(row.DATA)}</td>
                    <td><strong>${escapeHtml(usuario)}</strong></td>
                    <td>
                        <div class="rep-agendamento-wrapper">
                            <code class="rep-agendamento-id">${escapeHtml(agendamento)}</code>
                            ${hasAgendamento ? `
                                <a href="${csvUrl}" class="rep-btn-csv" title="Baixar CSV desta campanha">
                                    📥 CSV
                                </a>
                            ` : ''}
                        </div>
                    </td>
                    <td>${escapeHtml(row.FORNECEDOR)}</td>
                    <td><span class="rep-badge rep-badge-info">${escapeHtml(ambiente)}</span></td>
                    <td><span class="rep-number" style="color: var(--success);">${formatNumber(row.QTD_ENVIADO)}</span></td>
                    <td><span class="rep-number" style="color: var(--info);">${formatNumber(row.QTD_PENDENTE_APROVACAO)}</span></td>
                    <td><span class="rep-number" style="color: var(--warning);">${formatNumber(row.QTD_AGENDADO_MKC)}</span></td>
                    <td><span class="rep-number" style="color: var(--primary);">${formatNumber(row.QTD_PENDENTE)}</span></td>
                    <td><span class="rep-number" style="color: var(--danger);">${formatNumber(row.QTD_NEGADO)}</span></td>
                `;
                
                tableBody.appendChild(tr);
            });
        }
        
        function renderPagination() {
            const totalPages = totalRecords === 0 ? 0 : Math.ceil(totalRecords / perPage);
            const start = totalRecords === 0 ? 0 : ((currentPage - 1) * perPage) + 1;
            const end = totalRecords === 0 ? 0 : Math.min(currentPage * perPage, totalRecords);
            
            document.getElementById('showing-start').textContent = formatNumber(start);
            document.getElementById('showing-end').textContent = formatNumber(end);
            document.getElementById('total-records').textContent = formatNumber(totalRecords);
            
            paginationButtons.innerHTML = '';

            if (totalPages === 0) {
                return;
            }
            
            // Botão Anterior
            const btnPrev = document.createElement('button');
            btnPrev.textContent = '← Anterior';
            btnPrev.disabled = currentPage === 1;
            btnPrev.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    loadData();
                }
            });
            paginationButtons.appendChild(btnPrev);
            
            // Botões de página
            const maxButtons = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
            let endPage = Math.min(totalPages, startPage + maxButtons - 1);
            
            if (endPage - startPage < maxButtons - 1) {
                startPage = Math.max(1, endPage - maxButtons + 1);
            }
            
            if (startPage > 1) {
                const btn1 = document.createElement('button');
                btn1.textContent = '1';
                btn1.addEventListener('click', () => {
                    currentPage = 1;
                    loadData();
                });
                paginationButtons.appendChild(btn1);
                
                if (startPage > 2) {
                    const btnDots = document.createElement('button');
                    btnDots.textContent = '...';
                    btnDots.disabled = true;
                    paginationButtons.appendChild(btnDots);
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                const btn = document.createElement('button');
                btn.textContent = i;
                if (i === currentPage) {
                    btn.classList.add('active');
                }
                btn.addEventListener('click', () => {
                    currentPage = i;
                    loadData();
                });
                paginationButtons.appendChild(btn);
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    const btnDots = document.createElement('button');
                    btnDots.textContent = '...';
                    btnDots.disabled = true;
                    paginationButtons.appendChild(btnDots);
                }
                
                const btnLast = document.createElement('button');
                btnLast.textContent = totalPages;
                btnLast.addEventListener('click', () => {
                    currentPage = totalPages;
                    loadData();
                });
                paginationButtons.appendChild(btnLast);
            }
            
            // Botão Próximo
            const btnNext = document.createElement('button');
            btnNext.textContent = 'Próximo →';
            btnNext.disabled = currentPage === totalPages;
            btnNext.addEventListener('click', () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    loadData();
                }
            });
            paginationButtons.appendChild(btnNext);
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }
        
        // Carrega dados ao iniciar
        loadData();
        load1x1Stats();
        
    })();
    </script>
    <?php
}
?>