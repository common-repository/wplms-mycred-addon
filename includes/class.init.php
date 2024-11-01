<?php
/*
FILE : class.init.php
DESC : Initilize MyCred Add on and hooks
*/

if ( !defined( 'ABSPATH' ) ) exit;

class wplms_points_init {
	
	public $version;
	
	public $subscription_duration_parameter = 86400;

	public static $instance;
	public static function init(){

        if ( is_null( self::$instance ) )
            self::$instance = new wplms_points_init();
        return self::$instance;
    }

	function __construct(){
		
		//add_filter( 'mycred_label', 'mycred_pro_relable_mycred' );
		add_filter('mycred_setup_addons',array($this,'wplms_mycred_setup_addons'));
		add_filter('wplms_course_product_metabox',array($this,'wplms_mycred_custom_metabox'));
		
		add_filter('wplms_course_credits_array',array($this,'wplms_course_credits_array'),999,2);
		add_action('wplms_header_top_login',array($this,'wplms_mycred_show_points'));
		add_filter('wplms_course_product_id',array($this,'wplms_mycred_take_this_course_label'),9);
		add_action('wplms_course_before_front_main',array($this,'wplms_error_message_handle'));
		add_action('wp_ajax_use_mycred_points',array($this,'use_mycred_points'));
		add_action('wp_ajax_nopriv_use_mycred_points',array($this,'use_mycred_points'));
		add_action('wp_print_styles',array($this,'add_styles'));
		add_action('wplms_front_end_pricing_content',array($this,'wplms_front_end_pricing'),10,1);
		add_Action('wplms_course_pricing_save',array($this,'save_pricing'),10,2);
		add_action('lms_general_settings',array($this,'add_buy_points_setting'));
		
		add_filter('wplms_mycred_metabox',array($this,'front_end'));
		add_action('wp_ajax_no_priv_wplms_mycred_assign_course',array($this,'wplms_mycred_assign_course'));
	}

	

	function mycred_pro_relable_mycred() {
		return __('Points','wplms-mycred');
	}
	function add_styles(){
		wp_enqueue_style('wplms_mycred',plugins_url('../assets/wplms-mycred-addon.css',__FILE__),true);
		wp_enqueue_script('wplms_mycred',plugins_url('../assets/wplms-mycred-addon.js',__FILE__),true);
	}
	function wplms_mycred_take_this_course_label($x){
		global $post;
		if(empty($post))
			return $x;
		$points_required = get_post_meta($post->ID,'vibe_mycred_points',true);
		if(isset($points_required) && is_numeric($points_required)){
			$user_id = get_current_user_id();
			$mycred = mycred();
			$balance = $mycred->get_users_cred( $user_id );
			if($points_required <= $balance){
				echo '<script>jQuery(document).ready(function($){

					$( "body" ).delegate( ".course_button[href=\'#hasmycredpoints\']", "click", function(event){
						event.preventDefault();
						
						if($(this).hasClass("loader"))
							return;

						$(this).addClass("loader");
						$.ajax({
		                    type: "POST",
		                    url: ajaxurl,
		                    data: { action: "use_mycred_points", 
		                            security: $("#wplms_mycred_security").val(),
		                            id: '.$post->ID.'
		                          },
		                    cache: false,
		                    success: function (html) {
		                        $(this).removeClass("loader");
		                        $(this).html(html);
		                        setTimeout(function(){location.reload();}, 2000);
		                    }
		            });
						return false;
					});
				});</script>
				'.wp_nonce_field('security'.$user_id,'wplms_mycred_security').'
				';
				return '#hasmycredpoints';
			}else{	
				if(is_numeric($x)){
					if ( FALSE === get_post_status( $x ) ) {
					  return '?error=insufficient';
					} else {
					  return $x;
					}
					return $x;
				}else{
					return '?error=insufficient';
				}
			}
		}else{
			return $x;
		}
	}
	function wplms_error_message_handle(){
	  global $post;
	  if(isset($_REQUEST['error'])){ 
	  switch($_REQUEST['error']){
	    case 'insufficient':
	      echo '<div id="message" class="notice"><p>'.__('Purchase points to take this course','wplms-mycred').' : <a href="'.$this->wplms_get_mycred_purchase_points().'">'.__('Add Points','wplms-mycred').'</a></p></div>';
	    break;
	    }
	  }
	}
	function wplms_mycred_setup_addons($installed){
		if ( isset( $_GET['addon_action'] ) && isset( $_GET['addon_id'] ) && $_GET['addon_id'] == 'wplms' && $_GET['addon_action'] == 'activate'){
			$mycred_addons=get_option('mycred_pref_addons');
			
			if(!isset($mycred_addons['installed']['wplms']))
				delete_option('mycred_pref_addons');
		}
		// Transfer Add-on
		$installed['wplms'] = array(
			'name'        => 'WPLMS',
			'description' => __( 'MyCred points options for WPLMS Learning Management', 'wplms-mycred' ),
			'addon_url'   => 'http://github.com/vibethemes/wplms-mycred-addon',
			'version'     => '1.0',
			'author'      => 'VibeThemes',
			'author_url'  => 'http://www.vibethemes.com',
			'path'        => realpath(dirname(__FILE__)). 'myCRED-addon-wplms.php'
		);

		return $installed;
	}


	function wplms_mycred_custom_metabox($metabox){
		$prefix = 'vibe_';
		if(function_exists('calculate_duration_time')){
			$parameter = calculate_duration_time($this->subscription_duration_parameter);
		}else{
			$parameter = __('DAYS','wplms-mycred');
		}

		$mycred_metabox = apply_filters('wplms_mycred_metabox',array(  
			$prefix.'mycred_points' => array( // Text Input
				'label'	=> __('MyCred Points','wplms-mycred'), // <label>
				'desc'	=> __('MyCred Points required to take this course.','wplms-mycred'),
				'id'	=> $prefix.'mycred_points', // field id and name
				'type'	=> 'number' // type of field
			),
		    $prefix.'mycred_subscription' => array( // Text Input
				'label'	=> __('MyCred Subscription ','wplms-mycred'), // <label>
				'desc'	=> __('Enable subscription mode for this Course','wplms-mycred'), // description
				'id'	=> $prefix.'mycred_subscription', // field id and name
				'type'	=> 'showhide', // type of field
		        'options' => array(
		          array('value' => 'H',
		                'label' =>__('Hide','wplms-mycred')),
		          array('value' => 'S',
		                'label' =>__('Show','wplms-mycred')),
		        ),
		                'std'   => 'H'
			),
		     $prefix.'mycred_duration' => array( // Text Input
				'label'	=> __('Subscription Duration','wplms-mycred'), // <label>
				'desc'	=> __('Duration for Subscription Products (in ','wplms-mycred').$parameter.')', // description
				'id'	=> $prefix.'mycred_duration', // field id and name
				'type'	=> 'number' // type of field
			),
		     $prefix.'mycred_duration_parameter'=>array( // Text Input
				'label'	=> __('Points duration parameter','wplms-mycred'), // <label>
				'desc'	=> __('Subscription duration parameter','wplms-mycred'), // description
				'id'	=> $prefix.'mycred_duration_parameter', // field id and name
				'type'	=> 'duration', // type of field
				'std'	=> $parameter
			),
		));
		
		$metabox = array_merge($metabox,$mycred_metabox);
		return $metabox;
	}

	function front_end($settings){
		if(!is_admin()){
			$settings['vibe_mycred_subscription']['type'] = 'conditionalswitch';
			$settings['vibe_mycred_subscription']['from']= 'meta';
			$settings['vibe_mycred_subscription']['default']='H';
			$settings['vibe_mycred_subscription']['hide_nodes'] = array('vibe_mycred_duration','vibe_mycred_duration_parameter');
			$settings['vibe_mycred_subscription']['options'] = array('H'=>__('DISABLE','wplms-mycred' ),'S'=>__('ENABLE','wplms-mycred' ));	
		}
		return $settings;
	}


	function wplms_course_credits_array($price_html,$course_id){
		
		$points=get_post_meta($course_id,'vibe_mycred_points',true);
		if(isset($points) && is_numeric($points)){
			
			$mycred = mycred();
			$points_html ='<strong class="wplms_mycred_assign_course" data-course="'.$course_id.'">'.$mycred->format_creds($points);
			$subscription = get_post_meta($course_id,'vibe_mycred_subscription',true);
			if(isset($subscription) && $subscription && $subscription !='H'){
				$duration = get_post_meta($course_id,'vibe_mycred_duration',true);
				$duration_parameter = get_post_meta($course_id,'vibe_mycred_duration_parameter',true);
				$duration = $duration*$duration_parameter;

				if(function_exists('tofriendlytime'))
					$points_html .= ' <span class="subs"> '.__('per','wplms-mycred').' '.tofriendlytime($duration).'</span>';
			}
			
			$points_html .='</strong>';

			$key = '#mycredpoints';
			if(is_user_logged_in()){
				$user_id = get_current_user_id();
				$balance = $mycred->get_users_cred( $user_id );
				if($balance < $points){
					$key = '?error=insufficient';
				}
			}else{
				$key = '?error=login';
			}
			if(function_exists('is_wplms_4_0') && is_wplms_4_0()){
				$key = '#mycredpoints';
				//add_action('wp_footer',array($this,'assign_course'));	
			}

			$price_html[$key]=$points_html;
		}
		return $price_html;
	}

	function assign_course(){

		?>
			<script>
			document.addEventListener('userLoaded',listenMyCredButton,false);
			function listenMyCredButton(){
				
		        document.querySelectorAll('.the_course_button .course_button>a').forEach(function(el){
		        	console.log('#');
		        	if(el.getAttribute('href').indexOf('#mycredpoints') > -1){

			        	el.addEventListener('click',function(e){
			        		e.preventDefault();
			        		
			        		let course_id = el.querySelector('.wplms_mycred_assign_course').getAttribute('data-course');
			        		
		        			
					        localforage.getItem('bp_login_token').then(function(token){
					        	if(token){
					        		var xhr = new XMLHttpRequest();
									xhr.open('POST', ajaxurl);
							        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
							        xhr.onload = function() {
							            if (xhr.status === 200) {
							                let data = xhr.responseText;
							                var postevent = new CustomEvent('reload_course_button', { "detail":{course_id}});
        									document.dispatchEvent(postevent);
						              	}
							        };
				        			xhr.send(encodeURI('action=use_mycred_points&id='+course_id+'&token='+token));	
					        	}
					        });
			        	});

			        	document.removeEventListener('userLoaded',listenMyCredButton, false );
			        }
		    	});
			}
		</script>
		<?php
	}



	function wplms_mycred_show_points(){
		echo '<li><a href="'.$this->wplms_get_mycred_link().'"><strong>'.$this->get_wplms_mycred_points().'</strong></a></li>';
	}

	function wplms_get_mycred_link(){
		$mycred = get_option('mycred_pref_core');

		if(isset($mycred['buddypress']) && isset($mycred['buddypress']['history_url']) && isset($mycred['buddypress']['history_location']) && $mycred['buddypress']['history_location']){
			$link=bp_get_loggedin_user_link().$mycred['buddypress']['history_url'];
		}else{
			$link='#';
		}
		return $link;
	}
	function add_buy_points_setting($settings){
		// Create LMS settings switches
        $settings[] = array(
            'label'=>__('WPLMS Mycred Settings','wplms-mycred' ),
            'type'=> 'heading',
        );
		$settings[] = array(
				'label' => __('Buy Points Link','wplms-mycred'),
				'name' =>'mycred_buy_points',
				'type' => 'textbox',
				'desc' => __('Buy Points for MyCred, displayed when user points are less than required','wplms-mycred')
			);
		return $settings;
	}
	function wplms_get_mycred_purchase_points(){
		$settings = get_option('lms_settings');
		if(!empty($settings['general']['mycred_buy_points'])){
			$link = $settings['general']['mycred_buy_points'];
		}else
			$link='#';
			
		return $link;
	}
	function get_wplms_mycred_points() {
		if ( is_user_logged_in() && class_exists( 'myCRED_Core' ) ) {
			$mycred = mycred();
			$balance = $mycred->get_users_cred( get_current_user_id() );
			return $mycred->format_creds( $balance );
		}
	}

	function use_mycred_points(){
		
		$course_id = $_POST['id'];
		if ( (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'],'security'.$user_id)) && empty($_POST['token'])){
		     _e('Security check Failed.','wplms-mycred');
		     die();
		}	

		$user_id=get_current_user_id();

		if(!empty($_POST['token']) && function_exists('vibebp_expand_token')){
			$data = vibebp_expand_token($_POST['token']);
			if($data['status']){
				$user_id = $data['data']->data->user->id;
			}
		}

		if(!is_numeric($user_id)){
			 _e('Incorrect user','wplms-mycred');
		     die();
		}
		if(!is_numeric($course_id) || get_post_type($course_id) != 'course'){
			 _e('Incorrect Course','wplms-mycred');
		     die();
		}

		$points = get_post_meta($course_id,'vibe_mycred_points',true);

		if(!is_numeric($points) || !$points){
			 _e('No points set','wplms-mycred');
		     die();
		}
		$mycred = mycred();
		$balance = $mycred->get_users_cred( $user_id );

		if($balance < $points){
			_e('Not enough balance','wplms-mycred');
		     die();
		}
		$deduct = -1*$points;

		$subscription = get_post_meta($course_id,'vibe_mycred_subscription',true);
		if(isset($subscription) && $subscription && $subscription !='H'){

			$duration = get_post_meta($course_id,'vibe_mycred_duration',true);

		    $mycred_duration_parameter = get_post_meta($course_id,'vibe_mycred_duration_parameter',true);
		    if(empty($mycred_duration_parameter)){
		    	$mycred_duration_parameter = 86400;
		    }
		    $duration = $duration*$mycred_duration_parameter;
		    bp_course_add_user_to_course($user_id,$course_id,$duration);
		    
		}else{
			bp_course_add_user_to_course($user_id,$course_id);
		}	

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
      	if(isset($instructor_commission) && $instructor_commission == 0)
      		return;

      	if(!isset($instructor_commission))
	      $instructor_commission = 70;

	  	
	    $commissions = get_option('instructor_commissions');
	    if(isset($commissions) && is_array($commissions)){
	    } // End Commissions_array 

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
		


        do_action('wplms_course_mycred_points_puchased',$course_id,$user_id,$points);

        echo __('Course Assigned','wplms-mycred');
        die();
	}

	function wplms_front_end_pricing($course_id){

		if(isset($course_id) && $course_id){
			$vibe_mycred_points = get_post_meta($course_id,'vibe_mycred_points',true);
			$vibe_mycred_subscription = get_post_meta($course_id,'vibe_mycred_subscription',true);
			$vibe_mycred_duration = get_post_meta($course_id,'vibe_mycred_duration',true);	
		}else{
			$vibe_mycred_points=0;
			$vibe_mycred_subscription = 'H';
			$vibe_mycred_duration = 0;
		}
		


		echo '<li class="course_product" data-help-tag="19">
                <h3>'.__('Set Course Points','wplms-mycred').'<span>
                 <input type="text" id="vibe_mycred_points" class="small_box right" value="'.$vibe_mycred_points.'" /></span></h3>
            </li>
            <li class="course_product" >
                <h3>'.__('Subscription Type','wplms-mycred').'<span>
                    <div class="switch mycred-subscription">
                            <input type="radio" class="switch-input vibe_mycred_subscription" name="vibe_mycred_subscription" value="H" id="disable_cred_sub" '; checked($vibe_mycred_subscription,'H'); echo '>
                            <label for="disable_cred_sub" class="switch-label switch-label-off">'.__('Full Course','wplms-mycred').'</label>
                            <input type="radio" class="switch-input vibe_mycred_subscription" name="vibe_mycred_subscription" value="S" id="enable_cred_sub" '; checked($vibe_mycred_subscription,'S'); echo '>
                            <label for="enable_cred_sub" class="switch-label switch-label-on">'.__('Subscription','wplms-mycred').'</label>
                            <span class="switch-selection"></span>
                          </div>
                </span></h3>
            </li>
            <li class="credsubscription course_product" '.(($vibe_mycred_subscription == 'S')?'style="display:block;"':'style="display:none;"').'>
                <h3>'.__('Set Subscription','wplms-mycred').'<span>
                <input type="text" id="vibe_mycred_duration" class="small_box" value="'.$vibe_mycred_duration.'" /> '.calculate_duration_time($this->subscription_duration_parameter).'</span></h3>
            </li>
            ';
	}

	function custom_hook_quiz_retake(){
		$quiz_id= $_POST['quiz_id'];
	    if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'],'security') || !is_numeric($quiz_id)){
	       die();
	    }

	    $count_retakes = $wpdb->get_var($wpdb->prepare( "
										SELECT count(activity.content) FROM {$table_name} AS activity
										WHERE 	activity.component 	= 'course'
										AND 	activity.type 	= 'retake_quiz'
										AND 	user_id = %d
										AND 	item_id = %d
										ORDER BY date_recorded DESC
									" ,$user_id,$quiz_id));

	    do_action('mycred_quiz_retakes',$quiz_id,$count_retakes);
		die();
	}

	function save_pricing($course_id,$pricing){
		
        if(isset($pricing->vibe_mycred_points) && is_numeric($pricing->vibe_mycred_points)){
            update_post_meta($course_id,'vibe_mycred_points',$pricing->vibe_mycred_points);
            update_post_meta($course_id,'vibe_mycred_subscription',$pricing->vibe_mycred_subscription);
            update_post_meta($course_id,'vibe_mycred_duration',$pricing->vibe_mycred_duration);
            do_action('wplms_course_pricing_mycred_updated',$course_id,$pricing->vibe_mycred_points,$pricing->vibe_mycred_subscription,$pricing->vibe_mycred_duration);
        }else{
        	delete_post_meta($course_id,'vibe_mycred_points');
        }
	}
}

wplms_points_init::init();