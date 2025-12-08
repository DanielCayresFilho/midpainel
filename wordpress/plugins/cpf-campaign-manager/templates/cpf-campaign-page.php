<?php
/**
 * Template: Página de Campanha por CPF
 */

if (!defined('ABSPATH')) exit;
?>

<div class="cpf-cm-wrap">
    <div class="cpf-cm-header">
        <div class="cpf-cm-header-icon">📋</div>
        <div>
            <h1>Campanha por CPF</h1>
            <p>Faça upload de um CSV com CPFs e crie campanhas direcionadas</p>
        </div>
    </div>

    <div id="cpf-cm-message" class="cpf-cm-message" style="display:none;"></div>

    <!-- Step 1: Selecionar Base -->
    <div class="cpf-cm-card">
        <h2>📊 Passo 1: Selecione a Base de Dados</h2>
        <p class="cpf-cm-description">Escolha primeiro qual base (VW_BASE...) deseja consultar</p>
        <div class="cpf-cm-form-group">
            <label>Base de Dados</label>
            <select id="table-select" class="cpf-cm-select">
                <option value="">-- Escolha uma base --</option>
                <?php foreach ($tables as $table): ?>
                    <option value="<?php echo esc_attr($table[0]); ?>">
                        <?php echo esc_html($table[0]); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Step 2: Upload CSV -->
    <div class="cpf-cm-card" id="step-2" style="display:none;">
        <h2>📁 Passo 2: Upload do Arquivo CSV</h2>
        <p class="cpf-cm-description">Envie um CSV contendo um CPF ou telefone por linha</p>

        <div class="cpf-cm-form-group">
            <label>Tipo de cruzamento</label>
            <select id="matching-field" class="cpf-cm-select">
                <option value="">-- Escolha o tipo de dado --</option>
                <option value="cpf">CPF (11 dígitos)</option>
                <option value="telefone">Telefone (DDD + número)</option>
            </select>
            <p class="cpf-cm-upload-hint">
                Informe se o arquivo está com CPFs ou telefones. Vamos cruzar com a base usando o tipo escolhido.
            </p>
        </div>
        
        <div class="cpf-cm-upload-area" id="upload-area">
            <div class="cpf-cm-upload-icon">📄</div>
            <p><strong>Clique para selecionar</strong> ou arraste o arquivo aqui</p>
            <p class="cpf-cm-upload-hint">Apenas arquivos .csv (máx 10MB)</p>
            <input type="file" id="csv-file-input" accept=".csv" style="display:none;">
        </div>

        <div id="upload-preview" style="display:none;">
            <div class="cpf-cm-preview-box">
                <h3>✅ Arquivo carregado</h3>
                <p><strong>Total de registros:</strong> <span id="cpf-count">0</span></p>
                <p><strong>Primeiros valores:</strong></p>
                <div id="cpf-preview-list"></div>
                <button id="clear-upload" class="cpf-cm-btn cpf-cm-btn-secondary">🗑️ Remover Arquivo</button>
            </div>
        </div>
    </div>

    <!-- Step 3: Filtros Adicionais (Opcional) -->
    <div class="cpf-cm-card" id="step-3" style="display:none;">
        <h2>🔍 Passo 3: Filtros Adicionais (Opcional)</h2>
        <p class="cpf-cm-description">Adicione filtros extras para refinar sua busca</p>
        
        <div id="filters-container">
            <p>⏳ Carregando filtros...</p>
        </div>
    </div>

    <!-- Step 4: Resultado e Download -->
    <div class="cpf-cm-card" id="step-4" style="display:none;">
        <h2>⬇️ Passo 4: Baixe os telefones encontrados</h2>
        
        <!-- Preview da contagem -->
        <div class="cpf-cm-count-box">
            <strong>👥 Registros encontrados:</strong>
            <span id="records-count">---</span>
            <p class="cpf-cm-upload-hint" style="margin-top:8px;">
                O arquivo baixado terá: nome, telefone, CPF e idcob_contrato.
            </p>
        </div>

        <button id="download-clean-file-btn" class="cpf-cm-btn cpf-cm-btn-primary" disabled>
            ⬇️ Baixar arquivo limpo
        </button>
    </div>
</div>

<style>
.cpf-cm-wrap {
    max-width: 1200px;
    margin: 20px auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.cpf-cm-header {
    background: linear-gradient(135deg, #6366f1 0%, #7c3aed 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.cpf-cm-header-icon {
    font-size: 48px;
}

.cpf-cm-header h1 {
    margin: 0;
    font-size: 28px;
}

.cpf-cm-header p {
    margin: 5px 0 0 0;
    opacity: 0.9;
}

.cpf-cm-card {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.cpf-cm-card h2 {
    margin: 0 0 15px 0;
    font-size: 20px;
    color: #111827;
}

.cpf-cm-description {
    color: #6b7280;
    margin: 0 0 20px 0;
}

.cpf-cm-upload-area {
    border: 3px dashed #d1d5db;
    border-radius: 12px;
    padding: 60px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.cpf-cm-upload-area:hover {
    border-color: #6366f1;
    background: #f9fafb;
}

.cpf-cm-upload-icon {
    font-size: 64px;
    margin-bottom: 15px;
}

.cpf-cm-upload-hint {
    font-size: 13px;
    color: #9ca3af;
}

.cpf-cm-preview-box {
    background: #dbeafe;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #3b82f6;
}

.cpf-cm-preview-box h3 {
    margin: 0 0 15px 0;
    color: #1e40af;
}

#cpf-preview-list {
    font-family: monospace;
    background: white;
    padding: 10px;
    border-radius: 4px;
    margin: 10px 0;
}

.cpf-cm-form-group {
    margin-bottom: 20px;
}

.cpf-cm-form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #111827;
}

.cpf-cm-select {
    width: 100%;
    padding: 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 15px;
}

.cpf-cm-select:focus {
    outline: none;
    border-color: #6366f1;
}

.cpf-cm-count-box {
    background: #dbeafe;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
}

.cpf-cm-count-box span {
    font-size: 24px;
    font-weight: 800;
    color: #6366f1;
}

.cpf-cm-providers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
}

.cpf-cm-provider-checkbox {
    background: #f9fafb;
    padding: 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}

.cpf-cm-provider-checkbox:hover {
    border-color: #6366f1;
}

.cpf-cm-btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.cpf-cm-btn-primary {
    background: #6366f1;
    color: white;
}

.cpf-cm-btn-primary:hover:not(:disabled) {
    background: #4f46e5;
}

.cpf-cm-btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.cpf-cm-btn-secondary {
    background: #f3f4f6;
    color: #374151;
}

.cpf-cm-btn-secondary:hover {
    background: #e5e7eb;
}

.cpf-cm-message {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: 500;
}

.cpf-cm-message.success {
    background: #d1fae5;
    color: #065f46;
    border: 2px solid #10b981;
}

.cpf-cm-message.error {
    background: #fee2e2;
    color: #991b1b;
    border: 2px solid #ef4444;
}

.cpf-cm-filter-group {
    margin-bottom: 20px;
    padding: 15px;
    background: #f9fafb;
    border-radius: 8px;
}

.cpf-cm-filter-group strong {
    display: block;
    margin-bottom: 10px;
    color: #111827;
}

.cpf-cm-checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 8px;
}

.cpf-cm-checkbox-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    cursor: pointer;
}

.cpf-cm-checkbox-item:hover {
    border-color: #6366f1;
}
</style>