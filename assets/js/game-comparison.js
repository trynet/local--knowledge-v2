/**
 * Comparison-image enlargement only (View 5).
 * Does not submit answers or change gameplay state.
 */
(function () {
	'use strict';

	var root = document.querySelector('.lk-game__comparison');
	if (!root) {
		return;
	}

	var dialog = root.querySelector('.lk-game__comparison-dialog');
	var dialogImage = root.querySelector('.lk-game__comparison-dialog-image');
	var closeBtn = root.querySelector('[data-lk-dialog-close]');
	var triggers = root.querySelectorAll('.lk-game__comparison-trigger');

	if (!dialog || !dialogImage || !triggers.length) {
		return;
	}

	var lastTrigger = null;

	function openFromTrigger(trigger) {
		var src = trigger.getAttribute('data-lk-full-src') || '';
		var alt = trigger.getAttribute('data-lk-full-alt') || '';

		if (!src) {
			return;
		}

		lastTrigger = trigger;
		dialogImage.setAttribute('src', src);
		dialogImage.setAttribute('alt', alt);

		if (typeof dialog.showModal === 'function') {
			dialog.showModal();
		} else {
			dialog.setAttribute('open', 'open');
		}

		if (closeBtn) {
			closeBtn.focus();
		}
	}

	function closeDialog() {
		if (typeof dialog.close === 'function' && dialog.open) {
			dialog.close();
		} else {
			dialog.removeAttribute('open');
		}

		dialogImage.removeAttribute('src');
		dialogImage.setAttribute('alt', '');

		if (lastTrigger && typeof lastTrigger.focus === 'function') {
			lastTrigger.focus();
		}
		lastTrigger = null;
	}

	triggers.forEach(function (trigger) {
		trigger.addEventListener('click', function (event) {
			event.preventDefault();
			openFromTrigger(trigger);
		});
	});

	if (closeBtn) {
		closeBtn.addEventListener('click', function (event) {
			event.preventDefault();
			closeDialog();
		});
	}

	dialog.addEventListener('cancel', function (event) {
		event.preventDefault();
		closeDialog();
	});

	dialog.addEventListener('click', function (event) {
		if (event.target === dialog) {
			closeDialog();
		}
	});
})();
