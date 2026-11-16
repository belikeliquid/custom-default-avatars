/* Custom Default Avatars by Liquid Media: image set manager. */
(function ($) {
	'use strict';

	var $grid = $('#lmar-grid');
	var $input = $('#lmar-images');
	var $empty = $('#lmar-empty');
	var frame;

	function sync() {
		var ids = $grid.children('.lmar-item').map(function () {
			return $(this).attr('data-id');
		}).get();
		$input.val(ids.join(','));
		$empty.prop('hidden', ids.length > 0);
	}

	function addItem(id, url, title) {
		if ($grid.children('[data-id="' + id + '"]').length) {
			return;
		}
		var $li = $('<li class="lmar-item"/>').attr('data-id', id);
		$('<img alt=""/>').attr('src', url).appendTo($li);
		$('<button type="button" class="lmar-remove">&times;</button>')
			.attr('aria-label', lmarData.remove)
			.appendTo($li);
		if (title) {
			$li.attr('title', title);
		}
		$grid.append($li);
	}

	$grid.on('click', '.lmar-remove', function () {
		$(this).closest('.lmar-item').remove();
		sync();
	});

	$('#lmar-add').on('click', function (e) {
		e.preventDefault();
		if (!frame) {
			frame = wp.media({
				title: lmarData.frameTitle,
				button: { text: lmarData.frameButton },
				library: { type: 'image' },
				multiple: 'add'
			});
			frame.on('select', function () {
				var warn = false;
				frame.state().get('selection').each(function (att) {
					var a = att.toJSON();
					var sizes = a.sizes || {};
					var pick = sizes.medium || sizes.full || { url: a.url };
					if (!/\.(png|jpe?g|gif)$/i.test(pick.url)) {
						warn = true;
					}
					addItem(String(a.id), pick.url, a.title);
				});
				sync();
				if (warn) {
					window.alert(lmarData.webpNote);
				}
			});
		}
		frame.open();
	});

	$('#lmar-reset').on('click', function (e) {
		e.preventDefault();
		if (!window.confirm(lmarData.confirmReset)) {
			return;
		}
		$grid.empty();
		$.each(lmarData.defaults, function (i, d) {
			addItem(d.id, d.url);
		});
		sync();
	});
})(jQuery);
