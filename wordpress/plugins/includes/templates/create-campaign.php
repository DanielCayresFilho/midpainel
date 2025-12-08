<?php
/**
 * Template: Criar Nova Campanha
 */

if (!defined('ABSPATH')) exit;

$providers = Campaign_Manager_Core::get_available_providers();
?>

<div class="wrap cm-wrap">
    <div class="cm-header">
        <h1>📧 Criar Nova Campanha</h1>
        <p>Configure e agende suas campanhas multiplataforma</p>
    </div>

    <!-- Step 1: Base de Dados -->
    <div class="cm-card" id="step-1">
        <h2>📊 Passo 1: Selecione a Base de Dados</h2>
        <select id="data-source-select" class="cm-select">
            <option value="">-- Escolha uma base --</option>
            <?php if (!empty($tables)): ?>
                <?php foreach ($tables as $table): ?>
                    <option value="<?php echo esc_attr($table[0]); ?>">
                        <?php echo esc_html($table[0]); ?>
                    </option>
                <?php endforeach; ?>
            <?php else: ?>
                <option value="">Nenhuma tabela encontrada</option>
            <?php endif; ?>
        </select>
    </div>

    <!-- Step 2: Filtros -->
    <div class="cm-card" id="step-2" style="display:none;">
        <h2>🔍 Passo 2: Filtros</h2>
        
        <!-- O aviso de iscas será mostrado dinamicamente via JavaScript -->
        <div id="cm-baits-info-container"></div>
        
        <div id="filters-container">
            <p>Carregando filtros...</p>
        </div>
        
        <div class="cm-audience-badge">
            <strong>👥 Audiência:</strong>
            <span id="audience-count">0</span> clientes
        </div>

        <button id="continue-to-step-3" class="cm-btn cm-btn-primary">
            Continuar para Detalhes →
        </button>
    </div>

    <!-- Step 3: Detalhes -->
    <div class="cm-card" id="step-3" style="display:none;">
        <h2>⚙️ Passo 3: Detalhes da Campanha</h2>
        
        <!-- Template -->
        <div class="cm-form-group">
            <label>📄 Template da Mensagem</label>
            <select id="template-select" class="cm-select">
                <option value="">-- Escolha um template --</option>
                <?php if (!empty($message_templates)): ?>
                    <?php foreach ($message_templates as $template): ?>
                        <option value="<?php echo esc_attr($template->ID); ?>">
                            <?php echo esc_html($template->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="">Nenhum template disponível</option>
                <?php endif; ?>
            </select>
        </div>

        <!-- Provedores -->
        <div class="cm-form-group">
            <label>🌐 Provedores</label>
            
            <div style="margin-bottom:15px;">
                <label>
                    <input type="radio" name="distribution_mode" value="split" checked>
                    Dividir entre provedores
                </label>
                <label style="margin-left:20px;">
                    <input type="radio" name="distribution_mode" value="all">
                    Enviar para todos
                </label>
            </div>

            <div class="cm-providers-grid">
                <?php foreach ($providers as $code => $name): ?>
                    <label class="cm-provider-card">
                        <input type="checkbox" class="provider-checkbox" value="<?php echo esc_attr($code); ?>">
                        <strong><?php echo esc_html($name); ?></strong>
                        <input type="number" class="provider-percent" 
                               data-provider="<?php echo esc_attr($code); ?>" 
                               value="20" min="0" max="100">
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Limite -->
        <div class="cm-form-group">
            <label>📊 Limite de Registros (opcional)</label>
            <input type="number" id="record-limit" class="cm-input" placeholder="Ex: 1000">
        </div>

        <!-- Tipo de Agendamento -->
        <div class="cm-form-group">
            <label>📅 Tipo de Agendamento</label>
            <label>
                <input type="radio" name="scheduling_mode" value="immediate" checked>
                Envio Imediato
            </label>
            <label style="margin-left:20px;">
                <input type="radio" name="scheduling_mode" value="recurring">
                Salvar como Template
            </label>
        </div>

        <div id="recurring-options" style="display:none; margin-top:15px;">
            <input type="text" id="campaign-name" class="cm-input" 
                   placeholder="Nome do template">
        </div>

        <!-- Botão -->
        <button id="schedule-campaign-btn" class="cm-btn cm-btn-primary" disabled>
            🚀 Agendar Campanha
        </button>

        <div id="schedule-message"></div>
    </div>
</div>

<style>
.cm-wrap { max-width: 1200px; margin: 20px; }
.cm-header { 
    background: linear-gradient(135deg, #6366f1 0%, #7c3aed 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 20px;
}
.cm-header h1 { margin: 0; font-size: 28px; }
.cm-header p { margin: 5px 0 0 0; opacity: 0.9; }

.cm-card { 
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}
.cm-card h2 { 
    margin: 0 0 20px 0;
    font-size: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.cm-form-group { margin-bottom: 20px; }
.cm-form-group label { 
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
}

.cm-select,
.cm-input {
    width: 100%;
    padding: 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 15px;
}
.cm-select:focus,
.cm-input:focus {
    outline: none;
    border-color: #6366f1;
}

.cm-audience-badge {
    background: #dbeafe;
    padding: 15px 20px;
    border-radius: 8px;
    margin: 20px 0;
    border-left: 4px solid #3b82f6;
}
.cm-audience-badge strong { color: #1e40af; }
#audience-count { 
    font-size: 24px;
    font-weight: 800;
    color: #6366f1;
}

.cm-providers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
}
.cm-provider-card {
    background: #f9fafb;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 15px;
    cursor: pointer;
}
.cm-provider-card:hover {
    border-color: #6366f1;
}
.provider-percent {
    width: 100%;
    margin-top: 8px;
    padding: 8px;
    border: 2px solid #e5e7eb;
    border-radius: 6px;
    text-align: center;
}

.cm-btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.cm-btn-primary {
    background: #6366f1;
    color: white;
}
.cm-btn-primary:hover:not(:disabled) {
    background: #4f46e5;
}
.cm-btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

#schedule-message {
    margin-top: 15px;
    padding: 12px;
    border-radius: 8px;
    display: none;
}
#schedule-message.success {
    background: #d1fae5;
    border: 2px solid #10b981;
    color: #065f46;
    display: block;
}
#schedule-message.error {
    background: #fee2e2;
    border: 2px solid #ef4444;
    color: #991b1b;
    display: block;
}

.cm-checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 8px;
    max-height: 300px;
    overflow-y: auto;
    padding: 10px;
    background: #f9fafb;
    border-radius: 8px;
}
.cm-checkbox-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 6px;
    cursor: pointer;
}
.cm-checkbox-item:hover {
    border-color: #6366f1;
}

.cm-baits-info {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #f59e0b;
    display: flex;
    align-items: center;
    gap: 15px;
}

.cm-baits-icon {
    font-size: 32px;
    line-height: 1;
}

.cm-baits-info strong {
    color: #92400e;
    font-size: 16px;
    display: block;
}

.cm-baits-info p {
    margin: 5px 0 0 0;
    color: #78350f;
    font-size: 13px;
}
</style>