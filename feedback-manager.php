<?php
/*
Plugin Name: Feedback Manager
Description: Plugin for managing user feedback
Version: 1.0
Author: Your Name
*/

// Plugin activation hook
register_activation_hook( __FILE__, 'feedback_manager_activate' );

function feedback_manager_activate() {
    // Add any activation tasks here
    feedback_manager_create_table();
}

// Plugin deactivation hook
register_deactivation_hook( __FILE__, 'feedback_manager_deactivate' );

function feedback_manager_deactivate() {
    // Add any deactivation tasks here
}

// Add feedback form at the bottom of posts and pages
add_filter( 'the_content', 'feedback_manager_display_form' );






function feedback_manager_display_form( $content ) {
    if ( is_singular( array( 'post', 'page' ) ) ) {
        ob_start();

        global $post;
        ?>
        <form id="feedback-form" method="post" action="http://localhost:8888/wp/2023/09/04/thank-you/">
            <label for="name">Name</label>
            <input type="text" name="name" id="name" required>
            <input type="hidden" name="post_id" value="<?php echo esc_attr($post->ID); ?>">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" required>

            <label for="feedback-type">Feedback Type</label>
            <select name="feedback-type" id="feedback-type" required>
                <option value="">Select feedback type</option>
                <option value="Bug Report">Bug Report</option>
                <option value="Feature Request">Feature Request</option>
                <option value="General Feedback">General Feedback</option>
            </select>

            <label for="message">Message</label>
            <textarea name="message" id="message" required></textarea>

            <input type="submit" value="Submit" name="submit_form">
        </form>
        <?php
        $form = ob_get_clean();
        $content .= $form;
    }
    return $content;
}

// Save submitted feedback to database
add_action( 'wp_loaded', 'feedback_manager_save_feedback' );

function feedback_manager_save_feedback() {
    if ( isset( $_POST['name'] ) && isset( $_POST['email'] ) && isset( $_POST['feedback-type'] ) && isset( $_POST['message'] ) ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'feedback';

        $data = array(
            'post_id' => absint( $_POST['post_id'] ),
            'name' => sanitize_text_field( $_POST['name'] ),
            'email' => sanitize_email( $_POST['email'] ),
            'feedback_type' => sanitize_text_field( $_POST['feedback-type'] ),
            'message' => sanitize_textarea_field( $_POST['message'] ),
            'submission_timestamp' => current_time( 'mysql' ),
        );

        $wpdb->insert( $table_name, $data );
    }
}

// Create custom database table on plugin activation
function feedback_manager_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'feedback';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        name varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        feedback_type varchar(255) NOT NULL,
        message text NOT NULL,
        submission_timestamp datetime NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

// Create admin page for managing feedback
add_action( 'admin_menu', 'feedback_manager_admin_menu' );

function feedback_manager_admin_menu() {
    add_menu_page(
        'Feedback Manager',
        'Feedback Manager',
        'manage_options',
        'feedback-manager',
        'feedback_manager_admin_page',
        'dashicons-feedback',
        20
    );
}

function feedback_manager_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'feedback';
    $per_page = 10;
    $current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
    $total_items = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
    $total_pages = ceil( $total_items / $per_page );

    $feedback = $wpdb->get_results(
        "SELECT * FROM $table_name ORDER BY submission_timestamp DESC LIMIT " . ( $current_page - 1 ) * $per_page . ", $per_page"
    );
    ?>
    <div class="wrap">
        <h1>Feedback Manager</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col">Post/Page</th>
                    <th scope="col">Feedback Type</th>
                    <th scope="col">Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $feedback as $item ) : ?>
                    <tr>
                        <td><?php echo get_the_title( $item->post_id ); ?></td>
                        <td><?php echo $item->feedback_type; ?></td>
                        <td><?php echo $item->submission_timestamp; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        $page_links = paginate_links(
            array(
                'base' => add_query_arg( 'paged', '%#%' ),
                'format' => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total' => $total_pages,
                'current' => $current_page,
            )
        );

        if ( $page_links ) {
            echo '<div class="tablenav"><div class="tablenav-pages">' . $page_links . '</div></div>';
        }
        ?>
    </div>
    <?php
}




function form_capture()
{
    if(array_key_exists('submit_form',$_POST))
    {
        $to = get_option( 'sandrakv@mozilor.com' );
        $post_title = get_the_title( $post_id );
        $subject = 'New Feedback Submission';
        $body = '';

       $body .= 'feedback_type:'.$_POST('feedback-type').'<br /> ';
       $body .= 'email:'.$_POST('email').'<br /> ';
        
        wp_mail($to,$subject,$body);

    }
}
add_action('wp_head','form_capture');