# B2B Commerce - Tiered Pricing Guide

## 🎯 **How to Set Up Tiered Pricing**

### **Step 1: Access the Product Edit Page**
1. Go to **WordPress Admin → Products → All Products**
2. Click **"Edit"** on any product
3. Scroll down to find the **"B2B Pricing"** section

### **Step 2: Set Basic Role Prices**
For each customer role (Wholesale Customer, Distributor, etc.):
- **Regular Price**: Set the base price for this customer type
- **Sale Price**: Set the discounted price (optional)

### **Step 3: Add Tiered Pricing**
1. In the **"Tiered Pricing"** section for each role:
2. Click **"Add Tier"** button
3. Fill in the fields:
   - **Min Qty**: Minimum quantity for this tier (e.g., 5, 10, 50)
   - **Price**: The price or discount value
   - **Type**: Choose between:
     - **Fixed Price**: Set a specific price (e.g., $25.00)
     - **Percentage**: Set a discount percentage (e.g., 10 for 10% off)

### **Step 4: Save the Product**
Click **"Update"** or **"Publish"** to save your tiered pricing rules.

---

## 📊 **Tiered Pricing Examples**

### **Example 1: Fixed Price Tiers**
```
Role: Wholesale Customer
- Min Qty: 5, Price: $30.00, Type: Fixed Price
- Min Qty: 10, Price: $25.00, Type: Fixed Price
- Min Qty: 50, Price: $20.00, Type: Fixed Price
```
**Result:** 
- 1-4 items: Regular price
- 5-9 items: $30.00 each
- 10-49 items: $25.00 each
- 50+ items: $20.00 each

### **Example 2: Percentage Discount Tiers**
```
Role: Distributor
- Min Qty: 10, Price: 5.55, Type: Percentage
- Min Qty: 25, Price: 12.50, Type: Percentage
- Min Qty: 100, Price: 20.00, Type: Percentage
```
**Result:**
- 1-9 items: Regular price
- 10-24 items: 5.55% discount
- 25-99 items: 12.50% discount
- 100+ items: 20.00% discount

### **Example 3: Mixed Pricing Strategy**
```
Role: Retailer
- Min Qty: 5, Price: $35.00, Type: Fixed Price
- Min Qty: 20, Price: 5.25, Type: Percentage
```
**Result:**
- 1-4 items: Regular price
- 5-19 items: $35.00 each
- 20+ items: 5.25% discount off regular price

---

## 🔧 **How the Interface Works**

### **Adding a New Tier**
1. Click **"Add Tier"** button
2. A new row appears with three fields:
   - **Min Qty**: Number input for minimum quantity
   - **Price**: Text input for price or percentage
   - **Type**: Dropdown to select "Fixed Price" or "Percentage"
3. Fill in the values
4. Click **"Remove"** to delete a tier if needed

### **Understanding the Fields**

#### **Min Qty (Minimum Quantity)**
- Enter the minimum quantity required for this pricing tier
- Must be a whole number (1, 5, 10, 50, etc.)
- Tiers should be in ascending order

#### **Price Field**
- **For Fixed Price**: Enter the exact price (e.g., 25.00 for $25.00)
- **For Percentage**: Enter the discount percentage (e.g., 5.55 for 5.55% off)
- Use decimal format for prices (25.00, not 25) and percentages (5.55, not 5)

#### **Type Dropdown**
- **Fixed Price**: Sets a specific price for this quantity tier
- **Percentage**: Applies a percentage discount off the regular price

---

## ⚠️ **Important Notes**

### **Pricing Logic**
- Tiers are applied based on **minimum quantity**
- The **best price** is automatically selected for the customer
- If no tier matches, the regular price is used

### **Quantity Rules**
- Minimum quantity should be 1 or higher
- Tiers should be in ascending order (5, 10, 50, not 50, 5, 10)
- Each tier applies when the customer orders **at least** that quantity

### **Price Calculation**
- **Fixed Price**: Customer pays exactly this price per item
- **Percentage**: Discount is calculated from the regular price
- Example: Regular price $40, 10% discount = $36 per item

---

## 🎯 **Best Practices**

### **Setting Up Effective Tiers**
1. **Start Small**: Begin with 2-3 tiers per role
2. **Reasonable Gaps**: Don't make huge jumps (5→10→25 is better than 5→50→100)
3. **Test Pricing**: Ensure your margins work at each tier
4. **Clear Communication**: Make sure customers understand the pricing structure

### **Common Mistakes to Avoid**
- ❌ Setting minimum quantity to 0
- ❌ Using negative percentages
- ❌ Creating overlapping tiers
- ❌ Not testing the pricing logic

### **Testing Your Setup**
1. Create a test order with different quantities
2. Verify the correct price is applied
3. Check that the best tier is selected
4. Test with different customer roles

---

## 🔍 **Troubleshooting**

### **Price Not Updating**
- Make sure you clicked **"Update"** or **"Publish"**
- Check that the customer has the correct role
- Verify the minimum quantity is met

### **Percentage Not Working**
- Ensure you selected **"Percentage"** in the Type dropdown
- Enter the percentage as a number (10 for 10%, not 0.10)
- Check that the regular price is set

### **Tiers Not Showing**
- Verify the product is published
- Check that the customer is logged in
- Ensure the customer has the correct B2B role

---

## 📞 **Need Help?**

If you're still having trouble with tiered pricing:
1. Check the **B2B Commerce** admin panel for any error messages
2. Verify your customer roles are set up correctly
3. Test with a simple 2-tier setup first
4. Contact support if issues persist

**Remember:** Tiered pricing is a powerful feature that can significantly increase your B2B sales when configured properly!
