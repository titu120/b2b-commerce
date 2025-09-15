/**
 * B2B Commerce Frontend JavaScript
 * Free Version - Comprehensive B2B functionality
 */

jQuery(document).ready(function($) {
    'use strict';

    // Initialize B2B Commerce
    B2BCommerce.init();

    // B2B Quote Request Form
    $('.b2b-quote-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var button = form.find('button[type="submit"]');
        var originalText = button.text();
        
        // Show loading state
        button.text('Sending...').prop('disabled', true);
        
        var formData = new FormData(this);
        formData.append('action', 'b2b_quote_request');
        formData.append('nonce', b2b_ajax.nonce);
        
        $.ajax({
            url: b2b_ajax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    form.html('<div class="b2b-message success">' + response.data + '</div>');
                } else {
                    form.html('<div class="b2b-message error">' + response.data + '</div>');
                }
            },
            error: function() {
                form.html('<div class="b2b-message error">An error occurred. Please try again.</div>');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });

    // B2B Product Inquiry Form
    $('.b2b-inquiry-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var button = form.find('button[type="submit"]');
        var originalText = button.text();
        
        // Show loading state
        button.text('Sending...').prop('disabled', true);
        
        var formData = new FormData(this);
        formData.append('action', 'b2b_product_inquiry');
        formData.append('nonce', b2b_ajax.nonce);
        
        $.ajax({
            url: b2b_ajax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    form.html('<div class="b2b-message success">' + response.data + '</div>');
                } else {
                    form.html('<div class="b2b-message error">' + response.data + '</div>');
                }
            },
            error: function() {
                form.html('<div class="b2b-message error">An error occurred. Please try again.</div>');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });

    // B2B Registration Form
    $('.b2b-registration-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var button = form.find('button[type="submit"]');
        var originalText = button.text();
        
        // Basic validation
        var requiredFields = form.find('[required]');
        var isValid = true;
        
        requiredFields.each(function() {
            if (!$(this).val().trim()) {
                $(this).addClass('error');
                isValid = false;
            } else {
                $(this).removeClass('error');
            }
        });
        
        if (!isValid) {
            alert('Please fill in all required fields.');
            return;
        }
        
        // Show loading state
        button.text('Creating Account...').prop('disabled', true);
        
        var formData = new FormData(this);
        formData.append('action', 'b2b_register_user');
        formData.append('nonce', b2b_ajax.nonce);
        
        $.ajax({
            url: b2b_ajax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    form.html('<div class="b2b-message success">' + response.data + '</div>');
                } else {
                    form.html('<div class="b2b-message error">' + response.data + '</div>');
                }
            },
            error: function() {
                form.html('<div class="b2b-message error">An error occurred. Please try again.</div>');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });

    // Toggle quote/inquiry forms
    $('.b2b-quote-button').on('click', function(e) {
        e.preventDefault();
        var productId = $(this).data('product-id');
        var form = $('#b2b-quote-form-' + productId);
        
        if (form.length) {
            form.slideToggle();
        }
    });

    $('.b2b-inquiry-button').on('click', function(e) {
        e.preventDefault();
        var productId = $(this).data('product-id');
        var form = $('#b2b-inquiry-form-' + productId);
        
        if (form.length) {
            form.slideToggle();
        }
    });

    // Close forms
    $('.b2b-close-form').on('click', function(e) {
        e.preventDefault();
        $(this).closest('.b2b-form').slideUp();
    });

    // B2B User Management
    $('.b2b-approve-user').on('click', function(e) {
        e.preventDefault();
        var userId = $(this).data('user-id');
        var button = $(this);
        var originalText = button.text();
        
        if (!confirm('Are you sure you want to approve this user?')) {
            return;
        }
        
        button.text('Approving...').prop('disabled', true);
        
        $.ajax({
            url: b2b_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'b2b_approve_user',
                user_id: userId,
                nonce: b2b_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    button.closest('tr').fadeOut();
                    B2BCommerce.showMessage('User approved successfully!', 'success');
                } else {
                    B2BCommerce.showMessage('Error: ' + response.data, 'error');
                }
            },
            error: function() {
                B2BCommerce.showMessage('An error occurred. Please try again.', 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });

    $('.b2b-reject-user').on('click', function(e) {
        e.preventDefault();
        var userId = $(this).data('user-id');
        var button = $(this);
        var originalText = button.text();
        
        if (!confirm('Are you sure you want to reject this user?')) {
            return;
        }
        
        button.text('Rejecting...').prop('disabled', true);
        
        $.ajax({
            url: b2b_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'b2b_reject_user',
                user_id: userId,
                nonce: b2b_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    button.closest('tr').fadeOut();
                    B2BCommerce.showMessage('User rejected successfully!', 'success');
                } else {
                    B2BCommerce.showMessage('Error: ' + response.data, 'error');
                }
            },
            error: function() {
                B2BCommerce.showMessage('An error occurred. Please try again.', 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });

    // B2B Pricing Rules Management
    $('.b2b-save-pricing-rule').on('click', function(e) {
        e.preventDefault();
        var form = $(this).closest('form');
        var button = $(this);
        var originalText = button.text();
        
        button.text('Saving...').prop('disabled', true);
        
        var formData = new FormData(form[0]);
        formData.append('action', 'b2b_save_pricing_rule');
        formData.append('nonce', b2b_ajax.nonce);
        
        $.ajax({
            url: b2b_ajax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    B2BCommerce.showMessage('Pricing rule saved successfully!', 'success');
                    form[0].reset();
                } else {
                    B2BCommerce.showMessage('Error: ' + response.data, 'error');
                }
            },
            error: function() {
                B2BCommerce.showMessage('An error occurred. Please try again.', 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });

    $('.b2b-delete-pricing-rule').on('click', function(e) {
        e.preventDefault();
        var ruleId = $(this).data('rule-id');
        var button = $(this);
        var originalText = button.text();
        
        if (!confirm('Are you sure you want to delete this pricing rule?')) {
            return;
        }
        
        button.text('Deleting...').prop('disabled', true);
        
        $.ajax({
            url: b2b_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'b2b_delete_pricing_rule',
                rule_id: ruleId,
                nonce: b2b_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    button.closest('tr').fadeOut();
                    B2BCommerce.showMessage('Pricing rule deleted successfully!', 'success');
                } else {
                    B2BCommerce.showMessage('Error: ' + response.data, 'error');
                }
            },
            error: function() {
                B2BCommerce.showMessage('An error occurred. Please try again.', 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });

    // B2B Export Data
    $('.b2b-export-data').on('click', function(e) {
        e.preventDefault();
        var type = $(this).data('type');
        var button = $(this);
        var originalText = button.text();
        
        button.text('Exporting...').prop('disabled', true);
        
        $.ajax({
            url: b2b_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'b2b_export_data',
                type: type,
                nonce: b2b_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Create and download CSV file
                    var blob = new Blob([response.data], {type: 'text/csv'});
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'b2b_' + type + '_' + new Date().toISOString().slice(0,10) + '.csv';
                    a.click();
                    window.URL.revokeObjectURL(url);
                    B2BCommerce.showMessage('Data exported successfully!', 'success');
                } else {
                    B2BCommerce.showMessage('Error: ' + response.data, 'error');
                }
            },
            error: function() {
                B2BCommerce.showMessage('An error occurred. Please try again.', 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });


    // Initialize tooltips if available
    if (typeof $.fn.tooltip === 'function') {
        $('[data-tooltip]').tooltip();
    }

    // Initialize select2 if available
    if (typeof $.fn.select2 === 'function') {
        $('.b2b-select2').select2({
            placeholder: 'Select an option...',
            allowClear: true
        });
    }

    // Initialize data tables if available
    if (typeof $.fn.DataTable === 'function') {
        $('.b2b-data-table').DataTable({
            pageLength: 25,
            responsive: true,
            order: [[0, 'desc']]
        });
    }
});

// Global B2B Commerce functions
window.B2BCommerce = {
    init: function() {
        console.log('B2B Commerce initialized');
        this.bindEvents();
    },
    
    bindEvents: function() {
        // Additional event bindings can be added here
    },
    
    showMessage: function(message, type) {
        type = type || 'info';
        var messageHtml = '<div class="b2b-message ' + type + '">' + message + '</div>';
        $('body').prepend(messageHtml);
        
        setTimeout(function() {
            $('.b2b-message').fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    },
    
    formatPrice: function(price) {
        if (typeof wc_price === 'function') {
            return wc_price(price);
        }
        return '$' + parseFloat(price).toFixed(2);
    },
    
    validateEmail: function(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },
    
    formatDate: function(date) {
        var d = new Date(date);
        return d.toLocaleDateString();
    },
    
    formatCurrency: function(amount, currency) {
        currency = currency || 'USD';
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency
        }).format(amount);
    }
};