<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once 'check_event_status.php';
require_once 'check_event_status.php';

// Require admin access
requireAdmin();

$username = $_SESSION['username'] ?? 'Administrator';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management - Mawgifi</title>
    <style>
        :root {
            --primary-grad: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --text-dark: #2d3748;
            --text-light: #718096;
            --bg-light: #f7fafc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg-light);
        }

        .navbar {
            background: var(--primary-grad);
            color: white;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .navbar .brand {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .nav-links {
            display: flex;
            gap: 15px;
        }

        .nav-links a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            font-weight: 500;
            padding: 10px 18px;
            border-radius: 50px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateY(-2px);
        }

        .nav-links a.active {
            background: white;
            color: #6a67ce;
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .avatar-circle {
            width: 35px;
            height: 35px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .logout-btn {
            background: rgba(0, 0, 0, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 8px 18px;
            border-radius: 20px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: #e53e3e;
            border-color: #e53e3e;
        }

        .container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 30px;
        }

        .page-header {
            margin-bottom: 40px;
        }

        .page-title {
            font-size: 2rem;
            color: var(--text-dark);
            font-weight: 700;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: var(--text-light);
            font-size: 1.05rem;
        }

        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
        }

        .btn {
            background: var(--primary-grad);
            color: white;
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        .btn-secondary {
            background: #48bb78;
            box-shadow: 0 4px 15px rgba(72, 187, 120, 0.4);
        }

        .btn-secondary:hover {
            box-shadow: 0 6px 20px rgba(72, 187, 120, 0.5);
        }

        .search-filter {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 250px;
        }

        .search-box input,
        .search-box select {
            width: 100%;
            padding: 12px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .search-box input:focus,
        .search-box select:focus {
            outline: none;
            border-color: #667eea;
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .event-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-left: 5px solid #667eea;
        }

        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .event-card.maintenance {
            border-left-color: #ed8936;
        }

        .event-card.cleaning {
            border-left-color: #48bb78;
        }

        .event-card.lawn-mowing {
            border-left-color: #4299e1;
        }

        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .event-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .event-type-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-maintenance {
            background: #fed7d7;
            color: #c53030;
        }

        .badge-cleaning {
            background: #c6f6d5;
            color: #276749;
        }

        .badge-lawn-mowing {
            background: #bee3f8;
            color: #2c5282;
        }

        .badge-other {
            background: #e2e8f0;
            color: #4a5568;
        }

        .event-details {
            color: var(--text-light);
            font-size: 0.95rem;
            line-height: 1.8;
        }

        .event-detail-item {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .event-detail-item strong {
            color: var(--text-dark);
            min-width: 80px;
        }

        .event-report {
            margin-top: 15px;
            padding: 15px;
            background: #f7fafc;
            border-radius: 10px;
            font-size: 0.9rem;
            color: var(--text-light);
            max-height: 100px;
            overflow-y: auto;
        }

        .event-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .btn-small {
            flex: 1;
            padding: 8px 16px;
            font-size: 0.9rem;
            border-radius: 8px;
            cursor: pointer;
            border: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: #4299e1;
            color: white;
        }

        .btn-edit:hover {
            background: #3182ce;
        }

        .btn-delete {
            background: #fc8181;
            color: white;
        }

        .btn-delete:hover {
            background: #f56565;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: var(--primary-grad);
            color: white;
            padding: 25px 30px;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .close {
            color: white;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
            transition: all 0.3s ease;
        }

        .close:hover {
            transform: scale(1.2);
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .modal-footer {
            padding: 20px 30px;
            background: #f7fafc;
            border-radius: 0 0 20px 20px;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        .btn-cancel {
            background: #cbd5e0;
            color: #4a5568;
        }

        .btn-cancel:hover {
            background: #a0aec0;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .empty-state-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state h3 {
            color: var(--text-dark);
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--text-light);
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }

            .actions-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .events-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="brand">Mawgifi</div>

        <div class="nav-links">
            <a href="../Moudel1/Admin.php?view=dashboard">Dashboard</a>
            <a href="../modules/membership/index.php">Vehicles</a>
            <a href="../modules/parking/index.php">Parking Map</a>
            <a href="parking_management.php">Manage Parking</a>
            <a href="event_management.php" class="active">Events</a>
            <a href="../modules/booking/index.php">Bookings</a>
            <a href="../Moudel1/Admin.php?view=reports">Reports</a>
            <a href="../Moudel1/Admin.php?view=register">Register Student</a>
            <a href="../Moudel1/Admin.php?view=manage">Manage Profile</a>
            <a href="../Moudel1/Admin.php?view=profile">Profile</a>
        </div>

        <div class="user-profile">
            <div class="avatar-circle"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
            <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">📅 Event Management</h1>
            <p class="page-subtitle">Track and manage facility maintenance, cleaning, and building events</p>
        </div>

        <div class="actions-bar">
            <button class="btn" onclick="openEventModal()">
                ➕ Create New Event
            </button>
            <div class="search-filter">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="🔍 Search events..." oninput="filterEvents()">
                </div>
                <div class="search-box">
                    <select id="typeFilter" onchange="filterEvents()">
                        <option value="">All Types</option>
                        <option value="lawn-mowing">Lawn Mowing</option>
                        <option value="window-cleaning">Window Cleaning</option>
                        <option value="building-maintenance">Building Maintenance</option>
                        <option value="parking-maintenance">Parking Maintenance</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
        </div>

        <div id="eventsContainer" class="events-grid">
            <!-- Events will be loaded here -->
        </div>
    </div>

    <!-- Event Modal -->
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Create New Event</h2>
                <span class="close" onclick="closeEventModal()">&times;</span>
            </div>
            <form id="eventForm" onsubmit="submitEventForm(event)">
                <div class="modal-body">
                    <input type="hidden" id="event_id" name="event_id">

                    <div class="form-group">
                        <label for="event_name">Event Name *</label>
                        <input type="text" id="event_name" name="event_name" required
                            placeholder="e.g., Monthly Lawn Maintenance">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="event_type">Event Type *</label>
                            <select id="event_type" name="event_type" required>
                                <option value="">Select Type</option>
                                <option value="lawn-mowing">Lawn Mowing</option>
                                <option value="window-cleaning">Window Cleaning</option>
                                <option value="building-maintenance">Building Maintenance</option>
                                <option value="parking-maintenance">Parking Maintenance</option>
                                <option value="hvac-service">HVAC Service</option>
                                <option value="electrical-work">Electrical Work</option>
                                <option value="plumbing">Plumbing</option>
                                <option value="painting">Painting</option>
                                <option value="inspection">Inspection</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="area_id">Parking Area (Optional)</label>
                            <select id="area_id" name="area_id">
                                <option value="">No Area Closure</option>
                            </select>
                            <small style="color: #718096; display: block; margin-top: 5px;">Area will be closed during
                                event</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="event_time">Event Date & Time *</label>
                            <input type="datetime-local" id="event_time" name="event_time" required>
                        </div>

                        <div class="form-group">
                            <label for="duration_minutes">Duration (minutes) *</label>
                            <input type="number" id="duration_minutes" name="duration_minutes" required min="1"
                                placeholder="e.g., 120">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="RecordReport">Event Report / Notes</label>
                        <textarea id="RecordReport" name="RecordReport"
                            placeholder="Add any notes, observations, or details about this event..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeEventModal()">Cancel</button>
                    <button type="submit" class="btn">Save Event</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Load events on page load
        document.addEventListener('DOMContentLoaded', function () {
            loadEvents();
            loadAreas();

            // Auto-open modal if action=new in URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('action') === 'new') {
                openEventModal();
            }
        });

        // Load parking areas for dropdown
        function loadAreas() {
            fetch('../admin/parking_api.php?type=stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.areas) {
                        const areaSelect = document.getElementById('area_id');
                        data.areas.forEach(area => {
                            const option = document.createElement('option');
                            option.value = area.area_id;
                            option.textContent = area.area_name;
                            areaSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('Error loading areas:', error));
        }

        // Load all events
        function loadEvents() {
            fetch('event_api.php?type=list')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayEvents(data.events);
                    } else {
                        showError(data.message || 'Failed to load events');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Failed to load events');
                });
        }

        // Display events in the grid
        function displayEvents(events) {
            const container = document.getElementById('eventsContainer');

            if (events.length === 0) {
                container.innerHTML = `
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <div class="empty-state-icon">📅</div>
                        <h3>No Events Found</h3>
                        <p>Start by creating your first facility event</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = events.map(event => {
                const eventDate = new Date(event.event_time);
                const formattedDate = eventDate.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
                const formattedTime = eventDate.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                const typeClass = event.event_type.toLowerCase().replace(/[^a-z-]/g, '-');
                const badgeClass = `badge-${typeClass}`;

                return `
                    <div class="event-card ${typeClass}" data-event-id="${event.event_id}" data-event-type="${event.event_type}" data-event-name="${event.event_name.toLowerCase()}">
                        <div class="event-header">
                            <div>
                                <div class="event-name">${escapeHtml(event.event_name)}</div>
                                <span class="event-type-badge ${badgeClass}">${event.event_type}</span>
                            </div>
                        </div>
                        <div class="event-details">
                            <div class="event-detail-item">
                                <strong>📅 Date:</strong>
                                <span>${formattedDate}</span>
                            </div>
                            <div class="event-detail-item">
                                <strong>🕐 Time:</strong>
                                <span>${formattedTime}</span>
                            </div>
                            <div class="event-detail-item">
                                <strong>⏱️ Duration:</strong>
                                <span>${event.duration_minutes} minutes</span>
                            </div>
                            ${event.area_name ? `<div class="event-detail-item">
                                <strong>🅿️ Area:</strong>
                                <span>${escapeHtml(event.area_name)} (Closed)</span>
                            </div>` : ''}
                        </div>
                        ${event.RecordReport ? `<div class="event-report">${escapeHtml(event.RecordReport)}</div>` : ''}
                        <div class="event-actions">
                            <button class="btn-small btn-edit" onclick="editEvent(${event.event_id})">✏️ Edit</button>
                            <button class="btn-small btn-delete" onclick="deleteEvent(${event.event_id})">🗑️ Delete</button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Open modal for creating new event
        function openEventModal() {
            document.getElementById('modalTitle').textContent = 'Create New Event';
            document.getElementById('eventForm').reset();
            document.getElementById('event_id').value = '';
            document.getElementById('eventModal').style.display = 'block';
        }

        // Close modal
        function closeEventModal() {
            document.getElementById('eventModal').style.display = 'none';
        }

        // Edit event
        function editEvent(eventId) {
            fetch(`event_api.php?type=get&id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const event = data.event;
                        document.getElementById('modalTitle').textContent = 'Edit Event';
                        document.getElementById('event_id').value = event.event_id;
                        document.getElementById('event_name').value = event.event_name;
                        document.getElementById('event_type').value = event.event_type;
                        document.getElementById('area_id').value = event.area_id || '';
                        document.getElementById('duration_minutes').value = event.duration_minutes;

                        // Format datetime for input
                        const eventDate = new Date(event.event_time);
                        const isoString = eventDate.toISOString().slice(0, 16);
                        document.getElementById('event_time').value = isoString;

                        document.getElementById('RecordReport').value = event.RecordReport || '';
                        document.getElementById('eventModal').style.display = 'block';
                    } else {
                        showError(data.message || 'Failed to load event');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Failed to load event');
                });
        }

        // Submit event form
        function submitEventForm(e) {
            e.preventDefault();

            const formData = new FormData(e.target);
            const eventId = document.getElementById('event_id').value;
            const type = eventId ? 'update' : 'create';

            formData.append('type', type);

            // Debug: Log what's being sent
            console.log('Submitting event form:');
            console.log('Event ID:', eventId);
            console.log('Type:', type);
            console.log('Area ID value:', document.getElementById('area_id').value);
            for (let [key, value] of formData.entries()) {
                console.log(key + ':', value);
            }

            fetch('event_api.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    console.log('API Response:', data);
                    if (data.success) {
                        closeEventModal();
                        loadEvents();
                        showSuccess(eventId ? 'Event updated successfully!' : 'Event created successfully!');
                    } else {
                        showError(data.message || 'Failed to save event');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Failed to save event');
                });
        }

        // Delete event
        function deleteEvent(eventId) {
            if (!confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
                return;
            }

            fetch('event_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `type=delete&event_id=${eventId}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadEvents();
                        showSuccess('Event deleted successfully!');
                    } else {
                        showError(data.message || 'Failed to delete event');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Failed to delete event');
                });
        }

        // Filter events
        function filterEvents() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const typeFilter = document.getElementById('typeFilter').value;
            const eventCards = document.querySelectorAll('.event-card');

            eventCards.forEach(card => {
                const eventName = card.dataset.eventName;
                const eventType = card.dataset.eventType;

                const matchesSearch = eventName.includes(searchTerm);
                const matchesType = !typeFilter || eventType === typeFilter;

                card.style.display = matchesSearch && matchesType ? 'block' : 'none';
            });
        }

        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Show success message
        function showSuccess(message) {
            alert('✓ ' + message);
        }

        // Show error message
        function showError(message) {
            alert('✗ ' + message);
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            const modal = document.getElementById('eventModal');
            if (event.target === modal) {
                closeEventModal();
            }
        }
    </script>
</body>

</html>