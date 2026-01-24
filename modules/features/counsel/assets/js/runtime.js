(function($, window, document){
  'use strict';

  var CFG         = window.NME_Counsel_Settings || {};
  var FORM_ID     = parseInt(CFG.form_id || 0, 10);
  var YES_FIELDS  = Array.isArray(CFG.yes_fields) ? CFG.yes_fields.map(function(n){ return parseInt(n,10); }).filter(function(n){ return !isNaN(n); }) : [];
  var NO_FIELDS   = Array.isArray(CFG.no_fields)  ? CFG.no_fields.map(function(n){ return parseInt(n,10); }).filter(function(n){ return !isNaN(n); }) : [];

  var FLAG_FIELD  = parseInt(CFG.flag_field_id || 924, 10);
  var FLAG_VALUE  = String(CFG.flag_value_bounce || 'Bounce');
  var ARREST_FIELD = parseInt(CFG.arrest_field_id || 940, 10);

  var DEFAULT_YES = CFG.default_message || '';
  var DEFAULT_NO  = CFG.default_no_message || '';
  var CONFIRM_MSG = CFG.confirm_message || '<p>Thank you for confirming your answer.</p>';
  var ARREST_BOUNCE_MSG = CFG.arrest_bounce_message || '<p>Based on your response, you may need additional legal counsel.</p>';

  var OVERRIDES   = CFG.overrides || {};
  var DEBUG       = !!CFG.debug;

  // Track current field for arrest check
  var currentFieldId = null;
  // Track fields where user clicked "Back" (didn't confirm their answer)
  var fieldsNeedingConfirmation = new Set();

  // Intercept Gravity Forms navigation buttons that use inline onclick handlers
  function interceptGravityFormsButtons() {
    $('.gform_next_button, .gform_previous_button, .gform_save_link').each(function(){
      var $btn = $(this);
      var originalOnclick = $btn.attr('onclick');
      
      if (originalOnclick && !$btn.data('nmec-intercepted')) {
        // Store the original handler
        $btn.data('original-onclick', originalOnclick);
        $btn.data('nmec-intercepted', true);
        // Remove the inline onclick
        $btn.removeAttr('onclick');
        
        // Add our interceptor
        $btn.off('click.nmec-gf').on('click.nmec-gf', function(e){
          if (fieldsNeedingConfirmation.size > 0) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            console.log('GF Navigation blocked - showing confirmation');
            
            var fieldId = Array.from(fieldsNeedingConfirmation)[0];
            currentFieldId = fieldId;
            
            showNavigationConfirmation($(this));
            return false;
          } else {
            // No confirmation needed - execute original handler
            var onclick = $btn.data('original-onclick');
            if (onclick) {
              eval(onclick);
            }
          }
        });
      }
    });
  }

  function log(){ if (DEBUG && console && console.log) console.log.apply(console, ['[Counsel]'].concat([].slice.call(arguments))); }
  if (!FORM_ID) return;

  // ----------------------------
  // Modal Helpers
  // ----------------------------
  function isHtmlString(s){ return /<[\s\S]*?>/.test(String(s||'')); }

  function ensureModalStyles(){
    if (document.getElementById('nme-counsel-inline-styles')) return;
    var css = ''
      + '#nmeCounselOv{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9998;display:flex;align-items:center;justify-content:center;}'
      + '#nmeCounselMd{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:28px;border-radius:10px;box-shadow:0 10px 40px rgba(0,0,0,.25);z-index:9999;max-width:720px;width:92%;max-height:80vh;overflow:auto;font-size:16px;line-height:1.5;}'
      + '#nmeCounselMd .nme-btn{background:#0a62a3;color:#fff;border:0;padding:10px 18px;border-radius:8px;cursor:pointer;display:inline-block;box-shadow:0 1px 0 rgba(0,0,0,.1);}'
      + '#nmeCounselMd .nme-btn:hover{background:#084f83;}'
      + '#nmeCounselMd .nme-btn-back{background:#6c757d;}'
      + '#nmeCounselMd .nme-btn-back:hover{background:#5a6268;}'
      + '.nme-actions{display:flex;gap:12px;justify-content:center;margin-top:14px;flex-wrap:wrap;}'
      + '#nmeCounselMd p{margin:0 0 1em;}'
      + '#nmeCounselMd ul,#nmeCounselMd ol{margin:0 0 1em 1.25em;}'
      + '#nmeCounselMd a{text-decoration:underline;}'
      + '#nmeCounselMd strong,b{font-weight:600;}'
      + '#nmeCounselMd .nme-content{white-space:pre-line;}';
    var style = document.createElement('style');
    style.id = 'nme-counsel-inline-styles';
    style.appendChild(document.createTextNode(css));
    document.head.appendChild(style);
  }

  function closeModal(resetField){ 
    $('#nmeCounselOv,#nmeCounselMd').remove(); 
    if (resetField !== false) {
      currentFieldId = null;
    }
  }

  function openModal(html, preventOverlayClose){
    ensureModalStyles();
    closeModal(false); // Don't reset field when replacing modal
    var $ov = $('<div id="nmeCounselOv"></div>').appendTo('body');
    var $md = $('<div id="nmeCounselMd"></div>').html(html).appendTo('body');
    
    // Only allow overlay close if not prevented
    if (!preventOverlayClose) {
      $ov.on('click', function() { closeModal(true); });
    }
  }

  // ----------------------------
  // Field helpers
  // ----------------------------
  function baseIdFromInput($input){
    var name = String($input.attr('name')||'');
    var m = name.match(/^input_(\d+)/);
    return m ? parseInt(m[1],10) : null;
  }

  function isYes($input){
    if (!$input.is(':checked')) return false;
    var val = String($input.val()||'').trim().toLowerCase();
    var id  = $input.attr('id');
    var $lab= id ? $('label[for="'+id+'"]') : $();
    var label = String(($lab.text()||'')).trim().toLowerCase();
    return (val==='yes') || (val==='true') || (label==='yes') || (val==='1' && label.indexOf('yes')!==-1);
  }

  function isNo($input){
    if (!$input.is(':checked')) return false;
    
    // Get the base field ID first
    var baseId = baseIdFromInput($input);
    
    var val = String($input.val() || '').trim().toLowerCase();
    var id  = $input.attr('id');
    var $lab = id ? $('label[for="'+id+'"]') : $();
    var label = String(($lab.text() || '')).trim().toLowerCase();
    
    // Special case: arrest field (checkbox) - look for "I have been convicted of a crime"
    if (baseId === ARREST_FIELD) {
      var fullVal = String($input.val() || '').toLowerCase();
      var fullLabel = String($lab.text() || '').toLowerCase();
      // Check if contains "I have been convicted of a crime"
      if (fullVal.indexOf('i have been convicted of a crime') !== -1 || fullLabel.indexOf('i have been convicted of a crime') !== -1) {
        return true;
      }
    }
    
    // Standard NO detection for radio buttons
    if (val === 'no') return true;
    if (val === '0') return true;
    if (label === 'no') return true;
    if (label.indexOf('no') === 0) return true;
    
    return false;
  }

  // Check if arrest field contains "I have been convicted of a crime"
  function hasArrestText() {
    var $arrestInputs = $('input[type="checkbox"][name*="input_' + ARREST_FIELD + '"]');
    var hasArrest = false;
    
    $arrestInputs.each(function() {
      if ($(this).is(':checked')) {
        var val = String($(this).val() || '').toLowerCase();
        var id = $(this).attr('id');
        var $lab = id ? $('label[for="' + id + '"]') : $();
        var labelText = String($lab.text() || '').toLowerCase();
        
        // Check if contains "I have been convicted of a crime"
        if (val.indexOf('i have been convicted of a crime') !== -1 || labelText.indexOf('i have been convicted of a crime') !== -1) {
          hasArrest = true;
          return false; // break loop
        }
      }
    });
    
    return hasArrest;
  }

  // ----------------------------
  // Message Resolver
  // ----------------------------
  function messageFor(fieldId){
    if (OVERRIDES && OVERRIDES[fieldId]) {
      return isHtmlString(OVERRIDES[fieldId])
        ? OVERRIDES[fieldId]
        : '<p>' + String(OVERRIDES[fieldId]) + '</p>';
    }
    if (YES_FIELDS.indexOf(fieldId) !== -1) {
      var msgY = DEFAULT_YES || 'Are you sure you want to continue?';
      return isHtmlString(msgY) ? msgY : '<p>'+String(msgY)+'</p>';
    }
    if (NO_FIELDS.indexOf(fieldId) !== -1) {
      var msgN = DEFAULT_NO || 'Are you sure you want to continue?';
      return isHtmlString(msgN) ? msgN : '<p>'+String(msgN)+'</p>';
    }
    return '<p>Are you sure you want to continue?</p>';
  }

  function onCounsel(fieldId){
    currentFieldId = fieldId; // Store for arrest check
    var html =
      '<div class="nme-content">' + messageFor(fieldId) + '</div>' +
      '<div class="nme-actions">' +
      '<button class="nme-btn nme-btn-back nme-back" data-field="'+fieldId+'">Back</button>' +
      '<button class="nme-btn nme-confirm" data-field="'+fieldId+'">Answer is Correct</button>' +
      '</div>';
    openModal(html, false); // Allow overlay close
  }

  function showConfirmation(){
    // Debug logging
    console.log('showConfirmation called');
    console.log('currentFieldId:', currentFieldId);
    console.log('ARREST_FIELD:', ARREST_FIELD);
    console.log('hasArrestText():', hasArrestText());
    
    // Check if this is the arrest field with arrest text
    if (currentFieldId === ARREST_FIELD && hasArrestText()) {
      console.log('Showing ARREST_BOUNCE_MSG');
      var html =
        '<div class="nme-content">' + ARREST_BOUNCE_MSG + '</div>' +
        '<div class="nme-actions">' +
        '<button class="nme-btn nme-ack">I understand</button>' +
        '</div>';
      openModal(html, true); // Prevent overlay close for final message
    } else {
      // Show standard confirmation
      console.log('Showing CONFIRM_MSG');
      var html =
        '<div class="nme-content">' + CONFIRM_MSG + '</div>' +
        '<div class="nme-actions">' +
        '<button class="nme-btn nme-ack">I understand</button>' +
        '</div>';
      openModal(html, true); // Prevent overlay close for final message
    }
  }

  // ----------------------------
  // Bind change watchers
  // ----------------------------
  $(function(){
    var $form = $('#gform_'+FORM_ID);
    if (!$form.length) return;

    var yesSet = YES_FIELDS.length ? new Set(YES_FIELDS) : null;
    var noSet  = NO_FIELDS.length  ? new Set(NO_FIELDS)  : null;

    // Intercept Gravity Forms buttons on page load
    interceptGravityFormsButtons();
    
    // Re-run after AJAX page changes
    $(document).on('gform_page_loaded', function(){
      setTimeout(interceptGravityFormsButtons, 100);
    });

    // Register the general navigation interceptor for non-GF buttons
    registerNavigationInterceptor();

    $(document).on('change.nmec', 'input[name^="input_"]', function(){
      var $inp = $(this),
          baseId = baseIdFromInput($inp);
      if (!baseId) return;

      if (yesSet && yesSet.has(baseId) && isYes($inp)) {
        log('YES counsel on field', baseId);
        onCounsel(baseId);
        return;
      }

      if (noSet && noSet.has(baseId) && isNo($inp)) {
        log('NO counsel on field', baseId);
        onCounsel(baseId);
        return;
      }
    });

    // Make field 940 checkboxes behave like radio buttons
    $(document).on('change.nmec-radio', 'input[type="checkbox"][name*="input_' + ARREST_FIELD + '"]', function(){
      if ($(this).is(':checked')) {
        var fieldName = $(this).attr('name');
        $('input[type="checkbox"][name*="input_' + ARREST_FIELD + '"]').not(this).each(function() {
          $(this).prop('checked', false).trigger('change');
        });
        console.log('Unchecked other checkboxes in field ' + ARREST_FIELD);
      }
    });

    // Back button - closes modal and resets field, but tracks that confirmation is needed
    $(document).on('click.nmec-back', '.nme-back', function(){
      if (currentFieldId !== null) {
        fieldsNeedingConfirmation.add(currentFieldId);
        console.log('Added field ' + currentFieldId + ' to confirmation needed list');
      }
      closeModal(true);
    });

    // Confirm button - shows confirmation message (with arrest check)
    $(document).on('click.nmec-confirm', '.nme-confirm', function(){
      if (currentFieldId !== null) {
        fieldsNeedingConfirmation.delete(currentFieldId);
        console.log('Removed field ' + currentFieldId + ' from confirmation needed list');
      }
      showConfirmation();
    });

    // Acknowledge button - closes modal and resets field
    $(document).on('click.nmec-ack', '.nme-ack', function(){
      closeModal(true);
    });

    registerNavigationInterceptor();
  });

  // Show confirmation when trying to navigate with unconfirmed answers
  function showNavigationConfirmation($targetLink) {
    var unconfirmedFields = Array.from(fieldsNeedingConfirmation);
    
    var questionsHtml = '<div style="margin-bottom:1.5em; padding-bottom:1em; border-bottom:2px solid #ddd;">';
    questionsHtml += '<p style="margin:0 0 0.5em 0;"><strong>You have not confirmed your answers to the following question(s):</strong></p>';
    questionsHtml += '<ul style="margin:0.5em 0 0 0; padding-left:1.5em;">';
    
    unconfirmedFields.forEach(function(fieldId) {
      var fieldLabel = '';
      var $field = $('#field_' + FORM_ID + '_' + fieldId);
      if ($field.length) {
        var $label = $field.find('.gfield_label');
        if ($label.length) {
          fieldLabel = $label.text().trim();
        }
      }
      
      if (!fieldLabel) {
        fieldLabel = 'Field #' + fieldId;
      }
      
      questionsHtml += '<li>' + fieldLabel + '</li>';
    });
    
    questionsHtml += '</ul>';
    questionsHtml += '</div>';
    
    var hasArrestField = unconfirmedFields.indexOf(ARREST_FIELD) !== -1;
    var useArrestMessage = false;
    
    if (hasArrestField) {
      useArrestMessage = hasArrestText();
    }
    
    var message = useArrestMessage ? ARREST_BOUNCE_MSG : CONFIRM_MSG;
    
    var html =
      '<div class="nme-content">' + questionsHtml + message + '</div>' +
      '<div class="nme-actions">' +
      '<button class="nme-btn nme-btn-back nme-cancel-nav">Stay on Page</button>' +
      '<button class="nme-btn nme-continue-nav" data-target-stored="true">I understand, Continue</button>' +
      '</div>';
    
    openModal(html, true);
    
    var storedTarget = $targetLink;
    
    $(document).off('click.nmec-cancel-nav').on('click.nmec-cancel-nav', '.nme-cancel-nav', function(){
      closeModal(true);
    });
    
    $(document).off('click.nmec-continue-nav').on('click.nmec-continue-nav', '.nme-continue-nav', function(){
      console.log('Continue clicked, clearing all unconfirmed fields:', unconfirmedFields);
      
      unconfirmedFields.forEach(function(fid) {
        fieldsNeedingConfirmation.delete(fid);
      });
      
      closeModal(true);
      
      console.log('User confirmed, proceeding with navigation');
      
      setTimeout(function(){
        console.log('Attempting navigation with target:', storedTarget[0]);
        console.log('Target element:', storedTarget.prop('tagName'), storedTarget.attr('class'));
        
        var originalOnclick = storedTarget.data('original-onclick');
        if (originalOnclick) {
          console.log('Found GF onclick handler:', originalOnclick);
          storedTarget.off('click.nmec-gf');
          
          try {
            if (originalOnclick.indexOf('gform.submission.handleButtonClick') !== -1) {
              if (typeof gform !== 'undefined' && gform.submission && gform.submission.handleButtonClick) {
                console.log('Calling gform.submission.handleButtonClick directly');
                gform.submission.handleButtonClick(storedTarget[0]);
              } else {
                console.error('gform.submission.handleButtonClick not found');
              }
            } else {
              var fn = new Function(originalOnclick);
              fn.call(storedTarget[0]);
            }
          } catch(e) {
            console.error('Error executing GF handler:', e);
          }
          
          setTimeout(function(){
            interceptGravityFormsButtons();
          }, 1000);
        } else {
          console.log('No GF handler, treating as regular navigation');
          
          $(document).off('click.nmec-nav');
          console.log('Disabled general navigation interceptor');
          
          var href = storedTarget.attr('href');
          var isHashNavigation = href && href.indexOf('#') === 0;
          
          if (href && !isHashNavigation) {
            console.log('Navigating to href:', href);
            window.location.href = href;
          } else if (isHashNavigation) {
            console.log('Hash navigation to:', href);
            setTimeout(function(){
              console.log('Triggering click on hash link');
              storedTarget[0].click();
              
              setTimeout(function(){
                console.log('Re-enabling general navigation interceptor after hash nav');
                registerNavigationInterceptor();
              }, 500);
            }, 100);
          } else {
            console.log('Triggering native click on element');
            setTimeout(function(){
              storedTarget[0].click();
              
              setTimeout(function(){
                console.log('Re-enabling general navigation interceptor');
                registerNavigationInterceptor();
              }, 1000);
            }, 50);
          }
        }
      }, 300);
    });
  }

  function registerNavigationInterceptor() {
    $(document).on('click.nmec-nav', 'a, button, input[type="button"], input[type="submit"], .nme-nav-button', function(e){
      if ($(this).hasClass('nme-back') || $(this).hasClass('nme-confirm') || 
          $(this).hasClass('nme-ack') || $(this).hasClass('nme-cancel-nav') || 
          $(this).hasClass('nme-continue-nav')) {
        return;
      }
      
      if ($(this).data('nmec-intercepted')) {
        return;
      }
      
      if ($(this).closest('.gfield').length && !$(this).hasClass('gform_next_button') && !$(this).hasClass('gform_previous_button')) {
        return;
      }
      
      if (fieldsNeedingConfirmation.size > 0) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        console.log('Navigation blocked - showing confirmation for unconfirmed fields:', Array.from(fieldsNeedingConfirmation));
        
        var fieldId = Array.from(fieldsNeedingConfirmation)[0];
        currentFieldId = fieldId;
        
        var $clickedLink = $(this);
        
        showNavigationConfirmation($clickedLink);
        
        return false;
      }
    });
  }

})(jQuery, window, document);
