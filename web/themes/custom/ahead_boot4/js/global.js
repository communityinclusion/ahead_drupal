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
        
        //$('#firstb').toggle( "slow", function() {
          // Animation complete.
        
        /*
        $('#block-disabilityrelatedprogramsandcourses').toggle( "slow", function() {
          // Animation complete.
        });
        $('#block-disabilityrelatedservices').toggle( "slow", function() {
          // Animation complete.
        });
        $('#block-minorityserving').toggle( "slow", function() {
          // Animation complete.
        });
        $('#block-coursesordegreeprogramsintheareaofdisability').toggle( "slow", function() {
          // Animation complete.
        }); */
      });
      $('#sidebar_first div',context).once('initHidden').each( function() {
        //$(this).closest('div.initHidden').addClass('withCheck');
        
        }
      );
      
     
     /* $('#firstb',context).once('checkboxes').each(function(){
        $('#firstb').removeClass('initHidden');
        $('#firstb input').each(function(){
          
          console.log('loaded');
          if ($(this).is(':checked')) {
        
        
          if(!$('#firstb').hasClass('withCheck')) $("#firstb").addClass('initHidden').addClass('withCheck');
        
          
            return;


         
          } 
          else {
                if(!$('#firstb').hasClass('initHidden')) $("#firstb").addClass('initHidden');
          }
          });
      
        
      
      
      }); */
        
      
     
    

    }
  };


})(jQuery, Drupal);
