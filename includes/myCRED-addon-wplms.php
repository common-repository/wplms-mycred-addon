<?php
/**
 * Addon: wplms
 * Addon URI: http://github.com/vibethemes/wplms-mycred-addon
 * Version: 1.0
 * Description: Setup MyCred for wplmss
 * Author: VibeThemes
 * Author URI: http://www.vibethemes.com
 */
if ( ! defined( 'myCRED_VERSION' ) ) exit;

define( 'myCRED_WPLMS',         __FILE__ );
define( 'myCRED_WPLMS_DIR',     realpath(dirname(__FILE__)) );

//include_once( myCRED_wplms_DIR . 'includes/mycred-wplms-functions.php' );
//include_once( myCRED_wplms_DIR . 'includes/mycred-wplms-shortcodes.php' );

/**
 * myCRED_wplms_Module class
 * @since 1.4
 * @version 1.0
 */
if ( ! class_exists( 'myCRED_Wplms_Module' ) ) {
	class myCRED_Wplms_Module extends myCRED_Module {

		public $instances = array();

		/**
		 * Construct
		 */
		function __construct( $type = MYCRED_DEFAULT_TYPE_KEY ) {

			parent::__construct( 'myCRED_Wplms_Module', array(
				'module_name' => 'wplms',
				'defaults'    => array(
					'log'         => 'WPLMS Activity',
					'invalid'     => 'This is not a valid wplms',
					'expired'     => 'This wplms has expired',
					'user_limit'  => 'You have already used this wplms',
					'min'         => 'A minimum of %min% is required to use this wplms',
					'max'         => 'A maximum of %max% is required to use this wplms',
					'success'     => 'wplms successfully deposited into your account'
				),
				'register'    => false,
				'add_to_core' => false
			),$type );

		}

		/**
		 * Module Pre Init
		 * @since 1.0
		 * @version 1.0
		 */
		public function module_pre_init() {

			add_filter( 'mycred_parse_log_entry_wplms', array( $this, 'parse_log_entry' ), 10, 2 );
			add_filter('mycred_all_references',array($this,'wplms_references'));

		}
		/**
		 * Hook into Init
		 * @since 1.4
		 * @version 1.0
		 */
		function wplms_references($references){
			$references['wplms_course_start']  = __( 'WPLMS : Started Course', 'mycred' );
			return $references;
		}
		/**
		 * Hook into Init
		 * @since 1.4
		 * @version 1.0
		 */
		public function module_init() {
			$mycred_pref_addons = get_option('mycred_pref_addons');
			
			if(is_array($mycred_pref_addons) && is_array($mycred_pref_addons['active'])){
				if(in_array('wplms',$mycred_pref_addons['active']))
					$this->register_post_type();
			}

			add_action( 'mycred_add_menu',   array( $this, 'add_to_menu' ), $this->menu_pos );
		}

		/**
		 * Add Admin Menu Item
		 * @since 1.7
		 * @version 1.0
		 */
		public function add_to_menu() {

			add_submenu_page(
				'mycred',
				__( 'WPLMS Points', 'wplms-mycred' ),
				__( 'WPLMS Points', 'wplms-mycred' ),
				$this->core->edit_creds_cap(),
				'edit.php?post_type=mycred_wplms'
			);

		}

		/**
		 * Hook into Admin Init
		 * @since 1.4
		 * @version 1.0
		 */
		public function module_admin_init() {
			add_filter( 'post_updated_messages', array( $this, 'update_messages' ) );

			add_filter( 'manage_mycred_wplms_posts_columns',       array( $this, 'adjust_column_headers' ) );
			add_action( 'manage_mycred_wplms_posts_custom_column', array( $this, 'adjust_column_content' ), 10, 2 );

			add_filter( 'enter_title_here', array( $this, 'enter_title_here' )      );
			add_filter( 'post_row_actions', array( $this, 'adjust_row_actions' ), 10, 2 );
			add_action( 'add_meta_boxes',   array( $this, 'add_meta_boxes' ) );
			add_action( 'save_post',        array( $this, 'update_wplms_details' ) );
		}

		/**
		 * Register wplms Post Type
		 * @since 1.4
		 * @version 1.0
		 */
		protected function register_post_type() {
			$labels = array(
				'name'               => __( 'WPLMS Points', 'wplms-mycred' ),
				'singular_name'      => __( 'WPLMS Point', 'wplms-mycred' ),
				'add_new'            => __( 'Create New', 'wplms-mycred' ),
				'add_new_item'       => __( 'Create New Points Awarding Criteria', 'wplms-mycred' ),
				'edit_item'          => __( 'Edit Point', 'wplms-mycred' ),
				'new_item'           => __( 'New Point', 'wplms-mycred' ),
				'all_items'          => __( 'WPLMS Points', 'wplms-mycred' ),
				'view_item'          => '',
				'search_items'       => __( 'Search wplms points', 'wplms-mycred' ),
				'not_found'          => __( 'No wplms points found', 'wplms-mycred' ),
				'not_found_in_trash' => __( 'No wplms point found in Trash', 'wplms-mycred' ), 
				'parent_item_colon'  => '',
				'menu_name'          => __( 'WPLMS Points', 'wplms-mycred' )
			);
			$args = array(
				'labels'             => $labels,
				'publicly_queryable' => false,
				'show_ui'            => true, 
				'show_in_menu'       => 'myCRED',
				'capability_type'    => 'page',
				'supports'           => array( 'title' )
			);
			register_post_type( 'mycred_wplms', apply_filters( 'mycred_register_wplms', $args ) );
		}

		/**
		 * Adjust Update Messages
		 * @since 1.4
		 * @version 1.0
		 */
		public function update_messages( $messages ) {
			$messages['mycred_wplms'] = array(
				0  => '',
				1  => __( 'Points updated.', 'wplms-mycred' ),
				2  => '',
				3  => '',
				4  => __( 'Points updated.', 'wplms-mycred' ),
				5  => false,
				6  => __( 'WPLMS Point published.', 'wplms-mycred' ),
				7  => __( 'Point saved.', 'wplms-mycred' ),
				8  => '',
				9  => '',
				10 => __( 'Draft Point saved.', 'wplms-mycred' ),
			);

  			return $messages;
		}

		/**
		 * Adjust Enter Title Here
		 * @since 1.4
		 * @version 1.0
		 */
		public function enter_title_here( $title ) {
			global $post_type;
			if ( $post_type == 'mycred_wplms' )
				return __( 'WPLMS Points', 'wplms-mycred' );

			return $title;
		}

		/**
		 * Adjust Column Header
		 * @since 1.4
		 * @version 1.0
		 */
		public function adjust_column_headers( $columns ) {
			$columns['points']   = __( 'Points', 'wplms-mycred' );
			$columns['awarded']   = __( '# Times Awarded', 'wplms-mycred' );
			$columns['criteria'] = __( 'Criteria', 'wplms-mycred' );

			return $columns;
		}
		public function mycred_get_wplms_points($post_id){
			$value = get_post_meta( $post_id, 'value', true );
			if(!isset($value) || !$value)
				$value=0;

			return $value;
		}
		public function mycred_get_global_wplms_count($post_id){
			$used= get_post_meta( $post_id, 'total', true );
			
			if(!isset($used) || !$used)
				$used=0;

			return $used;
		}
		public function  mycred_get_global_wplms_points_criteria($post_id){
			$wplms_module_score=get_post_meta( $post_id, 'wplms_module', true );
			$wplms_module_score_operator= get_post_meta( $post_id, 'wplms_module_score_operator', true );
			return $wplms_module_score_operator.' '.$wplms_module_score;
		}
		/**
		 * Adjust Column Body
		 * @since 1.4
		 * @version 1.0
		 */
		public function adjust_column_content( $column_name, $post_id ) {
			global $mycred;

			switch ( $column_name ) {

				case 'points' :

					$value = $this->mycred_get_wplms_points( $post_id );
					if ( empty( $value ) ) $value = 0;
					
					echo $mycred->format_creds( $value );

				break;

				case 'awarded' :

					$count = $this->mycred_get_global_wplms_count( $post_id );
					if ( empty( $count ) )
						_e( 'not yet awarded', 'wplms-mycred' );
					else
						echo $count;

				break;

				case 'criteria' :

					$criteria = $this->mycred_get_global_wplms_points_criteria( $post_id );
					if ( empty( $criteria ) )
						_e( 'Not defined', 'wplms-mycred' );
					else
						echo $criteria;

				break;

			}
		}

		/**
		 * Adjust Row Actions
		 * @since 1.4
		 * @version 1.0
		 */
		public function adjust_row_actions( $actions, $post ) {
			if ( $post->post_type == 'mycred_wplms' ) {
				unset( $actions['inline hide-if-no-js'] );
				unset( $actions['view'] );
			}
			return $actions;
		}

		/**
		 * Parse Log Entries
		 * @since 1.4
		 * @version 1.0
		 */
		public function parse_log_entry( $content, $log_entry ) {
			return str_replace( '%wplms%', $log_entry->data, $content );
		}

		/**
		 * Add Meta Boxes
		 * @since 1.4
		 * @version 1.0
		 */
		public function add_meta_boxes() {

			add_meta_box(
				'mycred_wplms_setup',
				__( 'WPLMS Points Setup', 'wplms-mycred' ),
				array( $this, 'metabox_wplms_setup' ),
				'mycred_wplms',
				'normal',
				'core'
			);

			add_meta_box(
				'mycred_wplms_limits',
				__( 'Criteria Usage Limits', 'wplms-mycred' ),
				array( $this, 'metabox_wplms_limits' ),
				'mycred_wplms',
				'normal',
				'core'
			);

			add_meta_box(
				'mycred_wplms_requirements',
				__( 'Points Awarding Criteria', 'wplms-mycred' ),
				array( $this, 'mycred_wplms_requirements' ),
				'mycred_wplms',
				'normal',
				'core'
			);

		}

		/**
		 * Metabox: wplms Setup
		 * @since 1.4
		 * @version 1.0
		 */
		public function metabox_wplms_setup( $post ) {
			global $mycred;

wp_enqueue_script( 'jquery-ui-datepicker', array( 'jquery', 'jquery-ui-core' ) );
			$value = get_post_meta( $post->ID, 'value', true );
			if ( empty( $value ) )
				$value = 1;

			$expires = get_post_meta( $post->ID, 'expires', true );
			?>

<style type="text/css">
table { width: 100%; }
table th { width: 20%; text-align: right; }
table th label { padding-right: 12px; }
table td { width: 80%; padding-bottom: 6px; }
table td textarea { width: 95%; }
</style>
<input type="hidden" name="mycred-wplms-nonce" value="<?php echo wp_create_nonce( 'update-mycred-wplms' ); ?>" />
<table class="table wide-fat">
	<tbody>
		<tr valign="top">
			<th scope="row"><label for="mycred-wplms-value"><?php _e( 'Value', 'wplms-mycred' ); ?></label></th>
			<td>
				<input type="text" name="mycred_wplms[value]" id="mycred-wplms-value" value="<?php echo $mycred->number( $value ); ?>" /><br />
				<span class="description"><?php echo $mycred->template_tags_general( __( 'The amount of %plural% this wplms is worth.', 'wplms-mycred' ) ); ?></span>
				<?php if ( $mycred_types && count( $mycred_types ) > 1 ) : ?>

					<br /><label for="mycred-wplms-type"><?php _e( 'Point Type', 'wplms-mycred' ); ?></label><br /><?php mycred_types_select_from_dropdown( 'mycred_wplms[type]', 'mycred-wplms-type', $set_type ); ?>
					<span class="description"><?php _e( 'Select the point type that this wplms is applied.', 'wplms-mycred' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="mycred-wplms-value"><?php _e( 'Expire', 'wplms-mycred' ); ?></label></th>
			<td>
				<input type="text" name="mycred_wplms[expires]" id="mycred-wplms-expire" class="date-picker-field" value="<?php echo $expires; ?>" placeholder="YYYY-MM-DD" /><br />
				<span class="description"><?php _e( 'Optional date when this wplms points criteria expires. Expired WPLMS points criterias will be trashed.', 'wplms-mycred' ); ?></span>
			</td>
		</tr>
	</tbody>
</table>
	<?php do_action( 'mycred_wplms_after_setup', $post ); ?>

<?php
		}

		/**
		 * Metabox: wplms Limits
		 * @since 1.4
		 * @version 1.0
		 */
		public function metabox_wplms_limits( $post ) {
			global $mycred;

			$global_max = get_post_meta( $post->ID, 'global', true );
			if ( empty( $global_max ) )
				$global_max = 1;

			$user_max = get_post_meta( $post->ID, 'user', true );
			if ( empty( $user_max ) )
				$user_max = 1; ?>

<table class="table wide-fat">
	<tbody>
		<tr valign="top">
			<th scope="row"><label for="mycred-wplms-global"><?php _e( 'Global Maximum', 'wplms-mycred' ); ?></label></th>
			<td>
				<input type="text" name="mycred_wplms[global]" id="mycred-wplms-global" value="<?php echo abs( $global_max ); ?>" /><br />
				<span class="description"><?php _e( 'The maximum number of times this points awarding criteria can be used. Note that the wplms points criteria will be automatically trashed once this maximum is reached!', 'wplms-mycred' ); ?></span>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="mycred-wplms-user"><?php _e( 'User Maximum', 'wplms-mycred' ); ?></label></th>
			<td>
				<input type="text" name="mycred_wplms[user]" id="mycred-wplms-user" value="<?php echo abs( $user_max ); ?>" /><br />
				<span class="description"><?php _e( 'The maximum number of times points can be awarded using this criteria to a single user.', 'wplms-mycred' ); ?></span>
			</td>
		</tr>
	</tbody>
</table>
	<?php do_action( 'mycred_wplms_after_limits', $post ); ?>

<?php
		}

		/**
		 * Metabox: wplms Requirements
		 * @since 1.4
		 * @version 1.0
		 */
		public function mycred_wplms_requirements( $post ) {
			global $mycred;

			$wplms_module = get_post_meta( $post->ID, 'wplms_module', true );
			$wplms_module_id = get_post_meta( $post->ID, 'wplms_module_id', true );
			$wplms_module_score_operator= get_post_meta( $post->ID, 'wplms_module_score_operator', true );
			$wplms_module_score= get_post_meta( $post->ID, 'wplms_module_score', true );
			?>

<table class="table wide-fat">
	<tbody>
		<tr valign="top">
			<th scope="row"><label for="mycred-wplms-min_balance"><?php _e( 'WPLMS Module', 'wplms-mycred' ); ?></label></th>
			<td>
				<select name="mycred_wplms[wplms_module]" id="mycred-wplms-module">
					<option value="course" <?php selected($wplms_module,'course'); ?>><?php _e('Course','wplms-mycred'); ?></option>
					<option value="unit" <?php selected($wplms_module,'unit'); ?>><?php _e('Unit','wplms-mycred'); ?></option>
					<option value="quiz" <?php selected($wplms_module,'quiz'); ?>><?php _e('Quiz','wplms-mycred'); ?></option>
					<option value="assignment" <?php selected($wplms_module,'assignment'); ?>><?php _e('Assignment','wplms-mycred'); ?></option>
				</select>
				<br />
				<span class="description"><?php _e( 'Select module for the Points criteria.', 'wplms-mycred' ); ?></span>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="mycred-wplms-max_balance"><?php _e( 'Module ID', 'wplms-mycred' ); ?></label></th>
			<td>
				<input type="text" name="mycred_wplms[wplms_module_id]" id="mycred-wplms-module_id" value="<?php echo $wplms_module_id; ?>" /><br />
				<span class="description"><?php _e( 'Optional set this criteria for a specific module ID.', 'wplms-mycred' ); ?></span>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="mycred-wplms-min_balance"><?php _e( 'Module Criteria', 'wplms-mycred' ); ?></label></th>
			<td>
				<select name="mycred_wplms[wplms_module_score_operator]" id="mycred-wplms-module-score-operator">
					<option value="started" <?php selected($wplms_module_score_operator,'started'); ?>><?php _e('Started (No information required)','wplms-mycred'); ?></option>
					<option value="finished" <?php selected($wplms_module_score_operator,'finished'); ?>><?php _e('Finished (No information required)','wplms-mycred'); ?></option>
					<option value="greater" <?php selected($wplms_module_score_operator,'greater'); ?>><?php _e('Score Greater Than Equal to (Score required)','wplms-mycred'); ?></option>
					<option value="lesser" <?php selected($wplms_module_score_operator,'lesser'); ?>><?php _e('Score Lesser than (Score required)','wplms-mycred'); ?></option>
					<option value="equal" <?php selected($wplms_module_score_operator,'equal'); ?>><?php _e('Score Equals (Score required)','wplms-mycred'); ?></option>
					<option value="highest_score" <?php selected($wplms_module_score_operator,'highest_score'); ?>><?php _e('Highest Marks in module (No information required)','wplms-mycred'); ?></option>
					<option value="lowest_score" <?php selected($wplms_module_score_operator,'lowest_score'); ?>><?php _e('Lowest Marks in Module (No information required)','wplms-mycred'); ?></option>
					<option value="badges_earned" <?php selected($wplms_module_score_operator,'badges_earned'); ?>><?php _e('Number of Badges earned (# Badges information required)','wplms-mycred'); ?></option>
					<option value="certificates_earned" <?php selected($wplms_module_score_operator,'certificates_earned'); ?>><?php _e('Number of Certificates earned (# Certificates information required)','wplms-mycred'); ?></option>
				</select>
				<input type="text" name="mycred_wplms[wplms_module_score]" id="mycred-wplms-module-score" value="<?php echo $wplms_module_score; ?>" placeholder="<?php _e('Criteria specific information','wplms-mycred'); ?>" /><br />
				<br />
				<span class="description"><?php _e( 'Set the Points criteria.', 'wplms-mycred' ); ?></span>
			</td>
		</tr>
	</tbody>
</table>
	<?php do_action( 'mycred_wplms_after_requirements', $post ); ?>

<?php
		}

		/**
		 * Update wplms Details
		 * @since 1.4
		 * @version 1.0
		 */
		public function update_wplms_details( $post_id ) {
			if ( ! isset( $_POST['mycred-wplms-nonce'] ) || ! wp_verify_nonce( $_POST['mycred-wplms-nonce'], 'update-mycred-wplms' ) ) return $post_id;
			if ( isset( $_POST['mycred_wplms'] ) ) {
				
				$eligibility_option = get_option('wplms_mycred_eligibility');
				if(empty($eligibility_option)){
					$eligibility_option =array();
				}
				foreach ( $_POST['mycred_wplms'] as $key => $value ) {
					$value = sanitize_text_field( $value );
					update_post_meta( $post_id, $key, $value );
				}

				$wplms_module = $operator='';
				if(!empty($_POST['mycred_wplms']['wplms_module']))
					$wplms_module = sanitize_text_field($_POST['mycred_wplms']['wplms_module']);
				
				if(!empty($_POST['mycred_wplms']['wplms_module_score_operator']))
					$operator = sanitize_text_field($_POST['mycred_wplms']['wplms_module_score_operator']);


				if(!empty($operator ) && !empty($wplms_module)){
					if(in_array($operator,array('greater','lesser','equal','highest_scrore','lowest_score')))
						$operator = 'score';
					if(empty($eligibility_option[$wplms_module])){
						$eligibility_option[$wplms_module] = array();
					}

					if(!is_array($eligibility_option[$wplms_module][$operator]) || empty($eligibility_option[$wplms_module][$operator])){
						$eligibility_option[$wplms_module][$operator] = array();
					}
					if(!in_array($post_id, $eligibility_option[$wplms_module][$operator])){
						array_push($eligibility_option[$wplms_module][$operator], $post_id);
					}
					update_option('wplms_mycred_eligibility',$eligibility_option);
				}
				
			}
		}

		/**
		 * Add to General Settings
		 * @since 1.4
		 * @version 1.0
		 */
		public function after_general_settings($mycred=NULL) {
			if ( ! isset( $this->wplms ) )
				$prefs = $this->default_prefs;
			else
				$prefs = mycred_apply_defaults( $this->default_prefs, $this->wplms );  ?>

<h4><div class="icon icon-active"></div><?php _e( 'WPLMS', 'wplms-mycred' ); ?></h4>
<div class="body" style="display:none;">
	<label class="subheader" for="<?php echo $this->field_id( 'log' ); ?>"><?php _e( 'Log Template', 'wplms-mycred' ); ?></label>
	<ol id="myCRED-wplms-log">
		<li>
			<div class="h2"><input type="text" name="<?php echo $this->field_name( 'log' ); ?>" id="<?php echo $this->field_id( 'log' ); ?>" value="<?php echo $prefs['log']; ?>" class="long" /></div>
			<span class="description"><?php _e( 'Log entry for successful WPLMS Points criteria redemption.', 'wplms-mycred' ); ?></span>
		</li>
	</ol>
	<label class="subheader" for="<?php echo $this->field_id( 'success' ); ?>"><?php _e( 'Success Message', 'wplms-mycred' ); ?></label>
	<ol id="myCRED-wplms-log">
		<li>
			<div class="h2"><input type="text" name="<?php echo $this->field_name( 'success' ); ?>" id="<?php echo $this->field_id( 'success' ); ?>" value="<?php echo $prefs['success']; ?>" class="long" /></div>
			<span class="description"><?php _e( 'Message to show when a user has successfully earned WPLMS Points.', 'wplms-mycred' ); ?></span>
		</li>
	</ol>
	<!--label class="subheader" for="<?php echo $this->field_id( 'referral' ); ?>"><?php _e( 'Referrals', 'wplms-mycred' ); ?></label>
	<ol id="myCRED-wplms-log">
		<li>
			<div class="h2"><input type="text" name="<?php echo $this->field_name( 'referral' ); ?>" id="<?php echo $this->field_id( 'referral' ); ?>" value="<?php echo (is_numeric($prefs['referral'])?$prefs['referral']:'0'); ?>" class="long" /></div>
			<span class="description"><?php _e( 'Enter referral points when a user refers a new user.', 'wplms-mycred' ); ?></span>
		</li>
	</ol-->
</div>
<?php
		}

		/**
		 * Save Settings
		 * @since 1.4
		 * @version 1.0
		 */
		public function sanitize_extra_settings( $new_data, $data, $core ) {
			$new_data['wplms']['log'] = sanitize_text_field( $data['wplms']['log'] );
			$new_data['wplms']['success'] = sanitize_text_field( $data['wplms']['success'] );
			return $new_data;
		}

	}

	$wplms = new myCRED_Wplms_Module();
	$wplms->load();

}

?>