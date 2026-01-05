<?php
/**
 * =============================================================================
 * INITIALIZE PARKING DATA - ADMIN MODULE
 * =============================================================================
 * 
 * PURPOSE:
 * This is a ONE-TIME setup script that creates the complete parking infrastructure:
 * - 5 Parking Areas (A, B, C, D, E)
 * - 100 Parking Spaces distributed across the areas
 * 
 * FLOW:
 * 1. Admin accesses this page
 * 2. Script checks each area - creates if doesn't exist
 * 3. For each area, creates parking spaces in that area
 * 4. Uses database transaction for atomicity (all or nothing)
 * 5. Displays summary of what was created
 * 
 * IDEMPOTENT: Safe to run multiple times - won't create duplicates
 * 
 * PARKING LAYOUT:
 * Area A: Slots 1-14   (14 spaces) - Standard
 * Area B: Slots 15-44  (30 spaces) - Standard  
 * Area C: Slots 45-65  (21 spaces) - Standard
 * Area D: Slots 66-86  (21 spaces) - Standard
 * Area E: Slots 87-100 (14 spaces) - VIP
 * TOTAL: 100 parking spaces
 * 
 * =============================================================================
 */

// Include database configuration file for getDBConnection() function
require_once '../config/database.php';  // Line 30: Load database helper functions

// Include session configuration for requireAdmin() function
require_once '../config/session.php';  // Line 33: Load session/auth helper functions

// =========================================================================
// SECURITY CHECK - ADMIN ONLY
// =========================================================================
// This function checks if current user is logged in AND is an admin
// If not, it redirects to login page or shows error

requireAdmin();  // Line 41: Verify user has admin privileges before proceeding

// =========================================================================
// DATABASE CONNECTION
// =========================================================================

$conn = getDBConnection();  // Line 47: Get MySQLi database connection object

// Verify connection was successful
if (!$conn) {  // Line 50: If connection is null/false
    die("Database connection failed");  // Line 51: Stop script with error message
}

// =========================================================================
// PARKING AREA CONFIGURATION
// =========================================================================
// This array defines the complete parking structure
// Key = Area code (A, B, C, D, E)
// Value = Array with start slot, end slot, type, and size in square meters

$parking_areas = [  // Line 61: Define parking area configuration array
    // Area A: Slots 1-14 (14 total), Standard type, 350 square meters
    'A' => ['start' => 1, 'end' => 14, 'type' => 'Standard', 'size' => 350.00],  // Line 63: Area A config
    
    // Area B: Slots 15-44 (30 total), Standard type, 750 square meters
    'B' => ['start' => 15, 'end' => 44, 'type' => 'Standard', 'size' => 750.00],  // Line 66: Area B config
    
    // Area C: Slots 45-65 (21 total), Standard type, 525 square meters
    'C' => ['start' => 45, 'end' => 65, 'type' => 'Standard', 'size' => 525.00],  // Line 69: Area C config
    
    // Area D: Slots 66-86 (21 total), Standard type, 525 square meters
    'D' => ['start' => 66, 'end' => 86, 'type' => 'Standard', 'size' => 525.00],  // Line 72: Area D config
    
    // Area E: Slots 87-100 (14 total), VIP type, 350 square meters
    'E' => ['start' => 87, 'end' => 100, 'type' => 'VIP', 'size' => 350.00]  // Line 75: Area E config (VIP)
];

// =========================================================================
// INITIALIZATION COUNTERS
// =========================================================================

$areas_created = 0;  // Line 82: Counter for newly created areas
$spaces_created = 0;  // Line 83: Counter for newly created spaces
$errors = [];  // Line 84: Array to collect any error messages

// =========================================================================
// DATABASE TRANSACTION
// =========================================================================
// Transactions ensure data integrity - if anything fails, ALL changes are undone
// This prevents partial data (e.g., areas without spaces)

$conn->begin_transaction();  // Line 92: Start database transaction

try {  // Line 94: Begin try block - any exception will trigger rollback
    
    // =========================================================================
    // LOOP THROUGH EACH PARKING AREA
    // =========================================================================
    // $area_code will be 'A', 'B', 'C', 'D', 'E'
    // $area_info will be the configuration array for that area
    
    foreach ($parking_areas as $area_code => $area_info) {  // Line 102: Iterate through each area
        
        // Build area name (e.g., "Area A", "Area B")
        $area_name = 'Area ' . $area_code;  // Line 105: Construct human-readable area name
        
        // -----------------------------------------------------------------
        // CHECK IF AREA ALREADY EXISTS
        // -----------------------------------------------------------------
        // Use prepared statement to prevent SQL injection
        
        $check_sql = "SELECT area_id FROM ParkingArea WHERE area_name = ?";  // Line 112: SQL to find existing area
        $check_stmt = $conn->prepare($check_sql);  // Line 113: Prepare the SQL statement
        $check_stmt->bind_param("s", $area_name);  // Line 114: Bind area_name as string ("s")
        $check_stmt->execute();  // Line 115: Execute the query
        $result = $check_stmt->get_result();  // Line 116: Get the result set
        
        // -----------------------------------------------------------------
        // CREATE AREA IF IT DOESN'T EXIST
        // -----------------------------------------------------------------
        
        if ($result->num_rows === 0) {  // Line 122: If no matching area found
            // Area doesn't exist - create it
            $insert_sql = "INSERT INTO ParkingArea (area_name, area_type, AreaSize) VALUES (?, ?, ?)";  // Line 124: INSERT SQL
            $insert_stmt = $conn->prepare($insert_sql);  // Line 125: Prepare INSERT statement
            // Bind parameters: "ssd" = string, string, double (decimal number)
            $insert_stmt->bind_param("ssd", $area_name, $area_info['type'], $area_info['size']);  // Line 127: Bind values
            $insert_stmt->execute();  // Line 128: Execute INSERT
            $area_id = $conn->insert_id;  // Line 129: Get auto-generated area_id
            $insert_stmt->close();  // Line 130: Close INSERT statement
            $areas_created++;  // Line 131: Increment areas counter
        } else {  // Line 132: Area already exists
            // Area exists - get its ID for creating spaces
            $row = $result->fetch_assoc();  // Line 134: Fetch the row as associative array
            $area_id = $row['area_id'];  // Line 135: Extract the existing area_id
        }
        $check_stmt->close();  // Line 137: Close check statement to free resources
        
        // -----------------------------------------------------------------
        // CREATE PARKING SPACES FOR THIS AREA
        // -----------------------------------------------------------------
        // Loop from start slot to end slot (e.g., 1 to 14 for Area A)
        
        for ($slot = $area_info['start']; $slot <= $area_info['end']; $slot++) {  // Line 144: Loop through slot range
            
            // Format space number with leading zeros (e.g., "A-01", "A-14", "B-15")
            // str_pad adds leading zeros: 1 becomes "01", 14 stays "14"
            $space_number = $area_code . '-' . str_pad($slot, 2, '0', STR_PAD_LEFT);  // Line 148: Create space number
            
            // Generate unique QR code for this space (e.g., "SPACE-A-001")
            $qr_code = 'SPACE-' . $area_code . '-' . str_pad($slot, 3, '0', STR_PAD_LEFT);  // Line 151: Create QR code
            
            // -------------------------------------------------------------
            // CHECK IF SPACE ALREADY EXISTS
            // -------------------------------------------------------------
            
            $check_space_sql = "SELECT Space_id FROM ParkingSpace WHERE space_number = ?";  // Line 157: SQL to check space
            $check_space_stmt = $conn->prepare($check_space_sql);  // Line 158: Prepare check statement
            $check_space_stmt->bind_param("s", $space_number);  // Line 159: Bind space number as string
            $check_space_stmt->execute();  // Line 160: Execute query
            $space_result = $check_space_stmt->get_result();  // Line 161: Get results
            
            // -------------------------------------------------------------
            // CREATE SPACE IF IT DOESN'T EXIST
            // -------------------------------------------------------------
            
            if ($space_result->num_rows === 0) {  // Line 167: If space doesn't exist
                // Create the parking space
                $insert_space_sql = "INSERT INTO ParkingSpace (area_id, space_number, qr_code) VALUES (?, ?, ?)";  // Line 169: INSERT SQL
                $insert_space_stmt = $conn->prepare($insert_space_sql);  // Line 170: Prepare INSERT
                // "iss" = integer (area_id), string (space_number), string (qr_code)
                $insert_space_stmt->bind_param("iss", $area_id, $space_number, $qr_code);  // Line 172: Bind parameters
                $insert_space_stmt->execute();  // Line 173: Execute INSERT
                $insert_space_stmt->close();  // Line 174: Close statement
                $spaces_created++;  // Line 175: Increment spaces counter
            }
            $check_space_stmt->close();  // Line 177: Close check statement
        }
    }
    
    // =========================================================================
    // COMMIT TRANSACTION
    // =========================================================================
    // If we reach here, all operations succeeded - make changes permanent
    
    $conn->commit();  // Line 185: Commit all changes to database
    $success = true;  // Line 186: Set success flag for display
    
} catch (Exception $e) {  // Line 188: Catch any exception that occurred
    // =========================================================================
    // ROLLBACK ON ERROR
    // =========================================================================
    // Something failed - undo ALL changes made during this transaction
    
    $conn->rollback();  // Line 194: Rollback all changes
    $success = false;  // Line 195: Set failure flag
    $errors[] = $e->getMessage();  // Line 196: Store error message for display
}

$conn->close();  // Line 199: Close database connection

// =========================================================================
// CALCULATE SUMMARY STATISTICS
// =========================================================================

$total_areas = count($parking_areas);  // Line 205: Total areas defined (5)
$total_spaces = 100;  // Line 206: Total spaces expected (100)
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initialize Parking Data - Mawgifi</title>
    <style>
        :root {
            --primary-grad: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success: #48bb78;
            --danger: #e53e3e;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #f7fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 90%;
            text-align: center;
        }
        h1 {
            background: var(--primary-grad);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
        }
        .status {
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .status.success { background: rgba(72, 187, 120, 0.1); color: var(--success); }
        .status.error { background: rgba(229, 62, 62, 0.1); color: var(--danger); }
        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin: 30px 0;
        }
        .stat {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #718096;
            margin-top: 5px;
        }
        .areas-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .areas-table th, .areas-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .areas-table th { background: #f8fafc; font-weight: 600; }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: var(--primary-grad);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            margin-top: 20px;
            transition: transform 0.3s;
        }
        .btn:hover { transform: translateY(-2px); }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-a { background: rgba(102, 126, 234, 0.1); color: #667eea; }
        .badge-b { background: rgba(118, 75, 162, 0.1); color: #764ba2; }
        .badge-c { background: rgba(72, 187, 120, 0.1); color: #48bb78; }
        .badge-d { background: rgba(237, 137, 54, 0.1); color: #ed8936; }
        .badge-e { background: rgba(229, 62, 62, 0.1); color: #e53e3e; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🅿️ Parking Data Initialization</h1>
        
        <?php if ($success): ?>
            <div class="status success">
                ✅ Parking data initialized successfully!
            </div>
        <?php else: ?>
            <div class="status error">
                ❌ Error: <?php echo implode(', ', $errors); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat">
                <div class="stat-number"><?php echo $areas_created; ?>/<?php echo $total_areas; ?></div>
                <div class="stat-label">Areas Created</div>
            </div>
            <div class="stat">
                <div class="stat-number"><?php echo $spaces_created; ?>/<?php echo $total_spaces; ?></div>
                <div class="stat-label">Spaces Created</div>
            </div>
        </div>
        
        <h3 style="color: #2d3748; margin-bottom: 10px;">Parking Areas Summary</h3>
        <table class="areas-table">
            <thead>
                <tr>
                    <th>Area</th>
                    <th>Type</th>
                    <th>Slots</th>
                    <th>Range</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($parking_areas as $code => $info): ?>
                    <tr>
                        <td><span class="badge badge-<?php echo strtolower($code); ?>">Area <?php echo $code; ?></span></td>
                        <td><?php echo $info['type']; ?></td>
                        <td><?php echo ($info['end'] - $info['start'] + 1); ?> slots</td>
                        <td><?php echo $code; ?>-<?php echo str_pad($info['start'], 2, '0', STR_PAD_LEFT); ?> to <?php echo $code; ?>-<?php echo str_pad($info['end'], 2, '0', STR_PAD_LEFT); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="font-weight: bold; background: #f8fafc;">
                    <td colspan="2">Total</td>
                    <td>100 slots</td>
                    <td>5 areas</td>
                </tr>
            </tbody>
        </table>
        
        <a href="parking_management.php" class="btn">Go to Parking Management</a>
    </div>
</body>
</html>
