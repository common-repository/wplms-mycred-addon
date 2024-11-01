<?php

add_action( 'widgets_init', 'wplms_dash_mycred_widget' );

function wplms_dash_mycred_widget() {
    register_widget('wplms_dash_mycred_stats');
    register_widget('wplms_dash_mycred_balance');
}

class wplms_dash_mycred_balance extends WP_Widget {

    /** constructor -- name this the same as the class above */
    function __construct() {
    $widget_ops = array( 'classname' => 'wplms_dash_mycred_balance', 'description' => __('MyCred Balance widget for Dashboard', 'wplms-mycred') );
    $control_ops = array( 'width' => 300, 'height' => 350, 'id_base' => 'wplms_dash_mycred_balance' );
    parent::__construct( 'wplms_dash_mycred_balance', __(' DASHBOARD : MyCred Balance Widget', 'wplms-mycred'), $widget_ops, $control_ops );
  }
        
 
    /** @see WP_Widget::widget -- do not rename this */
    function widget( $args, $instance ) {
    extract( $args );

    $stats = 'mycred_points';
    //Our variables from the widget settings.
    $title = apply_filters('widget_title', $instance['title'] );
    $width =  $instance['width'];
    $user_id = get_current_user_id();
    echo '<div class="'.$width.'">
            <div class="dash-widget '.$stats.'">'.$before_widget;
        global $wpdb;
        $table = $wpdb->prefix.'myCRED_log';
        $query =$wpdb->prepare("
              SELECT creds
              FROM {$table} AS mycred
              WHERE  mycred.user_id   = %d
              ORDER BY mycred.time DESC
              LIMIT 10",$user_id);
        $marks=$wpdb->get_results($query);
        if(is_array($marks)){
          foreach($marks as $k=>$mark){
            $points[]=round($mark->creds,0);
          }
        }else{
          $points=array();
        }

        $mycred = mycred();
        $value = round($mycred->get_users_cred( $user_id ),0);
        echo '<div class="dash-stats">';
        echo '<h3>'.$mycred->format_creds($value).'<span>'.$title.'</span></h3>';
        echo '<div class="sparkline'.$stats.'">Loading..</div>';
        echo '</div>';
        echo $after_widget.'
        </div>
        </div>';
        $points_string='';
        if(is_array($points))
        $points_string = implode(',',$points);
        echo "<script>jQuery(document).ready(function($){
        var myvalues = [$points_string];
        $('.sparkline$stats').sparkline(myvalues, {
          type: 'bar',
          zeroAxis: false,
          barColor: '#FFF'});
        });
        </script>";
                
    }
 
    /** @see WP_Widget::update -- do not rename this */
    function update($new_instance, $old_instance) {   
	    $instance = $old_instance;
	    $instance['title'] = strip_tags($new_instance['title']);
	    $instance['width'] = $new_instance['width'];
	    return $instance;
    }
 
    /** @see WP_Widget::form -- do not rename this */
    function form($instance) {  
        $defaults = array( 
                        'title'  => __('My Balance','wplms-mycred'),
                        'content' => '',
                        'width' => 'col-md-6 col-sm-12'
                    );
  		  $instance = wp_parse_args( (array) $instance, $defaults );
        $title  = esc_attr($instance['title']);
        $width = esc_attr($instance['width']);
        ?>
        <p>
          <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:','wplms-mycred'); ?></label> 
          <input class="regular_text" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
        </p>
        <p>
          <label for="<?php echo $this->get_field_id('width'); ?>"><?php _e('Select Width','wplms-mycred'); ?></label> 
          <select id="<?php echo $this->get_field_id('width'); ?>" name="<?php echo $this->get_field_name('width'); ?>">
          	<option value="col-md-3 col-sm-6" <?php selected('col-md-3 col-sm-6',$width); ?>><?php _e('One Fourth','wplms-mycred'); ?></option>
          	<option value="col-md-4 col-sm-6" <?php selected('col-md-4 col-sm-6',$width); ?>><?php _e('One Third','wplms-mycred'); ?></option>
          	<option value="col-md-6 col-sm-12" <?php selected('col-md-6 col-sm-12',$width); ?>><?php _e('One Half','wplms-mycred'); ?></option>
            <option value="col-md-8 col-sm-12" <?php selected('col-md-8 col-sm-12',$width); ?>><?php _e('Two Third','wplms-mycred'); ?></option>
             <option value="col-md-8 col-sm-12" <?php selected('col-md-9 col-sm-12',$width); ?>><?php _e('Three Fourth','wplms-mycred'); ?></option>
          	<option value="col-md-12" <?php selected('col-md-12',$width); ?>><?php _e('Full','wplms-mycred'); ?></option>
          </select>
        </p>
        <?php 
    }
} 



class wplms_dash_mycred_stats extends WP_Widget {

    /** constructor -- name this the same as the class above */
    function __construct() {
    $widget_ops = array( 'classname' => 'wplms_dash_mycred_stats', 'description' => __('MyCred Stats widget for Dashboard', 'wplms-mycred') );
    $control_ops = array( 'width' => 300, 'height' => 350, 'id_base' => 'wplms_dash_mycred_stats' );
    parent::__construct( 'wplms_dash_mycred_stats', __(' DASHBOARD : MyCred Stats Widget', 'wplms-mycred'), $widget_ops, $control_ops );
  }
        
 
    /** @see WP_Widget::widget -- do not rename this */
    function widget( $args, $instance ) {
    extract( $args );
    
    $title = apply_filters('widget_title', $instance['title'] );
    $width =  $instance['width'];
    $num =  $instance['num'];
    $user_id = get_current_user_id();
    echo '<div class="'.$width.'">
            <div class="dash-widget">'.$before_widget;

    if ( $title )
      echo $before_title . $title . $after_title;

      global $wpdb;
      $table = $wpdb->prefix.'myCRED_log';
      $query =$wpdb->prepare("
            SELECT creds,ref,ref_id,time as t
            FROM {$table} AS mycred
            WHERE  mycred.user_id = %d
            ORDER BY mycred.time DESC
            LIMIT 0,%d",$user_id,$num);


      $spends=$wpdb->get_results($query);
      
      $mycred=mycred();
      $current_balance = $mycred->get_users_cred( $user_id );
      $total=array();
      if(is_array($spends)){
        foreach($spends as $k=>$spend){
          if($spend->ref == 'instructor_commission'){
            $commission_pie[$spend->ref_id] += round($spend->creds,0);
          }
          $sales_pie[$spend->ref] += round($spend->creds,0);  
          $sales[$spend->t] += $spend->creds;
        }
      }else{
        $points=array();
      }

      $i=0;
      if(is_array($sales))
      foreach($sales as $key=>$value){
        $i++;
        if($i == 1)
          $total[$key] = $current_balance;
        else if($i <=count($sales)){
          $total[$key] = $total[$prev_key[$i-1]]-$sales[$prev_key[$i-1]];
        }
        $prev_key[$i] = $key;
      }

      if(isset($sales) && is_array($sales)){


      ksort($sales);

      foreach($sales as $key => $value){
      $points_array[$key]=array(
          'date' => date('d-M-y',$key),
          'sales'=>$value,
          'total' => $total[$key]
          );
      }
     }
     
      echo '<div id="mycred_points_data" class="morris"></div>';
      echo '<div class="row">
            <div class="col-md-6">
            <label class="sales_labels">'.__('Action','wplms-mycred').'<strong>'.__('Points','wplms-mycred').' </strong></label>
            <div class="course_list">';
            $sales_pie_array=array();
          if(isset($sales_pie) && is_array($sales_pie) && count($sales_pie)){
            echo '<ul class="course_sales_list">';
            
            foreach($sales_pie as $ctitle=>$sales){
              
              $ctitle = str_replace('_',' ',$ctitle);
              echo '<li>'.$ctitle.'<strong>'.(($sales < 0)?'(-)'.$sales*-1:$sales).'</strong></li>';

              $sales_pie_array[]=array(
                'label'=>$ctitle,
                'value' => ($sales < 0)?$sales*-1:$sales
                );
            }
            echo '</ul>';
          }else{
            echo '<div class="message"><p>'.__('No data found','wplms-mycred').'</p></div>';
          }  
          echo '</div></div><div class="col-md-6">
            <div id="mycred_points_breakup" class="morris"></div>';

          if(current_user_can('edit_posts')){
            echo '<div id="mycred_commission_breakup" class="morris"></div>';  
          }
      
          echo '</div></div>';
          echo '</div></div>';
          if(current_user_can('edit_posts')){
            $commission_pie_array=array();
            if(!empty($commission_pie)){
              foreach($commission_pie as $cid => $val){
                $commission_pie_array[]=array(
                  'label'=>get_the_title($cid),
                  'value'=>$val
                );
              }
            }
          }
          echo '<script>
            var mycred_points_data=[';$first=0;
            if(!empty($points_array)) {       
            foreach($points_array as $data){
              if($first)
                echo ',';
              $first=1;
              echo str_replace('"','\'',json_encode($data,JSON_NUMERIC_CHECK));
            }}
            echo  '];
            var mycred_points_breakup =[';$first=0;
            if(isset($sales_pie_array) && is_Array($sales_pie_array))
            foreach($sales_pie_array as $data){
              if($first)
                echo ',';
              $first=1;
              echo str_replace('"','\'',json_encode($data,JSON_NUMERIC_CHECK));
            }
            echo  '];';
            if(current_user_can('edit_posts')){
            echo 'var mycred_commission_breakup =[';$first=0;
            if(!empty($commission_pie_array))
            foreach($commission_pie_array as $data){
              if($first)
                echo ',';
              $first=1;
              echo str_replace('"','\'',json_encode($data,JSON_NUMERIC_CHECK));
            }
            echo  '];';
          }
          echo '</script>';
  }
 
    /** @see WP_Widget::update -- do not rename this */
    function update($new_instance, $old_instance) {   
      $instance = $old_instance;
      $instance['title'] = strip_tags($new_instance['title']);
      $instance['num'] = strip_tags($new_instance['num']);
      $instance['width'] = $new_instance['width'];
      return $instance;
    }
 
    /** @see WP_Widget::form -- do not rename this */
    function form($instance) {  
        $defaults = array( 
                        'title'  => __('My Balance','wplms-mycred'),
                        'num' => 1,
                        'width' => 'col-md-6 col-sm-12'
                    );
        $instance = wp_parse_args( (array) $instance, $defaults );
        $title  = esc_attr($instance['title']);
        $num  = esc_attr($instance['num']);
        $width = esc_attr($instance['width']);
        ?>
        <p>
          <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:','wplms-mycred'); ?></label> 
          <input class="regular_text" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
        </p>
        <p>
          <label for="<?php echo $this->get_field_id('num'); ?>"><?php _e('Number of Transactions :','wplms-mycred'); ?></label> 
          <input class="regular_text" id="<?php echo $this->get_field_id('num'); ?>" name="<?php echo $this->get_field_name('num'); ?>" type="text" value="<?php echo $num; ?>" />
        </p>
        <p>
          <label for="<?php echo $this->get_field_id('width'); ?>"><?php _e('Select Width','wplms-mycred'); ?></label> 
          <select id="<?php echo $this->get_field_id('width'); ?>" name="<?php echo $this->get_field_name('width'); ?>">
            <option value="col-md-3 col-sm-6" <?php selected('col-md-3 col-sm-6',$width); ?>><?php _e('One Fourth','wplms-mycred'); ?></option>
            <option value="col-md-4 col-sm-6" <?php selected('col-md-4 col-sm-6',$width); ?>><?php _e('One Third','wplms-mycred'); ?></option>
            <option value="col-md-6 col-sm-12" <?php selected('col-md-6 col-sm-12',$width); ?>><?php _e('One Half','wplms-mycred'); ?></option>
            <option value="col-md-8 col-sm-12" <?php selected('col-md-8 col-sm-12',$width); ?>><?php _e('Two Third','wplms-mycred'); ?></option>
             <option value="col-md-8 col-sm-12" <?php selected('col-md-9 col-sm-12',$width); ?>><?php _e('Three Fourth','wplms-mycred'); ?></option>
            <option value="col-md-12" <?php selected('col-md-12',$width); ?>><?php _e('Full','wplms-mycred'); ?></option>
          </select>
        </p>
        <?php 
    }
} 

?>