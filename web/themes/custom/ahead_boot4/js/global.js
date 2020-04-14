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
      $('#instaddress',context).once('initHidden').each( function() {
        $(this).prepend('<div id="printButt" class="printThis"><span>Print page</span></div>');
          $(this).append('<div id="showAll" class="allHidden"><span>Open all sections.</span></div>');
        
      });

      $( "#showAll",context ).once('showAll').click(function() {
        if($(this).hasClass('allHidden')) {$(".ui-accordion-content").show();
        $(this).removeClass('allHidden');
        $(this).html('<span>Hide all sections.</span>');
        } else
        {$(".ui-accordion-content").hide();
          $(this).addClass('allHidden');
          $(this).html('<span>Open all sections.</span>');
        }

        
        
      });
      $( "#printButt",context ).once('printButt').click(function() {
        $(".ui-accordion-content").show();
          window.print();
          return false;

        
        
      });
      

      $('#firstb',context).once('initHidden').each( function() {
        if(!$('#block-savesearch h2').hasClass('toggler')) $('#block-savesearch h2').addClass('toggler');
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
          if(!$('#block-savesearch h2').hasClass('toggler')) $('#block-savesearch h2').addClass('toggler');
          $(this).prepend('<div><p class="ratingKey">Rating key:</p><ul class="tableKey clearfix"><li><img src="/sites/default/files/cedar_imgs/commonly_provided.png" style="width:18px" alt="Completely accessible" />&nbsp;&nbsp;Completely accessible</li><li><img src="/sites/default/files/cedar_imgs/occasionally_provided.png" style="width:18px" alt="Somewhat accessible" />&nbsp;&nbsp;Somewhat accessible</li><li><img src="/sites/default/files/cedar_imgs/not_provided.png" style="width:18px" alt="Generally not accessible" />&nbsp;&nbsp;Generally not accessible</li></ul></div>');

        });  
        $('a.use-ajax', context).once('flag-bookmark').click(function(){
          console.log('view name: ');
            

         setTimeout(function(){ $('.view-flag-bookmark').trigger('RefreshView');},2000);
      
        }); 


       
        $('h2.searchToggle',context).once('toggleSearch').click( function() {
          if(!$('#block-savesearch').hasClass('toggled')) { $('#block-savesearch').addClass('toggled');}
          else { $('#block-savesearch').removeClass('toggled'); }

        });
         
        $('h2.favToggle',context).once('toggleSearch2').click( function() {
          if(!$('#block-views-block-flag-bookmark-block-1').hasClass('toggled')) { $('#block-views-block-flag-bookmark-block-1').addClass('toggled');}
          else { $('#block-views-block-flag-bookmark-block-1').removeClass('toggled'); }

        });   
     
    

    }
  };
  
  
  


})(jQuery, Drupal);
