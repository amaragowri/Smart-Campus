/**
 * SmartCampus Toast Notification Library
 */
(function (global) {
    'use strict';

    let container = null;

    function getContainer() {
        if (!container || !document.body.contains(container)) {
            container = document.createElement('div');
            container.id = 'smartcampus-toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    const ICONS = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };

    const TITLES = {
        success: 'Success',
        error: 'Error',
        warning: 'Notice',
        info: 'Information'
    };

    function show(options) {
        if (typeof options === 'string') {
            options = { message: options };
        }

        const type = options.type || 'info';
        const title = options.title || TITLES[type] || 'Notice';
        const message = options.message || '';
        const duration = options.duration !== undefined ? options.duration : 4000;

        const parent = getContainer();

        const toast = document.createElement('div');
        toast.className = `sc-toast sc-toast-${type}`;

        toast.innerHTML = `
            <div class="sc-toast-icon">${ICONS[type] || '🔔'}</div>
            <div class="sc-toast-content">
                <div class="sc-toast-title">${escapeHtml(title)}</div>
                <div class="sc-toast-message">${escapeHtml(message)}</div>
            </div>
            <button type="button" class="sc-toast-close" aria-label="Close">&times;</button>
        `;

        function dismiss() {
            if (toast.classList.contains('sc-toast-hiding')) return;
            toast.classList.add('sc-toast-hiding');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }

        toast.querySelector('.sc-toast-close').addEventListener('click', (e) => {
            e.stopPropagation();
            dismiss();
        });

        toast.addEventListener('click', dismiss);

        if (duration > 0) {
            setTimeout(dismiss, duration);
        }

        parent.appendChild(toast);
        return toast;
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    global.Toast = {
        show: show,
        success: (msg, title = 'Success', duration = 3500) => show({ type: 'success', title, message: msg, duration }),
        error: (msg, title = 'Error', duration = 4500) => show({ type: 'error', title, message: msg, duration }),
        warning: (msg, title = 'Warning', duration = 4000) => show({ type: 'warning', title, message: msg, duration }),
        info: (msg, title = 'Info', duration = 3500) => show({ type: 'info', title, message: msg, duration })
    };
})(window);
