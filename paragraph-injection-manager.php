<?php
/**
 * Plugin Name: Paragraph Injection Manager
 * Description: Injects admin-defined message every N paragraphs as static HTML stored in post meta and shown on the front-end.
 * Version: 1.0
 * Author: Maharshi Kushwaha
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) exit;

class ParagraphInjectionManager {
    private $meta_key_cat = '_pim_selected_category';
    private $meta_key_injection = '_pim_injection_html';
    private $log_option = 'pim_injected_posts';
    private $message_option = 'pim_custom_message';
    private $interval_option = 'pim_injection_interval';

    public function __construct() {
        add_action('admin_init', [$this, 'admin_init_hooks']);
        add_action('admin_menu', [$this, 'maybe_add_admin_menu']);
        add_filter('the_content', [$this, 'inject_message_into_content']);
    }

    public function admin_init_hooks() {
        if (!current_user_can('administrator')) return;

        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post', [$this, 'save_meta']);
        add_action('admin_post_pim_inject_now', [$this, 'handle_injection']);
        add_action('admin_post_pim_clear_records', [$this, 'handle_clear_records']);
        add_action('admin_post_pim_save_message', [$this, 'handle_save_message']);
    }

    public function maybe_add_admin_menu() {
        if (!current_user_can('administrator')) return;
        $this->add_admin_menu();
    }

    // Add hierarchical category dropdown meta box in post editor
    public function add_meta_box() {
        add_meta_box('pim_category_selector', 'Paragraph Injection Category', [$this, 'category_meta_box_html'], 'post', 'side');
    }

    public function category_meta_box_html($post) {
        wp_nonce_field('pim_category_meta_box', 'pim_category_meta_box_nonce');
        $selected_cat_id = get_post_meta($post->ID, $this->meta_key_cat, true);
        $categories = get_categories(['hide_empty' => 0, 'orderby' => 'name', 'parent' => 0]);

        echo '<select name="pim_selected_category" style="width:100%">';
        echo '<option value="">-- Select Category (fallback to first assigned) --</option>';
        foreach ($categories as $cat) {
            $this->render_category_option_recursive($cat, $selected_cat_id);
        }
        echo '</select>';
    }

    private function render_category_option_recursive($cat, $selected_id, $depth = 0) {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $depth);
        printf(
            '<option value="%d" %s>%s%s</option>',
            $cat->term_id,
            selected($selected_id, $cat->term_id, false),
            $indent,
            esc_html($cat->name)
        );
        $children = get_categories(['hide_empty' => 0, 'parent' => $cat->term_id]);
        foreach ($children as $child) {
            $this->render_category_option_recursive($child, $selected_id, $depth + 1);
        }
    }

    // Save selected category meta on post save
    public function save_meta($post_id) {
        if (!isset($_POST['pim_category_meta_box_nonce']) || !wp_verify_nonce($_POST['pim_category_meta_box_nonce'], 'pim_category_meta_box')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['pim_selected_category'])) {
            update_post_meta($post_id, $this->meta_key_cat, intval($_POST['pim_selected_category']));
        }
    }

    // Add admin menu & settings page
    public function add_admin_menu() {
        add_menu_page(
            'Paragraph Injection Manager',
            'Paragraph Injection',
            'manage_options',
            'paragraph-injection-manager',
            [$this, 'admin_page_html'],
            'dashicons-editor-paragraph',
            80
        );
    }

    // Admin page HTML & forms
    public function admin_page_html() {
        if (!current_user_can('manage_options')) return;

        $done = get_option($this->log_option, []);
        if (!is_array($done)) $done = [];

        $all_posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);
        $remaining = array_diff($all_posts, $done);
        $remaining_count = count($remaining);

        $message = get_option($this->message_option, 'You are reading this {category} story on your own website.');
        $interval = intval(get_option($this->interval_option, 10));

        ?>
        <div class="wrap">
            <h1>Paragraph Injection Manager</h1>

            <?php if (isset($_GET['injected'])): ?>
                <div class="notice notice-success is-dismissible"><p>Injection done for batch.</p></div>
            <?php endif; ?>
            <?php if (isset($_GET['cleared'])): ?>
                <div class="notice notice-success is-dismissible"><p>All injections cleared.</p></div>
            <?php endif; ?>
            <?php if (isset($_GET['updated'])): ?>
                <div class="notice notice-success is-dismissible"><p>Message / Settings saved.</p></div>
            <?php endif; ?>

            <h2>Custom Injection Message</h2>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('pim_save_message_nonce', 'pim_save_message_nonce_field'); ?>
                <input type="hidden" name="action" value="pim_save_message" />
                <p>Use <code>{category}</code> to insert clickable category link.</p>
                <textarea name="pim_custom_message" rows="6" style="width:100%; font-family: monospace;"><?php echo esc_textarea($message); ?></textarea>

                <h2>Injection Interval</h2>
                <p>After how many paragraphs should the message be injected?</p>
                <input type="number" name="pim_injection_interval" value="<?php echo esc_attr($interval); ?>" min="1" style="width: 80px;" />

                <?php submit_button('Save Settings'); ?>
            </form>

            <hr>

            <h2>Injection Control</h2>
            <p><strong>Posts remaining to inject:</strong> <?php echo $remaining_count; ?></p>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('pim_inject_now_nonce', 'pim_inject_now_nonce_field'); ?>
                <input type="hidden" name="action" value="pim_inject_now" />
                <?php submit_button('Inject Now (Process 100 posts)'); ?>
            </form>

            <hr>

            <h2>Clear Injection Records</h2>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" onsubmit="return confirm('Type DELETE (uppercase) in the box below and then press OK to confirm clearing all injection records.');">
                <?php wp_nonce_field('pim_clear_records_nonce', 'pim_clear_records_nonce_field'); ?>
                <input type="hidden" name="action" value="pim_clear_records" />
                <p>Type <code>DELETE</code> to confirm:</p>
                <input type="text" name="confirm" required pattern="DELETE" style="text-transform: uppercase; width: 150px;" />
                <?php submit_button('Clear Records'); ?>
            </form>
        </div>
        <?php
    }

    // Save custom message + interval
    public function handle_save_message() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        if (!isset($_POST['pim_save_message_nonce_field']) || !wp_verify_nonce($_POST['pim_save_message_nonce_field'], 'pim_save_message_nonce')) {
            wp_die('Nonce verification failed');
        }

        if (isset($_POST['pim_custom_message'])) {
            $allowed_tags = wp_kses_allowed_html('post');
            $msg = wp_kses($_POST['pim_custom_message'], $allowed_tags);
            update_option($this->message_option, $msg);
        }

        if (isset($_POST['pim_injection_interval'])) {
            update_option($this->interval_option, max(1, intval($_POST['pim_injection_interval'])));
        }

        wp_redirect(admin_url('admin.php?page=paragraph-injection-manager&updated=1'));
        exit;
    }

    // Inject static HTML and save in meta (batch of 100)
    public function handle_injection() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        if (!isset($_POST['pim_inject_now_nonce_field']) || !wp_verify_nonce($_POST['pim_inject_now_nonce_field'], 'pim_inject_now_nonce')) {
            wp_die('Nonce verification failed');
        }

        $done = get_option($this->log_option, []);
        if (!is_array($done)) $done = [];

        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'fields'         => 'ids',
            'exclude'        => $done,
        ];
        $posts = get_posts($args);

        if (empty($posts)) {
            wp_redirect(admin_url('admin.php?page=paragraph-injection-manager&injected=1'));
            exit;
        }

        $message = get_option($this->message_option, 'You are reading this {category} story on your own website.');

        foreach ($posts as $post_id) {
            if (get_post_meta($post_id, $this->meta_key_injection, true)) {
                $done[] = $post_id;
                continue;
            }

            $selected_cat_id = get_post_meta($post_id, $this->meta_key_cat, true);
            $categories = get_the_category($post_id);

            if ($selected_cat_id && $selected_cat_id != '') {
                $cat = get_category($selected_cat_id);
            } elseif (!empty($categories)) {
                $cat = $categories[0];
            } else {
                $done[] = $post_id;
                continue;
            }

            if (!$cat || is_wp_error($cat)) {
                $done[] = $post_id;
                continue;
            }

            $cat_link = esc_url(get_category_link($cat->term_id));
            $cat_name = esc_html($cat->name);

            $injection_html = str_replace(
                '{category}',
                '<a href="' . $cat_link . '" title="' . esc_attr($cat_name) . '">' . $cat_name . '</a>',
                $message
            );

            update_post_meta($post_id, $this->meta_key_injection, $injection_html);
            $done[] = $post_id;
        }

        update_option($this->log_option, $done);

        wp_redirect(admin_url('admin.php?page=paragraph-injection-manager&injected=1'));
        exit;
    }

    // Clear injection records with SQL (fast reset)
    public function handle_clear_records() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        if (!isset($_POST['pim_clear_records_nonce_field']) || !wp_verify_nonce($_POST['pim_clear_records_nonce_field'], 'pim_clear_records_nonce')) {
            wp_die('Nonce verification failed');
        }
        if (empty($_POST['confirm']) || $_POST['confirm'] !== 'DELETE') {
            wp_die('Confirmation text incorrect. Type DELETE to clear records.');
        }

        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->postmeta WHERE meta_key = %s",
                $this->meta_key_injection
            )
        );

        delete_option($this->log_option);

        wp_redirect(admin_url('admin.php?page=paragraph-injection-manager&cleared=1'));
        exit;
    }

    // Front-end inject message every X paragraphs if meta present
    public function inject_message_into_content($content) {
        if (!is_singular('post')) return $content;

        global $post;

        $injection_html = get_post_meta($post->ID, $this->meta_key_injection, true);
        if (!$injection_html) return $content;

        $interval = intval(get_option($this->interval_option, 10));

        $paragraphs = preg_split('/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if (!$paragraphs) return $content;

        $full_paragraphs = [];
        for ($i = 0; $i < count($paragraphs); $i += 2) {
            $para = $paragraphs[$i];
            $closing = isset($paragraphs[$i + 1]) ? $paragraphs[$i + 1] : '';
            $full_paragraphs[] = $para . $closing;
        }

        $offset = $interval;

        for ($pos = $offset; $pos < count($full_paragraphs); $pos += ($interval + 1)) {
            array_splice($full_paragraphs, $pos, 0, ['<p>' . $injection_html . '</p>']);
        }

        return implode('', $full_paragraphs);
    }
}

new ParagraphInjectionManager();
