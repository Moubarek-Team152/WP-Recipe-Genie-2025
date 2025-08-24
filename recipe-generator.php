<?php
/**
 * Plugin Name:       Recipe Genie wp
 * Plugin URI:        https://www.nidacademy.org/recipe-genie-wp/
 * Description:       WP Recipe Genie is a simple and beginner-friendly WordPress plugin that helps food bloggers create SEO-optimized recipes with focus keywords, related keywords, internal and external links, and images. It streamlines recipe creation while improving search engine visibility.
 * Version:           1.0.0
 * Author:            Nid Academy
 * Author URI:        https://nidacademy.org
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       recipe-genie-wp
 * Domain Path:       /languages
 */

// Define version constant if not already defined by another process
if ( ! defined( 'RECIPE_GENERATOR_VERSION' ) ) {
    define( 'RECIPE_GENERATOR_VERSION', '1.0.0' ); // Default version
}

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'RECIPE_GENERATOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'RECIPE_GENERATOR_URL', plugin_dir_url( __FILE__ ) );

// Include dependencies
// require_once RECIPE_GENERATOR_PATH . 'includes/settings-page.php';
// require_once RECIPE_GENERATOR_PATH . 'includes/api-handler.php';
// require_once RECIPE_GENERATOR_PATH . 'includes/shortcode.php';
// require_once RECIPE_GENERATOR_PATH . 'includes/block.php';

/**
 * Initialize plugin functionality.
 */
function recipe_generator_init() {
    // Register block
    add_action( 'init', 'recipe_generator_register_block' );

    // Register shortcode
    add_shortcode( 'recipe_generator', 'recipe_generator_render_shortcode' );

    // Load plugin textdomain for translation
    load_plugin_textdomain( 'recipe-generator', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'recipe_generator_init' );

/**
 * Add admin menu for settings page.
 */
function recipe_generator_admin_menu() {
    // Add top-level menu page
    add_menu_page(
        __( 'Recipe Generator', 'recipe-generator' ), // Page title
        __( 'Recipe Generator', 'recipe-generator' ), // Menu title
        'manage_options',                             // Capability
        'recipe-generator-main',                      // Menu slug
        'recipe_generator_page_generate_recipe_html', // Function to display first page (Generate Recipe)
        'dashicons-food',                           // Icon URL
        62                                            // Position
    );

    // Add submenu page for "Generate Recipe" (this will also be the default page for the top-level menu)
    add_submenu_page(
        'recipe-generator-main',                      // Parent slug
        __( 'Generate Recipe', 'recipe-generator' ),  // Page title
        __( 'Generate Recipe', 'recipe-generator' ),  // Menu title
        'manage_options',                             // Capability
        'recipe-generator-main',                      // Menu slug (same as parent to make it the default)
        'recipe_generator_page_generate_recipe_html'  // Function
    );

    // Add submenu page for "Generated Recipes"
    add_submenu_page(
        'recipe-generator-main',                         // Parent slug
        __( 'Generated Recipes', 'recipe-generator' ),   // Page title
        __( 'Generated Recipes', 'recipe-generator' ),   // Menu title
        'manage_options',                                // Capability
        'recipe-generator-view-generated',               // Menu slug
        'recipe_generator_page_generated_recipes_html' // Function
    );

    // Add submenu page for "Settings"
    add_submenu_page(
        'recipe-generator-main',                         // Parent slug
        __( 'Recipe Generator Settings', 'recipe-generator' ), // Page title
        __( 'Settings', 'recipe-generator' ),            // Menu title
        'manage_options',                                // Capability
        'recipe-generator-settings',                     // Menu slug (use the existing one for settings)
        'recipe_generator_settings_page_html'            // Function (existing settings page function)
    );

    // Add Help submenu page
    add_submenu_page(
        'recipe-generator-main', // Parent slug
        __( 'Help', 'recipe-generator' ), // Page title
        __( 'Help', 'recipe-generator' ), // Menu title
        'manage_options', // Capability
        'recipe-generator-help', // Menu slug
        'recipe_generator_page_help_html' // Callback function
    );
}
add_action( 'admin_menu', 'recipe_generator_admin_menu' );

/**
 * Add Settings and Help links to the plugin action links.
 *
 * @param array $links An array of plugin action links.
 * @return array An array of plugin action links.
 */
function wp_recipe_genie_add_plugin_action_links( $links ) {
    $settings_page_url = admin_url( 'admin.php?page=recipe-generator-settings' );
    $help_page_url = admin_url( 'admin.php?page=recipe-generator-help' );

    $settings_link = '<a href="' . esc_url( $settings_page_url ) . '">' . esc_html__( 'Settings', 'recipe-generator' ) . '</a>';
    $help_link = '<a href="' . esc_url( $help_page_url ) . '">' . esc_html__( 'Help', 'recipe-generator' ) . '</a>';

    // Create an array for the new links to ensure order
    $custom_links = array(
        'settings' => $settings_link,
        'help'     => $help_link,
    );

    // Determine the key to insert after ('deactivate' or 'activate')
    $action_key_to_insert_after = '';
    if ( isset( $links['deactivate'] ) ) {
        $action_key_to_insert_after = 'deactivate';
    } elseif ( isset( $links['activate'] ) ) {
        $action_key_to_insert_after = 'activate';
    }

    if ( ! empty( $action_key_to_insert_after ) ) {
        $new_links_array = array();
        foreach ( $links as $key => $link ) {
            $new_links_array[ $key ] = $link;
            if ( $key === $action_key_to_insert_after ) {
                // Insert custom links after the identified action key
                foreach ( $custom_links as $custom_key => $custom_link_html ) {
                    $new_links_array[ $custom_key ] = $custom_link_html;
                }
            }
        }
        return $new_links_array;
    }

    // Fallback: if neither activate nor deactivate is found, merge (prepends custom links by default)
    return array_merge( $custom_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wp_recipe_genie_add_plugin_action_links' );


// Ensure WP_List_Table class is available
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Class Recipe_Generator_Generated_Recipes_List_Table
 * Handles the display of generated recipes in a WP_List_Table.
 */
class Recipe_Generator_Generated_Recipes_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => __( 'Generated Recipe', 'recipe-generator' ), // singular name of the listed records
            'plural'   => __( 'Generated Recipes', 'recipe-generator' ), // plural name of the listed records
            'ajax'     => false // does this table support ajax?
        ] );
    }

    public function get_columns() {
        $columns = [
            'cb'        => '<input type="checkbox" />',
            'title'     => __( 'Title', 'recipe-generator' ),
            'focus_keyword' => __( 'Focus Keyword', 'recipe-generator' ),
            'date'      => __( 'Date Created', 'recipe-generator' ),
        ];
        return $columns;
    }

    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $per_page = $this->get_items_per_page( 'recipes_per_page', 20 );
        $current_page = $this->get_pagenum();

        $query_args = [
            'post_type'      => 'post',
            'posts_per_page' => $per_page,
            'paged'          => $current_page,
            'post_status'    => ['draft', 'publish', 'pending', 'future', 'private'], // Include various statuses
            'meta_query'     => [
                [
                    'key'     => '_recipe_generator_ai_recipe',
                    'value'   => true,
                    'compare' => '=',
                ]
            ]
        ];
        
        // Add sorting
        $orderby = ( isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], array_keys( $this->get_sortable_columns() ) ) ) ? $_GET['orderby'] : 'date';
        $order = ( isset( $_GET['order'] ) && in_array( strtoupper( $_GET['order'] ), ['ASC', 'DESC'] ) ) ? $_GET['order'] : 'DESC';
        $query_args['orderby'] = $orderby;
        $query_args['order'] = $order;

        $query = new WP_Query( $query_args );

        $this->items = $query->get_posts();
        $total_items = $query->found_posts;

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page )
        ] );
    }

    protected function get_sortable_columns() {
        $sortable_columns = [
            'title' => ['title', false], // true for initial sort, false otherwise
            'date'  => ['date', true]    // Sort by date by default
        ];
        return $sortable_columns;
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'focus_keyword':
                return esc_html( get_post_meta( $item->ID, 'recipe_generator_focus_keyword', true ) );
            case 'date':
                return esc_html( get_the_date( '', $item ) );
            default:
                return print_r( $item, true ); // For debugging, should not happen
        }
    }

    function column_title( $item ) {
        $post_id = $item->ID;
        $title = get_the_title( $post_id );
        $post_status_obj = get_post_status_object( $item->post_status );
        $status_label = $post_status_obj ? ' — <span class="post-state">' . esc_html($post_status_obj->label) . '</span>' : '';

        $actions = [];
        $can_edit_post = current_user_can( 'edit_post', $post_id );

        // Edit Link
        if ( $can_edit_post && 'trash' !== $item->post_status ) {
            $actions['edit'] = sprintf(
                '<a href="%s" aria-label="%s">%s</a>',
                get_edit_post_link( $post_id ),
                esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;', 'recipe-generator' ), $title ) ),
                __( 'Edit', 'recipe-generator' )
            );
        }

        // Quick Edit Link
        if ( $can_edit_post && 'trash' !== $item->post_status ) {
            $actions['inline hide-if-no-js'] = sprintf( // Key used by WP core for Quick Edit
                '<a href="#" class="editinline" aria-label="%s">%s</a>',
                esc_attr( sprintf( __( 'Quick edit &#8220;%s&#8221; inline', 'recipe-generator' ), $title ) ),
                __( 'Quick Edit', 'recipe-generator' )
            );
        }
        
        // Trash/Restore/Delete Permanently Links
        if ( current_user_can( 'delete_post', $post_id ) ) {
            if ( 'trash' === $item->post_status ) {
                $actions['untrash'] = sprintf(
                    '<a href="%s" aria-label="%s">%s</a>',
                    wp_nonce_url( admin_url( sprintf( 'post.php?action=untrash&post=%d', $post_id ) ), 'untrash-post_' . $post_id ),
                    esc_attr( sprintf( __( 'Restore &#8220;%s&#8221; from the Trash', 'recipe-generator' ), $title ) ),
                    __( 'Restore', 'recipe-generator' )
                );
                $actions['delete'] = sprintf(
                    '<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
                    wp_nonce_url( admin_url( sprintf( 'post.php?action=delete&post=%d', $post_id ) ), 'delete-post_' . $post_id ),
                    esc_attr( sprintf( __( 'Delete &#8220;%s&#8221; permanently', 'recipe-generator' ), $title ) ),
                    __( 'Delete Permanently', 'recipe-generator' )
                );
            } else { // Not in trash
                $actions['trash'] = sprintf(
                    '<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
                    wp_nonce_url( admin_url( sprintf( 'post.php?action=trash&post=%d', $post_id ) ), 'trash-post_' . $post_id ),
                    esc_attr( sprintf( __( 'Move &#8220;%s&#8221; to the Trash', 'recipe-generator' ), $title ) ),
                    __( 'Trash', 'recipe-generator' )
                );
            }
        }
        
        // Preview Link (using 'view' key for better WP integration)
        if ( 'trash' !== $item->post_status && $can_edit_post ) { // Also check $can_edit_post for preview capability
            $preview_link = get_preview_post_link( $post_id );
            if ( $preview_link ) {
                $actions['view'] = sprintf(
                    '<a href="%s" rel="noopener noreferrer bookmark" aria-label="%s" target="_blank">%s</a>',
                    esc_url( $preview_link ),
                    esc_attr( sprintf( __( 'Preview &#8220;%s&#8221;', 'recipe-generator' ), $title ) ),
                    __( 'Preview', 'recipe-generator' )
                );
            }
        }

        $row_actions_output = $this->row_actions( $actions );

        // Main title link
        $main_title_link_href = $can_edit_post ? esc_url( get_edit_post_link( $post_id ) ) : '#'; // Link to edit if possible
        // ARIA label for the main title link
        $main_title_link_aria_label = $can_edit_post ? esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;', 'recipe-generator' ), $title ) ) : esc_attr( $title );


        return sprintf( '<strong><a class="row-title" href="%s" aria-label="%s">%s</a>%s</strong>%s',
            $main_title_link_href,
            $main_title_link_aria_label,
            esc_html( $title ), // Title text
            $status_label,
            $row_actions_output
        );
    }

    protected function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="post[]" value="%s" />',
            $item->ID
        );
    }
    
    // Add methods for sortable columns, bulk actions, specific column rendering (e.g., title with actions) later.
}

/**
 * Render the "Generated Recipes" admin page.
 */
function recipe_generator_page_generated_recipes_html() {
    if ( ! current_user_can( 'manage_options' ) ) { 
        wp_die( __( 'You do not have sufficient permissions to access this page.', 'recipe-generator' ) );
    }

    // Check for success message from redirect
    if ( isset( $_GET['post_created'] ) && isset( $_GET['message'] ) ) {
        $post_id = intval( $_GET['post_created'] );
        $message = sanitize_text_field( urldecode( $_GET['message'] ) );
        if ( $post_id > 0 && ! empty( $message ) ) {
            $edit_link = get_edit_post_link( $post_id );
            $display_message = $message;
            if ( $edit_link ) {
                $display_message .= ' <a href="' . esc_url( $edit_link ) . '">' . __( 'Edit Post', 'recipe-generator' ) . '</a>';
            }
            echo '<div id="message" class="notice notice-success is-dismissible"><p>' . wp_kses_post( $display_message ) . '</p></div>';
        }
    }

    $list_table = new Recipe_Generator_Generated_Recipes_List_Table();
    $list_table->prepare_items();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <p><?php _e( 'Below is a list of recipes generated by the AI. You can view, edit, or delete them from here.', 'recipe-generator' ); ?></p>
        
        <form method="post">
            <?php
            $list_table->search_box( __( 'Search Recipes', 'recipe-generator' ), 'recipe' );
            $list_table->display(); 
            ?>
        </form>
    </div>
    <?php
}

/**
 * Render the "Generate Recipe" admin page.
 */
function recipe_generator_page_generate_recipe_html() {
    // Ensure the user has the 'manage_options' capability to access this page.
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', 'recipe-generator' ) );
    }

    // Initialize variables to prevent undefined notices
    $admin_notice_message = '';
    $admin_notice_type = '';

    // Handle form submission for generating recipe content
    if ( isset( $_POST['action'] ) && $_POST['action'] === 'generate_recipe_content' ) {
        check_admin_referer( 'generate_recipe_action', 'recipe_generator_nonce' );

        // Prepare args for the API call
        $args = [
            'post_title'      => isset( $_POST['post_title'] ) ? sanitize_text_field( $_POST['post_title'] ) : '',
            'focus_keyword'   => isset( $_POST['focus_keyword'] ) ? sanitize_text_field( $_POST['focus_keyword'] ) : '',
            'related_keywords' => isset( $_POST['related_keywords'] ) ? sanitize_text_field( $_POST['related_keywords'] ) : '',
            'internal_link'   => isset( $_POST['internal_link'] ) ? esc_url_raw( $_POST['internal_link'] ) : '',
            'external_link'   => isset( $_POST['external_link'] ) ? esc_url_raw( $_POST['external_link'] ) : '',
            'generate_featured_image' => isset( $_POST['generate_featured_image'] ), // Will be true if checked, not present if unchecked
            'generate_ingredients_image' => isset( $_POST['generate_ingredients_image'] ),
            'generate_steps_image' => isset( $_POST['generate_steps_image'] ),
        ];
        
        $result = recipe_generator_generate_recipe_post( $args );
        
        if ( $result['success'] && $result['post_id'] ) {
            // Recipe created successfully, set message for current page
            $admin_notice_message = '<span class="dashicons dashicons-yes-alt"></span> ' . __( 'recipe cooked successfully', 'recipe-generator' ) . ' <a href="' . esc_url( get_edit_post_link( $result['post_id'] ) ) . '">' . __( 'Edit Post', 'recipe-generator' ) . '</a>';
            $admin_notice_type = 'success';
            // Clear POST data to reset the form, or let JS handle this.
            // For now, we'll rely on JS to clear/manage form state after AJAX.
            // $_POST = array(); // Uncomment if form reset is desired via PHP reload (not ideal for AJAX)
        } else {
            // Store error message to display as an admin notice
            $admin_notice_message = $result['message'];
            $admin_notice_type = 'error';
        }
    }
    // Note: The part for 'create_recipe_post_from_generated' action is removed as Step 3 is being removed.

    ?>
    <div class="wrap">
        <div style="display: flex; align-items: center;"><img src="<?php echo esc_url( plugins_url( 'assets/icon-256x256.png', __FILE__ ) ); ?>" alt="WP Recipe Genie Icon" style="width: 100px; height: 100px; margin-right: 8px;"><h1><?php echo esc_html( get_admin_page_title() ); ?></h1></div>
        <div id="recipe-generator-message" style="margin-bottom: 15px; display: none;"></div>

        <div id="recipe-generation-status" style="margin-bottom: 15px;"></div>

        <?php if ( ! empty( $admin_notice_message ) ) : ?>
            <div id="message" class="notice <?php echo $admin_notice_type === 'error' ? 'notice-error' : 'notice-success'; ?> is-dismissible">
                <p><?php echo wp_kses_post( $admin_notice_message ); ?></p>
            </div>
        <?php endif; ?>

        <p><?php _e( 'Generate Recipe Use the form below to generate a new recipe. The AI will craft a blog post based on your input, and it will be automatically saved as a draft in your \'Generated Recipes\' list.', 'recipe-generator' ); ?></p>
        
        <form method="post" action="" id="recipe-generator-form">
            <?php wp_nonce_field( 'generate_recipe_action', 'recipe_generator_nonce' ); ?>
            <input type="hidden" name="action" value="generate_recipe_content">

            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="post_title"><?php _e( 'Blog Post Title', 'recipe-generator' ); ?></label></th>
                    <td>
                        <input type="text" id="post_title" name="post_title" value="<?php echo esc_attr( $_POST['post_title'] ?? '' ); ?>" class="regular-text" required />
                        <p class="description"><?php _e( 'The main title for your recipe blog post (e.g., "Amazing Chocolate Chip Cookies"). This will be the H1 of your post.', 'recipe-generator' ); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="focus_keyword"><?php _e( 'Focus Keyword', 'recipe-generator' ); ?></label></th>
                    <td>
                        <input type="text" id="focus_keyword" name="focus_keyword" value="<?php echo esc_attr( $_POST['focus_keyword'] ?? '' ); ?>" class="regular-text" required />
                        <p class="description"><?php _e( 'The primary keyword you want this post to rank for (e.g., "chocolate chip cookies recipe").', 'recipe-generator' ); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="related_keywords"><?php _e( 'Related Keywords', 'recipe-generator' ); ?></label></th>
                    <td>
                        <input type="text" id="related_keywords" name="related_keywords" value="<?php echo esc_attr( $_POST['related_keywords'] ?? '' ); ?>" class="regular-text" />
                        <p class="description"><?php _e( 'Enter related keywords, separated by commas (e.g., "sourdough starter, artisan bread, baking tips"). These will be used to enrich the content.', 'recipe-generator' ); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="internal_link"><?php _e( 'Internal Link URL (Optional)', 'recipe-generator' ); ?></label></th>
                    <td>
                        <input type="url" id="internal_link" name="internal_link" value="<?php echo esc_url( $_POST['internal_link'] ?? '' ); ?>" class="regular-text" placeholder="https://yourwebsite.com/related-post" />
                        <p class="description"><?php _e( 'If you want the Focus Keyword to be linked, provide the URL here. Leave empty for no link.', 'recipe-generator' ); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="external_link"><?php _e( 'External Link URL (Optional)', 'recipe-generator' ); ?></label></th>
                    <td>
                        <input type="url" id="external_link" name="external_link" value="<?php echo esc_url( $_POST['external_link'] ?? '' ); ?>" class="regular-text" placeholder="https://externalsite.com/resource" />
                        <p class="description"><?php _e( 'If you want Related Keywords to be linked, provide a primary URL here. Leave empty for no link.', 'recipe-generator' ); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e( 'Image Generation Options', 'recipe-generator' ); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span><?php _e( 'Image Generation Options', 'recipe-generator' ); ?></span></legend>
                            <label for="generate_featured_image">
                                <input name="generate_featured_image" type="checkbox" id="generate_featured_image" value="1" <?php checked( !isset($_POST['action']) || isset($_POST['generate_featured_image']) ); ?>>
                                <?php _e( 'Generate Featured Image', 'recipe-generator' ); ?>
                            </label>
                            <br>
                            <label for="generate_ingredients_image">
                                <input name="generate_ingredients_image" type="checkbox" id="generate_ingredients_image" value="1" <?php checked( !isset($_POST['action']) || isset($_POST['generate_ingredients_image']) ); ?>>
                                <?php _e( 'Generate Ingredients Image (in content)', 'recipe-generator' ); ?>
                            </label>
                            <br>
                            <label for="generate_steps_image">
                                <input name="generate_steps_image" type="checkbox" id="generate_steps_image" value="1" <?php checked( !isset($_POST['action']) || isset($_POST['generate_steps_image']) ); ?>>
                                <?php _e( 'Generate Step-by-Step Instructions Image (in content)', 'recipe-generator' ); ?>
                            </label>
                            <p class="description"><?php _e( 'Select which images should be automatically generated by Fal AI. Requires Fal AI API Key to be set.', 'recipe-generator' ); ?></p>
                        </fieldset>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Generate Recipe & Save as Draft', 'recipe-generator' ), 'primary', 'submit_generate_content' ); ?>
        </form>

        <?php 
        // Step 2 and Step 3 content and forms are now removed.
        ?>

    </div>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('recipe-generator-form');
            const messageDiv = document.getElementById('recipe-generator-message');
            const generateButton = document.getElementById('submit_generate_content');

            if (form && messageDiv && generateButton) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();

                    messageDiv.innerHTML = '<p><span class="spinner is-active" style="float: left; margin-right: 5px;"></span> <?php echo esc_js(__('Generating recipe, please wait... This may take a moment.', 'recipe-generator')); ?></p>';
                    messageDiv.className = 'notice notice-info is-dismissible';
                    messageDiv.style.display = 'block';
                    generateButton.disabled = true;

                    const formData = new FormData(form);
                    formData.set('action', 'generate_recipe_content_ajax'); // Ensure correct AJAX action for the handler
                    // The nonce 'recipe_generator_nonce' is included by new FormData(form)

                    fetch(ajaxurl, { // ajaxurl is a global JS variable in WordPress admin
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        generateButton.disabled = false;
                        if (data.success) {
                            messageDiv.className = 'notice notice-success is-dismissible';
                            messageDiv.innerHTML = `<p>${data.data.message}</p>`; // Access message from data.data
                            form.reset(); // Reset form fields on success
                        } else {
                            messageDiv.className = 'notice notice-error is-dismissible';
                            messageDiv.innerHTML = `<p>${data.data.message || '<?php echo esc_js(__('An unknown error occurred.', 'recipe-generator')); ?>'}</p>`;
                        }
                        messageDiv.style.display = 'block';
                    })
                    .catch(error => {
                        generateButton.disabled = false;
                        messageDiv.className = 'notice notice-error is-dismissible';
                        console.error('Error:', error);
                        messageDiv.innerHTML = `<p><?php echo esc_js(__('Request failed. Please check the console for details or try again.', 'recipe-generator')); ?></p>`;
                        messageDiv.style.display = 'block';
                    });
                });
            }
        });
    </script>
    <?php
}

/**
 * Callback for the settings page.
 */
function recipe_generator_settings_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'recipe_generator_options' );
            do_settings_sections( 'recipe-generator-settings' );
            submit_button( __( 'Save Settings', 'recipe-generator' ) );
            ?>
        </form>
    </div>
    <?php
}

/**
 * Register settings.
 */
function recipe_generator_register_settings() {
    register_setting( 'recipe_generator_options', 'recipe_generator_settings', 'recipe_generator_sanitize_settings' );

    // OpenRouter API Settings Section
    add_settings_section(
        'recipe_generator_section_api', // ID
        __( 'OpenRouter API Settings', 'recipe-generator' ), // Title
        'recipe_generator_section_api_callback', // Callback
        'recipe-generator-settings' // Page slug where this section will be shown
    );

    add_settings_field(
        'api_key', // ID
        __( 'OpenRouter API Key', 'recipe-generator' ), // Title
        'recipe_generator_field_api_key_html', // Callback to render HTML
        'recipe-generator-settings', // Page slug
        'recipe_generator_section_api', // Section ID
        [ 'label_for' => 'recipe_generator_settings[api_key]' ] // Args
    );

    add_settings_field(
        'selected_model', // ID
        __( 'Select LLM Model', 'recipe-generator' ), // Title
        'recipe_generator_field_model_html', // Callback to render HTML
        'recipe-generator-settings', // Page slug
        'recipe_generator_section_api', // Section ID
        [ 'label_for' => 'recipe_generator_settings[selected_model]' ] // Args
    );

    // Fal AI Settings Section
    add_settings_section(
        'recipe_generator_section_fal_ai', // ID
        __( 'Fal AI Settings (for Image Generation)', 'recipe-generator' ), // Title
        'recipe_generator_section_fal_ai_callback', // Callback
        'recipe-generator-settings' // Page slug
    );

    add_settings_field(
        'fal_ai_api_key', // ID
        __( 'Fal AI API Key', 'recipe-generator' ), // Title
        'recipe_generator_field_fal_ai_api_key_html', // Callback to render HTML
        'recipe-generator-settings', // Page slug
        'recipe_generator_section_fal_ai', // Section ID
        [ 'label_for' => 'recipe_generator_settings[fal_ai_api_key]' ] // Args
    );
}

// NOTE: The original content of recipe_generator_register_settings was removed 
// because it's being fully replaced by the content above to ensure correct ordering and inclusion of new sections/fields.
// The original function only contained the OpenRouter settings. This replacement adds the Fal AI section correctly.
// The following is a placeholder for the original content that is now integrated above.
/* function recipe_generator_register_settings() { 
    register_setting( 'recipe_generator_options', 'recipe_generator_settings', 'recipe_generator_sanitize_settings' );

    add_settings_section(
        'recipe_generator_section_api', // ID
        __( 'API Settings', 'recipe-generator' ), // Title
        'recipe_generator_section_api_callback', // Callback
        'recipe-generator-settings' // Page slug where this section will be shown
    );

    add_settings_field(
        'api_key', // ID
        __( 'OpenRouter API Key', 'recipe-generator' ), // Title
        'recipe_generator_field_api_key_html', // Callback to render HTML
        'recipe-generator-settings', // Page slug
        'recipe_generator_section_api', // Section ID
        [ 'label_for' => 'recipe_generator_settings[api_key]' ] // Args
    );

    add_settings_field(
        'selected_model', // ID
        __( 'Select LLM Model', 'recipe-generator' ), // Title
        'recipe_generator_field_model_html', // Callback to render HTML
        'recipe-generator-settings', // Page slug
        'recipe_generator_section_api', // Section ID
        [ 'label_for' => 'recipe_generator_settings[selected_model]' ] // Args
    );
} */

function recipe_generator_register_settings_original_content_placeholder() {
    register_setting( 'recipe_generator_options', 'recipe_generator_settings', 'recipe_generator_sanitize_settings' );

    add_settings_section(
        'recipe_generator_section_api', // ID
        __( 'API Settings', 'recipe-generator' ), // Title
        'recipe_generator_section_api_callback', // Callback
        'recipe-generator-settings' // Page slug where this section will be shown
    );

    add_settings_field(
        'api_key', // ID
        __( 'OpenRouter API Key', 'recipe-generator' ), // Title
        'recipe_generator_field_api_key_html', // Callback to render HTML
        'recipe-generator-settings', // Page slug
        'recipe_generator_section_api', // Section ID
        [ 'label_for' => 'recipe_generator_settings[api_key]' ] // Args
    );

    add_settings_field(
        'selected_model', // ID
        __( 'Select LLM Model', 'recipe-generator' ), // Title
        'recipe_generator_field_model_html', // Callback to render HTML
        'recipe-generator-settings', // Page slug
        'recipe_generator_section_api', // Section ID
        [ 'label_for' => 'recipe_generator_settings[selected_model]' ] // Args
    );
}
add_action( 'admin_init', 'recipe_generator_register_settings' );

/**
 * Render the Help admin page.
 */
function recipe_generator_page_help_html() {
    // Ensure the user has the 'manage_options' capability to access this page.
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', 'recipe-generator' ) );
    }

    ?>
    <div class="wrap recipe-generator-help-page">
        <h1><?php _e('Help & Support', 'recipe-generator'); ?></h1>

        <p><?php _e('Click on a section below to expand it and find links to relevant documentation or support information.', 'recipe-generator'); ?></p>

        <div id="recipe-generator-help-accordion">
            <div class="recipe-generator-help-section">
                <h3 class="recipe-generator-help-section-title">
                    <?php _e('Getting Started', 'recipe-generator'); ?>
                    <span class="dashicons dashicons-arrow-down-alt2 toggle-icon"></span>
                </h3>
                <div class="recipe-generator-help-section-content">
                    <p><?php _e('NID Academy is an e-learning platform dedicated to helping entrepreneurs, content creators, and digital marketers succeed online. It offers comprehensive courses, workshops, and coaching sessions focused on digital marketing, SEO, social media growth, and e-commerce strategies. NID Academy’s mission is to provide practical, hands-on training that empowers individuals to build and scale profitable online businesses.', 'recipe-generator'); ?></p>
                    <p><?php _e('Through its focus on actionable learning, NID Academy bridges the gap between theory and real world application, enabling learners to transform their ideas into successful online ventures.', 'recipe-generator'); ?></p>
                </div>
            </div>

            <div class="recipe-generator-help-section">
                <h3 class="recipe-generator-help-section-title">
                    <?php _e('Basic Troubleshooting Steps', 'recipe-generator'); ?>
                    <span class="dashicons dashicons-arrow-down-alt2 toggle-icon"></span>
                </h3>
                <div class="recipe-generator-help-section-content">
                    <p><?php _e('If the plugin is not working as expected, try these common fixes:', 'recipe-generator'); ?></p>
                    <ul>
                        <li><?php _e('<strong>API Key:</strong> Ensure your OpenRouter and Fal.ai API keys are correctly entered in the plugin settings and are active.', 'recipe-generator'); ?></li>
                        <li><?php _e('<strong>Permissions:</strong> Check if your user role has the necessary permissions to use the plugin features.', 'recipe-generator'); ?></li>
                        <li><?php _e('<strong>Network Issues:</strong> Verify your internet connection. The plugin needs to connect to external APIs.', 'recipe-generator'); ?></li>
                        <li><?php _e('<strong>Plugin Conflicts:</strong> Try deactivating other plugins temporarily to see if there is a conflict.', 'recipe-generator'); ?></li>
                        <li><?php _e('<strong>Theme Conflicts:</strong> Switch to a default WordPress theme (like Twenty Twenty-One) to check for theme-related issues.', 'recipe-generator'); ?></li>
                    </ul>
                </div>
            </div>

            <div class="recipe-generator-help-section">
                <h3 class="recipe-generator-help-section-title">
                    <?php _e('Common Problems and Solutions', 'recipe-generator'); ?>
                    <span class="dashicons dashicons-arrow-down-alt2 toggle-icon"></span>
                </h3>
                <div class="recipe-generator-help-section-content">
                    <p><?php _e('Here are some frequently asked questions:', 'recipe-generator'); ?></p>
                    <ul>
                        <li><strong><?php _e('Q1: How do I generate recipe images with the plugin?', 'recipe-generator'); ?></strong><br><?php _e('A1: After installing the plugin, go to the Recipe Generator settings and enter your Fal.ai API key. Then, create a new recipe post, fill in the recipe details, and click "Generate Images." The plugin will fetch AI-generated images for your dish, ingredients, and preparation steps.', 'recipe-generator'); ?></li>
                        <li><strong><?php _e('Q2: Why aren’t my recipe images appearing?', 'recipe-generator'); ?></strong><br><?php _e('A2: Ensure your Fal.ai API key is valid and has sufficient credits. Check your internet connection and verify the image generation settings (image size, style). Also, confirm that your WordPress site can connect to external APIs.', 'recipe-generator'); ?></li>
                        <li><strong><?php _e('Q3: Can I customize the recipe image styles?', 'recipe-generator'); ?></strong><br><?php _e('A3: Yes! The plugin allows you to choose different image styles like "realistic," "cartoon," or "artistic." You can adjust these options in the settings before generating the images.', 'recipe-generator'); ?></li>
                        <li><strong><?php _e('Q4: Where are the generated images saved?', 'recipe-generator'); ?></strong><br><?php _e('A4: Generated images are automatically saved in your WordPress Media Library under the "Uploads" folder, and linked to your recipe post.', 'recipe-generator'); ?></li>
                        <li><strong><?php _e('Q5: What if I need support or have issues?', 'recipe-generator'); ?></strong><br><?php _e('A5: For plugin support, visit the "Help & Support" section in the plugin or contact the developer through the WordPress.org plugin page. For general recipe creation tips, check out learning resources on NID Academy’s website at www.nidacademy.org.', 'recipe-generator'); ?></li>
                    </ul>
                </div>
            </div>

            <div class="recipe-generator-help-section">
                <h3 class="recipe-generator-help-section-title">
                    <?php _e('Compatibility Issues', 'recipe-generator'); ?>
                    <span class="dashicons dashicons-arrow-down-alt2 toggle-icon"></span>
                </h3>
                <div class="recipe-generator-help-section-content">
                    <p><?php _e('For optimal performance, please ensure your environment meets the following requirements (referencing NID Academy’s technical recommendations where applicable):', 'recipe-generator'); ?></p>
                    <ul>
                        <li><?php _e('<strong>PHP Version:</strong> 7.4 or higher recommended.', 'recipe-generator'); ?></li>
                        <li><?php _e('<strong>WordPress Version:</strong> 5.5 or higher recommended.', 'recipe-generator'); ?></li>
                        <li><?php _e('<strong>Hosting:</strong> Ensure your hosting allows outgoing cURL requests for API communication. Sufficient memory allocation is also advised for processing recipes and images.', 'recipe-generator'); ?></li>
                        <li><?php _e('<strong>Known Conflicts:</strong> [List any known plugin/theme conflicts if identified. Currently, none are pre-defined.]', 'recipe-generator'); ?></li>
                    </ul>
                </div>
            </div>

            <div class="recipe-generator-help-section">
                <h3 class="recipe-generator-help-section-title">
                    <?php _e('Contact Support', 'recipe-generator'); ?>
                    <span class="dashicons dashicons-arrow-down-alt2 toggle-icon"></span>
                </h3>
                <div class="recipe-generator-help-section-content">
                    <p><?php _e('If you need further assistance, please reach out through the following channels:', 'recipe-generator'); ?></p>
                    <ul>
                        <li><?php _e('<strong>Support Page:</strong> <a href="https://www.nidacademy.org/contact" target="_blank" rel="noopener noreferrer">NID Academy Contact Page</a>', 'recipe-generator'); ?></li>
                        <li><?php _e('<strong>Email:</strong> <a href="mailto:info@nidacademy.org">info@nidacademy.org</a>', 'recipe-generator'); ?></li>
                        <li><?php _e('<strong>LinkedIn:</strong> <a href="https://www.linkedin.com/in/mohamednidsaid/" target="_blank" rel="noopener noreferrer">Mohamed Nidsaid on LinkedIn</a>', 'recipe-generator'); ?></li>
                    </ul>
                </div>
            </div>

            <div class="recipe-generator-help-section">
                <h3 class="recipe-generator-help-section-title">
                    <?php _e('OpenRouter API Documentation', 'recipe-generator'); ?>
                    <span class="dashicons dashicons-arrow-down-alt2 toggle-icon"></span>
                </h3>
                <div class="recipe-generator-help-section-content">
                    <p><?php _e('OpenRouter provides access to a wide variety of LLMs. Their documentation covers API keys, model selection, and usage.', 'recipe-generator'); ?></p>
                    <p><a href="https://openrouter.ai/docs/quickstart" target="_blank" rel="noopener noreferrer" class="button button-secondary"><?php _e('View OpenRouter Quickstart', 'recipe-generator'); ?></a></p>
                    <p><a href="https://openrouter.ai/docs" target="_blank" rel="noopener noreferrer" class="button button-secondary"><?php _e('View Full OpenRouter Docs', 'recipe-generator'); ?></a></p>
                </div>
            </div>

            <div class="recipe-generator-help-section">
                <h3 class="recipe-generator-help-section-title">
                    <?php _e('Fal.ai API Documentation', 'recipe-generator'); ?>
                    <span class="dashicons dashicons-arrow-down-alt2 toggle-icon"></span>
                </h3>
                <div class="recipe-generator-help-section-content">
                    <p><?php _e('Fal.ai is used for image generation. Their documentation provides information on API usage, available models, and managing credentials.', 'recipe-generator'); ?></p>
                    <p><a href="https://docs.fal.ai/" target="_blank" rel="noopener noreferrer" class="button button-secondary"><?php _e('View Fal.ai Documentation', 'recipe-generator'); ?></a></p>
                </div>
            </div>
        </div>
    </div>

    <style>
        .recipe-generator-help-page #recipe-generator-help-accordion {
            margin-top: 20px;
        }
        .recipe-generator-help-section {
            border: 1px solid #ccd0d4;
            margin-bottom: -1px; /* For continuous border effect */
        }
        .recipe-generator-help-section:first-child {
            border-top-left-radius: 3px;
            border-top-right-radius: 3px;
        }
        .recipe-generator-help-section:last-child {
            border-bottom-left-radius: 3px;
            border-bottom-right-radius: 3px;
            margin-bottom: 0;
        }
        .recipe-generator-help-section-title {
            background-color: #f6f7f7; 
            padding: 10px 15px;
            margin: 0;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            border-bottom: 1px solid #ccd0d4;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .recipe-generator-help-section:last-child .recipe-generator-help-section-title {
             border-bottom: none; /* No bottom border for the last item if content is hidden */
        }
        .recipe-generator-help-section-title.active {
            border-bottom-color: #ccd0d4; /* Ensure border is visible when active */
        }
         .recipe-generator-help-section-title.active + .recipe-generator-help-section-content {
            border-top: 1px solid #ccd0d4;
        }
        .recipe-generator-help-section-title .toggle-icon {
            transition: transform 0.2s ease-in-out;
            font-size: 20px; /* Make icon a bit larger */
        }
        .recipe-generator-help-section-title.active .toggle-icon {
            transform: rotate(-180deg);
        }
        .recipe-generator-help-section-content {
            padding: 15px;
            background-color: #fff;
            display: none; /* Hidden by default */
        }
        .recipe-generator-help-section-content p {
            margin-top: 0;
            margin-bottom: 10px;
        }
        .recipe-generator-help-section-content p:last-child {
            margin-bottom: 0;
        }
        .recipe-generator-help-section-content .button-secondary {
            margin-right: 10px;
        }
    </style>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#recipe-generator-help-accordion .recipe-generator-help-section-title').click(function() {
                var $content = $(this).next('.recipe-generator-help-section-content');
                var $icon = $(this).find('.toggle-icon');

                // Toggle current section
                $content.slideToggle(200);
                $(this).toggleClass('active');
                // $icon.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2'); // This can also work for icon change

                // Optional: Close other sections when one is opened
                // $('#recipe-generator-help-accordion .recipe-generator-help-section-content').not($content).slideUp(200);
                // $('#recipe-generator-help-accordion .recipe-generator-help-section-title').not($(this)).removeClass('active');
            });
        });
    </script>
    <?php
}


/**
 * Callback for the Fal AI settings section.
 */
function recipe_generator_section_fal_ai_callback() {
    echo '<p>' . esc_html__( 'Enter your Fal AI API key for image generation.', 'recipe-generator' ) . '</p>';
}

/**
 * Render HTML for the Fal AI API Key field.
 */
function recipe_generator_field_fal_ai_api_key_html( $args ) {
    $options = get_option( 'recipe_generator_settings' );
    ?>
    <input type="password" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="recipe_generator_settings[fal_ai_api_key]" value="<?php echo esc_attr( $options['fal_ai_api_key'] ?? '' ); ?>" class="regular-text">
    <p class="description"><?php esc_html_e( 'Your Fal AI API key (e.g., Key YOUR_FAL_KEY_ID:YOUR_FAL_KEY_SECRET). The plugin will prepend "Key " if not present.', 'recipe-generator' ); ?></p>
    <?php
}

/**
 * Callback for the API settings section.
 */
function recipe_generator_section_api_callback() {
    echo '<p>' . esc_html__( 'Enter your OpenRouter API key and choose your preferred model.', 'recipe-generator' ) . '</p>';
}

/**
 * Render HTML for the API Key field.
 */
function recipe_generator_field_api_key_html( $args ) {
    $options = get_option( 'recipe_generator_settings' );
    ?>
    <input type="password" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="recipe_generator_settings[api_key]" value="<?php echo esc_attr( $options['api_key'] ?? '' ); ?>" class="regular-text">
    <p class="description"><?php esc_html_e( 'Your OpenRouter API key.', 'recipe-generator' ); ?></p>
    <?php
}

/**
 * Render HTML for the LLM Model selection field.
 */
function recipe_generator_field_model_html( $args ) {
    $options = get_option( 'recipe_generator_settings' );
    $current_selected_model = $options['selected_model'] ?? 'openai/gpt-3.5-turbo'; // Default model

    $available_models = recipe_generator_get_available_models();

    ?>
    <select id="<?php echo esc_attr( $args['label_for'] ); ?>" name="recipe_generator_settings[selected_model]">
        <?php foreach ( $available_models as $model_id => $model_name ) : ?>
            <option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $current_selected_model, $model_id ); ?>>
                <?php echo esc_html( $model_name ); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="button" id="refresh-models-button" class="button"> <?php _e('Refresh List', 'recipe-generator'); ?> </button>
    <span id="refresh-models-status" style="margin-left: 10px;"></span>
    <p class="description"><?php esc_html_e( 'Choose the language model for generating recipes. Click "Refresh List" to fetch the latest models from OpenRouter (requires API key to be set).', 'recipe-generator' ); ?></p>
    
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#refresh-models-button').on('click', function() {
                var $button = $(this);
                var $status = $('#refresh-models-status');
                var $select = $('#<?php echo esc_js( $args['label_for'] ); ?>'); // Use the ID passed to the function
                var originalSelectedValue = $select.val();

                $button.prop('disabled', true);
                $status.html('<span class="spinner is-active" style="float:none; vertical-align: middle;"></span> ' + '<?php echo esc_js(__('Refreshing models...', 'recipe-generator')); ?>')
                       .attr('class', 'notice notice-info inline recipe-generator-notice')
                       .css('display', 'inline-block');

                $.ajax({
                    url: '<?php echo esc_js(admin_url( 'admin-ajax.php' )); ?>',
                    type: 'POST',
                    data: {
                        action: 'recipe_generator_refresh_models',
                        security: '<?php echo esc_js(wp_create_nonce( 'recipe_generator_refresh_models_nonce' )); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.html('<?php echo esc_js(__('Model list refreshed!', 'recipe-generator')); ?>')
                                   .attr('class', 'notice notice-success inline recipe-generator-notice');
                            $select.empty();
                            if (response.data.models && Object.keys(response.data.models).length > 0) {
                                var modelExists = false;
                                $.each(response.data.models, function(id, name) {
                                    var $option = $('<option>', { value: id, text: name });
                                    if (id === originalSelectedValue) {
                                        $option.prop('selected', true);
                                        modelExists = true;
                                    }
                                    $select.append($option);
                                });
                                if (!modelExists && $select.find('option').length > 0) {
                                    $select.find('option:first').prop('selected', true);
                                }
                            } else {
                                $status.html('<?php echo esc_js(__('No models returned from API.', 'recipe-generator')); ?>')
                                       .attr('class', 'notice notice-warning inline recipe-generator-notice');
                            }
                        } else {
                            var errorMessage = '<?php echo esc_js(__('Error. Check console.', 'recipe-generator')); ?>';
                            if (response.data && response.data.message) {
                                errorMessage += ' ' + response.data.message; // Append specific error from server
                            }
                            $status.html(errorMessage).attr('class', 'notice notice-error inline recipe-generator-notice');
                            console.error('Error refreshing models:', response.data ? response.data.message : 'Unknown error');
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        $status.html('<?php echo esc_js(__('AJAX error. Check console.', 'recipe-generator')); ?>').attr('class', 'notice notice-error inline recipe-generator-notice');
                        console.error('AJAX error:', textStatus, errorThrown);
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                        setTimeout(function() {
                            $status.fadeOut(function() { $(this).html('').removeAttr('class').removeAttr('style'); });
                        }, 7000);
                    }
                });
            });

            // Basic styling for notices, if not already present
            if ($('style#recipe-generator-admin-styles').length === 0) {
                $('<style id="recipe-generator-admin-styles">' +
                  '.recipe-generator-notice.inline { margin-left: 10px; padding: 5px 10px; }' +
                  '.recipe-generator-notice .spinner { margin-right: 5px; }' +
                  '</style>').appendTo('head');
            }
        });
    </script>
    <?php
}

/**
 * Sanitize settings.
 */
function recipe_generator_sanitize_settings( $input ) {
    $new_input = [];

    if ( isset( $input['api_key'] ) ) {
        $new_input['api_key'] = sanitize_text_field( $input['api_key'] );
    }

    if ( isset( $input['selected_model'] ) ) {
        $new_input['selected_model'] = sanitize_text_field( $input['selected_model'] );
    }

    if ( isset( $input['fal_ai_api_key'] ) ) {
        $fal_key = sanitize_text_field( $input['fal_ai_api_key'] );
        // Ensure the key starts with "Key " for Fal AI authorization header
        if ( !empty($fal_key) && strpos($fal_key, 'Key ') !== 0 ) {
            // Check if it's in the format key_id:key_secret without "Key " prefix
            if (preg_match('/^[a-zA-Z0-9]+:[a-zA-Z0-9]+$/', $fal_key)) {
                 $new_input['fal_ai_api_key'] = 'Key ' . $fal_key;
            } else {
                // If it's some other format or just a partial key, store as is for now, or consider erroring.
                // For simplicity, we'll store it. User might be pasting the full "Authorization: Key ..." string.
                // A more robust validation might be needed depending on exact Fal AI key formats.
                $new_input['fal_ai_api_key'] = $fal_key; 
            }
        } else {
            $new_input['fal_ai_api_key'] = $fal_key;
        }
    }

    return $new_input;
}

// NOTE: The original content of recipe_generator_sanitize_settings was removed 
// because it's being fully replaced by the content above to ensure correct sanitization of new fields.
// The original function only contained sanitization for api_key and selected_model.
// The following is a placeholder for the original content that is now integrated above.
/* function recipe_generator_sanitize_settings( $input ) { 
    $new_input = [];

    if ( isset( $input['api_key'] ) ) {
        $new_input['api_key'] = sanitize_text_field( $input['api_key'] );
    }

    if ( isset( $input['selected_model'] ) ) {
        $new_input['selected_model'] = sanitize_text_field( $input['selected_model'] );
    }

    // Add other settings sanitization here if needed

    return $new_input;
} */

function recipe_generator_sanitize_settings_original_content_placeholder() {
    $sanitized_input = [];
    if ( isset( $input['api_key'] ) ) {
        $sanitized_input['api_key'] = sanitize_text_field( $input['api_key'] );
    }

    // Sanitize selected_model
    if ( isset( $input['selected_model'] ) ) {
        // Basic sanitization, ensure it's a string. More robust validation could check against the known list.
        $sanitized_input['selected_model'] = sanitize_text_field( $input['selected_model'] );
    }

    return $sanitized_input;
}

/**
 * Function to get the cached models or a default list
 */
function recipe_generator_get_available_models() {
    $cached_models = get_option('recipe_generator_models_cache');
    if ( !empty($cached_models) && is_array($cached_models) ) {
        return $cached_models;
    }
    // Fallback to a default list if cache is empty or invalid
    return [
        'openai/gpt-3.5-turbo' => 'OpenAI: GPT-3.5 Turbo (Default)',
        'openai/gpt-4' => 'OpenAI: GPT-4',
        'openai/gpt-4o' => 'OpenAI: GPT-4o',
        'anthropic/claude-3-opus-20240229' => 'Anthropic: Claude 3 Opus',
        'anthropic/claude-3-sonnet-20240229' => 'Anthropic: Claude 3 Sonnet',
        'anthropic/claude-3-haiku-20240307' => 'Anthropic: Claude 3 Haiku',
        'google/gemini-pro' => 'Google: Gemini Pro',
        'meta-llama/llama-3-70b-instruct' => 'Meta: Llama 3 70B Instruct',
        'mistralai/mistral-7b-instruct' => 'Mistral AI: Mistral 7B Instruct',
    ];
}

/**
 * Function to fetch models from OpenRouter API
 */
function recipe_generator_fetch_and_cache_models() {
    $options = get_option('recipe_generator_settings');
    $api_key = $options['api_key'] ?? '';

    if (empty($api_key)) {
        return new WP_Error('api_key_missing', __('API key is not set. Please set your OpenRouter API Key in the plugin settings.', 'recipe-generator'));
    }

    $api_url = 'https://openrouter.ai/api/v1/models';
    $request_args = [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
            'HTTP-Referer'  => get_site_url(), // Required by OpenRouter
            'X-Title'       => get_bloginfo('name') // Optional: Your site name
        ],
        'timeout' => 30,
    ];

    $response = wp_remote_get($api_url, $request_args);

    if (is_wp_error($response)) {
        return new WP_Error('api_request_failed', $response->get_error_message());
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code !== 200) {
        $error_data = json_decode($response_body, true);
        $error_message = $error_data['error']['message'] ?? __('Unknown API error while fetching models.', 'recipe-generator');
        return new WP_Error('api_error', sprintf(__('API Error (%d): %s', 'recipe-generator'), $response_code, $error_message));
    }

    $data = json_decode($response_body, true);

    if (!isset($data['data']) || !is_array($data['data'])) {
        return new WP_Error('invalid_response_format', __('Unexpected API response format when fetching models.', 'recipe-generator'));
    }

    $models = [];
    foreach ($data['data'] as $model_data) {
        if (isset($model_data['id']) && isset($model_data['name'])) {
            // Add context like pricing or context window if desired, e.g., $model_data['context_length']
            $models[$model_data['id']] = $model_data['name'] . (isset($model_data['id']) && $model_data['id'] === 'openai/gpt-3.5-turbo' ? ' (Default)' : '');
        }
    }

    if (empty($models)) {
        return new WP_Error('no_models_found', __('No models found in API response.', 'recipe-generator'));
    }
    
    // Ensure the default model is present if somehow missed, or select a sensible default
    if (!isset($models['openai/gpt-3.5-turbo'])) {
        // This case should ideally not happen if OpenRouter always lists it
        // Or, pick the first model from the fetched list as a fallback default if 'openai/gpt-3.5-turbo' is truly unavailable
    }

    update_option('recipe_generator_models_cache', $models);
    return $models;
}

/**
 * AJAX handler for refreshing models
 */
add_action('wp_ajax_recipe_generator_refresh_models', 'recipe_generator_ajax_refresh_models');

function recipe_generator_ajax_refresh_models() {
    // Verify nonce for security
    check_ajax_referer('recipe_generator_refresh_models_nonce', 'security');

    $result = recipe_generator_fetch_and_cache_models();

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    } else {
        wp_send_json_success(['models' => $result]);
    }
}

/**
 * AJAX handler for generating recipe content.
 */
add_action( 'wp_ajax_generate_recipe_content_ajax', 'recipe_generator_ajax_generate_content_handler' );

function recipe_generator_ajax_generate_content_handler() {
    // Verify the nonce
    // The nonce field name sent by JS FormData is 'recipe_generator_nonce'
    // The action used when creating the nonce was 'generate_recipe_action'
    check_ajax_referer( 'generate_recipe_action', 'recipe_generator_nonce' );

    // Prepare args for the API call directly from $_POST values
    $args = [
        'post_title'      => isset( $_POST['post_title'] ) ? sanitize_text_field( $_POST['post_title'] ) : '',
        'focus_keyword'   => isset( $_POST['focus_keyword'] ) ? sanitize_text_field( $_POST['focus_keyword'] ) : '',
        'related_keywords' => isset( $_POST['related_keywords'] ) ? sanitize_text_field( $_POST['related_keywords'] ) : '',
        'internal_link'   => isset( $_POST['internal_link'] ) ? esc_url_raw( $_POST['internal_link'] ) : '',
        'external_link'   => isset( $_POST['external_link'] ) ? esc_url_raw( $_POST['external_link'] ) : '',
        'generate_featured_image' => isset( $_POST['generate_featured_image'] ), // Checkbox value
        'generate_ingredients_image' => isset( $_POST['generate_ingredients_image'] ), // Checkbox value
        'generate_steps_image' => isset( $_POST['generate_steps_image'] ), // Checkbox value
    ];

    // Call the existing function to generate the post
    $result = recipe_generator_generate_recipe_post( $args );

    if ( $result['success'] && $result['post_id'] ) {
        // $result['message'] from recipe_generator_generate_recipe_post already contains the main success text and image status.
        // We just need to append the edit link to it for the AJAX response.
        $edit_link_html = ' <a href="' . esc_url( get_edit_post_link( $result['post_id'], 'raw' ) ) . '">' . esc_html__( 'Edit Post', 'recipe-generator' ) . '</a>';
        $final_ajax_message = $result['message'] . $edit_link_html;

        wp_send_json_success( [
            'message'   => $final_ajax_message,
            'post_id'   => $result['post_id']
        ] );
    } else {
        wp_send_json_error( [
            'message' => $result['message'] ? $result['message'] : __( 'An unknown error occurred during recipe generation.', 'recipe-generator' ),
        ] );
    }
}

/**
 * Generate recipe post content using OpenRouter API.
 *
 * @param array $args { 
 *     @type string $post_title
 *     @type string $focus_keyword
 *     @type string $related_keywords
 *     @type string $internal_link
 *     @type string $external_link
 *     @type string $recipe_custom_prompt
 * }
 * @return array { 
 *     @type bool   $success True on success, false on failure.
 *     @type int|null $post_id The ID of the created post on success, null otherwise.
 *     @type string $message A success or error message.
 *     @type string|null $content The generated content if successful before post creation, null otherwise.
 * } 
 */
/**
 * Generate an image using Fal AI, upload it to WordPress Media Library, and return the attachment ID.
 *
 * @param string $focus_keyword The focus keyword to use in the image prompt.
 * @param string $post_title The title of the post, used for image alt text.
 * @return int|false The attachment ID on success, false on failure.
 */
function recipe_generator_generate_and_upload_fal_image( $prompt, $post_title, $focus_keyword_for_alt ) {
    $options = get_option( 'recipe_generator_settings' );
    $fal_api_key = isset( $options['fal_ai_api_key'] ) ? $options['fal_ai_api_key'] : '';

    if ( empty( $fal_api_key ) ) {
        error_log( 'Fal AI API Key is not set. Skipping image generation.' );
        return false;
    }

    $request_id = ''; // Initialize to prevent undefined variable warning
    $image_url = '';  // Initialize to prevent undefined variable warning

    if ( empty( $prompt ) ) {
        error_log('Prompt is empty, cannot generate Fal AI image.');
        return false;
    }

    
    // Ensure the API key starts with "Key " if it's in the id:secret format
    if (strpos($fal_api_key, 'Key ') !== 0 && preg_match('/^[a-zA-Z0-9\-_]+:[a-zA-Z0-9\-_]+$/', $fal_api_key)) {
        $fal_api_key = 'Key ' . $fal_api_key;
    }

    $initial_request_url = 'https://fal.run/fal-ai/recraft/v3/text-to-image';
    $initial_request_args = [
        'method'  => 'POST',
        'headers' => [
            'Authorization' => $fal_api_key,
            'Content-Type'  => 'application/json',
        ],
        'body'    => json_encode( [
            'prompt'     => $prompt,
            'image_size' => 'portrait_4_3',
            'style'      => 'realistic_image',
            'colors'     => []
        ] ),
        'timeout' => 30, // 30 seconds for the initial request
    ];

    $initial_response = wp_remote_post( $initial_request_url, $initial_request_args );

    if ( is_wp_error( $initial_response ) ) {
        error_log( 'Fal AI initial request failed: ' . $initial_response->get_error_message() );
        return false;
    }

    $initial_body = wp_remote_retrieve_body( $initial_response );
    $initial_data = json_decode( $initial_body, true );
    $image_url = null;

    // Check if the initial response contains the image URL directly
    if ( isset( $initial_data['images'][0]['url'] ) ) {
        $image_url = $initial_data['images'][0]['url'];
        error_log( "Fal AI: Image URL found directly in initial response: " . $image_url );
    } elseif ( isset( $initial_data['request_id'] ) ) {
        // Proceed with polling if request_id is present and no direct image URL
        $request_id = $initial_data['request_id'];
        error_log( "Fal AI: Initial response contains request_id: {$request_id}. Proceeding with polling." );
        $status_url_base = 'https://fal.run/fal-ai/recraft/v3/text-to-image/requests/'; // Base URL for status checks
        $status_url = $status_url_base . $request_id . '/response'; // Fal often uses /response for the final result
        
        $max_retries = 10; // Increased retries
        $retry_delay = 15; // 15 seconds delay

        for ( $i = 0; $i < $max_retries; $i++ ) {
        sleep( $retry_delay );
        $status_response = wp_remote_get( $status_url, array(
            'headers' => array('Authorization' => $fal_api_key),
            'timeout' => 20,
        ) );

        if ( is_wp_error( $status_response ) ) {
            error_log( "Fal AI status check failed for request_id {$request_id}: " . $status_response->get_error_message() );
            continue; // Try again
        }

        $status_code = wp_remote_retrieve_response_code( $status_response );
        $status_body = wp_remote_retrieve_body( $status_response );
        $status_data = json_decode( $status_body, true );

        // Check for explicit status field first
        if ( isset( $status_data['status'] ) ) {
            $current_status = strtolower( $status_data['status'] );
            if ( $current_status === 'completed' || $current_status === 'succeeded' ) {
                if ( isset( $status_data['image_url'] ) ) {
                    $image_url = $status_data['image_url'];
                    error_log("Fal AI image for request_id {$request_id} completed. Image URL found.");
                    break;
                } elseif (isset($status_data['response']['image_url'])) { // Common nested structure
                    $image_url = $status_data['response']['image_url'];
                    error_log("Fal AI image for request_id {$request_id} completed. Nested image URL found.");
                    break;
                } else {
                    error_log("Fal AI image for request_id {$request_id} status is {$current_status}, but no image_url found. Body: " . $status_body);
                    // Potentially break if status is success but no URL, or let it timeout
                }
            } elseif ( $current_status === 'failed' || $current_status === 'error' ) {
                error_log("Fal AI image for request_id {$request_id} failed with status: {$current_status}. Body: " . $status_body);
                return false; // Explicit failure, stop polling
            } elseif ( $current_status === 'processing' || $current_status === 'in_progress' || $current_status === 'pending' ) {
                error_log("Fal AI image for request_id {$request_id} status: {$current_status} (Attempt: " . ($i+1) . ")");
                // Continue polling
            } else {
                // Unknown status, log and continue polling or break depending on desired behavior
                error_log("Fal AI image for request_id {$request_id} has unknown status: {$current_status}. Body: " . $status_body);
            }
        } elseif ( $status_code === 200 && isset($status_data['image_url']) ) {
            // Fallback: No explicit status field, but image_url is present (original logic for direct /response output)
            $image_url = $status_data['image_url'];
            error_log("Fal AI image for request_id {$request_id} image_url found directly (no explicit status field).");
            break;
        } else {
            // Log other statuses or unexpected responses if no status field and no image_url
            error_log("Fal AI status check for request_id {$request_id} returned status code {$status_code} with no explicit status or image_url. Body: " . $status_body);
        }
    } // End of for loop
} // End of elseif ( isset( $initial_data['request_id'] ) )

    // If polling was skipped because image_url was found directly, or if polling completed, this check is still valid.
    if ( empty( $image_url ) ) {
        error_log( "Fal AI image generation timed out or no image_url found after polling for request_id {$request_id}." );
        return false;
    }

    error_log("Fal AI: Successfully retrieved image URL: {$image_url} for Fal request_id {$request_id}. Attempting to sideload.");

    // We need 'wp-admin/includes/media.php', 'wp-admin/includes/file.php', 'wp-admin/includes/image.php'
    if ( !function_exists('media_sideload_image') ) {
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
    }

    // Sideload the image - Use the focus keyword for a concise and relevant alt text/title
    $image_description = sanitize_text_field( $focus_keyword_for_alt );
    
    error_log("Fal AI: Calling media_sideload_image with URL: {$image_url} and description: {$image_description}");
    $attachment_id = media_sideload_image( $image_url, 0, esc_html( $image_description ), 'id' ); // Pass 0 for post_id, 'id' to get ID back

    if ( is_wp_error( $attachment_id ) ) {
        error_log( 'Fal AI image sideload failed. URL was: ' . $image_url . ' | WordPress Error: ' . $attachment_id->get_error_message() );
        return false;
    } else if ( !$attachment_id || !is_numeric($attachment_id) ){
        error_log( 'Fal AI image sideload did not return a valid attachment ID. URL was: ' . $image_url . ' | Result: ' . print_r($attachment_id, true) );
        return false;
    }

    error_log("Fal AI: Successfully sideloaded image. Attachment ID: {$attachment_id} from URL: {$image_url}");
    return $attachment_id;
}

function recipe_generator_generate_recipe_post( $args ) {
    $options = get_option( 'recipe_generator_settings' );
    $api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
    $selected_model = isset( $options['selected_model'] ) && !empty( $options['selected_model'] ) ? $options['selected_model'] : 'openai/gpt-3.5-turbo'; // Default model

    if ( empty( $api_key ) ) {
        return [
            'success' => false,
            'post_id' => null,
            'message' => __( 'Error: API Key is not configured. Please set your OpenRouter API Key in the plugin settings.', 'recipe-generator' ),
            'content' => null
        ];
    }

    // Use provided args or fall back to empty strings if not set (defaults from settings are removed)
    $post_title      = !empty($args['post_title']) ? $args['post_title'] : __( 'New Recipe', 'recipe-generator' );
    $focus_keyword   = !empty($args['focus_keyword']) ? $args['focus_keyword'] : '';
    $related_keywords = !empty($args['related_keywords']) ? $args['related_keywords'] : '';
    $internal_link   = !empty($args['internal_link']) ? $args['internal_link'] : '#';
    $external_link   = !empty($args['external_link']) ? $args['external_link'] : '';
    $final_image_status_message = ''; // Initialize image status message

    // Image generation flags
    $generate_featured_image = !empty($args['generate_featured_image']); // True if checked
    $generate_ingredients_image = !empty($args['generate_ingredients_image']);
    $generate_steps_image = !empty($args['generate_steps_image']);

    // Construct the new detailed prompt for HTML output
    $base_prompt = "Act as a professional blogger, SEO specialist, and generative engine optimization (GEO) expert. Generate a comprehensive, data-driven, and easy-to-follow recipe article formatted directly in HTML suitable for a WordPress post. Ensure the article is a comprehensive WordPress blog post that is at least 900 words long, and ideally closer to 1000-1500 words for thoroughness. Follow the structure and guidelines below strictly, using advanced generative techniques to ensure the content is engaging, personalized, and semantically optimized for search engines.

Blog Post Title: %s 
Focus Keyword: %s

Important: Ensure all paragraphs in the generated content are concise, ideally under 60 words each, to maintain readability.
Related keywords: %s

Integrate the focus keyword naturally throughout the post, particularly in the main title (e.g., <h1>Title</h1> or as the post title itself), subheadings (e.g., <h2>Section</h2> or <h3>Subsection</h3>), and within the first 100 words (e.g., in a <p> tag).
Use semantic variations and related keywords to enhance search engine context and relevance.

HTML Formatting for Specific Keywords:
1. Focus Keyword Linking: The Focus Keyword for this article is '%s'. The Internal Link URL is '%s'. In the HTML content you generate, please find up to 4 natural textual occurrences of this Focus Keyword. For each of these occurrences (up to a maximum of 4), format it exactly as: <a href=\"%s\" target=\"_blank\"><strong>%s</strong></a>.
2. Related Keyword Linking: The list of Related Keywords for this article is: '%s'. The External Link URL is '%s'. In the HTML content, find the first natural textual occurrence of *any one* keyword from this list. When found, format that specific keyword you've chosen exactly as: <a href=\"%s\" target=\"_blank\"><strong>[The Chosen Related Keyword from your list]</strong></a>. Only link the first related keyword you encounter from the list.

Content Structure (use HTML tags):

<p>Start with an intriguing question that captures the reader’s attention immediately. Ensure the question is relevant to “Your recipe,” challenges common beliefs, and evokes curiosity.</p>
<p>Seamlessly incorporate the focus keyword within the first 100 words.</p>

<h2>Ingredients List</h2>
<p>Provide a clear, organized list of ingredients. Use <ul> and <li> tags for the list. Include suggestions for potential substitutions. Use engaging language and sensory descriptions to enhance reader interest.</p>

<h2>Timing</h2>
<p>Detail the preparation, cooking, and total time required.</p>
<p>Include any data or comparisons that can add context (e.g., “90 minutes, which is 20%% less time than the average recipe”).</p>

<h3>Step-by-Step Instructions</h3>
<p>Present clear, easy-to-follow steps. Use <h3>Step X: Title</h3> for each step's heading, followed by <p> tags for instructions. For ordered steps within a larger instruction set, you can use <ol> and <li>. Include actionable tips and tricks that add value, ensuring each step feels engaging and tailored.</p>

<h2>Nutritional Information</h2>
<p>Provide comprehensive nutritional details, citing data insights where applicable. Wrap this section in appropriate HTML (e.g., <p> or a <div> with paragraphs).</p>

<h2>Healthier Alternatives for the Recipe</h2>
<p>Suggest modifications or ingredient swaps that maintain flavor while enhancing nutritional benefits. Offer creative ideas to make the recipe adaptable for various dietary needs.</p>

<h2>Serving Suggestions</h2>
<p>Offer creative, appealing serving suggestions that resonate with a broad audience. Incorporate personalized tips that make the dish more inviting and versatile.</p>

<h2>Common Mistakes to Avoid</h2>
<p>List typical pitfalls with insights on how to avoid them. Use a mix of data insights and experiential advice to enhance credibility. This could be a <ul> or <ol>.</p>

<h2>Storing Tips for the Recipe</h2>
<p>Provide practical advice on storing leftovers or prepping ingredients ahead of time. Emphasize best practices for maintaining freshness and flavor.</p>

<h2>Conclusion</h2>
<p>Summarize the key points of the recipe.</p>
<p>Include a dynamic call-to-action that invites readers to try the recipe, share feedback, or explore similar posts.</p>
IMPORTANT: Please ensure you complete ALL requested sections of the article as outlined above, from the introduction to the conclusion. Strive to meet the target word count by providing thorough information in each section to deliver a complete and valuable post.
";

    $prompt = sprintf(
        $base_prompt,
        $post_title,         // 1st %s: Blog Post Title
        $focus_keyword,      // 2nd %s: Focus Keyword (definition)
        $related_keywords,   // 3rd %s: Related keywords (definition)
        $focus_keyword,      // 4th %s: "The Focus Keyword for this article is '%s'"
        $internal_link,      // 5th %s: "The Internal Link URL is '%s'"
        $internal_link,      // 6th %s: href for focus keyword link
        $focus_keyword,      // 7th %s: text for focus keyword link
        $related_keywords,   // 8th %s: "The list of Related Keywords for this article is: '%s'"
        $external_link,      // 9th %s: "The External Link URL is '%s'"
        $external_link       // 10th %s: href for related keyword link
    );

    $api_url = 'https://openrouter.ai/api/v1/chat/completions'; // OpenRouter API endpoint
    $request_args = [
        'method'  => 'POST',
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
            'HTTP-Referer'  => get_site_url(), // Required by OpenRouter
            'X-Title'       => get_bloginfo('name') // Optional: Your site name
        ],
        'body'    => json_encode([
            'model'    => $selected_model, // Use the selected model
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 3800, // Adjusted max_tokens
            'temperature' => 0.6, // Lowered temperature
        ]),
        'timeout' => 120, // Timeout remains 120 seconds
    ];

    $response = wp_remote_post( $api_url, $request_args );

    if ( is_wp_error( $response ) ) {
        return [
            'success' => false,
            'post_id' => null,
            'message' => __( 'Error: API request failed. ', 'recipe-generator' ) . $response->get_error_message(),
            'content' => null
        ];
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( ! $data || ! isset( $data['choices'][0]['message']['content'] ) ) {
        $error_message = isset($data['error']['message']) ? $data['error']['message'] : __( 'Invalid API response or no content received.', 'recipe-generator' );
        return [
            'success' => false,
            'post_id' => null,
            'message' => __( 'Error: ', 'recipe-generator' ) . $error_message,
            'content' => null
        ];
    }

    $generated_content = $data['choices'][0]['message']['content'];

    // Sanitize the HTML content received from AI
    $content = recipe_generator_sanitize_content( $generated_content ); 

    // Generate and insert additional images into the content if Fal AI is configured and selected
    $options = get_option( 'recipe_generator_settings' ); // Re-fetch options for safety
    $fal_api_key_present = !empty($options['fal_ai_api_key']);
    $focus_keyword_present = !empty($focus_keyword);
    $can_generate_fal_images = $fal_api_key_present && $focus_keyword_present;

    // In-content images generation messages
    $ingredients_image_message = '';
    $steps_image_message = '';

    if ( $can_generate_fal_images ) {
        // 1. Ingredients Image
        if ( $generate_ingredients_image ) {
            $ingredients_image_prompt = "an image of " . $focus_keyword . " ingredients";
            $ingredients_image_alt = $focus_keyword . " ingredients";
            $ingredients_image_id = recipe_generator_generate_and_upload_fal_image( $ingredients_image_prompt, $post_title . ' - Ingredients', $ingredients_image_alt );
            if ( $ingredients_image_id && !is_wp_error($ingredients_image_id) ) {
                $ingredients_image_url = wp_get_attachment_url( $ingredients_image_id );
                if ($ingredients_image_url) {
                    $ingredients_img_html = '<figure class="wp-block-image size-large"><img src="' . esc_url($ingredients_image_url) . '" alt="' . esc_attr($ingredients_image_alt) . '"/></figure>';
                    // Inject after <h2>Ingredients List</h2>
                    $content = preg_replace( '/(<h2[^>]*>\s*Ingredients List\s*<\/h2>)/i', '$1' . "\n" . $ingredients_img_html, $content, 1 );
                    error_log("Fal AI: Inserted ingredients image for post being created with title: " . $post_title);
                } else {
                    error_log("Fal AI: Could not get URL for ingredients image ID: " . $ingredients_image_id);
                }
            } else {
                error_log("Fal AI: Failed to generate ingredients image. Error: " . (is_wp_error($ingredients_image_id) ? $ingredients_image_id->get_error_message() : 'Unknown error'));
                $ingredients_image_message = __( 'Ingredients image generation failed.', 'recipe-generator' );
            }
        } else {
            $ingredients_image_message = __( 'Ingredients image generation skipped by user.', 'recipe-generator' );
            error_log("Fal AI: Ingredients image generation skipped by user for post: " . $post_title);
        }

        // 2. Preparing Steps Image
        if ( $generate_steps_image ) {
            $steps_image_prompt = "an image of " . $focus_keyword . " preparing steps";
            $steps_image_alt = $focus_keyword . " preparing steps";
            $steps_image_id = recipe_generator_generate_and_upload_fal_image( $steps_image_prompt, $post_title . ' - Preparing Steps', $steps_image_alt );
            if ( $steps_image_id && !is_wp_error($steps_image_id) ) {
                $steps_image_url = wp_get_attachment_url( $steps_image_id );
                if ($steps_image_url) {
                    $steps_img_html = '<figure class="wp-block-image size-large"><img src="' . esc_url($steps_image_url) . '" alt="' . esc_attr($steps_image_alt) . '"/></figure>';
                    // Inject after the step-by-step instructions heading
                    // Regex looks for <h3> containing "Instructions" or "Steps", optionally with "Step-by-Step"
                    $content = preg_replace( '/(<h3[^>]*>(?:Step-by-Step\s+)?(?:Instructions|Steps).*?<\/h3>)/is', '$1' . "\n" . $steps_img_html, $content, 1 );
                    error_log("Fal AI: Inserted steps image for post being created with title: " . $post_title);
                } else {
                    error_log("Fal AI: Could not get URL for steps image ID: " . $steps_image_id);
                }
            } else {
                error_log("Fal AI: Failed to generate steps image. Error: " . (is_wp_error($steps_image_id) ? $steps_image_id->get_error_message() : 'Unknown error'));
                $steps_image_message = __( 'Steps image generation failed.', 'recipe-generator' );
            }
        } else {
            $steps_image_message = __( 'Steps image generation skipped by user.', 'recipe-generator' );
            error_log("Fal AI: Steps image generation skipped by user for post: " . $post_title);
        }
    } else if (!$fal_api_key_present) {
        if ($generate_ingredients_image) $ingredients_image_message = __( 'Ingredients image skipped (Fal AI key missing).', 'recipe-generator' );
        if ($generate_steps_image) $steps_image_message = __( 'Steps image skipped (Fal AI key missing).', 'recipe-generator' );
        error_log("Fal AI: In-content image generation skipped (Fal AI key missing) for post: " . $post_title);
    } else if (!$focus_keyword_present) {
        if ($generate_ingredients_image) $ingredients_image_message = __( 'Ingredients image skipped (Focus keyword missing).', 'recipe-generator' );
        if ($generate_steps_image) $steps_image_message = __( 'Steps image skipped (Focus keyword missing).', 'recipe-generator' );
        error_log("Fal AI: In-content image generation skipped (Focus keyword missing) for post: " . $post_title);
    }

    // Create the post data
    $post_data = [
        'post_title'   => wp_strip_all_tags( $post_title ),
        'post_content' => $content, // Use HTML content directly from AI (after sanitization)
        'post_status'  => 'draft', // Save as draft initially
        'post_author'  => get_current_user_id(),
        'post_type'    => 'post', // Explicitly set post type
    ];

    $new_post_id = wp_insert_post( $post_data );

    if ( is_wp_error( $new_post_id ) ) {
        return [
            'success' => false,
            'post_id' => null,
            'message' => __( 'Error creating post: ', 'recipe-generator' ) . $new_post_id->get_error_message(),
            'content' => $generated_content // Return content so user doesn't lose it
        ];
    }

    // If post created successfully, try to set Fal AI generated featured image
    $fal_message_addon = ''; // This will store featured image status
    $options = get_option( 'recipe_generator_settings' ); // Re-fetch options for safety

    if ( $generate_featured_image ) {
        if ( !empty($options['fal_ai_api_key']) && !empty($focus_keyword) ) {
            $fal_image_prompt = "an image of " . $focus_keyword . " dish";
            // Pass the focus_keyword itself for alt text usage
            $featured_image_id = recipe_generator_generate_and_upload_fal_image( $fal_image_prompt, $post_title, $focus_keyword );

            if ( $featured_image_id ) {

                set_post_thumbnail( $new_post_id, $featured_image_id );
                $fal_message_addon = ' ' . __( 'Featured image generated and set via Fal AI.', 'recipe-generator' );
                error_log("Fal AI: Successfully generated and set featured image for post {$new_post_id}. Attachment ID: {$featured_image_id}");
            } else {
                $fal_message_addon = ' ' . __( 'Featured image generation/setting failed via Fal AI.', 'recipe-generator' );
                error_log('Fal AI: Failed to generate/set featured image for post ID: ' . $new_post_id . (is_wp_error($featured_image_id) ? ' Error: ' . $featured_image_id->get_error_message() : ''));
            }
        } else if (empty($options['fal_ai_api_key'])) {
            $fal_message_addon = ' ' . __( 'Featured image generation skipped (Fal AI API key not set).', 'recipe-generator' );
            error_log('Fal AI: Featured image generation skipped (Fal AI API key not set) for post ID: ' . $new_post_id);
        } else { // Focus keyword missing
            $fal_message_addon = ' ' . __( 'Featured image generation skipped (Focus keyword not provided).', 'recipe-generator' );
            error_log('Fal AI: Featured image generation skipped (Focus keyword not provided) for post ID: ' . $new_post_id);
        }
    } else { // User chose not to generate featured image
        $fal_message_addon = ' ' . __( 'Featured image generation skipped by user.', 'recipe-generator' );
        error_log('Fal AI: Featured image generation skipped by user for post ID: ' . $new_post_id);
    }


    // Save focus keyword and related keywords as post meta
    if ( ! empty( $focus_keyword ) ) {
        update_post_meta( $new_post_id, 'recipe_generator_focus_keyword', sanitize_text_field( $focus_keyword ) );
    }
    if ( ! empty( $related_keywords ) ) {
        update_post_meta( $new_post_id, 'recipe_generator_related_keywords', sanitize_text_field( $related_keywords ) );
    }
    // Add a meta field to identify this as an AI recipe
    update_post_meta( $new_post_id, '_recipe_generator_ai_recipe', true );

    return [
        'success' => true,
        'post_id' => $new_post_id,
        'message' => '<span class="dashicons dashicons-yes-alt"></span> ' . __( 'Recipe draft created successfully.', 'recipe-generator' ) . $final_image_status_message,
        'content' => $content // Return the generated HTML content
    ];
}

/**
 * Sanitize the content received from the AI.
 */
function recipe_generator_sanitize_content( $content ) {
    $allowed_html = [
        'h2'     => [],
        'h3'     => [],
        'ul'     => [],
        'ol'     => [],
        'li'     => [],
        'p'      => [],
        'a'      => [ 'href' => true, 'target' => true ],
        'strong' => [],
        'em'     => [],
        'br'     => [],
    ];
    return wp_kses( $content, $allowed_html );
}

/**
 * Render the shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output for the shortcode.
 */
function recipe_generator_render_shortcode( $atts ) {
    $atts = shortcode_atts([
        'post_title'      => '',
        'focus_keyword'   => '',
        'related_keywords' => '',
        'internal_link'   => '',
        'external_link'   => '',
    ], $atts, 'recipe_generator' );

    return recipe_generator_generate_recipe_post( $atts );
}

/**
 * Register Gutenberg block.
 */
function recipe_generator_register_block() {
    // Block registration will be handled here, typically using register_block_type_from_metadata
    // This requires block.json and related JS/CSS files.
    // We will create these in a later step.
    if ( ! function_exists( 'register_block_type' ) ) {
        return; // Gutenberg is not active.
    }

    // Path to the block.json file, assuming it's in a 'build' directory
    $block_json_path = RECIPE_GENERATOR_PATH . 'build/block.json'; 

    if ( file_exists( $block_json_path ) ) {
        register_block_type( $block_json_path, [
            'render_callback' => 'recipe_generator_render_block',
        ]);
    }
}

/**
 * Render callback for the Gutenberg block.
 *
 * @param array $attributes Block attributes.
 * @return string HTML output for the block.
 */
function recipe_generator_render_block( $attributes ) {
    // Attributes from the block editor will be passed here.
    // These should match the ones defined in block.json and used in edit.js
    $args = [
        'post_title'      => isset( $attributes['postTitle'] ) ? $attributes['postTitle'] : '',
        'focus_keyword'   => isset( $attributes['focusKeyword'] ) ? $attributes['focusKeyword'] : '',
        'related_keywords' => isset( $attributes['relatedKeywords'] ) ? $attributes['relatedKeywords'] : '',
        'internal_link'   => isset( $attributes['internalLink'] ) ? $attributes['internalLink'] : '',
        'external_link'   => isset( $attributes['externalLink'] ) ? $attributes['externalLink'] : '',
    ];
    return recipe_generator_generate_recipe_post( $args );
}

/**
 * Enqueue scripts and styles for the frontend.
 */
function recipe_generator_enqueue_frontend_assets() {
    // The 'wp-block-recipe-generator-block-style' handle is automatically registered
    // by WordPress when register_block_type processes block.json with a 'style' entry.
    // We enqueue it here to ensure styles are applied if the shortcode is used,
    // even if the block itself isn't on the page.
    if ( is_singular() ) {
        global $post;
        if ( $post && has_shortcode( $post->post_content, 'recipe_generator' ) ) {
            wp_enqueue_style( 'wp-block-recipe-generator-block-style' );
        }
    }
}
// Filter the admin footer text for plugin pages
function wp_recipe_genie_override_admin_footer_text($footer_text) {
    $screen = get_current_screen();
    if ( ! $screen ) {
        return $footer_text;
    }

    $plugin_pages_bases = [
        'toplevel_page_recipe-generator-main', // Main page (Generate Recipe)
        'recipe-generator_page_recipe-generator-generated-recipes', // Generated Recipes submenu
        'recipe-generator_page_recipe-generator-settings', // Settings submenu
        'recipe-generator_page_recipe-generator-help' // Help submenu
    ];

    if (in_array($screen->base, $plugin_pages_bases)) {
        return 'Made with <span style="color: #ff0000;">♥</span> by Nid Academy Team';
    }
    return $footer_text;
}
add_filter( 'admin_footer_text', 'wp_recipe_genie_override_admin_footer_text', 11 );

add_action( 'wp_enqueue_scripts', 'recipe_generator_enqueue_frontend_assets' );

/**
 * Renders the admin footer HTML.
 */
function wp_recipe_genie_admin_footer_html() {
    $help_page_url = esc_url( admin_url( 'admin.php?page=recipe-generator-help' ) );
    // Ensure this slug matches your plugin's slug on wordpress.org for the review link
    $review_link = 'https://wordpress.org/support/plugin/recipe-generator/reviews/?filter=5#new-post';
?>
    <div class="wp-recipe-genie-admin-footer" style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; clear: both;">
        <p style="margin-bottom: 10px; font-size: 13px;">
            <?php esc_html_e( 'Made with', 'recipe-generator' ); ?> <span style="color: #ff0000;">♥</span> <?php esc_html_e( 'by Nid Academy Team', 'recipe-generator' ); ?>
        </p>
        <p style="margin-bottom: 15px; font-size: 13px;">
            <a href="<?php echo $help_page_url; ?>"><?php esc_html_e( 'Support', 'recipe-generator' ); ?></a> /
            <a href="<?php echo $help_page_url; ?>"><?php esc_html_e( 'Docs', 'recipe-generator' ); ?></a> /
            <a href="<?php echo $help_page_url; ?>"><?php esc_html_e( 'Help', 'recipe-generator' ); ?></a>
        </p>
        <p class="social-icons" style="margin-bottom: 20px; font-size: 20px;">
            <a href="https://www.facebook.com/nidacademy1" target="_blank" title="<?php esc_attr_e( 'Facebook', 'recipe-generator' ); ?>" style="text-decoration: none; margin: 0 5px;"><span class="dashicons dashicons-facebook-alt"></span></a>
            <a href="https://www.instagram.com/nidacademy_/" target="_blank" title="<?php esc_attr_e( 'Instagram', 'recipe-generator' ); ?>" style="text-decoration: none; margin: 0 5px;"><span class="dashicons dashicons-instagram"></span></a>
            <a href="https://www.linkedin.com/in/mohamednidsaid/" target="_blank" title="<?php esc_attr_e( 'LinkedIn', 'recipe-generator' ); ?>" style="text-decoration: none; margin: 0 5px;"><span class="dashicons dashicons-linkedin"></span></a>
            <a href="https://x.com/Nidacademyar" target="_blank" title="<?php esc_attr_e( 'X', 'recipe-generator' ); ?>" style="text-decoration: none; margin: 0 5px;"><span class="dashicons dashicons-twitter-alt"></span></a>
            <a href="https://www.youtube.com/@Nid.Academy" target="_blank" title="<?php esc_attr_e( 'YouTube', 'recipe-generator' ); ?>" style="text-decoration: none; margin: 0 5px;"><span class="dashicons dashicons-youtube"></span></a>
        </p>
        <p style="font-size: 13px;">
            <?php
            printf(
                wp_kses_post( __( 'Please rate %1$s ★★★★★ on %2$s', 'recipe-generator' ) ),
                '<strong>WP Recipe Genie</strong>',
                '<a href="' . esc_url( $review_link ) . '" target="_blank">' . esc_html__( 'WordPress.org', 'recipe-generator' ) . '</a>'
            );
            ?>
        </p>
    </div>
<?php
}

