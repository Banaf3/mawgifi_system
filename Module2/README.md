# Module 2: Manage Parking Area and Spaces

## Overview

This module handles all parking area and parking space management for the MAWGIFI parking system.

## Features

### 1. Manage Parking Areas
- **Create** new parking areas with customizable properties
- **Update** existing parking area details (name, type, size, color, status)
- **Delete** parking areas (with cascade deletion of associated spaces)
- **View** all parking areas with detailed information

**Area Types:**
- General use (immediate parking without reservation)
- Booking required (advance reservation needed)

**Area Status:**
- Available (open for parking)
- Occupied (full)
- Under Maintenance (building/facility work)
- Temporarily Closed (events, lawn mowing, window cleaning)

### 2. Manage Parking Spaces
- Automatic generation of unique space numbers based on area size
- QR code generation for each parking space
- Space availability tracking (available, occupied, reserved)
- Association with parking areas

### 3. View Daily Available Parking Areas
- Real-time parking availability display
- Color-coded area status visualization
- Interactive parking map
- Time-based availability checking

## Folder Structure

```
Module2/
├── admin/                          # Admin management pages
│   ├── parking_management.php      # Main CRUD interface
│   ├── init_parking.php            # Initialize parking data
│   ├── update_parking_schema.php   # Database schema updates
│   ├── update_parking_schema.sql   # SQL schema file
│   └── check_event_status.php      # Auto-update area status
├── api/                            # API endpoints
│   └── parking_api.php             # CRUD operations API
├── public/                         # Public user pages
│   ├── index.php                   # Parking availability view
│   └── process_booking.php         # Handle booking requests
├── assets/                         # Module assets
│   └── parking_slots_optimized.php # Optimized slot display
└── README.md                       # This file
```

## Database Tables

### ParkingArea
| Column | Type | Description |
|--------|------|-------------|
| area_id | INT | Primary key |
| area_name | VARCHAR(100) | Area name (e.g., "Area A") |
| area_type | VARCHAR(50) | "general" or "booking_required" |
| AreaSize | INT | Maximum parking spaces |
| area_color | VARCHAR(20) | Hex color code for map display |
| area_status | VARCHAR(50) | Current status |
| Availability_id | INT | Foreign key to Availability |

### ParkingSpace
| Column | Type | Description |
|--------|------|-------------|
| space_id | INT | Primary key |
| area_id | INT | Foreign key to ParkingArea |
| space_number | INT | Unique space number (1-100) |
| qr_code | VARCHAR(255) | QR code path |
| space_status | VARCHAR(50) | Current status |

## Usage

### Admin Access
Navigate to: `/mawgifi_system/Module2/admin/parking_management.php`

### Public Parking View
Navigate to: `/mawgifi_system/Module2/public/index.php`

### API Endpoints
- `GET /Module2/api/parking_api.php?type=stats` - Get parking statistics
- `GET /Module2/api/parking_api.php?type=list` - List all areas
- `GET /Module2/api/parking_api.php?type=map` - Get map data
- `POST /Module2/api/parking_api.php?type=area` - Create/Update area
- `POST /Module2/api/parking_api.php?type=space` - Create/Update space

## Setup

1. Run `init_parking.php` to create initial parking areas and spaces
2. Run `update_parking_schema.php` if upgrading from previous version
3. Access admin panel to manage areas and spaces

## Color Codes (Default)

| Area | Color | Space Range |
|------|-------|-------------|
| Area A | #667eea (Purple) | 1-14 |
| Area B | #764ba2 (Violet) | 15-44 |
| Area C | #48bb78 (Green) | 45-65 |
| Area D | #ed8936 (Orange) | 66-86 |
| Area E | #e53e3e (Red) | 87-100 |

## Status Colors (Map Display)

| Status | Color | Description |
|--------|-------|-------------|
| Available | Green | Open for parking |
| Occupied | Yellow | Full capacity |
| Under Maintenance | Orange | Building work |
| Temporarily Closed | Red | Events/cleaning |
