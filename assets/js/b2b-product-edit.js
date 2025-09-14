jQuery(document).ready(function($) {
    'use strict';
    
    // Add tier functionality
    $(document).on('click', '.add-tier', function() {
        var role = $(this).data('role');
        var container = $('.b2b-tiers-container[data-role="' + role + '"]');
        
        var tierRow = '<div class="b2b-tier-row">' +
            '<input type="number" name="b2b_tier_min_qty[' + role + '][]" placeholder="' + b2b_product_edit.strings.min_qty + '" min="1" style="width: 80px;" title="' + b2b_product_edit.strings.min_qty_tooltip + '">' +
            '<input type="text" name="b2b_tier_price[' + role + '][]" placeholder="' + b2b_product_edit.strings.price_percentage + '" class="wc_input_price tier-price-input" style="width: 100px;" title="' + b2b_product_edit.strings.price_tooltip + '">' +
            '<select name="b2b_tier_type[' + role + '][]" class="tier-type-select" style="width: 100px;" title="' + b2b_product_edit.strings.type_tooltip + '">' +
            '<option value="fixed">' + b2b_product_edit.strings.fixed_price + '</option>' +
            '<option value="percentage">' + b2b_product_edit.strings.percentage + '</option>' +
            '</select>' +
            '<button type="button" class="button remove-tier" style="margin-left: 5px;" title="' + b2b_product_edit.strings.remove_tooltip + '">' + b2b_product_edit.strings.remove + '</button>' +
            '</div>';
        
        container.append(tierRow);
        
        // Add helpful tooltip
        var newRow = container.find('.b2b-tier-row').last();
        newRow.find('.tier-price-input').attr('placeholder', b2b_product_edit.strings.price_example);
    });
    
    // Remove tier functionality
    $(document).on('click', '.remove-tier', function() {
        $(this).closest('.b2b-tier-row').remove();
    });
    
    // Price validation and dynamic placeholder
    $(document).on('blur', '.wc_input_price', function() {
        var value = $(this).val();
        if (value && !isNaN(value)) {
            // Format to 2 decimal places for fixed prices
            $(this).val(parseFloat(value).toFixed(2));
        }
    });
    
    // Dynamic placeholder based on type selection
    $(document).on('change', '.tier-type-select', function() {
        var priceInput = $(this).closest('.b2b-tier-row').find('.tier-price-input');
        var selectedType = $(this).val();
        
        if (selectedType === 'percentage') {
            priceInput.attr('placeholder', b2b_product_edit.strings.percentage_example);
            priceInput.attr('title', b2b_product_edit.strings.percentage_tooltip);
            // Clear the input when switching to percentage to avoid confusion
            priceInput.val('');
        } else {
            priceInput.attr('placeholder', b2b_product_edit.strings.price_example);
            priceInput.attr('title', b2b_product_edit.strings.price_tooltip);
            // Clear the input when switching to fixed price to avoid confusion
            priceInput.val('');
        }
    });
    
    // Validation for percentage values
    $(document).on('input', '.tier-price-input', function() {
        var typeSelect = $(this).closest('.b2b-tier-row').find('.tier-type-select');
        var selectedType = typeSelect.val();
        var value = $(this).val();
        
        if (selectedType === 'percentage' && value) {
            // For percentage, allow decimal numbers up to 2 decimal places
            if (parseFloat(value) > 100) {
                $(this).val(100);
            } else if (parseFloat(value) < 0) {
                $(this).val(0);
            }
            
            // Remove any non-numeric characters except decimal
            value = value.replace(/[^0-9.]/g, '');
            
            // Ensure only one decimal point
            var parts = value.split('.');
            if (parts.length > 2) {
                value = parts[0] + '.' + parts.slice(1).join('');
            }
            
            // Limit to 2 decimal places
            if (parts.length === 2 && parts[1].length > 2) {
                value = parts[0] + '.' + parts[1].substring(0, 2);
            }
            
            $(this).val(value);
        }
    });
    
    // Add visual percentage indicator
    $(document).on('change', '.tier-type-select', function() {
        var priceInput = $(this).closest('.b2b-tier-row').find('.tier-price-input');
        var selectedType = $(this).val();
        
        // Remove existing percentage indicator
        priceInput.removeClass('percentage-input');
        $(this).closest('.b2b-tier-row').find('.percentage-indicator').remove();
        
        if (selectedType === 'percentage') {
            priceInput.addClass('percentage-input');
            // Add percentage indicator
            priceInput.after('<span class="percentage-indicator">%</span>');
        }
    });
    
    // Initialize percentage indicators on page load
    $(document).ready(function() {
        $('.tier-type-select').each(function() {
            if ($(this).val() === 'percentage') {
                var priceInput = $(this).closest('.b2b-tier-row').find('.tier-price-input');
                priceInput.addClass('percentage-input');
                // Add percentage indicator if not already present
                if (priceInput.siblings('.percentage-indicator').length === 0) {
                    priceInput.after('<span class="percentage-indicator">%</span>');
                }
            }
        });
    });
    
    // Show/hide role sections based on product type
    function toggleB2BPricing() {
        var productType = $('#product-type').val();
        if (productType === 'simple' || productType === 'external') {
            $('.b2b-pricing-section').show();
        } else {
            $('.b2b-pricing-section').hide();
        }
    }
    
    // Initial check
    toggleB2BPricing();
    
    // Check on product type change
    $(document).on('change', '#product-type', function() {
        toggleB2BPricing();
    });
    
    // Auto-save warning for unsaved changes
    var hasChanges = false;
    
    $(document).on('change input', '.b2b-pricing-section input, .b2b-pricing-section select', function() {
        hasChanges = true;
    });
    
    // Warn before leaving page with unsaved changes
    $(window).on('beforeunload', function() {
        if (hasChanges) {
            return b2b_product_edit.strings.unsaved_changes_warning;
        }
    });
    
    // Clear warning when form is submitted
    $('form#post').on('submit', function() {
        hasChanges = false;
    });
    
    // Initialize WooCommerce price fields
    if (typeof wc_admin_meta_boxes !== 'undefined') {
        wc_admin_meta_boxes.init_price_fields();
    }
});
