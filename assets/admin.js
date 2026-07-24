import './stimulus_bootstrap.js';
import './admin.css';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('td[data-column="status"] .badge-warning').forEach((badge) => {
        badge.closest('tr')?.classList.add('row-awaiting-admin-reply');
    });
});
