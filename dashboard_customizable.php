<?php
ob_start();
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

// Save widget layout if posted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['widgets_order'])) {
    $stmt = $pdo->prepare("INSERT INTO user_settings (user_id, dashboard_layout, updated_at) 
                           VALUES (?, ?, NOW()) 
                           ON DUPLICATE KEY UPDATE dashboard_layout = ?, updated_at = NOW()");
    $stmt->execute([$_SESSION['user_id'], $_POST['widgets_order'], $_POST['widgets_order']]);
    exit('saved');
}

// Get user's saved layout
$stmt = $pdo->prepare("SELECT dashboard_layout FROM user_settings WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$savedLayout = $stmt->fetchColumn();
$widgets_order = $savedLayout ? explode(',', $savedLayout) : ['stats', 'recent_leads', 'chart', 'tasks', 'notifications'];

// Include header
require_once 'includes/header.php';
?>

<style>
    .dashboard-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .grid-stack {
        background: transparent;
        min-height: 600px;
    }
    
    .grid-stack-item {
        background: var(--card-bg);
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    
    .widget-header {
        padding: 15px 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        cursor: move;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .widget-header h4 {
        margin: 0;
        font-size: 16px;
    }
    
    .widget-controls {
        display: flex;
        gap: 10px;
    }
    
    .widget-controls button {
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        cursor: pointer;
        padding: 5px 8px;
        border-radius: 5px;
        transition: all 0.3s;
    }
    
    .widget-controls button:hover {
        background: rgba(255,255,255,0.4);
    }
    
    .widget-content {
        padding: 20px;
        min-height: 250px;
    }
    
    /* Widget Picker Styles */
    .widget-picker-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }
    
    .widget-picker {
        background: var(--card-bg);
        border-radius: 16px;
        width: 400px;
        max-width: 90%;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        overflow: hidden;
    }
    
    .widget-picker-header {
        padding: 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .widget-picker-header h3 {
        margin: 0;
        font-size: 18px;
    }
    
    .widget-picker-header button {
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
    }
    
    .widget-picker-body {
        padding: 20px;
        max-height: 400px;
        overflow-y: auto;
    }
    
    .widget-option {
        display: block;
        width: 100%;
        padding: 12px 15px;
        margin: 8px 0;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        cursor: pointer;
        text-align: left;
        transition: all 0.3s;
        color: var(--text-primary);
        font-size: 14px;
    }
    
    .widget-option:hover {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        transform: translateX(5px);
    }
    
    .widget-option i {
        margin-right: 10px;
        width: 24px;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .stat-card {
        text-align: center;
        padding: 15px;
        background: var(--bg-secondary);
        border-radius: 10px;
    }
    
    .stat-card h4 {
        font-size: 12px;
        color: var(--text-secondary);
        margin-bottom: 8px;
    }
    
    .stat-number {
        font-size: 28px;
        font-weight: bold;
        color: #3498db;
    }
    
    .lead-item {
        padding: 12px;
        border-bottom: 1px solid var(--border-color);
        cursor: pointer;
        transition: background 0.3s;
    }
    
    .lead-item:hover {
        background: var(--bg-secondary);
    }
    
    .lead-item strong {
        display: block;
        margin-bottom: 5px;
    }
    
    .task-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .task-checkbox {
        cursor: pointer;
        width: 18px;
        height: 18px;
    }
    
    .task-title {
        flex: 1;
    }
    
    .task-title.completed {
        text-decoration: line-through;
        opacity: 0.6;
    }
    
    .add-task-form {
        margin-top: 15px;
        display: flex;
        gap: 10px;
    }
    
    .add-task-form input {
        flex: 1;
        padding: 8px 12px;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        background: var(--bg-primary);
        color: var(--text-primary);
    }
    
    .add-task-form button {
        padding: 8px 16px;
        background: #27ae60;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }
    
    .notification-item {
        padding: 12px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .notification-item.unread {
        background: rgba(52, 152, 219, 0.1);
        border-left: 3px solid #3498db;
    }
    
    .notification-message {
        font-size: 13px;
    }
    
    .notification-date {
        font-size: 10px;
        color: var(--text-secondary);
        margin-top: 5px;
    }
    
    .btn-primary {
        background: #3498db;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }
    
    .btn-secondary {
        background: #95a5a6;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }
    
    .btn-sm {
        padding: 4px 8px;
        font-size: 11px;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 4px;
        cursor: pointer;
        color: var(--text-primary);
    }
    
    .btn-sm:hover {
        background: #3498db;
        color: white;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.5;
    }
</style>

<div class="dashboard-toolbar">
    <h2><i class="fas fa-th-large"></i> Customizable Dashboard</h2>
    <div>
        <button onclick="saveLayout()" class="btn-primary">
            <i class="fas fa-save"></i> Save Layout
        </button>
        <button onclick="resetLayout()" class="btn-secondary">
            <i class="fas fa-undo"></i> Reset
        </button>
        <button onclick="openWidgetPicker()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;">
            <i class="fas fa-plus"></i> Add Widget
        </button>
    </div>
</div>

<!-- Widget Picker Modal -->
<div id="widgetPickerOverlay" class="widget-picker-overlay">
    <div class="widget-picker">
        <div class="widget-picker-header">
            <h3><i class="fas fa-plus-circle"></i> Add Widget</h3>
            <button onclick="closeWidgetPicker()">&times;</button>
        </div>
        <div class="widget-picker-body">
            <button class="widget-option" onclick="addWidget('stats', 'Statistics')">
                <i class="fas fa-chart-line"></i> Statistics - View key metrics
            </button>
            <button class="widget-option" onclick="addWidget('recent_leads', 'Recent Leads')">
                <i class="fas fa-users"></i> Recent Leads - Latest 5 leads
            </button>
            <button class="widget-option" onclick="addWidget('chart', 'Sales Chart')">
                <i class="fas fa-chart-bar"></i> Sales Chart - Pipeline visualization
            </button>
            <button class="widget-option" onclick="addWidget('tasks', 'My Tasks')">
                <i class="fas fa-tasks"></i> My Tasks - Pending tasks
            </button>
            <button class="widget-option" onclick="addWidget('notifications', 'Notifications')">
                <i class="fas fa-bell"></i> Notifications - Recent alerts
            </button>
        </div>
    </div>
</div>

<div class="grid-stack" id="dashboard-grid"></div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/gridstack@8.0.0/dist/gridstack.min.css">
<script src="https://cdn.jsdelivr.net/npm/gridstack@8.0.0/dist/gridstack-all.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    let grid;
    let dashboardChart = null;
    
    // Widget definitions
    const widgets = {
        stats: {
            title: 'Statistics',
            render: async (element) => {
                element.innerHTML = `
                    <div class="stats-grid" id="stats-grid">
                        <div class="stat-card"><h4>Total Leads</h4><div class="stat-number">-</div></div>
                        <div class="stat-card"><h4>Won Deals</h4><div class="stat-number">-</div></div>
                        <div class="stat-card"><h4>Conversion Rate</h4><div class="stat-number">-</div></div>
                        <div class="stat-card"><h4>Expected Revenue</h4><div class="stat-number">-</div></div>
                    </div>
                `;
                await loadStats();
            }
        },
        recent_leads: {
            title: 'Recent Leads',
            render: async (element) => {
                element.innerHTML = '<div id="recent-leads-list"><div class="empty-state"><i class="fas fa-spinner fa-spin"></i><br>Loading...</div></div>';
                await loadRecentLeads();
            }
        },
        chart: {
            title: 'Sales Pipeline',
            render: async (element) => {
                element.innerHTML = '<canvas id="pipeline-chart" style="height:250px;"></canvas>';
                await loadChart();
            }
        },
        tasks: {
            title: 'My Tasks',
            render: (element) => {
                element.innerHTML = `
                    <div id="tasks-list"><div class="empty-state"><i class="fas fa-spinner fa-spin"></i><br>Loading...</div></div>
                    <div class="add-task-form">
                        <input type="text" id="new-task-title" placeholder="Add a new task...">
                        <button onclick="addNewTask()">Add</button>
                    </div>
                `;
                loadTasks();
            }
        },
        notifications: {
            title: 'Notifications',
            render: async (element) => {
                element.innerHTML = '<div id="notifications-list"><div class="empty-state"><i class="fas fa-spinner fa-spin"></i><br>Loading...</div></div>';
                await loadNotifications();
            }
        }
    };
    
    // Initialize dashboard
    function initDashboard() {
        const options = {
            cellHeight: 80,
            minRow: 4,
            margin: 15,
            disableDrag: false,
            disableResize: false,
            alwaysShowResizeHandle: true
        };
        
        grid = GridStack.init(options, '#dashboard-grid');
        
        const savedOrder = <?php echo json_encode($widgets_order); ?>;
        if (savedOrder && savedOrder.length) {
            savedOrder.forEach((widget, index) => {
                if (widgets[widget]) {
                    addWidgetToGrid(widget, index);
                }
            });
        }
    }
    
    function addWidgetToGrid(widgetId, position = null) {
        const widget = widgets[widgetId];
        if (!widget) return;
        
        // Check if widget already exists
        const existingItems = grid.getGridItems();
        const alreadyExists = existingItems.some(item => {
            const header = item.querySelector('.widget-header h4');
            return header && header.innerText === widget.title;
        });
        
        if (alreadyExists) {
            showNotification('Widget already exists on dashboard!', 'warning');
            return;
        }
        
        const x = position !== null ? (position % 2) * 6 : 0;
        const y = position !== null ? Math.floor(position / 2) : (grid.getGridItems().length);
        
        grid.addWidget({
            x: x,
            y: y,
            w: 6,
            h: 4,
            content: `<div class="widget-header">
                        <h4><i class="fas ${getWidgetIcon(widgetId)}"></i> ${widget.title}</h4>
                        <div class="widget-controls">
                            <button onclick="refreshWidget(this)" title="Refresh">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button onclick="removeWidget(this)" title="Remove">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                      </div>
                      <div class="widget-content"></div>`
        }, (el) => {
            widget.render(el.querySelector('.widget-content'));
        });
        
        showNotification(`"${widget.title}" widget added!`, 'success');
    }
    
    function getWidgetIcon(widgetId) {
        const icons = {
            stats: 'fa-chart-line',
            recent_leads: 'fa-users',
            chart: 'fa-chart-bar',
            tasks: 'fa-tasks',
            notifications: 'fa-bell'
        };
        return icons[widgetId] || 'fa-widget';
    }
    
    function openWidgetPicker() {
        document.getElementById('widgetPickerOverlay').style.display = 'flex';
    }
    
    function closeWidgetPicker() {
        document.getElementById('widgetPickerOverlay').style.display = 'none';
    }
    
    function addWidget(widgetId, title) {
        addWidgetToGrid(widgetId);
        closeWidgetPicker();
        saveLayout();
    }
    
    function removeWidget(btn) {
        const widget = btn.closest('.grid-stack-item');
        const header = widget.querySelector('.widget-header h4');
        const title = header ? header.innerText : 'Widget';
        
        if (confirm(`Remove "${title}" from dashboard?`)) {
            grid.removeWidget(widget);
            saveLayout();
            showNotification(`"${title}" removed`, 'info');
        }
    }
    
    function refreshWidget(btn) {
        const widget = btn.closest('.grid-stack-item');
        const header = widget.querySelector('.widget-header h4');
        const title = header ? header.innerText : '';
        const widgetId = Object.keys(widgets).find(key => widgets[key].title === title);
        
        if (widgetId && widgets[widgetId]) {
            const content = widget.querySelector('.widget-content');
            widgets[widgetId].render(content);
            showNotification('Widget refreshed!', 'success');
        }
    }
    
    async function saveLayout() {
        const items = grid.getGridItems();
        const widgetOrder = [];
        
        items.forEach(item => {
            const header = item.querySelector('.widget-header h4');
            const title = header ? header.innerText : '';
            const widgetId = Object.keys(widgets).find(key => widgets[key].title === title);
            if (widgetId) widgetOrder.push(widgetId);
        });
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'widgets_order=' + widgetOrder.join(',')
            });
            if (response.ok) {
                showNotification('Layout saved!', 'success');
            }
        } catch (error) {
            console.error('Save failed:', error);
            showNotification('Save failed', 'error');
        }
    }
    
    function resetLayout() {
        if (confirm('Reset dashboard to default layout? All custom widgets will be removed.')) {
            // Clear all widgets
            const items = grid.getGridItems();
            items.forEach(item => grid.removeWidget(item));
            
            // Add default widgets
            const defaultWidgets = ['stats', 'recent_leads', 'chart', 'tasks', 'notifications'];
            defaultWidgets.forEach((widget, index) => {
                addWidgetToGrid(widget, index);
            });
            
            saveLayout();
            showNotification('Dashboard reset to default!', 'success');
        }
    }
    
    // Load data functions
    async function loadStats() {
        try {
            const response = await fetch('ajax_handlers.php?action=dashboard_stats');
            const data = await response.json();
            
            const statsGrid = document.getElementById('stats-grid');
            if (statsGrid) {
                const numbers = statsGrid.querySelectorAll('.stat-number');
                if (numbers[0]) numbers[0].textContent = data.total_leads || 0;
                if (numbers[1]) numbers[1].textContent = data.won_deals || 0;
                if (numbers[2]) numbers[2].textContent = (data.conversion_rate || 0) + '%';
                if (numbers[3]) numbers[3].textContent = '৳' + (data.expected_revenue || 0).toLocaleString();
            }
        } catch (error) {
            console.error('Stats load error:', error);
        }
    }
    
    async function loadRecentLeads() {
        try {
            const response = await fetch('ajax_handlers.php?action=recent_leads&limit=5');
            const leads = await response.json();
            
            if (leads.length === 0) {
                document.getElementById('recent-leads-list').innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><br>No leads found</div>';
                return;
            }
            
            const html = leads.map(lead => `
                <div class="lead-item" onclick="window.location.href='view_lead.php?id=${lead.id}'">
                    <strong>${escapeHtml(lead.name)}</strong>
                    <div style="display: flex; justify-content: space-between; font-size: 12px; margin-top: 5px;">
                        <span>📞 ${lead.phone}</span>
                        <span>${lead.lead_stage || 'New'}</span>
                    </div>
                </div>
            `).join('');
            
            document.getElementById('recent-leads-list').innerHTML = html;
        } catch (error) {
            console.error('Recent leads error:', error);
            document.getElementById('recent-leads-list').innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><br>Error loading leads</div>';
        }
    }
    
    async function loadChart() {
        try {
            const response = await fetch('ajax_handlers.php?action=dashboard_stats');
            const data = await response.json();
            
            const ctx = document.getElementById('pipeline-chart')?.getContext('2d');
            if (ctx) {
                if (dashboardChart) dashboardChart.destroy();
                
                const stages = data.stages || ['New Lead', 'Contacted', 'Qualified', 'Proposal', 'Negotiation', 'Won'];
                const counts = data.stage_counts || [0, 0, 0, 0, 0, 0];
                
                dashboardChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: stages,
                        datasets: [{
                            label: 'Number of Leads',
                            data: counts,
                            backgroundColor: 'rgba(52, 152, 219, 0.7)',
                            borderColor: '#3498db',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        scales: {
                            y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } }
                        }
                    }
                });
            }
        } catch (error) {
            console.error('Chart load error:', error);
        }
    }
    
    async function loadTasks() {
        try {
            const response = await fetch('ajax_handlers.php?action=get_tasks');
            const tasks = await response.json();
            
            if (tasks.length === 0) {
                document.getElementById('tasks-list').innerHTML = '<div class="empty-state"><i class="fas fa-check-circle"></i><br>No pending tasks</div>';
                return;
            }
            
            const html = tasks.map(task => `
                <div class="task-item" data-task-id="${task.id}">
                    <input type="checkbox" class="task-checkbox" onchange="updateTaskStatus(${task.id}, this.checked)" ${task.status === 'Completed' ? 'checked' : ''}>
                    <span class="task-title ${task.status === 'Completed' ? 'completed' : ''}">${escapeHtml(task.title)}</span>
                    <small style="color: var(--text-secondary);">Due: ${task.due_date || 'No date'}</small>
                </div>
            `).join('');
            
            document.getElementById('tasks-list').innerHTML = html;
        } catch (error) {
            console.error('Tasks load error:', error);
        }
    }
    
    async function loadNotifications() {
        try {
            const response = await fetch('ajax_handlers.php?action=get_notifications');
            const notifs = await response.json();
            
            if (notifs.length === 0) {
                document.getElementById('notifications-list').innerHTML = '<div class="empty-state"><i class="fas fa-bell-slash"></i><br>No notifications</div>';
                return;
            }
            
            const html = notifs.map(notif => `
                <div class="notification-item ${notif.is_read ? '' : 'unread'}">
                    <div class="notification-message">${escapeHtml(notif.message)}</div>
                    <div class="notification-date">${notif.created_at}</div>
                </div>
            `).join('');
            
            document.getElementById('notifications-list').innerHTML = html;
        } catch (error) {
            console.error('Notifications load error:', error);
        }
    }
    
    function addNewTask() {
        const input = document.getElementById('new-task-title');
        const title = input ? input.value.trim() : '';
        
        if (title) {
            fetch('ajax_handlers.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add_task&title=${encodeURIComponent(title)}`
            }).then(() => {
                input.value = '';
                loadTasks();
                showNotification('Task added!', 'success');
            }).catch(error => {
                showNotification('Failed to add task', 'error');
            });
        } else {
            showNotification('Please enter a task title', 'warning');
        }
    }
    
    window.updateTaskStatus = async (taskId, completed) => {
        const status = completed ? 'Completed' : 'Pending';
        try {
            await fetch('ajax_handlers.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=update_task&task_id=${taskId}&status=${status}`
            });
            loadTasks();
        } catch (error) {
            console.error('Task update failed:', error);
        }
    };
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        const bgColor = type === 'success' ? '#27ae60' : (type === 'error' ? '#e74c3c' : (type === 'warning' ? '#f39c12' : '#3498db'));
        notification.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            background: ${bgColor};
            color: white;
            border-radius: 8px;
            z-index: 10001;
            animation: slideInRight 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        notification.innerHTML = message;
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 3000);
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', () => {
        initDashboard();
        
        // Auto-refresh data every 30 seconds
        setInterval(() => {
            loadStats();
            loadRecentLeads();
            loadTasks();
            loadNotifications();
        }, 30000);
    });
    
    // Close modal when clicking outside
    document.getElementById('widgetPickerOverlay').addEventListener('click', function(e) {
        if (e.target === this) {
            closeWidgetPicker();
        }
    });
</script>

<?php require_once 'includes/footer.php'; 
ob_end_flush();
?>