<?php
/**
 * Plugin Name: ArchiaAI
 * Description: Make SEO Rich Blogs using ai! ArchiaStudio.com
 * Version: 1.5
 * Author: ArchiaStudio
 * Text Domain: archia-ai-blog
 */

if (!defined('ABSPATH')) exit;

// --------- Defaults & constants ----------
define('ARCHIA_OPTION_GROUP', 'archia_ai_blog_options');
define('ARCHIA_CRON_HOOK', 'archia_ai_blog_scheduled_generate');
define('ARCHIA_MODELS_URL', 'https://raw.githubusercontent.com/archiastudio/cloud/refs/heads/main/freeAgents.json');

// Register options with default values
function archia_register_options() {
    register_setting(ARCHIA_OPTION_GROUP, 'archia_options', 'archia_sanitize_options');

    add_option('archia_options', array(
        'openrouter_keys' => '',
        'unsplash_key' => '',
        'pexels_key' => '',
        'blog_api_key' => '',
        'company_type' => 'web agency',
        'company_name' => 'ArchiaStudio',
        'company_domain' => 'archiastudio.com',
        'company_email' => 'hello@archiastudio.com',
        'auto_generate' => 'yes',
        'auto_interval' => 'hourly', // hourly, twicedaily, daily
        'posts_per_run' => 1,
        'author_id' => get_current_user_id(),
        'default_status' => 'publish' // or 'draft'
    ));
}
add_action('admin_init', 'archia_register_options');

function archia_sanitize_options($in) {
    $out = array();
    $allowed_categories = array(
    'nature','office','people','technology','minimal','abstract','aerial','blurred','bokeh',
    'gradient','monochrome','vintage','white','black','blue','red','green','yellow',
    'cityscape','workspace','food','travel','textures','industry','indoor','outdoor','studio',
    'finance','medical','season','holiday','event','sport','science','legal','estate',
    'restaurant','retail','wellness','agriculture','construction','craft','cosmetic',
    'automotive','gaming','education'
);

$out['image_category'] = in_array($in['image_category'] ?? '', $allowed_categories)
    ? $in['image_category']
    : 'technology';
    $out['openrouter_keys'] = sanitize_textarea_field($in['openrouter_keys'] ?? '');
    $out['blog_api_key'] = sanitize_text_field($in['blog_api_key'] ?? '');
    $out['company_type'] = sanitize_text_field($in['company_type'] ?? 'web agency');
    $out['company_name'] = sanitize_text_field($in['company_name'] ?? 'ArchiaStudio');
    $out['company_domain'] = sanitize_text_field($in['company_domain'] ?? 'archiastudio.com');
    $out['company_email'] = sanitize_email($in['company_email'] ?? 'hello@archiastudio.com');
    $out['auto_generate'] = ($in['auto_generate'] ?? 'no') === 'yes' ? 'yes' : 'no';
    $out['auto_interval'] = in_array($in['auto_interval'] ?? '', array('hourly','twicedaily','daily')) ? $in['auto_interval'] : 'hourly';
    $out['posts_per_run'] = max(1, intval($in['posts_per_run'] ?? 1));
    $out['author_id'] = intval($in['author_id'] ?? get_current_user_id());
    $out['default_status'] = in_array($in['default_status'] ?? 'publish', array('publish','draft')) ? $in['default_status'] : 'publish';

    // Update cron schedule immediately after options are saved
    if (get_option('archia_options') !== false) {
        archia_update_schedule($out);
    }
    
    return $out;
}

// Admin menu
function archia_admin_menu() {
    add_menu_page('Archia AI Blog', 'Archia AI Blog', 'manage_options', 'archia-ai-blog', 'archia_admin_page', 'dashicons-admin-generic', 80);
}
add_action('admin_menu', 'archia_admin_menu');

// Load Admin Assets (JS, CSS, Toastify)
function archia_load_admin_assets($hook) {
    if ($hook !== 'toplevel_page_archia-ai-blog') {
        return;
    }

    // Toastify Assets
    wp_enqueue_style('toastify-css', 'https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css');
    wp_enqueue_script('toastify-js', 'https://cdn.jsdelivr.net/npm/toastify-js', array(), '1.11.0', true);

    // Custom Admin Script
    wp_enqueue_script('archia-admin-js', plugins_url('archia-admin.js', __FILE__), array('jquery', 'toastify-js'), '1.0', true);
    
    // Pass data to the script
    $opts = get_option('archia_options', array());
    wp_localize_script('archia-admin-js', 'archia_data', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('archia_manual_generate_ajax'),
        'success_sound' => 'https://archiastudio.com/warehouse/data/sounds/success.mp3',
        'error_sound' => 'https://archiastudio.com/warehouse/data/sounds/error.mp3',
        'blog_api_key' => $opts['blog_api_key'] ?? '',
    ));

    // Custom CSS for the progress bar
    ?>
    <style>
/* Define CSS Variables for easy color adjustments */


        .healer{
            --dot-bg: rgb(255, 255, 255);
            --dot-color: rgba(0, 0, 0, 0.265);
            --dot-size: 2px;
            --dot-space: 22px;
            background:
                linear-gradient(90deg, var(--dot-bg) calc(var(--dot-space) - var(--dot-size)), transparent 1%) center / var(--dot-space) var(--dot-space),
                linear-gradient(var(--dot-bg) calc(var(--dot-space) - var(--dot-size)), transparent 1%) center / var(--dot-space) var(--dot-space),
                var(--dot-color);
            background-position-y: 0%;
            animation: shimmer-y 100s infinite linear;
            /* Added padding for responsiveness */
            transition: all 0.5s ease-in;
            border-radius:15px;
        }

        @keyframes shimmer-y {
            to {
                background-position-y: 100%;
            }
        }


:root {
    --archia-primary-color: #0073aa; /* Standard WordPress Blue */
    --archia-secondary-color: #2c3338;
    --archia-bg-light: #ffffff;
    --archia-bg-gray: #f9f9f9;
    --archia-border-light: #e5e5e5;
    --archia-focus-shadow: 0 0 0 1px var(--archia-primary-color);
}

/* --- 1. General Container and Typography --- */
.wrap {
    max-width: 1000px;
    margin-right: auto;
    margin-left: auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
}

.wrap h2 {
    color: var(--archia-secondary-color);
    font-size: 1.8em;
    font-weight: 600;
    margin-top: 40px;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--archia-border-light);
}

/* Logo Styling */
.wrap > img[src*="archiaai.png"] {
    display: block;
    margin: 0 0 20px 0;
    max-width: 200px;
    height: auto;
    opacity: 0.9;
}

/* --- 2. API Keys & Settings Grouping (Visual Blocks) --- */

/* Style the form table wrapper to look like distinct settings cards */
.form-table {
    width: 100%;
    border-collapse: separate; /* Allows for rounded corners and better borders */
    border-spacing: 0;
    margin-bottom: 30px;
    background: var(--archia-bg-light);
    border: 1px solid var(--archia-border-light);
    border-radius: 6px;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.05);
}

.form-table tr {
    transition: background-color 0.1s ease;
}

/* Subtle background change on hover for better user guidance */
.form-table tr:hover {
    background-color: var(--archia-bg-gray);
}

.form-table tr:not(:last-child) {
    border-bottom: 1px solid var(--archia-border-light);
}

.form-table th, 
.form-table td {
    padding: 18px 20px;
    vertical-align: middle;
}

.form-table th {
    width: 35%; 
    font-weight: 500;
    color: var(--archia-secondary-color);
    text-align: left;
}

/* --- 3. Input, Select, and Textarea Fields --- */

input[type="text"],
input[type="number"],
textarea,
select {
    padding: 8px 12px;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    background-color: var(--archia-bg-light);
    transition: border-color 0.2s, box-shadow 0.2s;
    line-height: 1.5;
}

input[type="text"]:focus,
input[type="number"]:focus,
textarea:focus,
select:focus {
    border-color: var(--archia-primary-color);
    box-shadow: var(--archia-focus-shadow);
    outline: none;
}

/* Sizing consistency for text fields */
.form-table input[type="text"][style*="width:60%"],
.form-table input[type="number"][style*="width:60%"],
.form-table select[name*="archia_options"] {
    max-width: 60%;
    width: 100%; 
    box-sizing: border-box;
}

.form-table textarea {
    min-height: 100px;
    width: 100%;
    box-sizing: border-box;
    font-family: monospace; /* Better for API keys */
}

/* Description/Helper Text */
p.description {
    font-size: 0.85em;
    color: #666;
    margin-top: 8px;
    line-height: 1.5;
    font-style: normal;
}

/* Emphasis on required key configuration */
p.description strong {
    color: #d94a37; /* A subtle warning red */
}

/* Disabled Input */
.form-table input[disabled] {
    background-color: #f0f0f0;
    cursor: not-allowed;
    color: #888;
}


/* --- 4. Manual Controls Section (More prominent) --- */

#archia-manual-generate-form {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
}

#archia-generate-now-btn {
    /* Larger and more inviting button */
    height: 40px;
    padding: 0 20px;
    font-size: 1em;
    font-weight: 600;
}

/* Progress Bar Styling (Modern Flat Look) */
.archia-progress-bar-container {
    background-color: #eee;
    border-radius: 4px;
    height: 20px;
    width: 100%;
    margin-top: 10px;
    overflow: hidden;
}

#archia-progress-bar {
    height: 100%;
    background-color: var(--archia-primary-color); 
    text-align: center;
    line-height: 20px;
    color: var(--archia-bg-light);
    width: 0%; 
    transition: width 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94); /* Smoother transition */
    font-size: 0.85em;
    font-weight: bold;
}

/* Result Output */
#archia-result-output {
    margin-top: 20px;
    padding: 15px;
    background-color: var(--archia-bg-gray);
    border: 1px solid var(--archia-border-light);
    border-left: 5px solid var(--archia-primary-color); /* Highlight left border */
    border-radius: 4px;
    min-height: 30px;
}
/* --- DARK MODE STYLES (Targeting .wrap.dark-mode) --- */

.wrap.dark-mode {
    /* Main Background */
    background-color: #1e1e1e; /* Dark background */
    color: #f0f0f0; /* Light text */
}

.wrap.dark-mode h2 {
    color: #e0e0e0;
    border-bottom-color: #333333;
}

/* Dark Mode Form Table (The "Card") */
.wrap.dark-mode .form-table {
    background: #252526;
    border-color: #333333;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.2);
}

.wrap.dark-mode .form-table tr:not(:last-child) {
    border-bottom-color: #333333;
}

.wrap.dark-mode .form-table tr:hover {
    background-color: #2c2c2c;
}

.wrap.dark-mode .form-table th {
    color: #f0f0f0;
}

/* Dark Mode Input Fields */
.wrap.dark-mode input[type="text"],
.wrap.dark-mode input[type="number"],
.wrap.dark-mode textarea,
.wrap.dark-mode select {
    background-color: #3e3e3e;
    border-color: #4f4f4f;
    color: #f0f0f0;
}

.wrap.dark-mode input[type="text"]:focus,
.wrap.dark-mode input[type="number"]:focus,
.wrap.dark-mode textarea:focus,
.wrap.dark-mode select:focus {
    border-color: #0099cc; /* A bright blue for dark mode focus */
    box-shadow: 0 0 0 1px #0099cc;
}

/* Disabled Input in Dark Mode */
.wrap.dark-mode .form-table input[disabled] {
    background-color: #333333;
    color: #a0a0a0;
}

/* Description/Helper Text */
.wrap.dark-mode p.description {
    color: #aaaaaa;
}

/* Result Output Box */
.wrap.dark-mode #archia-result-output {
    background-color: #2c2c2c;
    border-color: #4f4f4f;
    border-left-color: #0099cc; /* Use the dark mode primary color */
}

/* Progress Bar in Dark Mode */
.wrap.dark-mode .archia-progress-bar-container {
    background-color: #3e3e3e;
}

.wrap.dark-mode #archia-progress-bar {
    background-color: #0099cc; 
}
        .archia-progress-bar-container {
            width: 100%;
            background-color: #f3f3f3;
            border-radius: 5px;
            margin-top: 10px;
            display: none; /* Initially hidden */
        }
        .archia-progress-bar {
            width: 0%;
            height: 25px;
            background-color: #0073aa; /* WordPress Blue */
            text-align: center;
            line-height: 25px;
            color: white;
            border-radius: 5px;
            transition: width 0.4s ease;
        }
    </style>
    <?php
}
add_action('admin_enqueue_scripts', 'archia_load_admin_assets');

function archia_get_license_details() {
    $opts = get_option('archia_options', []);
    $key  = $opts['blog_api_key'] ?? '';

    if (!$key) return null;

    $cache_key = 'archia_license_details_' . md5($key);
    $cached = get_transient($cache_key);
    if ($cached !== false) return $cached;

    $res = wp_remote_get(
        'https://api.archiastudio.com/api/getdetails/' . rawurlencode($key),
        ['timeout' => 15]
    );

    if (is_wp_error($res)) return null;

    $code = wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) return null;

    $json = json_decode(wp_remote_retrieve_body($res), true);
    if (!is_array($json)) return null;

    // Cache for 6 hours
    set_transient($cache_key, $json, 6 * HOUR_IN_SECONDS);

    return $json;
}

function archia_admin_page() {
    if (!current_user_can('manage_options')) return;
    $opts = get_option('archia_options', array());
    $license = archia_get_license_details();
    ?>
    <div class="wrap healer">
        <img src="https://archiastudio.com/warehouse/data/images/archiaai.png" width="200px">
        <!-- LICENSE CARD -->
    <?php if ($license): ?>
        <div class="archia-license-card" style="
    display: flex;
    gap: 2rem;
    align-items: center;
    justify-content: left;
">
            <img src="<?php echo esc_url($license['image']); ?>" style="width:120px;border-radius: 100%;border: 5px solid #0000001c;" class="archia-license-img">
            <div>
                <h3><?php echo esc_html($license['name']); ?></h3>
                <p>
                    <strong>Plan:</strong>
                    <span class="archia-plan archia-plan-<?php echo esc_attr($license['plan']); ?>">
                        <?php echo esc_html(strtoupper($license['plan'])); ?>
                    </span>
                </p>
                <p><strong>Days Remaining:</strong> <?php echo intval($license['daysRemaining']); ?> days</p>
                <a href="<?php echo esc_url($license['website']); ?>" target="_blank">Visit Website</a>
            </div>
        </div>
    <?php else: ?>
        <div class="archia-license-card archia-license-invalid">
            License not found or invalid.
        </div>
    <?php endif; ?>
        <form method="post" action="options.php" style="
    padding: 1rem;
    background: white;
    border-radius: 15px;
    margin: 1rem 0px;
">
            <?php settings_fields(ARCHIA_OPTION_GROUP); ?>
            <?php do_settings_sections(ARCHIA_OPTION_GROUP); ?>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">OpenRouter API Keys (one per line)</th>
                    <td><textarea name="archia_options[openrouter_keys]" rows="3" cols="50"><?php echo esc_textarea($opts['openrouter_keys'] ?? ''); ?></textarea>
                    <p class="description">Add your OpenRouter keys (one per line). Keys will be rotated randomly. <strong>API keys should be configured here.</strong></p></td>
                </tr>

                <tr valign="top">
    <th scope="row">Featured Image Category</th>
    <td>
        <select name="archia_options[image_category]">
            <option value="nature">nature</option>
            <option value="office">office</option>
            <option value="people">people</option>
            <option value="technology">technology</option>
            <option value="minimal">minimal</option>
            <option value="abstract">abstract</option>
            <option value="aerial">aerial</option>
            <option value="blurred">blurred</option>
            <option value="bokeh">bokeh</option>
            <option value="gradient">gradient</option>
            <option value="monochrome">monochrome</option>
            <option value="vintage">vintage</option>
            <option value="white">white</option>
            <option value="black">black</option>
            <option value="blue">blue</option>
            <option value="red">red</option>
            <option value="green">green</option>
            <option value="yellow">yellow</option>
            <option value="cityscape">cityscape</option>
            <option value="workspace">workspace</option>
            <option value="food">food</option>
            <option value="travel">travel</option>
            <option value="textures">textures</option>
            <option value="industry">industry</option>
            <option value="indoor">indoor</option>
            <option value="outdoor">outdoor</option>
            <option value="studio">studio</option>
            <option value="finance">finance</option>
            <option value="medical">medical</option>
            <option value="season">season</option>
            <option value="holiday">holiday</option>
            <option value="event">event</option>
            <option value="sport">sport</option>
            <option value="science">science</option>
            <option value="legal">legal</option>
            <option value="estate">estate</option>
            <option value="restaurant">restaurant</option>
            <option value="retail">retail</option>
            <option value="wellness">wellness</option>
            <option value="agriculture">agriculture</option>
            <option value="construction">construction</option>
            <option value="craft">craft</option>
            <option value="cosmetic">cosmetic</option>
            <option value="automotive">automotive</option>
            <option value="gaming">gaming</option>
            <option value="education">education</option>
        </select>

        <script>
            document.querySelector(
                'select[name="archia_options[image_category]"]'
            ).value = "<?php echo esc_js($opts['image_category'] ?? 'technology'); ?>";
        </script>

        <p class="description">
            Used for featured images via <strong>static.photos</strong> (1200×630).
        </p>
    </td>
</tr>

                <tr valign="top">
                    <th scope="row">License / Blog API Key</th>
                    <td><input type="text" name="archia_options[blog_api_key]" value="<?php echo esc_attr($opts['blog_api_key'] ?? ''); ?>" style="width:60%">
                    <p class="description">Optional — plugin will attempt to verify license via the same endpoint if provided.</p></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Company Type</th>
                    <td><input type="text" name="archia_options[company_type]" value="<?php echo esc_attr($opts['company_type'] ?? 'web agency'); ?>" style="width:60%"><p class="description">Example: "web agency", "SaaS company", "app studio". This will be used in topic prompts.</p></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Company Name</th>
                    <td><input type="text" name="archia_options[company_name]" value="<?php echo esc_attr($opts['company_name'] ?? 'ArchiaStudio'); ?>" style="width:60%"><p class="description">Example: "ArchiaStudio", "Acme Corp". This will be used in topic prompts.</p></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Company Domain</th>
                    <td><input type="text" name="archia_options[company_domain]" value="<?php echo esc_attr($opts['company_domain'] ?? 'archiastudio.com'); ?>" style="width:60%"><p class="description">Example: "archiastudio.com" — used in CTA links inside generated posts.</p></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Company Email</th>
                    <td><input type="text" name="archia_options[company_email]" value="<?php echo esc_attr($opts['company_email'] ?? 'hello@archiastudio.com'); ?>" style="width:60%"><p class="description">Example: "hello@archiastudio.com" — used in CTA inside generated posts.</p></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Auto-generate?</th>
                    <td>
                        <label><input type="checkbox" name="archia_options[auto_generate]" value="yes" <?php checked($opts['auto_generate'] ?? 'yes','yes'); ?>> Enable scheduled auto-generation (WP-Cron)</label>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Schedule interval</th>
                    <td>
                        <select name="archia_options[auto_interval]">
                            <option value="hourly" <?php selected($opts['auto_interval'] ?? 'hourly','hourly'); ?>>Hourly (Gold minimum)</option>
                            <option value="twicedaily" <?php selected($opts['auto_interval'] ?? 'hourly','twicedaily'); ?>>Twice daily (Silver minimum)</option>
                            <option value="daily" <?php selected($opts['auto_interval'] ?? 'hourly','daily'); ?>>Daily</option>
                        </select>
                        <p class="description">The minimum interval is enforced by your license plan.</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Posts per run</th>
                    <td><input type="number" min="1" name="archia_options[posts_per_run]" value="<?php echo esc_attr($opts['posts_per_run'] ?? 1); ?>" style="width:80px" disabled></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Default post status</th>
                    <td>
                        <select name="archia_options[default_status]">
                            <option value="publish" <?php selected($opts['default_status'],'publish'); ?>>Publish</option>
                            <option value="draft" <?php selected($opts['default_status'],'draft'); ?>>Draft</option>
                        </select>
                    </td>
                </tr>
            </table>

            <?php submit_button('Save Settings'); ?>
        </form>

        <h2>Manual Controls</h2>
        <p>Click to generate a post now (runs same logic as scheduled job). Progress will be shown below.</p>
        <form id="archia-manual-generate-form">
            <?php wp_nonce_field('archia_manual_generate_ajax'); ?>
            <button type="submit" id="archia-generate-now-btn" class="button button-primary" style="
    width: 100%;">Generate Now</button>
        </form>
        
        <div class="archia-progress-bar-container">
            <div class="archia-progress-bar" id="archia-progress-bar">0%</div>
        </div>

        <div id="archia-result-output"></div>

        <img src="https://archiastudio.com/warehouse/data/images/archiaai.png" width="200px">
    </div>
    <?php
}

// Models fetching helper (remote freeAgents.json)
function archia_fetch_remote_models() {
    $cached = get_transient('archia_remote_models');
    if ($cached && is_array($cached)) return $cached;
    $res = wp_remote_get(ARCHIA_MODELS_URL, array('timeout'=>15));
    if (is_wp_error($res)) return null;
    $code = wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) return null;
    $body = wp_remote_retrieve_body($res);
    $json = json_decode($body,true);
    if (!is_array($json)) return null;
    $models = array();
    if (!empty($json['models']) && is_array($json['models'])) $models = $json['models'];
    else {
        foreach ($json as $v) { if (is_string($v)) $models[] = $v; }
    }
    if (empty($models)) return null;
    set_transient('archia_remote_models',$models,5*MINUTE_IN_SECONDS);
    return $models;
}

// ------- WP-Cron schedule handling --------

/**
 * Handles the logic for updating the WP-Cron schedule based on plugin options and license plan.
 */
function archia_update_schedule($opts) {
    // 1. Clear existing schedule
    wp_clear_scheduled_hook(ARCHIA_CRON_HOOK);

    // 2. Check if auto-generate is enabled
    if (!empty($opts['auto_generate']) && $opts['auto_generate'] === 'yes') {
        
        // 3. Determine the effective interval based on license plan
        $plan = 'none'; // Default to no cron
        $license = array('valid' => false);

        if (!empty($opts['blog_api_key'])) {
            $license = archia_verify_license($opts['blog_api_key']);
            if ($license['valid']) {
                $plan = strtolower($license['plan'] ?? 'none');
            }
        }
        
        $user_interval = $opts['auto_interval'] ?? 'hourly';
        $effective_interval = null;

        if ($plan === 'gold') {
            // Gold plan: Min interval is 'hourly'
            $effective_interval = $user_interval;
        } elseif ($plan === 'silver') {
            // Silver plan: Min interval is 'twicedaily' (6 hours)
            if ($user_interval === 'twicedaily' || $user_interval === 'daily') {
                $effective_interval = $user_interval;
            } else {
                $effective_interval = 'twicedaily'; // Default silver minimum
            }
        } else {
            // Other/no valid license/expired: No auto-post
            return; 
        }

        // 4. Schedule the new event if an effective interval was determined
        if ($effective_interval && !wp_next_scheduled(ARCHIA_CRON_HOOK)) {
            wp_schedule_event(time(), $effective_interval, ARCHIA_CRON_HOOK);
        }
    }
}


function archia_activate() {
    archia_register_schedules();
    $opts = get_option('archia_options');
    // Use the new update function on activation
    archia_update_schedule($opts); 
}
register_activation_hook(__FILE__, 'archia_activate');

function archia_deactivate() {
    wp_clear_scheduled_hook(ARCHIA_CRON_HOOK);
}
register_deactivation_hook(__FILE__, 'archia_deactivate');

function archia_register_schedules() {
    add_filter('cron_schedules', function($s) {
        $s['hourly'] = array('interval' => 3600, 'display' => 'Hourly');
        $s['twicedaily'] = array('interval' => 12*3600, 'display' => 'Twice Daily');
        $s['daily'] = array('interval' => 24*3600, 'display' => 'Daily');
        return $s;
    });
}
add_action('init', 'archia_register_schedules');

add_action(ARCHIA_CRON_HOOK, 'archia_cron_generate_handler');

// AJAX handler for manual generation
function archia_manual_generate_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permission denied.'));
    }
    if (!check_ajax_referer('archia_manual_generate_ajax', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Nonce verification failed.'));
    }

    // Run the core generation logic
    $results = archia_cron_generate_handler();

    if (empty($results)) {
        wp_send_json_error(array('message' => 'No posts were created or an unknown error occurred.'));
    }

    // Check if any error occurred during the run
    $all_success = true;
    foreach ($results as $res) {
        if (!($res['success'] ?? false)) {
            $all_success = false;
            break;
        }
    }

    if ($all_success) {
        wp_send_json_success(array('message' => 'Post(s) successfully generated!', 'results' => $results));
    } else {
        // Find the most relevant error message
        $first_error = 'Generation completed with errors.';
        foreach ($results as $res) {
            if ($res['error'] ?? false) {
                $first_error = $res['error'];
                break;
            }
        }
        wp_send_json_error(array('message' => $first_error, 'results' => $results));
    }
}
add_action('wp_ajax_archia_manual_generate', 'archia_manual_generate_ajax');


// ---------- Core generate handler (used by cron + manual + REST) ----------
function archia_cron_generate_handler() {
    $opts = get_option('archia_options', array());
    $results = array();

    // Validate license (optional)
    if (!empty($opts['blog_api_key'])) {
        $license = archia_verify_license($opts['blog_api_key']);
        if (!$license['valid']) {
            return array(array('error' => 'License invalid: ' . ($license['reason'] ?? 'unknown'), 'license' => $license));
        }
    }

    $posts_to_create = max(1, intval($opts['posts_per_run'] ?? 1));
    for ($i=0; $i<$posts_to_create; $i++) {
        // Generate topic
        $topic = archia_generate_topic();
        if (!$topic) {
            $results[] = array('error' => 'Failed to generate topic');
            continue;
        }

        $title = $topic['title'];
        $idea = $topic['idea'] ?? '';

        if (empty($idea)) {
            $idea = archia_generate_idea_for_title($title);
        }

        $keywords = archia_generate_keywords_for_title($title);

        $contentHtml = archia_generate_content_html($title, $idea);

        if (!$contentHtml) {
            $results[] = array('error' => 'LLM generation failed for ' . $title);
            continue;
        }

        $imageUrl = archia_get_static_photo($opts['image_category'] ?? 'technology');

        $post_id = archia_create_post_from_generated($title, $contentHtml, $keywords, $imageUrl, $opts);

        if (is_wp_error($post_id)) {
            $results[] = array('error' => $post_id->get_error_message());
        } else {
            $results[] = array('success' => true, 'post_id' => $post_id, 'title' => $title, 'url' => get_permalink($post_id));
            // optionally ping search engines to crawl new post
            archia_ping_search_engines(get_permalink($post_id));
        }
    }

    return $results;
}

// --------- REST API routes ----------
add_action('rest_api_init', function() {
    register_rest_route('archia/v1', '/generate', array(
        'methods' => 'POST',
        'callback' => 'archia_rest_generate',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));

    register_rest_route('archia/v1', '/topic', array(
        'methods' => 'GET',
        'callback' => 'archia_rest_topic',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
});

function archia_rest_generate($request) {
    $body = $request->get_json_params() ?? array();
    $title = sanitize_text_field($body['title'] ?? '');
    $idea = sanitize_text_field($body['idea'] ?? '');
    $imageUrl = esc_url_raw($body['imageUrl'] ?? '');

    $opts = get_option('archia_options', array());
    // License check
    if (!empty($opts['blog_api_key'])) {
        $license = archia_verify_license($opts['blog_api_key']);
        if (!$license['valid']) {
             return new WP_Error('license_error', 'License invalid: ' . ($license['reason'] ?? 'unknown'), array('status' => 403));
        }
    }


    if (!$title) {
        $topic = archia_generate_topic();
        if (!$topic) return new WP_Error('no_topic', 'Failed to generate topic', array('status' => 502));
        $title = $topic['title'];
        if (!$idea) $idea = $topic['idea'] ?? '';
    }

    if (!$idea) $idea = archia_generate_idea_for_title($title);

    $keywords = archia_generate_keywords_for_title($title);
    $contentHtml = archia_generate_content_html($title, $idea);
    if (!$contentHtml) return new WP_Error('llm_failed','LLM generation failed', array('status'=>502));

    $imageUrl = archia_get_static_photo($opts['image_category'] ?? 'technology');


    $post_id = archia_create_post_from_generated($title, $contentHtml, $keywords, $imageUrl, $opts);

    if (is_wp_error($post_id)) return $post_id;

    archia_ping_search_engines(get_permalink($post_id));

    return rest_ensure_response(array('success'=>true, 'post_id'=>$post_id, 'url'=>get_permalink($post_id)));
}

function archia_rest_topic() {
    $topic = archia_generate_topic();
    if (!$topic) return new WP_Error('no_topic','Topic generation failed', array('status'=>502));
    return rest_ensure_response($topic);
}

// ---------- LLM & helper implementations (OpenRouter-only) ----------

function archia_pick_random_from_lines($str) {
    $lines = preg_split("/\r\n|\n|\r/", trim($str));
    $out = array();
    foreach ($lines as $l) {
        $t = trim($l);
        if ($t) $out[] = $t;
    }
    if (empty($out)) return null;
    return $out[array_rand($out)];
}

function archia_get_openrouter_key() {
    $opts = get_option('archia_options', array());
    return archia_pick_random_from_lines($opts['openrouter_keys'] ?? '');
}

function archia_call_openrouter($prompt) {
    $key = archia_get_openrouter_key();
    if (!$key) return null;

    // Try several common models (OpenRouter will reject invalid ones)
    $models = archia_fetch_remote_models();

    // Use a default model list if fetching fails
    if (empty($models)) {
        $models = array('nousresearch/nous-hermes-2-mixtral-8x7b-dpo', 'openai/gpt-3.5-turbo-0125');
    }

    foreach ($models as $model) {
        $body = array(
            'model' => $model,
            'messages' => array(
                array('role'=>'system', 'content'=>'You are an expert SEO content writer. Return clean content in the requested format, no extra commentary.'),
                array('role'=>'user', 'content'=>$prompt)
            ),
            'max_tokens' => 3000,
        );
        $res = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
            'headers' => array('Authorization' => 'Bearer '.$key, 'Content-Type' => 'application/json'),
            'body' => wp_json_encode($body),
            'timeout' => 60
        ));
        if (is_wp_error($res)) continue;
        $code = wp_remote_retrieve_response_code($res);
        if ($code < 200 || $code >= 300) continue;
        $json = json_decode(wp_remote_retrieve_body($res), true);
        $content = $json['choices'][0]['message']['content'] ?? '';
        if (strlen(trim($content)) > 10) return trim($content);
    }
    return null;
}

function archia_run_llm($prompt) {
    // OpenRouter-only now
    $res = archia_call_openrouter($prompt);
    return $res;
}

// ---------- High-level generation helpers (use companyType/companyName/companyDomain/companyEmail) ----------
function archia_generate_topic() {
    $opts = get_option('archia_options', array());
    $year = date('Y');
    $companyType = $opts['company_type'] ?? 'web agency';
    $companyName = $opts['company_name'] ?? 'ArchiaStudio';

    $prompt = "Generate ONE SEO-optimized blog title and a one-line idea for a {$companyType} {$companyName} for year {$year} or ".($year+1).". Return either JSON: {\"title\":\"...\",\"idea\":\"...\"} OR plain text (title on first line, idea on second). No markdown, no bullets, no commentary.";
    $out = archia_run_llm($prompt);
    if (!$out) return null;

    // try JSON parse
    $parsed = json_decode($out, true);
    if (is_array($parsed) && !empty($parsed['title'])) {
        return array('title' => trim($parsed['title']), 'idea' => trim($parsed['idea'] ?? ''));
    }

    // fallback to lines
    $lines = preg_split("/\r\n|\n|\r/", trim($out));
    $lines = array_map('trim', $lines);
    $lines = array_values(array_filter($lines));
    $title = $lines[0] ?? trim($out);
    $idea = $lines[1] ?? '';
    return array('title'=>$title, 'idea'=>$idea);
}

function archia_generate_idea_for_title($title) {
    $opts = get_option('archia_options', array());
    $year = date('Y');
    $companyType = $opts['company_type'] ?? 'web agency';
    $companyName = $opts['company_name'] ?? 'ArchiaStudio';

    $prompt = "Write a single-line SEO-friendly idea/description for a blog titled: \"{$title}\" for {$companyType} {$companyName} {$year} or ".($year+1).". No bullets, no markdown.";
    $out = archia_run_llm($prompt);
    if (!$out) return '';
    $lines = preg_split("/\r\n|\n|\r/", trim($out));
    return trim($lines[0]);
}

function archia_generate_keywords_for_title($title) {
    $prompt = "Generate SEO keywords for the blog title \"{$title}\". Return only comma separated keywords, no extra text.";
    $out = archia_run_llm($prompt);
    if (!$out) return '';
    return preg_replace("/\s*\n\s*/", " ", trim($out));
}

function archia_generate_content_html($title, $idea) {
    $opts = get_option('archia_options', array());
    $companyType = $opts['company_type'] ?? 'web agency';
    $companyName = $opts['company_name'] ?? 'ArchiaStudio';
    $companyDomain = $opts['company_domain'] ?? 'archiastudio.com';
    $companyEmail = $opts['company_email'] ?? 'hello@archiastudio.com';

    $prompt = "Generate a fully SEO-optimized HTML fragment for a blog post for {$companyType} {$companyName}.\nTitle: {$title}\nIdea/brief: {$idea}\nRequirements:\n- Return HTML that will be placed inside <div class=\"content\"> ... </div>.\n- Use <h2>, <h3>, <p>, <ul>, <li>, etc.\n- Include an engaging intro, 5-8 subheadings, useful bullet lists, and a conclusion + CTA linking to https://{$companyDomain}/#calcom and {$companyEmail}.\n- Do NOT include <html>, <head>, or <body>.\n- Do NOT wrap in markdown or backticks.";
    return archia_run_llm($prompt);
}

// ========================================================
// ===============   IMAGE HELPERS  ========================
function archia_get_static_photo($category) {
    $category = sanitize_text_field($category ?: 'technology');
    return "https://static.photos/{$category}/1200x630?seed=" . uniqid('', true);
}


function archia_download_and_attach_image($image_url, $post_id, $title = '') {

    if (empty($image_url) || empty($post_id)) return false;

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attempts = 3;

    while ($attempts--) {

        // Force headers (important for CDN)
        $tmp = download_url($image_url, 30, [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (WordPress; ArchiaAI)',
                'Accept'     => 'image/*'
            ]
        ]);

        if (is_wp_error($tmp)) {
            error_log('[ArchiaAI] Download failed: ' . $tmp->get_error_message());
            continue;
        }

        // Validate file size (min 5KB)
        if (filesize($tmp) < 5120) {
            @unlink($tmp);
            error_log('[ArchiaAI] Image too small, retrying');
            continue;
        }

        // Validate mime type
        $mime = mime_content_type($tmp);
        if (!str_starts_with($mime, 'image/')) {
            @unlink($tmp);
            error_log('[ArchiaAI] Invalid mime: ' . $mime);
            continue;
        }

        // Build safe filename
        $filename = sanitize_file_name(
            'archia-' . substr(md5($image_url . microtime()), 0, 12) . '.jpg'
        );

        $file_array = [
            'name'     => $filename,
            'tmp_name' => $tmp
        ];

        $attachment_id = media_handle_sideload($file_array, $post_id, $title);

        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            error_log('[ArchiaAI] media_handle_sideload failed');
            continue;
        }

        set_post_thumbnail($post_id, $attachment_id);
        return $attachment_id;
    }

    error_log('[ArchiaAI] Image download failed after retries');
    return false;
}






// ---------- Create WP Post & attach featured image ----------
function archia_create_post_from_generated($title, $contentHtml, $keywords, $imageUrl, $opts = array()) {
    $postarr = array(
        'post_title' => wp_strip_all_tags($title),
        'post_content' => "<div class=\"content\">".$contentHtml."</div>",
        'post_status' => $opts['default_status'] ?? 'publish',
        'post_author' => $opts['author_id'] ?? get_current_user_id(),
        'post_category' => array(),
    );
    $post_id = wp_insert_post($postarr, true);
    if (is_wp_error($post_id)) return $post_id;

    if (!empty($keywords)) {
        wp_set_post_tags($post_id, $keywords);
    }

    if (!empty($imageUrl)) {
        // sideload image into media library
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Use media_sideload_image to create attachment
        $tmp = archia_download_and_attach_image($imageUrl, $post_id, $title);
        if (!is_wp_error($tmp) && intval($tmp) > 0) {
            set_post_thumbnail($post_id, intval($tmp));
        }
    }

    return $post_id;
}




// ---------- License verification ----------
function archia_verify_license($key) {
    if (empty($key)) return array('valid' => false, 'reason' => 'You New A License Key To Run', 'key' => $key); // no license set -> behave permissively

    $res = wp_remote_get('https://api.archiastudio.com/api/getdetails/' . rawurlencode($key), array('timeout'=>15));
    
    if (is_wp_error($res)) {
        return array('valid' => false, 'reason' => 'Network error', 'key' => $key);
    }
    
    $code = wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) {
         return array('valid' => false, 'reason' => 'License API returned '.$code, 'key' => $key);
    }
    
    $json = json_decode(wp_remote_retrieve_body($res), true);
    
    if (empty($json['expires'])) {
         return array('valid' => false, 'reason' => 'Invalid response from license API', 'key' => $key);
    }
    
    // Check expiration and add renewal link if expired
    if (time()*1000 > intval($json['expires'])) {
        $renewal_link = 'https://archiastudio.com/renew?key=' . rawurlencode($key);
        $reason = "License expired. Get it renewed from <a href=\"{$renewal_link}\" target=\"_blank\">{$renewal_link}</a>";
        return array('valid' => false, 'reason' => $reason, 'plan' => $json['plan'] ?? null, 'key' => $key);
    }
    
    return array('valid' => true, 'plan' => $json['plan'] ?? null, 'details'=>$json, 'key' => $key);
}

// ---------- Ping search engines (optional) ----------
function archia_ping_search_engines($url) {
    if (empty($url)) return;
    // ping WordPress sitemap index (default) to notify search engines
    $sitemap_index = get_site_url(null, 'sitemap.xml');
    $engines = array(
        "https://www.google.com/ping?sitemap=" . rawurlencode($sitemap_index),
        "https://www.bing.com/ping?sitemap=" . rawurlencode($sitemap_index)
    );
    foreach ($engines as $u) {
        wp_remote_get($u, array('timeout'=>5));
    }
}

// ---------- Utility ----------
function archia_strip_tags($html) {
    return trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($html)));
}

// ===================================================
// GitHub Releases Auto Updater (ArchiaAI)
// ===================================================

define('ARCHIA_GITHUB_REPO', 'archiastudio/archiaaiwp');
define('ARCHIA_PLUGIN_BASENAME', plugin_basename(__FILE__));

add_filter('pre_set_site_transient_update_plugins', 'archia_check_github_release');
add_filter('plugins_api', 'archia_github_plugin_info', 20, 3);

/**
 * Check GitHub Releases for updates
 */
function archia_check_github_release($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $response = wp_remote_get(
        'https://api.github.com/repos/' . ARCHIA_GITHUB_REPO . '/releases/latest',
        [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/vnd.github+json'
            ]
        ]
    );

    if (is_wp_error($response)) return $transient;

    $release = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($release['tag_name'])) return $transient;

    $latest_version = ltrim($release['tag_name'], 'v');
    $current_version = get_plugin_data(__FILE__)['Version'];

    if (version_compare($current_version, $latest_version, '<')) {
        $zip_url = null;

        if (!empty($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if (str_ends_with($asset['name'], '.zip')) {
                    $zip_url = $asset['browser_download_url'];
                    break;
                }
            }
        }

        if (!$zip_url) return $transient;

        $transient->response[ARCHIA_PLUGIN_BASENAME] = (object) [
            'slug'        => dirname(ARCHIA_PLUGIN_BASENAME),
            'plugin'      => ARCHIA_PLUGIN_BASENAME,
            'new_version' => $latest_version,
            'url'         => $release['html_url'],
            'package'     => $zip_url,
        ];
    }

    return $transient;
}

/**
 * Plugin details popup
 */
function archia_github_plugin_info($res, $action, $args) {
    if ($action !== 'plugin_information') return $res;
    if ($args->slug !== dirname(ARCHIA_PLUGIN_BASENAME)) return $res;

    $response = wp_remote_get(
        'https://api.github.com/repos/' . ARCHIA_GITHUB_REPO . '/releases/latest'
    );

    if (is_wp_error($response)) return $res;

    $release = json_decode(wp_remote_retrieve_body($response), true);
    if (!$release) return $res;

    return (object) [
        'name'         => 'ArchiaAI',
        'slug'         => dirname(ARCHIA_PLUGIN_BASENAME),
        'version'      => ltrim($release['tag_name'], 'v'),
        'author'       => '<a href="https://archiastudio.com">ArchiaStudio</a>',
        'homepage'     => 'https://archiastudio.com',
        'sections'     => [
            'description' => 'SEO Rich AI Blog',
            'changelog'   => nl2br($release['body'] ?? 'Bug fixes & improvements')
        ]
    ];
}
