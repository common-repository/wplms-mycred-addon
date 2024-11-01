<?php
/**
 * Get Current Users Ref ID
 * Returns the current users ref ID.
 * @version 1.0
 */
function mycred_get_my_ref_id()
{
	if ( !is_user_logged_in() ) return;

	$user_id = get_current_user_id();
	$ref = get_user_meta( $user_id, 'ref_id', true );
	if ( empty( $ref ) ) {
		$ref = wp_generate_password( 6, false );
		update_user_meta( $user_id, 'ref_id', $ref );
	}
	return urlencode( $ref );
}

/**
 * Add Ref ID to Home URL
 * Appends the current users refid to the sites home url.
 * @version 1.0
 */
function mycred_get_ref_for_home()
{
	if ( !is_user_logged_in() ) return;
	return add_query_arg( array( 'ref' => mycred_get_my_ref_id() ), home_url() );
}

/**
 * Add Ref ID to URL
 * Appends the current users refid to a given url.
 * @version 1.0
 */
function mycred_get_ref_for_url( $url = '' )
{
	if ( !is_user_logged_in() || empty( $url ) ) return;
	return add_query_arg( array( 'ref' => mycred_get_my_ref_id() ), $url );
}

/**
 * Insert Users Ref ID
 * Inserts the current users referral ID in the profile edit page.
 * @version 1.0
 */
add_action( 'profile_personal_options', 'show_users_referral_id' );
function show_users_referral_id( $user = NULL){ 
	echo '<p>'.__('Refer users to our website and earn points!','wplms-mycred').'</p>
	<p>'.mycred_get_ref_for_home().'</p>';
}

add_filter('mycred_br_history_page_title','show_referral_on_bp');
function show_referral_on_bp($title){
	$referral_option = get_option('mycred_pref_core');
	if(is_array($referral_option) && is_array($referral_option['wplms']) && is_numeric($referral_option['wplms']['referral']))
		$referral = $referral_option['wplms']['referral'];
	if(isset($referral) && $referral > 0){
		$title.='<span style="float:right;font-size:12px"><strong>'.__('Referral URL ','wplms-mycred').' ('.$referral.')</strong> : '.mycred_get_ref_for_home().'</span>';
	}
	return $title;
}

/**
 * Get User ID from Ref ID
 * Returns the user id of a given referral id.
 * @version 1.0
 */
function mycred_get_userid_from_ref( $ref_id = NULL ){

	if ( $ref_id === NULL ) return false;

	global $wpdb;
	$user_id = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s;", 'ref_id', $ref_id ) );
	$wpdb->flush();
	if ( $user_id === NULL ) return false;

	return intval( $user_id );
}

/**
 * Detect Referrer
 * Checks if user has been referred, awards points if it is
 * a unique IP and removed the ref query argument.
 * @version 1.0
 */
add_action( 'template_redirect', 'mycred_catch_referred_users' );
function mycred_catch_referred_users()
{	
	if ( !isset( $_GET['ref'] ) ) return;

	if ( !empty( $_GET['ref'] ) ) {
		$referral_option = get_option('mycred_pref_core');
		if(is_array($referral_option) && is_array($referral_option['wplms']) && is_numeric($referral_option['wplms']['referral']))
			$referral = $referral_option['wplms']['referral'];
		else
			return;

		$ref_id = urldecode( $_GET['ref'] );
		$user_id = mycred_get_userid_from_ref( $ref_id );

		
		if ( $user_id !== false ) return;
		if ( is_user_logged_in() && get_current_user_id() == $user_id ) return;

		$mycred = mycred();
		$IP = $_SERVER['REMOTE_ADDR'];
		if ( empty( $IP ) ) return;
		if ( ! $mycred->has_entry( 'visitor_referring', '', $user_id, $IP ) ) {
			$mycred->add_creds(
				'visitor_referring',
				$user_id,
				$referral,
				'%plural% for visitor referral',
				'',
				$IP
			);
		}
		wp_redirect( remove_query_arg( 'ref' ) );
		exit;
	}
}