<?php
/*
Plugin Name: Eloqua Forms
Plugin URI: https://github.com/boyte/wp-eloqua-forms
Description: Basic plugin that imports forms from Eloqua into a custom post type in Wordpress. 
Version: 1.0
Author: Cody Boyte
Author URI: https://github.com/boyte/
License: GPL
*/

//
// Include eloqua-php-request
// https://github.com/slaterson/eloqua-php-request
//

include_once(__DIR__.'/eloquaRequest.php');

//
// Create the custom Eloqua Form Type
//
add_action( 'init', 'create_form_post_type' );
function create_form_post_type() {
	register_post_type( 'eloqua_form',
    array(
      'labels' => array( 'name' => __( 'Eloqua Forms' ), 'singular_name' => __( 'Eloqua Form' ) ),
      'public' => true, 'has_archive' => false,
      'menu_position' => 12,
      'menu_icon' => 'dashicons-index-card',
      'supports' => array('title','author','editor','thumbnail','custom-fields','revisions'),
    )
  );
}

//
// Get and update forms function
//

function get_and_update_forms_from_eloqua() {
	// replace these values with your own information from an Eloqua user with an API license
	$eloquaRequest = new EloquaRequest('YOUR_ELOQUA_COMPANY', 'API_USERNAME', 'API_USER_PASSWORD', 'https://secure.p03.eloqua.com/API/REST/2.0');

	// Get when the last form was updated to know where to start the sync
	$last_updated_eloqua_form_date = get_option("last_updated_eloqua_form_date");
	if($last_updated_eloqua_form_date == "") $last_updated_eloqua_form_date = 0;

	$response = $eloquaRequest->get("/assets/forms?page=1&count=1000&depth=complete&orderBy=updatedAt&lastUpdatedAt=$last_updated_eloqua_form_date");

	if(empty($response->elements)) {
		echo("<div class='updated fade'><p><strong>Sync complete</strong>. No forms updated since last sync.</p></div>");
		return;
	}

	// Get all forms and associated meta values
	$args = array(
		'post_type' => 'eloqua_form',
		'post_status' => 'publish',
		'nopaging' => true // Show all posts, no pagination
	);
	$existing_posts = new WP_Query( $args );
	$existing_posts_with_meta = new StdClass();

	foreach($existing_posts->posts as $existing_post) {
		$eloqua_form_id = get_post_meta($existing_post->ID, 'eloqua_form_id', true);

		$existing_posts_with_meta->post[$eloqua_form_id] = new StdClass();
		$existing_posts_with_meta->post[$eloqua_form_id]->wordpress_post = $existing_post;
		$existing_posts_with_meta->post[$eloqua_form_id]->eloqua_form_id = $eloqua_form_id;
		$existing_posts_with_meta->post[$eloqua_form_id]->eloqua_form_last_updated_at = get_post_meta($existing_post->ID, 'eloqua_form_last_updated_at', true);
		$existing_posts_with_meta->post[$eloqua_form_id]->eloqua_form_created_at = get_post_meta($existing_post->ID, 'eloqua_form_created_at', true);
	}

	foreach($response->elements as $form) {
		if(isset($existing_posts_with_meta->post) &&
		   array_key_exists($form->id, $existing_posts_with_meta->post) &&
		   $form->currentStatus == "deleted")
		{
			// Form is marked as deleted
			// Delete the post
			$delete_post = wp_delete_post($existing_posts_with_meta->post[$form->id]->wordpress_post->ID);
			if($delete_post != false) {
				echo("<div class='updated fade'><p>Deleted <strong>" . $existing_posts_with_meta->post[$form->id]->wordpress_post->post_title . "</strong>.</p></div>");
			}

		} elseif(isset($existing_posts_with_meta->post) &&
		         array_key_exists($form->id, $existing_posts_with_meta->post))
		{
			// Form exists in WordPress
			// Let's update it
			$post = array(
			  'ID'             => $existing_posts_with_meta->post[$form->id]->wordpress_post->ID,
			  'post_content'   => $form->html,
			  'post_status'    => 'publish',
			  'post_title'     => $form->name,
			  'post_type'      => 'eloqua_form'
			);  
			$new_post_id = wp_insert_post( $post, $wp_error );
			update_post_meta($new_post_id, 'eloqua_form_last_updated_at', $form->updatedAt);
			echo("<div class='updated fade'><p>Updated <strong>{$form->name}</strong>.</p></div>");
			// date('M. j', $form->updatedAt)

		} else {
			if($form->currentStatus != "deleted") {
				// Form does not exist in WordPress
				// Let's create it
				$post = array(
				  'post_content'   => $form->html,
				  'post_status'    => 'publish',
				  'post_title'     => $form->name,
				  'post_type'      => 'eloqua_form'
				);  
				$new_post_id = wp_insert_post( $post, $wp_error );
				update_post_meta($new_post_id, 'eloqua_form_id', $form->id);
				update_post_meta($new_post_id, 'eloqua_form_last_updated_at', $form->updatedAt);
				update_post_meta($new_post_id, 'eloqua_form_created_at', $form->createdAt);
				echo("<div class='updated fade'><p>Created <strong>{$form->name}</strong>.</p></div>");
			}

		}

		// Set this here because
		// if the last result in $response->elements is
		// deleted, it doesn't come with updatedAt
		if($form->currentStatus != "deleted") {
			$new_last_updated_eloqua_form_date = $form->updatedAt + 1; // Add 1 second to not grab the last form again in the next sync
		}

	}

	echo("<div class='updated fade'><p>Sync complete.</p></div>");

	// Store the last form updatedAt to know where to start in next sync
	// This field is available to be edited in Global Custom Fields to manually overwrite
	update_option("last_updated_eloqua_form_date", $new_last_updated_eloqua_form_date);
}

//
// Page to refresh forms
//

add_action('admin_menu', 'add_update_forms_page');

function add_update_forms_page() {
    add_submenu_page('edit.php?post_type=eloqua_form', 'Update Eloqua Forms', 'Update Eloqua Forms', 'manage_options', 'update-forms', 'update_eloqua_forms_page');
}

function update_eloqua_forms_page() {
    ?>
    <div class="wrap">
      <h2>Update Eloqua Forms</h2>

      <?php
      if($_POST['action'] == "update-forms") {
 	    get_and_update_forms_from_eloqua();
      }
      ?>

      <form method="post" action="#">
        <? wp_nonce_field('update-forms') ?>
        <input type="hidden" name="action" value="update-forms" />

        <p>Click the button below to sync forms from Eloqua.</p>
        <p><input type="submit" name="Submit" value="Update forms" class="button-primary" /></p>


      </form>
    </div>
    <?php 
}

//
// show_form shortcode
// Valid attributes:
// post_id (WordPress post ID) OR eloqua_form_id (Eloqua form ID)
// title (Title above the form)
// redirect_to (Page to redirect to after form is submitted. ** NEEDS HTTP:// ** )

add_shortcode('show_form', 'show_form_shortcode');

function show_form_shortcode($attributes) {

  if(is_array($attributes)) {
    extract($attributes);
  }

  // Need this to setup_postdata correctly
  global $post;
  
  // Set global so eloqua_auto_fill_form_variables() can access
  global $form_redirect_to;

  if(isset($post_id)) {
	  // If post_id is set, just display that form
  	  $post = get_post($post_id);

  } elseif(isset($eloqua_form_id)) {
	  // If eloqua_form_id is set, just display that form
	  $args = array(
	    'post_type'   => 'form',
	    'post_status' => 'publish',
	    'meta_query'  => array(
	    	array( 'key' => 'eloqua_form_id',
	    		   'value' => $eloqua_form_id,
	    		   'compare' => '='
	    	     )
	    	)
	   );
	  $post_query = new WP_Query( $args );
	  $post = $post_query->posts[0];

  } else {
  	// If form is specifically turned off (false),
  	// return function to not display anything.
  	if(get_field('form_enabled') == false) {
  		return;
  	}

  	// Check if form is enabled for this post
  	if(get_field('form_enabled') == true) {
		// Get the selected form for this post
		$form_post_object = get_field('form_post_object');
		$form_title =  get_field('form_title');
		$form_redirect_to =  get_field('form_redirect_to');
		$post = $form_post_object;
	}
  }

  if(!$post) {
  	return "<div>Sorry, this resource is currently unavailable.
  	        For support, please send us an email.</div>";
  }

  // Use these as overrides if explicitly set in the shortcode
  if($title != "") {
	  $form_title = $title;
  }
  if($redirect_to != "") {
  	$form_redirect_to = $redirect_to;
  }

  setup_postdata( $post );

  $html_form_id = "form" . get_post_meta(get_the_ID(), 'eloqua_form_id', true); // For JavaScript CSS selectors
  $form_data = get_the_content();
  $form_data = clean_eloqua_form_data($form_data);

  $output = "";
  $output .= "<div class=\"form-wrapper\">";
  if($form_title) {
	  $output .= "<div class=\"form-title\">{$form_title}</div>";
  }
  $output .= $form_data;
  $output .= "<div class=\"clearfix\"></div>";
  $output .= "</div>";
  $output .= eloqua_form_javascript($html_form_id);

  // IMPORTANT:
  // Reset the postdata so the rest of the page works correctly
  wp_reset_postdata(); 

  return $output;

}

//
// Function to clean Eloqua form data
// Remove style and value attributes
//

function clean_eloqua_form_data($html) {
	$clean_html = $html;

	// Remove style attributes
	$clean_html = preg_replace('%style="[^"]+"%i', '', $clean_html);
	//$clean_html = preg_replace('/(<[^>]+) style=".*?"/i', '$1', $clean_html);

	// Remove value attributes
	$clean_html = preg_replace('/<eloqua[^>]+\>/i', '', $clean_html);
	//$clean_html = preg_replace('%value="[^"]+"%i', '', $clean_html);
	//$clean_html = preg_replace('/(<[^>]+) value=".*?"/i', '$1', $clean_html);

	return $clean_html;
}

//
// Function to add Javascript needed
// to the echo'd Eloqua form
//

function eloqua_form_javascript($html_form_id) {
	$auto_fill_form_variables = eloqua_auto_fill_form_variables();

	$output = "";

	$output .= "<script>";
	$output .= "$(document).ready(function() {";

	// If hidden value, add class "hide" to the parent form
	$output .= "$('form#$html_form_id').find('input:hidden').closest('.field-wrapper').addClass('hide');";
	$output .= "\n";

	// Link labels near checkboxes so you can 
	// click on the label to check/uncheck
	$output .= "$.each($('form#$html_form_id input[type=checkbox]'), function() {";
	$output .= "$(this).attr('id', $(this).attr('name'));";
	$output .= "$(this).parent().find('label').attr('for', $(this).attr('id'));";
	$output .= "});";

	// Auto fill form variables
	foreach($auto_fill_form_variables as $form_key => $form_value) {
		if($form_value != "") {
			$output .= "\n";
			$output .= "if($('form#$html_form_id input[name=$form_key]').length > 0) {";
			$output .= "$('form#$html_form_id input[name=$form_key]').val('$form_value');";
			$output .= "}";
		}
	}

	$output .= "});";
	$output .= "</script>";

	return $output;
}

function eloqua_auto_fill_form_variables() {
	global $form_redirect_to;

	// the autofill variables look for html fields and then use jquery to fill them
	// with data point from your server or the URL. The UTMs are set for Google Analytics.
	$auto_fill_variables = array(
		'lastMQLreferrer' => wp_get_referer(),
		'lastMQLtitle' => "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
		'redirectURL' => $form_redirect_to,

		'utm_campaign' => $_GET["utm_campaign"],
		'utm_content' => $_GET["utm_content"],
		'utm_source' => $_GET["utm_source"],
		'utm_medium' => $_GET["utm_medium"],
	);

	return $auto_fill_variables;
}


?>
