<?php
/*
Plugin Name: Expression Engine Blog Importer
Description: Imports blog posts from other websites
Version: 1.0
Author: Your Name
*/

// Add the options page
add_action('admin_menu', 'ee_blog_importer_menu');
function ee_blog_importer_menu() {
  add_management_page('Expression Engine Blog Importer', 'Expression Engine Blog Importer', 'manage_options', 'ee-blog-importer', 'ee_blog_importer_options');
}

// Display the options page
function ee_blog_importer_options() {
  if (!current_user_can('manage_options'))  {
    wp_die( __('You do not have sufficient permissions to access this page.') );
  }
  echo '<div class="wrap">';
  echo '<h1>Expression Engine Blog Importer Options</h1>';
  echo '<form method="post" action="options.php">';

  // Output security nonce and registered settings fields
  wp_nonce_field('update-options');
  settings_fields('ee_blog_importer_settings');

  // Output the form fields
  do_settings_sections('ee-blog-importer');

  // Output the submit button
  submit_button();

  echo '</form>';
  echo '</div>';

  // Check if the user has the necessary permissions
  if (!current_user_can('manage_options')) {
    return;
  }

  // Check if the form has been submitted
  if (isset($_POST['run_import'])) {
    // Run the import_blog_posts() function
    import_blog_posts();
  }

  // Display the Run Import form 
  ?>
  <hr>
  <div class="wrap">
    <form method="post">
      <input type="submit" name="run_import" value="Run Import" class="button button-primary">
    </form>
  </div>
  <?php
}

// Register the plugin settings
add_action('admin_init', 'ee_blog_importer_settings');
function ee_blog_importer_settings() {
  register_setting('ee_blog_importer_settings', 'ee_blog_importer_settings');
  add_settings_section('ee_blog_importer_section', '', '', 'ee-blog-importer');
  add_settings_field('ee_blog_importer_host', 'Host', 'ee_blog_importer_host_callback', 'ee-blog-importer', 'ee_blog_importer_section');
  add_settings_field('ee_blog_importer_database', 'Database', 'ee_blog_importer_database_callback', 'ee-blog-importer', 'ee_blog_importer_section');
  add_settings_field('ee_blog_importer_username', 'Username', 'ee_blog_importer_username_callback', 'ee-blog-importer', 'ee_blog_importer_section');
  add_settings_field('ee_blog_importer_password', 'Password', 'ee_blog_importer_password_callback', 'ee-blog-importer', 'ee_blog_importer_section');
}

// Output the form fields
function ee_blog_importer_host_callback() {
  $options = get_option('ee_blog_importer_settings');
  if(empty($options['ee_blog_importer_host'])){
    $options['ee_blog_importer_host'] = '';
  }
  echo '<input type="text" name="ee_blog_importer_settings[ee_blog_importer_host]" value="' . $options['ee_blog_importer_host'] . '" />';
}
function ee_blog_importer_database_callback() {
  $options = get_option('ee_blog_importer_settings');
  if(empty($options['ee_blog_importer_database'])){
    $options['ee_blog_importer_database'] = '';
  }
  echo '<input type="text" name="ee_blog_importer_settings[ee_blog_importer_database]" value="' . $options['ee_blog_importer_database'] . '" />';
}
function ee_blog_importer_username_callback() {
  $options = get_option('ee_blog_importer_settings');
  if(empty($options['ee_blog_importer_username'])){
    $options['ee_blog_importer_username'] = '';
  }
  echo '<input type="text" name="ee_blog_importer_settings[ee_blog_importer_username]" value="' . $options['ee_blog_importer_username'] . '" />';
}
function ee_blog_importer_password_callback() {
  $options = get_option('ee_blog_importer_settings');
  if(empty($options['ee_blog_importer_password'])){
    $options['ee_blog_importer_password'] = '';
  }
  echo '<input type="password" name="ee_blog_importer_settings[ee_blog_importer_password]" value="' . $options['ee_blog_importer_password'] . '" />';
}

// Import Expression Engine blog posts
function import_blog_posts() {
  // Connect to the database using the credentials from the options page
  $host = get_option('ee_blog_importer_host');
  $database = get_option('ee_blog_importer_database');
  $username = get_option('ee_blog_importer_username');
  $password = get_option('ee_blog_importer_password');
  $conn = mysqli_connect($host, $username, $password, $database);

  // Select all rows from the exp_channel_titles and exp_channel_fields tables, joined on the entry_id column
  $result = mysqli_query($conn, "SELECT t.entry_id, t.open, t.title, t.url_title, t.sticky, t.entry_date, f.field_id_137 AS blog_content, f.field_id_213 AS author_alias
  FROM exp_channel_titles t
  JOIN exp_channel_fields f
  ON t.entry_id = f.entry_id");
  while ($row = mysqli_fetch_assoc($result)) {
    // Save the values of the relevant columns into variables
    $entry_id = $row['entry_id'];
    $open = $row['open'];
    $title = $row['title'];
    $url_title = $row['url_title'];
    $sticky = $row['sticky'];
    $entry_date = $row['entry_date'];
    $blog_content = $row['blog_content'];
    $author_alias = $row['author_alias'];

    // Check if the blog_content variable is empty
    if (!empty($blog_content)) {
      // Create the WordPress post
      $post = array(
        'post_title' => $title,
        'post_name' => $url_title,
        'post_status' => 'publish',
        'post_type' => 'post',
        'post_author' => 1,
        'post_date' => $entry_date,
        'post_content' => $blog_content,
      );
      $post_id = wp_insert_post($post);

      // Set the post as sticky if the sticky column is set to "y"
      if ($sticky == "y") {
        stick_post($post_id);
      }
      // Search for rows in the exp_category_posts table that include the entry_id value
      $result2 = mysqli_query($conn, "SELECT * FROM exp_category_posts WHERE entry_id = '$entry_id'");
      while ($row2 = mysqli_fetch_assoc($result2)) {
        // Save the values of the cat_id column into a variable
        $cat_id = $row2['cat_id'];

        // Search for the cat_name and cat_url_title values that correspond to the cat_id value
        $result3 = mysqli_query($conn, "SELECT * FROM exp_categories WHERE cat_id = '$cat_id'");
        $row3 = mysqli_fetch_assoc($result3);
        $cat_name = $row3['cat_name'];
        $cat_url_title = $row3['cat_url_title'];

        // Check if the category has already been created
        $category_exists = term_exists($cat_name, 'category');
        if ($category_exists == 0 || $category_exists == null) {
          // Create the category if it doesn't exist
          wp_create_category($cat_name, 0, array('slug' => $cat_url_title));
        }

        // Add the category to the post
        wp_set_post_categories($post_id, $cat_id, true);
      }

      // Search for rows in the exp_tag_entries table that include the entry_id value
      $result4 = mysqli_query($conn, "SELECT * FROM exp_tag_entries WHERE entry_id = '$entry_id'");
      while ($row4 = mysqli_fetch_assoc($result4)) {
        // Save the values of the tag_id column into a variable
        $tag_id = $row4['tag_id'];
        // Search for the tag_name value that corresponds to the tag_id value
        $result5 = mysqli_query($conn, "SELECT * FROM exp_tag_tags WHERE tag_id = '$tag_id'");
        $row5 = mysqli_fetch_assoc($result5);
        $tag_name = $row5['tag_name'];

        // Check if the tag has already been created
        $tag_exists = term_exists($tag_name, 'post_tag');
        if ($tag_exists == 0 || $tag_exists == null) {
          // Create the tag if it doesn't exist
          wp_create_tag($tag_name);
        }

        // Add the tag to the post
        wp_set_post_tags($post_id, $tag_name, true);
      }
    }
  }
}

// Create AJAX load button
function ee_blog_importer_enqueue_scripts() {
  // Register and enqueue the JavaScript file
  wp_enqueue_script( 'ee-blog-importer', plugin_dir_url( __FILE__ ) . 'js/ee-blog-importer.js', array( 'jquery' ), '1.0.0', true );
}
add_action( 'wp_enqueue_scripts', 'ee_blog_importer_enqueue_scripts' );
