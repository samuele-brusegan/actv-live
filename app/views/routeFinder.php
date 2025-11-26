<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trova Percorso - ACTV</title>
    <?php require COMMON_HTML_HEAD; ?>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F5F5F5;
            margin: 0;
            padding: 0;
        }

        /* Header Verde */
        .header-green {
            background: #009E61;
            padding: 2rem 1.5rem 4rem;
            color: white;
            clip-path: polygon(0 0, 100% 0, 100% 75%, 0 100%);
            margin-bottom: -2rem;
            position: relative;
            z-index: 1;
        }

        .header-title {
            font-family: 'Inter', sans-serif;
            font-weight: 800;
            font-size: 28px;
            line-height: 1.2;
            margin-top: 1rem;
        }

        .main-content {
            padding: 0 1.5rem 1.5rem;
            position: relative;
            z-index: 2;
        }

        .selection-card {
            background: #FFFFFF;
            box-shadow: 0px 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 15px;
            padding: 1.25rem 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .selection-card:active {
            transform: scale(0.98);
            box-shadow: 0px 1px 4px rgba(0, 0, 0, 0.15);
        }

        .selection-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #F5F5F5;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 20px;
        }

        .selection-content {
            flex-grow: 1;
        }

        .selection-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 0.25rem;
        }

        .selection-value {
            font-family: 'SF Pro', sans-serif;
            font-weight: 700;
            font-size: 16px;
            color: #000;
        }

        .swap-button {
            background: #007bff;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            margin: 0.5rem auto;
            font-size: 18px;
        }
        .datetime-section {
            margin-top: 1.5rem;
        }

        .section-label {
            font-family: 'SF Pro', sans-serif;
            font-weight: 600;
            font-size: 14px;
            color: #000;
            margin-bottom: 0.5rem;
        }
        .datetime-inputs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
 
        .datetime-input {
            flex: 1;
            padding: 0.875rem;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
             font-family: 'Inter', sans-serif;
             font-size: 15px;
             background: #FFFFFF;
             box-shadow: 0px 1px 4px rgba(0, 0, 0, 0.08);
             transition: all 0.2s ease;
         }
         
        .datetime-input:focus {
            outline: none;
            border-color: #009E61;
            box-shadow: 0px 2px 8px rgba(0, 158, 97, 0.2);
        }

        .toggle-container {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .toggle-label {
            font-family: 'SF Pro', sans-serif;
            font-size: 14px;
            margin-right: 0.5rem;
        }

        .toggle-switch {
            position: relative;
            width: 50px;
            height: 28px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 28px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: #009E61;
        }

        input:checked + .toggle-slider:before {
            transform: translateX(22px);
        }

        .search-button {
            background: #0152BB;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 1rem;
            width: 100%;
            font-family: 'SF Pro', sans-serif;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            margin-top: 1.5rem;
            transition: all 0.2s ease;
        }

        .search-button:active {
            transform: scale(0.98);
            background: #013d99;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header-green">
        <div style="height: 20px;">
            <a href="/" style="color: white; text-decoration: none; font-size: 24px;">&larr;</a>
        </div>
        <div class="header-title">Trova<br>Percorso</div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Origin Selection -->
        <div class="selection-card" onclick="selectStation('origin')">
            <div class="selection-icon">📍</div>
            <div class="selection-content">
                <div class="selection-label">fermata più vicina</div>
                <div class="selection-value" id="origin-value">Seleziona partenza</div>
            </div>
        </div>

        <!-- Swap Button -->
        <button class="swap-button" onclick="swapStations()">⇅</button>

        <!-- Destination Selection -->
        <div class="selection-card" onclick="selectStation('destination')">
            <div class="selection-icon">📍</div>
            <div class="selection-content">
                <div class="selection-label">selezionata prima</div>
                <div class="selection-value" id="destination-value">Seleziona destinazione</div>
            </div>
        </div>

        <!-- Date/Time Triggers -->
        <div class="datetime-section">
            <div class="section-label">seleziona data e ora partenza:</div>
            <div class="datetime-inputs">
                <div class="datetime-trigger" onclick="openDateModal()">
                    <span id="display-date"><?= date('d/m/Y') ?></span>
                    <span class="chevron">›</span>
                </div>
                <div class="datetime-trigger" onclick="openTimeModal()">
                    <span id="display-time"><?= date('H:i') ?></span>
                    <span class="chevron">›</span>
                </div>
            </div>
        </div>

        <!-- Return Trip Toggle -->
        <div class="toggle-container">
            <span class="toggle-label">ritorno</span>
            <label class="toggle-switch">
                <input type="checkbox" id="return-toggle" onchange="toggleReturn()">
                <span class="toggle-slider"></span>
            </label>
        </div>

        <!-- Search Button -->
        <button class="search-button" onclick="searchRoutes()">ricerca soluzioni</button>

    </div>

    <!-- Date Modal -->
    <div id="date-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-handle"></div>
            
            <!-- Simple Calendar Header -->
            <div class="calendar-header">
                <button onclick="changeMonth(-1)">‹</button>
                <span id="calendar-month-year">September 2025</span>
                <button onclick="changeMonth(1)">›</button>
            </div>
            
            <!-- Calendar Grid -->
            <div class="calendar-grid" id="calendar-grid">
                <!-- Days will be generated by JS -->
            </div>

            <div class="modal-actions">
                <button class="btn-primary" onclick="confirmDate()">fatto</button>
                <button class="btn-outline" onclick="closeDateModal()">annulla</button>
            </div>
        </div>
    </div>

    <!-- Time Modal -->
    <div id="time-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-handle"></div>
            
            <div class="time-picker-container">
                <div class="time-picker-wheel" id="hour-wheel">
                    <!-- Hours generated by JS -->
                </div>
                <div class="time-picker-separator">:</div>
                <div class="time-picker-wheel" id="minute-wheel">
                    <!-- Minutes generated by JS -->
                </div>
                <div class="time-picker-highlight"></div>
            </div>

            <div class="modal-actions">
                <button class="btn-primary" onclick="confirmTime()">fatto</button>
                <button class="btn-outline" onclick="closeTimeModal()">annulla</button>
            </div>
        </div>
    </div>

    <!-- Custom Pickers Styles moved to style.css -->

    <script>
        let selectedOrigin = null;
        let selectedDestination = null;
        
        // Date/Time State
        let currentDate = new Date();
        let selectedDate = new Date();
        let selectedHour = new Date().getHours();
        let selectedMinute = new Date().getMinutes();

        // --- Modal Logic ---
        function openDateModal() {
            document.getElementById('date-modal').style.display = 'flex';
            renderCalendar();
        }

        function closeDateModal() {
            document.getElementById('date-modal').style.display = 'none';
        }

        function confirmDate() {
            document.getElementById('display-date').textContent = formatDate(selectedDate);
            closeDateModal();
        }

        function openTimeModal() {
            document.getElementById('time-modal').style.display = 'flex';
            renderTimePicker();
        }

        function closeTimeModal() {
            document.getElementById('time-modal').style.display = 'none';
        }

        function confirmTime() {
            const h = selectedHour.toString().padStart(2, '0');
            const m = selectedMinute.toString().padStart(2, '0');
            document.getElementById('display-time').textContent = `${h}:${m}`;
            closeTimeModal();
        }

        // --- Calendar Logic ---
        function renderCalendar() {
            const grid = document.getElementById('calendar-grid');
            grid.innerHTML = '';
            
            const year = selectedDate.getFullYear();
            const month = selectedDate.getMonth();
            
            document.getElementById('calendar-month-year').textContent = selectedDate.toLocaleString('default', { month: 'long', year: 'numeric' });

            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();

            // Empty slots
            for (let i = 0; i < firstDay; i++) {
                grid.appendChild(document.createElement('div'));
            }

            // Days
            for (let d = 1; d <= daysInMonth; d++) {
                const el = document.createElement('div');
                el.className = 'calendar-day';
                el.textContent = d;
                if (d === selectedDate.getDate()) el.classList.add('selected');
                
                el.onclick = () => {
                    selectedDate.setDate(d);
                    renderCalendar();
                };
                
                grid.appendChild(el);
            }
        }
        
        function changeMonth(delta) {
            selectedDate.setMonth(selectedDate.getMonth() + delta);
            renderCalendar();
        }

        // --- Time Picker Logic ---
        function renderTimePicker() {
            const hWheel = document.getElementById('hour-wheel');
            const mWheel = document.getElementById('minute-wheel');
            
            hWheel.innerHTML = '';
            mWheel.innerHTML = '';
            
            // Hours
            for (let i = 0; i < 24; i++) {
                const el = document.createElement('div');
                el.className = 'time-item';
                el.textContent = i.toString().padStart(2, '0');
                if (i === selectedHour) el.classList.add('active');
                hWheel.appendChild(el);
            }
            
            // Minutes
            for (let i = 0; i < 60; i+=5) { // 5 min steps for easier scrolling
                const el = document.createElement('div');
                el.className = 'time-item';
                el.textContent = i.toString().padStart(2, '0');
                if (i === selectedMinute) el.classList.add('active');
                mWheel.appendChild(el);
            }
            
            // Scroll to selected (simplified)
            // In a real app, we'd need complex scroll handling.
            // For now, let's just set the values on click/scroll
            
            hWheel.onscroll = () => {
                // Calculate selected based on scroll position
                const index = Math.round(hWheel.scrollTop / 40);
                selectedHour = index;
                // Update styling
                Array.from(hWheel.children).forEach((c, i) => {
                    c.classList.toggle('active', i === index);
                });
            };
            
             mWheel.onscroll = () => {
                const index = Math.round(mWheel.scrollTop / 40);
                selectedMinute = index * 5;
                 Array.from(mWheel.children).forEach((c, i) => {
                    c.classList.toggle('active', i === index);
                });
            };
        }

        function formatDate(date) {
            return date.toLocaleDateString('it-IT');
        }

        // --- Existing Logic ---
        function selectStation(type) {
            localStorage.setItem('route_selection_mode', type);
            window.location.href = '/station-selector?mode=select&type=' + type;
        }

        function swapStations() {
            const temp = selectedOrigin;
            selectedOrigin = selectedDestination;
            selectedDestination = temp;
            updateUI();
            saveState();
        }

        function updateUI() {
            document.getElementById('origin-value').textContent = selectedOrigin ? selectedOrigin.name : 'Seleziona partenza';
            document.getElementById('destination-value').textContent = selectedDestination ? selectedDestination.name : 'Seleziona destinazione';
        }

        function saveState() {
            if (selectedOrigin) localStorage.setItem('route_origin', JSON.stringify(selectedOrigin));
            else localStorage.removeItem('route_origin');
            
            if (selectedDestination) localStorage.setItem('route_destination', JSON.stringify(selectedDestination));
            else localStorage.removeItem('route_destination');
        }

        function searchRoutes() {
            if (!selectedOrigin || !selectedDestination) {
                alert('Seleziona sia la partenza che la destinazione');
                return;
            }

            const dateStr = selectedDate.toISOString().split('T')[0];
            const timeStr = `${selectedHour.toString().padStart(2, '0')}:${selectedMinute.toString().padStart(2, '0')}`;
            
            localStorage.setItem('route_departure_date', dateStr);
            localStorage.setItem('route_departure_time', timeStr);

            window.location.href = '/route-results';
        }

        function toggleReturn() {
            console.log("Toggle return not implemented yet");
            // dopo tot sec unchecka #return-toggle
            setTimeout(() => {
                document.getElementById('return-toggle').checked = false;
            }, 400);
        }

        window.addEventListener('DOMContentLoaded', () => {
            const savedOrigin = localStorage.getItem('route_origin');
            const savedDestination = localStorage.getItem('route_destination');

            if (savedOrigin) selectedOrigin = JSON.parse(savedOrigin);
            if (savedDestination) selectedDestination = JSON.parse(savedDestination);
            
            updateUI();
            
            // Initialize displays
            document.getElementById('display-date').textContent = formatDate(selectedDate);
            document.getElementById('display-time').textContent = `${selectedHour.toString().padStart(2, '0')}:${selectedMinute.toString().padStart(2, '0')}`;
        });
    </script>

</body>
</html>
