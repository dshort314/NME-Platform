(function($) {
    'use strict';
    
    $(document).ready(function() {
        initTooltips();
    });
    
    function initTooltips() {
        // Handle tooltip trigger interactions (click only)
        $(document).on('click', '.gf-tooltip-trigger', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $trigger = $(this);
            var $container = $trigger.siblings('.gf-tooltip-container');
            
            if ($container.length) {
                // Toggle active class
                $trigger.toggleClass('active');
                $container.toggleClass('active');
                
                // Close other open tooltips
                $('.gf-tooltip-trigger').not($trigger).removeClass('active');
                $('.gf-tooltip-container').not($container).removeClass('active');
            }
        });
        
        // Handle keyboard accessibility
        $(document).on('keydown', '.gf-tooltip-trigger', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).trigger('click');
            }
        });
        
        // Close tooltip when clicking outside
        $(document).on('click', function(e) {
            var $target = $(e.target);
            
            // Don't close if clicking on a tooltip trigger or container
            if ($target.hasClass('gf-tooltip-trigger') || 
                $target.closest('.gf-tooltip-container').length ||
                $target.closest('.gfield_label').length) {
                return;
            }
            
            // Close all active tooltips
            $('.gf-tooltip-trigger').removeClass('active');
            $('.gf-tooltip-container').removeClass('active');
        });
        
        // Make tooltip triggers accessible
        $('.gf-tooltip-trigger').each(function() {
            var $trigger = $(this);
            
            if (!$trigger.attr('tabindex')) {
                $trigger.attr('tabindex', '0');
            }
            if (!$trigger.attr('role')) {
                $trigger.attr('role', 'button');
            }
            if (!$trigger.attr('aria-label')) {
                $trigger.attr('aria-label', 'Show guidance');
            }
            if (!$trigger.attr('aria-expanded')) {
                $trigger.attr('aria-expanded', 'false');
            }
        });
        
        // Update aria-expanded when tooltips are toggled
        $(document).on('click', '.gf-tooltip-trigger', function() {
            var $trigger = $(this);
            var isActive = $trigger.hasClass('active');
            $trigger.attr('aria-expanded', isActive ? 'true' : 'false');
        });
        
        // Handle tooltip positioning for edge cases
        repositionTooltips();
        $(window).on('resize', debounce(repositionTooltips, 250));
    }
    
    function repositionTooltips() {
        $('.gf-tooltip-container').each(function() {
            var $container = $(this);
            var $trigger = $container.siblings('.gf-tooltip-trigger');
            
            if (!$trigger.length) return;
            
            // Reset position
            $container.css({
                'left': '50%',
                'transform': 'translateX(-50%)'
            });
            
            // Check if tooltip goes off screen
            var containerRect = $container[0].getBoundingClientRect();
            var viewportWidth = window.innerWidth;
            
            if (containerRect.left < 10) {
                // Too far left
                $container.css({
                    'left': '0',
                    'transform': 'translateX(0)'
                });
            } else if (containerRect.right > viewportWidth - 10) {
                // Too far right
                $container.css({
                    'left': 'auto',
                    'right': '0',
                    'transform': 'translateX(0)'
                });
            }
        });
    }
    
    // Debounce function for resize events
    function debounce(func, wait) {
        var timeout;
        return function executedFunction() {
            var context = this;
            var args = arguments;
            var later = function() {
                timeout = null;
                func.apply(context, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Handle tooltips in dynamically loaded content (like multi-page forms)
    $(document).on('gform_page_loaded', function() {
        setTimeout(function() {
            // Re-initialize tooltips for new page content
            $('.gf-tooltip-trigger').each(function() {
                var $trigger = $(this);
                
                if (!$trigger.attr('tabindex')) {
                    $trigger.attr('tabindex', '0');
                }
                if (!$trigger.attr('role')) {
                    $trigger.attr('role', 'button');
                }
                if (!$trigger.attr('aria-label')) {
                    $trigger.attr('aria-label', 'Show guidance');
                }
                if (!$trigger.attr('aria-expanded')) {
                    $trigger.attr('aria-expanded', 'false');
                }
            });
            
            repositionTooltips();
        }, 100);
    });
    
    // Handle AJAX form submissions and conditional logic
    $(document).on('gform_post_conditional_logic', function() {
        setTimeout(repositionTooltips, 100);
    });
    
})(jQuery);
