<?php
/**
 * PRofile
 *
 * @class       Vibe_Mycred_Profile
 * @author      VibeThemes
 * @category    Admin
 * @package     vibekb
 * @version     1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



class Vibe_Mycred_Profile{


	public static $instance;
	public static function init(){

        if ( is_null( self::$instance ) )
            self::$instance = new Vibe_Mycred_Profile();
        return self::$instance;
    }

	private function __construct(){
		add_action( 'bp_setup_nav', array($this,'add_mycred_tab'), 101 );
		add_action('wp_enqueue_scripts',array($this,'enqueue_script'));
        add_filter('vibebp_component_icon',array($this,'set_icon'),10,2);
        
        add_filter('wplms_course_credits_array',array($this,'wplms_course_credits_array'),9,2);

        add_filter('wplms_course_product_id',array($this,'course_mycred_price_check'),10,2);
        add_action('wp_footer',array($this,'course_mycred_non_loggedin'),10,2);
	}

	function course_mycred_non_loggedin(){
		if(is_user_logged_in())
			return;
		?>
		<script>
			 document.addEventListener('DOMContentLoaded',function(){
              if(document.querySelectorAll('.course_button')){
                document.querySelectorAll('.course_button').forEach(function(el){
                  if(el.querySelector('a')){
                    el.querySelector('a').addEventListener('click',function(event){
                    	if(el.querySelector('a').getAttribute('href').includes('mycredpoints')){
                    		if(window.wplms_course_data.hasOwnProperty('login_popup') && window.wplms_course_data.login_popup){
                        
		                        let user = sessionStorage.getItem('bp_user');
		                        if (typeof user!=='undefined' && user){
		                        }else{
		                          event.preventDefault();
		                          const nevent = new Event('vibebp_show_login_popup');
		                          document.dispatchEvent(nevent);
		                        }
		                      }else{
		                       
		                          event.preventDefault();
		                          const nevent = new Event('vibebp_show_login_popup');
		                          document.dispatchEvent(nevent);
		                        
		                      }
                    	}
                      
                      
                    });
                  }
                });
              }
              
            });
		</script>
		<?php
	}

	function course_mycred_price_check($link,$course_id){
		$points=get_post_meta($course_id,'vibe_mycred_points',true);
		if(isset($points) && is_numeric($points)){
			$link = '#mycredpoints';
		}
		return $link;
	}

	function wplms_course_credits_array($price_html,$course_id){

		if(class_exists('wplms_points_init')){
			$init = wplms_points_init::init();
			remove_filter('wplms_course_credits_array',array($init,'wplms_course_credits_array'),10,2);
		}

		$points=get_post_meta($course_id,'vibe_mycred_points',true);
		if(isset($points) && is_numeric($points)){
			$mycred = mycred();
			$points_html ='<strong>'.$mycred->format_creds($points);
			$subscription = get_post_meta($course_id,'vibe_mycred_subscription',true);
			if(isset($subscription) && $subscription && $subscription !='H'){
				$duration = get_post_meta($course_id,'vibe_mycred_duration',true);
				$duration_parameter = get_post_meta($course_id,'vibe_mycred_duration_parameter',true);
				$duration = $duration*$duration_parameter;

				if(function_exists('tofriendlytime'))
					$points_html .= ' <span class="subs"> '.__('per','wplms-mycred').' '.tofriendlytime($duration).'</span>';
			}
			$key = '#mycredpoints';
			$points_html .='</strong>';
			
			$price_html[$key]=$points_html;
		}
		return $price_html;
	}

    function set_icon($icon,$component_name){

        if($component_name == 'points' ){
            return 'vicon-star';
        }
        return $icon;
    }
	function add_mycred_tab(){
		global $bp;
		$slug='points';
		//Add VibeDrive tab in profile menu
	    bp_core_new_nav_item( array( 
	        'name' => __('Points','wplms-mycred'),
	        'slug' => $slug, 
	        'item_css_id' => 'points',
	        'screen_function' => array($this,'show_screen'),
	        'default_subnav_slug' => 'home', 
	        'position' => 55,
	        'user_has_access' => (bp_is_my_profile() || current_user_can('manage_options'))
	    ) );

		bp_core_new_subnav_item( array(
			'name' 		  => __('Log','wplms-mycred'),
			'slug' 		  => 'log',
			'parent_slug' => $slug,
        	'parent_url' => $bp->displayed_user->domain.$slug.'/',
			'screen_function' => array($this,'show_screen'),
			'user_has_access' => (bp_is_my_profile() || current_user_can('manage_options'))
		) );

		
	}

	function show_screen(){

	}

	function enqueue_script(){
		if(!function_exists('mycred')){
			return ;
			
		}
		$mycred = mycred();
		$blog_id = '';
        if(function_exists('get_current_blog_id')){
            $blog_id = get_current_blog_id();
        }

            
		$kb=apply_filters('vibe_mycred_script_args',array(
			'api_url'=> get_rest_url($blog_id,WPLMS_API_NAMESPACE).'/mycred',
            'settings'=>array(
            	
            ),
            
            'timestamp'=>time(),
            'per_page'=>apply_filters('wplms_mycred_log_api_number',20),
            'date_format'=>get_option('date_format'),
            'time_format'=>get_option('time_format'),
            'translations'=>array(
                'date'=>_x('Date','api call','wplms-mycred'),
                'course'=>_x('Course','api call','wplms-mycred'),
                'student'=>_x('Student','api call','wplms-mycred'),
                'select_date'=>_x('Select a date','api call','wplms-mycred'),
                'start_date'=>_x('Select start date','api call','wplms-mycred'),
                'points' => _x('Points','','wplms-mycred'),
                'entry'=> _x('Entry','','wplms-mycred'),
                'points_label' => $mycred->plural(),
                'load_more' =>  _x('Load More','','wplms-mycred'),
                'no_records' => _x('No Records found!','','wplms-mycred'),
                'buy_with_points' => _x('Buy with Points','','wplms-mycred')
            ),
        ));
        $enqueue_script = false;
        if(function_exists('bp_is_user') && bp_is_user()){
            $enqueue_script = true;
        }
        
        if(is_singular('course') ||  apply_filters('vibebp_enqueue_profile_script',$enqueue_script)){
            wp_enqueue_script('vibe-mycred',plugins_url('../assets/js/mycred.js',__FILE__),array('wp-element','wp-data',),WPLMS_MYCRED_PLUGIN_VERSION);
            wp_localize_script('vibe-mycred','vibe_mycred',$kb);
           /* wp_enqueue_style('vibe-mycred',plugins_url('../assets/css/mycred.css',__FILE__),array(),WPLMS_MYCRED_PLUGIN_VERSION);*/
        }
	}
}
Vibe_Mycred_Profile::init();