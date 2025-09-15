# B2B Commerce - Quote/Inquiry Button Fix

## Issue Description
The "Request a Quote" and "Product Inquiry" buttons were showing on ALL products regardless of whether the product was intended for wholesale/B2B customers or regular customers.

## Solution Implemented

### 1. Added Product-Specific Controls
- **Quote/Inquiry Button Control**: Added a new product meta field `_b2b_show_quote_buttons` that allows administrators to control whether these buttons should appear for specific products.
- **Default Behavior**: By default, buttons are shown for B2B customers on products they can access.

### 2. Enhanced Button Visibility Logic
The buttons now check multiple conditions before displaying:

1. **User Authentication**: User must be logged in
2. **B2B Role Check**: User must have a B2B role (`b2b_customer`, `wholesale_customer`, `distributor`, `retailer`)
3. **Product Access Check**: User must have permission to view/purchase the product based on:
   - `_b2b_wholesale_only` setting
   - `_b2b_visible_roles` setting
   - `_b2b_visible_groups` setting
4. **Button-Specific Setting**: Product must have `_b2b_show_quote_buttons` set to 'yes' (or not set, which defaults to 'yes')

### 3. Files Modified

#### `includes/ProductManager.php`
- Added `should_show_b2b_buttons()` helper function
- Updated `product_inquiry_button()` to use the helper function
- Added quote button control field to product admin panel
- Updated save function to handle the new field

#### `includes/AdvancedFeatures.php`
- Updated `quote_request_button()` to use the helper function

#### `includes/PricingManager.php`
- Updated `price_request_button()` to use the helper function

### 4. Admin Panel Changes
In the product edit page, under the "B2B Settings" section, administrators can now:
- Control product visibility restrictions (existing functionality)
- **NEW**: Control whether quote/inquiry buttons should be shown for B2B customers

### 5. How It Works

#### For Regular Products (Non-Wholesale)
- Quote/Inquiry buttons will NOT appear for regular customers
- Buttons will only appear for B2B customers if they have access to the product

#### For Wholesale-Only Products
- Quote/Inquiry buttons will only appear for wholesale customers
- Regular customers won't see these buttons

#### For Products with Role Restrictions
- Quote/Inquiry buttons will only appear for users with the specified roles
- Users without the required roles won't see these buttons

#### For Products with Explicit Button Disabling
- If `_b2b_show_quote_buttons` is set to 'no', buttons won't appear for anyone
- This allows fine-grained control over which products should have quote/inquiry functionality

## Benefits
1. **Better User Experience**: Regular customers won't see B2B-specific buttons on regular products
2. **Improved Security**: B2B functionality is properly restricted to appropriate users
3. **Flexible Control**: Administrators can control button visibility on a per-product basis
4. **Consistent Behavior**: All quote/inquiry buttons now follow the same visibility rules

## Testing
To test the fix:
1. Create a regular product (no B2B restrictions)
2. Create a wholesale-only product
3. Log in as a regular customer and verify no quote/inquiry buttons appear
4. Log in as a B2B customer and verify buttons only appear on appropriate products
5. Use the admin panel to disable quote buttons on specific products and verify they don't appear
