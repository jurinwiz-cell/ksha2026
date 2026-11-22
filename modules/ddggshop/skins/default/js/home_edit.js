/* 홈 블록 라이브 인라인 편집 (관리자 전용) */
(function () {
	'use strict';
	function $(id) { return document.getElementById(id); }
	function blocks() { return Array.prototype.slice.call(document.querySelectorAll('#ds-home .ds-homeblock[data-srl]')).filter(function (b) { return b.getAttribute('data-srl') !== '0'; }); }

	/* ---- 편집 모드 토글 ---- */
	function enterMode(on) {
		document.body.classList.toggle('ddg-editmode', on);
		try { on ? sessionStorage.setItem('ddg_editmode', '1') : sessionStorage.removeItem('ddg_editmode'); } catch (e) {}
		blocks().forEach(function (b) {
			b.setAttribute('draggable', on ? 'true' : 'false');
			if (on) bindDrag(b);
		});
	}
	window.ddgHbToggleMode = function () { enterMode(!document.body.classList.contains('ddg-editmode')); };

	/* ---- 드래그 정렬 ---- */
	var dragEl = null;
	function bindDrag(b) {
		if (b._dhbBound) return; b._dhbBound = true;
		b.addEventListener('dragstart', function (e) { if (!document.body.classList.contains('ddg-editmode')) { e.preventDefault(); return; } dragEl = b; b.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', 'x'); } catch (_) {} });
		b.addEventListener('dragend', function () { b.classList.remove('dragging'); blocks().forEach(function (x) { x.classList.remove('dragover'); }); });
		b.addEventListener('dragover', function (e) { if (!dragEl || dragEl === b) return; e.preventDefault(); b.classList.add('dragover'); });
		b.addEventListener('dragleave', function () { b.classList.remove('dragover'); });
		b.addEventListener('drop', function (e) { e.preventDefault(); b.classList.remove('dragover'); if (!dragEl || dragEl === b) return;
			var rect = b.getBoundingClientRect(); var after = (e.clientY - rect.top) > rect.height / 2;
			var home = $('ds-home'); home.insertBefore(dragEl, after ? b.nextElementSibling : b);
			saveOrder();
		});
	}
	function saveOrder() {
		var s = blocks().map(function (b) { return b.getAttribute('data-srl'); });
		exec_json('ddggshop.procDdggshopAdminReorderHomeBlocks', { srls: s.join(',') }, function () {});
	}

	/* ---- 빠른 작업: 숨김/노출, 삭제 ---- */
	window.ddgHbToggle = function (srl) {
		exec_json('ddggshop.procDdggshopAdminToggleHomeBlock', { block_srl: srl }, function () { location.reload(); });
	};
	window.ddgHbDelete = function (srl) {
		if (!confirm('이 블록을 삭제할까요?')) return;
		exec_json('ddggshop.procDdggshopAdminDeleteHomeBlock', { block_srl: srl }, function () { location.reload(); });
	};

	/* ---- 드로어(추가/수정) ---- */
	function findBlock(srl) { var a = window.DDG_HB || []; for (var i = 0; i < a.length; i++) { if (parseInt(a[i].block_srl, 10) === parseInt(srl, 10)) return a[i]; } return null; }
	function setType(t) { document.querySelector('input[name=hbtype][value="' + t + '"]').checked = true; ddgHbType(); }

	window.ddgHbEdit = function (srl) {
		var b = srl ? findBlock(srl) : null;
		$('hb-srl').value = b ? b.block_srl : '';
		$('hb-title').value = b ? (b.title || '') : '';
		$('hb-img-existing').value = b ? (b.image || '') : '';
		$('hb-source').value = (b && b.block_type !== 'promo' && b.source_type) ? b.source_type : 'new';
		$('hb-cat').value = (b && b.source_type === 'category') ? b.source_value : '';
		$('hb-tagsel').value = (b && b.source_type === 'tag') ? b.source_value : '';
		$('hb-count').value = b ? (b.list_count || 8) : 8;
		$('hb-layout').value = b ? (b.layout || 'grid') : 'grid';
		$('hb-link').value = (b && b.block_type !== 'promo') ? (b.link || '') : '';
		$('hb-promolink').value = (b && b.block_type === 'promo') ? (b.link || '') : '';
		$('hb-img').value = '';
		$('hb-img-prev').innerHTML = (b && b.image) ? '<img src="' + b.image + '" alt="">' : '';
		$('hb-status').checked = b ? (b.status !== 'N') : true;
		$('hb-results').innerHTML = ''; $('hb-kw').value = '';
		// 직접 선택 칩 복원
		var chips = $('hb-chips'); chips.innerHTML = '';
		if (b && b.items) b.items.forEach(function (it) { addChip(it.item_srl, it.item_name, it.thumb); });
		setType(b ? b.block_type : 'products');
		$('ds-hb-title').textContent = b ? '블록 수정' : '블록 추가';
		$('ds-hb-drawer').classList.add('on');
	};
	window.ddgHbClose = function () { $('ds-hb-drawer').classList.remove('on'); };

	window.ddgHbType = function () {
		var t = document.querySelector('input[name=hbtype]:checked').value;
		$('hb-products').style.display = (t === 'promo') ? 'none' : '';
		$('hb-promo').style.display = (t === 'promo') ? '' : 'none';
		if (t !== 'promo') ddgHbSource();
	};
	window.ddgHbSource = function () {
		var s = $('hb-source').value;
		$('hb-manual').style.display = (s === 'manual') ? '' : 'none';
		$('hb-category').style.display = (s === 'category') ? '' : 'none';
		$('hb-tag').style.display = (s === 'tag') ? '' : 'none';
	};

	window.ddgHbSearch = function () {
		exec_json('ddggshop.procDdggshopAdminSearchItems', { keyword: $('hb-kw').value }, function (ret) {
			var box = $('hb-results'); box.innerHTML = '';
			var items = ret.items || [];
			if (!items.length) { box.innerHTML = '<div class="ds-hb-nores">검색 결과가 없습니다.</div>'; return; }
			items.forEach(function (it) {
				var d = document.createElement('div'); d.className = 'ds-hb-result';
				d.innerHTML = (it.thumb ? '<img src="' + it.thumb + '" alt="">' : '<span class="ds-hb-noimg"></span>') + '<span class="ds-hb-rn">' + it.item_name + '</span><button type="button">추가</button>';
				d.querySelector('button').addEventListener('click', function () { addChip(it.item_srl, it.item_name, it.thumb); });
				box.appendChild(d);
			});
		});
	};
	function addChip(srl, name, thumb) {
		if (document.querySelector('#hb-chips .ds-hb-chip[data-srl="' + srl + '"]')) return;
		var c = document.createElement('span'); c.className = 'ds-hb-chip'; c.setAttribute('data-srl', srl);
		c.innerHTML = (thumb ? '<img src="' + thumb + '" alt="">' : '') + '<span>' + name + '</span><button type="button">×</button>';
		c.querySelector('button').addEventListener('click', function () { c.parentNode.removeChild(c); });
		$('hb-chips').appendChild(c);
	}

	window.ddgHbSave = function () {
		var type = document.querySelector('input[name=hbtype]:checked').value;
		var fd = new FormData();
		fd.append('module', 'admin');
		fd.append('act', 'procDdggshopAdminSaveHomeBlock');
		fd.append('block_srl', $('hb-srl').value);
		fd.append('block_type', type);
		fd.append('title', $('hb-title').value);
		fd.append('status', $('hb-status').checked ? 'Y' : 'N');
		if (type === 'promo') {
			fd.append('image', $('hb-img-existing').value);
			fd.append('promo_link', $('hb-promolink').value);
			if ($('hb-img').files && $('hb-img').files[0]) fd.append('image_file', $('hb-img').files[0]);
		} else {
			var src = $('hb-source').value;
			fd.append('source_type', src);
			fd.append('list_count', $('hb-count').value);
			fd.append('layout', $('hb-layout').value);
			fd.append('link', $('hb-link').value);
			fd.append('category_srl', $('hb-cat').value);
			fd.append('tag_srl', $('hb-tagsel').value);
			var srls = []; document.querySelectorAll('#hb-chips .ds-hb-chip').forEach(function (c) { srls.push(c.getAttribute('data-srl')); });
			fd.append('item_srls', srls.join(','));
		}
		// FormData(멀티파트) 전송은 exec_json('raw', ...) 사용 + CSRF 토큰을 필드로 전달
		fd.append('_rx_csrf_token', getCSRFToken());
		exec_json('raw', fd, function () { location.reload(); });
	};

	/* ---- 초기화: 새로고침 후 편집모드 유지 ---- */
	document.addEventListener('DOMContentLoaded', function () {
		var keep = false; try { keep = sessionStorage.getItem('ddg_editmode') === '1'; } catch (e) {}
		if (keep) enterMode(true);
		$('hb-img') && $('hb-img').addEventListener('change', function () {
			var f = this.files && this.files[0];
			if (f) { var r = new FileReader(); r.onload = function (e) { $('hb-img-prev').innerHTML = '<img src="' + e.target.result + '" alt="">'; }; r.readAsDataURL(f); }
		});
	});
})();
