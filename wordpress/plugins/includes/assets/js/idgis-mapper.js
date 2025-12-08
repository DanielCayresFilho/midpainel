/**
 * IDGIS Mapper - JavaScript
 */

jQuery(document).ready(function($) {
    
    // Carrega mapeamentos ao iniciar
    loadMappings();
    
    // ===== BUSCA IDGIS DA TABELA =====
    $('#tabela_origem').on('change', function() {
        const tableName = $(this).val();
        const idgisSelect = $('#idgis_original');
        
        if (!tableName) {
            idgisSelect.prop('disabled', true)
                .html('<option value="">-- Selecione a tabela primeiro --</option>');
            return;
        }
        
        idgisSelect.prop('disabled', true).html('<option value="">⏳ Carregando...</option>');
        
        $.ajax({
            url: cmIdgisAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cm_idgis_get_idgis_from_table',
                nonce: cmIdgisAjax.nonce,
                table_name: tableName
            },
            success: function(response) {
                if (response.success) {
                    idgisSelect.html('<option value="">-- Selecione um IDGIS --</option>');
                    
                    if (response.data && response.data.length > 0) {
                        $.each(response.data, function(index, idgis) {
                            idgisSelect.append(`<option value="${idgis}">${idgis}</option>`);
                        });
                        idgisSelect.prop('disabled', false);
                    } else {
                        idgisSelect.html('<option value="">❌ Nenhum IDGIS encontrado</option>');
                    }
                } else {
                    idgisSelect.html('<option value="">❌ Erro ao carregar</option>');
                    showMessage(response.data || 'Erro ao buscar IDGIS', 'error');
                }
            },
            error: function() {
                idgisSelect.html('<option value="">❌ Erro de conexão</option>');
                showMessage('Erro de comunicação', 'error');
            }
        });
    });
    
    // ===== SALVAR MAPEAMENTO =====
    $('#cm-idgis-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            action: 'cm_idgis_save_mapping',
            nonce: cmIdgisAjax.nonce,
            tabela_origem: $('#tabela_origem').val(),
            provedor_destino: $('#provedor_destino').val(),
            idgis_original: $('#idgis_original').val(),
            idgis_mapeado: $('#idgis_mapeado').val()
        };
        
        if (!formData.tabela_origem || !formData.idgis_original || !formData.idgis_mapeado) {
            showMessage('❌ Preencha todos os campos obrigatórios', 'error');
            return;
        }
        
        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('⏳ Salvando...');
        
        $.ajax({
            url: cmIdgisAjax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showMessage('✅ ' + response.data, 'success');
                    loadMappings();
                    $('#cm-idgis-form')[0].reset();
                    $('#idgis_original').prop('disabled', true)
                        .html('<option value="">-- Selecione a tabela primeiro --</option>');
                } else {
                    showMessage('❌ ' + (response.data || 'Erro ao salvar'), 'error');
                }
            },
            error: function() {
                showMessage('❌ Erro de comunicação', 'error');
            },
            complete: function() {
                submitBtn.prop('disabled', false).html('➕ Adicionar Mapeamento');
            }
        });
    });
    
    // ===== CARREGAR MAPEAMENTOS =====
    function loadMappings() {
        $('#mappings-loading').show();
        $('#mappings-table').hide();
        
        $.ajax({
            url: cmIdgisAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cm_idgis_get_mappings',
                nonce: cmIdgisAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderMappings(response.data);
                } else {
                    $('#mappings-loading').html('<p style="color:#dc3232;">❌ Erro ao carregar</p>');
                }
            },
            error: function() {
                $('#mappings-loading').html('<p style="color:#dc3232;">❌ Erro de conexão</p>');
            }
        });
    }
    
    // ===== RENDERIZAR MAPEAMENTOS =====
    function renderMappings(mappings) {
        const tbody = $('#mappings-tbody');
        tbody.empty();
        
        if (!mappings || mappings.length === 0) {
            $('#mappings-loading').html('<p>Nenhum mapeamento cadastrado.</p>');
            return;
        }
        
        $.each(mappings, function(index, mapping) {
            const statusBadge = mapping.ativo == 1 
                ? '<span class="cm-badge cm-badge-success">✓ Ativo</span>' 
                : '<span class="cm-badge cm-badge-danger">✗ Inativo</span>';
            
            let provedorDisplay = mapping.provedor_destino;
            if (mapping.provedor_destino === '*' || mapping.provedor_destino === '') {
                provedorDisplay = '<span class="cm-badge cm-badge-primary">🌟 TODOS</span>';
            }
            
            const row = `
                <tr>
                    <td><strong>${escapeHtml(mapping.tabela_origem)}</strong></td>
                    <td>${provedorDisplay}</td>
                    <td><code>${mapping.idgis_ambiente_original}</code></td>
                    <td><code style="color:#6366f1;font-weight:700;">${mapping.idgis_ambiente_mapeado}</code></td>
                    <td>${statusBadge}</td>
                    <td>${formatDate(mapping.criado_em)}</td>
                    <td>
                        <button class="cm-btn-danger delete-mapping" data-id="${mapping.id}">
                            🗑️ Deletar
                        </button>
                    </td>
                </tr>
            `;
            
            tbody.append(row);
        });
        
        $('#mappings-loading').hide();
        $('#mappings-table').show();
    }
    
    // ===== DELETAR MAPEAMENTO =====
    $(document).on('click', '.delete-mapping', function() {
        if (!confirm('⚠️ Deletar este mapeamento?')) {
            return;
        }
        
        const id = $(this).data('id');
        const button = $(this);
        
        button.prop('disabled', true).html('⏳ Deletando...');
        
        $.ajax({
            url: cmIdgisAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cm_idgis_delete_mapping',
                nonce: cmIdgisAjax.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    showMessage('✅ ' + response.data, 'success');
                    loadMappings();
                } else {
                    showMessage('❌ ' + (response.data || 'Erro ao deletar'), 'error');
                    button.prop('disabled', false).html('🗑️ Deletar');
                }
            },
            error: function() {
                showMessage('❌ Erro de comunicação', 'error');
                button.prop('disabled', false).html('🗑️ Deletar');
            }
        });
    });
    
    // ===== FUNÇÕES AUXILIARES =====
    
    function showMessage(text, type) {
        const messageDiv = $('#cm-idgis-message');
        const className = type === 'success' ? 'success' : 'error';
        
        messageDiv
            .removeClass('success error')
            .addClass(className)
            .html(text)
            .show();
        
        setTimeout(function() {
            messageDiv.fadeOut();
        }, 5000);
    }
    
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        
        const date = new Date(dateString);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        
        return `${day}/${month}/${year} ${hours}:${minutes}`;
    }
    
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});