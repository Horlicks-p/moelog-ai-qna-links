<?php
/**
 * Plugin Name: Moelog AI Q&A Links
 * Description: 在每篇文章底部顯示作者預設的問題清單，點擊後開新分頁，由 AI 生成答案。支援 OpenAI/Gemini，可自訂模型與提示。新增自訂「問題清單抬頭」與「回答頁免責聲明」。
 * Version: 1.3.2
 * Author: Horlicks (moelog.com)
 * Text Domain: moelog-ai-qna
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

class Moelog_AIQnA {
    const OPT_KEY        = 'moelog_aiqna_settings';
    const META_KEY       = '_moelog_aiqna_questions';
    const META_KEY_LANG  = '_moelog_aiqna_questions_lang';
    const SECRET_OPT_KEY = 'moelog_aiqna_secret';
    const VERSION        = '1.3.2';
    const NONCE_ACTION   = 'moe_aiqna_open';
    const RATE_TTL       = 60; // 同一 IP 同一問題 60 秒冷卻

    // 預設模型
    const DEFAULT_MODEL_OPENAI = 'gpt-4o-mini';
    const DEFAULT_MODEL_GEMINI = 'gemini-2.5-flash';

    /** @var string */
    private $secret = '';

    public function __construct() {
        // 產生/讀取 per-site 隨機密鑰（不依賴 pluggable）
        $secret = get_option(self::SECRET_OPT_KEY, '');
        if (empty($secret)) {
            try {
                $secret = bin2hex(random_bytes(32)); // 64 hex chars
            } catch (\Exception $e) {
                $secret = hash('sha256', microtime(true) . wp_salt() . rand());
            }
            add_option(self::SECRET_OPT_KEY, $secret, '', false);
        }
        $this->secret = (string) $secret;

        // 載入文字域（i18n）
        add_action('plugins_loaded', function() {
            load_plugin_textdomain('moelog-ai-qna', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        });

        // Plugins 列表「設定」捷徑
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
            $links[] = '<a href="' . esc_url(admin_url('options-general.php?page=moelog_aiqna')) . '">' . esc_html__('設定', 'moelog-ai-qna') . '</a>';
            return $links;
        });

        // WordPress 版本檢查
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            add_action('admin_notices', function() {
                printf(
                    '<div class="error"><p>%s</p></div>',
                    esc_html__('Moelog AI Q&A 需 WordPress 5.0 或以上版本。', 'moelog-ai-qna')
                );
            });
            return;
        }

        // API Key 未設定提醒
        add_action('admin_notices', [$this, 'notice_if_no_key']);

        // 後台設定頁
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);

        // 文章後台：問題清單 metabox
        add_action('add_meta_boxes', [$this, 'add_questions_metabox']);
        add_action('save_post', [$this, 'save_questions_meta']);

        // 前台：文章底部插入問題清單 + 短碼
        add_filter('the_content', [$this, 'append_questions_block']);
        add_shortcode('moelog_aiqna', [$this, 'shortcode_questions_block']);

        // 前台：攔截問答頁
        add_action('init', [$this, 'register_query_var']);
        add_action('template_redirect', [$this, 'render_answer_page']);

        // 載入樣式檔
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);

        // 啟用：flush rewrite
        register_activation_hook(__FILE__, function() {
            flush_rewrite_rules();
        });

        // 停用：flush + 清除 transient
        register_deactivation_hook(__FILE__, function () {
            flush_rewrite_rules();
            self::cleanup_transients();
        });
    }

    /* ---------- Admin Notices ---------- */
    public function notice_if_no_key() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings = get_option(self::OPT_KEY, []);
        $has = defined('MOELOG_AIQNA_API_KEY') ? MOELOG_AIQNA_API_KEY : ($settings['api_key'] ?? '');
        if (empty($has)) {
            echo '<div class="notice notice-warning"><p>';
            echo esc_html__('Moelog AI Q&A 尚未設定 API Key，請至「設定 → Moelog AI Q&A」完成設定。', 'moelog-ai-qna');
            echo '</p></div>';
        }
    }

    /* ---------- 設定頁 ---------- */
    public function add_settings_page() {
        add_options_page(
            __('Moelog AI Q&A', 'moelog-ai-qna'),
            __('Moelog AI Q&A', 'moelog-ai-qna'),
            'manage_options',
            'moelog_aiqna',
            [$this, 'settings_page_html']
        );
    }

    public function register_settings() {
        register_setting(self::OPT_KEY, self::OPT_KEY, [$this, 'sanitize_settings']);
        add_settings_section('general', __('一般設定', 'moelog-ai-qna'), '__return_false', self::OPT_KEY);

        // AI 供應商
        add_settings_field('provider', __('AI 供應商', 'moelog-ai-qna'), function() {
            $o = get_option(self::OPT_KEY, []);
            $val = $o['provider'] ?? 'openai';
            ?>
            <select name="<?php echo esc_attr(self::OPT_KEY); ?>[provider]">
                <option value="openai" <?php selected($val, 'openai'); ?>><?php esc_html_e('OpenAI', 'moelog-ai-qna'); ?></option>
                <option value="gemini" <?php selected($val, 'gemini'); ?>><?php esc_html_e('Google Gemini', 'moelog-ai-qna'); ?></option>
            </select>
            <?php
        }, self::OPT_KEY, 'general');

        // API Key 欄位：遮罩顯示
        add_settings_field('api_key', __('API Key', 'moelog-ai-qna'), function() {
            $o = get_option(self::OPT_KEY, []);
            $masked = defined('MOELOG_AIQNA_API_KEY');

            if ($masked) {
                echo '<input type="password" style="width:420px" value="********" disabled>';
                echo '<p class="description">' . esc_html__('已使用 wp-config.php 設定 MOELOG_AIQNA_API_KEY。若要改用此處，請先移除常數定義。', 'moelog-ai-qna') . '</p>';
            } else {
                $has_saved = !empty($o['api_key']);
                $display = $has_saved ? str_repeat('*', 20) : '';
                printf(
                    '<input type="password" style="width:420px" name="%s[api_key]" value="%s" placeholder="sk-...">',
                    esc_attr(self::OPT_KEY),
                    esc_attr($display)
                );
                echo '<p class="description">' . esc_html__('如已設定，出於安全僅顯示遮罩；要更換請直接輸入新 Key。', 'moelog-ai-qna') . '</p>';
                echo '<p class="description">' . esc_html__('建議改用 wp-config.php 定義 MOELOG_AIQNA_API_KEY。', 'moelog-ai-qna') . '</p>';
            }
        }, self::OPT_KEY, 'general');

        // 模型
        add_settings_field('model', __('模型（OpenAI/Gemini）', 'moelog-ai-qna'), function() {
            $o = get_option(self::OPT_KEY, []);
            $provider = $o['provider'] ?? 'openai';
            $default = ($provider === 'gemini') ? self::DEFAULT_MODEL_GEMINI : self::DEFAULT_MODEL_OPENAI;
            $val = $o['model'] ?? $default;
            printf(
                '<input type="text" style="width:320px" name="%s[model]" value="%s" placeholder="%s">',
                esc_attr(self::OPT_KEY),
                esc_attr($val),
                esc_attr($default)
            );
            echo '<p class="description">' . esc_html__('例：gpt-4o-mini 或 gemini-2.5-flash。', 'moelog-ai-qna') . '</p>';
        }, self::OPT_KEY, 'general');

        add_settings_field('temperature', __('Temperature', 'moelog-ai-qna'), function() {
            $o = get_option(self::OPT_KEY, []);
            $val = isset($o['temperature']) ? floatval($o['temperature']) : 0.3;
            printf(
                '<input type="number" step="0.1" min="0" max="2" name="%s[temperature]" value="%s">',
                esc_attr(self::OPT_KEY),
                esc_attr($val)
            );
        }, self::OPT_KEY, 'general');

        add_settings_field('include_content', __('是否附上文章內容給 AI', 'moelog-ai-qna'), function() {
            $o = get_option(self::OPT_KEY, []);
            $val = !empty($o['include_content']);
            printf(
                '<label><input type="checkbox" name="%s[include_content]" value="1" %s> %s</label>',
                esc_attr(self::OPT_KEY),
                checked($val, true, false),
                esc_html__('啟用（可提升貼文脈絡）', 'moelog-ai-qna')
            );
        }, self::OPT_KEY, 'general');

        add_settings_field('max_chars', __('文章內容截斷長度（字元）', 'moelog-ai-qna'), function() {
            $o = get_option(self::OPT_KEY, []);
            $val = isset($o['max_chars']) ? intval($o['max_chars']) : 6000;
            printf(
                '<input type="number" min="500" max="20000" name="%s[max_chars]" value="%s">',
                esc_attr(self::OPT_KEY),
                esc_attr($val)
            );
        }, self::OPT_KEY, 'general');

        add_settings_field('system_prompt', __('System Prompt（可選）', 'moelog-ai-qna'), function() {
            $o = get_option(self::OPT_KEY, []);
            $val = $o['system_prompt'] ?? __("你是嚴謹的專業編輯，提供簡潔準確的答案。", 'moelog-ai-qna');
            printf(
                '<textarea style="width:100%%;max-width:720px;height:100px" name="%s[system_prompt]">%s</textarea>',
                esc_attr(self::OPT_KEY),
                esc_textarea($val)
            );
        }, self::OPT_KEY, 'general');

        /* ===== 清單抬頭 <h3> ===== */
        add_settings_field('list_heading', __('問題清單抬頭（前台 ）', 'moelog-ai-qna'), function() {
            $o = get_option(self::OPT_KEY, []);
            $default = __('Have more questions? Ask the AI below.', 'moelog-ai-qna');
            $val = isset($o['list_heading']) && $o['list_heading'] !== '' ? $o['list_heading'] : $default;
            printf(
                '<input type="text" style="width:100%%;max-width:720px" name="%s[list_heading]" value="%s" placeholder="%s">',
                esc_attr(self::OPT_KEY),
                esc_attr($val),
                esc_attr($default)
            );
            echo '<p class="description">'.esc_html__('會顯示在問題清單上方的標題。可輸入任意語言。', 'moelog-ai-qna').'</p>';
        }, self::OPT_KEY, 'general');

        /* ===== 回答頁免責聲明 ===== */
        add_settings_field('disclaimer_text', __('回答頁免責聲明', 'moelog-ai-qna'), function() {
            $o = get_option(self::OPT_KEY, []);
            $default = "本頁面由AI生成，可能會發生錯誤，請查核重要資訊。\n使用本AI生成內容服務即表示您同意此內容僅供個人參考，且您了解輸出內容可能不準確。\n所有爭議內容{site}保有最終解釋權。";
            $val = isset($o['disclaimer_text']) && $o['disclaimer_text'] !== '' ? $o['disclaimer_text'] : $default;
            printf(
                '<textarea style="width:100%%;max-width:720px;height:140px" name="%s[disclaimer_text]">%s</textarea>',
                esc_attr(self::OPT_KEY),
                esc_textarea($val)
            );
            echo '<p class="description">'.esc_html__('支援 {site} 代表網站名稱，亦相容舊式 %s。可多行。', 'moelog-ai-qna').'</p>';
        }, self::OPT_KEY, 'general');
    }

    public function sanitize_settings($input) {
        $prev = get_option(self::OPT_KEY, []);
        $out = [];

        // 允許 gemini 供應商
        $out['provider']        = in_array(($input['provider'] ?? 'openai'), ['openai', 'gemini'], true) ? $input['provider'] : 'openai';
        $out['model']           = sanitize_text_field($input['model'] ?? (($out['provider'] === 'gemini') ? self::DEFAULT_MODEL_GEMINI : self::DEFAULT_MODEL_OPENAI));
        $out['temperature']     = floatval($input['temperature'] ?? 0.3);
        $out['include_content'] = !empty($input['include_content']) ? 1 : 0;
        $out['max_chars']       = max(500, min(20000, intval($input['max_chars'] ?? 6000)));
        $out['system_prompt']   = wp_kses_post($input['system_prompt'] ?? '');

        // 清單抬頭
        $default_heading        = __('Have more questions? Ask the AI below.', 'moelog-ai-qna');
        $out['list_heading']    = sanitize_text_field($input['list_heading'] ?? $default_heading);
        if ($out['list_heading'] === '') $out['list_heading'] = $default_heading;

        // 免責
        $default_disclaimer     = "本頁面由AI生成，可能會發生錯誤，請查核重要資訊。\n使用本AI生成內容服務即表示您同意此內容僅供個人參考，且您了解輸出內容可能不準確。\n所有爭議內容{site}保有最終解釋權。";
        $out['disclaimer_text'] = sanitize_textarea_field($input['disclaimer_text'] ?? $default_disclaimer);
        if ($out['disclaimer_text'] === '') $out['disclaimer_text'] = $default_disclaimer;

        if (defined('MOELOG_AIQNA_API_KEY')) {
            $out['api_key'] = '';
        } else {
            $in = trim($input['api_key'] ?? '');
            if ($in === '' || preg_match('/^\*+$/', $in)) {
                $out['api_key'] = isset($prev['api_key']) ? $prev['api_key'] : '';
            } else {
                $out['api_key'] = $in;
            }
        }
        return $out;
    }

    public function settings_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Moelog AI Q&A', 'moelog-ai-qna'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPT_KEY);
                do_settings_sections(self::OPT_KEY);
                submit_button();
                ?>
            </form>
            <hr>
            <p><strong><?php esc_html_e('使用說明：', 'moelog-ai-qna'); ?></strong></p>
            <ol>
                <li><?php esc_html_e('在「設定 → Moelog AI Q&A」填入 API Key / 模型等。', 'moelog-ai-qna'); ?></li>
                <li><?php esc_html_e('編輯文章時，於右側/下方的「AI 問題清單」每行輸入一題並選擇語言（可選自動）。', 'moelog-ai-qna'); ?></li>
                <li><?php esc_html_e('前台文章底部會顯示問題列表（抬頭可自訂）。點擊後開新分頁顯示 AI 答案與免責聲明（可自訂）。', 'moelog-ai-qna'); ?></li>
                <li><?php esc_html_e('或使用短碼 [moelog_aiqna] 手動插入問題清單。', 'moelog-ai-qna'); ?></li>
            </ol>
        </div>
        <?php
    }

    /* ---------- Metabox：每篇文章輸入問題 ---------- */
    public function add_questions_metabox() {
        add_meta_box(
            'moelog_aiqna_box',
            __('AI 問題清單（每行一題；會顯示在文章底部）', 'moelog-ai-qna'),
            [$this, 'questions_metabox_html'],
            ['post', 'page'],
            'normal',
            'default'
        );
    }

    public function questions_metabox_html($post) {
        $questions = get_post_meta($post->ID, self::META_KEY, true);
        $langs = get_post_meta($post->ID, self::META_KEY_LANG, true);
        $langs = is_array($langs) ? $langs : [];
        $questions = $questions ? explode("\n", $questions) : [''];
        wp_nonce_field('moelog_aiqna_save', 'moelog_aiqna_nonce');
        ?>
        <div id="moelog-aiqna-questions">
            <div id="moelog-aiqna-rows">
                <?php foreach ($questions as $i => $q): ?>
                <div class="moe-question-row" style="margin-bottom:10px; display:flex; align-items:flex-end;">
                    <textarea style="width:70%; min-height:60px; flex-grow:1; line-height:1.2em;" name="moelog_aiqna_questions[]" placeholder="<?php esc_attr_e('例：為何科技新創偏好使用「.io」？', 'moelog-ai-qna'); ?>"><?php echo esc_textarea($q); ?></textarea>
                    <select name="moelog_aiqna_langs[]" style="margin-left:10px; min-width:120px;">
                        <option value="auto" <?php selected($langs[$i] ?? 'auto', 'auto'); ?>><?php esc_html_e('自動偵測', 'moelog-ai-qna'); ?></option>
                        <option value="zh" <?php selected($langs[$i] ?? '', 'zh'); ?>><?php esc_html_e('繁體中文', 'moelog-ai-qna'); ?></option>
                        <option value="ja" <?php selected($langs[$i] ?? '', 'ja'); ?>><?php esc_html_e('日文', 'moelog-ai-qna'); ?></option>
                        <option value="en" <?php selected($langs[$i] ?? '', 'en'); ?>><?php esc_html_e('英文', 'moelog-ai-qna'); ?></option>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>

            <p id="moelog-aiqna-help" style="color:#666; margin-top:6px;">
                <?php esc_html_e('提示：每行一題，建議 3–8 題，每題最多 200 字。語言選擇「自動偵測」時，AI 會根據問題文字判斷語言。', 'moelog-ai-qna'); ?>
            </p>
            <button type="button" id="moelog-aiqna-add-btn" class="button">
                <?php esc_html_e('新增問題', 'moelog-ai-qna'); ?>
            </button>
        </div>

        <script>
        (function() {
            const rowsBox = document.getElementById('moelog-aiqna-rows');
            const addBtn  = document.getElementById('moelog-aiqna-add-btn');

            function makeRow() {
                const row = document.createElement('div');
                row.className = 'moe-question-row';
                row.style.marginBottom = '10px';
                row.style.display = 'flex';
                row.style.alignItems = 'flex-end';
                row.innerHTML = `
                    <textarea style="width:70%; min-height:60px; flex-grow:1; line-height:1.2em;"
                        name="moelog_aiqna_questions[]"
                        placeholder="<?php esc_attr_e('例：為何科技新創偏好使用「.io」？', 'moelog-ai-qna'); ?>"></textarea>
                    <select name="moelog_aiqna_langs[]" style="margin-left:10px; min-width:120px;">
                        <option value="auto"><?php esc_html_e('自動偵測', 'moelog-ai-qna'); ?></option>
                        <option value="zh"><?php esc_html_e('繁體中文', 'moelog-ai-qna'); ?></option>
                        <option value="ja"><?php esc_html_e('日文', 'moelog-ai-qna'); ?></option>
                        <option value="en"><?php esc_html_e('英文', 'moelog-ai-qna'); ?></option>
                    </select>`;
                return row;
            }

            addBtn.addEventListener('click', function() {
                rowsBox.appendChild(makeRow());
            });

            window.moelogAddQuestionRow = function() {
                rowsBox.appendChild(makeRow());
            };
        })();
        </script>
        <?php
    }

    public function save_questions_meta($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (!isset($_POST['moelog_aiqna_nonce'])) return;

        check_admin_referer('moelog_aiqna_save', 'moelog_aiqna_nonce');

        $questions = isset($_POST['moelog_aiqna_questions']) ? (array) $_POST['moelog_aiqna_questions'] : [];
        $langs = isset($_POST['moelog_aiqna_langs']) ? (array) $_POST['moelog_aiqna_langs'] : [];
        $lines = [];
        $lang_data = [];

        foreach ($questions as $i => $q) {
            $q = trim(wp_unslash($q));
            if (empty($q)) continue;
            if (function_exists('mb_substr')) {
                $q = mb_substr($q, 0, 200, 'UTF-8');
            } else {
                $q = substr($q, 0, 200);
            }
            $lines[] = $q;
            $lang_data[] = in_array($langs[$i] ?? 'auto', ['auto', 'zh', 'ja', 'en']) ? $langs[$i] : 'auto';
        }

        $lines = array_slice($lines, 0, 8);
        $lang_data = array_slice($lang_data, 0, 8);
        update_post_meta($post_id, self::META_KEY, implode("\n", $lines));
        update_post_meta($post_id, self::META_KEY_LANG, $lang_data);
    }

    /* ---------- 前台輸出 ---------- */
    public function append_questions_block($content) {
        if (!is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        return $content . $this->get_questions_block(get_the_ID());
    }

    public function shortcode_questions_block($atts) {
        $post_id = get_the_ID();
        if (!$post_id) return '';
        return $this->get_questions_block($post_id);
    }

    private function get_questions_block($post_id) {
        $raw = get_post_meta($post_id, self::META_KEY, true);
        $langs = get_post_meta($post_id, self::META_KEY_LANG, true);
        $langs = is_array($langs) ? $langs : [];
        if (!$raw) return '';

        $lines = array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $raw)));
        if (empty($lines)) return '';

        // 讀取後台自訂抬頭（含預設 + 濾鏡）
        $o = get_option(self::OPT_KEY, []);
        $default_heading = __('Have more questions? Ask the AI below.', 'moelog-ai-qna');
        $heading = isset($o['list_heading']) && $o['list_heading'] !== '' ? $o['list_heading'] : $default_heading;
        $heading = apply_filters('moelog_aiqna_list_heading', $heading);

        $items = '';
        foreach ($lines as $idx => $q) {
            $ts = time();
            // Nonce action：加入 per-site secret 的片段
            $act = self::NONCE_ACTION . '|' . $post_id . '|' . substr(hash_hmac('sha256', (string) $post_id, $this->secret), 0, 12);
            $nonce = wp_create_nonce($act);

            // 使用站內 secret 作為 HMAC 密鑰
            $sig = hash_hmac('sha256', $post_id . '|' . $q . '|' . $ts, $this->secret);

            // 不手動 rawurlencode，交給 add_query_arg()
            $url = add_query_arg([
                'moe_ai'  => 1,
                'post_id' => $post_id,
                'q'       => $q,
                'lang'    => $langs[$idx] ?? 'auto',
                '_nonce'  => $nonce,
                'ts'      => $ts,
                'sig'     => $sig,
            ], home_url('/'));

            $items .= sprintf(
                '<li><a class="moe-aiqna-link" target="_blank" rel="noopener" href="%s">%s</a></li>',
                esc_url($url),
                esc_html($q)
            );
        }

        return sprintf(
            '<p class="ask_chatgpt" title="%s"></p><div class="moe-aiqna-block"><ul>%s</ul></div>',
            esc_html($heading),
            $items
        );
    }

    public function enqueue_styles() {
        $is_answer_page = !empty($_GET['moe_ai']);
        $is_single_main = (is_singular() && is_main_query());
        if ($is_answer_page || $is_single_main) {
            wp_enqueue_style(
                'moelog-aiqna',
                plugin_dir_url(__FILE__) . 'assets/style.css',
                [],
                self::VERSION
            );
        }
    }

    /* ---------- 問答頁 ---------- */
    public function register_query_var() {
        add_rewrite_tag('%moe_ai%', '1');
        add_rewrite_tag('%post_id%', '([0-9]+)');
        add_rewrite_tag('%q%', '(.+)');
        add_rewrite_tag('%lang%', '([a-z]+)');
        add_rewrite_tag('%_nonce%', '(.+)');
        add_rewrite_tag('%ts%', '([0-9]+)');
        add_rewrite_tag('%sig%', '([A-Fa-f0-9]{64})'); // SHA-256 固定 64 字元
    }

    public function render_answer_page() {
        if (!isset($_GET['moe_ai'])) {
            return;
        }

        // 🚫 阻擋已知爬蟲 (防止觸發 AI API) — 可由開發者過濾擴充
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $bot_patterns = [
            'bot', 'crawl', 'spider', 'scrape', 'curl', 'wget',
            'Googlebot', 'Bingbot', 'Baiduspider', 'facebookexternalhit'
        ];
        $bot_patterns = apply_filters('moelog_aiqna_blocked_bots', $bot_patterns);
        foreach ($bot_patterns as $pattern) {
            if (stripos($ua, $pattern) !== false) {
                status_header(403);
                exit('Bots are not allowed');
            }
        }

        $post_id  = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        $question = isset($_GET['q']) ? wp_unslash($_GET['q']) : '';
        $nonce    = isset($_GET['_nonce']) ? sanitize_text_field($_GET['_nonce']) : '';
        $lang     = isset($_GET['lang']) ? sanitize_text_field($_GET['lang']) : 'auto';
        $ts       = isset($_GET['ts']) ? intval($_GET['ts']) : 0;
        $sig      = isset($_GET['sig']) ? sanitize_text_field($_GET['sig']) : '';

        $act = self::NONCE_ACTION . '|' . $post_id . '|' . substr(hash_hmac('sha256', (string) $post_id, $this->secret), 0, 12);

        // 基礎檢查
        if (!$post_id || $question === '' || $nonce === '' || !$ts || $sig === '') {
            wp_die(__('參數錯誤或連結已失效。', 'moelog-ai-qna'), __('錯誤', 'moelog-ai-qna'), ['response' => 400]);
        }

        // Nonce 驗證
        if (!wp_verify_nonce($nonce, $act)) {
            wp_die(__('連結驗證失敗或已過期。', 'moelog-ai-qna'), __('錯誤', 'moelog-ai-qna'), ['response' => 403]);
        }

        // 縮短有效期至 15 分鐘
        if (abs(time() - $ts) > MINUTE_IN_SECONDS * 15) {
            wp_die(__('連結已過期，請回原文重新點擊問題。', 'moelog-ai-qna'), __('錯誤', 'moelog-ai-qna'), ['response' => 403]);
        }

        // HMAC 檢查（只用站內 secret）
        $expect = hash_hmac('sha256', $post_id . '|' . $question . '|' . $ts, $this->secret);
        if (!hash_equals($expect, $sig)) {
            wp_die(__('簽章檢核失敗。', 'moelog-ai-qna'), __('錯誤', 'moelog-ai-qna'), ['response' => 403]);
        }

        $settings      = get_option(self::OPT_KEY, []);
        $provider      = $settings['provider'] ?? 'openai';
        $api_key       = defined('MOELOG_AIQNA_API_KEY') ? MOELOG_AIQNA_API_KEY : ($settings['api_key'] ?? '');
        $default_model = ($provider === 'gemini') ? self::DEFAULT_MODEL_GEMINI : self::DEFAULT_MODEL_OPENAI;
        $model         = $settings['model'] ?? $default_model;
        $temp          = isset($settings['temperature']) ? floatval($settings['temperature']) : 0.3;
        $system        = $settings['system_prompt'] ?? '';
        $include       = !empty($settings['include_content']);
        $max_chars     = isset($settings['max_chars']) ? intval($settings['max_chars']) : 6000;

        $post = get_post($post_id);
        if (!$post) {
            wp_die(__('找不到文章。', 'moelog-ai-qna'), __('錯誤', 'moelog-ai-qna'), ['response' => 404]);
        }

        // Context 更嚴格清理 + UTF-8 安全截斷
        $context = '';
        if ($include) {
            $raw = $post->post_title . "\n\n" . strip_shortcodes($post->post_content);
            $raw = wp_strip_all_tags($raw);
            $raw = preg_replace('/\s+/u', ' ', $raw);

            if (function_exists('grapheme_strlen') && function_exists('grapheme_substr')) {
                $context = (grapheme_strlen($raw) > $max_chars) ? grapheme_substr($raw, 0, $max_chars) : $raw;
            } elseif (function_exists('mb_strlen') && function_exists('mb_substr')) {
                $context = (mb_strlen($raw, 'UTF-8') > $max_chars) ? mb_substr($raw, 0, $max_chars, 'UTF-8') : $raw;
            } else {
                $context = (strlen($raw) > $max_chars) ? substr($raw, 0, $max_chars) : $raw;
            }
        }

        // 快取優先 → 冷卻 → IP 限流 → 打 API
        $context_hash = substr(hash('sha256', $context), 0, 32);
        $cache_key = 'moe_aiqna_' . hash('sha256', $post_id . '|' . $question . '|' . $model . '|' . $context_hash . '|' . $lang);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $freq_key = 'moe_aiqna_freq_' . md5($ip . '|' . $post_id . '|' . $question);
        $ip_key   = 'moe_aiqna_ip_' . md5($ip);

        // 1) 先回快取（不計入 IP 次數）
        $cached_answer = get_transient($cache_key);
        if ($cached_answer !== false) {
            $this->render_answer_html($post_id, $question, $cached_answer);
            exit;
        }

        // 2) 冷卻檢查（無快取時才檢）
        if (get_transient($freq_key)) {
            wp_die(__('請求過於頻繁，請稍後再試。', 'moelog-ai-qna'), __('錯誤', 'moelog-ai-qna'), ['response' => 429]);
        }

        // 3) 要打 API 才檢/加 IP 計數
        $ip_cnt = (int) get_transient($ip_key);
        if ($ip_cnt >= 10) {
            wp_die(__('請求過於頻繁，請稍後再試。', 'moelog-ai-qna'), __('錯誤', 'moelog-ai-qna'), ['response' => 429]);
        }
        set_transient($ip_key, $ip_cnt + 1, HOUR_IN_SECONDS);

        // 4) 設置冷卻鎖（避免並發）
        set_transient($freq_key, 1, self::RATE_TTL);

        // 呼叫 AI（總是傳遞 cache_key）
        $answer = $this->call_ai($provider, [
            'api_key'     => $api_key,
            'model'       => $model,
            'temperature' => $temp,
            'system'      => $system,
            'question'    => $question,
            'context'     => $context,
            'url'         => get_permalink($post_id),
            'post_id'     => $post_id,
            'lang'        => $lang,
            'cache_key'   => $cache_key,
        ]);

        $this->render_answer_html($post_id, $question, $answer);
        exit;
    }

    private function render_answer_html($post_id, $question, $answer) {
        status_header(200);
        nocache_headers();

        // Content Security Policy：允許自家資源、Google Fonts、以及必要的 inline JS/CSS
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <meta name="robots" content="noindex,nofollow">
            <title><?php echo esc_html(get_the_title($post_id)); ?> - <?php esc_html_e('AI 解答', 'moelog-ai-qna'); ?></title>
            <link rel="stylesheet" href="<?php echo esc_url(get_stylesheet_uri()); ?>">
            <link href="https://fonts.googleapis.com/css2?family=DotGothic16&family=Noto+Sans+JP&family=Noto+Sans+TC:wght@100..900&family=Press+Start+2P&display=swap" rel="stylesheet">
            <?php wp_head(); ?>
            <style>
                .moe-typing-cursor { display:inline-block; width:1px; background:#999; margin-left:2px; animation:moe-blink 1s step-end infinite; vertical-align:baseline; }
                @keyframes moe-blink { 50% { background: transparent; } }
            </style>
            <script>
            function moelogClosePage() {
                window.close();
                setTimeout(function() {
                    var fb = document.getElementById('moelog-fallback');
                    if (fb) fb.style.display = 'block';
                }, 300);
                return false;
            }
            </script>
        </head>
        <body class="moe-aiqna-answer">
            <div class="moe-container">
                <div class="moe-head">
                    <h1 class="moe-question"><?php echo esc_html($question); ?></h1>
                </div>
                <div class="moe-answer-wrap">
                    <div class="moe-question-echo"><strong>Q：</strong><?php echo esc_html($question); ?></div>
                    <?php
                    // 僅允許極簡白名單（禁用 <a>、不開 code/pre），先在後端完成淨化
                    $allowed = [
                        'p' => [], 'ul' => [], 'ol' => [], 'li' => [],
                        'strong' => [], 'em' => [], 'br' => [],
                    ];
                    $safe_html = $answer ? wp_kses(wpautop($answer), $allowed) : '';

                    // 二次保險：移除任何殘存的 on* 事件屬性（理論上 wp_kses 已排除）
                    $safe_html = preg_replace('/\son\w+\s*=\s*(["\']).*?\1/iu', '', $safe_html);
                    ?>
                    <div id="moe-ans-target"></div>
                    <template id="moe-ans-source"><?php echo $safe_html; ?></template>
                    <noscript><?php echo $safe_html ? $safe_html : '<p>' . esc_html__('抱歉，目前無法取得 AI 回答，請稍後再試。', 'moelog-ai-qna') . '</p>'; ?></noscript>
                    <script>
                    (function() {
                        const srcTpl = document.getElementById('moe-ans-source');
                        const target = document.getElementById('moe-ans-target');
                        if (!srcTpl || !target) return;
                        const ALLOWED = new Set(['P','UL','OL','LI','STRONG','EM','BR']);
                        const SPEED = 18;
                        function cloneShallow(node) {
                            if (node.nodeType === Node.TEXT_NODE) return document.createTextNode('');
                            if (node.nodeType === Node.ELEMENT_NODE) {
                                const tag = node.tagName.toUpperCase();
                                if (!ALLOWED.has(tag)) return document.createTextNode(node.textContent || '');
                                if (tag === 'BR') return document.createElement('br');
                                return document.createElement(tag.toLowerCase());
                            }
                            return document.createTextNode('');
                        }
                        function prepareTyping(srcParent, dstParent, queue) {
                            Array.from(srcParent.childNodes).forEach(src=>{
                                if (src.nodeType===Node.TEXT_NODE) {
                                    const t = document.createTextNode('');
                                    dstParent.appendChild(t);
                                    const text = src.textContent || '';
                                    if (text.length) queue.push({ node:t, text });
                                } else if (src.nodeType===Node.ELEMENT_NODE) {
                                    const cloned = cloneShallow(src);
                                    dstParent.appendChild(cloned);
                                    if (cloned.nodeType===Node.TEXT_NODE) {
                                        const text = cloned.textContent || '';
                                        if (text.length) queue.push({ node:cloned, text });
                                    } else if (cloned.tagName && cloned.tagName.toUpperCase()==='BR') {
                                        // no children
                                    } else {
                                        prepareTyping(src, cloned, queue);
                                    }
                                }
                            });
                        }
                        async function typeQueue(queue) {
                            const cursor = document.createElement('span');
                            cursor.className = 'moe-typing-cursor';
                            target.appendChild(cursor);
                            for (const item of queue) {
                                const chars = Array.from(item.text);
                                for (let i=0; i<chars.length; i++) {
                                    item.node.textContent += chars[i];
                                    await new Promise(r => setTimeout(r, SPEED));
                                }
                            }
                            cursor.remove();
                        }
                        const sourceRoot = document.createElement('div');
                        sourceRoot.innerHTML = srcTpl.innerHTML;
                        const queue = [];
                        prepareTyping(sourceRoot, target, queue);
                        if (queue.length === 0) {
                            target.innerHTML = '<p><?php echo esc_js(esc_html__('抱歉，目前無法取得 AI 回答，請稍後再試。', 'moelog-ai-qna')); ?></p>';
                        } else {
                            typeQueue(queue);
                        }
                    })();
                    </script>
                </div>
                <div class="moe-close-area">
                    <a href="#" class="moe-close-btn" onclick="return moelogClosePage();"><?php esc_html_e('← 關閉此頁', 'moelog-ai-qna'); ?></a>
                    <div id="moelog-fallback" class="moe-fallback" style="display:none;">
                        <?php esc_html_e('若瀏覽器不允許自動關閉視窗，請點此回到文章：', 'moelog-ai-qna'); ?>
                        <a href="<?php echo esc_url(get_permalink($post_id)); ?>" target="_self" rel="noopener"><?php echo esc_html(get_the_title($post_id)); ?></a>
                    </div>

                    <?php
                    $site_name = get_bloginfo('name', 'display');
                    if (empty($site_name)) {
                        $host = parse_url(home_url(), PHP_URL_HOST);
                        $site_name = $host ?: '本網站';
                    }

                    $o = get_option(self::OPT_KEY, []);
                    $default_tpl = "本頁面由AI生成，可能會發生錯誤，請查核重要資訊。\n使用本AI生成內容服務即表示您同意此內容僅供個人參考，且您了解輸出內容可能不準確。\n所有爭議內容{site}保有最終解釋權。";
                    $tpl = isset($o['disclaimer_text']) && $o['disclaimer_text'] !== '' ? $o['disclaimer_text'] : $default_tpl;
                    $disclaimer = str_replace(['{site}', '%s'], $site_name, $tpl);
                    $disclaimer = apply_filters('moelog_aiqna_disclaimer_text', $disclaimer, $site_name);
                    ?>
                    <p class="moe-disclaimer" style="margin-top:20px; font-size:0.85em; color:#666; line-height:1.5em;">
                        <?php echo nl2br(esc_html($disclaimer)); ?>
                    </p>
                </div>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }

    /* ---------- 語言偵測 ---------- */
    private function detect_language($text) {
        $s = trim((string) $text);
        if (empty($s)) return 'en';

        // 日文：平假名、片假名
        if (preg_match('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{31F0}-\x{31FF}\x{FF66}-\x{FF9F}]/u', $s)) {
            return 'ja';
        }

        // 中文：漢字 + 中文標點
        if (preg_match('/[\p{Han}\x{3000}-\x{303F}\x{FF01}-\x{FF5E}]/u', $s)) {
            $han = preg_match_all('/\p{Han}/u', $s, $m1);
            $latin = preg_match_all('/[A-Za-z]/u', $s, $m2);
            if ($han > 0 && $latin < max(1, $han / 2)) {
                return 'zh';
            }
        }

        // 英文：純拉丁字母
        if (preg_match('/^[A-Za-z0-9\s.,!?]+$/u', $s)) {
            return 'en';
        }

        // 進階偵測
        if (function_exists('mb_detect_encoding')) {
            $encoding = mb_detect_encoding($s, ['UTF-8', 'EUC-JP', 'SJIS', 'ISO-8859-1'], true);
            if ($encoding === 'EUC-JP' || $encoding === 'SJIS') return 'ja';
            if ($encoding === 'UTF-8' && preg_match('/\p{Han}/u', $s)) return 'zh';
        }
        return 'en';
    }

    /* ---------- AI 呼叫 ---------- */
    private function call_ai($provider, $args) {
        $api_key = $args['api_key'] ?? '';
        if (empty($api_key)) {
            return __('尚未設定 API Key。', 'moelog-ai-qna');
        }

        $question = trim($args['question'] ?? '');
        $context  = trim($args['context'] ?? '');
        $model    = $args['model'] ?? (($provider === 'gemini') ? self::DEFAULT_MODEL_GEMINI : self::DEFAULT_MODEL_OPENAI);
        $temp     = isset($args['temperature']) ? floatval($args['temperature']) : 0.3;
        $system   = $args['system'] ?? '';
        $url      = $args['url'] ?? '';
        $post_id  = intval($args['post_id'] ?? 0);
        $lang     = $args['lang'] ?? $this->detect_language($question);

        // 快取鍵（含語言）
        $context_hash = substr(hash('sha256', $context), 0, 32);
        $cache_key = $args['cache_key'] ?? ('moe_aiqna_' . hash('sha256', $post_id . '|' . $question . '|' . $model . '|' . $context_hash . '|' . $lang));
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // 檢查 URL 是否為本地/私有位址（避免外露本地網址）
        $is_local_url = false;
        $host = parse_url($url, PHP_URL_HOST);
        if ($host) {
            if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) || preg_match('/^(10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|192\.168\.)/', $host)) {
                $is_local_url = true;
            }
        }

        // 語言提示
        switch ($lang) {
            case 'ja': $lang_hint = '回答は日本語で、簡潔にしてください。'; break;
            case 'zh': $lang_hint = '請以繁體中文回答，保持簡潔。'; break;
            case 'en': $lang_hint = 'Answer in English, keep it concise.'; break;
            default:   $lang_hint = 'Answer in the same language as the question, keep it concise.'; break;
        }

        // Prompt
        $user_prompt = "問題：{$question}\n\n";
        if ($context) {
            $user_prompt .= __("以下為原文脈絡（可能已截斷）：\n{$context}\n\n", 'moelog-ai-qna');
        }
        if (!$is_local_url && $url) {
            $user_prompt .= __("請盡量簡潔作答；涉及數據與時序請給年份/日期。原文連結：{$url}", 'moelog-ai-qna');
        } else {
            $user_prompt .= __("請盡量簡潔作答；涉及數據與時序請給年份/日期。", 'moelog-ai-qna');
        }

        switch ($provider) {
            case 'gemini':
                $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
                $full_user_content = ($system ? $system . "\n\n" : '') . $lang_hint . "\n\n" . $user_prompt;

                $body = [
                    'contents' => [[
                        'role'  => 'user',
                        'parts' => [['text' => $full_user_content]],
                    ]],
                    'generationConfig' => [
                        'temperature' => $temp,
                    ]
                ];

                $resp = wp_remote_post($endpoint, [
                    'headers' => [ 'Content-Type' => 'application/json' ],
                    'timeout' => 20,
                    'body'    => wp_json_encode($body),
                ]);

                if (is_wp_error($resp)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[Moelog AIQnA] HTTP Error (Gemini): ' . $resp->get_error_message());
                    }
                    return __('呼叫 Google Gemini 失敗，請稍後再試。', 'moelog-ai-qna');
                }

                $code = wp_remote_retrieve_response_code($resp);
                $raw  = wp_remote_retrieve_body($resp);
                $json = json_decode($raw, true);

                if ($code >= 200 && $code < 300) {
                    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    if ($text !== '') {
                        $answer = trim($text);
                        set_transient($cache_key, $answer, DAY_IN_SECONDS);
                        return $answer;
                    }
                }

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Moelog AIQnA] Gemini HTTP ' . $code . ' Body: ' . $raw);
                }

                // 統一錯誤訊息（避免洩露細節）
                if ($code === 400 || $code === 403) {
                    $msg = $json['error']['message'] ?? '未知錯誤';
                    if (strpos($msg, 'API_KEY_INVALID') !== false || (strpos($msg, 'model') !== false && strpos($msg, 'not found') !== false)) {
                        return __('服務暫時無法使用，請檢查 API Key 或模型名稱。', 'moelog-ai-qna');
                    }
                    if (strpos($msg, 'blocked') !== false || strpos($msg, 'PROMPT_FILTERED') !== false) {
                        return __('問題或答案被安全過濾機制阻擋。', 'moelog-ai-qna');
                    }
                    return __('AI 服務回傳異常，請稍後再試。', 'moelog-ai-qna');
                }

                return __('Google Gemini 回傳異常，請稍後再試。', 'moelog-ai-qna');

            case 'openai':
            default:
                $endpoint = 'https://api.openai.com/v1/chat/completions';
                $body = [
                    'model' => $model,
                    'temperature' => $temp,
                    'messages' => [
                        ['role' => 'system', 'content' => $system ?: 'You are a professional editor providing concise and accurate answers.'],
                        ['role' => 'system', 'content' => $lang_hint],
                        ['role' => 'user',   'content' => $user_prompt],
                    ]
                ];
                $resp = wp_remote_post($endpoint, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                    ],
                    'timeout' => 20,
                    'body' => wp_json_encode($body),
                ]);
                if (is_wp_error($resp)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[Moelog AIQnA] HTTP Error: ' . $resp->get_error_message());
                    }
                    return __('呼叫 OpenAI 失敗，請稍後再試。', 'moelog-ai-qna');
                }
                $code = wp_remote_retrieve_response_code($resp);
                $json = json_decode(wp_remote_retrieve_body($resp), true);
                if ($code >= 200 && $code < 300 && !empty($json['choices'][0]['message']['content'])) {
                    $answer = trim($json['choices'][0]['message']['content']);
                    set_transient($cache_key, $answer, DAY_IN_SECONDS);
                    return $answer;
                }
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Moelog AIQnA] HTTP ' . $code);
                }

                if ($code === 401) {
                    return __('服務暫時無法使用，請檢查 API Key 或模型名稱。', 'moelog-ai-qna');
                }
                if ($code === 429) {
                    $msg = $json['error']['message'] ?? '';
                    if (strpos($msg, 'insufficient_quota') !== false) {
                        return __('服務暫時無法使用，請檢查 API 額度。', 'moelog-ai-qna');
                    }
                    return __('請求過於頻繁，請稍候再試。', 'moelog-ai-qna');
                }
                return __('AI 服務回傳異常，請稍後再試。', 'moelog-ai-qna');
        }
    }

    /* ---------- 清理資料 ---------- */
    public static function cleanup_transients() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_moe_aiqna_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_moe_aiqna_%'");
    }

    public static function uninstall() {
        delete_option(self::OPT_KEY);
        delete_option(self::SECRET_OPT_KEY);
        self::cleanup_transients();

        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s OR meta_key = %s",
                self::META_KEY,
                self::META_KEY_LANG
            )
        );
    }
}

/* 啟動：等 WP 載入完成再建立實例（避免過早呼叫 get_option 等函式） */
add_action('plugins_loaded', function () {
    new Moelog_AIQnA();
});

/* 卸載時完整清理 */
register_uninstall_hook(__FILE__, ['Moelog_AIQnA', 'uninstall']);
