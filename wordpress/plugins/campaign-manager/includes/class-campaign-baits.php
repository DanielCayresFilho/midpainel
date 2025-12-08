<?php
/**
 * Gerenciamento de Iscas para Campanhas
 */

if (!defined('ABSPATH')) exit;

class Campaign_Manager_Baits {
    
    private $table;
    
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'cm_baits';
        
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('wp_ajax_cm_baits_save', array($this, 'save_bait'));
        add_action('wp_ajax_cm_baits_get', array($this, 'get_baits'));
        add_action('wp_ajax_cm_baits_delete', array($this, 'delete_bait'));
        add_action('wp_ajax_cm_baits_toggle', array($this, 'toggle_bait'));
    }
    
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cm_baits';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            telefone varchar(20) NOT NULL,
            nome varchar(255) NOT NULL,
            idgis_ambiente int(11) NOT NULL,
            ativo tinyint(1) DEFAULT 1,
            criado_em datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function register_menu() {
        add_submenu_page(
            'create-campaign',
            'Iscas',
            '🎣 Iscas',
            'edit_posts',
            'cm-baits',
            array($this, 'render_page')
        );
    }
    
    public function render_page() {
        ?>
        <div class="wrap">
            <h1>🎣 Iscas de Campanha</h1>
            <p>Telefones que sempre receberão todas as campanhas</p>
            
            <div id="cm-baits-message" style="display:none;padding:10px;margin:15px 0;border-radius:5px;"></div>
            
            <div style="background:white;padding:20px;margin:20px 0;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                <h2>➕ Adicionar Isca</h2>
                <form id="cm-baits-form">
                    <table class="form-table">
                        <tr>
                            <th><label>📱 Telefone</label></th>
                            <td>
                                <input type="text" name="telefone" id="bait_telefone" 
                                       class="regular-text" placeholder="11999999999" required>
                                <p class="description">Apenas números, sem espaços</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>👤 Nome</label></th>
                            <td>
                                <input type="text" name="nome" id="bait_nome" 
                                       class="regular-text" placeholder="Ex: Monitoramento 01" required>
                            </td>
                        </tr>
                        <tr>
                            <th><label>🔢 IDGIS Ambiente</label></th>
                            <td>
                                <input type="number" name="idgis_ambiente" id="bait_idgis" 
                                       class="regular-text" placeholder="Ex: 364" required>
                            </td>
                        </tr>
                    </table>
                    <button type="submit" class="button button-primary">➕ Adicionar Isca</button>
                </form>
            </div>

            <div style="background:white;padding:20px;margin:20px 0;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                <h2>📋 Iscas Cadastradas</h2>
                <div id="baits-loading">⏳ Carregando...</div>
                <div id="baits-container" style="display:none;"></div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            loadBaits();
            
            $('#cm-baits-form').on('submit', function(e) {
                e.preventDefault();
                
                var btn = $(this).find('button[type="submit"]');
                btn.prop('disabled', true).text('⏳ Salvando...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cm_baits_save',
                        nonce: '<?php echo wp_create_nonce('campaign-manager-nonce'); ?>',
                        telefone: $('#bait_telefone').val(),
                        nome: $('#bait_nome').val(),
                        idgis_ambiente: $('#bait_idgis').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            showMessage('✅ ' + response.data, 'success');
                            loadBaits();
                            $('#cm-baits-form')[0].reset();
                        } else {
                            showMessage('❌ ' + response.data, 'error');
                        }
                    },
                    error: function() {
                        showMessage('❌ Erro de conexão', 'error');
                    },
                    complete: function() {
                        btn.prop('disabled', false).text('➕ Adicionar Isca');
                    }
                });
            });
            
            function loadBaits() {
                $('#baits-loading').show();
                $('#baits-container').hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cm_baits_get',
                        nonce: '<?php echo wp_create_nonce('campaign-manager-nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            renderBaits(response.data);
                        } else {
                            $('#baits-loading').html('<p>Erro ao carregar iscas</p>');
                        }
                    },
                    error: function() {
                        $('#baits-loading').html('<p>Erro de conexão</p>');
                    }
                });
            }
            
            function renderBaits(baits) {
                var container = $('#baits-container');
                container.empty();
                
                if (!baits || baits.length === 0) {
                    $('#baits-loading').html('<p>Nenhuma isca cadastrada.</p>');
                    return;
                }
                
                var table = $('<table class="wp-list-table widefat fixed striped">');
                table.append('<thead><tr><th>Telefone</th><th>Nome</th><th>IDGIS</th><th>Status</th><th>Ações</th></tr></thead>');
                var tbody = $('<tbody>');
                
                $.each(baits, function(i, bait) {
                    var status = bait.ativo == 1 
                        ? '<span style="color:green;font-weight:bold;">✓ Ativo</span>' 
                        : '<span style="color:red;font-weight:bold;">✗ Inativo</span>';
                    
                    var row = $('<tr>');
                    row.append('<td><strong>' + bait.telefone + '</strong></td>');
                    row.append('<td>' + bait.nome + '</td>');
                    row.append('<td>' + bait.idgis_ambiente + '</td>');
                    row.append('<td>' + status + '</td>');
                    
                    var actions = $('<td>');
                    actions.append(
                        $('<button class="button toggle-bait" style="margin-right:5px;">')
                            .data('id', bait.id)
                            .data('ativo', bait.ativo)
                            .text(bait.ativo == 1 ? '⏸️ Desativar' : '▶️ Ativar')
                    );
                    actions.append(
                        $('<button class="button delete-bait">')
                            .data('id', bait.id)
                            .text('🗑️ Deletar')
                    );
                    
                    row.append(actions);
                    tbody.append(row);
                });
                
                table.append(tbody);
                container.append(table);
                
                $('#baits-loading').hide();
                $('#baits-container').show();
            }
            
            $(document).on('click', '.toggle-bait', function() {
                var id = $(this).data('id');
                var ativo = $(this).data('ativo') == 1 ? 0 : 1;
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cm_baits_toggle',
                        nonce: '<?php echo wp_create_nonce('campaign-manager-nonce'); ?>',
                        id: id,
                        ativo: ativo
                    },
                    success: function(response) {
                        if (response.success) {
                            loadBaits();
                        }
                    }
                });
            });
            
            $(document).on('click', '.delete-bait', function() {
                if (!confirm('⚠️ Deletar esta isca?')) return;
                
                var id = $(this).data('id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cm_baits_delete',
                        nonce: '<?php echo wp_create_nonce('campaign-manager-nonce'); ?>',
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            showMessage('✅ ' + response.data, 'success');
                            loadBaits();
                        }
                    }
                });
            });
            
            function showMessage(text, type) {
                var msg = $('#cm-baits-message');
                msg.css({
                    'background': type === 'success' ? '#d1fae5' : '#fee2e2',
                    'border': '2px solid ' + (type === 'success' ? '#10b981' : '#ef4444'),
                    'color': type === 'success' ? '#065f46' : '#991b1b'
                });
                msg.html(text).show();
                setTimeout(function() { msg.fadeOut(); }, 5000);
            }
        });
        </script>
        <?php
    }
    
    public function save_bait() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        
        $telefone = preg_replace('/[^0-9]/', '', $_POST['telefone']);
        $nome = sanitize_text_field($_POST['nome']);
        $idgis = intval($_POST['idgis_ambiente']);
        
        if (empty($telefone) || empty($nome) || $idgis <= 0) {
            wp_send_json_error('Dados incompletos.');
        }
        
        global $wpdb;
        $result = $wpdb->insert($this->table, array(
            'telefone' => $telefone,
            'nome' => $nome,
            'idgis_ambiente' => $idgis,
            'ativo' => 1
        ), array('%s', '%s', '%d', '%d'));
        
        if ($result === false) {
            wp_send_json_error('Erro ao salvar: ' . $wpdb->last_error);
        }
        
        wp_send_json_success('Isca adicionada com sucesso!');
    }
    
    public function get_baits() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        
        global $wpdb;
        $baits = $wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY criado_em DESC",
            ARRAY_A
        );
        
        wp_send_json_success($baits);
    }
    
    public function delete_bait() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        
        $id = intval($_POST['id']);
        if ($id <= 0) {
            wp_send_json_error('ID inválido.');
        }
        
        global $wpdb;
        $result = $wpdb->delete($this->table, array('id' => $id), array('%d'));
        
        if ($result === false) {
            wp_send_json_error('Erro ao deletar.');
        }
        
        wp_send_json_success('Isca deletada!');
    }
    
    public function toggle_bait() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        
        $id = intval($_POST['id']);
        $ativo = intval($_POST['ativo']);
        
        if ($id <= 0) {
            wp_send_json_error('ID inválido.');
        }
        
        global $wpdb;
        $result = $wpdb->update(
            $this->table,
            array('ativo' => $ativo),
            array('id' => $id),
            array('%d'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Erro ao atualizar.');
        }
        
        wp_send_json_success($ativo ? 'Ativada!' : 'Desativada!');
    }
    
    public static function get_active_baits() {
        global $wpdb;
        $table = $wpdb->prefix . 'cm_baits';
        
        $baits = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE ativo = 1",
            ARRAY_A
        );
        
        return $baits ? $baits : array();
    }
}

new Campaign_Manager_Baits();