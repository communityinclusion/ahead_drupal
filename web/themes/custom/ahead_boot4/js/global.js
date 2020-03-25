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
          if(m > -1 || n > -1 || o > -1 || p > -1) { console.log("it's there");
                  if(!$('#firstb').hasClass('withCheck'))$('#firstb').addClass('withCheck');
        
            } 
          else { console.log("Not there"); 
            if ($('#firstb').hasClass('withCheck'))$('#firstb').removeClass('withCheck');
          }
      
       
        
        }
        );
        
      
     
    

    }
  };
  
  
  


})(jQuery, Drupal);
