/**
 * How To Play video modal.
 * Pattern follows comparison dialog: native <dialog>, Escape, overlay click, focus restore.
 */
(function () {
	'use strict';

	var openBtn = document.querySelector('.lk-how-to-play-watch');
	var dialog = document.getElementById('lk-how-to-play-dialog');

	if (!openBtn || !dialog) {
		return;
	}

	var closeBtn = dialog.querySelector('[data-lk-how-to-play-close]');
	var video = dialog.querySelector('.lk-how-to-play-video');
	var lastTrigger = null;

	function pauseVideo() {
		if (video && typeof video.pause === 'function') {
			video.pause();
		}
	}

	function openDialog() {
		lastTrigger = openBtn;

		if (typeof dialog.showModal === 'function') {
			dialog.showModal();
		} else {
			dialog.setAttribute('open', 'open');
		}

		if (closeBtn && typeof closeBtn.focus === 'function') {
			closeBtn.focus();
		}
	}

	function closeDialog() {
		pauseVideo();

		if (typeof dialog.close === 'function' && dialog.open) {
			dialog.close();
		} else {
			dialog.removeAttribute('open');
		}

		if (lastTrigger && typeof lastTrigger.focus === 'function') {
			lastTrigger.focus();
		}
		lastTrigger = null;
	}

	openBtn.addEventListener('click', function (event) {
		event.preventDefault();
		openDialog();
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

	dialog.addEventListener('close', function () {
		pauseVideo();
	});

	dialog.addEventListener('click', function (event) {
		if (event.target === dialog) {
			closeDialog();
		}
	});
})();
