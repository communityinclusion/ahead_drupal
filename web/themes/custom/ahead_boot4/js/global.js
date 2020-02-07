/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.ahead_boot4 = {
    attach: function (context, settings) {

      
      $( "#block-institutionsize",context ).once('block-institutionsize').addClass('hideAdv');
      $( "#block-disabilityrelatedprogramsandcourses",context ).once('block-disabilityrelatedprogramsandcourses').addClass('hideAdv');
      $( "#block-disabilityrelatedservices",context ).once('block-disabilityrelatedservices').addClass('hideAdv');
      $( "#block-minorityserving",context ).once('block-minorityserving').addClass('hideAdv');
      $( "#block-coursesordegreeprogramsintheareaofdisability",context ).once('block-coursesordegreeprogramsintheareaofdisability').addClass('hideAdv');
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
      }).click();
        $('.view-header').hide();
        $('.block-publicorprivate',context).once('block-publicorprivate').each(function() { $('<p id="advButton">Test</p>').insertBefore(this);
        });
      
        /* $(context).find('#sidebar_first').once('#advButton').each( function() {

          if($('#advButton').length ) { $('#advButton').remove();}
        }); */
        
      
     
    

    }
  };


})(jQuery, Drupal);
