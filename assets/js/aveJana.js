jQuery("#aveJana_write_review_btn").click(function() {
	jQuery("#aveJana_write_review_box").toggle();
});

jQuery(".aveJana-overall-rating").starRating({
	totalStars: 5,
	emptyColor: 'lightgray',
	activeColor: '#FFD700',
	initialRating: jQuery('#aveJana_loader_image_div').data('value'),
	readOnly: true,
	useGradient: false,
	starSize: 25
});

jQuery( ".aveJana_category_rating" ).each(function() {
	jQuery(this).starRating({
		totalStars: 5,
		emptyColor: 'lightgray',
		activeColor: '#FFD700',
		initialRating: jQuery(this).data('value'),
		readOnly: true,
		useGradient: false,
		starSize: 15
	});
});

jQuery( ".avejana-separate-rate" ).each(function() {
	jQuery(this).starRating({
		totalStars: 5,
		emptyColor: 'lightgray',
		activeColor: '#FFD700',
		initialRating: jQuery(this).data('value'),
		readOnly: true,
		useGradient: false,
		starSize: 20
	});
});

jQuery(".aveJana-summary-rating").starRating({
	totalStars: 5,
	emptyColor: 'lightgray',
	activeColor: '#FFD700',
	initialRating: jQuery('#aveJana_loader_image_div').data('value'),
	readOnly: true,
	useGradient: false,
	starSize: 20
});

jQuery("#aveJana_write_review_div").click(function() {
	jQuery('#aveJana_message').css('display', 'none');
});

jQuery("#aveJana_review_title, #aveJana_username, #aveJana_user_email, #aveJana_review_text").keyup(function() {
	jQuery('#aveJana_message').css('display', 'none');
});

jQuery("#aveJana_qna_username, #aveJana_qna_user_email, #aveJana_question_text").keyup(function() {
	jQuery('#aveJana_qna_message').css('display', 'none');
});

jQuery("#aveJana_submit_review").click(function() {
	if(jQuery("#aveJana_review_title").val() != '' && jQuery("#aveJana_username").val() != '' && jQuery("#aveJana_user_email").val() != '' && jQuery("#aveJana_review_text").val() != '' && jQuery('#aveJana_review_rating').val() != '') {
		var src = jQuery("#aveJana_loader_image_div").html();
		jQuery('#aveJana_message').html("<img src='" + src + "'>");
		jQuery('#aveJana_message').css('display', 'block');
	}
});

jQuery("#aveJana_qna_form").click(function() {
	if( jQuery("#aveJana_qna_username").val() != '' && jQuery("#aveJana_qna_user_email").val() != '' && jQuery("#aveJana_question_text").val() != '') {
		var src = jQuery("#aveJana_loader_image_div").html();
		jQuery('#aveJana_qna_message').html("<img src='" + src + "'>");
		jQuery('#aveJana_qna_message').css('display', 'block');
	}
});

jQuery(".aveJana-write-rating").starRating({
	starSize: 20,
	emptyColor: 'lightgray',
	hoverColor: '#FFD700',
	activeColor: '#FFD700',
	useGradient: false,
	useFullStars: true,
	disableAfterRate: false,
	callback: function(currentRating, $el) {
		jQuery('#aveJana_review_rating').val(currentRating);
	}
});

jQuery('#aveJana_review_form').submit(function(e) {
	e.preventDefault();
	if(jQuery('#aveJana_review_rating').val() === '') {
		jQuery('#aveJana_message').html("Please select star rating to proceed");
		jQuery('#aveJana_message').css('display', 'block');
		return;
	}
	var formObj = {
		action: 'wc_aveJana_submit_review',
		product_id: jQuery('#aveJana_review_rating').data('productid'),
		user_name: jQuery('#aveJana_username').val(),
		user_email: jQuery('#aveJana_user_email').val(),
		review_title: jQuery('#aveJana_review_title').val(),
		review_rating: jQuery('#aveJana_review_rating').val(),
		review: jQuery('#aveJana_review_text').val()
	}
	
	jQuery.post( aveJana_review.ajax_url, formObj, function(response) {
		jQuery("#aveJana_review_title").val('');
		if(jQuery("#aveJana_username").attr('type') == 'text' ) {
			jQuery("#aveJana_username").val('');
		}
		if(jQuery("#aveJana_user_email").attr('type') == 'email' ) {
			jQuery("#aveJana_user_email").val('');
		}
		jQuery("#aveJana_review_text").val('');
		jQuery('#aveJana_message').html(response);
		jQuery('#aveJana_message').css('color', 'blue');
		jQuery('#aveJana_message').css('display', 'block');
	});
});

jQuery('#aveJana_qna_form').submit(function(e) {
	e.preventDefault();
	var formObj = {
		action: 'wc_aveJana_submit_question',
		product_id: jQuery('#aveJana_review_rating').data('productid'),
		user_name: jQuery('#aveJana_qna_username').val(),
		user_email: jQuery('#aveJana_qna_user_email').val(),
		question: jQuery('#aveJana_question_text').val()
	}
	
	jQuery.post( aveJana_review.ajax_url, formObj, function(response) {
		if(jQuery("#aveJana_qna_username").attr('type') == 'text' ) {
			jQuery("#aveJana_qna_username").val('');
		}
		if(jQuery("#aveJana_qna_user_email").attr('type') == 'email' ) {
			jQuery("#aveJana_qna_user_email").val('');
		}
		jQuery("#aveJana_question_text").val('');
		jQuery('#aveJana_qna_message').html(response);
		jQuery('#aveJana_qna_message').css('color', 'blue');
		jQuery('#aveJana_qna_message').css('display', 'block');
	});
});

function open_avejana_review_tab() {
	jQuery('.entry-content').each(function() {
		jQuery(this).css('display', 'none');
	});
	document.getElementById('tab-aveJana_review_widget').style = 'display:block';
	jQuery("a[href='#tab-aveJana_review_widget']").tab('show');
	window.location.href = "#tab-aveJana_review_widget";
	history.pushState("", document.title, window.location.pathname);
	return false;
}

function open_avejana_question_tab() {
	jQuery('.entry-content').each(function() {
		jQuery(this).css('display', 'none');
	});
	document.getElementById('tab-aveJana_qna_widget').style = 'display:block';
	jQuery("a[href='#tab-aveJana_qna_widget']").tab('show');
	window.location.href = "#tab-aveJana_qna_widget";
	history.pushState("", document.title, window.location.pathname);
	return false;
}