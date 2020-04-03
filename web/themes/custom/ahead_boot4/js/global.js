/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';
  

  Drupal.behaviors.ahead_boot4 = {
    attach: function (context, settings) {
     
      $( "#advButton",context ).once('advButton').click(function() {
        if(!$('#firstb').hasClass('withCheck'))$('#firstb').addClass('withCheck');
        else if ($('#firstb').hasClass('withCheck'))$('#firstb').removeClass('withCheck');
        
        
      });
      

      $('#firstb',context).once('initHidden').each( function() {
          var m = window.location.search.indexOf("institution_size");
          var n = window.location.search.indexOf("disability_related");
          var o = window.location.search.indexOf("minority_serving");
          var p = window.location.search.indexOf("courses_or_degree_programs");
          if(m > -1 || n > -1 || o > -1 || p > -1) { 
                  if(!$('#firstb').hasClass('withCheck'))$('#firstb').addClass('withCheck');
        
            } 
          else {  
            if ($('#firstb').hasClass('withCheck'))$('#firstb').removeClass('withCheck');
          }
      
       
        
        }
        );

        $('legend.legendDescrip',context).once('initHidden').each( function() {
          $(this).append('<p class="ratingKey">Rating key:</p><ul class="tableKey clearfix"><li><img src="/sites/default/files/cedar_imgs/commonly_provided.png" style="width:18px" alt="Commonly provided in the last three years" />&nbsp;&nbsp;Commonly provided in the last three years</li><li><img src="/sites/default/files/cedar_imgs/occasionally_provided.png" style="width:18px" alt="Occasionally provided in the last three years" />&nbsp;&nbsp;Occasionally provided in the last three years</li><li><img src="/sites/default/files/cedar_imgs/not_provided.png" style="width:18px" alt="Not provided in the last three years" />&nbsp;&nbsp;Not provided in the last three years</li></ul>');

        });
        

        $('div#campus-access',context).once('initHidden').each( function() {
          $(this).prepend('<p class="ratingKey">Rating key:</p><ul class="tableKey clearfix"><li><img src="/sites/default/files/cedar_imgs/commonly_provided.png" style="width:18px" alt="Completely accessible" />&nbsp;&nbsp;Completely accessible</li><li><img src="/sites/default/files/cedar_imgs/occasionally_provided.png" style="width:18px" alt="Somewhat accessible" />&nbsp;&nbsp;Somewhat accessible</li><li><img src="/sites/default/files/cedar_imgs/not_provided.png" style="width:18px" alt="Generally not accessible" />&nbsp;&nbsp;Generally not accessible</li></ul>');

        });      
     
    

    }
  };
  
  
  


})(jQuery, Drupal);
