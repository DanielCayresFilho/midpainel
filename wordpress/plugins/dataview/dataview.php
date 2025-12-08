<?php
/**
 * Plugin Name:       Relatório de Fornecedores
 * Description:       Adiciona uma página de relatório no admin para visualizar dados da tabela evento_fornecedor.
 * Version:           1.0
 * Author:            WP Dev Helper
 */

// Medida de segurança para impedir o acesso direto ao arquivo.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adiciona a página do relatório ao menu do painel administrativo.
 */
function rf_adicionar_pagina_relatorio_admin() {
    add_menu_page(
        'Visualização de dados',      // Título da página (tag <title>)
        'Relatórios',                     // Texto que aparece no menu
        'manage_options',                 // Capacidade necessária para ver o item
        'relatorio_fornecedores',         // Slug (URL) da página
        'rf_renderizar_pagina_relatorio', // Função que vai renderizar o conteúdo da página
        'dashicons-chart-area',           // Ícone do menu (pode escolher outro em https://developer.wordpress.org/resource/dashicons/)
        20                                // Posição no menu
    );
}
add_action( 'admin_menu', 'rf_adicionar_pagina_relatorio_admin' );

/**
 * Renderiza o conteúdo (HTML) da página do relatório.
 */
function rf_renderizar_pagina_relatorio() {
    // Acesso à variável global do WordPress para interagir com o banco de dados.
    global $wpdb;

    // Monta o nome completo da tabela usando o prefixo do WordPress.
    $table_name = $wpdb->prefix . 'eventos_envios';

    // A consulta SQL que você especificou. Adicionei um ORDER BY para deixar os dados mais recentes no topo.
    $query = "
        SELECT 
            data, 
            fornecedor,
            case when codigoCarteira LIKE '%INDEF%' then 'BV CL' else 'BV VEICULOS' end as codigoCarteira,
            COUNT(*) as qtd 
        FROM {$table_name} 
        where codigoCarteira <> 1
        GROUP BY data, codigoCarteira, fornecedor
        ORDER BY data DESC
    ";

    // Executa a consulta e armazena os resultados.
    $results = $wpdb->get_results( $query );
    ?>
    
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        
        <p>Retorno Eventos Envios.</p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col">Data</th>
                    <th scope="col">Fornecedor</th>
                    <th scope="col">Carteira</th>
                    <th scope="col">Quantidade</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Verifica se a consulta retornou algum resultado.
                if ( $results ) {
                    // Loop para percorrer cada linha de resultado.
                    foreach ( $results as $row ) {
                        echo '<tr>';
                        echo '<td>' . esc_html( $row->data ) . '</td>';
                        echo '<td>' . esc_html( $row->fornecedor ) . '</td>';
                        echo '<td>' . esc_html( $row->codigoCarteira ) . '</td>';
                        echo '<td>' . esc_html( $row->qtd ) . '</td>';
                        echo '</tr>';
                    }
                } else {
                    // Se não houver resultados, exibe uma mensagem.
                    echo '<tr><td colspan="4">Nenhum dado encontrado na tabela.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    
    <?php
}