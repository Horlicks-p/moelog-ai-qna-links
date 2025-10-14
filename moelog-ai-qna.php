<?php
/**
 * Plugin Name: Moelog AI Q&A Links
 * Description: 在每篇文章底部顯示作者預設的問題清單,點擊後開新分頁,由 AI 生成答案。支持 OpenAI/Gemini,可自訂模型與提示。
 * Version: 1.6.2
 * Author: Horlicks (moelog.com)
 * Text Domain: moelog-ai-qna
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined("ABSPATH")) {
    exit();
}

class Moelog_AIQnA
{
    const OPT_KEY = "moelog_aiqna_settings";
    const META_KEY = "_moelog_aiqna_questions";
    const META_KEY_LANG = "_moelog_aiqna_questions_lang";
    const SECRET_OPT_KEY = "moelog_aiqna_secret";
    const VERSION = "1.6.2";
    const NONCE_ACTION = "moe_aiqna_open";
    const RATE_TTL = 60;
    const PRETTY_BASE = "qna";
    const CACHE_TTL = 86400;
    const DEFAULT_MODEL_OPENAI = "gpt-4o-mini";
    const DEFAULT_MODEL_GEMINI = "gemini-2.5-flash";

    private $secret = "";
    private $csp_nonce = null;
    private $prefetch_injected = false; // 避免單題連結的預抓取腳本重複注入
    public function __construct()
    {
        // 初始化 HMAC secret
        $secret = get_option(self::SECRET_OPT_KEY, "");
        if (empty($secret)) {
            try {
                $secret = bin2hex(random_bytes(32));
            } catch (\Exception $e) {
                $secret = hash("sha256", microtime(true) . wp_salt() . rand());
            }
            add_option(self::SECRET_OPT_KEY, $secret, "", false);
        }
        $this->secret = (string) $secret;

        // 回答頁才產生 CSP nonce
        $req_uri = $_SERVER["REQUEST_URI"] ?? "";
        $is_answer =
            isset($_GET["moe_ai"]) ||
            strpos($req_uri, "/" . self::PRETTY_BASE . "/") !== false ||
            strpos($req_uri, "/ai-answer/") !== false;

        if ($is_answer) {
            try {
                $this->csp_nonce = rtrim(
                    strtr(base64_encode(random_bytes(16)), "+/", "-_"),
                    "=",
                );
            } catch (\Exception $e) {
                $this->csp_nonce = base64_encode(
                    hash("sha256", microtime(true), true),
                );
            }
        }

        // 語系
        add_action("plugins_loaded", function () {
            load_plugin_textdomain(
                "moelog-ai-qna",
                false,
                dirname(plugin_basename(__FILE__)) . "/languages/",
            );
        });

        // 設定捷徑
        add_filter(
            "plugin_action_links_" . plugin_basename(__FILE__),
            function ($links) {
                $links[] =
                    '<a href="' .
                    esc_url(
                        admin_url("options-general.php?page=moelog_aiqna"),
                    ) .
                    '">' .
                    esc_html__("設定", "moelog-ai-qna") .
                    "</a>";
                return $links;
            },
        );

        // 版本檢查
        if (version_compare(get_bloginfo("version"), "5.0", "<")) {
            add_action("admin_notices", function () {
                printf(
                    '<div class="error"><p>%s</p></div>',
                    esc_html__(
                        "Moelog AI Q&A 需 WordPress 5.0 或以上版本。",
                        "moelog-ai-qna",
                    ),
                );
            });
            return;
        }

        // 管理後台/初始化
        add_action("admin_notices", [$this, "notice_if_no_key"]);
        add_action("admin_menu", [$this, "add_settings_page"]);
        add_action("admin_init", [$this, "register_settings"]);
        add_action("add_meta_boxes", [$this, "add_questions_metabox"]);
        add_action("save_post", [$this, "save_questions_meta"]);
        add_filter("the_content", [$this, "append_questions_block"]);
        add_shortcode("moelog_aiqna", [$this, "shortcode_questions_block"]);
        add_action("init", [$this, "register_query_var"]);
        add_action(
            "init",
            function () {
                if (get_option("moe_aiqna_needs_flush") === "1") {
                    flush_rewrite_rules(false); // 避免改 .htaccess
                    delete_option("moe_aiqna_needs_flush");
                }
            },
            20,
        );
        add_action("template_redirect", [$this, "render_answer_page"]);
        add_action("wp_enqueue_scripts", [$this, "enqueue_styles"]);
    }

    /*** ====== 輔助：輕量語言提示 / 引用規則 / 清洗 ====== ***/
    private static function lang_hint_lite($lang)
    {
        switch ($lang) {
            case "ja":
                return "回答は日本語で簡潔に。外部の事実に依存する場合のみ、確実に覚えている正確なURLの出典を示してください。確信が持てない場合は、出典を記載しないでください。";
            case "zh":
                return "請以繁體中文回答，保持簡潔。只有在答案依賴外部事實且你「能確定正確網址」時，才在文末列出出處；若不確定，請不要引用。";
            case "en":
                return "Answer concisely in English. Only cite when you are sure of the exact URL; if uncertain, do not include any citation.";
            default:
                return "Answer concisely. Only cite when you are sure of the exact URL; if uncertain, do not include any citation.";
        }
    }
    private static function citation_rules_lite()
    {
        return "【引用規則（輕量）】\n" .
            "1) 引用格式:[網域名稱]完整網址,例如 [wikipedia.org]https://...\n" .
            "2) 僅在答案依賴外部可驗證事實，且你『能確定正確網址』時才引用；不得猜測或編造網址。\n" .
            "3) 不要引用本頁原文連結與 moelog.com；不要使用短網址。\n" .
            "4) 最多列 3 筆，優先權威來源（百科、官方、學術、主要媒體）。\n" .
            "5) 若沒有可確認的來源，完全不要輸出「參考資料」。\n";
    }
    private static function sanitize_citations_lite($markdown)
    {
        $lines = preg_split("/\r?\n/", (string) $markdown);
        $out = [];
        $in_refs = false;
        $kept = 0;
        $bad_domains = [
            "moelog.com",
            "bit.ly",
            "t.co",
            "goo.gl",
            "tinyurl.com",
            "ow.ly",
            "is.gd",
            "reurl.cc",
            "shorturl.at",
        ];
        foreach ($lines as $line) {
            $trim = trim($line);
            if (!$in_refs && preg_match('/^參考資料$/u', $trim)) {
                $in_refs = true;
                $out[] = $line;
                continue;
            }
            if ($in_refs) {
                if (
                    !preg_match(
                        '/^\s*-\s*\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)\s*$/i',
                        $trim,
                        $m,
                    )
                ) {
                    $in_refs = false;
                    $out[] = $line;
                    continue;
                }
                $url = $m[2];
                $host = parse_url($url, PHP_URL_HOST);
                $is_bad = false;
                foreach ($bad_domains as $bd) {
                    if (
                        $host === $bd ||
                        substr($host, -strlen("." . $bd)) === "." . $bd
                    ) {
                        $is_bad = true;
                        break;
                    }
                }
                if ($is_bad) {
                    continue;
                }
                if ($kept < 3) {
                    $out[] = $line;
                    $kept++;
                }
                continue;
            }
            $out[] = $line;
        }
        return implode("\n", $out);
    }

    /*** ====== 管理後台 ====== ***/
    public function notice_if_no_key()
    {
        if (!current_user_can("manage_options")) {
            return;
        }
        $settings = get_option(self::OPT_KEY, []);
        $has = defined("MOELOG_AIQNA_API_KEY")
            ? MOELOG_AIQNA_API_KEY
            : $settings["api_key"] ?? "";
        if (empty($has)) {
            echo '<div class="notice notice-warning"><p>';
            echo esc_html__(
                "Moelog AI Q&A 尚未設定 API Key,請至「設定 → Moelog AI Q&A」完成設定。",
                "moelog-ai-qna",
            );
            echo "</p></div>";
        }
    }

    public function add_settings_page()
    {
        add_options_page(
            __("Moelog AI Q&A", "moelog-ai-qna"),
            __("Moelog AI Q&A", "moelog-ai-qna"),
            "manage_options",
            "moelog_aiqna",
            [$this, "settings_page_html"],
        );
    }

    public function register_settings()
    {
        register_setting(self::OPT_KEY, self::OPT_KEY, [
            $this,
            "sanitize_settings",
        ]);

        add_settings_section(
            "general",
            __("一般設定", "moelog-ai-qna"),
            "__return_false",
            self::OPT_KEY,
        );

        // 供應商
        add_settings_field(
            "provider",
            __("AI 供應商", "moelog-ai-qna"),
            function () {
                $o = get_option(self::OPT_KEY, []);
                $val = $o["provider"] ?? "openai";
                ?>
            <select name="<?php echo esc_attr(self::OPT_KEY); ?>[provider]">
                <option value="openai" <?php selected(
                    $val,
                    "openai",
                ); ?>><?php esc_html_e("OpenAI", "moelog-ai-qna"); ?></option>
                <option value="gemini" <?php selected(
                    $val,
                    "gemini",
                ); ?>><?php esc_html_e(
    "Google Gemini",
    "moelog-ai-qna",
); ?></option>
            </select>
        <?php
            },
            self::OPT_KEY,
            "general",
        );

        // API Key
        add_settings_field(
            "api_key",
            __("API Key", "moelog-ai-qna"),
            function () {
                $o = get_option(self::OPT_KEY, []);
                $masked = defined("MOELOG_AIQNA_API_KEY");
                if ($masked) {
                    echo '<input type="password" style="width:420px" value="********" disabled>';
                    echo '<p class="description">' .
                        esc_html__(
                            "已使用 wp-config.php 設定 MOELOG_AIQNA_API_KEY。若要改用此處,請先移除常數定義。",
                            "moelog-ai-qna",
                        ) .
                        "</p>";
                } else {
                    $has_saved = !empty($o["api_key"]);
                    $display = $has_saved ? str_repeat("*", 20) : "";
                    printf(
                        '<input type="password" style="width:420px" name="%s[api_key]" value="%s" placeholder="sk-...">',
                        esc_attr(self::OPT_KEY),
                        esc_attr($display),
                    );
                    echo '<p class="description">' .
                        esc_html__(
                            "如已設定,出於安全僅顯示遮罩;要更換請直接輸入新 Key。",
                            "moelog-ai-qna",
                        ) .
                        "</p>";
                    echo '<p class="description">' .
                        esc_html__(
                            "建議改用 wp-config.php 定義 MOELOG_AIQNA_API_KEY。",
                            "moelog-ai-qna",
                        ) .
                        "</p>";
                }
            },
            self::OPT_KEY,
            "general",
        );

        // 模型
        add_settings_field(
            "model",
            __("模型(OpenAI/Gemini)", "moelog-ai-qna"),
            function () {
                $o = get_option(self::OPT_KEY, []);
                $provider = $o["provider"] ?? "openai";
                $default =
                    $provider === "gemini"
                        ? self::DEFAULT_MODEL_GEMINI
                        : self::DEFAULT_MODEL_OPENAI;
                $val = $o["model"] ?? $default;
                printf(
                    '<input type="text" style="width:320px" name="%s[model]" value="%s" placeholder="%s">',
                    esc_attr(self::OPT_KEY),
                    esc_attr($val),
                    esc_attr($default),
                );
                echo '<p class="description">' .
                    esc_html__(
                        "例:gpt-4o-mini 或 gemini-2.5-flash。",
                        "moelog-ai-qna",
                    ) .
                    "</p>";
            },
            self::OPT_KEY,
            "general",
        );

        // Temperature
        add_settings_field(
            "temperature",
            __("Temperature", "moelog-ai-qna"),
            function () {
                $o = get_option(self::OPT_KEY, []);
                $val = isset($o["temperature"])
                    ? floatval($o["temperature"])
                    : 0.3;
                printf(
                    '<input type="number" step="0.1" min="0" max="2" name="%s[temperature]" value="%s">',
                    esc_attr(self::OPT_KEY),
                    esc_attr($val),
                );
            },
            self::OPT_KEY,
            "general",
        );

        // 是否附上文章內容
        add_settings_field(
            "include_content",
            __("是否附上文章內容給 AI", "moelog-ai-qna"),
            function () {
                $o = get_option(self::OPT_KEY, []);
                $val = !empty($o["include_content"]);
                printf(
                    '<label><input type="checkbox" name="%s[include_content]" value="1" %s> %s</label>',
                    esc_attr(self::OPT_KEY),
                    checked($val, true, false),
                    esc_html__("啟用(可提升貼文脈絡)", "moelog-ai-qna"),
                );
            },
            self::OPT_KEY,
            "general",
        );

        // 截斷長度
        add_settings_field(
            "max_chars",
            __("文章內容截斷長度(字元)", "moelog-ai-qna"),
            function () {
                $o = get_option(self::OPT_KEY, []);
                $val = isset($o["max_chars"]) ? intval($o["max_chars"]) : 6000;
                printf(
                    '<input type="number" min="500" max="20000" name="%s[max_chars]" value="%s">',
                    esc_attr(self::OPT_KEY),
                    esc_attr($val),
                );
            },
            self::OPT_KEY,
            "general",
        );

        // System Prompt
        add_settings_field(
            "system_prompt",
            __("System Prompt(可選)", "moelog-ai-qna"),
            function () {
                $o = get_option(self::OPT_KEY, []);
                $val =
                    $o["system_prompt"] ??
                    __(
                        "你是嚴謹的專業編輯,提供簡潔準確的答案。",
                        "moelog-ai-qna",
                    );
                printf(
                    '<textarea style="width:100%%;max-width:720px;height:100px" name="%s[system_prompt]">%s</textarea>',
                    esc_attr(self::OPT_KEY),
                    esc_textarea($val),
                );
            },
            self::OPT_KEY,
            "general",
        );

        // 問題清單抬頭
        add_settings_field(
            "list_heading",
            __("問題清單抬頭(前台)", "moelog-ai-qna"),
            function () {
                $o = get_option(self::OPT_KEY, []);
                $default = __(
                    "Have more questions? Ask the AI below.",
                    "moelog-ai-qna",
                );
                $val =
                    isset($o["list_heading"]) && $o["list_heading"] !== ""
                        ? $o["list_heading"]
                        : $default;
                printf(
                    '<input type="text" style="width:100%%;max-width:720px" name="%s[list_heading]" value="%s" placeholder="%s">',
                    esc_attr(self::OPT_KEY),
                    esc_attr($val),
                    esc_attr($default),
                );
                echo '<p class="description">' .
                    esc_html__(
                        "會顯示在問題清單上方的標題。可輸入任意語言。",
                        "moelog-ai-qna",
                    ) .
                    "</p>";
            },
            self::OPT_KEY,
            "general",
        );

        // 免責聲明
        add_settings_field(
            "disclaimer_text",
            __("回答頁免責聲明", "moelog-ai-qna"),
            function () {
                $o = get_option(self::OPT_KEY, []);
                $default =
                    "本頁面由AI生成,可能會發生錯誤,請查核重要資訊。\n使用本AI生成內容服務即表示您同意此內容僅供個人參考,且您了解輸出內容可能不準確。\n所有爭議內容{site}保有最終解釋權。";
                $val =
                    isset($o["disclaimer_text"]) && $o["disclaimer_text"] !== ""
                        ? $o["disclaimer_text"]
                        : $default;
                printf(
                    '<textarea style="width:100%%;max-width:720px;height:140px" name="%s[disclaimer_text]">%s</textarea>',
                    esc_attr(self::OPT_KEY),
                    esc_textarea($val),
                );
                echo '<p class="description">' .
                    esc_html__(
                        "支持 {site} 代表網站名稱,亦相容舊式 %s。可多行。",
                        "moelog-ai-qna",
                    ) .
                    "</p>";
            },
            self::OPT_KEY,
            "general",
        );
    }

    public function sanitize_settings($input)
    {
        $prev = get_option(self::OPT_KEY, []);
        $out = [];

        $out["provider"] = in_array(
            $input["provider"] ?? "openai",
            ["openai", "gemini"],
            true,
        )
            ? $input["provider"]
            : "openai";
        $out["model"] = sanitize_text_field(
            $input["model"] ??
                ($out["provider"] === "gemini"
                    ? self::DEFAULT_MODEL_GEMINI
                    : self::DEFAULT_MODEL_OPENAI),
        );
        $out["temperature"] = floatval($input["temperature"] ?? 0.3);
        $out["include_content"] = !empty($input["include_content"]) ? 1 : 0;
        $out["max_chars"] = max(
            500,
            min(20000, intval($input["max_chars"] ?? 6000)),
        );
        $out["system_prompt"] = wp_kses_post($input["system_prompt"] ?? "");

        $default_heading = __(
            "Have more questions? Ask the AI below.",
            "moelog-ai-qna",
        );
        $out["list_heading"] = sanitize_text_field(
            $input["list_heading"] ?? $default_heading,
        );
        if ($out["list_heading"] === "") {
            $out["list_heading"] = $default_heading;
        }

        $default_disclaimer =
            "本頁面由AI生成,可能會發生錯誤,請查核重要資訊。\n使用本AI生成內容服務即表示您同意此內容僅供個人參考,且您了解輸出內容可能不準確。\n所有爭議內容{site}保有最終解釋權。";
        $out["disclaimer_text"] = sanitize_textarea_field(
            $input["disclaimer_text"] ?? $default_disclaimer,
        );
        if ($out["disclaimer_text"] === "") {
            $out["disclaimer_text"] = $default_disclaimer;
        }

        if (defined("MOELOG_AIQNA_API_KEY")) {
            $out["api_key"] = "";
        } else {
            $in = trim($input["api_key"] ?? "");
            if ($in === "" || preg_match('/^\*+$/', $in)) {
                $out["api_key"] = isset($prev["api_key"])
                    ? $prev["api_key"]
                    : "";
            } else {
                $out["api_key"] = $in;
            }
        }
        return $out;
    }

    public function settings_page_html()
    {
        if (!current_user_can("manage_options")) {
            return;
        } ?>
        <div class="wrap">
            <h1><?php esc_html_e("Moelog AI Q&A", "moelog-ai-qna"); ?>
                <span style="font-size:0.6em;color:#999;">v<?php echo esc_html(
                    self::VERSION,
                ); ?></span>
            </h1>

            <!-- 主要設定表單 -->
            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPT_KEY);
                do_settings_sections(self::OPT_KEY);
                submit_button();
                ?>
            </form>

            <!-- 快取管理 -->
            <hr style="margin: 30px 0;">
            <h2><?php esc_html_e("🗑️ 快取管理", "moelog-ai-qna"); ?></h2>
            <div style="background:#f9f9f9; padding:15px; border-left:4px solid #2271b1; margin-bottom:20px;">
                <p style="margin:0;">
                    <strong><?php esc_html_e(
                        "說明:",
                        "moelog-ai-qna",
                    ); ?></strong>
                    <?php esc_html_e(
                        "AI 回答會快取 24 小時。如果發現回答有誤或需要重新生成,可以清除快取。",
                        "moelog-ai-qna",
                    ); ?>
                </p>
            </div>

            <!-- 清除所有快取 -->
            <h3><?php esc_html_e("清除所有快取", "moelog-ai-qna"); ?></h3>
            <form method="post" action="" style="margin-bottom:30px;">
                <?php wp_nonce_field(
                    "moelog_aiqna_clear_cache",
                    "moelog_aiqna_clear_cache_nonce",
                ); ?>
                <p><?php esc_html_e(
                    "這會清除所有文章所有問題的 AI 回答快取。",
                    "moelog-ai-qna",
                ); ?></p>
                <button type="submit" name="moelog_aiqna_clear_cache" class="button button-secondary"
                        onclick="return confirm('<?php echo esc_js(
                            __(
                                "確定要清除所有 AI 回答快取嗎?此操作無法復原。",
                                "moelog-ai-qna",
                            ),
                        ); ?>');">
                    🗑️ <?php esc_html_e("清除所有快取", "moelog-ai-qna"); ?>
                </button>
            </form>
            <?php if (
                isset($_POST["moelog_aiqna_clear_cache"]) &&
                check_admin_referer(
                    "moelog_aiqna_clear_cache",
                    "moelog_aiqna_clear_cache_nonce",
                )
            ) {
                global $wpdb;
                $count = $wpdb->query(
                    "DELETE FROM {$wpdb->options} 
                     WHERE option_name LIKE '_transient_moe_aiqna_%' 
                        OR option_name LIKE '_transient_timeout_moe_aiqna_%'",
                );
                echo '<div class="notice notice-success is-dismissible" style="margin:15px 0;"><p><strong>';
                printf(
                    esc_html__("✅ 成功清除 %d 筆快取記錄!", "moelog-ai-qna"),
                    $count,
                );
                echo "</strong></p></div>";
            } ?>

            <!-- 清除單一快取 -->
            <hr style="margin: 20px 0;">
            <h3><?php esc_html_e("清除單一問題快取", "moelog-ai-qna"); ?></h3>
            <form method="post" action="">
                <?php wp_nonce_field(
                    "moelog_aiqna_clear_single",
                    "moelog_aiqna_clear_single_nonce",
                ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="clear_post_id"><?php esc_html_e(
                            "文章 ID",
                            "moelog-ai-qna",
                        ); ?></label></th>
                        <td>
                            <input type="number" id="clear_post_id" name="post_id" required style="width:150px;" min="1">
                            <p class="description"><?php esc_html_e(
                                "要清除快取的文章 ID(可在文章列表或網址列看到)",
                                "moelog-ai-qna",
                            ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="clear_question"><?php esc_html_e(
                            "問題文字",
                            "moelog-ai-qna",
                        ); ?></label></th>
                        <td>
                            <input type="text" id="clear_question" name="question" required style="width:100%;max-width:500px;">
                            <p class="description"><?php esc_html_e(
                                "輸入完整的問題文字(需與文章中設定的問題完全一致)",
                                "moelog-ai-qna",
                            ); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="moelog_aiqna_clear_single" class="button button-secondary">
                        🗑️ <?php esc_html_e(
                            "清除此問題快取",
                            "moelog-ai-qna",
                        ); ?>
                    </button>
                </p>
            </form>
            <?php if (
                isset($_POST["moelog_aiqna_clear_single"]) &&
                check_admin_referer(
                    "moelog_aiqna_clear_single",
                    "moelog_aiqna_clear_single_nonce",
                )
            ) {
                $post_id = intval($_POST["post_id"]);
                $question = sanitize_text_field($_POST["question"]);
                if ($post_id && $question) {
                    global $wpdb;
                    $partial_hash = substr(
                        hash("sha256", $post_id . "|" . $question),
                        0,
                        32,
                    );
                    $pattern = "%moe_aiqna_%" . $partial_hash . "%";
                    $count = $wpdb->query(
                        $wpdb->prepare(
                            "DELETE FROM {$wpdb->options}
                             WHERE option_name LIKE %s
                                OR option_name LIKE %s",
                            "_transient" . $pattern,
                            "_transient_timeout" . $pattern,
                        ),
                    );
                    echo '<div class="notice notice-success is-dismissible" style="margin:15px 0;"><p>';
                    if ($count > 0) {
                        echo "<strong>";
                        printf(
                            esc_html__(
                                "✅ 成功清除 %d 筆相關快取!(文章 ID: %d)",
                                "moelog-ai-qna",
                            ),
                            $count,
                            $post_id,
                        );
                        echo "</strong><br>";
                        printf(
                            esc_html__("問題: %s", "moelog-ai-qna"),
                            "<code>" . esc_html($question) . "</code>",
                        );
                    } else {
                        echo "<strong>";
                        esc_html_e("⚠️ 未找到相關快取", "moelog-ai-qna");
                        echo "</strong><br>";
                        esc_html_e(
                            "可能原因:快取已過期、問題文字不符、或該問題從未被訪問過。",
                            "moelog-ai-qna",
                        );
                    }
                    echo "</p></div>";
                } else {
                    echo '<div class="notice notice-error is-dismissible" style="margin:15px 0;"><p>';
                    esc_html_e(
                        "❌ 請填寫完整的文章 ID 和問題文字。",
                        "moelog-ai-qna",
                    );
                    echo "</p></div>";
                }
            } ?>

            <!-- 使用說明 -->
            <hr style="margin: 30px 0;">
            <p><strong><?php esc_html_e(
                "ℹ️ 使用說明:",
                "moelog-ai-qna",
            ); ?></strong></p>
            <ol>
                <li><?php esc_html_e(
                    "在「設定 → Moelog AI Q&A」填入 API Key / 模型等。",
                    "moelog-ai-qna",
                ); ?></li>
                <li><?php esc_html_e(
                    "編輯文章時,於右側/下方的「AI 問題清單」每行輸入一題並選擇語言(可選自動)。",
                    "moelog-ai-qna",
                ); ?></li>
                <li><?php esc_html_e(
                    "前台文章底部會顯示問題列表(抬頭可自訂)。點擊後開新分頁顯示 AI 答案與免責聲明(可自訂)。",
                    "moelog-ai-qna",
                ); ?></li>
<li><?php esc_html_e(
    "或使用短碼 [moelog_aiqna] 手動插入問題清單。",
    "moelog-ai-qna",
); ?></li>
<li><?php esc_html_e(
    '也可用 [moelog_aiqna index="3"] 將第 3 題單獨放在任意段落(index 範圍 1-8)。',
    "moelog-ai-qna",
); ?></li>

            </ol>

            <!-- 版本資訊 -->
<p><strong><?php esc_html_e("🆕 v1.6.2 更新:", "moelog-ai-qna"); ?></strong></p>
<ul style="list-style-type:circle; margin-left:20px;">
    <li>✅ 修正 shortcode 重複顯示問題</li>
    <li>✅ 支援單題顯示: [moelog_aiqna index="N"]</li>
    <li>✅ 優化預抓取腳本,避免重複注入</li>
    <li>✅ 改善 shortcode 使用體驗</li>
</ul>
        </div>
        <?php
    }

    public function add_questions_metabox()
    {
        add_meta_box(
            "moelog_aiqna_box",
            __("AI 問題清單(每行一題;會顯示在文章底部)", "moelog-ai-qna"),
            [$this, "questions_metabox_html"],
            ["post", "page"],
            "normal",
            "default",
        );
    }

    public function questions_metabox_html($post)
    {
        $questions = get_post_meta($post->ID, self::META_KEY, true);
        $langs = get_post_meta($post->ID, self::META_KEY_LANG, true);
        $langs = is_array($langs) ? $langs : [];
        $questions = $questions ? explode("\n", $questions) : [""];
        wp_nonce_field("moelog_aiqna_save", "moelog_aiqna_nonce");
        ?>
        <div id="moelog-aiqna-questions">
            <div id="moelog-aiqna-rows">
                <?php foreach ($questions as $i => $q): ?>
                <div class="moe-question-row" style="margin-bottom:10px; display:flex; align-items:flex-end;">
                    <textarea style="width:70%; min-height:60px; flex-grow:1; line-height:1.2em;" name="moelog_aiqna_questions[]" placeholder="<?php esc_attr_e(
                        "例:為何科技新創偏好使用「.io」?",
                        "moelog-ai-qna",
                    ); ?>"><?php echo esc_textarea($q); ?></textarea>
                    <select name="moelog_aiqna_langs[]" style="margin-left:10px; min-width:120px;">
                        <option value="auto" <?php selected(
                            $langs[$i] ?? "auto",
                            "auto",
                        ); ?>><?php esc_html_e(
    "自動偵測",
    "moelog-ai-qna",
); ?></option>
                        <option value="zh"   <?php selected(
                            $langs[$i] ?? "",
                            "zh",
                        ); ?>><?php esc_html_e(
    "繁體中文",
    "moelog-ai-qna",
); ?></option>
                        <option value="ja"   <?php selected(
                            $langs[$i] ?? "",
                            "ja",
                        ); ?>><?php esc_html_e(
    "日文",
    "moelog-ai-qna",
); ?></option>
                        <option value="en"   <?php selected(
                            $langs[$i] ?? "",
                            "en",
                        ); ?>><?php esc_html_e(
    "英文",
    "moelog-ai-qna",
); ?></option>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>
<p id="moelog-aiqna-help" style="color:#666; margin-top:6px;">
    <?php esc_html_e(
        "提示:每行一題,建議 3—8 題,每題最多 200 字。語言選擇「自動偵測」時,AI 會根據問題文字判斷語言。",
        "moelog-ai-qna",
    ); ?>
    <br>
    <strong style="color:#2271b1;">
    <?php esc_html_e(
        "💡 使用 [moelog_aiqna index=\"1\"] 可單獨插入第 1 題,以此類推。",
        "moelog-ai-qna",
    ); ?>
    </strong>
</p>
            <button type="button" id="moelog-aiqna-add-btn" class="button"><?php esc_html_e(
                "新增問題",
                "moelog-ai-qna",
            ); ?></button>
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
                row.innerHTML = `<textarea style="width:70%; min-height:60px; flex-grow:1; line-height:1.2em;" name="moelog_aiqna_questions[]" placeholder="<?php echo esc_attr__(
                    "例:為何科技新創偏好使用「.io」?",
                    "moelog-ai-qna",
                ); ?>"></textarea>
                                 <select name="moelog_aiqna_langs[]" style="margin-left:10px; min-width:120px;">
                                   <option value="auto"><?php echo esc_html__(
                                       "自動偵測",
                                       "moelog-ai-qna",
                                   ); ?></option>
                                   <option value="zh"><?php echo esc_html__(
                                       "繁體中文",
                                       "moelog-ai-qna",
                                   ); ?></option>
                                   <option value="ja"><?php echo esc_html__(
                                       "日文",
                                       "moelog-ai-qna",
                                   ); ?></option>
                                   <option value="en"><?php echo esc_html__(
                                       "英文",
                                       "moelog-ai-qna",
                                   ); ?></option>
                                 </select>`;
                return row;
            }
            addBtn.addEventListener('click', function() { rowsBox.appendChild(makeRow()); });
        })();
        </script>
        <?php
    }

    public function save_questions_meta($post_id)
    {
        if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can("edit_post", $post_id)) {
            return;
        }
        if (!isset($_POST["moelog_aiqna_nonce"])) {
            return;
        }
        check_admin_referer("moelog_aiqna_save", "moelog_aiqna_nonce");

        $questions = isset($_POST["moelog_aiqna_questions"])
            ? (array) $_POST["moelog_aiqna_questions"]
            : [];
        $langs = isset($_POST["moelog_aiqna_langs"])
            ? (array) $_POST["moelog_aiqna_langs"]
            : [];
        $lines = [];
        $lang_data = [];

        foreach ($questions as $i => $q) {
            $q = trim(wp_unslash($q));
            if (empty($q)) {
                continue;
            }
            if (function_exists("mb_substr")) {
                $q = mb_substr($q, 0, 200, "UTF-8");
            } else {
                $q = substr($q, 0, 200);
            }
            $lines[] = $q;
            $lang_data[] = in_array(
                $langs[$i] ?? "auto",
                ["auto", "zh", "ja", "en"],
                true,
            )
                ? $langs[$i] ?? "auto"
                : "auto";
        }
        $lines = array_slice($lines, 0, 8);
        $lang_data = array_slice($lang_data, 0, 8);
        update_post_meta($post_id, self::META_KEY, implode("\n", $lines));
        update_post_meta($post_id, self::META_KEY_LANG, $lang_data);
    }

    public function append_questions_block($content)
    {
        if (!is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        // 只要本文使用過 [moelog_aiqna]（包含 index 參數或多次使用），就不要再自動附加整份清單
        if (has_shortcode($content, "moelog_aiqna")) {
            return $content;
        }

        return $content . $this->get_questions_block(get_the_ID());
    }

    public function shortcode_questions_block($atts)
    {
        $post_id = get_the_ID();
        if (!$post_id) {
            return "";
        }

        $atts = shortcode_atts(
            [
                "index" => "", // 1–8。空白=顯示整份清單
            ],
            $atts,
            "moelog_aiqna",
        );

        $idx = intval($atts["index"]);
        if ($idx >= 1 && $idx <= 8) {
            // 只輸出第 N 題（單一連結）
            return $this->shortcode_single_question($post_id, $idx);
        }

        // 預設：輸出整份清單
        return $this->get_questions_block($post_id);
    }

    /*** ====== 產生簡短 slug ====== ***/
    private function extract_meaningful_chars($text, $max_len = 3)
    {
        $text = trim((string) $text);
        if (empty($text)) {
            return "q";
        }

        if (preg_match_all("/\b[A-Z]{2,}\b/", $text, $m)) {
            $abbr = strtolower(implode("", $m[0]));
            if (strlen($abbr) >= 2) {
                return substr($abbr, 0, $max_len);
            }
        }

        if (preg_match_all("/\b[A-Za-z]+\b/", $text, $m)) {
            $initials = "";
            foreach ($m[0] as $word) {
                if (strlen($word) >= 2) {
                    $initials .= strtolower($word[0]);
                }
            }
            if (strlen($initials) >= 2) {
                return substr($initials, 0, $max_len);
            }
        }

        return "q" . substr(md5($text), 0, 3);
    }

    private function slugify_question_v150($q, $post_id)
    {
        $abbr = $this->extract_meaningful_chars($q, 3);
        $salt = substr(hash_hmac("sha256", $q, $this->secret), 0, 3);
        return $abbr . "-" . $salt . "-" . $post_id;
    }

    // 對外 URL 生成器（GEO ready）
    public function build_answer_url($post_id, $question)
    {
        $slug = $this->slugify_question_v150($question, $post_id);
        return user_trailingslashit(home_url(self::PRETTY_BASE . "/" . $slug));
    }

    public function slugify_question_public($q, $post_id, $max = 40)
    {
        return $this->slugify_question_v150($q, $post_id);
    }

    /*** ====== 其他工具 ====== ***/
    private function build_token($post_id, $q)
    {
        $raw = hash_hmac("sha256", $post_id . "|" . $q, $this->secret, true);
        return rtrim(strtr(base64_encode($raw), "+/", "-_"), "=");
    }

    private function client_ip()
    {
        if (!empty($_SERVER["HTTP_CF_CONNECTING_IP"])) {
            return $_SERVER["HTTP_CF_CONNECTING_IP"];
        }
        if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            return trim(explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"])[0]);
        }
        return $_SERVER["REMOTE_ADDR"] ?? "0.0.0.0";
    }

    private function get_questions_block($post_id)
    {
        $raw = get_post_meta($post_id, self::META_KEY, true);
        $langs = get_post_meta($post_id, self::META_KEY_LANG, true);
        $langs = is_array($langs) ? $langs : [];
        if (!$raw) {
            return "";
        }

        $lines = array_filter(
            array_map("trim", preg_split('/\r\n|\n|\r/', $raw)),
        );
        if (empty($lines)) {
            return "";
        }

        $o = get_option(self::OPT_KEY, []);
        $default_heading = __(
            "Have more questions? Ask the AI below.",
            "moelog-ai-qna",
        );
        $heading =
            isset($o["list_heading"]) && $o["list_heading"] !== ""
                ? $o["list_heading"]
                : $default_heading;
        $heading = apply_filters("moelog_aiqna_list_heading", $heading);

        $items = "";
        foreach ($lines as $idx => $q) {
            $slug = $this->slugify_question_v150($q, $post_id);
            $pretty = user_trailingslashit(
                home_url(self::PRETTY_BASE . "/" . $slug),
            );
            $items .= sprintf(
                '<li><a class="moe-aiqna-link" target="_blank" rel="noopener" href="%s" data-lang="%s">%s</a></li>',
                esc_url($pretty),
                esc_attr($langs[$idx] ?? "auto"),
                esc_html($q),
            );
        }

        $prefetch_js = <<<HTML
        <script>
        (function(){
          if (navigator.connection && navigator.connection.saveData) return;
          var links = document.querySelectorAll('.moe-aiqna-link');
          var timer;
          function prefetch(url){
            try{
              var u = new URL(url);
              u.searchParams.set('pf','1');
              var l = document.createElement('link');
              l.rel='prefetch'; l.as='document'; l.href=u.toString();
              document.head.appendChild(l);
            }catch(e){}
          }
          var enterEvt = ('PointerEvent' in window) ? 'pointerenter' : 'mouseenter';
          var leaveEvt = ('PointerEvent' in window) ? 'pointerleave' : 'mouseleave';
          links.forEach(function(a){
            a.addEventListener(enterEvt, function(){ timer = setTimeout(function(){ prefetch(a.href); }, 100); });
            a.addEventListener(leaveEvt,  function(){ if (timer) clearTimeout(timer); });
          });
        })();
        </script>
        HTML;

        return sprintf(
            '<div class="moe-aiqna-block"><h3>%s</h3><ul>%s</ul></div>'',
            esc_html($heading),
            $items,
            $prefetch_js,
        );
    }
    private function shortcode_single_question($post_id, $index)
    {
        $raw = get_post_meta($post_id, self::META_KEY, true);
        if (!$raw) {
            return "<!-- Moelog AI Q&A: 尚未設定問題 -->";
        }

        $questions = array_values(
            array_filter(array_map("trim", preg_split('/\r\n|\n|\r/', $raw))),
        );
        if ($index < 1 || $index > count($questions)) {
            return sprintf(
                "<!-- Moelog AI Q&A: 第 %d 題不存在 (共 %d 題) -->",
                $index,
                count($questions),
            );
        }

        // ✅ 正確順序:先取得問題文字
        $q = $questions[$index - 1];

        // ✅ 讀取語言設定
        $langs = get_post_meta($post_id, self::META_KEY_LANG, true);
        $langs = is_array($langs) ? $langs : [];
        $lang = $langs[$index - 1] ?? "auto";

        // ✅ 現在才生成 URL (因為 $q 已定義)
        $url = $this->build_answer_url($post_id, $q);

        // 單題連結（沿用 .moe-aiqna-link，樣式與預抓取一致）
        $html = sprintf(
            '<a class="moe-aiqna-link" target="_blank" rel="noopener" href="%s" data-lang="%s"><h3>%s</h3></a>',
            esc_url($url),
            esc_attr($lang),
            esc_html($q),
        );

        // 確保頁面上至少注入一次預抓取腳本
        $html .= $this->ensure_prefetch_script_once();

        return $html;
    }

    private function ensure_prefetch_script_once()
    {
        if ($this->prefetch_injected) {
            return "";
        }
        $this->prefetch_injected = true;

        return <<<HTML
        <script>
        (function(){
          if (navigator.connection && navigator.connection.saveData) return;

          function prefetch(url){
            try{
              var u = new URL(url);
              u.searchParams.set('pf','1'); // 讓回答頁走 204 預取
              var l = document.createElement('link');
              l.rel = 'prefetch';
              l.as  = 'document';
              l.href = u.toString();
              document.head.appendChild(l);
            }catch(e){}
          }

          var enterEvt = ('PointerEvent' in window) ? 'pointerenter' : 'mouseenter';
          var leaveEvt = ('PointerEvent' in window) ? 'pointerleave'  : 'mouseleave';
          var timer;

          function bind(a){
            if (a.__moeBound) return;
            a.addEventListener(enterEvt, function(){ timer = setTimeout(function(){ prefetch(a.href); }, 100); });
            a.addEventListener(leaveEvt,  function(){ if (timer) clearTimeout(timer); });
            a.__moeBound = true;
          }

          // 綁定現有
          document.querySelectorAll('.moe-aiqna-link').forEach(bind);

          // 動態加入時也嘗試綁定
          var mo = new MutationObserver(function(){
            document.querySelectorAll('.moe-aiqna-link').forEach(bind);
          });
          mo.observe(document.documentElement, {subtree:true, childList:true});
        })();
        </script>
        HTML;
    }

    public function enqueue_styles()
    {
        $is_answer_page =
            !empty($_GET["moe_ai"]) ||
            (isset($_SERVER["REQUEST_URI"]) &&
                (preg_match(
                    "#/" . self::PRETTY_BASE . "/#",
                    $_SERVER["REQUEST_URI"],
                ) ||
                    preg_match("#/ai-answer/#", $_SERVER["REQUEST_URI"])));
        $is_single_main = is_singular() && is_main_query();
        if ($is_answer_page || $is_single_main) {
            // 若確定有 assets/style.css 再保留；否則可移除
            wp_enqueue_style(
                "moelog-aiqna",
                plugin_dir_url(__FILE__) . "assets/style.css",
                [],
                self::VERSION,
            );
        }
    }

    public function register_query_var()
    {
        add_rewrite_tag("%moe_ai%", "([^&]+)");
        add_rewrite_tag("%post_id%", "([0-9]+)");
        add_rewrite_tag("%q%", "(.+)");
        add_rewrite_tag("%lang%", "([a-z]+)");
        add_rewrite_tag("%_nonce%", "(.+)");
        add_rewrite_tag("%ts%", "([0-9]+)");
        add_rewrite_tag("%sig%", "([A-Fa-f0-9]{64})");

        add_rewrite_tag("%k%", "([A-Za-z0-9_\-=]+)");
        add_rewrite_tag("%slug%", "([^/]+)");

        // v1.5.x 簡短 slug
        add_rewrite_rule(
            "^" . self::PRETTY_BASE . '/([a-z0-9]+)-([a-f0-9]{3})-([0-9]+)/?$',
            'index.php?moe_ai=1&v150_slug=$matches[1]&v150_hash=$matches[2]&post_id=$matches[3]',
            "top",
        );
        add_rewrite_tag("%v150_slug%", "([a-z0-9]+)");
        add_rewrite_tag("%v150_hash%", "([a-f0-9]{3})");

        // 舊版相容
        add_rewrite_rule(
            '^ai-answer/([^/]+)-([0-9]+)/?$',
            'index.php?moe_ai=1&slug=$matches[1]&post_id=$matches[2]',
            "top",
        );
    }

    /*** ====== 回答頁輸出 ====== ***/
    public function render_answer_page()
    {
        $req_uri = $_SERVER["REQUEST_URI"] ?? "";
        $is_pretty =
            strpos($req_uri, "/" . self::PRETTY_BASE . "/") !== false ||
            strpos($req_uri, "/ai-answer/") !== false;
        if (!isset($_GET["moe_ai"]) && !$is_pretty) {
            return;
        }

        header("Referrer-Policy: strict-origin-when-cross-origin");

        // 爬蟲擋
        $ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
        $bot_patterns = apply_filters("moelog_aiqna_blocked_bots", [
            "bot",
            "crawl",
            "spider",
            "scrape",
            "curl",
            "wget",
            "Googlebot",
            "Bingbot",
            "Baiduspider",
            "facebookexternalhit",
        ]);
        foreach ($bot_patterns as $pattern) {
            if (stripos($ua, $pattern) !== false) {
                status_header(403);
                exit("Bots are not allowed");
            }
        }

        // 預取 ping
        if (isset($_GET["pf"]) && $_GET["pf"] === "1") {
            status_header(204);
            header("Cache-Control: private, max-age=300");
            return;
        }

        $post_id = isset($_GET["post_id"])
            ? intval($_GET["post_id"])
            : (get_query_var("post_id") ?:
            0);
        $question = "";
        $lang = isset($_GET["lang"])
            ? sanitize_text_field($_GET["lang"])
            : "auto";

        // v1.5 簡短 slug 邏輯
        $v150_slug = get_query_var("v150_slug");
        $v150_hash = get_query_var("v150_hash");
        if ($post_id && $v150_slug && $v150_hash) {
            $raw = get_post_meta($post_id, self::META_KEY, true);
            $candidates = array_filter(
                array_map("trim", preg_split('/\r\n|\n|\r/', (string) $raw)),
            );
            $candidates = array_slice($candidates, 0, 8);
            $langs_meta = get_post_meta($post_id, self::META_KEY_LANG, true);
            $langs_meta = is_array($langs_meta) ? $langs_meta : [];
            foreach ($candidates as $idx => $qc) {
                $test_slug = $this->slugify_question_v150($qc, $post_id);
                if (
                    $test_slug ===
                    $v150_slug . "-" . $v150_hash . "-" . $post_id
                ) {
                    $question = $qc;
                    $lang = $langs_meta[$idx] ?? "auto";
                    break;
                }
            }
            if ($question === "") {
                wp_die(
                    __("連結已過期或無效,請回原文重新點擊。", "moelog-ai-qna"),
                    __("錯誤", "moelog-ai-qna"),
                    ["response" => 410],
                );
            }
        }

        // 舊版相容 token
        $token = isset($_GET["k"]) ? sanitize_text_field($_GET["k"]) : "";
        $slug = get_query_var("slug") ?: "";
        if ($post_id && !$question && $token) {
            $raw = get_post_meta($post_id, self::META_KEY, true);
            $candidates = array_filter(
                array_map("trim", preg_split('/\r\n|\n|\r/', (string) $raw)),
            );
            $candidates = array_slice($candidates, 0, 8);
            $langs_meta = get_post_meta($post_id, self::META_KEY_LANG, true);
            $langs_meta = is_array($langs_meta) ? $langs_meta : [];
            foreach ($candidates as $idx => $qc) {
                $tokenCand = $this->build_token($post_id, $qc);
                if (hash_equals($tokenCand, (string) $token)) {
                    $question = $qc;
                    $lang = $langs_meta[$idx] ?? "auto";
                    break;
                }
            }
            if ($question === "") {
                wp_die(
                    __("連結已過期或無效,請回原文重新點擊。", "moelog-ai-qna"),
                    __("錯誤", "moelog-ai-qna"),
                    ["response" => 410],
                );
            }
        }

        // 舊直連參數校驗
        if (!$question) {
            $question = isset($_GET["q"]) ? wp_unslash($_GET["q"]) : "";
            $nonce = isset($_GET["_nonce"])
                ? sanitize_text_field($_GET["_nonce"])
                : "";
            $ts = isset($_GET["ts"]) ? intval($_GET["ts"]) : 0;
            $sig = isset($_GET["sig"]) ? sanitize_text_field($_GET["sig"]) : "";
            if ($post_id && $question && $nonce && $ts && $sig) {
                if (abs(time() - $ts) > MINUTE_IN_SECONDS * 15) {
                    wp_die(
                        __(
                            "連結已過期,請回原文重新點擊問題。",
                            "moelog-ai-qna",
                        ),
                        __("錯誤", "moelog-ai-qna"),
                        ["response" => 403],
                    );
                }
                $act =
                    self::NONCE_ACTION .
                    "|" .
                    $post_id .
                    "|" .
                    substr(
                        hash_hmac("sha256", (string) $post_id, $this->secret),
                        0,
                        12,
                    );
                if (!wp_verify_nonce($nonce, $act)) {
                    wp_die(
                        __("連結驗證失敗或已過期。", "moelog-ai-qna"),
                        __("錯誤", "moelog-ai-qna"),
                        ["response" => 403],
                    );
                }
                $expect = hash_hmac(
                    "sha256",
                    $post_id . "|" . $question . "|" . $ts,
                    $this->secret,
                );
                if (!hash_equals($expect, $sig)) {
                    wp_die(
                        __("簽章檢核失敗。", "moelog-ai-qna"),
                        __("錯誤", "moelog-ai-qna"),
                        ["response" => 403],
                    );
                }
            }
        }

        if (!$post_id || $question === "") {
            wp_die(
                __("參數錯誤或連結已失效。", "moelog-ai-qna"),
                __("錯誤", "moelog-ai-qna"),
                ["response" => 400],
            );
        }

        $settings = get_option(self::OPT_KEY, []);
        $provider = $settings["provider"] ?? "openai";
        $api_key = defined("MOELOG_AIQNA_API_KEY")
            ? MOELOG_AIQNA_API_KEY
            : $settings["api_key"] ?? "";
        $default_model =
            $provider === "gemini"
                ? self::DEFAULT_MODEL_GEMINI
                : self::DEFAULT_MODEL_OPENAI;
        $model = $settings["model"] ?? $default_model;
        $temp = isset($settings["temperature"])
            ? floatval($settings["temperature"])
            : 0.3;
        $system = $settings["system_prompt"] ?? "";
        $include = !empty($settings["include_content"]);
        $max_chars = isset($settings["max_chars"])
            ? intval($settings["max_chars"])
            : 6000;

        $post = get_post($post_id);
        if (!$post) {
            wp_die(
                __("找不到文章。", "moelog-ai-qna"),
                __("錯誤", "moelog-ai-qna"),
                ["response" => 404],
            );
        }

        $context = "";
        if ($include) {
            $raw =
                $post->post_title .
                "\n\n" .
                strip_shortcodes($post->post_content);
            $raw = wp_strip_all_tags($raw);
            $raw = preg_replace("/\s+/u", " ", $raw);
            if (function_exists("mb_strlen") && function_exists("mb_strcut")) {
                $context =
                    mb_strlen($raw, "UTF-8") > $max_chars
                        ? mb_strcut($raw, 0, $max_chars * 4, "UTF-8")
                        : $raw;
            } elseif (
                function_exists("mb_strlen") &&
                function_exists("mb_substr")
            ) {
                $context =
                    mb_strlen($raw, "UTF-8") > $max_chars
                        ? mb_substr($raw, 0, $max_chars, "UTF-8")
                        : $raw;
            } else {
                $context =
                    strlen($raw) > $max_chars
                        ? substr($raw, 0, $max_chars)
                        : $raw;
            }
        }

        $context_hash = substr(hash("sha256", $context), 0, 32);
        $cache_key =
            "moe_aiqna_" .
            hash(
                "sha256",
                $post_id .
                    "|" .
                    $question .
                    "|" .
                    $model .
                    "|" .
                    $context_hash .
                    "|" .
                    $lang,
            );
        $ip = $this->client_ip();
        $freq_key =
            "moe_aiqna_freq_" . md5($ip . "|" . $post_id . "|" . $question);
        $ip_key = "moe_aiqna_ip_" . md5($ip);

        // 讀快取
        $cached_answer = get_transient($cache_key);
        if ($cached_answer !== false) {
            $this->render_answer_html($post_id, $question, $cached_answer);
            exit();
        }

        // 頻率限制
        if (get_transient($freq_key)) {
            wp_die(
                __("請求過於頻繁,請稍後再試。", "moelog-ai-qna"),
                __("錯誤", "moelog-ai-qna"),
                ["response" => 429],
            );
        }
        $ip_cnt = (int) get_transient($ip_key);
        if ($ip_cnt >= 10) {
            if (defined("WP_DEBUG") && WP_DEBUG) {
                error_log(
                    "[Moelog AIQnA] Rate limit hit: IP {$ip}, Post {$post_id}, Question: " .
                        substr($question, 0, 50),
                );
            }
            wp_die(
                __("請求過於頻繁,請稍後再試。", "moelog-ai-qna"),
                __("錯誤", "moelog-ai-qna"),
                ["response" => 429],
            );
        }
        set_transient($ip_key, $ip_cnt + 1, HOUR_IN_SECONDS);
        set_transient($freq_key, 1, self::RATE_TTL);

        // 呼叫 AI
        $answer = $this->call_ai($provider, [
            "api_key" => $api_key,
            "model" => $model,
            "temperature" => $temp,
            "system" => $system,
            "question" => $question,
            "context" => $context,
            "url" => get_permalink($post_id),
            "post_id" => $post_id,
            "lang" => $lang,
            "cache_key" => $cache_key,
        ]);

        $this->render_answer_html($post_id, $question, $answer);
        exit();
    }

    private function render_answer_html($post_id, $question, $answer)
    {
        $GLOBALS["moe_aiqna_is_answer_page"] = true;
        status_header(200);
        header(
            "Cache-Control: public, max-age=0, s-maxage=" .
                self::CACHE_TTL .
                ", stale-while-revalidate=60",
        );
        header("Vary: Accept-Encoding, User-Agent");

        $nonce = $this->csp_nonce;
        $csp_report_uri = apply_filters("moelog_aiqna_csp_report_uri", "");
        $csp =
            "default-src 'self'; " .
            "img-src 'self' data:; " .
            "style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; " .
            "font-src 'self' https://fonts.gstatic.com data:; " .
            "connect-src 'self'; " .
            "script-src 'self' 'nonce-{$nonce}'; " .
            "frame-ancestors 'none'; base-uri 'self'; form-action 'self'";
        if (!empty($csp_report_uri)) {
            $csp .= "; report-uri {$csp_report_uri}";
        }
        header("Content-Security-Policy: {$csp}");

        $slug = $this->slugify_question_v150($question, $post_id);
        $canonical = user_trailingslashit(
            home_url(self::PRETTY_BASE . "/" . $slug),
        );
        ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo("charset"); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1" />
<?php $robots = apply_filters(
    "moelog_aiqna_answer_robots",
    "noindex,nofollow",
); ?>
<meta name="robots" content="<?php echo esc_attr($robots); ?>">
<link rel="canonical" href="<?php echo esc_url($canonical); ?>" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DotGothic16&family=Noto+Sans+JP&family=Noto+Sans+TC:wght@100..900&family=Press+Start+2P&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url(
    plugin_dir_url(__FILE__) . "assets/style.css",
); ?>?ver=<?php echo esc_attr(self::VERSION); ?>">
<link rel="stylesheet" href="<?php echo esc_url(
    get_stylesheet_uri(),
); ?>?ver=<?php echo esc_attr(wp_get_theme()->get("Version")); ?>">
<title><?php echo esc_html(
    get_the_title($post_id),
); ?> - <?php esc_html_e("AI 解答", "moelog-ai-qna"); ?></title>
<?php
$answer_url = $this->build_answer_url($post_id, $question);
// GEO 模組可在此插入結構化資料
do_action(
    "moelog_aiqna_answer_head",
    $answer_url,
    $post_id,
    $question,
    $answer,
);
?>
<style nonce="<?php echo esc_attr($nonce); ?>">
.moe-typing-cursor{display:inline-block;width:1px;background:#999;margin-left:2px;animation:moe-blink 1s step-end infinite;vertical-align:baseline;}
@keyframes moe-blink{50%{background:transparent;}}
.moe-answer-wrap{word-wrap:break-word;word-break:break-word;overflow-wrap:anywhere;max-width:100%;}
.moe-answer-wrap p,.moe-answer-wrap li{word-wrap:break-word;word-break:break-word;overflow-wrap:anywhere;}
.moe-answer-wrap code,.moe-answer-wrap pre{word-wrap:break-word;word-break:break-all;overflow-wrap:anywhere;white-space:pre-wrap;max-width:100%;}
.moe-original-section {padding: 0 45px  0  45px;font-size: 120%;letter-spacing: 0.5px;color: #666;}
.moe-original-link {color: #94B800;text-decoration: none;}
.moe-original-link:hover {color: #69644e; text-decoration: underline;}
@media (max-width: 768px) {.moe-original-section {padding: 0 20px;}.moe-original-url { font-size: 0.85em;}}
</style>
<script nonce="<?php echo esc_attr($nonce); ?>">
function moelogClosePage(){
  try{
    window.close();
    setTimeout(function(){ try{window.open('','_self');}catch(e){} try{window.close();}catch(e){} },50);
    setTimeout(function(){
      var fb = document.getElementById('moelog-fallback');
      if(!window.closed){
        if(history.length>1){ history.back(); }
        else { if(fb) fb.style.display='block'; }
      }
    },300);
  }catch(e){
    var fb = document.getElementById('moelog-fallback');
    if(fb) fb.style.display='block';
  }
  return false;
}
document.addEventListener('DOMContentLoaded', function(){
  var btn = document.getElementById('moe-close-btn');
  if(btn){ btn.addEventListener('click', function(e){ e.preventDefault(); moelogClosePage(); }); }
});
</script>
</head>
<body class="moe-aiqna-answer">
<div class="moe-container">
<?php
$banner_url = apply_filters("moelog_aiqna_banner_url", "");
$banner_alt = apply_filters("moelog_aiqna_banner_alt", get_bloginfo("name"));
?>
<div class="moe-banner" <?php if (
    $banner_url
): ?>style="background-image:url('<?php echo esc_url(
    $banner_url,
); ?>')"<?php endif; ?> role="img" aria-label="<?php echo esc_attr($banner_alt); ?>"></div>

<!-- QUESTION bar（顯示 Q: 問題文字） -->
<div class="moe-answer-wrap">
  <div class="moe-question-echo"><?php echo esc_html($question); ?></div>
<?php
$allowed = [
    "p" => [],
    "ul" => [],
    "ol" => [],
    "li" => [],
    "strong" => [],
    "em" => [],
    "br" => [],
    "span" => ["class" => [], "title" => []],
];
$safe_html = $answer ? wp_kses(wpautop($answer), $allowed) : "";
$safe_html = preg_replace(
    "/<(\w+)\s+[^>]*on\w+\s*=\s*[^>]*>/i",
    '<$1>',
    $safe_html,
);
$safe_html = preg_replace_callback(
    '/(https?:\/\/[^\s<>"]+)/i',
    function ($m) {
        $url = urldecode($m[1]);
        return '<span class="moe-url" title="' .
            esc_attr($url) .
            '">' .
            esc_html($url) .
            "</span>";
    },
    $safe_html,
);
?>
  <div id="moe-ans-target"></div>
  <template id="moe-ans-source"><?php echo $safe_html; ?></template>
<div class="moe-original-section">
    <?php esc_html_e("原文連結", "moelog-ai-qna"); ?><br>
    <?php
    // 取得乾淨的域名 (移除 www.)
    $domain = parse_url(home_url(), PHP_URL_HOST);
    $clean_domain = preg_replace("/^www\./", "", $domain);

    // 取得文章網址
    $permalink = get_permalink($post_id);

    // 顯示用的乾淨網址 (移除 www.)
    $display_url = urldecode($permalink);
    ?>
    [<?php echo esc_html($clean_domain); ?>]
    <a href="<?php echo esc_url($permalink); ?>" 
       target="_blank" 
       rel="noopener noreferrer"
       class="moe-original-link">
        <?php echo esc_html($display_url); ?>
    </a>
</div>
  <noscript><?php echo $safe_html
      ? $safe_html
      : "<p>" .
          esc_html__(
              "抱歉,目前無法取得 AI 回答,請稍後再試。",
              "moelog-ai-qna",
          ) .
          "</p>"; ?></noscript>
  <script nonce="<?php echo esc_attr($nonce); ?>">
  (function(){
    const srcTpl = document.getElementById('moe-ans-source');
    const target = document.getElementById('moe-ans-target');
    if(!srcTpl||!target) return;
    const ALLOWED = new Set(['P','UL','OL','LI','STRONG','EM','BR','SPAN']);
    const SPEED = 18;
    function cloneShallow(node){
      if(node.nodeType===Node.TEXT_NODE) return document.createTextNode('');
      if(node.nodeType===Node.ELEMENT_NODE){
        const tag=node.tagName.toUpperCase();
        if(!ALLOWED.has(tag)) return document.createTextNode(node.textContent||'');
        if(tag==='BR') return document.createElement('br');
        const el=document.createElement(tag.toLowerCase());
        if(tag==='SPAN'){ if(node.className) el.className=node.className; if(node.title) el.title=node.title; }
        return el;
      }
      return document.createTextNode('');
    }
    function prepareTyping(srcParent, dstParent, queue){
      Array.from(srcParent.childNodes).forEach(src=>{
        if(src.nodeType===Node.TEXT_NODE){
          const t=document.createTextNode(''); dstParent.appendChild(t);
          const text=src.textContent||''; if(text.length) queue.push({node:t, text});
        }else if(src.nodeType===Node.ELEMENT_NODE){
          const cloned=cloneShallow(src); dstParent.appendChild(cloned);
          if(cloned.nodeType===Node.TEXT_NODE){
            const text=cloned.textContent||''; if(text.length) queue.push({node:cloned, text});
          }else if(cloned.tagName && cloned.tagName.toUpperCase()==='BR'){
          }else{
            prepareTyping(src, cloned, queue);
          }
        }
      });
    }
    async function typeQueue(queue){
      const cursor=document.createElement('span');
      cursor.className='moe-typing-cursor';
      target.appendChild(cursor);
      for(const item of queue){
        const chars=Array.from(item.text);
        for(let i=0;i<chars.length;i++){
          item.node.textContent+=chars[i];
          await new Promise(r=>setTimeout(r, SPEED));
        }
      }
      cursor.remove();
    }
    const sourceRoot=document.createElement('div');
    sourceRoot.innerHTML=srcTpl.innerHTML;
    const queue=[]; prepareTyping(sourceRoot, target, queue);
    if(queue.length===0){
      target.innerHTML='<p><?php echo esc_js(
          esc_html__("抱歉,目前無法取得 AI 回答,請稍後再試。", "moelog-ai-qna"),
      ); ?></p>';
    }else{
      typeQueue(queue);
    }
  })();
  </script>
</div>

<div class="moe-close-area">
  <a href="#" id="moe-close-btn" class="moe-close-btn"><?php esc_html_e(
      "← 關閉此頁",
      "moelog-ai-qna",
  ); ?></a>
  <div id="moelog-fallback" class="moe-fallback" style="display:none;">
    <?php esc_html_e(
        "若瀏覽器不允許自動關閉視窗,請點此回到文章:",
        "moelog-ai-qna",
    ); ?>
    <a href="<?php echo esc_url(
        get_permalink($post_id),
    ); ?>" target="_self" rel="noopener"><?php echo esc_html(get_the_title($post_id)); ?></a>
  </div>
</div>
</div><!-- /.moe-container -->
<div class="moe-bottom"></div>

<?php
$site_name = get_bloginfo("name", "display");
if (empty($site_name)) {
    $host = parse_url(home_url(), PHP_URL_HOST);
    $site_name = $host ?: "本網站";
}
$o = get_option(self::OPT_KEY, []);
$default_tpl =
    "本頁面由AI生成,可能會發生錯誤,請查核重要資訊。\n使用本AI生成內容服務即表示您同意此內容僅供個人參考,且您了解輸出內容可能不準確。\n所有爭議內容{site}保有最終解釋權。";
$tpl =
    isset($o["disclaimer_text"]) && $o["disclaimer_text"] !== ""
        ? $o["disclaimer_text"]
        : $default_tpl;
$disclaimer = str_replace(["{site}", "%s"], $site_name, $tpl);
$disclaimer = apply_filters(
    "moelog_aiqna_disclaimer_text",
    $disclaimer,
    $site_name,
);
?>
<p class="moe-disclaimer" style="margin-top:10px;text-align:center;font-size:0.95em; color:#666; line-height:1.5em;">
  <?php echo nl2br(esc_html($disclaimer)); ?>
</p>
</body>
</html>
<?php
    }

    /*** ====== 語言偵測 ====== ***/
    private function detect_language($text)
    {
        $s = trim((string) $text);
        if ($s === "") {
            return "en";
        }
        if (
            preg_match(
                "/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{31F0}-\x{31FF}\x{FF66}-\x{FF9F}]/u",
                $s,
            )
        ) {
            return "ja";
        }
        if (preg_match("/[\p{Han}\x{3000}-\x{303F}\x{FF01}-\x{FF5E}]/u", $s)) {
            $han = preg_match_all("/\p{Han}/u", $s, $m1);
            $latin = preg_match_all("/[A-Za-z]/u", $s, $m2);
            if ($han > 0 && $latin < max(1, $han / 2)) {
                return "zh";
            }
        }
        if (preg_match('/^[A-Za-z0-9\s.,!?]+$/u', $s)) {
            return "en";
        }
        if (function_exists("mb_detect_encoding")) {
            $encoding = mb_detect_encoding(
                $s,
                ["UTF-8", "EUC-JP", "SJIS", "ISO-8859-1"],
                true,
            );
            if ($encoding === "EUC-JP" || $encoding === "SJIS") {
                return "ja";
            }
            if ($encoding === "UTF-8" && preg_match("/\p{Han}/u", $s)) {
                return "zh";
            }
        }
        return "en";
    }

    /*** ====== 呼叫 AI ====== ***/
    private function call_ai($provider, $args)
    {
        $api_key = $args["api_key"] ?? "";
        if (empty($api_key)) {
            return __("尚未設定 API Key。", "moelog-ai-qna");
        }

        $question = trim($args["question"] ?? "");
        $context = trim($args["context"] ?? "");
        $model =
            $args["model"] ??
            ($provider === "gemini"
                ? self::DEFAULT_MODEL_GEMINI
                : self::DEFAULT_MODEL_OPENAI);
        $temp = isset($args["temperature"])
            ? floatval($args["temperature"])
            : 0.3;
        $system = $args["system"] ?? "";
        $url = $args["url"] ?? "";
        $post_id = intval($args["post_id"] ?? 0);
        $lang = $args["lang"] ?? $this->detect_language($question);
        $cache_key =
            $args["cache_key"] ??
            "moe_aiqna_" .
                hash(
                    "sha256",
                    $post_id .
                        "|" .
                        $question .
                        "|" .
                        $model .
                        "|" .
                        substr(hash("sha256", $context), 0, 32) .
                        "|" .
                        $lang,
                );

        // 二次快取檢查
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // 本機 URL 判斷
        $is_local_url = false;
        $host = parse_url($url, PHP_URL_HOST);
        if ($host) {
            if (
                in_array($host, ["localhost", "127.0.0.1", "::1"], true) ||
                preg_match(
                    "/^(10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|192\.168\.)/",
                    $host,
                )
            ) {
                $is_local_url = true;
            }
        }

        // Prompt 組裝（已去除重複 citation 規則）
        $lang_hint = self::lang_hint_lite($lang);
        $citation_rules = self::citation_rules_lite();

        $user_prompt = $lang_hint . "\n\n";
        $user_prompt .= "問題：{$question}\n\n";
        if (!empty($context)) {
            $user_prompt .= "以下為原文脈絡（可能已截斷；僅供理解，禁止作為引用來源）：\n{$context}\n\n";
        }
        $user_prompt .= $citation_rules . "\n";
        if (!$is_local_url && $url) {
            $user_prompt .= "原文連結（僅供理解，禁止作為引用來源）：{$url}\n";
        }
        $user_prompt .=
            "\n【輸出格式要求】\nA) 先給出答案主體；\nB) 若有引用，最後加上小標題「參考資料」並逐行列出；\nC) 若沒有引用，就不要出現「參考資料」。\n";

        switch ($provider) {
            case "gemini":
                $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
                $full_user_content =
                    ($system ? $system . "\n\n" : "") .
                    $lang_hint .
                    "\n\n" .
                    $user_prompt;
                $body = [
                    "contents" => [
                        [
                            "role" => "user",
                            "parts" => [["text" => $full_user_content]],
                        ],
                    ],
                    "generationConfig" => ["temperature" => $temp],
                ];
                $resp = wp_remote_post($endpoint, [
                    "headers" => ["Content-Type" => "application/json"],
                    "timeout" => 20,
                    "body" => wp_json_encode($body),
                ]);
                if (is_wp_error($resp)) {
                    if (defined("WP_DEBUG") && WP_DEBUG) {
                        error_log(
                            "[Moelog AIQnA] HTTP Error (Gemini): " .
                                $resp->get_error_message(),
                        );
                    }
                    return __(
                        "呼叫 Google Gemini 失敗,請稍後再試。",
                        "moelog-ai-qna",
                    );
                }
                $code = wp_remote_retrieve_response_code($resp);
                $raw = wp_remote_retrieve_body($resp);
                $json = json_decode($raw, true);
                if ($code >= 200 && $code < 300) {
                    $text =
                        $json["candidates"][0]["content"]["parts"][0]["text"] ??
                        "";
                    if ($text !== "") {
                        $answer = trim($text);
                        $answer = self::sanitize_citations_lite($answer);
                        set_transient($cache_key, $answer, self::CACHE_TTL);
                        return $answer;
                    }
                }
                if (defined("WP_DEBUG") && WP_DEBUG) {
                    error_log(
                        "[Moelog AIQnA] Gemini HTTP " .
                            $code .
                            " Body: " .
                            $raw,
                    );
                }
                if ($code === 400 || $code === 403) {
                    $msg = $json["error"]["message"] ?? "未知錯誤";
                    if (
                        strpos($msg, "API_KEY_INVALID") !== false ||
                        (strpos($msg, "model") !== false &&
                            strpos($msg, "not found") !== false)
                    ) {
                        return __(
                            "服務暫時無法使用,請檢查 API Key 或模型名稱。",
                            "moelog-ai-qna",
                        );
                    }
                    if (
                        strpos($msg, "blocked") !== false ||
                        strpos($msg, "PROMPT_FILTERED") !== false
                    ) {
                        return __(
                            "問題或答案被安全過濾機制阻擋。",
                            "moelog-ai-qna",
                        );
                    }
                    return __("AI 服務回傳異常,請稍後再試。", "moelog-ai-qna");
                }
                return __(
                    "Google Gemini 回傳異常,請稍後再試。",
                    "moelog-ai-qna",
                );

            case "openai":
            default:
                $endpoint = "https://api.openai.com/v1/chat/completions";
                $body = [
                    "model" => $model,
                    "temperature" => $temp,
                    "messages" => [
                        [
                            "role" => "system",
                            "content" =>
                                $system ?:
                                "You are a professional editor providing concise and accurate answers.",
                        ],
                        ["role" => "system", "content" => $lang_hint],
                        ["role" => "user", "content" => $user_prompt],
                    ],
                ];
                $resp = wp_remote_post($endpoint, [
                    "headers" => [
                        "Authorization" => "Bearer " . $api_key,
                        "Content-Type" => "application/json",
                    ],
                    "timeout" => 20,
                    "body" => wp_json_encode($body),
                ]);
                if (is_wp_error($resp)) {
                    if (defined("WP_DEBUG") && WP_DEBUG) {
                        error_log(
                            "[Moelog AIQnA] HTTP Error: " .
                                $resp->get_error_message(),
                        );
                    }
                    return __("呼叫 OpenAI 失敗,請稍後再試。", "moelog-ai-qna");
                }
                $code = wp_remote_retrieve_response_code($resp);
                $json = json_decode(wp_remote_retrieve_body($resp), true);
                if (
                    $code >= 200 &&
                    $code < 300 &&
                    !empty($json["choices"][0]["message"]["content"])
                ) {
                    $answer = trim($json["choices"][0]["message"]["content"]);
                    $answer = self::sanitize_citations_lite($answer);
                    set_transient($cache_key, $answer, self::CACHE_TTL);
                    return $answer;
                }
                if (defined("WP_DEBUG") && WP_DEBUG) {
                    error_log("[Moelog AIQnA] OpenAI HTTP " . $code);
                }
                if ($code === 401) {
                    return __(
                        "服務暫時無法使用,請檢查 API Key 或模型名稱。",
                        "moelog-ai-qna",
                    );
                }
                if ($code === 429) {
                    $msg = $json["error"]["message"] ?? "";
                    if (strpos($msg, "insufficient_quota") !== false) {
                        return __(
                            "服務暫時無法使用,請檢查 API 額度。",
                            "moelog-ai-qna",
                        );
                    }
                    return __("請求過於頻繁,請稍候再試。", "moelog-ai-qna");
                }
                return __("AI 服務回傳異常,請稍後再試。", "moelog-ai-qna");
        }
    }

    /*** ====== 卸載清理 ====== ***/
    public static function uninstall()
    {
        delete_option(self::OPT_KEY);
        delete_option(self::SECRET_OPT_KEY);
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s OR meta_key = %s",
                self::META_KEY,
                self::META_KEY_LANG,
            ),
        );
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_moe_aiqna_%'",
        );
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_moe_aiqna_%'",
        );
    }
}

// 啟用/停用
register_activation_hook(__FILE__, function () {
    update_option("moe_aiqna_needs_flush", "1", false);
});
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});

// 建立單一實例
global $moelog_aiqna_instance;
$moelog_aiqna_instance = new Moelog_AIQnA();
$GLOBALS["moelog_aiqna_instance"] = $moelog_aiqna_instance;
if (!function_exists("moelog_aiqna_instance")) {
    function moelog_aiqna_instance()
    {
        return $GLOBALS["moelog_aiqna_instance"] ?? null;
    }
}

// 載入 GEO 模組（如有）
if (file_exists(__DIR__ . "/moelog-ai-geo.php")) {
    require_once __DIR__ . "/moelog-ai-geo.php";
}

// 卸載掛鉤
register_uninstall_hook(__FILE__, ["Moelog_AIQnA", "uninstall"]);
