<?php
/**
 * Plugin Name: Feedback Manager
 * Description: A plugin to manage feedback.
 * Version: 1.0.0
 * Author: Your Name
 */

// Enqueue CSS and JS files
function feedback_manager_enqueue_scripts() {
    wp_enqueue_style( 'feedback-manager-style', plugins_url( 'css/style.css', __FILE__ ) );
    wp_enqueue_script( 'feedback-manager-script', plugins_url( 'js/script.js', __FILE__ ), array( 'jquery' ), '1.0.0', true );
}
add_action( 'wp_enqueue_scripts', 'feedback_manager_enqueue_scripts' );

// Display feedback form at the bottom of posts and pages
function feedback_manager_display_form() {
    if ( is_singular() ) {
        $form_html = '
        <div class="feedback-form">
            <h3>Leave Feedback</h3>
            <form id="feedback-form" method="post" action="http://localhost:8888/wp/2023/09/04/thank-you/">
                <div class="form-group">
                    <label for="user_name">Name</label>
                    <input type="text" name="user_name" id="user_name" required>
                </div>
                <div class="form-group">
                    <label for="user_email">Email</label>
                    <input type="email" name="user_email" id="user_email" required>
                </div>
                <div class="form-group">
                    <label for="feedback_type">Feedback Type</label>
                    <select name="feedback_type" id="feedback_type" required>
                        <option value="General">General</option>
                        <option value="Bug Report">Bug Report</option>
                        <option value="Feature Request">Feature Request</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="feedback_message">Message</label>
                    <textarea name="feedback_message" id="feedback_message" required></textarea>
                </div>
                <input type="submit" value="Submit Feedback">
            </form>
        </div>';

        echo $form_html;
    }
}
add_action( 'the_content', 'feedback_manager_display_form' );



function feedback_manager_process_form() {
    if ( isset( $_POST['user_name'] ) && isset( $_POST['user_email'] ) && isset( $_POST['feedback_type'] ) && isset( $_POST['feedback_message'] ) ) {
        $user_name = sanitize_text_field( $_POST['user_name'] );
        $user_email = sanitize_email( $_POST['user_email'] );
        $feedback_type = sanitize_text_field( $_POST['feedback_type'] );
        $feedback_message = sanitize_textarea_field( $_POST['feedback_message'] );

        // Validate email
        if ( ! is_email( $user_email ) ) {
            // Handle invalid email error
            wp_die( 'Invalid email address.' );
        }

        // Store feedback in the database or perform other actions
        store_feedback( get_the_ID(), $user_name, $user_email, $feedback_type, $feedback_message );

        // Redirect or display success message
        wp_redirect( get_permalink() . '?feedback=submitted' );
        exit;
    }
}
add_action( 'init', 'feedback_manager_process_form' );




function create_feedback_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'feedback';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        post_id int(11) NOT NULL,
        user_name varchar(255) NOT NULL,
        user_email varchar(255) NOT NULL,
        feedback_type varchar(255) NOT NULL,
        feedback_message text NOT NULL,
        submission_timestamp datetime NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
add_action( 'after_setup_theme', 'create_feedback_table' );




function store_feedback( $post_id, $user_name, $user_email, $feedback_type, $feedback_message ) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'feedback';

    $wpdb->insert(
        $table_name,
        array(
            'post_id' => $post_id,
            'user_name' => $user_name,
            'user_email' => $user_email,
            'feedback_type' => $feedback_type,
            'feedback_message' => $feedback_message,
            'submission_timestamp' => current_time( 'mysql' ),
        )
    );
}















function add_feedback_admin_page() {
    add_menu_page(
        'Feedback Admin Page', // Page title
        'Feedback', // Menu title
        'manage_options', // Capability required to access the page
        'feedback-admin', // Menu slug
        'display_feedback_admin_page', // Callback function to display the page content
        'dashicons-feedback', // Menu icon
        25 // Position in the admin menu
    );
}
add_action('admin_menu', 'add_feedback_admin_page');

// Callback function to display the admin page content



function display_feedback_admin_page() {
    ?>
    <div class="wrap">
        <h1>Feedback Admin Page</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Post/Page</th>
                    <th>Feedback Type</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Query to retrieve feedback data
                $feedback_args = array(
                    'post_type' => 'feedback', // Replace with your custom post type name
                    'posts_per_page' => -1
                );
                $feedback_query = new WP_Query($feedback_args);

                // Loop through the feedback and display in table rows
                if ($feedback_query->have_posts()) :
                    while ($feedback_query->have_posts()) : $feedback_query->the_post();
                        $post_title = get_the_title();
                        $feedback_type = get_post_meta(get_the_ID(), 'feedback_type', true);
                        $timestamp = get_the_time('Y-m-d H:i:s');
                        ?>
                        <tr>
                            <td><?php echo $post_title; ?></td>
                            <td><?php echo $feedback_type; ?></td>
                            <td><?php echo $timestamp; ?></td>
                        </tr>
                        <?php
                        // Send email notification to the site administrator
                        $to = get_option('admin_email');
                        $subject = 'New Feedback Submission';
                        $message = "A new feedback has been submitted:\n\n";
                        $message .= "Post/Page: $post_title\n";
                        $message .= "Feedback Type: $feedback_type\n";
                        $message .= "User's Email: " . get_post_meta(get_the_ID(), 'user_email', true) . "\n";
                        wp_mail($to, $subject, $message);
                    endwhile;
                    wp_reset_postdata();
                else :
                    ?>
                    <tr>
                        <td colspan="3">No feedback found.</td>
                    </tr>
                    <?php
                endif;
                ?>
            </tbody>
        </table>
    </div>
    <?php
}




function send_feedback_notification( $post_id, $user_email, $feedback_type ) {
    $post_title = get_the_title( $post_id );

    $to = get_option( 'admin_email' );
    $subject = 'New Feedback Submission';
    $message = "A new feedback has been submitted:\n\n";
    $message .= "Post/Page: $post_title\n";
    $message .= "Feedback Type: $feedback_type\n";
    $message .= "User Email: $user_email";

    wp_mail( $to, $subject, $message );
}



function submit_feedback( $post_id, $user_name, $user_email, $feedback_type, $feedback_message ) {
    // ...
    $wpdb->insert(
        $table_name,
        array(
            // ...
        )
    );

    send_feedback_notification( $post_id, $user_email, $feedback_type );
}
