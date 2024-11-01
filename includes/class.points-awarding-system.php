<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class WPLMS_Points_Awarding_System{

    public static $instance;
    public static function init(){
    if ( is_null( self::$instance ) )
        self::$instance = new WPLMS_Points_Awarding_System();

        return self::$instance;
    }

	public $action_hooks=array(
		'course' => array(	
			'subscribed' => 'wplms_course_subscribed',// $course_id
			'started'=>'wplms_start_course', // $course_id
			'finished'=>'wplms_submit_course',// $course_id
			'score'=>'wplms_evaluate_course',//$course_id,$marks,$user_id
			'badges_earned'=>'wplms_badge_earned',//$course_id,$badges,$user_id
			'certificates_earned'=>'wplms_certificate_earned',//$course_id,$certificates,$user_id
			'course_review'=>'wplms_course_review',
			'course_unsubscribe'=>'wplms_course_unsubscribe',
			'course_retake'=>'wplms_course_retake'
			),
		'quiz' => array(
			'started'=>'wplms_start_quiz', //$quiz_id
			'finished'=>'wplms_submit_quiz', //$quiz_id
			'score'=>'wplms_evaluate_quiz',//$quiz_id,$marks,$user_id
			'quiz_retake'=>'wplms_quiz_retake'
			),
		'assignment' => array(
			'started'=>'wplms_start_assignment', //$assignment_id,$marks, $user_id
			'finished'=>'wplms_submit_assignment', //$assignment_id,$marks, $user_id
			'score'=>'wplms_evaluate_assignment',// $assignment_id,$marks, $user_id
			),
		'unit' => array(
			'finished'=>'wplms_unit_complete',
	));

	private function __construct(){

		$eligibility_option = get_option('wplms_mycred_eligibility');
		if(!isset($eligibility_option) || !is_array($eligibility_option)){
			update_option('wplms_mycred_eligibility',$this->action_hooks); // initialize action hooks
		}

		foreach($this->action_hooks as $key => $hooks){
			foreach($hooks as $hook){
				//print_r($hook.' action registered');
				add_action($hook,array($this,'check_eligibility'),10,4);
			}
		}
		add_action('bp_activity_register_activity_actions',array($this,'wplms_mycred_register_actions'));
	}

	function user_id_exists($user){

	    global $wpdb;

	    $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->users WHERE ID = %d", $user));

	    if($count == 1){ return true; }else{ return false; }

	}

	function get_user_id_from_prarams($id,$info=NULL,$user_id=NULL,$another_u_id=null,$current_hook){
		
		$post_type = get_post_type($id);
		
		switch ($post_type) {
			case 'course':
				if(!in_array($current_hook, array('wplms_evaluate_course','wplms_badge_earned','wplms_certificate_earned'))){
					if(!empty($info)){
						return $info;
					}
				}
			break;

			case 'quiz':
				if(!in_array($current_hook, array('wplms_evaluate_quiz'))){
					if(!empty($info)){
						return $info;
					}
				}
			break;

			case 'unit':
				//no need handled below
			break;
			
			case 'assignment':
			case 'wplms-assignment':
				if(!in_array($current_hook, array('wplms_evaluate_assignment'))){
					if(!empty($info)){
						return $info;
					}
				}
			break;

			default:

				return apply_filters('wplms_mycred_addon_user_id',$user_id,$id,$info,$user_id,$another_u_id,$current_hook);
			break;
		}

		if(is_user_logged_in() && (empty($user_id) || !$user_id || !is_numeric($user_id))){
			
			$user_id = get_current_user_id();
		}
		if(empty($user_id) || !$this->user_id_exists($user_id) && !empty($another_u_id)){
			$user_id = $another_u_id;
		}

		return $user_id;
	}

	function check_eligibility($id,$info=NULL,$user_id=NULL,$another_u_id=null){
		$current_hook = current_filter();
		
		$user_id = $this->get_user_id_from_prarams($id,$info,$user_id,$another_u_id,$current_hook);
		$point_criteria_ids = '';
		$eligibility_option = get_option('wplms_mycred_eligibility');
		foreach($this->action_hooks as $module => $hooks){
			foreach($hooks as $set=>$hook){
				if($current_hook == $hook){
					if(is_array($eligibility_option[$module][$set])){
						$point_criteria_ids=$eligibility_option[$module][$set];
					}
					break;
					break;
				}
			}
		}

		//post ids are points criteria
		if(is_array($point_criteria_ids)){
			
			foreach($point_criteria_ids as $point_criteria_id){
				$_module = get_post_meta($point_criteria_id,'wplms_module',true);
				$module_id=get_post_meta($point_criteria_id,'wplms_module_id',true);
				$passed_flag=1;
				if(isset($module_id) && is_numeric($module_id)){
					if($module_id !== $id){
						$passed_flag=0;
					}
				}

				if(strpos($current_hook, $_module)===false){
					$passed_flag=0;
				}
				if($passed_flag){
					
					$expiry = get_post_meta($point_criteria_id,'expires',true);
					if(isset($expiry) && is_date($expiry) && time() < strtotime($expiry)){
						$passed_flag=0;
					}

					if($passed_flag){
						
						$global_usage = get_post_meta($point_criteria_id,'global',true);
						$total_usage = get_post_meta($point_criteria_id,'total',true);
						
						if(!empty($global_usage) && is_numeric($global_usage)){
							if($total_usage >= $global_usage){
								$passed_flag=0;
							}
						}
						if($passed_flag){

							
							$user_usage = get_post_meta($point_criteria_id,'user',true);
							$user_specific_usage = get_user_meta($user_id,$point_criteria_id,true);
							if(isset($user_usage) && is_numeric($user_usage) && $user_specific_usage >= $user_usage){
								$passed_flag=0;
							}

							if($passed_flag){
								
								$operator = get_post_meta($point_criteria_id,'wplms_module_score_operator',true);
								
								
								if(isset($operator) && $operator){
									if(empty($total_usage)){
										$total_usage = 0;
									}
									if(empty($user_specific_usage)){
										$user_specific_usage = 0;
									}
									/*print_r($operator);print_r('$$$$');
									print_r($point_criteria_id.','.$id.','.$info.','.$user_id.','.$module.','.$total_usage.','.$user_specific_usage);

									2979,2803,1,,unit,13,0*/

									$this->$operator($point_criteria_id,$id,$info,$user_id,$_module,$total_usage,$user_specific_usage,$another_u_id);
								}
							}
						}
					}
				}
			}
	  	}
	}


	function started($point_criteria_id,$id,$info=NULL,$user_id,$module,$total_usage,$user_specific_usage,$another_u_id=null){
		$value = get_post_meta($point_criteria_id,'value',true);
		if(!$user_id || !is_numeric($user_id))
				$user_id = get_current_user_id();
		

		if(is_numeric($value)){
			$mycred = mycred();	
			$mycred->update_users_balance( $user_id, $value);
			$total_usage++;
			$user_specific_usage++;
			update_post_meta($point_criteria_id,'total',$total_usage);
			update_user_meta($user_id,$point_criteria_id,$user_specific_usage);
			$this->record(array(
				'user_id'=>$user_id,
				'id'=>$id,
				'amount' => $value,
				'module'=>$module,
				'logentry'=> sprintf(__('Student %s gained %s points for starting module "%s" from criteria "%s" ','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id)),
				'message' => sprintf(__('Student %s gained %s points for starting module "%s" from criteria "%s" ','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id)),
				));
		}
	}

	function finished($point_criteria_id,$id,$info,$user_id,$module,$total_usage,$user_specific_usage,$another_u_id=null){
		$value = get_post_meta($point_criteria_id,'value',true);
		
		if(!$user_id || !is_numeric($user_id))
				$user_id = get_current_user_id();
			
		if(is_numeric($value)){
			$mycred = mycred();	
			$mycred->update_users_balance( $user_id, $value);
			$total_usage++;
			$user_specific_usage++;
			update_post_meta($point_criteria_id,'total',$total_usage);
			update_user_meta($user_id,$point_criteria_id,$user_specific_usage);
			$this->record(array(
				'user_id'=>$user_id,
				'id'=>$id,
				'amount' => $value,
				'module'=>$module,
				'logentry'=> sprintf(__('Student %s gained %s points for finishing module "%s" from criteria "%s" ','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id)),
				'message' => sprintf(__('Student %s gained %s points for finishing module "%s" from criteria "%s" ','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id)),
				));
		}
	}

	function greater($point_criteria_id,$id,$info=NULL,$user_id,$module,$total_usage,$user_specific_usage,$another_u_id=null){

		

		$value = get_post_meta($point_criteria_id,'value',true);
		$module_score = get_post_meta($point_criteria_id,'wplms_module_score',true);

		

		if(is_numeric($value) && $info >= $module_score){
			$mycred = mycred();	
			$mycred->update_users_balance( $user_id, $value);
			$total_usage++;
			$user_specific_usage++;
			update_post_meta($point_criteria_id,'total',$total_usage);
			update_user_meta($user_id,$point_criteria_id,$user_specific_usage);
			$this->record(array(
				'user_id'=>$user_id,
				'id'=>$id,
				'amount' => $value,
				'module'=>$module,
				'logentry'=> sprintf(__('Student %s gained %s points for getting score more than %s in module "%s" from criteria "%s" ','wplms-mycred'),bp_core_get_userlink($user_id),$value,$module_score,get_the_title($id),get_the_title($point_criteria_id)),
				'message'=> sprintf(__('Student %s gained %s points for getting score more than %s in module "%s" from criteria "%s" ','wplms-mycred'),bp_core_get_userlink($user_id),$value,$module_score,get_the_title($id),get_the_title($point_criteria_id))
				));
		}
	}

	function lesser($point_criteria_id,$id,$info=NULL,$user_id,$module,$total_usage,$user_specific_usage,$another_u_id=null){
		$value = get_post_meta($point_criteria_id,'value',true);
		$module_score = get_post_meta($point_criteria_id,'wplms_module_score',true);
		if(is_numeric($value) && $info < $module_score){
			$mycred = mycred();	
			$mycred->update_users_balance( $user_id, $value);
			$total_usage++;
			$user_specific_usage++;
			update_post_meta($point_criteria_id,'total',$total_usage);
			update_user_meta($user_id,$point_criteria_id,$user_specific_usage);
			$this->record(array(
				'user_id'=>$user_id,
				'id'=>$id,
				'amount' => $value,
				'module'=>$module,
				'logentry'=> sprintf(__('Student %s gained %s points for getting score less than %s in module "%s" from criteria "%s" ','wplms-mycred'),bp_core_get_userlink($user_id),$value,$module_score,get_the_title($id),get_the_title($point_criteria_id)),
				'message'=> sprintf(__('Student %s gained %s points for getting score less than %s in module "%s" from criteria "%s" ','wplms-mycred'),bp_core_get_userlink($user_id),$value,$module_score,get_the_title($id),get_the_title($point_criteria_id))
				));
		}
	}
	function equal($point_criteria_id,$id,$info=NULL,$user_id,$module,$total_usage,$user_specific_usage,$another_u_id=null){
		$value = get_post_meta($point_criteria_id,'value',true);
		$module_score = get_post_meta($point_criteria_id,'wplms_module_score',true);
		if(is_numeric($value) && $info == $module_score){
			$mycred = mycred();	
			$mycred->update_users_balance( $user_id, $value);
			$total_usage++;
			$user_specific_usage++;
			update_post_meta($point_criteria_id,'total',$total_usage);
			update_user_meta($user_id,$point_criteria_id,$user_specific_usage);
			$this->record(array(
				'user_id'=>$user_id,
				'id'=>$id,
				'amount' => $value,
				'module'=>$module,
				'logentry'=> sprintf(__('Student %s gained %s points for getting score equal to %s in module "%s" from criteria "%s" ','wplms-mycred'),bp_core_get_userlink($user_id),$value,$module_score,get_the_title($id),get_the_title($point_criteria_id)),
				'message'=> sprintf(__('Student %s gained %s points for getting score equal to %s in module "%s" from criteria "%s" ','wplms-mycred'),bp_core_get_userlink($user_id),$value,$module_score,get_the_title($id),get_the_title($point_criteria_id))
				));
		}
	}
	function highest_score($point_criteria_id,$id,$info=NULL,$user_id,$module,$total_usage,$user_specific_usage,$another_u_id=null){
		$value = get_post_meta($point_criteria_id,'value',true);
		global $wpdb;
		$x = $wpdb->get_results($wpdb->prepare("SELECT MAX(meta_value) AS max, meta_key as user FROM {$wpdb->postsmeta} WHERE post_id = %d AND meta_key REGEXP '[0-9]+' AND meta_value REGEXP '[0-9]+'",$id),ARRAY_A);

		if(is_numeric($value) && $user_id ==  $x['user']){
			$mycred = mycred();	
			$mycred->update_users_balance( $user_id, $value);
			$total_usage++;
			$user_specific_usage++;
			update_post_meta($point_criteria_id,'total',$total_usage);
			update_user_meta($user_id,$point_criteria_id,$user_specific_usage);
			$this->record(array(
				'user_id'=>$user_id,
				'id'=>$id,
				'amount' => $value,
				'module'=>$module,
				'logentry'=> sprintf(__('Student %s gained %s points for getting highest score in module "%s" from criteria "%s" ','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id)),
				'message'=> sprintf(__('Student %s gained %s points for getting highest score in module "%s" from criteria "%s" ','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id))
				));
		}
	}
	function lowest_score($point_criteria_id,$id,$info=NULL,$user_id,$module,$total_usage,$user_specific_usage,$another_u_id=null){
		$value = get_post_meta($point_criteria_id,'value',true);
		global $wpdb;
		$x = $wpdb->get_results($wpdb->prepare("SELECT MIN(meta_value) AS max, meta_key as user FROM {$wpdb->postsmeta} WHERE post_id = %d AND meta_key REGEXP '[0-9]+' AND meta_value REGEXP '[0-9]+'",$id),ARRAY_A);

		if(is_numeric($value) && $user_id ==  $x['user']){
			$mycred = mycred();	
			$mycred->update_users_balance( $user_id, $value);
			$total_usage++;
			$user_specific_usage++;
			update_post_meta($point_criteria_id,'total',$total_usage);
			update_user_meta($user_id,$point_criteria_id,$user_specific_usage);
			$this->record(array(
				'user_id'=>$user_id,
				'id'=>$id,
				'amount' => $value,
				'module'=>$module,
				'logentry'=> sprintf(__('Student %s gained %s points for getting lowest score in module "%s" from criteria "%s" ','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id)),
				'message'=> sprintf(__('Student %s gained %s points for getting lowest score in module "%s" from criteria "%s" ','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id))
				));
		}
	}
	function badges_earned($point_criteria_id,$id,$info=NULL,$user_id,$module,$total_usage,$user_specific_usage,$another_u_id=null){
		$value = get_post_meta($point_criteria_id,'value',true);
		if(is_array($info)){
			$count = count($info);
			$module_score = get_post_meta($point_criteria_id,'wplms_module_score',true);
			if(is_numeric($value) && $count >= $module_score){
				$mycred = mycred();	
				$mycred->update_users_balance( $user_id, $value);
				$total_usage++;
				$user_specific_usage++;
				update_post_meta($point_criteria_id,'total',$total_usage);
				update_user_meta($user_id,$point_criteria_id,$user_specific_usage);
				$this->record(array(
				'user_id'=>$user_id,
				'id'=>$id,
				'amount' => $value,
				'module'=>$module,
				'logentry'=> sprintf(__('Student %s gained %s points for earning a Badge in module "%s" from criteria "%s" ','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id)),
				'message'=> sprintf(__('Student %s gained %s points for earning a Badge in module "%s" from criteria "%s" ','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id))
				));
			}
		}
	}
	function certificates_earned($point_criteria_id,$id,$info=NULL,$user_id,$module,$total_usage,$user_specific_usage,$another_u_id=null){
		$value = get_post_meta($point_criteria_id,'value',true);
		
		if(is_array($info)){
			$count = count($info);
			$module_score = get_post_meta($point_criteria_id,'wplms_module_score',true);
			if(is_numeric($value) && $count >= $module_score){
				$mycred = mycred();	
				$mycred->update_users_balance( $user_id, $value);
				$total_usage++;
				$user_specific_usage++;
				update_post_meta($point_criteria_id,'total',$total_usage);
				update_user_meta($user_id,$point_criteria_id,$user_specific_usage);
				$this->record(array(
				'user_id'=>$user_id,
				'id'=>$id,
				'amount' => $value,
				'module'=>$module,
				'logentry'=> sprintf(__('Student %s gained %s points for earning a Certificate in module "%s" from criteria "%s" ','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id)),
				'message'=> sprintf(__('Student %s gained %s points for earning a Certificate in module "%s" from criteria "%s" ','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id))
				));
			}
		}
	}

	function record($args=array()){
		$defaults =array(
			'action' => 'mycred_add',
			'user_id'=>get_current_user_id(),
			'module'=>'course',
			'amount'=>0,
			'logentry'=>__('Started Course','wplms-mycred'),
			'id'=>0,
			'message'=>__('Student Started course','wplms-mycred')
			);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$mycred = mycred();
		$mycred->add_to_log($action,
			$user_id,
			$amount,
			$logentry,
			$id,
			$message);

		$bp_args= array(
			'user_id' => $user_id,
			'action' => $action,
			'content' => $message,
			'primary_link' => get_permalink($id),
			'component' => $module,
			'item_id' => $id
		);
		bp_course_record_activity($bp_args);
	}
	function wplms_mycred_register_actions(){
		global $bp;
		$bp_course_action_desc=array(
			'mycred_add' => __( 'Add MyCred credits', 'vibe' ),
			);
		foreach($bp_course_action_desc as $key => $value){
			bp_activity_set_action($bp->activity->id,$key,$value);	
		}
	}
}

WPLMS_Points_Awarding_System::init();
