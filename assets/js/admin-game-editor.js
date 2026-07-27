(function ($) {
	'use strict';

	function updateCorrectLocationOptions() {
		var $select = $('#lk_correct_location');

		if (!$select.length) {
			return;
		}

		var current = $select.val();

		$select.find('option').each(function () {
			var value = $(this).val();

			if (!value) {
				return;
			}

			var text = $('.lk-location-input[data-location-index="' + value + '"]').val();

			if (!text) {
				text = 'Location Choice ' + value;
			}

			$(this).text(text);
		});

		$select.val(current);
	}

	function setImageState($control, attachmentId, previewHtml) {
		var $preview = $control.find('.lk-image-preview');
		var $input = $control.find('.lk-image-id');
		var $select = $control.find('.lk-select-image');
		var $remove = $control.find('.lk-remove-image');
		var hasImage = !!attachmentId;

		$input.val(attachmentId || '');
		$preview.toggleClass('is-set', hasImage);
		$preview.html(previewHtml || '');
		$select.text(hasImage ? lkGameEditor.replaceLabel : lkGameEditor.selectLabel);
		$remove.prop('disabled', !hasImage);
	}

	function openMediaFrame($control) {
		var frame = wp.media({
			title: lkGameEditor.title,
			button: {
				text: lkGameEditor.button
			},
			library: {
				type: 'image'
			},
			multiple: false
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var url = (attachment.sizes && attachment.sizes.medium)
				? attachment.sizes.medium.url
				: attachment.url;
			var previewHtml = '<img src="' + url + '" alt="" class="lk-image-preview__img" />';

			setImageState($control, attachment.id, previewHtml);
		});

		frame.open();
	}

	$(function () {
		var $details = $('.lk-game-details');

		if (!$details.length) {
			return;
		}

		$details.on('click', '.lk-select-image', function (event) {
			event.preventDefault();
			openMediaFrame($(this).closest('.lk-image-control'));
		});

		$details.on('click', '.lk-remove-image', function (event) {
			event.preventDefault();
			setImageState($(this).closest('.lk-image-control'), '', '');
		});

		$details.on('input', '.lk-location-input', updateCorrectLocationOptions);
	});
})(jQuery);
