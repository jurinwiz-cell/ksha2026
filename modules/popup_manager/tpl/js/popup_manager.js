(function() {
	'use strict';

	// Initialize popups on DOM ready
	document.addEventListener('DOMContentLoaded', function() {
		if (typeof popup_manager_popups === 'undefined' || !popup_manager_popups.length) {
			return;
		}

		popup_manager_popups.forEach(function(popup) {
			// Skip popups the user chose to hide (cookie check on the client,
			// so it works even when the page HTML is served from cache).
			if (getCookie('popup_hide_' + popup.srl)) {
				return;
			}
			createPopup(popup);
		});
	});

	function createPopup(popup) {
		// Create popup container
		var container = document.createElement('div');
		container.id = 'popup_manager_' + popup.srl;
		container.className = 'popup-manager-popup';

		var styles = [
			'position: fixed',
			'top: ' + popup.top + 'px',
			'left: ' + popup.left + 'px',
			'width: ' + popup.width + 'px',
			'z-index: 10000'
		];

		if (popup.height > 0) {
			styles.push('height: ' + popup.height + 'px');
		}

		container.style.cssText = styles.join('; ');

		// Popup header for dragging
		var header = document.createElement('div');
		header.className = 'popup-manager-header';
		header.innerHTML = '<span class="popup-manager-title">' + escapeHtml(popup.title) + '</span>';
		container.appendChild(header);

		// Popup content
		var content = document.createElement('div');
		content.className = 'popup-manager-content';

		if (popup.type === 'image' && popup.image_url) {
			// Image popup
			var img = document.createElement('img');
			img.src = popup.image_url;
			img.alt = popup.title;
			img.style.cssText = 'max-width: 100%; height: auto; display: block;';

			if (popup.image_link) {
				var link = document.createElement('a');
				link.href = popup.image_link;
				link.target = popup.link_target || '_self';
				link.appendChild(img);
				content.appendChild(link);
			} else {
				content.appendChild(img);
			}
		} else {
			// HTML popup
			content.innerHTML = popup.content;
		}

		container.appendChild(content);

		// Close bar with "don't show" checkbox
		var closeBar = document.createElement('div');
		closeBar.className = 'popup-manager-close-bar';
		closeBar.innerHTML =
			'<label class="popup-manager-hide-label">' +
				'<input type="checkbox" class="popup-hide-check" data-srl="' + popup.srl + '" data-days="' + popup.cookie_days + '" /> ' +
				popup.cookie_days + '일간 보지않기' +
			'</label>' +
			'<button type="button" class="popup-close-btn" data-srl="' + popup.srl + '">닫기</button>';
		container.appendChild(closeBar);

		document.body.appendChild(container);

		// Bind close button event
		closeBar.querySelector('.popup-close-btn').addEventListener('click', function() {
			closePopup(popup.srl);
		});

		// Bind checkbox event
		closeBar.querySelector('.popup-hide-check').addEventListener('change', function() {
			if (this.checked) {
				setCookie('popup_hide_' + popup.srl, '1', popup.cookie_days);
				closePopup(popup.srl);
			}
		});

		// Make draggable
		makeDraggable(container, header);
	}

	function closePopup(srl) {
		var popup = document.getElementById('popup_manager_' + srl);
		if (popup) {
			popup.style.opacity = '0';
			popup.style.transform = 'scale(0.9)';
			setTimeout(function() {
				popup.remove();
			}, 200);
		}
	}

	function setCookie(name, value, days) {
		var expires = '';
		if (days) {
			var date = new Date();
			date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
			expires = '; expires=' + date.toUTCString();
		}
		document.cookie = name + '=' + (value || '') + expires + '; path=/';
	}

	function getCookie(name) {
		var prefix = name + '=';
		var parts = document.cookie ? document.cookie.split(';') : [];
		for (var i = 0; i < parts.length; i++) {
			var c = parts[i].replace(/^\s+/, '');
			if (c.indexOf(prefix) === 0) {
				return decodeURIComponent(c.substring(prefix.length));
			}
		}
		return '';
	}

	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	function makeDraggable(element, handle) {
		var pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;

		handle.style.cursor = 'move';
		handle.addEventListener('mousedown', dragMouseDown);

		function dragMouseDown(e) {
			e.preventDefault();
			pos3 = e.clientX;
			pos4 = e.clientY;
			document.addEventListener('mouseup', closeDragElement);
			document.addEventListener('mousemove', elementDrag);
		}

		function elementDrag(e) {
			e.preventDefault();
			pos1 = pos3 - e.clientX;
			pos2 = pos4 - e.clientY;
			pos3 = e.clientX;
			pos4 = e.clientY;
			element.style.top = (element.offsetTop - pos2) + 'px';
			element.style.left = (element.offsetLeft - pos1) + 'px';
		}

		function closeDragElement() {
			document.removeEventListener('mouseup', closeDragElement);
			document.removeEventListener('mousemove', elementDrag);
		}
	}
})();
