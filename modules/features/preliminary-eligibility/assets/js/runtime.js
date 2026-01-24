(function($, window, document) {
  'use strict';

  var CFG = window.NME_Prelim_Settings || {};
  var FORM_ID = parseInt(CFG.form_id || 0, 10);

  var YES_IDS = Array.isArray(CFG.yes_fields) ? CFG.yes_fields.map(Number) : [];
  var NO_IDS = Array.isArray(CFG.no_fields) ? CFG.no_fields.map(Number) : [];
  var COMPLEX = Array.isArray(CFG.complex) ? CFG.complex.map(Number) : [];
  var CODE_IDS = Array.isArray(CFG.code_fields) ? CFG.code_fields.map(Number) : [];
  var CODE_THR = parseInt(CFG.code_thresh || 12, 10);

  var SEV_MAP = (CFG.severity_map && typeof CFG.severity_map === 'object') ? CFG.severity_map : {};
  var DEFAULT_SEV = (CFG.default_severity || 'C1').toUpperCase();

  var OVERRIDES = (CFG.overrides && typeof CFG.overrides === 'object') ? CFG.overrides : {};
  var C1_DEFAULT = CFG.c1_default || '';
  var C2_DEFAULT = CFG.c2_default || '';

  var LABEL_TARGETS = CFG.label_targets || {};

  if (!FORM_ID) return;

  // Calculate USC date: today + 1 year + 1 day
  function calculateUSCDate() {
    var today = new Date();
    var targetDate = new Date(today.getFullYear() + 1, today.getMonth(), today.getDate() + 1);
    var month = String(targetDate.getMonth() + 1).padStart(2, '0');
    var day = String(targetDate.getDate()).padStart(2, '0');
    return month + '/' + day + '/' + targetDate.getFullYear();
  }

  // Apply label replacements
  function applyLabelReplacements() {
    var calculatedDate = calculateUSCDate();

    Object.keys(LABEL_TARGETS).forEach(function(fieldId) {
      var target = LABEL_TARGETS[fieldId];
      var $label = $('#field_' + FORM_ID + '_' + fieldId + ' .gfield_label, #field_' + FORM_ID + '_' + fieldId + ' label.gfield_label').first();
      if (!$label.length) return;

      var $required = $label.find('.gfield_required').first();
      var requiredHTML = $required.length ? $required.prop('outerHTML') : '';
      var finalLabelHTML;

      if (target.template) {
        finalLabelHTML = target.template.replace(/\[USC_CALCULATED_DATE\]/g, calculatedDate);
      } else if (target.html) {
        finalLabelHTML = target.html;
      } else {
        return;
      }

      $label.html(finalLabelHTML + (requiredHTML ? ' ' + requiredHTML : ''));
    });
  }

  // Modal styles
  function ensureStyles() {
    if (document.getElementById('nme-prelim-inline-styles')) return;
    var css = ''
      + '#nmePrelimOv{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9998;display:flex;align-items:center;justify-content:center;}'
      + '#nmePrelimMd{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:28px;border-radius:10px;box-shadow:0 10px 40px rgba(0,0,0,.25);z-index:9999;max-width:720px;width:92%;max-height:80vh;overflow:auto;font-size:16px;line-height:1.5;}'
      + '#nmePrelimMd p{margin:0 0 1em;}'
      + '#nmePrelimMd ul,#nmePrelimMd ol{margin:0 0 1em 1.25em;}'
      + '#nmePrelimMd a{text-decoration:underline;}'
      + '#nmePrelimMd strong,b{font-weight:600;}'
      + '.nme-actions{display:flex;gap:12px;justify-content:center;margin-top:14px;flex-wrap:wrap;}'
      + '#nmePrelimMd .nme-btn{background:#0a62a3;color:#fff;border:0;padding:10px 18px;border-radius:8px;cursor:pointer;display:inline-block;box-shadow:0 1px 0 rgba(0,0,0,.1);}'
      + '#nmePrelimMd .nme-btn:hover{background:#084f83;}'
      + '#gform_' + FORM_ID + ' .gform_next_button{display:inline-block !important;}';
    var style = document.createElement('style');
    style.id = 'nme-prelim-inline-styles';
    style.appendChild(document.createTextNode(css));
    document.head.appendChild(style);
  }

  function closeModal() {
    $('#nmePrelimOv,#nmePrelimMd').remove();
  }

  function openModal(html) {
    ensureStyles();
    closeModal();
    var $ov = $('<div id="nmePrelimOv"></div>').appendTo('body');
    var $md = $('<div id="nmePrelimMd"></div>').html(html).appendTo('body');
    function closeAll() { $ov.remove(); $md.remove(); }
    $ov.on('click', closeAll);
    $md.on('click', '.nme-ack', closeAll);
  }

  function baseId($el) {
    var name = String($el.attr('name') || '');
    var m = name.match(/^input_(\d+)/);
    return m ? parseInt(m[1], 10) : null;
  }

  // Custom field detection for non-standard answers
  function isCustomYesChoice($el, fid) {
    if (!$el.is(':checked')) return false;
    var val = String($el.val() || '').toLowerCase();
    var id = $el.attr('id');
    var $lab = id ? $('label[for="' + id + '"]') : $();
    var text = String($lab.text() || '').toLowerCase();

    // Field 87: "Currently" triggers
    if (fid === 87) {
      return text.indexOf('currently') !== -1 || val.indexOf('currently') !== -1;
    }
    return false;
  }

  function isCustomNoChoice($el, fid) {
    if (!$el.is(':checked')) return false;
    var val = String($el.val() || '').toLowerCase();
    var id = $el.attr('id');
    var $lab = id ? $('label[for="' + id + '"]') : $();
    var text = String($lab.text() || '').toLowerCase();

    // Field 79: "disability" triggers
    if (fid === 79) {
      return text.indexOf('disability') !== -1 || text.indexOf('impairment') !== -1;
    }

    // Fields 82, 83: Second radio button position
    if (fid === 82 || fid === 83) {
      var $allRadios = $('input[name="' + $el.attr('name') + '"]');
      return $allRadios.index($el) === 1;
    }

    // Field 84: "don't agree"
    if (fid === 84) {
      return text.indexOf("don't agree") !== -1 || text.indexOf("disagree") !== -1;
    }

    // Field 91: "retain counsel"
    if (fid === 91) {
      return text.indexOf("retain counsel") !== -1 || text.indexOf("ought to retain") !== -1;
    }

    return false;
  }

  function isYesChoice($el) {
    var tag = $el.prop('tagName');
    if (tag === 'SELECT') {
      var opt = $el.find('option:selected');
      var v = String(opt.val() || '').toLowerCase();
      var t = String(opt.text() || '').toLowerCase();
      return v === 'yes' || v === 'true' || t === 'yes' || (v === '1' && t.indexOf('yes') !== -1);
    }
    if ($el.is(':radio') || $el.is(':checkbox')) {
      if (!$el.is(':checked')) return false;
      var v = String($el.val() || '').toLowerCase();
      var id = $el.attr('id');
      var $lab = id ? $('label[for="' + id + '"]') : $();
      var t = String($lab.text() || '').toLowerCase();
      return v === 'yes' || v === 'true' || t === 'yes' || (v === '1' && t.indexOf('yes') !== -1);
    }
    return false;
  }

  function isNoChoice($el) {
    var tag = $el.prop('tagName');
    if (tag === 'SELECT') {
      var opt = $el.find('option:selected');
      var v = String(opt.val() || '').toLowerCase();
      var t = String(opt.text() || '').toLowerCase();
      return v === 'no' || v === 'false' || t === 'no' || (v === '0' && t.indexOf('no') !== -1);
    }
    if ($el.is(':radio') || $el.is(':checkbox')) {
      if (!$el.is(':checked')) return false;
      var v = String($el.val() || '').toLowerCase();
      var id = $el.attr('id');
      var $lab = id ? $('label[for="' + id + '"]') : $();
      var t = String($lab.text() || '').toLowerCase();
      return v === 'no' || v === 'false' || t === 'no' || (v === '0' && t.indexOf('no') !== -1);
    }
    return false;
  }

  function fallbackMessage() {
    return DEFAULT_SEV === 'C2' ? (C2_DEFAULT || C1_DEFAULT) : (C1_DEFAULT || C2_DEFAULT);
  }

  function messageFor(fid, isYes, isCode) {
    var o = OVERRIDES[fid];
    var complex = COMPLEX.indexOf(fid) !== -1;

    // Per-field overrides
    if (o) {
      if (complex) {
        if (isYes && o.yes) return o.yes;
        if (!isYes && o.no) return o.no;
      } else {
        if (o.both) return o.both;
      }
    }

    // Severity-based default
    var sev = (SEV_MAP && SEV_MAP[fid]) ? String(SEV_MAP[fid]).toUpperCase() : DEFAULT_SEV;
    if (sev === 'C2') return C2_DEFAULT || fallbackMessage();
    return C1_DEFAULT || fallbackMessage();
  }

  function onTrigger(fid, isYes, isCode) {
    var html = '<div class="nme-content">' + messageFor(fid, isYes, isCode) + '</div>'
             + '<div class="nme-actions"><button class="nme-btn nme-ack">I understand</button></div>';
    openModal(html);
  }

  $(function() {
    var $form = $('#gform_' + FORM_ID);
    if (!$form.length) return;

    ensureStyles();
    applyLabelReplacements();

    // Re-apply labels when LPR date changes
    $(document).on('change', '#input_' + FORM_ID + '_65', applyLabelReplacements);

    // Remove foreign widgets
    $('.sr-conditional-submit,[data-sr-conditional-submit]').remove();

    // Change handler for radios/checks/selects
    $(document).on('change.nmeprelim',
      '#gform_' + FORM_ID + ' input[name^="input_"], #gform_' + FORM_ID + ' select[name^="input_"]',
      function() {
        var $el = $(this);
        var fid = baseId($el);
        if (!fid) return;

        var inYes = YES_IDS.indexOf(fid) !== -1;
        var inNo = NO_IDS.indexOf(fid) !== -1;
        var inComplex = COMPLEX.indexOf(fid) !== -1;

        if (inYes && (isYesChoice($el) || isCustomYesChoice($el, fid))) {
          onTrigger(fid, true, false);
          return;
        }
        if (inNo && (isNoChoice($el) || isCustomNoChoice($el, fid))) {
          onTrigger(fid, false, false);
          return;
        }
        if (inComplex && (isYesChoice($el) || isNoChoice($el) || isCustomYesChoice($el, fid) || isCustomNoChoice($el, fid))) {
          onTrigger(fid, isYesChoice($el) || isCustomYesChoice($el, fid), false);
          return;
        }
      }
    );

    // CODE fields (numeric threshold)
    function handleCodeEval() {
      var $el = $(this);
      var fid = baseId($el);
      if (!fid) return;
      if (CODE_IDS.indexOf(fid) === -1) return;

      var raw = ($el.val() || '').toString().replace(/,/g, '').trim();
      var num = parseFloat(raw);
      if (!isNaN(num) && num > CODE_THR) {
        onTrigger(fid, true, true);
      }
    }

    $(document).on('input.nmeprelim change.nmeprelim',
      '#gform_' + FORM_ID + ' input[name^="input_"]',
      handleCodeEval
    );

    // Re-apply labels after GF page render
    $(document).on('gform_post_render', function(event, form_id) {
      if (form_id == FORM_ID) {
        applyLabelReplacements();
      }
    });
  });

})(jQuery, window, document);
