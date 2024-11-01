if(typeof jQuery != 'undefined'){
  jQuery(document).ready(function($){
  	 if(jQuery('#mycred_points_data').length){
          Morris.Area({
              element: 'mycred_points_data',
              data: mycred_points_data,
              xkey: 'date',
              ykeys: ['sales','total'],
              labels: ['Points','Balance'],
              lineColors: ['#23b7e5','#f05050'],
              lineWidth: 1,
              resize:true,
              parseTime: false
            });
          Morris.Donut({
            element: 'mycred_points_breakup',
            data: mycred_points_breakup,
            colors:['#7266ba','#23b7e5','#f05050','#fad733','#27c24c','#fa7252']
          });
    }
    $('#mycred_commission_breakup').each(function(){
      Morris.Donut({
            element: 'mycred_commission_breakup',
            data: mycred_commission_breakup,
            colors:['#7266ba','#23b7e5','#f05050','#fad733','#27c24c','#fa7252']
          }); 
    });
    jQuery('body').delegate('.mycred-subscription','click',function(event){
        
          var parent=jQuery(this).parent();
          var hidden=jQuery('.credsubscription');
          var checkvalue=parent.find('.switch-input:checked').val();    
          if(checkvalue == 'S'){ // jQuery records the previous known value
              hidden.fadeIn(200);
          }else{
              hidden.fadeOut(200);
          }
      });
  });
}