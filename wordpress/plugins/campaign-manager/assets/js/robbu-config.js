/**
 * Robbu Config - JavaScript
 */

jQuery(document).ready(function($) {
    
    // Carrega mapeamentos ao iniciar
    loadQueues();
    
    // ===== BUSCAR QUEUES DA API =====
    $('#fetch-queues-btn').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('⏳ Buscando...');
        
        $.ajax({
            url: cmRobbuAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cm_robbu_fetch_queues_api',
                nonce: cmRobbuAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    populateQueueSelect(response.data);
                    showMessage('✅ Queues carregadas com sucesso!', 'success');
                } else {
                    showMessage('❌ ' + response.data, 'error');
                }
            },
            error: function() {
                showMessage('❌ Erro de conexão', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html('🔄 Buscar Queues da API');
            }
        });
    });
    
    // ===== POPULAR SELECT DE QUEUES =====
    function populateQueueSelect(queues) {
        const select = $('#queue_select');
        select.html('<option value="">-- Selecione uma Queue --</option>');
        
        if (!queues || queues.length === 0) {
            select.html('<option value="">❌ Nenhuma queue encontrada</option>');
            return;
        }
        
        $.each(queues, function(index, queue) {
            const queueId = queue.id || queue.IdQueue || '';
            const queueName = queue.name || queue.NomeQueue || '';
            
            if (queueId && queueName) {
                // Formato: "ID|NOME"
                const value = queueId + '|' + queueName;
                select.append(`<option value="${value}">${queueName} (${queueId})</option>`);
            }
        });
        
        select.prop('disabled', false);
    }
    
    // ===== SALVAR MAPEAMENTO =====
    $('#cm-robbu-queue-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            action: 'cm_robbu_save_queue',
            nonce: cmRobbuAjax.nonce,
            idgis_ambiente: $('#idgis_ambiente').val(),
            queue_data: $('#queue_select').val()
        };
        
        if (!formData.idgis_ambiente || !formData.queue_data) {
            showMessage('❌ Preencha todos os campos', 'error');
            return;
        }
        
        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('⏳ Salvando...');
        
        $.ajax({
            url: cmRobbuAjax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showMessage('✅ ' + response.data, 'success');
                    loadQueues();
                    $('#cm-robbu-queue-form')[0].reset();
                    $('#queue_select').prop('disabled', true)
                        .html('<option value="">-- Clique em "Buscar Queues da API" primeiro --</option>');
                } else {
                    showMessage('❌ ' + (response.data || 'Erro ao salvar'), 'error');
                }
            },
            error: function() {
                showMessage('❌ Erro de comunicação', 'error');
            },
            complete: function() {
                submitBtn.prop('disabled', false).html('➕ Salvar Mapeamento');
            }
        });
    });
    
    // ===== CARREGAR MAPEAMENTOS =====
    function loadQueues() {
        $('#queues-loading').show();
        $('#queues-table').hide();
        
        $.ajax({
            url: cmRobbuAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cm_robbu_get_queues',
                nonce: cmRobbuAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderQueues(response.data);
                } else {
                    $('#queues-loading').html('<p style="color:#dc3232;">❌ Erro ao carregar</p>');
                }
            },
            error: function() {
                $('#queues-loading').html('<p style="color:#dc3232;">❌ Erro de conexão</p>');
            }
        });
    }
    
    // ===== RENDERIZAR MAPEAMENTOS =====
    function renderQueues(queues) {
        const tbody = $('#queues-tbody');
        tbody.empty();
        
        if (!queues || queues.length === 0) {
            $('#queues-loading').html('<p>Nenhum mapeamento cadastrado.</p>');
            return;
        }
        
        $.each(queues, function(index, queue) {
            const statusBadge = queue.ativo == 1 
                ? '<span class="cm-badge cm-badge-success">✓ Ativo</span>' 
                : '<span class="cm-badge cm-badge-danger">✗ Inativo</span>';
            
            const row = `
                <tr>
                    <td><strong>${queue.idgis_ambiente}</strong></td>
                    <td><code>${queue.queue_id}</code></td>
                    <td>${escapeHtml(queue.queue_name)}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <button class="cm-btn-danger delete-queue" data-id="${queue.id}">
                            🗑️ Deletar
                        </button>
                    </td>
                </tr>
            `;
            
            tbody.append(row);
        });
        
        $('#queues-loading').hide();
        $('#queues-table').show();
    }
    
    // ===== DELETAR MAPEAMENTO =====
    $(document).on('click', '.delete-queue', function() {
        if (!confirm('⚠️ Deletar este mapeamento?')) {
            return;
        }
        
        const id = $(this).data('id');
        const button = $(this);
        
        button.prop('disabled', true).html('⏳ Deletando...');
        
        $.ajax({
            url: cmRobbuAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cm_robbu_delete_queue',
                nonce: cmRobbuAjax.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    showMessage('✅ ' + response.data, 'success');
                    loadQueues();
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
        const messageDiv = $('#cm-robbu-message');
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