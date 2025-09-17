# Quick Reference: Tiered Pricing Interface

## 🎯 **How to Add Tiered Pricing**

### **Step-by-Step Instructions:**

1. **Go to Product Edit Page**
   - WordPress Admin → Products → All Products
   - Click "Edit" on any product
   - Scroll down to "B2B Pricing" section

2. **Set Basic Prices** (Optional)
   - Regular Price: Base price for this customer type
   - Sale Price: Discounted price (optional)

3. **Add Tiered Pricing**
   - Click **"Add Tier"** button
   - Fill in the three fields:
     - **Min Qty**: Minimum quantity (e.g., 5, 10, 50)
     - **Price**: Price or percentage value
     - **Type**: Choose "Fixed Price" or "Percentage"

4. **Save the Product**
   - Click "Update" or "Publish"

---

## 📝 **Field Explanations**

### **Min Qty (Minimum Quantity)**
- **What it is**: The minimum quantity required for this pricing tier
- **Example**: 5 means this price applies when customer orders 5 or more items
- **Format**: Whole numbers only (1, 5, 10, 50, etc.)

### **Price Field**
- **For Fixed Price**: Enter exact price (e.g., 25.00 for $25.00)
- **For Percentage**: Enter discount percentage (e.g., 5.55 for 5.55% off)
- **Format**: Use decimal format for prices (25.00, not 25) and percentages (5.55, not 5)

### **Type Dropdown**
- **Fixed Price**: Customer pays exactly this price per item
- **Percentage**: Applies a percentage discount off the regular price

---

## 💡 **Quick Examples**

### **Example 1: Fixed Price Tiers**
```
Min Qty: 5, Price: 30.00, Type: Fixed Price
Min Qty: 10, Price: 25.00, Type: Fixed Price
```
**Result**: 5-9 items = $30 each, 10+ items = $25 each

### **Example 2: Percentage Discount**
```
Min Qty: 10, Price: 5.55, Type: Percentage
Min Qty: 25, Price: 12.50, Type: Percentage
```
**Result**: 10-24 items = 5.55% off, 25+ items = 12.50% off

---

## ⚠️ **Important Tips**

### **Do's:**
- ✅ Set minimum quantity to 1 or higher
- ✅ Use ascending order (5, 10, 25, not 25, 5, 10)
- ✅ Test your pricing with different quantities
- ✅ Save the product after making changes

### **Don'ts:**
- ❌ Set minimum quantity to 0
- ❌ Use negative percentages
- ❌ Forget to click "Update" after changes
- ❌ Create overlapping tiers

---

## 🔧 **Interface Features**

### **Dynamic Placeholders**
- The price field placeholder changes based on your selection:
  - **Fixed Price**: "Enter price (e.g., 25.00)"
  - **Percentage**: "Enter percentage (e.g., 10 for 10%)"

### **Validation**
- Percentage values are automatically limited to 0-100%
- Price values are formatted to 2 decimal places
- Minimum quantity must be 1 or higher

### **Tooltips**
- Hover over fields for helpful tips
- Each field has a title attribute with guidance

---

## 🎯 **Common Use Cases**

### **Wholesale Pricing**
```
Min Qty: 5, Price: 30.00, Type: Fixed Price
Min Qty: 10, Price: 25.00, Type: Fixed Price
Min Qty: 50, Price: 20.00, Type: Fixed Price
```

### **Volume Discounts**
```
Min Qty: 10, Price: 5.55, Type: Percentage
Min Qty: 25, Price: 12.50, Type: Percentage
Min Qty: 100, Price: 20.00, Type: Percentage
```

### **Mixed Strategy**
```
Min Qty: 5, Price: 35.00, Type: Fixed Price
Min Qty: 20, Price: 5.25, Type: Percentage
```

---

## 🔍 **Troubleshooting**

### **Price Not Updating**
- ✅ Click "Update" or "Publish"
- ✅ Check customer role is correct
- ✅ Verify minimum quantity is met

### **Percentage Not Working**
- ✅ Select "Percentage" in Type dropdown
- ✅ Enter percentage as number (10, not 0.10)
- ✅ Check regular price is set

### **Tiers Not Showing**
- ✅ Product is published
- ✅ Customer is logged in
- ✅ Customer has correct B2B role

---

## 📞 **Need More Help?**

- Check the full **TIERED_PRICING_GUIDE.md** for detailed instructions
- Test with a simple 2-tier setup first
- Contact support if issues persist

**Remember**: Start simple and add complexity as needed!
