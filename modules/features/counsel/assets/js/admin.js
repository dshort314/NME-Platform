(function($){
    'use strict';

    function syncRow($row) {
        var checked = $row.find('.nme-counsel-toggle').prop('checked');
        $row.find('.nme-counsel-editor-area').toggle(!!checked);
    }

    $(function(){
        // Initial state for all rows
        $('.nme-counsel-table tr').each(function() {
            syncRow($(this));
        });

        // Toggle handler (event delegation)
        $(document).on('change', '.nme-counsel-toggle', function(){
            syncRow($(this).closest('tr'));
        });
    });

})(jQuery);
