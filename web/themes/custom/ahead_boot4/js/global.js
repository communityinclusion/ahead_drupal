/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';


  Drupal.behaviors.ahead_boot4 = {
    attach: function (context, settings) {
      once('initFadein','.view-solr-search-content',context).forEach(function(value,i) {
          // When the page has loaded
          $('.view-solr-search-content').addClass('loaded');
        });

      once('initHidden','body',context).forEach(function(value,i) {

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


      once('advButton',"#advButton").forEach(function(value,i) {
        $(value).click(function() {
          if(!$('#firstb').hasClass('withCheck'))$('#firstb').addClass('withCheck');
          else if ($('#firstb').hasClass('withCheck'))$('#firstb').removeClass('withCheck');


        });


      });
      once('initHidden','#instaddress',context).forEach( function(value,i) {
        $(value).prepend('<div id="printButt" class="printThis" tabindex="0" role="button" aria-pressed="false"><span>Print page</span></div>');
          $(value).append('<div id="showAll" class="allHidden" tabindex="0" role="button" aria-pressed="false"><span>Open all sections.</span></div>');

      });

    once('showAll',"#showAll").forEach(function(value,i) {
      $(value).click(function() {
          if($(this).hasClass('allHidden')) {$(".ui-accordion-content").show();
          $(this).removeClass('allHidden');
          $(this).html('<span>Hide all sections.</span>');
          } else
          {$(".ui-accordion-content").hide();
            $(this).addClass('allHidden');
            $(this).html('<span>Open all sections.</span>');
          }



        });



      });
      once('printBut',"#printButt").forEach(function(value,i) {
        $(value).click(function() {
          $(".ui-accordion-content").show();
            window.print();
            return false;



        });



      });

      once('solr-search-content','.view-display-id-page_1',context).forEach(function(value,i){
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
              if ($('.searchJump').hasClass('searchJump'))$('.view-header').removeClass('searchJump');

            }
          else {
            if(!$('.view-header').hasClass('homeShow'))$('.view-header').addClass('homeShow');
            if(!$('.searchJump').hasClass('homeShow'))$('.searchJump').addClass('homeShow');
            if(!$('.view-content.row').hasClass('hideRow'))$('.view-content.row').addClass('hideRow');
            if(!$('.view-content.row').hasClass('hideRow'))$('.view-content.row').addClass('hideRow');
            if(!$('nav').hasClass('hideRow'))$('nav').addClass('hideRow');
          }



        }
        );


      once('initHidden','#firstb',context).forEach( function(value,i) {
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

        once('initHiddenn','legend.legendDescrip',context).forEach( function(value,i) {
          $(value).append('<p class="ratingKey">Rating key:</p><ul class="tableKey clearfix"><li><img src="/sites/default/files/cedar_imgs/commonly_provided.png" style="width:18px" alt="Commonly provided in the last three years" />&nbsp;&nbsp;Commonly provided in the last three years</li><li><img src="/sites/default/files/cedar_imgs/occasionally_provided.png" style="width:18px" alt="Occasionally provided in the last three years" />&nbsp;&nbsp;Occasionally provided in the last three years</li><li><img src="/sites/default/files/cedar_imgs/not_provided.png" style="width:18px" alt="Not provided in the last three years" />&nbsp;&nbsp;Not provided in the last three years</li></ul>');

        });


        once('initHiddennn','div#campus-access',context).forEach( function(value,i) {
          if(!$('#block-savesearch h2').hasClass('toggler')) $('#block-savesearch h2').addClass('toggler');
          $(this).prepend('<div><p class="ratingKey">Rating key:</p><ul class="tableKey clearfix"><li><img src="/sites/default/files/cedar_imgs/commonly_provided.png" style="width:18px" alt="Completely accessible" />&nbsp;&nbsp;Completely accessible</li><li><img src="/sites/default/files/cedar_imgs/occasionally_provided.png" style="width:18px" alt="Somewhat accessible" />&nbsp;&nbsp;Somewhat accessible</li><li><img src="/sites/default/files/cedar_imgs/not_provided.png" style="width:18px" alt="Generally not accessible" />&nbsp;&nbsp;Generally not accessible</li></ul></div>');

        });
        once('flag-bookmark','a.use-ajax').forEach(function(value,i){
          $(value).click(function(){
            console.log('view name: ');


           setTimeout(function(){ $('.view-flag-bookmark').trigger('RefreshView');},2000);

          });

        });



        once('toggleSearch','h2.searchToggle',context).forEach( function(value,i) {
          $(value).click( function() {
          if(!$('#block-savesearch').hasClass('toggled')) { $('#block-savesearch').addClass('toggled');}
          else { $('#block-savesearch').removeClass('toggled'); }

          });
        });

        once('toggleSearch2','h2.favToggle',context).forEach( function(value,i) {
          $(value).click( function() {
            if(!$('#block-ahead-boot4-views-block-flag-bookmark-block-1').hasClass('toggled')) { $('#block-ahead-boot4-views-block-flag-bookmark-block-1').addClass('toggled');}
            else { $('#block-ahead-boot4-views-block-flag-bookmark-block-1').removeClass('toggled'); }
          });

        });
        once('autoClose',document,context).forEach(function(value,i) {
          $(value).mouseup(function(e) {
            var container = $("#block-ahead-boot4-views-block-flag-bookmark-block-1");

            // if the target of the click isn't the container nor a descendant of the container
            if (!container.is(e.target) && container.has(e.target).length === 0)
            {
              container.removeClass('toggled');
            }
          });
        });
        once('autoClose2',document,context).forEach(function(value,i) {
          $(value).mouseup(function(e) {
            var container = $('#block-savesearch');

            // if the target of the click isn't the container nor a descendant of the container
            if (!container.is(e.target) && container.has(e.target).length === 0)
            {
              container.removeClass('toggled');
            }
          });
        });



    }
  };





})(jQuery, Drupal);
