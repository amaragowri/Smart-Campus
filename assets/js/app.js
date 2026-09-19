/**
 * SmartCampus Client-Side Framework & Utilities
 */
(function (global) {
    'use strict';

    /**
     * Retrieve CSRF token from DOM or meta tag
     */
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) return meta.content;

        const input = document.querySelector('input[name="csrf_token"]');
        if (input && input.value) return input.value;

        return '';
    }

    /**
     * Enhanced Fetch wrapper with automatic CSRF, JSON headers, and error handling
     */
    async function apiFetch(url, options = {}) {
        const headers = Object.assign({
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }, options.headers || {});

        const csrfToken = getCsrfToken();
        if (csrfToken && !headers['X-CSRF-Token']) {
            headers['X-CSRF-Token'] = csrfToken;
        }

        // If body is plain object, serialize to JSON
        if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }

        options.headers = headers;

        try {
            const response = await fetch(url, options);
            const data = await response.json().catch(() => null);

            if (!response.ok) {
                const errorMsg = (data && data.message) ? data.message : `HTTP Error ${response.status}: ${response.statusText}`;
                throw new Error(errorMsg);
            }

            return data;
        } catch (err) {
            console.error('[SmartCampus API Error]', err);
            throw err;
        }
    }

    /**
     * Theme Manager (Dark / Light Mode)
     */
    function initTheme() {
        const savedTheme = localStorage.getItem('smartcampus-theme') || 'light';
        applyTheme(savedTheme);

        const toggles = document.querySelectorAll('.theme-toggle, .home-theme-btn, [data-action="toggle-theme"]');
        toggles.forEach(btn => {
            btn.addEventListener('click', () => {
                const currentTheme = document.body.classList.contains('dark-theme') || 
                                     document.body.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                applyTheme(newTheme);
            });
        });
    }

    function applyTheme(theme) {
        localStorage.setItem('smartcampus-theme', theme);

        if (theme === 'dark') {
            document.body.classList.add('dark-theme');
            document.body.setAttribute('data-theme', 'dark');
        } else {
            document.body.classList.remove('dark-theme');
            document.body.setAttribute('data-theme', 'light');
        }

        // Update toggle button icons if available
        const toggles = document.querySelectorAll('.theme-toggle, .home-theme-btn, [data-action="toggle-theme"]');
        toggles.forEach(btn => {
            btn.textContent = theme === 'dark' ? '☀️' : '🌙';
        });
    }

    /**
     * Password Visibility Toggle Helper
     */
    function initPasswordToggles() {
        document.querySelectorAll('.password-toggle, #passwordToggle, [data-toggle="password"]').forEach(btn => {
            btn.addEventListener('click', function () {
                const targetId = this.getAttribute('data-target') || 'password';
                const input = document.getElementById(targetId) || this.previousElementSibling;
                if (!input) return;

                if (input.type === 'password') {
                    input.type = 'text';
                    this.textContent = '🙈';
                    this.title = 'Hide password';
                } else {
                    input.type = 'password';
                    this.textContent = '👁';
                    this.title = 'Show password';
                }
            });
        });
    }

    /**
     * Asynchronous Login Form Handler
     */
    function initAsyncAuth() {
        const form = document.querySelector('form[data-async-auth]');
        if (!form) return;

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            const submitBtn = form.querySelector('button[type="submit"]');
            const usernameInput = form.querySelector('input[name="username"]');
            const passwordInput = form.querySelector('input[name="password"]');
            const roleInput = form.querySelector('input[name="role"]');

            const username = usernameInput ? usernameInput.value.trim() : '';
            const password = passwordInput ? passwordInput.value : '';
            const role = roleInput ? roleInput.value : (form.getAttribute('data-role') || '');

            if (!username || !password) {
                if (global.Toast) {
                    global.Toast.warning('Please enter both username and password.');
                }
                return;
            }

            // Set loading state
            if (submitBtn) {
                submitBtn.classList.add('is-loading');
                submitBtn.disabled = true;
            }

            try {
                const payload = {
                    username: username,
                    password: password,
                    role: role,
                    csrf_token: getCsrfToken()
                };

                const apiUrl = form.getAttribute('data-api') || '../api/auth/login.php';
                const response = await apiFetch(apiUrl, {
                    method: 'POST',
                    body: payload
                });

                if (response && response.success) {
                    if (global.Toast) {
                        global.Toast.success(response.message || 'Login successful! Redirecting...');
                    }
                    setTimeout(() => {
                        window.location.href = response.redirect || '../index.php';
                    }, 800);
                } else {
                    throw new Error((response && response.message) ? response.message : 'Login failed');
                }
            } catch (err) {
                if (global.Toast) {
                    global.Toast.error(err.message || 'Login failed. Please try again.');
                }
                if (submitBtn) {
                    submitBtn.classList.remove('is-loading');
                    submitBtn.disabled = false;
                }
            }
        });
    }

    // Auto initialize on DOM ready
    document.addEventListener('DOMContentLoaded', () => {
        initTheme();
        initPasswordToggles();
        initAsyncAuth();
    });

    // Expose global namespace
    global.SmartCampus = {
        apiFetch,
        getCsrfToken,
        applyTheme,
        initAsyncAuth
    };

})(window);
