# B2B Commerce - Bulk Calculator Control

## 🎯 **New Feature: Bulk Calculator Visibility Control**

### **Overview**
The B2B Commerce plugin now includes a new product-level setting that allows administrators to control whether the bulk pricing calculator is displayed for each individual product.

### **How It Works**

#### **Admin Control**
1. **Access Product Settings**: Go to **WordPress Admin → Products → All Products**
2. **Edit Product**: Click "Edit" on any product
3. **Find B2B Settings**: Scroll down to the **"B2B Commerce"** section
4. **Control Visibility**: Look for the **"Show Bulk Pricing Calculator"** checkbox

#### **Settings Available**
- ✅ **Checked (Default)**: Bulk pricing calculator is shown to B2B customers
- ❌ **Unchecked**: Bulk pricing calculator is hidden for this product

### **User Experience**

#### **For B2B Customers**
- **When Enabled**: Customers see the bulk pricing calculator on the product page
- **When Disabled**: The calculator is completely hidden from the product page
- **Default Behavior**: Calculator is shown by default (unless explicitly disabled)

#### **For Regular Customers**
- The bulk pricing calculator is never shown (B2B feature only)

### **Technical Implementation**

#### **Product Meta Field**
- **Field Name**: `_b2b_show_bulk_calculator`
- **Values**: `'yes'` (default) or `'no'`
- **Storage**: Stored as product meta data

#### **Visibility Logic**
The bulk calculator is shown only when ALL conditions are met:
1. ✅ User is logged in
2. ✅ User has a B2B role (b2b_customer, wholesale_customer, distributor, retailer)
3. ✅ User has permission to view the product (B2B restrictions)
4. ✅ Bulk calculator is enabled for this product
5. ✅ Product has tiered pricing rules configured

### **Use Cases**

#### **When to Disable Bulk Calculator**
- **Simple Products**: Products with no tiered pricing
- **Custom Pricing**: Products with complex pricing that doesn't fit the calculator
- **Quote-Only Products**: Products where customers should request quotes instead
- **Limited Stock**: Products with very limited availability

#### **When to Enable Bulk Calculator**
- **Tiered Pricing Products**: Products with quantity-based discounts
- **Bulk Products**: Products designed for wholesale/volume purchases
- **Self-Service**: When you want customers to calculate prices themselves

### **Integration with Existing Features**

#### **Works with Quote/Inquiry Buttons**
- Both features can be controlled independently
- You can show calculator but hide quote buttons (or vice versa)
- Each setting has its own checkbox in the product admin

#### **Works with B2B Restrictions**
- Calculator respects product visibility restrictions
- Only shown to users who can access the product
- Follows the same permission logic as other B2B features

### **Admin Interface**

#### **Product Edit Page**
```
┌─ B2B Commerce ──────────────────────────────┐
│ ☑ Show Quote/Inquiry Buttons               │
│ ☑ Show Bulk Pricing Calculator             │ ← New Option
│                                             │
│ Restrict Visibility: ☑ Enable to limit...  │
│ Visible to Roles: [administrator, ...]     │
│ Visible to Groups: [Choose groups...]      │
│ ☐ Only visible to wholesale users          │
└─────────────────────────────────────────────┘
```

#### **Default Behavior**
- **New Products**: Bulk calculator is enabled by default
- **Existing Products**: Bulk calculator is enabled by default
- **Migration**: No action required for existing products

### **Code Examples**

#### **Check if Calculator Should be Shown**
```php
// In your theme or custom code
if (class_exists('B2B\\ProductManager')) {
    $product_manager = new ProductManager();
    $should_show = $product_manager->should_show_bulk_calculator($product_id);
    
    if ($should_show) {
        // Show bulk calculator
    }
}
```

#### **Get Setting Value**
```php
$show_calculator = get_post_meta($product_id, '_b2b_show_bulk_calculator', true);
if ($show_calculator !== 'no') {
    // Calculator is enabled (default behavior)
}
```

### **Benefits**

#### **For Administrators**
- **Granular Control**: Control calculator visibility per product
- **Better UX**: Hide calculator for products where it's not relevant
- **Flexibility**: Mix and match with other B2B features

#### **For Customers**
- **Cleaner Interface**: Only see calculator when it's useful
- **Better Experience**: No confusion with irrelevant calculators
- **Focused Features**: See only relevant tools for each product

### **Best Practices**

#### **Recommended Settings**
- **Enable for**: Products with tiered pricing, bulk products, wholesale items
- **Disable for**: Simple products, custom pricing products, quote-only items

#### **Testing**
1. **Test with Different Roles**: Verify calculator shows/hides correctly
2. **Test with Restrictions**: Ensure calculator respects B2B restrictions
3. **Test Default Behavior**: Verify new products have calculator enabled

### **Troubleshooting**

#### **Calculator Not Showing**
- ✅ Check if user is logged in
- ✅ Verify user has B2B role
- ✅ Confirm product has tiered pricing rules
- ✅ Check if calculator is enabled for this product
- ✅ Verify user has permission to view the product

#### **Calculator Showing When Disabled**
- ✅ Check if setting was saved correctly
- ✅ Verify no caching issues
- ✅ Check if theme is overriding the setting

### **Future Enhancements**

#### **Potential Features**
- **Global Setting**: Site-wide default for new products
- **Category Control**: Enable/disable by product category
- **Role-Based Control**: Different settings for different B2B roles
- **Conditional Logic**: Show based on product attributes

---

## 📞 **Support**

If you need help with the bulk calculator control feature:
1. Check the product settings in the admin panel
2. Verify user roles and permissions
3. Test with different user accounts
4. Contact support if issues persist

**Remember**: This feature gives you complete control over when and where the bulk pricing calculator appears!
