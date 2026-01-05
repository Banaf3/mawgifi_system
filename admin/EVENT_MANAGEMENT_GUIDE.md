# 📅 Event Management System

## Overview
The Event Management system allows administrators to track and record facility events such as:
- 🌱 Lawn Mowing
- 🪟 Window Cleaning
- 🔧 Building Maintenance
- 🅿️ Parking Maintenance
- ⚡ Electrical Work
- 🚰 Plumbing
- 🎨 Painting
- 🔍 Inspections
- And more...

## Features

### ✨ Key Capabilities
1. **Create Events** - Record new facility maintenance events
2. **Edit Events** - Update event details, times, and reports
3. **Delete Events** - Remove completed or cancelled events
4. **Search & Filter** - Find events by name or type
5. **Event Reports** - Add notes and observations for each event
6. **Color-Coded Cards** - Different colors for different event types

### 📊 Event Information
Each event tracks:
- Event Name
- Event Type (Lawn Mowing, Cleaning, etc.)
- Date and Time
- Duration (in minutes)
- Report/Notes

## How to Use

### Creating an Event
1. Click the "➕ Create New Event" button
2. Fill in the event details:
   - **Event Name**: e.g., "Monthly Lawn Maintenance"
   - **Event Type**: Select from dropdown
   - **Duration**: How long the event will take (in minutes)
   - **Event Date & Time**: When the event is scheduled
   - **Report/Notes**: Optional details about the event
3. Click "Save Event"

### Editing an Event
1. Find the event card in the grid
2. Click the "✏️ Edit" button
3. Update the information
4. Click "Save Event"

### Deleting an Event
1. Find the event card
2. Click the "🗑️ Delete" button
3. Confirm the deletion

### Searching Events
- Use the search box to find events by name
- Use the type dropdown to filter by event category
- Both filters work together

## Database

### Event Table Structure
```sql
CREATE TABLE Event (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50),
    event_time DATETIME,
    duration_minutes INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    event_name VARCHAR(150),
    RecordReport TEXT
);
```

## Files

### Admin Pages
- `admin/event_management.php` - Main event management interface
- `admin/event_api.php` - Backend API for CRUD operations

### API Endpoints
- `?type=list` - Get all events
- `?type=get&id=X` - Get single event
- `?type=create` - Create new event (POST)
- `?type=update` - Update existing event (POST)
- `?type=delete` - Delete event (POST)
- `?type=stats` - Get event statistics

## Access

### Navigation
Event Management can be accessed from:
1. **Dashboard** - Click "📅 Event Management" card
2. **Top Navigation** - "Events" link in navbar
3. **Direct URL** - `/admin/event_management.php`

### Permissions
- Only administrators can access event management
- Requires active admin session

## Examples

### Common Event Types

**Lawn Mowing**
- Name: "Weekly Lawn Service - Front Campus"
- Type: Lawn Mowing
- Duration: 180 minutes
- Notes: "Mow, edge, and blow all grass areas"

**Window Cleaning**
- Name: "Building A - Exterior Windows"
- Type: Window Cleaning
- Duration: 240 minutes
- Notes: "All windows on floors 1-3"

**Building Maintenance**
- Name: "HVAC System Inspection"
- Type: Building Maintenance
- Duration: 120 minutes
- Notes: "Quarterly maintenance check"

**Parking Maintenance**
- Name: "Parking Lot Line Repainting"
- Type: Parking Maintenance
- Duration: 480 minutes
- Notes: "Re-stripe Area A and Area B"

## Tips

✅ **Best Practices**
- Create events ahead of schedule for planning
- Add detailed notes in the report field
- Use consistent naming conventions
- Review past events to track maintenance history

⚠️ **Important Notes**
- Event times are in 24-hour format
- Duration is always in minutes (e.g., 2 hours = 120 minutes)
- Deleted events cannot be recovered
- All times are stored in the database timezone

## Troubleshooting

**Events not appearing?**
- Check your internet connection
- Refresh the page
- Verify database connection

**Can't create events?**
- Ensure all required fields are filled
- Check that duration is greater than 0
- Verify date/time format is correct

**Search not working?**
- Clear the search box and try again
- Check spelling of event names
- Try filtering by type first

## Support

For additional help or feature requests, contact your system administrator.

---

*Last Updated: January 5, 2026*
