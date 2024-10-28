<?php
/*
Plugin Name: aveJana Reviews
Description: aveJana helps woocommerce stores generate tons of reviews and Q&A and use them to drive qualified traffic, increase conversion rate and boost sales. aveJana integrates with woocommerce websites and appears as a Review tab at the bottom of the Product Page. aveJana is integrated with social platforms such as Facebook and Twitter which help ensure a wider reach for the best reviews of Woocommerce stores.
Version: 2.0.1
Author: aveJana
Author URI: https://www.avejana.com
Text Domain: aveJana
*/

define( 'AVEJANA_PLUGIN_DIR' , __FILE__ );
$plugin = plugin_basename( __FILE__ );

if( !function_exists( 'add_action') ) {
	echo 'Not allowed!';
	exit;
}

register_activation_hook( __FILE__, 'wc_aveJana_activation' );
register_deactivation_hook( __FILE__, 'wc_aveJana_deactivate' );
add_filter( 'woocommerce_product_tabs', 'wc_aveJana_set_new_tabs', 98 );
add_action( 'admin_menu', 'wc_aveJana_admin_menu' );
add_action( 'admin_init', 'wc_aveJana_admin_init' );

add_action( 'admin_post_wc_aveJana_save_options', 'wc_aveJana_save_options' );
add_action( 'admin_post_wc_aveJana_upload_historical_sales', 'wc_aveJana_upload_historical_sales' );

add_filter( 'cron_schedules','wc_aveJana_cron_schedules' );
add_action( 'wc_aveJana_upload_products_hook', 'wc_aveJana_upload_products' );
add_action( 'wc_aveJana_upload_sales_hook', 'wc_aveJana_upload_sales' );

add_action( 'wp_enqueue_scripts', 'wc_aveJana_enqueue', 9999 );

add_action( 'wp_ajax_wc_aveJana_submit_review', 'wc_aveJana_submit_review' );
add_action( 'wp_ajax_nopriv_wc_aveJana_submit_review', 'wc_aveJana_submit_review' );
add_action( 'wp_ajax_wc_aveJana_submit_question', 'wc_aveJana_submit_question' );
add_action( 'wp_ajax_nopriv_wc_aveJana_submit_question', 'wc_aveJana_submit_question' );

add_action( 'woocommerce_after_shop_loop_item_title', 'wc_aveJana_template_loop_rating', 5 );
add_action( 'woocommerce_single_product_summary', 'wc_aveJana_product_summary');

add_action( 'woocommerce_order_status_processing', 'wc_aveJana_order_processing' );
add_action( 'woocommerce_order_status_completed', 'wc_aveJana_order_processing' );
add_action( 'woocommerce_order_status_cancelled', 'wc_aveJana_order_cancelled');

add_filter( "plugin_action_links_$plugin", 'wc_aveJana_plugin_add_settings_link' );

function wc_aveJana_plugin_add_settings_link( $links ) {
	$settings_link = '<a href="admin.php?page=avejana-settings">' . __( 'Settings' ) . '</a>'; 
	array_push( $links, $settings_link );
	return $links;
}

function wc_aveJana_order_cancelled( $order_id ) {
	$aveJana_settings = get_option( 'aveJana_settings' );
	if( $aveJana_settings['company_url'] != '' ) {
		global $wpdb;
		$sales_delete_url = $aveJana_settings['company_url'] . '/api/sales';
		$args = array(
			'headers' => array(
				'user_id' => $aveJana_settings['company_id'],
				'REST-AJEVANA-KEY' => $aveJana_settings['api_key']
			),
			'method' => 'DELETE',
			'body' => array(
				'OrderID' => $order_id,
			)
		);
		$response = wp_remote_request( $sales_delete_url, $args );
		$body = json_decode( $response['body'] );
		if( $body->status === 'success' ) {
			$wpdb->query("update $wpdb->posts set is_uploaded_to_aveJana = 0 where ID = '" . $order_id ."' ");
		}
	}
}

function wc_aveJana_order_processing( $order_id ) {
	$aveJana_settings = get_option( 'aveJana_settings' );
	if( $aveJana_settings['company_url'] != '' ) {
		global $woocommerce;
		global $wpdb;
		$sales_url = $aveJana_settings['company_url'] . '/api/sales';
		$order = new WC_Order($order_id);
		$order_date = date('Y-m-d');
		$name =  sanitize_text_field( get_post_meta($order_id, '_billing_first_name', true) );
		$email = sanitize_email( get_post_meta($order_id, '_billing_email', true) );
		$args = array(
			'headers' => array(
				'user_id' => $aveJana_settings['company_id'],
				'REST-AJEVANA-KEY' => $aveJana_settings['api_key']
			),
			'method' => 'PUT',
			'body' => array(
				'OrderID' => $order_id,
				'OrderDate' => $order_date,
				'CustomerName' => $name,
				'CustomerEmail' => $email,
				'ProductID' => '',
				'Price' => 0,
				'Quantity' => 0
			)
		);
		$items = $order->get_items();
		foreach( $items as $item ){
			$args['body']['ProductID'] = $item['product_id'];
			$total = str_replace(",", "", $item['line_total']);
			$args['body']['Price'] = number_format($total, 2, ".", "");
			$args['body']['Quantity'] = $item['qty'];
			$response = wp_remote_request( $sales_url, $args );
			$body = json_decode( $response['body'] );
			if( $body->status === 'success' ) {
				$wpdb->query("update $wpdb->posts set is_uploaded_to_aveJana = 1 where ID = '" . $args['body']['OrderID'] ."' ");
			}
		}
	}
	return true;
}

function wc_aveJana_product_summary() {
	$aveJana_settings = get_option( 'aveJana_settings' );
	if( $aveJana_settings['company_url'] != '' ) {
		$aveJana_meta_data = get_post_meta( get_the_ID(), '_aveJana_reviews', true );

		$pageview_url = $aveJana_settings['company_url'] . '/api/pageviews';
		$args = array(
			'headers' => array(
				'user_id' => $aveJana_settings['company_id'],
				'REST-AJEVANA-KEY' => $aveJana_settings['api_key']
			),
			'method' => 'PUT',
			'body' => array(
				'CompanyID' => $aveJana_settings['company_id'],
				'ProductID' => get_the_ID(),
				'IP' => wc_aveJana_get_client_ip()
			)
		);
		$response = wp_remote_request( $pageview_url, $args );
		if( $aveJana_meta_data != null ) {
			if( $aveJana_meta_data['reviews_count'] == '' || $aveJana_meta_data['reviews_count'] == 0 ) {
				echo "<a href='' onclick='return open_avejana_review_tab()' style='font-size: 14px; font-family: Helvetica,Raleway,Verdana,Arial,sans-serif;'>Be the First to Review</a> | <a href='' onclick='return open_avejana_question_tab()' id='aveJana_questions_tab_link' style='font-size: 14px; font-family: Helvetica, Raleway,Verdana,Arial,sans-serif;'>Ask a Question</a>";
			} elseif ( $aveJana_meta_data['reviews_count'] > 0 ) {
				echo "
					<span>
						<div class='aveJana-summary-rating' style='float: left;'></div>
						<div style='float: right; font-size: 14px; font-family: Helvetica,Raleway,Verdana,Arial,sans-serif;'>
							<a href='' onclick='return open_avejana_review_tab()'>" . $aveJana_meta_data['reviews_count'] . " Review(s)</a> | <a href='' id='aveJana_questions_tab_link' onclick='return open_avejana_question_tab()' style='font-size: 14px; font-family: Helvetica,Raleway,Verdana,Arial,sans-serif;'>Ask a Question</a>
						</div>
					</span><div style='clear: left;'></div>";
			}
		} else {
			echo "<a href='' onclick='return open_avejana_review_tab()' style='font-size: 14px; font-family: Helvetica,Raleway,Verdana,Arial,sans-serif;'>Be the First to Review</a> | <a href='' onclick='return open_avejana_question_tab()' id='aveJana_questions_tab_link' style='font-size: 14px; font-family: Helvetica,Raleway,Verdana,Arial,sans-serif;'>Ask a Question</a>";
		}
	}
}

function wc_aveJana_template_loop_rating() {
	$aveJana_settings = get_option( 'aveJana_settings' );
	if( $aveJana_settings['company_url'] != '' ) {
		$aveJana_meta_data = get_post_meta( get_the_ID(), '_aveJana_reviews', true );
		if( $aveJana_meta_data != null ) {
			if ( $aveJana_meta_data['reviews_count'] > 0 ) {
				echo "<div class='aveJana_category_rating' data-value='" . esc_attr( $aveJana_meta_data['average_rating'] ) . "' style='text-align: center'></div>
				<div style='font-size: 14px; text-align: center; font-family: Helvetica,Raleway,Verdana,Arial,sans-serif;'>" . esc_attr( $aveJana_meta_data['reviews_count'] ) . " Review(s)</div>
				<div style='clear: left;'></div>";
			}
		}
	}
}

function wc_aveJana_get_client_ip() {
	$ipaddress = '';
	if (getenv('HTTP_CLIENT_IP'))
		$ipaddress = getenv('HTTP_CLIENT_IP');
	else if(getenv('HTTP_X_FORWARDED_FOR'))
		$ipaddress = getenv('HTTP_X_FORWARDED_FOR');
	else if(getenv('HTTP_X_FORWARDED'))
		$ipaddress = getenv('HTTP_X_FORWARDED');
	else if(getenv('HTTP_FORWARDED_FOR'))
		$ipaddress = getenv('HTTP_FORWARDED_FOR');
	else if(getenv('HTTP_FORWARDED'))
		$ipaddress = getenv('HTTP_FORWARDED');
	else if(getenv('REMOTE_ADDR'))
		$ipaddress = getenv('REMOTE_ADDR');
	else
		$ipaddress = 'UNKNOWN';
	return $ipaddress;
}

function wc_aveJana_submit_review() {
	$aveJana_settings = get_option( 'aveJana_settings' );
	if( $aveJana_settings['company_url'] != '' ) {
		$ratings = intval( sanitize_text_field( $_POST['review_rating'] ) );
		if( !$ratings || ($ratings < 0 && $ratings > 5) ) {
			echo "Invalid ratings value";
			wp_die();
		}
		$args = array(
				'headers' => array(
					'user_id' => $aveJana_settings['company_id'],
					'REST-AJEVANA-KEY' => $aveJana_settings['api_key']
				),
				'method' => 'PUT',
				'body' => array(
					'FromCompany' => sanitize_text_field( $aveJana_settings['company_id'] ),
					'ProductID' => sanitize_text_field( $_POST['product_id'] ),
					'IsPrivate' => 3,
					'UserName' => sanitize_user( $_POST['user_name'] ),
					'UserEmail' => sanitize_email( $_POST['user_email'] ),
					'Title' => sanitize_text_field( $_POST['review_title'] ),
					'Ratings' => $ratings,
					'Description' => str_replace( PHP_EOL, "<br>", sanitize_text_field( $_POST['review'] ) )
				)
		);
		$response = wp_remote_request( $aveJana_settings['basic_url'] . '/api/review', $args );
		$body = json_decode( $response['body'] );
		if( $body->status == 'success' ) {
			echo 'Review submitted successfully for moderation.';
		} else {
			echo $body->message;
		}
		wp_die();
	} else {
		echo "Please connect to the aveJana's dashboard to submit revuews";
		wp_die();
	}
	wp_die();
}

function wc_aveJana_submit_question() {
	$aveJana_settings = get_option( 'aveJana_settings' );
	if( $aveJana_settings['company_url'] != '' ) {
		$args = array(
				'headers' => array(
					'user_id' => $aveJana_settings['company_id'],
					'REST-AJEVANA-KEY' => $aveJana_settings['api_key']
				),
				'method' => 'PUT',
				'body' => array(
					'FromCompany' => sanitize_text_field( $aveJana_settings['company_id'] ),
					'ProductID' => sanitize_text_field( $_POST['product_id'] ),
					'IsPrivate' => 3,
					'UserName' => sanitize_user( $_POST['user_name'] ),
					'UserEmail' => sanitize_email( $_POST['user_email'] ),
					'Question' => str_replace( PHP_EOL, "<br>", sanitize_text_field( $_POST['question'] ) )
				)
		);
		$response = wp_remote_request( $aveJana_settings['basic_url'] . '/api/question', $args );
		$body = json_decode( $response['body'] );
		if( $body->status == 'success' ) {
			echo 'Question submitted successfully for moderation.';
		} else {
			echo $body->message;
		}
		wp_die();
	} else {
		echo "Please connect to the aveJana's dashboard to submit your question";
		wp_die();
	}
	wp_die();
}

function wc_aveJana_enqueue() {
	$aveJana_settings = get_option( 'aveJana_settings' );
	if( get_post_type() !== 'product' ) {
		return;
	}
	wp_register_style( 'aveJana_style', plugins_url( '/assets/css/avejana.css', __FILE__ ) );
	wp_enqueue_style( 'aveJana_style' );
	if($aveJana_settings['load_bootstrap'] === 'Yes') {
		wp_register_script( 'aveJana_bootstrap', plugins_url( '/assets/js/bootstrap.min.js', __FILE__ ), array('jquery'), false, true );
	}
	wp_register_script( 'aveJana_star_rating', plugins_url( '/assets/js/jquery.star-rating-svg.js', __FILE__ ), array('jquery'), false, true );
	wp_register_script( 'aveJana_rating', plugins_url( '/assets/js/aveJana.js', __FILE__ ), array('aveJana_star_rating'), false, true );
	if ( !wp_script_is( 'jquery', 'enqueued' )) {
		wp_enqueue_script( 'jquery' );
	}

	wp_localize_script( 'aveJana_rating', 'aveJana_review', array(
		"ajax_url" => admin_url( "admin-ajax.php" )
	));
	if($aveJana_settings['load_bootstrap'] === 'Yes') {
		wp_enqueue_script( 'aveJana_bootstrap' );
	}
	wp_enqueue_script( 'aveJana_star_rating' );
	wp_enqueue_script( 'aveJana_rating' );
}


function wc_aveJana_set_new_tabs( $tabs ) {
	unset( $tabs['reviews'] );
	$tabs['aveJana_review_widget'] = array(
		'title' => __( 'Reviews', 'aveJana' ),
		'priority' => 90,
		'callback' => 'wc_aveJana_show_review_widget'
	);
	$tabs['aveJana_qna_widget'] = array(
		'title' => __( 'Questions & Answers', 'aveJana' ),
		'priority' => 100,
		'callback' => 'wc_aveJana_show_qna_widget'
	);
	return $tabs;	
}

function wc_aveJana_show_review_widget() {
	$aveJana_settings = get_option( 'aveJana_settings' );
	if( $aveJana_settings['company_url'] != '' ) {
		$args = array(
			'headers' => array(
				'user_id' => $aveJana_settings['company_id'],
				'REST-AJEVANA-KEY' => $aveJana_settings['api_key']
			)
		);
		$review_response = wp_remote_get( $aveJana_settings['basic_url'] . '/api/reviewreply?CompanyID=' . $aveJana_settings['company_id'] . '&ProductID=' . get_the_ID(), $args );
		$reviews_count = 0;
		$avg_rating = 0;
		if(!is_a($response, 'WP_Error')) {
			$review_body = json_decode( $review_response['body'] ) ;
			if($review_body->status == 'success') {
				$reviews_count = count($review_body->message);
				foreach ($review_body->message as $review) {
					$avg_rating = $avg_rating + $review->Ratings;
				}
				$avg_rating = round($avg_rating / count($review_body->message), 0);
			}
			$aveJana_meta_data = array(
				'reviews_count' => $reviews_count,
				'average_rating' => $avg_rating
			);
			update_post_meta( get_the_ID(), '_aveJana_reviews', $aveJana_meta_data );
		}
		$is_snippets_enabled = $aveJana_settings['show_snippets'];
		if(substr($aveJana_settings['logo_position'], 0, 3) == 'Top') {
			if( $aveJana_settings['show_logo'] === 'Yes' ) { ?>
				<div class="aveJana_row" style="margin-bottom: 25px">
				<?php if( $aveJana_settings['logo_position'] === 'Top-Left' ) { ?>
					<span style="font-size: 12px; margin-right: 5px">Powered by </span><a href="https://www.avejana.com"><img src="<?php echo esc_url(plugins_url( '/assets/images/avejana-new-logo.png', __FILE__ )) ?>" style="width: 50px; height: 20px; margin-top: -5px;"></a>
				<?php } elseif ( $aveJana_settings['logo_position'] === 'Top-Center' ) { ?>
					<span class="aveJana-image-span-width" style="margin: 0 auto; display: block"><span style="font-size: 12px; margin-right: 5px"><a href="https://www.avejana.com">Powered by </span><a href="https://www.avejana.com"><img src="<?php echo esc_url(plugins_url( '/assets/images/avejana-new-logo.png', __FILE__ )) ?>" style="width: 50px; height: 20px; margin-top: -5px;"></a></span>
				<?php } elseif ( $aveJana_settings['logo_position'] === 'Top-Right' ) { ?>
					<span style="float: right;"><a href="https://www.avejana.com"><span style="font-size: 12px; margin-right: 5px">Powered by </span><a href="https://www.avejana.com"><img src="<?php echo esc_url(plugins_url( '/assets/images/avejana-new-logo.png', __FILE__ )) ?>" style="width: 50px; height: 20px; margin-top: -5px;"></a></span>
				<?php } ?>
				</div>
			<?php }
		} ?>
		<div class="avejana-clear">&nbsp;</div>
		<div style="display: none" id="aveJana_loader_image_div" data-value="<?php echo $avg_rating ?>"><?php echo plugins_url( '/assets/images/ajax-loader.gif', __FILE__ ) ?></div>
		<div style="padding-top: 2%; margin-bottom: 65px">
			<div style="float: left">
				<button id="aveJana_write_review_btn" class="aveJana-button"><?php _e( 'Write Review', 'aveJana' ) ?></button>
			</div>
			<?php if( $reviews_count > 0 ) { ?>
				<div style="float: right; margin-top: 15px;">
					<span style="float: left; line-height: 27px; margin-right: 10px; font-weight: bold; font-size: 14px; font-family: Helvetica;"><?php isset($reviews_count) ? _e( "$reviews_count Review(s)", 'aveJana' ) : '' ?></span>
					<div class="aveJana-overall-rating" style="float: right;"></div>
				</div>
			<?php } ?>
		</div>
		<div id="aveJana_write_review_box" style="display: none; margin-top: -65px">
			<form class="aveJana-review-form" id="aveJana_review_form" >
				<?php wp_nonce_field( 'wc_aveJana_review_verify' ) ?>
				<div>
					<label class="aveJana-label"><?php _e( 'Rating', 'aveJana' ) ?></label>
					<div>
						<input type="hidden" name="aveJana_review_rating" id="aveJana_review_rating" data-productid="<?php echo get_the_ID() ?>">
						<div class="aveJana-write-rating" id="aveJana_write_review_div"></div>
					</div>
				</div>
				<div>
					<label class="aveJana-label"><?php _e( 'Review Title', 'aveJana' ) ?></label>
					<div>
						<input id="aveJana_review_title" name="aveJana_review_title" type="text" class="aveJana-input-text-email" maxlength="255" required>
					</div>
				</div>
				<?php if (!is_user_logged_in()) { ?>
					<div>
						<label class="aveJana-label"><?php _e( 'Name', 'aveJana' ) ?></label>
						<div>
							<input id="aveJana_username" name="aveJana_username" type="text" class="aveJana-input-text-email" maxlength="255" required>
						</div>
					</div>
					<div>
						<label class="aveJana-label"><?php _e( 'Email', 'aveJana' ) ?></label>
						<div>
							<input id="aveJana_user_email" name="aveJana_user_email" type="email" class="aveJana-input-text-email" maxlength="255" required>
						</div>
					</div>
				<?php } else { ?>
					<div>
						<input type="hidden" name="aveJana_username" id="aveJana_username" value="<?php echo wp_get_current_user()->display_name ?>">
						<input type="hidden" name="aveJana_user_email" id="aveJana_user_email" value="<?php echo wp_get_current_user()->user_email ?>">
					</div>
				<?php } ?>
				<div>
					<label class="aveJana-label"><?php _e( 'Review', 'aveJana' ) ?></label>
					<div>
						<textarea id="aveJana_review_text" name="aveJana_review_text" spellcheck="true" rows="3" cols="38" class="aveJana-textarea" required=""></textarea>
					</div>
				</div>
				<div>
					<div>
						<button id="aveJana_submit_review" class="aveJana-button"><?php _e( 'Submit', 'aveJana' ) ?></button>
					</div>
				</div>
				<div>
					<label></label>
					<div id="aveJana_message" style="display: none; color: red; font-weight: bold">
						<img src="<?php echo plugins_url( '/assets/images/ajax-loader.gif', __FILE__ ) ?>">
					</div>
				</div>
			</form>
		</div>

		<?php if(isset($review_body) && $review_body->status == 'success') { 
			foreach ($review_body->message as $review) {
				if($is_snippets_enabled === 'Yes') { ?>
					<div itemscope itemtype="https://schema.org/Product" style="display: none">
						<span itemprop="name"><?php echo get_the_title() ?></span>
						<div itemprop="review" itemscope itemtype="https://schema.org/Review">
							<span itemprop="name"><?php echo esc_html(stripslashes_deep( $review->Title )) ?></span> -
							<span itemprop="author"><?php echo esc_html( $review->UserName ) ?></span>
							<meta itemprop="datePublished" content="<?php echo esc_attr( $review->ReviewDate ) ?>">
							<div itemprop="reviewRating" itemscope itemtype="https://schema.org/Rating">
								<meta itemprop="worstRating" content = "1">
								<span itemprop="ratingValue"><?php echo esc_html($review->Ratings) ?></span>/
								<span itemprop="bestRating">5</span>stars
							</div>
							<span itemprop="description"><?php echo esc_html(stripslashes_deep( $review->Description )) ?></span>
						</div>
					</div>
				<?php } ?>
				<div class="avejana-clear">&nbsp;</div>
				<div class="avejana-comments" style="margin-top: 20px">
					<div class="avejana-username">
						<?php echo esc_html(strtoupper(substr($review->UserName, 0, 1))) ?>
					</div>
					<div class="avejana-details">
						<div class="avejana-review-feild">
							<p><?php echo esc_html($review->UserName) ?>
								<span style="margin-left: 10px;" class="avejana-date"><?php $dt = date_create($review->ReviewDate); echo esc_html(date_format($dt, 'm/d/Y')) ?></span>
								<span style="font-size:11px;color:#20b2aa;font-style:italic;">
									<?php
									if( wc_customer_bought_product( $review->UserEmail, NULL, get_the_ID() ) ){
										echo 'Verified buyer';
									}
									?>
								</span>
							</p>
						</div>
						<div class="avejana-separate-rate" data-value="<?php echo esc_attr($review->Ratings) ?>"></div>
						<div class="avejana-tweet">
							<?php echo esc_html(stripslashes_deep( $review->Title )) ?>
							<p><?php echo esc_html(stripslashes_deep( $review->Description )) ?></p>
						</div>
						<?php if($review->Reply != '') { ?>
							<div class="avejana-response">
								<p class="avejana-header"><?php echo esc_html($review->RepliedBy) . "'s" ?> Response: </p>
								<p class="avejana-response-comment"><?php echo esc_html($review->Reply) ?></p>
							</div>
						<?php } ?>
					</div>
					<div class="avejana-clear">&nbsp;</div>
				</div>
			<?php }
		}
		if(substr($aveJana_settings['logo_position'], 0, 3) == 'Bot') {
			if( $aveJana_settings['show_logo'] === 'Yes' ) { ?>
				<div class="aveJana_row">
				<?php if( $aveJana_settings['logo_position'] === 'Bottom-Left' ) { ?>
					<a href="https://www.avejana.com"><img src="<?php echo esc_url(plugins_url( '/assets/images/avejana-new-logo.png', __FILE__ )) ?>" style="width: 50px; height: 20px; margin-top: -5px;"></a>
				<?php } elseif ( $aveJana_settings['logo_position'] === 'Bottom-Center' ) { ?>
					<a href="https://www.avejana.com"><img src="<?php echo esc_url(plugins_url( '/assets/images/avejana-new-logo.png', __FILE__ )) ?>" style="margin: auto; display: block; width: 50px; height: 20px; margin-top: -5px;"></a>
				<?php } elseif ( $aveJana_settings['logo_position'] === 'Bottom-Right' ) { ?>
					<a href="https://www.avejana.com"><img src="<?php echo esc_url(plugins_url( '/assets/images/avejana-new-logo.png', __FILE__ )) ?>" style="float: right; width: 50px; height: 20px; margin-top: -5px;"></a>
				<?php } ?>
				</div>
			<?php }
		}
	} else {
		if(substr($aveJana_settings['logo_position'], 0, 3) == 'Top') { ?>
			<div class="aveJana_row" style="margin-bottom: 25px">
			<?php if( $aveJana_settings['logo_position'] === 'Top-Left' ) { ?>
				<a href="https://www.avejana.com"><span style="font-size: 12px; margin-right: 5px">Powered by </span><img src="<?php echo esc_url(plugins_url( '/assets/images/avejana-new-logo.png', __FILE__ )) ?>" style="width: 50px; height: 20px; margin-top: -5px;"></a>
			<?php } elseif ( $aveJana_settings['logo_position'] === 'Top-Center' ) { ?>
				<a href="https://www.avejana.com"><span style="margin: 0 auto; display: block; width: 20%"><span style="font-size: 12px; margin-right: 5px">Powered by </span><img src="<?php echo esc_url(plugins_url( '/assets/images/avejana-new-logo.png', __FILE__ )) ?>" style="width: 50px; height: 20px; margin-top: -5px;"></span></a>
			<?php } elseif ( $aveJana_settings['logo_position'] === 'Top-Right' ) { ?>
				<a href="https://www.avejana.com"><span style="float: right;"><span style="font-size: 12px; margin-right: 5px">Powered by </span><img src="<?php echo esc_url(plugins_url( '/assets/images/avejana-new-logo.png', __FILE__ )) ?>" style="width: 50px; height: 20px; margin-top: -5px;"></span></a>
			<?php } ?>
			</div>
		<?php } ?>
		<div><p>No Reviews to display.</p></div>
	<?php }
}

function wc_aveJana_show_qna_widget() {
	$aveJana_settings = get_option( 'aveJana_settings' );
	if($aveJana_settings['company_url'] != '') {
		$args = array(
			'headers' => array(
				'user_id' => $aveJana_settings['company_id'],
				'REST-AJEVANA-KEY' => $aveJana_settings['api_key']
			)
		);
		$response = wp_remote_get( $aveJana_settings['basic_url'] . '/api/answer?CompanyID=' . $aveJana_settings['company_id'] . '&ProductID=' . get_the_ID(), $args );
		if(!is_a($response, 'WP_Error')) {
			$body = json_decode( $response['body'] ) ;
			$questions_count = count($body->message);
			$aveJana_meta_data = array(
				'question_count' => $questions_count
			);
			update_post_meta( get_the_ID(), '_aveJana_questions', $aveJana_meta_data );
		}
		$is_snippets_enabled = $aveJana_settings['show_snippets'];
?>
		<div class="aveJana-qna-div">
			<form class="aveJana-review-form" id="aveJana_qna_form">
				<?php wp_nonce_field( 'wc_aveJana_qna_verify' ) ?>
				<?php if (!is_user_logged_in()) { ?>
					<div>
						<label class="aveJana-label"><?php _e( 'Name', 'aveJana' ) ?></label>
						<div>
							<input id="aveJana_qna_username" name="aveJana_qna_username" type="text" class="aveJana-input-text-email" maxlength="255" required>
						</div>
					</div>
					<div>
						<label class="aveJana-label"><?php _e( 'Email', 'aveJana' ) ?></label>
						<div>
							<input id="aveJana_qna_user_email" name="aveJana_qna_user_email" type="email" class="aveJana-input-text-email" maxlength="255" required>
						</div>
					</div>
				<?php } else { ?>
					<div>
						<input type="hidden" name="aveJana_qna_username" id="aveJana_qna_username" value="<?php echo wp_get_current_user()->display_name ?>">
						<input type="hidden" name="aveJana_qna_user_email" id="aveJana_qna_user_email" value="<?php echo wp_get_current_user()->user_email ?>">
					</div>
				<?php } ?>
				<div>
					<label class="aveJana-label"><?php _e( 'Ask a Question', 'aveJana' ) ?></label>
					<div>
						<textarea id="aveJana_question_text" name="aveJana_question_text" spellcheck="true" rows="3" cols="38" class="aveJana-textarea" required=""></textarea>
					</div>
				</div>
				<div>
					<div>
						<button type="submit" id="aveJana_submit_question" style="margin-top: 15px" class="aveJana-button"><?php _e( 'Submit', 'aveJana' ) ?></button>
					</div>
				</div>
				<div>
					<label></label>
					<div id="aveJana_qna_message" style="display: none; color: red; font-weight: bold">
						<img src="<?php echo plugins_url( '/assets/images/ajax-loader.gif', __FILE__ ) ?>">
					</div>
				</div>
			</form>
		</div>
		<?php if(isset($body) && $body->status == 'success') { ?>
			<?php foreach ($body->message as $question) { ?>
				<?php if($is_snippets_enabled === 'Yes') { ?>
					<div itemscope itemtype="https://schema.org/Question" style="display: none">
						<div itemprop="text"><?php echo esc_html($question->Question) ?></div>
						<time itemprop="dateCreated" datetime="<?php echo esc_html($question->QuestionDate) ?>"></time>
						<div itemprop="author" itemscope itemtype="https://schema.org/Person">
							<span itemprop="name"><?php echo esc_html($question->UserName) ?></span>
						</div>
						<div itemprop="acceptedAnswer" itemscope itemtype="https://schema.org/Answer">
							<div itemprop="text"><?php echo esc_html($question->Answer) ?></div>
							<div itemprop="author" itemscope itemtype="https://schema.org/Person">
								<span itemprop="name"><?php echo esc_html($question->AnsweredBy) ?></span>
							</div>
						</div>
					</div>
				<?php } ?>
				<div class="avejana-comments" style="margin-top: 20px">
					<div class="avejana-username">
						<?php echo esc_html(strtoupper(substr($question->UserName, 0, 1))) ?>
					</div>
					<div class="avejana-details">
						<div class="avejana-review-feild">
							<p><?php echo esc_html($question->UserName) ?><span style="margin-left: 10px;" class="avejana-date"><?php $dt = date_create($question->QuestionDate); echo esc_html(date_format($dt, 'm/d/Y')) ?></span></p>
						</div>
						<div class="avejana-tweet">
							<?php echo esc_html($question->Question) ?>
						</div>
						<?php if($question->Answer != '') { ?>
							<div class="avejana-response" style="margin-top: 15px;">
								<p class="avejana-header"><?php echo esc_html($question->AnsweredBy) . "'s" ?> Response: </p>
								<p class="avejana-response-comment"><?php echo esc_html($question->Answer) ?></p>
							</div>
						<?php } ?>
					</div>
					<div class="avejana-clear">&nbsp;</div>
				</div>
			<?php } ?>
		<?php } ?>
<?php } else {
		echo "No questions to display";
	}
}

function wc_aveJana_admin_menu() {
	add_menu_page(
		'aveJana Settings',
		'aveJana',
		'manage_options',
		'avejana-settings',
		'wc_aveJana_admin_page',
		plugins_url( '/assets/images/avejana-icon.png', __FILE__ )
	);
}

function wc_aveJana_admin_init() {
	add_action( 'admin_enqueue_scripts', 'wc_aveJana_admin_enqueue' );
}

function wc_aveJana_admin_enqueue() {
	if( !isset( $_GET['page'] ) || $_GET['page'] != 'avejana-settings' ) {
		return;
	}
	wp_register_style( 'aveJana_style', plugins_url( '/assets/css/avejana.css', __FILE__ ) );
	wp_enqueue_style( 'aveJana_style' );
}

function wc_aveJana_admin_page() {
	$aveJana_settings = get_option( 'aveJana_settings' );
?>
	<div class="wrap">
		<div class="avejana-content">
			<div>
				<h2><?php _e( 'aveJana Settings', 'aveJana' ) ?></h2>
				<p>To customize the look and feel and to edit your Mail After Purchase settings or to get your CompanyID and API Key, just head to your dashboard
					<a href="<?php echo $aveJana_settings['company_url'] ?>" target="_blank"><?php echo $aveJana_settings['company_url'] ?></a>.
					<?php if( $aveJana_settings['company_url'] == '' ) { ?>
						If you are not registered with aveJana, you can sign up by clicking <a href="https://www.avejana.com/contact-avejana/" target="_blank">Signup</a>
					<?php } ?>
				</p>
			</div>
			<div class="">
				<form method="post" action="admin-post.php" class="av-form-list">
					<input type="hidden" name="action" value="wc_aveJana_save_options">
					<?php wp_nonce_field( 'wc_aveJana_options_verify' ) ?>
					<ul class="av-form-list">
						<li class="formli">
							<label><?php _e( 'Company ID', 'aveJana' ) ?>:</label>
							<input type="text" class="input-box" name="aveJana_inputCompanyID" id="aveJana_inputCompanyID" value="<?php echo $aveJana_settings['company_id'] ?>" required>
						</li>
						<li class="formli">
							<label><?php _e( 'API KEY', 'aveJana' ) ?></label>
							<input type="text" class="input-box" name="aveJana_inputAPIKEY" id="aveJana_inputAPIKEY" value="<?php echo $aveJana_settings['api_key'] ?>" required>
						</li>
						<li class="formli">
							<label><?php _e( 'Company URL', 'aveJana' ) ?></label>
							<input type="text" class="input-box" name="aveJana_inputCompanyURL" id="aveJana_inputCompanyURL" value="<?php echo $aveJana_settings['company_url'] ?>" required readonly>
						</li>
						<li class="formli">
							<label><?php _e( "Show aveJana Logo", "aveJana" ) ?></label>
							<select class="input-box" name="aveJana_inputShowLogo" id="aveJana_inputShowLogo" >
								<option value="<?php _e( 'Yes', 'aveJana' ) ?>" <?php echo $aveJana_settings['show_logo'] == 'Yes' ? 'selected' : '' ?>><?php _e( "Yes", "aveJana" ) ?></option>
								<option value="<?php _e( 'No', 'aveJana' ) ?>" <?php echo $aveJana_settings['show_logo'] == 'No' ? 'selected' : '' ?>><?php _e( "No", "aveJana" ) ?></option>
							</select>
						</li>
						<li class="formli">
							<label><?php _e( "aveJana's Logo Position", 'aveJana' ) ?></label>
							<select class="input-box" name="aveJana_inputLogoPosition" id="aveJana_inputLogoPosition">
								<option value="<?php _e( 'Top-Left', 'aveJana' ) ?>" <?php echo $aveJana_settings['logo_position'] == 'Top-Left' ? 'selected' : '' ?>><?php _e( 'Top-Left', 'aveJana' ) ?></option>
								<option value="<?php _e( 'Top-Center', 'aveJana' ) ?>" <?php echo $aveJana_settings['logo_position'] == 'Top-Center' ? 'selected' : '' ?>><?php _e( 'Top-Center', 'aveJana' ) ?></option>
								<option value="<?php _e( 'Top-Right', 'aveJana' ) ?>" <?php echo $aveJana_settings['logo_position'] == 'Top-Right' ? 'selected' : '' ?>><?php _e( 'Top-Right', 'aveJana' ) ?></option>
								<option value="<?php _e( 'Bottom-Left', 'aveJana' ) ?>" <?php echo $aveJana_settings['logo_position'] == 'Bottom-Left' ? 'selected' : '' ?>><?php _e( 'Bottom-Left', 'aveJana' ) ?></option>
								<option value="<?php _e( 'Bottom-Center', 'aveJana' ) ?>" <?php echo $aveJana_settings['logo_position'] == 'Bottom-Center' ? 'selected' : '' ?>><?php _e( 'Bottom-Center', 'aveJana' ) ?></option>
								<option value="<?php _e( 'Bottom-Right', 'aveJana' ) ?>" <?php echo $aveJana_settings['logo_position'] == 'Bottom-Right' ? 'selected' : '' ?>><?php _e( 'Bottom-Right', 'aveJana' ) ?></option>
							</select>
						</li>
						<li class="formli">
							<label><?php _e( "Load bootstrap JS", "aveJana" ) ?></label>
							<select class="input-box" name="aveJana_inputLoadBootstrap" id="aveJana_inputLoadBootstrap">
								<option value="<?php _e( 'Yes', 'aveJana' ) ?>" <?php echo $aveJana_settings['load_bootstrap'] == 'Yes' ? 'selected' : '' ?>><?php _e( 'Yes', 'aveJana' ) ?></option>
								<option value="<?php _e( 'No', 'aveJana' ) ?>" <?php echo $aveJana_settings['load_bootstrap'] == 'No' ? 'selected' : '' ?>><?php _e( 'No', 'aveJana' ) ?></option>
							</select>
						</li>
					</ul>
					<div class="buttons-set">
						<button type="submit" id="submit-aveJana-settings"><?php _e( 'Save Settings', 'aveJana' ) ?></button>
					</div>
				</form>
			</div>
			<button id="aveJana_upload_sales_btn" style="background-color: #3498DB; color: #ffffff; margin-top: 10px; height: 40px;" data-value="<?php //echo $aveJana_settings['company_url'] ?>" >Upload Historical Sales</button>
			<div id="myModal" class="aveJana-modal">
				<div class="aveJana-modal-content">
					<span class="aveJana-close">&times;</span>
					<div style="text-align: center;">
						<p id="aveJana-modal-text"></p><br>
						<form method="post" action="admin-post.php" class="av-form-list">
							<input type="hidden" name="action" value="wc_aveJana_upload_historical_sales">
							<?php wp_nonce_field( 'wc_aveJana_sales_history_verify' ) ?>
							<button id="aveJana_modal_button" type="submit" style="background-color: #3498DB; color: #ffffff; height: 40px; width: 75px">OK</button>
						</form>
					</div>
				</div>
			</div>
			<div id="aveJana-image-div" class="aveJana-image-div" style="display: none;">
				<img src="<?php echo esc_url(plugins_url('/assets/images/ajax-loader.gif', __FILE__)) ?>">
			</div>
			<?php
			if( isset( $_GET['status']) && $_GET['status'] == 1 ) {
			?>
				<div id="aveJana-success-div" class="aveJana-image-div aveJana-message-div">You are successfully connected to your aveJana's dashboard.</div>
			<?php
			} elseif( isset( $_GET['status']) && $_GET['status'] == 0 ) {
			?>
				<div id="aveJana-error-div" class="aveJana-image-div aveJana-message-div">Invalid CompanyID or API Key.</div>
			<?php } ?>
		</div>
	</div>
	<script type="text/javascript">
		var submit_btn = document.getElementById( "submit-aveJana-settings" );
		var historical_sales_btn = document.getElementById( "aveJana_upload_sales_btn" );
		var modal = document.getElementById('myModal');
		var span = document.getElementsByClassName("aveJana-close")[0];
		historical_sales_btn.onclick = function() {
			if( document.getElementById("aveJana_inputCompanyURL").getAttribute('value') === '' ) {
				document.getElementById( "aveJana-modal-text" ).innerHTML = "Connect to aveJana's dashboard to start this feature";
			} else {
				document.getElementById( "aveJana-modal-text" ).innerHTML = "By clicking OK, you'll agree to upload your sales history to your aveJana's dashboard";
			}
			modal.style.display = "block";
		}

		span.onclick = function() {
			modal.style.display = "none";
		}

		window.onclick = function(event) {
			if (event.target == modal) {
				modal.style.display = "none";
			}
		}

		submit_btn.onclick = function() {
			var image_div = document.getElementById( "aveJana-image-div" );
			var success_div = document.getElementById( "aveJana-success-div" );
			var error_div = document.getElementById( "aveJana-error-div" );
			image_div.setAttribute( "style", "display: block" );
			if( error_div !== null ) {
				error_div.setAttribute( "style", "display: none" );
			}
			if( success_div !== null ) {
				success_div.setAttribute( "style", "display: none" );
			}
		}
	</script>
<?php
}

function wc_aveJana_upload_historical_sales() {
	if( !current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You are not allowed to be on this page.', 'aveJana' ) );
	}

	check_admin_referer( 'wc_aveJana_sales_history_verify' );
	wp_schedule_event( time(), '30sec', 'wc_aveJana_upload_sales_hook' );
	wp_redirect( admin_url( 'admin.php?page=avejana-settings' ) );
}

function wc_aveJana_upload_sales() {
	$aveJana_settings = get_option( 'aveJana_settings' );
	if( $aveJana_settings['company_url'] != '' ) {
		global $woocommerce;
		global $wpdb;
		$sales_url = $aveJana_settings['company_url'] . '/api/sales';
		$args = array(
			'post_type' => 'shop_order',
			'post_status' => array('wc-completed', 'wc-processing'),
			'posts_per_page' => 25
		);
		add_filter( 'posts_where', 'wc_aveJana_posts_where' );
		$aveJana_loop = new WP_Query( $args );
		remove_filter( 'posts_where', 'wc_aveJana_posts_where' );
		if ( $aveJana_loop->have_posts() ) {
			while ( $aveJana_loop->have_posts() ) {
				$aveJana_loop->the_post();
				$order_id = $aveJana_loop->post->ID;
				$order = new WC_Order($order_id);
				$order_date = date('Y-m-d');
				$name =  sanitize_text_field(get_post_meta($order_id,'_billing_first_name',true));
				$email = sanitize_text_field(get_post_meta($order_id,'_billing_email',true));
				$args = array(
					'headers' => array(
						'user_id' => $aveJana_settings['company_id'],
						'REST-AJEVANA-KEY' => $aveJana_settings['api_key']
					),
					'method' => 'PUT',
					'body' => array(
						'OrderID' => $order_id,
						'OrderDate' => $order_date,
						'CustomerName' => $name,
						'CustomerEmail' => $email,
						'ProductID' => '',
						'Price' => 0,
						'Quantity' => 0
					)
				);
				$items = $order->get_items();
				foreach( $items as $item ){
					$args['body']['ProductID'] = $item['product_id'];
					$total = str_replace(",", "", $item['line_total']);
					$args['body']['Price'] = number_format($total, 2, ".", "");
					$args['body']['Quantity'] = $item['qty'];
					$update_post = array(
						'ID' => $aveJana_loop->post->ID,
						'is_uploaded_to_aveJana' => 1
					);
					$response = wp_remote_request( $sales_url, $args );
					$body = json_decode( $response['body'] );
					if( $body->status === 'success' ) {
						$wpdb->query("update $wpdb->posts set is_uploaded_to_aveJana = 1 where ID = '" . $order_id . "' ");
					}
				}
			}
		}
	}
	wp_redirect( admin_url( 'admin.php?page=avejana-settings' ) );
}

function wc_aveJana_save_options() {
	if( !current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You are not allowed to be on this page.', 'aveJana' ) );
	}

	check_admin_referer( 'wc_aveJana_options_verify' );
	$opts = get_option( 'aveJana_settings' );
	$opts['company_id'] = sanitize_text_field( $_POST['aveJana_inputCompanyID'] );
	$opts['api_key'] = sanitize_text_field( $_POST['aveJana_inputAPIKEY'] );
	$opts['company_url'] = sanitize_text_field( $_POST['aveJana_inputCompanyURL'] );
	$opts['show_logo'] = sanitize_text_field( $_POST['aveJana_inputShowLogo'] );
	$opts['logo_position'] = sanitize_text_field( $_POST['aveJana_inputLogoPosition'] );
	$opts['load_bootstrap'] = sanitize_text_field( $_POST['aveJana_inputLoadBootstrap'] );
	$args = array(
		'headers' => array(
			'user_id' => $opts['company_id'],
			'REST-AJEVANA-KEY' => $opts['api_key']
		)
	);
	$response = wp_remote_get( 'https://company.avejana.com/api/get_company_url', $args );
	$body = json_decode( $response['body'] ) ;
	if( $body->status === 'success' ) {
		$opts['company_url'] = stripslashes_deep( $body->message );
		wp_schedule_event( time(), '30sec', 'wc_aveJana_upload_products_hook' );
		$status_value = 1;
	} else {
		$opts['company_url'] = '';
		$status_value = 0;
	}

	if( $opts['company_url'] != '' ) {
		$args = array(
				'headers' => array(
					'user_id' => $opts['company_id'],
					'REST-AJEVANA-KEY' => $opts['api_key']
				)
		);
		$response = wp_remote_get( $opts['company_url'] . '/api/snippets?CompanyID=' . $opts['company_id'], $args );
		if(!is_a($response, 'WP_Error')) {
			$body = json_decode( $response['body'] ) ;
			if( $body->status === 'success' ) {
				if($body->message == 1) {
					$opts['show_snippets'] = 'Yes';
				} elseif ($body->message == 0) {
					$opts['show_snippets'] = 'No';
				}
			} else {
				$opts['show_snippets'] = 'No';
			}
		} else {
			$opts['show_snippets'] = 'No';
		}
	} else {
		$opts['show_snippets'] = 'No';
	}
	update_option( 'aveJana_settings', $opts );
	wp_redirect( admin_url( 'admin.php?page=avejana-settings&status=' . $status_value ) );
}

function wc_aveJana_upload_products() {
	global $wpdb;
	$aveJana_settings = get_option( 'aveJana_settings' );
	if( $aveJana_settings['company_url'] != '' ) {
		$args = array(
			'post_type' => 'product',
			'posts_per_page' => 25
		);
		add_filter( 'posts_where', 'wc_aveJana_posts_where' );
		$aveJana_loop = new WP_Query( $args );
		remove_filter( 'posts_where', 'wc_aveJana_posts_where' );
		if ( $aveJana_loop->have_posts() ) {
			while ( $aveJana_loop->have_posts() ) {
				$aveJana_loop->the_post();
				$product_id = $aveJana_loop->post->ID;
				$product = new WC_Product($product_id);
				if ( version_compare( wc_aveJana_woocommerce_version_check(), '3.0', "<" ) ) {
					$desc = $product->get_post_data()->post_excerpt;
					$price = $product->get_price_including_tax();
				} else {
					$desc = get_post()->post_excerpt;
					$price = wc_get_price_including_tax( $product );
				}
				if($price == "") {
					$price = 0.0;
				}
				$price = str_replace(",", "", $price);
				$price = number_format($price, 2, ".", "");
				$product_image = wp_get_attachment_url(get_post_thumbnail_id($product_id));
				$product_image = $product_image ? $product_image : '';
				$product_image = "";
				$attachment_id =  get_post_thumbnail_id( $product_id );
				if( isset($attachment_id) ) {
					$product_image_obj = wp_get_attachment_image_src( $attachment_id, 'single-post-thumbnail' );
					if( isset($product_image_obj) ) {
						$product_image = $product_image_obj[0];
					}
				}
				$response = wp_remote_request( $aveJana_settings['company_url'] . '/api/product', array(
						'method' => 'PUT',
						'headers' => array(
							'user_id' => $aveJana_settings['company_id'],
							'REST-AJEVANA-KEY' => $aveJana_settings['api_key']
						),
						'body' => array(
							'CompanyID'				=>	$aveJana_settings['company_id'],
							'ProductID'				=>	$product_id,
							'ProductURL'			=>	get_permalink( $product_id ),
							'ProductName'			=>	$product->get_title(),
							'ProductDescription'	=>	$desc,
							'ProductPictureURL'		=>	$product_image,
							'ProductPrice'			=>	$price
						)
					)
				);
				$response_body = json_decode( $response['body'] ) ;
				if( $response_body->status === 'success' ) {
					$wpdb->query("update " . $wpdb->prefix . "posts set is_uploaded_to_aveJana = 1 where ID = '$product_id' ");
				}
			}
		}
	}
}

function wc_aveJana_posts_where( $where ) {
	$where .= ' AND is_uploaded_to_aveJana = 0';
	return $where;
}

function wc_aveJana_cron_schedules( $schedules ) {
	if( !isset($schedules["30sec"]) ) {
		$schedules["30sec"] = array(
			'interval' => 30,
			'display' => __('Once every 30 seconds')
		);
	}
	if( !isset($schedules["5min"]) ) {
		$schedules["5min"] = array(
			'interval' => 60 * 5,
			'display' => __('Once every 5 minutes')
		);
	}
	if( !isset($schedules["15min"]) ) {
		$schedules["15min"] = array(
			'interval' => 15 * 60,
			'display' => __('Once every 15 minutes')
		);
	}
	if( !isset($schedules["30min"]) ) {
		$schedules["30min"] = array(
			'interval' => 30 * 60,
			'display' => __('Once every 30 minutes')
		);
	}
	if( !isset($schedules["1hour"]) ) {
		$schedules["1hour"] = array(
			'interval' => 60 * 60,
			'display' => __('Once every 30 minutes')
		);
	}
	return $schedules;
}

function wc_aveJana_activation() {
	if( version_compare( get_bloginfo( 'version' ), '4.2', '<' ) ) {
		wp_die( 'You must have a minimum version of 4.2 to use this plugin.' );
	}
	if( !wc_aveJana_compatible() ) {
		if( version_compare(phpversion(), '5.2.0') < 0 ) {
			wp_die( 'aveJana plugin requires PHP 5.2.0 or above.' );
		}
		if( !function_exists('curl_init') ) {
			wp_die( 'aveJana plugin requires cURL library.' );
		}
	}
	if( !wc_aveJana_woocommerce_version_check() ) {
		wp_die( "You must have WooCommerce installed and activated for aveJana's Review plugin to work properly" );
	}

	if(current_user_can( 'activate_plugins' )) {
		$default_settings = get_option( 'aveJana_settings' );
		if( !$default_settings ) {
			add_option( 'aveJana_settings', wc_aveJana_get_default_settings() );
		} else {
			if( $default_settings['company_url'] != '' ) {
				wp_schedule_event( time(), '30sec', 'wc_aveJana_upload_products_hook' );
			}
		}
		update_option('native_star_ratings_enabled', get_option('woocommerce_enable_review_rating'));
		update_option('woocommerce_enable_review_rating', 'no');
		global $wpdb;
		$row = $wpdb->get_results(  "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '" . $wpdb->prefix . "posts' AND column_name = 'is_uploaded_to_aveJana'");
		
		if( count($row) <= 0 ){
			$wpdb->query("ALTER TABLE " . $wpdb->prefix . "posts ADD `is_uploaded_to_aveJana` TINYINT NOT NULL DEFAULT '0'");
		}
	}
}

function wc_aveJana_woocommerce_version_check() {
	if ( class_exists( 'WooCommerce' ) ) {
		global $woocommerce;
		return $woocommerce->version;
	}
	return false;
}

function wc_aveJana_get_default_settings() {
	return array(
		'company_id'		=>	'',
		'api_key'			=>	'',
		'basic_url'			=>	'https://company.avejana.com',
		'company_url'		=>	'',
		'show_logo'			=>	'Yes',
		'logo_position'		=>	'Top Center',
		'load_bootstrap'	=>	'Yes',
		'show_snippets'		=>	'No'
	);
}

function wc_aveJana_deactivate() {
	update_option('woocommerce_enable_review_rating', get_option('native_star_ratings_enabled'));
	wp_clear_scheduled_hook( 'wc_aveJana_upload_products_hook' );
	wp_clear_scheduled_hook( 'wc_aveJana_upload_sales_hook' );
}

function wc_aveJana_compatible() {
	return version_compare( phpversion(), '8.2.0') >= 0 && function_exists( 'curl_init' );
}