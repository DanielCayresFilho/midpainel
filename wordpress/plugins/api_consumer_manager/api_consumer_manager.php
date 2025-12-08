<?php
/**
 * Plugin Name: API Consumer Manager V3
 * Description: Manages API credentials and settings for ALL consumer plugins (CDA, GOSAC, Noah, Salesforce, RCS).
 * Version: 3.0.0
 * Author: Daniel Cayres
 */

if (!defined('ABSPATH')) exit;

class Api_Consumer_Manager_V3 {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_add_acm_credential', [$this, 'handle_add_credential']);
        add_action('admin_post_delete_acm_credential', [$this, 'handle_delete_credential']);
        add_action('admin_post_save_static_credentials', [$this, 'handle_save_static']);
    }

    public function add_admin_menu() {
        add_menu_page('API Consumer Settings', 'API Manager', 'manage_options', 'api_consumer_manager', [$this, 'render_settings_page'], 'dashicons-rest-api', 20);
    }

    public function register_settings() {
        register_setting('api_consumer_settings_group', 'acm_master_api_key');
        register_setting('api_consumer_settings_group', 'acm_provider_credentials'); // All providers
        register_setting('api_consumer_settings_group', 'acm_static_credentials'); // Static configs
        register_setting('api_consumer_settings_group', 'ga_dashboard_password');
    }

    public function handle_add_credential() {
        check_admin_referer('acm_add_nonce');
        
        $credentials = get_option('acm_provider_credentials', []);
        if (!is_array($credentials)) {
            $credentials = [];
        }

        $provider = sanitize_key($_POST['provider']);
        $env_id = sanitize_text_field($_POST['env_id']);
        
        // Campos dinâmicos baseados no provider
        $credential_data = [];
        
        switch ($provider) {
            case 'salesforce':
                $credential_data = [
                    'operacao' => sanitize_text_field($_POST['operacao']),
                    'automation_id' => sanitize_text_field($_POST['automation_id'])
                ];
                break;
                
            case 'rcs':
                $credential_data = [
                    'broker_code' => sanitize_text_field($_POST['broker_code']),
                    'customer_code' => sanitize_text_field($_POST['customer_code'])
                ];
                break;
                
            default: // noah, gosac, cda
                $credential_data = [
                    'url' => esc_url_raw($_POST['url']),
                    'token' => sanitize_text_field($_POST['token'])
                ];
                break;
        }

        if (!empty($provider) && !empty($env_id) && !empty($credential_data)) {
            if (!isset($credentials[$provider]) || !is_array($credentials[$provider])) {
                $credentials[$provider] = [];
            }
            $credentials[$provider][$env_id] = $credential_data;
            update_option('acm_provider_credentials', $credentials);
        }
        
        wp_redirect(admin_url('admin.php?page=api_consumer_manager&status=added'));
        exit;
    }

    public function handle_delete_credential() {
        check_admin_referer('acm_delete_nonce');
        
        $credentials = get_option('acm_provider_credentials', []);
        if (!is_array($credentials)) {
            $credentials = [];
        }

        $provider = sanitize_key($_GET['provider']);
        $env_id = sanitize_text_field($_GET['env_id']);

        if (isset($credentials[$provider][$env_id])) {
            unset($credentials[$provider][$env_id]);
            update_option('acm_provider_credentials', $credentials);
        }

        wp_redirect(admin_url('admin.php?page=api_consumer_manager&status=deleted'));
        exit;
    }

    public function handle_save_static() {
        check_admin_referer('acm_static_nonce');
        
        $static_credentials = [
            // CDA
            'cda_api_url' => esc_url_raw($_POST['cda_api_url']),
            'cda_api_key' => sanitize_text_field($_POST['cda_api_key']),
            
            // Salesforce
            'sf_client_id' => sanitize_text_field($_POST['sf_client_id']),
            'sf_client_secret' => sanitize_text_field($_POST['sf_client_secret']),
            'sf_username' => sanitize_text_field($_POST['sf_username']),
            'sf_password' => sanitize_text_field($_POST['sf_password']),
            'sf_token_url' => esc_url_raw($_POST['sf_token_url']),
            'sf_api_url' => esc_url_raw($_POST['sf_api_url']),
            
            // Marketing Cloud
            'mkc_client_id' => sanitize_text_field($_POST['mkc_client_id']),
            'mkc_client_secret' => sanitize_text_field($_POST['mkc_client_secret']),
            'mkc_token_url' => esc_url_raw($_POST['mkc_token_url']),
            'mkc_api_url' => esc_url_raw($_POST['mkc_api_url']),
            
            // RCS Ótima
            'rcs_base_url' => esc_url_raw($_POST['rcs_base_url']),
            'rcs_token' => sanitize_text_field($_POST['rcs_token'])
        ];
        
        update_option('acm_static_credentials', $static_credentials);
        
        wp_redirect(admin_url('admin.php?page=api_consumer_manager&status=static_saved'));
        exit;
    }
    
    public function render_settings_page() {
        ?>
<div class="wrap">
    <h1>🔧 API Consumer Settings</h1>
    <p>Centralized management for all API consumer plugins.</p>

    <?php if (isset($_GET['status'])): ?>
        <?php if ($_GET['status'] == 'added'): ?>
            <div id="message" class="updated notice is-dismissible">
                <p>✅ Credential added successfully.</p>
            </div>
        <?php elseif ($_GET['status'] == 'deleted'): ?>
            <div id="message" class="updated notice is-dismissible">
                <p>🗑️ Credential deleted.</p>
            </div>
        <?php elseif ($_GET['status'] == 'static_saved'): ?>
            <div id="message" class="updated notice is-dismissible">
                <p>💾 Static credentials saved successfully.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Master API Key -->
    <div class="card">
        <h2>🔑 Master API Key</h2>
        <form action="options.php" method="post">
            <?php settings_fields('api_consumer_settings_group'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="acm_master_api_key">Master API Key</label></th>
                    <td>
                        <input type="text" name="acm_master_api_key" id="acm_master_api_key"
                               value="<?php echo esc_attr(get_option('acm_master_api_key')); ?>" 
                               class="regular-text" required />
                        <p class="description">Secret key required to access all endpoints.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Master Key'); ?>
        </form>
    </div>

    <!-- Static Credentials -->
    <div class="card">
        <h2>🏢 Static Provider Credentials</h2>
        <form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
            <input type="hidden" name="action" value="save_static_credentials">
            <?php wp_nonce_field('acm_static_nonce'); ?>
            
            <?php 
            $static = get_option('acm_static_credentials', []); 
            ?>
            
            <h3>CDA Provider</h3>
            <table class="form-table">
                <tr>
                    <th><label for="cda_api_url">CDA API URL</label></th>
                    <td><input type="url" name="cda_api_url" id="cda_api_url" 
                               value="<?php echo esc_attr($static['cda_api_url'] ?? ''); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="cda_api_key">CDA API Key</label></th>
                    <td><input type="text" name="cda_api_key" id="cda_api_key" 
                               value="<?php echo esc_attr($static['cda_api_key'] ?? ''); ?>" class="regular-text" /></td>
                </tr>
            </table>

            <h3>Salesforce</h3>
            <table class="form-table">
                <tr>
                    <th><label for="sf_client_id">Client ID</label></th>
                    <td><input type="text" name="sf_client_id" id="sf_client_id" 
                               value="<?php echo esc_attr($static['sf_client_id'] ?? ''); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="sf_client_secret">Client Secret</label></th>
                    <td><input type="password" name="sf_client_secret" id="sf_client_secret" 
                               value="<?php echo esc_attr($static['sf_client_secret'] ?? ''); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="sf_username">Username</label></th>
                    <td><input type="text" name="sf_username" id="sf_username" 
                               value="<?php echo esc_attr($static['sf_username'] ?? ''); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="sf_password">Password + Security Token</label></th>
                    <td><input type="password" name="sf_password" id="sf_password" 
                               value="<?php echo esc_attr($static['sf_password'] ?? ''); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="sf_token_url">Token URL</label></th>
                    <td><input type="url" name="sf_token_url" id="sf_token_url" 
                               value="<?php echo esc_attr($static['sf_token_url'] ?? 'https://concilig.my.salesforce.com/services/oauth2/token'); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="sf_api_url">API URL</label></th>
                    <td><input type="url" name="sf_api_url" id="sf_api_url" 
                               value="<?php echo esc_attr($static['sf_api_url'] ?? 'https://concilig.my.salesforce.com/services/data/v59.0/composite/sobjects'); ?>" class="regular-text" /></td>
                </tr>
            </table>

            <h3>Marketing Cloud</h3>
            <table class="form-table">
                <tr>
                    <th><label for="mkc_client_id">Client ID</label></th>
                    <td><input type="text" name="mkc_client_id" id="mkc_client_id" 
                               value="<?php echo esc_attr($static['mkc_client_id'] ?? ''); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="mkc_client_secret">Client Secret</label></th>
                    <td><input type="password" name="mkc_client_secret" id="mkc_client_secret" 
                               value="<?php echo esc_attr($static['mkc_client_secret'] ?? ''); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="mkc_token_url">Token URL</label></th>
                    <td><input type="url" name="mkc_token_url" id="mkc_token_url" 
                               value="<?php echo esc_attr($static['mkc_token_url'] ?? 'https://mchdb47kwgw19dh5mmnsw0fvhv2m.auth.marketingcloudapis.com/v2/token'); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="mkc_api_url">API Base URL</label></th>
                    <td><input type="url" name="mkc_api_url" id="mkc_api_url" 
                               value="<?php echo esc_attr($static['mkc_api_url'] ?? 'https://mchdb47kwgw19dh5mmnsw0fvhv2m.rest.marketingcloudapis.com/automation/v1/automations'); ?>" class="regular-text" /></td>
                </tr>
            </table>

            <h3>RCS Ótima</h3>
            <table class="form-table">
                <tr>
                    <th><label for="rcs_base_url">Base URL</label></th>
                    <td><input type="url" name="rcs_base_url" id="rcs_base_url" 
                               value="<?php echo esc_attr($static['rcs_base_url'] ?? 'https://services.otima.digital/'); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="rcs_token">Authorization Token</label></th>
                    <td><input type="text" name="rcs_token" id="rcs_token" 
                               value="<?php echo esc_attr($static['rcs_token'] ?? ''); ?>" class="regular-text" /></td>
                </tr>
            </table>
            
             <h3>Dashboard Security</h3>
            <table class="form-table">
                <tr>
                    <th><label for="dashboard_password">Dashboard Password</label></th>
                    <td>
                        <input type="password" name="dashboard_password" id="dashboard_password" 
                               value="<?php echo esc_attr($static['dashboard_password'] ?? 'admin123'); ?>" 
                               class="regular-text" />
                        <p class="description">Password to access the Status Dashboard (get_agendamentos)</p>
                    </td>
                </tr>
            </table>

            <?php submit_button('💾 Save Static Credentials', 'primary', 'save_static'); ?>
        </form>
    </div>

    <!-- Dynamic Provider Credentials -->
    <div class="card">
        <h2>🔗 Dynamic Provider Credentials</h2>
        <p>Configure environment-specific credentials for each provider.</p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 12%;">Provider</th>
                    <th style="width: 12%;">Environment ID</th>
                    <th style="width: 40%;">Configuration</th>
                    <th style="width: 20%;">Preview</th>
                    <th style="width: 16%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $credentials = get_option('acm_provider_credentials', []);
                if (!is_array($credentials)) {
                    $credentials = [];
                }
                $has_credentials = false;
                
                foreach (['gosac', 'noah', 'cda', 'salesforce', 'rcs'] as $provider_name) {
                    if (isset($credentials[$provider_name]) && is_array($credentials[$provider_name])) {
                        foreach ($credentials[$provider_name] as $env_id => $data) {
                            if (is_array($data) && !empty($data)) {
                                $has_credentials = true;
                                $delete_url = wp_nonce_url(admin_url('admin-post.php?action=delete_acm_credential&provider=' . $provider_name . '&env_id=' . $env_id), 'acm_delete_nonce');
                                
                                echo '<tr>';
                                echo '<td><strong>' . strtoupper($provider_name) . '</strong></td>';
                                echo '<td><code>' . esc_html($env_id) . '</code></td>';
                                
                                // Configuration column
                                echo '<td>';
                                if ($provider_name === 'salesforce') {
                                    echo '<strong>Operação:</strong> ' . esc_html($data['operacao'] ?? '') . '<br>';
                                    echo '<strong>Automation ID:</strong> ' . esc_html(substr($data['automation_id'] ?? '', 0, 30)) . '...';
                                } elseif ($provider_name === 'rcs') {
                                    echo '<strong>Broker:</strong> ' . esc_html($data['broker_code'] ?? '') . '<br>';
                                    echo '<strong>Customer:</strong> ' . esc_html($data['customer_code'] ?? '');
                                } else {
                                    echo '<strong>URL:</strong> ' . esc_html($data['url'] ?? '') . '<br>';
                                    echo '<strong>Token:</strong> ' . esc_html(substr($data['token'] ?? '', 0, 20)) . '...';
                                }
                                echo '</td>';
                                
                                // Preview column
                                echo '<td>';
                                if ($provider_name === 'salesforce') {
                                    echo '<code>' . esc_html(substr($data['automation_id'] ?? '', 0, 8)) . '...</code>';
                                } elseif ($provider_name === 'rcs') {
                                    echo '<code>' . esc_html($data['customer_code'] ?? '') . '</code>';
                                } else {
                                    echo '<code>' . esc_html(substr($data['token'] ?? '', 0, 8)) . '...</code>';
                                }
                                echo '</td>';
                                
                                echo '<td><a href="' . esc_url($delete_url) . '" style="color: #a00;" onclick="return confirm(\'Delete this credential?\')">🗑️ Delete</a></td>';
                                echo '</tr>';
                            }
                        }
                    }
                }
                if (!$has_credentials) {
                    echo '<tr><td colspan="5" style="text-align:center;color:#666;font-style:italic;">No dynamic credentials configured yet.</td></tr>';
                }
                ?>
            </tbody>
        </table>

        <h3 style="margin-top: 30px;">➕ Add New Credential</h3>
        <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" id="credential-form">
            <input type="hidden" name="action" value="add_acm_credential">
            <?php wp_nonce_field('acm_add_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="acm_provider">Provider</label></th>
                    <td>
                        <select name="provider" id="acm_provider" required onchange="toggleCredentialFields()">
                            <option value="">Select a Provider</option>
                            <option value="gosac">GOSAC</option>
                            <option value="noah">Noah</option>
                            <option value="cda">CDA</option>
                            <option value="salesforce">Salesforce</option>
                            <option value="rcs">RCS Ótima</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="acm_env_id">Environment ID</label></th>
                    <td>
                        <input type="text" name="env_id" id="acm_env_id" class="regular-text" required
                               placeholder="e.g., 3641" />
                        <p class="description">The idgis_ambiente value used in your campaigns</p>
                    </td>
                </tr>
            </table>

            <!-- Fields for URL/Token based providers -->
            <div id="url-token-fields" style="display:none;">
                <table class="form-table">
                    <tr>
                        <th><label for="acm_url">API URL</label></th>
                        <td><input type="url" name="url" id="acm_url" class="regular-text"
                                   placeholder="https://provider.api.com/endpoint" /></td>
                    </tr>
                    <tr>
                        <th><label for="acm_token">Token/Key</label></th>
                        <td><input type="text" name="token" id="acm_token" class="regular-text" /></td>
                    </tr>
                </table>
            </div>

            <!-- Fields for Salesforce -->
            <div id="salesforce-fields" style="display:none;">
                <table class="form-table">
                    <tr>
                        <th><label for="acm_operacao">Operação Name</label></th>
                        <td><input type="text" name="operacao" id="acm_operacao" class="regular-text"
                                   placeholder="BV_VEIC_ADM_Tradicional" /></td>
                    </tr>
                    <tr>
                        <th><label for="acm_automation_id">Automation ID</label></th>
                        <td><input type="text" name="automation_id" id="acm_automation_id" class="regular-text"
                                   placeholder="0e309929-51ae-4e2a-b8d1-ee17c055f42e" /></td>
                    </tr>
                </table>
            </div>

            <!-- Fields for RCS -->
            <div id="rcs-fields" style="display:none;">
                <table class="form-table">
                    <tr>
                        <th><label for="acm_broker_code">Broker Code</label></th>
                        <td><input type="text" name="broker_code" id="acm_broker_code" class="regular-text"
                                   placeholder="rcs_concilig_single" /></td>
                    </tr>
                    <tr>
                        <th><label for="acm_customer_code">Customer Code</label></th>
                        <td><input type="text" name="customer_code" id="acm_customer_code" class="regular-text"
                                   placeholder="01" /></td>
                    </tr>
                </table>
            </div>

            <?php submit_button('➕ Add Credential'); ?>
        </form>
    </div>
</div>

<style>
.card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    margin: 20px 0;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.card h2 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}
.form-table th {
    width: 200px;
}
.wp-list-table th {
    font-weight: 600;
}
</style>

<script>
function toggleCredentialFields() {
    const provider = document.getElementById('acm_provider').value;
    
    // Hide all field groups
    document.getElementById('url-token-fields').style.display = 'none';
    document.getElementById('salesforce-fields').style.display = 'none';
    document.getElementById('rcs-fields').style.display = 'none';
    
    // Show relevant fields
    if (provider === 'salesforce') {
        document.getElementById('salesforce-fields').style.display = 'block';
    } else if (provider === 'rcs') {
        document.getElementById('rcs-fields').style.display = 'block';
    } else if (provider && provider !== '') {
        document.getElementById('url-token-fields').style.display = 'block';
    }
}
</script>

<?php
    }

    /**
     * Helper function to get provider credentials
     */
    public static function get_provider_credentials($provider, $env_id = null) {
        $credentials = get_option('acm_provider_credentials', []);
        
        if ($env_id) {
            return $credentials[$provider][$env_id] ?? null;
        }
        
        return $credentials[$provider] ?? [];
    }

    /**
     * Helper function to get static credentials
     */
    public static function get_static_credentials($key = null) {
        $static = get_option('acm_static_credentials', []);
        
        if ($key) {
            return $static[$key] ?? null;
        }
        
        return $static;
    }

    /**
     * Helper function to check API key
     */
    public static function check_api_key(\WP_REST_Request $request) {
        $master_key = get_option('acm_master_api_key');
        if (empty($master_key)) {
            return new WP_Error('no_master_key', 'Master API Key não configurada.', ['status' => 503]);
        }

        $provided_key = $request->get_header('X-API-KEY');
        if ($provided_key !== $master_key) {
            return new WP_Error('invalid_key', 'API Key inválida.', ['status' => 401]);
        }
        
        return true;
    }
}

new Api_Consumer_Manager_V3();