<?php
/**
 * =============================================================================
 * DATABASE SCHEMA UPDATE SCRIPT - MODULE 2
 * =============================================================================
 * 
 * PURPOSE:
 * This migration script adds two new columns to the ParkingArea table:
 * 1. area_color (VARCHAR 7) - Stores hex color code for map display
 * 2. area_status (ENUM) - Stores area availability status
 * 
 * FLOW:
 * 1. Check if columns already exist (idempotent)
 * 2. Add missing columns with ALTER TABLE
 * 3. Set default colors for areas A-E
 * 4. Display results and current area data
 * 
 * SAFE TO RUN MULTIPLE TIMES - Won't duplicate columns
 * 
 * =============================================================================
 */

// Include database configuration for getDBConnection() function
require_once '../config/database.php';  // Line 22: Load database connection helper

// Include session configuration for requireAdmin() function
require_once '../config/session.php';  // Line 25: Load session/auth helper

// =========================================================================
// SECURITY CHECK - ADMIN ONLY
// =========================================================================
// Only administrators should be able to modify database schema

requireAdmin();  // Line 32: Verify admin access before proceeding

// =========================================================================
// DATABASE CONNECTION
// =========================================================================

$conn = getDBConnection();  // Line 38: Get MySQLi connection object

if (!$conn) {  // Line 40: Check if connection successful
    die("Database connection failed");  // Line 41: Stop with error if no connection
}

// =========================================================================
// OUTPUT HTML HEADER AND STYLES
// =========================================================================
// Using echo to output HTML since we're displaying progress in real-time

echo "<!DOCTYPE html><html><head><title>Database Schema Update</title>";  // Line 49: Start HTML document
echo "<style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;}";  // Line 50: Body styles
echo ".box{background:white;padding:20px;border-radius:10px;margin:10px 0;box-shadow:0 2px 4px rgba(0,0,0,0.1);}";  // Line 51: Box container styles
echo ".success{color:green;font-weight:bold;}";  // Line 52: Success message styles
echo ".error{color:red;font-weight:bold;}";  // Line 53: Error message styles
echo ".warning{color:orange;font-weight:bold;}";  // Line 54: Warning message styles
echo ".btn{display:inline-block;padding:10px 20px;background:#667eea;color:white;text-decoration:none;border-radius:5px;margin:10px 5px;}";  // Line 55: Button styles
echo ".btn:hover{background:#5568d3;}</style></head><body>";  // Line 56: Button hover styles and close head

echo "<h2>Updating Database Schema...</h2>";  // Line 58: Page title

// =========================================================================
// INITIALIZE TRACKING VARIABLES
// =========================================================================

$messages = [];  // Line 64: Array to store status messages for display
$has_errors = false;  // Line 65: Flag to track if any errors occurred

// =========================================================================
// CHECK IF COLUMNS ALREADY EXIST
// =========================================================================
// SHOW COLUMNS returns column info if it exists, empty result if not

// Check for area_color column
$check_sql = "SHOW COLUMNS FROM ParkingArea LIKE 'area_color'";  // Line 73: SQL to check for color column
$result = $conn->query($check_sql);  // Line 74: Execute the check query
$color_exists = $result->num_rows > 0;  // Line 75: True if column exists (num_rows > 0)

// Check for area_status column
$check_sql = "SHOW COLUMNS FROM ParkingArea LIKE 'area_status'";  // Line 78: SQL to check for status column
$result = $conn->query($check_sql);  // Line 79: Execute the check query
$status_exists = $result->num_rows > 0;  // Line 80: True if column exists

// =========================================================================
// ADD MISSING COLUMNS
// =========================================================================

if ($color_exists && $status_exists) {  // Line 86: Both columns already exist
    // Nothing to do - database is already up to date
    $messages[] = ['type' => 'success', 'msg' => '✓ Columns already exist. Database is up to date.'];  // Line 88: Add success message
} else {
    // -----------------------------------------------------------------
    // ADD AREA_COLOR COLUMN IF MISSING
    // -----------------------------------------------------------------
    
    if (!$color_exists) {  // Line 94: If color column doesn't exist
        // ALTER TABLE adds a new column
        // VARCHAR(7) holds hex color like "#a0a0a0" (7 characters including #)
        // AFTER area_type places it after that column in table structure
        $sql = "ALTER TABLE ParkingArea ADD COLUMN area_color VARCHAR(7) DEFAULT '#a0a0a0' AFTER area_type";  // Line 99: ALTER SQL
        
        if ($conn->query($sql)) {  // Line 101: Execute ALTER and check success
            $messages[] = ['type' => 'success', 'msg' => '✓ Successfully added area_color column'];  // Line 102: Success message
        } else {
            $messages[] = ['type' => 'error', 'msg' => '✗ Error adding area_color: ' . $conn->error];  // Line 104: Error with details
            $has_errors = true;  // Line 105: Set error flag
        }
    } else {
        $messages[] = ['type' => 'success', 'msg' => '✓ area_color column already exists'];  // Line 108: Already exists message
    }

    // -----------------------------------------------------------------
    // ADD AREA_STATUS COLUMN IF MISSING
    // -----------------------------------------------------------------
    
    if (!$status_exists) {  // Line 114: If status column doesn't exist
        // ENUM creates a column that can only hold specific values
        // This prevents invalid status values from being stored
        $sql = "ALTER TABLE ParkingArea ADD COLUMN area_status ENUM('available', 'occupied', 'temporarily_closed', 'under_maintenance') DEFAULT 'available' AFTER area_type";  // Line 118: ALTER SQL with ENUM
        
        if ($conn->query($sql)) {  // Line 120: Execute ALTER and check success
            $messages[] = ['type' => 'success', 'msg' => '✓ Successfully added area_status column'];  // Line 121: Success message
        } else {
            $messages[] = ['type' => 'error', 'msg' => '✗ Error adding area_status: ' . $conn->error];  // Line 123: Error message
            $has_errors = true;  // Line 124: Set error flag
        }
    } else {
        $messages[] = ['type' => 'success', 'msg' => '✓ area_status column already exists'];  // Line 127: Already exists message
    }

    // -----------------------------------------------------------------
    // SET DEFAULT COLORS FOR EXISTING AREAS
    // -----------------------------------------------------------------
    // Only do this if we just added the color column and no errors occurred
    
    if (!$color_exists && !$has_errors) {  // Line 134: If color column was just added AND no errors
        // Array of UPDATE statements to set unique colors for each area
        $updates = [
            // Area A gets purple-blue color (#667eea)
            "UPDATE ParkingArea SET area_color = '#667eea' WHERE (area_name LIKE '%A%') AND (area_color = '#a0a0a0' OR area_color IS NULL) LIMIT 1",  // Line 138: Update Area A
            // Area B gets dark purple color (#764ba2)
            "UPDATE ParkingArea SET area_color = '#764ba2' WHERE (area_name LIKE '%B%') AND (area_color = '#a0a0a0' OR area_color IS NULL) LIMIT 1",  // Line 140: Update Area B
            // Area C gets green color (#48bb78)
            "UPDATE ParkingArea SET area_color = '#48bb78' WHERE (area_name LIKE '%C%') AND (area_color = '#a0a0a0' OR area_color IS NULL) LIMIT 1",  // Line 142: Update Area C
            // Area D gets orange color (#ed8936)
            "UPDATE ParkingArea SET area_color = '#ed8936' WHERE (area_name LIKE '%D%') AND (area_color = '#a0a0a0' OR area_color IS NULL) LIMIT 1",  // Line 144: Update Area D
            // Area E gets red color (#e53e3e)
            "UPDATE ParkingArea SET area_color = '#e53e3e' WHERE (area_name LIKE '%E%') AND (area_color = '#a0a0a0' OR area_color IS NULL) LIMIT 1"  // Line 146: Update Area E
        ];

        // Execute each UPDATE statement
        foreach ($updates as $sql) {  // Line 150: Loop through update statements
            $conn->query($sql);  // Line 151: Execute each UPDATE
        }
        $messages[] = ['type' => 'success', 'msg' => '✓ Applied default colors to existing areas'];  // Line 153: Success message
    }
}

// =========================================================================
// DISPLAY STATUS MESSAGES
// =========================================================================

echo "<div class='box'>";  // Line 160: Start message container
foreach ($messages as $msg) {  // Line 161: Loop through all messages
    // Output each message with appropriate CSS class for styling
    echo "<p class='{$msg['type']}'>{$msg['msg']}</p>";  // Line 163: Output message paragraph
}
echo "</div>";  // Line 165: Close message container

// =========================================================================
// DISPLAY SUCCESS OR ERROR SUMMARY
// =========================================================================

if (!$has_errors) {  // Line 171: If no errors occurred
    // Display success summary with instructions
    echo "<div class='box' style='background:#d1fae5;border-left:4px solid #10b981;'>";  // Line 173: Green success box
    echo "<h3 style='color:#059669;'>✓ Update Completed Successfully!</h3>";  // Line 174: Success heading
    echo "<p>The database schema has been updated. You can now:</p>";  // Line 175: Description
    echo "<ul>";  // Line 176: Start list
    echo "<li>Set custom colors for parking areas</li>";  // Line 177: Feature 1
    echo "<li>Change area status (Available, Occupied, Closed, Maintenance)</li>";  // Line 178: Feature 2
    echo "<li>See colors reflected on the parking map</li>";  // Line 179: Feature 3
    echo "</ul>";  // Line 180: End list
    echo "</div>";  // Line 181: Close success box
} else {
    // Display error summary
    echo "<div class='box' style='background:#fee2e2;border-left:4px solid #ef4444;'>";  // Line 184: Red error box
    echo "<h3 style='color:#dc2626;'>✗ Some errors occurred</h3>";  // Line 185: Error heading
    echo "<p>Please check the error messages above and try again or run the SQL manually.</p>";  // Line 186: Error instructions
    echo "</div>";  // Line 187: Close error box
}

// =========================================================================
// DISPLAY CURRENT PARKING AREAS TABLE
// =========================================================================
// Show all areas with their current settings to verify changes

echo "<div class='box'>";  // Line 194: Start table container
echo "<h3>Current Parking Areas:</h3>";  // Line 195: Table heading

$sql = "SELECT * FROM ParkingArea";  // Line 197: SQL to get all areas
$result = $conn->query($sql);  // Line 198: Execute query

if ($result && $result->num_rows > 0) {  // Line 200: If areas exist
    // Build HTML table
    echo "<table style='width:100%;border-collapse:collapse;'>";  // Line 202: Start table
    echo "<tr style='background:#667eea;color:white;'>";  // Line 203: Header row with purple background
    echo "<th style='border:1px solid #ddd;padding:8px;'>ID</th>";  // Line 204: ID column header
    echo "<th style='border:1px solid #ddd;padding:8px;'>Name</th>";  // Line 205: Name column header
    echo "<th style='border:1px solid #ddd;padding:8px;'>Type</th>";  // Line 206: Type column header
    echo "<th style='border:1px solid #ddd;padding:8px;'>Color</th>";  // Line 207: Color column header
    echo "<th style='border:1px solid #ddd;padding:8px;'>Status</th>";  // Line 208: Status column header
    echo "</tr>";  // Line 209: Close header row

    // Loop through each area and display its data
    while ($row = $result->fetch_assoc()) {  // Line 212: Fetch each row as associative array
        echo "<tr>";  // Line 213: Start data row
        echo "<td style='border:1px solid #ddd;padding:8px;'>{$row['area_id']}</td>";  // Line 214: Display area ID
        echo "<td style='border:1px solid #ddd;padding:8px;'><strong>{$row['area_name']}</strong></td>";  // Line 215: Display area name (bold)
        echo "<td style='border:1px solid #ddd;padding:8px;'>{$row['area_type']}</td>";  // Line 216: Display area type
        
        // Get color with fallback to default if not set
        $color = isset($row['area_color']) ? $row['area_color'] : '#a0a0a0';  // Line 219: Get color or default gray
        echo "<td style='border:1px solid #ddd;padding:8px;'>";  // Line 220: Start color cell
        // Display color as visual box + hex code
        echo "<div style='display:inline-block;width:50px;height:30px;background:{$color};border:1px solid #ccc;vertical-align:middle;'></div> ";  // Line 222: Color preview box
        echo $color;  // Line 223: Display hex code
        echo "</td>";  // Line 224: Close color cell
        
        // Get status with fallback
        $status = isset($row['area_status']) ? $row['area_status'] : 'N/A';  // Line 227: Get status or N/A
        echo "<td style='border:1px solid #ddd;padding:8px;'>{$status}</td>";  // Line 228: Display status
        echo "</tr>";  // Line 229: Close data row
    }
    echo "</table>";  // Line 231: Close table
} else {
    echo "<p>No parking areas found.</p>";  // Line 233: Message if no areas
}
echo "</div>";  // Line 235: Close table container

$conn->close();  // Line 237: Close database connection
?>

<!-- =========================================================================
     NAVIGATION BUTTONS
     ========================================================================= -->
<br>
<a href="parking_management.php" class="btn">← Back to Parking Management</a>  <!-- Link to management page -->
<a href="check_database.php" class="btn">View Database Info</a>  <!-- Link to database info page -->

</body>

</html>