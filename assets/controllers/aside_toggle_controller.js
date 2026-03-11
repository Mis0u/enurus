import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('sidebar-toggle');
        const isCollapsed = sidebar.classList.toggle('collapsed');

        toggle.style.left = isCollapsed ? '54px' : '227px';
        toggle.querySelector('svg').style.transform = isCollapsed ? 'rotate(180deg)' : '';
    }
}
