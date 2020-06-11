/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';


  Drupal.behaviors.ahead_boot4 = {
    attach: function (context, settings) {

      $('body',context).once('initHidden').each(function() {
        var beforePrint = function() {
          $(".ui-accordion-content").show();
        };
        var afterPrint = function() {
            console.log('Functionality to run after printing');
        };
    
        if (window.matchMedia) {
            var mediaQueryList = window.matchMedia('print');
            mediaQueryList.addListener(function(mql) {
                if (mql.matches) {
                    beforePrint();
                } else {
                    afterPrint();
                }
            });
        }
    
        window.onbeforeprint = beforePrint;
        window.onafterprint = afterPrint;
     });


      $( "#advButton",context ).once('advButton').click(function() {
        if(!$('#firstb').hasClass('withCheck'))$('#firstb').addClass('withCheck');
        else if ($('#firstb').hasClass('withCheck'))$('#firstb').removeClass('withCheck');


      });
      $('#instaddress',context).once('initHidden').each( function() {
        $(this).prepend('<div id="printButt" class="printThis" tabindex="0" role="button" aria-pressed="false"><span>Print page</span></div>');
          $(this).append('<div id="showAll" class="allHidden" tabindex="0" role="button" aria-pressed="false"><span>Open all sections.</span></div>');

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

      $('.view-solr-search-content',context).once('solr-search-content').each(function(){
          var m = window.location.search.indexOf("institution_size");
          var n = window.location.search.indexOf("disability_related");
          var o = window.location.search.indexOf("minority_serving");
          var p = window.location.search.indexOf("courses_or_degree_programs");
          var q = window.location.search.indexOf("primarily_online");
          var r = window.location.search.indexOf("search_api_fulltext");
          var s = window.location.search.indexOf("state");
          var t = window.location.search.indexOf("degree_type");
          var u = window.location.search.indexOf("public_or_private");
          if(m > -1 || n > -1 || o > -1 || p > -1 || q > -1 || r > -1 || s > -1 || t > -1 || u > -1 ) {
              if ($('.view-header').hasClass('homeShow'))$('.view-header').removeClass('homeShow');
              if ($('.view-content.row').hasClass('hideRow'))$('.view-content.row').removeClass('hideRow');
              if ($('nav').hasClass('hideRow'))$('nav').removeClass('hideRow');

            }
          else {
            if(!$('.view-header').hasClass('homeShow'))$('.view-header').addClass('homeShow');
            if(!$('.view-content.row').hasClass('hideRow'))$('.view-content.row').addClass('hideRow');
            if(!$('.view-content.row').hasClass('hideRow'))$('.view-content.row').addClass('hideRow');
            if(!$('nav').hasClass('hideRow'))$('nav').addClass('hideRow');
          }



        }
        );


      $('#firstb',context).once('initHidden').each( function() {
        if(!$('#block-savesearch h2').hasClass('toggler')) $('#block-savesearch h2').addClass('toggler');
          var m = window.location.search.indexOf("institution_size");
          var n = window.location.search.indexOf("disability_related");
          var o = window.location.search.indexOf("minority_serving");
          var p = window.location.search.indexOf("courses_or_degree_programs");
          var q = window.location.search.indexOf("primarily_online");
          if(m > -1 || n > -1 || o > -1 || p > -1 || q > -1) {
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
        $(document,context).once('autoClose').mouseup(function(e) {
          var container = $("#block-views-block-flag-bookmark-block-1");

          // if the target of the click isn't the container nor a descendant of the container
          if (!container.is(e.target) && container.has(e.target).length === 0)
          {
            container.removeClass('toggled');
          }
        });
        $(document,context).once('autoClose2').mouseup(function(e) {
          var container = $('#block-savesearch');

          // if the target of the click isn't the container nor a descendant of the container
          if (!container.is(e.target) && container.has(e.target).length === 0)
          {
            container.removeClass('toggled');
          }
        });



    }
  };





})(jQuery, Drupal);
