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
        
        $('#block-institutionsize').toggle( "slow", function() {
          // Animation complete.
        });
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
        });
      });
      
        /* $(context).find('#sidebar_first').once('#advButton').each( function() {

          if($('#advButton').length ) { $('#advButton').remove();}
        }); */
        
      
     
    

    }
  };


})(jQuery, Drupal);
