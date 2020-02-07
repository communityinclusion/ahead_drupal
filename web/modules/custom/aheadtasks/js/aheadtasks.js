/**
 * @file
 * JavaScript for aheadtasks.
 */

(function ($) {

    // Re-enable form elements that are disabled for non-ajax situations.
    Drupal.behaviors.hideBlocks = {
      attach: function () {
            $( document ).ready(function() {
                $('#block-institutionsize').hide();
            });
        }
    };
  
  })(jQuery);
  