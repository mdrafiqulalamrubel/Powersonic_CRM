    </div> <!-- Close content-area -->
            </div> <!-- Close main-content -->
        </div> <!-- Close crm-wrapper -->

        <!-- Dark Mode Toggle Button -->
        <button class="dark-mode-toggle" id="darkModeToggle" title="Toggle Dark Mode (Ctrl+D)">
            🌙
        </button>

        <!-- Shortcuts Hint -->
        <div class="shortcuts-hint" id="shortcutsHint" onclick="showShortcutsHelp()">
            ⌨️ <kbd>Ctrl+S</kbd> Save | <kbd>?</kbd> Help
        </div>

        <!-- Autosave Indicator -->
        <div id="autosave-indicator">💾 Saving...</div>

        <script>
            // ============================================
            // DARK MODE TOGGLE
            // ============================================
            const darkModeToggle = document.getElementById('darkModeToggle');
            const isDarkMode = localStorage.getItem('darkMode') === 'enabled';

            if (isDarkMode) {
                document.body.classList.add('dark-mode');
                darkModeToggle.innerHTML = '☀️';
            }

            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', () => {
                    document.body.classList.toggle('dark-mode');
                    
                    if (document.body.classList.contains('dark-mode')) {
                        localStorage.setItem('darkMode', 'enabled');
                        darkModeToggle.innerHTML = '☀️';
                    } else {
                        localStorage.setItem('darkMode', 'disabled');
                        darkModeToggle.innerHTML = '🌙';
                    }
                });
            }

            // ============================================
            // TOAST NOTIFICATION SYSTEM
            // ============================================
            function showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `toast-notification toast-${type}`;
                notification.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle')}"></i>
                        <span>${message}</span>
                        <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: white; cursor: pointer;">×</button>
                    </div>
                `;
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.style.animation = 'fadeOut 0.3s ease';
                    setTimeout(() => notification.remove(), 300);
                }, 3000);
            }

            // ============================================
            // KEYBOARD SHORTCUTS
            // ============================================
            function saveCurrentForm() {
                const form = document.querySelector('form');
                if (form) {
                    // Check if form has submit button
                    const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                    if (submitBtn) {
                        submitBtn.click();
                    } else {
                        form.submit();
                    }
                    showNotification('Form submitted!', 'success');
                } else {
                    showNotification('No form found on this page', 'warning');
                }
            }

            function showShortcutsHelp() {
                const modal = document.createElement('div');
                modal.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.7);
                    z-index: 10001;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                `;
                modal.innerHTML = `
                    <div style="background: var(--card-bg); padding: 30px; border-radius: 12px; max-width: 500px; width: 90%;">
                        <h3 style="margin-bottom: 20px; color: var(--text-primary);">
                            <i class="fas fa-keyboard"></i> Keyboard Shortcuts
                        </h3>
                        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 10px;">
                            <div><kbd>Ctrl + S</kbd></div><div>Save current form</div>
                            <div><kbd>Ctrl + N</kbd></div><div>New Lead</div>
                            <div><kbd>Ctrl + F</kbd></div><div>Focus search box</div>
                            <div><kbd>Ctrl + H</kbd></div><div>Go to Dashboard</div>
                            <div><kbd>Ctrl + D</kbd></div><div>Toggle Dark Mode</div>
                            <div><kbd>Alt + N</kbd></div><div>Next Lead</div>
                            <div><kbd>Alt + P</kbd></div><div>Previous Lead</div>
                            <div><kbd>Delete</kbd></div><div>Delete selected item</div>
                            <div><kbd>Escape</kbd></div><div>Close modal</div>
                            <div><kbd>?</kbd></div><div>Show this help</div>
                        </div>
                        <button onclick="this.parentElement.parentElement.remove()" style="margin-top: 20px; padding: 8px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer;">
                            Close
                        </button>
                    </div>
                `;
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) modal.remove();
                });
                document.body.appendChild(modal);
            }

            document.addEventListener('keydown', function(e) {
                const isCtrl = e.ctrlKey || e.metaKey;
                
                // Ctrl + S - Save
                if (isCtrl && e.key === 's') {
                    e.preventDefault();
                    saveCurrentForm();
                }
                
                // Ctrl + N - New Lead
                if (isCtrl && e.key === 'n') {
                    e.preventDefault();
                    window.location.href = 'add_lead.php';
                }
                
                // Ctrl + F - Focus Search
                if (isCtrl && e.key === 'f') {
                    e.preventDefault();
                    const searchInput = document.querySelector('input[type="search"], #searchInput, .search-input');
                    if (searchInput) {
                        searchInput.focus();
                        showNotification('Search focused', 'info');
                    }
                }
                
                // Ctrl + H - Dashboard
                if (isCtrl && e.key === 'h') {
                    e.preventDefault();
                    const dashboardLink = document.querySelector('a[href*="dashboard.php"]');
                    if (dashboardLink) window.location.href = dashboardLink.href;
                }
                
                // Ctrl + D - Dark Mode
                if (isCtrl && e.key === 'd') {
                    e.preventDefault();
                    if (darkModeToggle) darkModeToggle.click();
                }
                
                // Escape - Close modals
                if (e.key === 'Escape') {
                    document.querySelectorAll('.modal, [class*="modal"]').forEach(modal => {
                        if (modal.style.display !== 'none') {
                            modal.style.remove();
                        }
                    });
                }
                
                // ? - Show help
                if (e.key === '?' && !isCtrl && !e.altKey) {
                    e.preventDefault();
                    showShortcutsHelp();
                }
            });

            // ============================================
            // INLINE EDITING
            // ============================================
            function makeInlineEditable() {
                const editableFields = document.querySelectorAll('.editable-field');
                
                editableFields.forEach(field => {
                    field.addEventListener('click', async function(e) {
                        e.stopPropagation();
                        
                        const originalValue = this.innerText;
                        const fieldName = this.dataset.field;
                        const leadId = this.dataset.leadId;
                        const fieldType = this.dataset.type || 'text';
                        
                        let input;
                        if (fieldType === 'select') {
                            input = document.createElement('select');
                            const options = this.dataset.options || '';
                            options.split(',').forEach(opt => {
                                const option = document.createElement('option');
                                option.value = opt.trim();
                                option.textContent = opt.trim();
                                if (opt.trim() === originalValue) option.selected = true;
                                input.appendChild(option);
                            });
                        } else {
                            input = document.createElement('input');
                            input.type = fieldType;
                            input.value = originalValue;
                        }
                        
                        input.className = 'inline-edit-input';
                        input.style.width = Math.max(this.offsetWidth, 100) + 'px';
                        
                        const originalContent = this.innerHTML;
                        this.innerHTML = '';
                        this.appendChild(input);
                        input.focus();
                        
                        const saveEdit = async () => {
                            const newValue = input.value;
                            
                            try {
                                const response = await fetch('ajax_handlers.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({
                                        action: 'update_lead_field',
                                        lead_id: leadId,
                                        field: fieldName,
                                        value: newValue
                                    })
                                });
                                
                                const result = await response.json();
                                
                                if (result.success) {
                                    this.innerText = newValue;
                                    showNotification(`${fieldName} updated!`, 'success');
                                } else {
                                    this.innerText = originalValue;
                                    showNotification('Update failed: ' + (result.error || 'Unknown error'), 'error');
                                }
                            } catch (error) {
                                this.innerText = originalValue;
                                showNotification('Network error', 'error');
                            }
                        };
                        
                        input.addEventListener('blur', saveEdit);
                        input.addEventListener('keypress', (e) => {
                            if (e.key === 'Enter') {
                                input.blur();
                            }
                            if (e.key === 'Escape') {
                                this.innerText = originalValue;
                                input.remove();
                            }
                        });
                    });
                });
            }

            // ============================================
            // AUTOSAVE
            // ============================================
            let autosaveTimeout = null;
            const AUTOSAVE_DELAY = 2000;

            function initAutosave() {
                const forms = document.querySelectorAll('form');
                
                forms.forEach(form => {
                    const inputs = form.querySelectorAll('input, textarea, select');
                    inputs.forEach(input => {
                        input.addEventListener('input', () => {
                            clearTimeout(autosaveTimeout);
                            showAutosaveIndicator(true);
                            
                            autosaveTimeout = setTimeout(() => {
                                saveFormAutosave(form);
                            }, AUTOSAVE_DELAY);
                        });
                    });
                });
                
                // Restore autosaved data
                restoreAutosave();
            }

            function showAutosaveIndicator(saving = true) {
                const indicator = document.getElementById('autosave-indicator');
                if (indicator) {
                    if (saving) {
                        indicator.style.display = 'block';
                        indicator.innerHTML = '💾 Saving draft...';
                    } else {
                        indicator.innerHTML = '✓ Draft saved';
                        setTimeout(() => {
                            indicator.style.display = 'none';
                        }, 1500);
                    }
                }
            }

            async function saveFormAutosave(form) {
                const formId = form.id || 'autosave-form';
                const formData = new FormData(form);
                const leadId = formData.get('id');
                
                // Save to localStorage
                const saveData = {};
                formData.forEach((value, key) => {
                    if (key !== 'submit') saveData[key] = value;
                });
                localStorage.setItem(`autosave_${formId}`, JSON.stringify(saveData));
                
                // If we have a lead ID, save to server
                if (leadId && leadId > 0) {
                    try {
                        const response = await fetch('ajax_handlers.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                action: 'autosave_lead',
                                lead_id: leadId,
                                data: saveData
                            })
                        });
                        
                        if (response.ok) {
                            showAutosaveIndicator(false);
                            localStorage.removeItem(`autosave_${formId}`);
                        }
                    } catch (error) {
                        console.error('Autosave failed:', error);
                    }
                } else {
                    showAutosaveIndicator(false);
                }
            }

            function restoreAutosave() {
                const forms = document.querySelectorAll('form');
                forms.forEach(form => {
                    const formId = form.id || 'autosave-form';
                    const savedData = localStorage.getItem(`autosave_${formId}`);
                    
                    if (savedData && !form.querySelector('[name="id"]')?.value) {
                        const data = JSON.parse(savedData);
                        const restoreDiv = document.createElement('div');
                        restoreDiv.style.cssText = `
                            position: fixed;
                            top: 80px;
                            left: 50%;
                            transform: translateX(-50%);
                            background: #3498db;
                            color: white;
                            padding: 12px 20px;
                            border-radius: 8px;
                            z-index: 10000;
                            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                        `;
                        restoreDiv.innerHTML = `
                            You have unsaved draft. 
                            <button onclick="restoreDraft('${formId}')" style="background: white; color: #3498db; border: none; padding: 4px 12px; margin: 0 5px; border-radius: 4px; cursor: pointer;">Restore</button>
                            <button onclick="discardDraft('${formId}')" style="background: rgba(255,255,255,0.3); color: white; border: none; padding: 4px 12px; border-radius: 4px; cursor: pointer;">Discard</button>
                        `;
                        document.body.appendChild(restoreDiv);
                        
                        window.restoreDraft = (fid) => {
                            const saved = localStorage.getItem(`autosave_${fid}`);
                            if (saved) {
                                const data = JSON.parse(saved);
                                Object.keys(data).forEach(key => {
                                    const input = document.querySelector(`[name="${key}"]`);
                                    if (input) input.value = data[key];
                                });
                            }
                            restoreDiv.remove();
                            showNotification('Draft restored', 'success');
                        };
                        
                        window.discardDraft = (fid) => {
                            localStorage.removeItem(`autosave_${fid}`);
                            restoreDiv.remove();
                            showNotification('Draft discarded', 'info');
                        };
                    }
                });
            }

            // Clear autosave on form submit
            document.addEventListener('submit', (e) => {
                if (e.target.tagName === 'FORM') {
                    const formId = e.target.id || 'autosave-form';
                    localStorage.removeItem(`autosave_${formId}`);
                }
            });

            // ============================================
            // SIDEBAR & MENU FUNCTIONS (Existing)
            // ============================================
            function toggleSidebar() {
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.getElementById('mainContent');
                
                if (sidebar && mainContent) {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('expanded');
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                }
            }
            
            function toggleSubmenu(element) {
                if (!element) return;
                element.classList.toggle('open');
                const submenu = element.nextElementSibling;
                if (submenu && submenu.classList) {
                    submenu.classList.toggle('open');
                }
            }
            
            // Initialize all features
            document.addEventListener('DOMContentLoaded', function() {
                // Sidebar state
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
                
                // Initialize inline editing
                makeInlineEditable();
                
                // Initialize autosave
                initAutosave();
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