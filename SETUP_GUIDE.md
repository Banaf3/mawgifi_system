# Quick Setup Guide - Parking Management Updates

## Step-by-Step Installation

### 1. **Update Database Schema** (Required First)
   
   Visit this URL in your browser (must be logged in as admin):
   ```
   http://localhost/mawgifi_system/admin/update_parking_schema.php
   ```
   
   This script will:
   - Add `area_color` column to ParkingArea table
   - Add `area_status` column to ParkingArea table
   - Set default colors for existing areas
   - Display a table of all parking areas

   **Expected Output:** You should see green checkmarks (✓) indicating successful updates.

### 2. **Verify the Updates**
   
   Go to Parking Management:
   ```
   http://localhost/mawgifi_system/admin/parking_management.php
   ```
   
   You should see:
   - ✅ No "Initialize All Parking Data" button (removed)
   - ✅ Total space counter at the top (X / 100 spaces)
   - ✅ Color and Status columns in the areas table
   - ✅ Color display boxes showing each area's color

### 3. **Test Creating/Editing Areas**
   
   **Create a new area:**
   1. Click "➕ Add New Area"
   2. Fill in area details
   3. **Pick a color** using the color picker
   4. **Select a status** (Available/Occupied/etc.)
   5. Click "Save Area"
   
   **Edit an existing area:**
   1. Click "✏️ Edit" on any area
   2. Change the color or status
   3. Click "Save Area"

### 4. **View on Parking Map**
   
   Go to the parking map:
   ```
   http://localhost/mawgifi_system/modules/parking/index.php
   ```
   
   You should see:
   - ✅ Parking slots colored according to their area
   - ✅ Areas marked as occupied/closed/maintenance show as red
   - ✅ Area name changes reflected immediately

### 5. **Test 100 Space Limit**
   
   **Single Space:**
   1. Go to Parking Management → Parking Spaces tab
   2. Try to create a space when you already have 100
   3. You should see: "Cannot create space. Maximum limit of 100 spaces reached."
   
   **Bulk Create:**
   1. Click "📦 Bulk Create Spaces"
   2. Set range that would exceed 100 total
   3. Click "Create Spaces"
   4. You should see error with current count

## What Should Work Now

### ✅ Dynamic Colors
- Each area has its own color on the map
- Change color in management → reflects on map
- Default colors assigned automatically

### ✅ Area Status
- Set area to "Occupied" → turns red on map
- Set area to "Temporarily Closed" → turns red, non-clickable
- Set area to "Under Maintenance" → turns red, non-clickable
- Set area to "Available" → uses area's custom color

### ✅ Area Name Changes
- Rename area in management
- Parking slots update to show new area name
- Map continues to work with new names

### ✅ Space Limit
- Total counter shows X/100 at top
- Counter color changes:
  - Green: < 90 spaces
  - Yellow: 90-99 spaces
  - Red: 100 spaces
- Cannot create beyond 100 spaces
- Error messages show current total

## Troubleshooting

### Problem: Colors not showing
**Solution:**
1. Run update_parking_schema.php again
2. Check that area_color column exists:
   ```sql
   DESCRIBE ParkingArea;
   ```
3. Clear browser cache (Ctrl+F5)

### Problem: 100 limit not enforced
**Solution:**
1. Check browser console for errors (F12)
2. Verify parking_api.php was updated
3. Test the stats endpoint:
   ```
   http://localhost/mawgifi_system/admin/parking_api.php?type=stats
   ```
   Should return: `{"success":true,"total_spaces":XX}`

### Problem: Map still shows old colors
**Solution:**
1. Hard refresh the page (Ctrl+F5)
2. Check that area has a color assigned
3. Verify database has area_color values

### Problem: Status changes not working
**Solution:**
1. Verify area_status column exists
2. Check allowed values:
   - available
   - occupied
   - temporarily_closed
   - under_maintenance
3. Refresh parking map page

## Quick Tests

### Test 1: Color Assignment
```
1. Edit "Area A"
2. Set color to blue (#0000FF)
3. Save
4. Open parking map
5. Slots 1-14 should be blue
```

### Test 2: Status Change
```
1. Edit "Area B"
2. Set status to "Occupied"
3. Save
4. Open parking map
5. Slots 15-44 should be red
```

### Test 3: Space Limit
```
1. Check current total (top of page)
2. Calculate: 100 - current = remaining
3. Try to create (remaining + 1) spaces
4. Should see error message
```

## Common Scenarios

### Scenario 1: Adding New Parking Area
```
1. Click "Add New Area"
2. Name: "Area F"
3. Type: "Standard"
4. Size: 500
5. Color: Pick purple (#800080)
6. Status: Available
7. Save → Area created with purple color
```

### Scenario 2: Temporarily Closing an Area
```
1. Find area in table
2. Click "Edit"
3. Change status to "Temporarily Closed"
4. Save
5. Map shows area as red
6. Users cannot book slots in this area
```

### Scenario 3: Reaching Space Limit
```
Current: 95 spaces
Action: Try to bulk create 10 spaces
Result: Error - "Cannot create 10 space(s). Current total: 95, would exceed 100 space limit."
Solution: Create only 5 spaces
```

## Need Help?

1. Check browser console (F12) for JavaScript errors
2. Check PHP error logs for backend issues
3. Verify all files were updated
4. Re-run the schema update script
5. Refer to PARKING_UPDATES_README.md for detailed documentation

## Success Checklist

- [ ] Schema update script ran successfully
- [ ] No "Initialize All Parking Data" button visible
- [ ] Total space counter displays at top
- [ ] Can pick color when creating/editing area
- [ ] Can set status when creating/editing area
- [ ] Areas table shows color and status columns
- [ ] Map displays slots in their area's colors
- [ ] Occupied/closed areas show as red on map
- [ ] Cannot create beyond 100 spaces
- [ ] Error messages show current space count
- [ ] Area name changes reflect on map

If all items are checked ✓, the system is working correctly!
