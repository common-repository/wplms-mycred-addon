<?php

/**
 * WPLMS Attendance API
 *
 * @author 		VibeThemes
 * @category 	Admin
 * @package 	Wplms-Attendance/Includes
 * @version     1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wplms_Mycred_API{

	public static $instance;
    public static function init(){

        if ( is_null( self::$instance ) )
            self::$instance = new Wplms_Mycred_API();
        return self::$instance;
        
    }

	private function __construct(){
		if(!defined('WPLMS_API_NAMESPACE')){
			$this->namespace = BP_COURSE_API_NAMESPACE.'/mycred';
		}else{
			$this->namespace = WPLMS_API_NAMESPACE.'/mycred';
			
		}

		add_action( 'rest_api_init', array($this,'mycred_endpoints'));
		
 
	}


	function mycred_endpoints(){
		register_rest_route( $this->namespace, '/logs', array(
			array(
				'methods'             =>  "POST",
				'callback'            =>  array( $this, 'get_user_mycred_logs' ),
				'permission_callback' => array( $this, 'mycred_request_validate' ),
				
			),
		));
		register_rest_route( $this->namespace, '/assigncourse', array(
			array(
				'methods'             =>  "POST",
				'callback'            =>  array( $this, 'check_points' ),
				'permission_callback' => array( $this, 'mycred_request_validate' ),
				
			),
		));

		
	}

	function mycred_request_validate($request){

		$body = json_decode($request->get_body(),true);
        
        if (empty($body['token']) && function_exists('vibebp_get_setting')){
            $client_id = $request->get_param('client_id');
            if($client_id == vibebp_get_setting('client_id')){
                return true;
            }
        }else{
            $token = $body['token'];
        }

        if(!empty($body['token'])){
            
            $this->user = apply_filters('vibebp_api_get_user_from_token','',$body['token']);
            if(!empty($this->user)){
            	$this->user_id = $this->user->id;
                return true;
            }
        }

		$user_id = $request->get_param('id');

		$security = $request->get_param('security');

		$user = get_userdata( $user_id );
		if ( $user === false ) {
		    return false;
		} else {
		   return true;
		}

		if($security == urlencode(vibe_get_option('security_key'))){
			return true;
		}

		return false;
	}

	function check_points($request){
		$body = json_decode($request->get_body(),true);
        $course_id = $body['id'];
        $user_id = $this->user->id;
        $points = (int)get_post_meta($course_id,'vibe_mycred_points',true);
		$mycred = mycred();
		$balance = (int)$mycred->get_users_cred( $user_id );
		$return  = array('status'=>false);
		if($balance < $points){
			$return['message'] = _x('Not enough balance','wplms-mycred');
		    return new WP_REST_Response( $return, 200 );
		}

		$deduct = -1*$points;
		$new_balance = $balance;
		$subscription = get_post_meta($course_id,'vibe_mycred_subscription',true);
		if(isset($subscription) && $subscription && $subscription !='H'){

			$duration = get_post_meta($course_id,'vibe_mycred_duration',true);

		    $mycred_duration_parameter = get_post_meta($course_id,'vibe_mycred_duration_parameter',true);
		    if(empty($mycred_duration_parameter)){
		    	$mycred_duration_parameter = 86400;
		    }
		    $duration = $duration*$mycred_duration_parameter;
	
		    bp_course_add_user_to_course($user_id,$course_id,$duration);
		    $new_balance = $balance - $points;
		}else{

			bp_course_add_user_to_course($user_id,$course_id);
			$new_balance = $balance - $points;
		}
		$return  = array('status'=>true);

		$expiry = bp_course_get_user_expiry_time($user_id,$course_id);
		$mycred->update_users_balance( $user_id, $deduct);
		$mycred->add_to_log('take_course',
			$user_id,
			$deduct,
			sprintf(__('Student %s subscibed for course','wplms-mycred'),bp_core_get_user_displayname($user_id)),
			$course_id,
			__('Student Subscribed to course , ends on ','wplms-mycred').date("jS F, Y",$expiry));


		$durationtime = $duration.' '.calculate_duration_time($mycred_duration_parameter);

		bp_course_record_activity(array(
		      'action' => __('Student subscribed for course ','wplms-mycred').get_the_title($course_id),
		      'content' => __('Student ','wplms-mycred').bp_core_get_userlink( $user_id ).__(' subscribed for course ','wplms-mycred').get_the_title($course_id).__(' for ','wplms-mycred').$durationtime,
		      'type' => 'subscribe_course',
		      'item_id' => $course_id,
		      'primary_link'=>get_permalink($course_id),
		      'secondary_item_id'=>$user_id
        ));   
        $instructors=apply_filters('wplms_course_instructors',get_post_field('post_author',$course_id),$course_id);

        // Commission calculation
        
        if(function_exists('vibe_get_option'))
      	$instructor_commission = vibe_get_option('instructor_commission');
      		
      	if(!isset($instructor_commission))
	      $instructor_commission = 70;

	  	
	    $commissions = get_option('instructor_commissions');
	    if(isset($commissions) && is_array($commissions)){
	    	if(is_array($instructors)){
	    		foreach($instructors as $instructor){
	    			if(!empty($commissions[$course_id]) && !empty($commissions[$course_id][$instructor])){
						$calculated_commission_base = round(($points*$commissions[$course_id][$instructor]/100),2);
					}else{
						$i_commission = $instructor_commission/count($instructors);
						$calculated_commission_base = round(($points*$i_commission/100),2);
					}
					$mycred->update_users_balance( $instructor, $calculated_commission_base);
					$mycred->add_to_log('instructor_commission',
					$instructor,
					$calculated_commission_base,
					__('Instructor earned commission','wplms-mycred'),
					$course_id,
					__('Instructor earned commission for student purchasing the course via points ','wplms-mycred')
					);
	    		}
	    	}else{
	    		if(isset($commissions[$course_id][$instructors])){
					$calculated_commission_base = round(($points*$commissions[$course_id][$instructors]/100),2);
				}else{
					$calculated_commission_base = round(($points*$instructor_commission/100),2);
				}

				$mycred->update_users_balance( $instructors, $calculated_commission_base);
				$mycred->add_to_log('instructor_commission',
					$instructor,
					$calculated_commission_base,
					__('Instructor earned commission','wplms-mycred'),
					$course_id,
					__('Instructor earned commission for student purchasing the course via points ','wplms-mycred')
					);
	    	}
		} // End Commissions_array 
        do_action('wplms_course_mycred_points_puchased',$course_id,$user_id,$points);
        return new WP_REST_Response( $return, 200 );
	}

	function get_user_mycred_logs($request){
		$body = json_decode($request->get_body(),true);
		$paged = $body['paged'];
		$number = apply_filters('wplms_mycred_log_api_number',20);	
		$offset = ($paged-1)*$number;	

		$return = array('status'=>false,'entries' => array(),'message'=>_x('something went wrong','','wplms-mycred'));
		$myred   = mycred();
		$args = array(
			'user_id' => $this->user->id,
			'number' => $number,
			'paged' => (int)$paged,
			'offset' => $offset,
			'orderby' => 'time',
			'order'   => 'DESC'
		);
		
		//can be used to add date field
		/*if(!empty($time)){
			$args['time'] = array(
				'dates'   => array( '2016-01-01 00:00:01', '2020-12-31 23:59:59' ),
				'compare' => 'BETWEEN'
			);
		}*/

		// The Query
		$log = new myCRED_Query_Log( $args );
		$log->headers =  array(
				'entry'    => __( 'Entry', 'wplms-mycred' ),
				'time'     => __( 'Date', 'wplms-mycred' ),
				'creds'    => $myred->plural(),
				);
		// The Loop
		if ( $log->have_entries() ) {
			$return['status'] = true;
			$return['message']=_x('Records found','','wplms-mycred');
			// Build your custom loop

			foreach ( $log->results as $entry ) {
				
				$return['entries'][] = $log->get_the_entry($entry);

			}
			
			
		}

		$return['balance'] = mycred_get_users_balance($this->user->id);

		return new WP_REST_Response( $return, 200 );
	}

	
}

Wplms_Mycred_API::init();
