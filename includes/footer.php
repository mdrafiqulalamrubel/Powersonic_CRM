            </div> <!-- Close content-area -->
        </div> <!-- Close main-content -->
    </div> <!-- Close crm-wrapper -->

    <script>
        // Toggle Sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            if (sidebar && mainContent) {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                
                // Save state to localStorage
                const isCollapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('sidebarCollapsed', isCollapsed);
            }
        }
        
        // Toggle Submenu
        function toggleSubmenu(element) {
            if (!element) return;
            element.classList.toggle('open');
            const submenu = element.nextElementSibling;
            if (submenu && submenu.classList) {
                submenu.classList.toggle('open');
            }
        }
        
        // Load saved sidebar state
        document.addEventListener('DOMContentLoaded', function() {
            const savedState = localStorage.getItem('sidebarCollapsed');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            if (savedState === 'true' && sidebar && mainContent) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }
            
            // Keep submenus open based on active page
            document.querySelectorAll('.submenu .menu-item.active').forEach(function(activeItem) {
                const submenu = activeItem.closest('.submenu');
                if (submenu) {
                    submenu.classList.add('open');
                    const parentMenu = submenu.previousElementSibling;
                    if (parentMenu && parentMenu.classList && parentMenu.classList.contains('has-submenu')) {
                        parentMenu.classList.add('open');
                    }
                }
            });
        });
        
        // Mobile menu handling
        if (window.innerWidth <= 768) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.toggle-sidebar');
            
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    if (sidebar) sidebar.classList.toggle('mobile-open');
                });
            }
        }
    </script>
</body>
</html>