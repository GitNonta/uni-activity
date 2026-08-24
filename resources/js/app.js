import './bootstrap';
import './echo';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

const applyTheme = (theme) => {
	const isDark = theme === 'dark';
	document.documentElement.setAttribute('data-theme', theme);
	document.documentElement.classList.toggle('dark', isDark);
	document.querySelectorAll('.theme-icon-sun').forEach((element) => {
		element.style.display = isDark ? 'block' : 'none';
	});
	document.querySelectorAll('.theme-icon-moon').forEach((element) => {
		element.style.display = isDark ? 'none' : 'block';
	});
};

const savedTheme = localStorage.getItem('app-theme');
const preferredTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
applyTheme(savedTheme || preferredTheme);

document.addEventListener('click', (event) => {
	const themeToggle = event.target.closest('[data-theme-toggle]');
	if (themeToggle) {
		const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
		const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
		localStorage.setItem('app-theme', nextTheme);
		applyTheme(nextTheme);
	}
});

document.addEventListener('submit', (event) => {
	const form = event.target.closest('#loginForm');
	const button = form?.querySelector('#submitBtn');
	if (!button || button.disabled) {
		return;
	}

	button.disabled = true;
	button.innerHTML = '<svg class="login-submit-spinner" style="width:18px;height:18px;min-width:18px;min-height:18px;max-width:18px;max-height:18px;animation:login-submit-spin 1s linear infinite;display:inline-block;flex-shrink:0;vertical-align:middle;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> <span>กำลังส่งรหัส OTP...</span>';
});

window.addEventListener('pageshow', (event) => {
	if (event.persisted) {
		window.location.reload();
	}
});

Alpine.start();
