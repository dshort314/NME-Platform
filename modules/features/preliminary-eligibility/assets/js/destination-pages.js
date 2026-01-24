(function($) {
  'use strict';

  $(document).ready(function() {
    var urlParams = new URLSearchParams(window.location.search);
    var messageKey = urlParams.get('message_key');

    if (!messageKey) {
      console.log('NME Prelim: No message key found in URL');
      return;
    }

    var pageSlug = typeof nmePrelimDestinations !== 'undefined' ? nmePrelimDestinations.page_slug : '';
    var targetDivId = '';

    if (pageSlug === 'eligible-greater-than-1-year') {
      targetDivId = '#deferral';
    } else if (pageSlug === 'see-a-lawyer') {
      targetDivId = '#see_lawyer';
    } else {
      console.log('NME Prelim: Unknown page slug:', pageSlug);
      return;
    }

    var $targetDiv = $(targetDivId);
    if (!$targetDiv.length) {
      console.log('NME Prelim: Target div not found:', targetDivId);
      return;
    }

    // Loading state
    $targetDiv.html('<div style="text-align: center; padding: 20px; color: #666;"><span style="display: inline-block; width: 20px; height: 20px; border: 2px solid #ccc; border-top-color: #0073aa; border-radius: 50%; animation: spin 1s linear infinite; margin-right: 10px;"></span>Loading assessment results...</div>');

    // Add spinner CSS
    if (!document.getElementById('nme-prelim-spinner-styles')) {
      var style = document.createElement('style');
      style.id = 'nme-prelim-spinner-styles';
      style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
      document.head.appendChild(style);
    }

    // Fetch message via AJAX
    $.ajax({
      url: nmePrelimDestinations.ajaxurl,
      type: 'POST',
      data: {
        action: 'nme_get_prelim_message',
        message_key: messageKey
      },
      timeout: 10000,
      success: function(response) {
        if (response.success && response.data) {
          $targetDiv.html(response.data);

          // Clean URL
          if (window.history && window.history.replaceState) {
            var cleanUrl = window.location.href.split('?')[0];
            window.history.replaceState({}, document.title, cleanUrl);
          }
        } else {
          showError('Unable to load assessment results. The content may have expired.');
        }
      },
      error: function(xhr, status, error) {
        console.error('NME Prelim: AJAX error:', status, error);
        var msg = 'Unable to load assessment results.';
        if (status === 'timeout') {
          msg += ' Request timed out.';
        }
        showError(msg);
      }
    });

    function showError(message) {
      $targetDiv.html(
        '<div style="background: #f9f9f9; border-left: 4px solid #d63638; padding: 15px; margin: 20px 0;">' +
        '<h3 style="margin-top: 0; color: #d63638;">Error Loading Results</h3>' +
        '<p>' + message + '</p>' +
        '<p><a href="javascript:window.location.reload();">Click here to refresh the page</a></p>' +
        '</div>'
      );
    }
  });

})(jQuery);
