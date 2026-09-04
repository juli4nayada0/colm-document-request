/**
 * COLM Registrar Document Request and Tracking System
 * Navigation & Tab Switcher Module
 */

const navigation = {
    init() {
        const toggleBtn = document.getElementById('btnToggleSidebar');
        const sidebar = document.getElementById('appSidebar');
        let backdrop = document.getElementById('sidebarBackdrop');

        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.id = 'sidebarBackdrop';
            backdrop.className = 'sidebar-backdrop';
            document.body.appendChild(backdrop);
        }

        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('sidebar-open');
                backdrop.classList.toggle('show');
            });

            backdrop.addEventListener('click', () => {
                sidebar.classList.remove('sidebar-open');
                backdrop.classList.remove('show');
            });
        }

        // Setup Tab Navigation if nav-items have data-view attribute
        const navItems = document.querySelectorAll('.nav-item[data-view]');
        navItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const viewId = item.getAttribute('data-view');
                this.switchView(viewId);

                // On mobile, close sidebar after clicking
                if (sidebar) {
                    sidebar.classList.remove('sidebar-open');
                    backdrop.classList.remove('show');
                }
            });
        });

        // Check URL hash on page load
        const hash = window.location.hash.replace('#', '');
        if (hash) {
            this.switchView(hash);
        }
    },

    switchView(viewId) {
        if (!viewId) return;

        // Update active class on navigation links
        document.querySelectorAll('.nav-item[data-view]').forEach(item => {
            if (item.getAttribute('data-view') === viewId) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });

        // Hide all view panes and show target view
        document.querySelectorAll('.view-pane').forEach(pane => {
            pane.style.display = 'none';
        });

        const targetPane = document.getElementById(`view_${viewId}`);
        if (targetPane) {
            targetPane.style.display = 'block';
            window.location.hash = viewId;

            // Trigger view specific refresh handler if registered
            if (window.onViewChanged && typeof window.onViewChanged === 'function') {
                window.onViewChanged(viewId);
            }
        }
    }
};

window.navigation = navigation;
