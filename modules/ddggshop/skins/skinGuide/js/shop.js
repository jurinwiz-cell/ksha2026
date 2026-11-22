/* ddggshop 기본 스킨 스크립트 */
(function () {
	function shopMid() {
		var el = document.querySelector('.ddggshop');
		return el ? el.getAttribute('data-mid') : '';
	}
	function shopBase() {
		var el = document.querySelector('.ddggshop');
		var b = el ? el.getAttribute('data-base') : '';
		return b || '/';
	}
	function shopUrl(act, extra) {
		var u = shopBase() + '?mid=' + encodeURIComponent(shopMid()) + '&act=' + encodeURIComponent(act);
		if (extra) {
			for (var k in extra) { if (extra.hasOwnProperty(k)) u += '&' + k + '=' + encodeURIComponent(extra[k]); }
		}
		return u;
	}

	/* iOS 안전영역: viewport-fit=cover 보장 → 오버레이가 노치/홈인디케이터까지 덮도록 */
	(function () {
		var vp = document.querySelector('meta[name=viewport]');
		if (vp && vp.content.indexOf('viewport-fit') < 0) vp.setAttribute('content', vp.content + ', viewport-fit=cover');
	})();
	var DDG_OVERLAYS = 0;
	function ddggOverlayLock(on) {
		if (on) { DDG_OVERLAYS++; document.documentElement.classList.add('ddgg-noscroll'); }
		else { DDG_OVERLAYS = Math.max(0, DDG_OVERLAYS - 1); if (DDG_OVERLAYS === 0) document.documentElement.classList.remove('ddgg-noscroll'); }
	}

	/* 상세 수량 조절 */
	function ddggDisc(unit) {
		var rules = window.DDG_DISC_RULES || [];
		if (!rules.length || unit <= 0) return unit;
		var best = unit;
		for (var i = 0; i < rules.length; i++) {
			var r = rules[i], p = (r.type === 'amount') ? (unit - r.value) : Math.floor(unit * (100 - Math.min(100, r.value)) / 100);
			if (p < 0) p = 0;
			if (p < best) best = p;
		}
		return best;
	}
	function ddggSingleTotal() {
		var el = document.getElementById('ds-single-total');
		var q = document.getElementById('ds-qty');
		if (!el || !q) return;
		var price = parseInt(el.getAttribute('data-price'), 10) || 0;
		var v = parseInt(q.value, 10) || 1;
		if (v < 1) v = 1;
		var label = el.getAttribute('data-label') || dsl('js_opt_total');
		el.innerHTML = '<span class="ds-single-total-label">' + label + '</span> <strong>' + (price * v).toLocaleString() + dsl('won') + '</strong>';
	}
	window.ddggQty = function (d) {
		var q = document.getElementById('ds-qty');
		if (!q) return;
		var v = parseInt(q.value, 10) || 1;
		v += d;
		if (v < 1) v = 1;
		q.value = v;
		ddggSingleTotal();
	};
	document.addEventListener('DOMContentLoaded', function () {
		var q = document.getElementById('ds-qty');
		if (q && document.getElementById('ds-single-total')) {
			q.addEventListener('input', ddggSingleTotal);
			q.addEventListener('change', ddggSingleTotal);
			ddggSingleTotal();
		}
	});
	window.ddggMainImg = function (src) {
		var m = document.getElementById('ds-main-img');
		if (m) m.src = src;
	};

	function detailQty() {
		var q = document.getElementById('ds-qty');
		var v = q ? parseInt(q.value, 10) : 1;
		return (v && v > 0) ? v : 1;
	}

	/* 장바구니 담기 */
	window.ddggshopAddCart = function (item_srl) {
		exec_json('ddggshop.procDdggshopAddCart', { item_srl: item_srl, qty: detailQty() }, function (ret) {
			if (confirm(dsl('js_added_go'))) {
				location.href = shopUrl('dispDdggshopCart');
			}
		});
	};

	/* 바로구매 — 장바구니를 거치지 않고 주문서로 (단일 상품) */
	window.ddggshopBuyNow = function (item_srl) {
		location.href = shopUrl('dispDdggshopOrder', { buy_item_srl: item_srl, buy_qty: detailQty() });
	};

	/* 장바구니 수량 변경 */
	window.ddggshopCartQty = function (cart_srl, qty) {
		qty = parseInt(qty, 10);
		if (!qty || qty < 1) qty = 1;
		exec_json('ddggshop.procDdggshopUpdateCart', { cart_srl: cart_srl, qty: qty }, function () {
			location.reload();
		});
	};

	/* 장바구니 삭제 */
	window.ddggshopCartRemove = function (cart_srl) {
		if (!confirm(dsl('js_cart_remove_confirm'))) return;
		exec_json('ddggshop.procDdggshopDeleteCart', { cart_srl: cart_srl }, function () {
			location.reload();
		});
	};

	function val(id) {
		var el = document.getElementById(id);
		return el ? el.value : '';
	}

	/* 배송지: 주문자 정보와 동일 */
	window.ddggSameAsOrderer = function (chk) {
		if (!chk.checked) return;
		var on = document.getElementById('ds-orderer-name');
		var op = document.getElementById('ds-orderer-phone');
		var rn = document.getElementById('ds-receiver-name');
		var rp = document.getElementById('ds-receiver-phone');
		if (on && rn) rn.value = on.value;
		if (op && rp) rp.value = op.value;
	};

	/* 합계 재계산 (기본 + 추가배송 - 쿠폰할인 - 적립금) */
	window.DDG_EXTRA = 0; window.DDG_DISCOUNT = 0; window.DDG_COUPON = ''; window.DDG_POINTUSE = 0;
	function ddggRecalcTotal() {
		var pay = (window.DDG_PAY || 0) + (window.DDG_EXTRA || 0) - (window.DDG_DISCOUNT || 0) - (window.DDG_POINTUSE || 0);
		if (pay < 0) pay = 0;
		var ta = document.getElementById('ds-total-amt'); if (ta) ta.innerText = pay.toLocaleString() + dsl('won');
		var pa = document.getElementById('ds-pay-amt'); if (pa) pa.innerText = pay.toLocaleString();
	}
	function ddggMaxBeforePoint() { return Math.max(0, (window.DDG_PAY || 0) + (window.DDG_EXTRA || 0) - (window.DDG_DISCOUNT || 0)); }
	window.ddggApplyPoint = function () {
		var inp = document.getElementById('ds-point');
		var p = inp ? (parseInt(inp.value, 10) || 0) : 0;
		if (p < 0) p = 0;
		if (p > (window.DDG_MYPOINT || 0)) { p = window.DDG_MYPOINT || 0; alert(dsl('js_point_exceed')); }
		var mx = ddggMaxBeforePoint();
		if (p > mx) p = mx;
		if (inp) inp.value = p;
		window.DDG_POINTUSE = p;
		var row = document.getElementById('ds-point-row'); if (row) row.style.display = p > 0 ? '' : 'none';
		var pa = document.getElementById('ds-point-amt'); if (pa) pa.innerText = '-' + p.toLocaleString() + dsl('won');
		ddggRecalcTotal();
	};
	window.ddggAllPoint = function () {
		var inp = document.getElementById('ds-point');
		var p = Math.min(window.DDG_MYPOINT || 0, ddggMaxBeforePoint());
		if (inp) inp.value = p;
		ddggApplyPoint();
	};

	/* 추가배송비: 우편번호 자동가산 + 해외/지역 선택 중 큰 값 적용 */
	window.DDG_EXTRA_ZIP = 0; window.DDG_EXTRA_REGION = 0; window.DDG_EXTRA_ZIP_LABEL = ''; window.DDG_EXTRA_REGION_LABEL = '';
	function ddggUpdateExtraRow() {
		var z = window.DDG_EXTRA_ZIP || 0, r = window.DDG_EXTRA_REGION || 0;
		var extra = Math.max(z, r);
		var region = (r >= z) ? (window.DDG_EXTRA_REGION_LABEL || '') : (window.DDG_EXTRA_ZIP_LABEL || '');
		window.DDG_EXTRA = extra;
		var row = document.getElementById('ds-extra-row');
		if (extra > 0) {
			if (row) row.style.display = '';
			var el = document.getElementById('ds-extra-amt'); if (el) el.innerText = '+' + extra.toLocaleString() + dsl('won');
			var lb = document.getElementById('ds-extra-label'); if (lb) lb.innerText = dsl('extra_shipping') + (region ? (' (' + region + ')') : '');
		} else if (row) { row.style.display = 'none'; }
		ddggRecalcTotal();
	}
	window.ddggApplyShipExtra = function () {
		var zEl = document.getElementById('ds-zipcode');
		var z = zEl ? zEl.value.replace(/[^0-9]/g, '') : '';
		var extra = 0, region = '';
		if (z && window.DDG_SHIPRULES && DDG_SHIPRULES.length) {
			var zz = ('00000' + z).slice(-5);
			for (var i = 0; i < DDG_SHIPRULES.length; i++) {
				var r = DDG_SHIPRULES[i], s = ('00000' + r.s).slice(-5), e = ('00000' + r.e).slice(-5);
				if (zz >= s && zz <= e) { extra = r.fee; region = r.region || ''; break; }
			}
		}
		window.DDG_EXTRA_ZIP = extra; window.DDG_EXTRA_ZIP_LABEL = region;
		ddggUpdateExtraRow();
	};
	window.ddggApplyShipRegion = function () {
		var sel = document.getElementById('ds-ship-region');
		var opt = sel && sel.options[sel.selectedIndex];
		var fee = opt ? (parseInt(opt.getAttribute('data-fee'), 10) || 0) : 0;
		window.DDG_EXTRA_REGION = fee;
		window.DDG_EXTRA_REGION_LABEL = (opt && sel.value) ? (opt.text.split(' (')[0]) : '';
		ddggUpdateExtraRow();
	};

	/* 쿠폰 적용 / 해제 */
	window.ddggApplyCoupon = function () {
		var inp = document.getElementById('ds-coupon');
		var code = inp ? inp.value.replace(/^\s+|\s+$/g, '') : '';
		if (!code) { alert(dsl('js_need_coupon')); return; }
		var it = (window.DDG_PAY || 0) - (window.DDG_SHIP || 0);
		exec_json('ddggshop.procDdggshopValidateCoupon', { coupon_code: code, item_total: it }, function (ret) {
			window.DDG_DISCOUNT = parseInt(ret.discount, 10) || 0;
			window.DDG_COUPON = ret.coupon_code || code;
			var row = document.getElementById('ds-discount-row'); if (row) row.style.display = '';
			var da = document.getElementById('ds-discount-amt'); if (da) da.innerText = '-' + window.DDG_DISCOUNT.toLocaleString() + dsl('won');
			var ap = document.getElementById('ds-coupon-applied'); if (ap) ap.innerText = '✓ ' + window.DDG_COUPON + dsl('js_applied_suffix');
			ddggRecalcTotal();
		});
	};
	window.ddggUseMyCoupon = function (code) {
		var inp = document.getElementById('ds-coupon');
		if (inp) inp.value = code;
		ddggApplyCoupon();
	};
	window.ddggRemoveCoupon = function () {
		window.DDG_DISCOUNT = 0; window.DDG_COUPON = '';
		var row = document.getElementById('ds-discount-row'); if (row) row.style.display = 'none';
		var ap = document.getElementById('ds-coupon-applied'); if (ap) ap.innerText = '';
		var inp = document.getElementById('ds-coupon'); if (inp) inp.value = '';
		ddggRecalcTotal();
	};

	/* 주문/결제 */
	window.ddggshopCheckout = function () {
		var pmEl = document.querySelector('input[name="pay_method"]:checked');
		if (!pmEl) { alert(dsl('js_need_paymethod')); return; }
		// 포인트 전액결제: 보유 적립금이 결제금액 전체를 충당해야 함
		if (pmEl.value === 'point') {
			var remain = Math.max(0, ddggMaxBeforePoint() - (window.DDG_POINTUSE || 0));
			if (remain > 0) {
				alert(dsl('js_point_short') + remain.toLocaleString() + dsl('won'));
				return;
			}
		}
		var data = {
			shop_mid: val('ds-shop-mid'),
			orderer_name: val('ds-orderer-name'),
			orderer_phone: val('ds-orderer-phone'),
			orderer_email: val('ds-orderer-email'),
			order_password: val('ds-order-pw'),
			receiver_name: val('ds-receiver-name'),
			receiver_phone: val('ds-receiver-phone'),
			zipcode: val('ds-zipcode'),
			addr1: val('ds-addr1'),
			addr2: val('ds-addr2'),
			delivery_memo: val('ds-delivery-memo'),
			memo: '',
			pay_method: pmEl.value,
			buy_item_srl: val('ds-buy-item'),
			buy_option_srl: val('ds-buy-option'),
			buy_qty: val('ds-buy-qty'),
			save_address: (document.getElementById('ds-save-addr') && document.getElementById('ds-save-addr').checked) ? 'Y' : 'N',
			coupon_code: window.DDG_COUPON || '',
			ship_region_srl: val('ds-ship-region'),
			used_point: window.DDG_POINTUSE || 0
		};
		function focusEl(id){ var e = document.getElementById(id); if (e) e.focus(); }
		if (!data.orderer_name) { alert(dsl('js_need_orderer_name')); focusEl('ds-orderer-name'); return; }
		if (!data.orderer_phone) { alert(dsl('js_need_orderer_phone')); focusEl('ds-orderer-phone'); return; }
		// 배송지 입력창이 있는 주문만 주소 검증 (기프트콘·콘텐츠 등 비배송 상품은 생략)
		var needsAddress = !!document.getElementById('ds-addr-box');
		if (needsAddress) {
			if (!data.receiver_name) { alert(dsl('js_need_receiver')); focusEl('ds-receiver-name'); return; }
			if (!data.receiver_phone) { alert(dsl('js_need_receiver_phone')); focusEl('ds-receiver-phone'); return; }
			if (!data.addr1) { alert(dsl('js_need_address')); return; }
		}
		var btn = document.getElementById('ds-pay-btn');
		if (btn) btn.disabled = true;

		exec_json('ddggshop.procDdggshopCreateOrder', data, function (ret) {
			if (parseInt(ret.requires_client, 10) === 1 && ret.pay_method === 'toss') {
				if (typeof TossPayments === 'undefined') {
					alert(dsl('js_pay_module_fail'));
					if (btn) btn.disabled = false;
					return;
				}
				try {
					var tp = TossPayments(window.DDGSHOP_TOSS_CLIENT);
					var payment = tp.payment({ customerKey: TossPayments.ANONYMOUS });
					payment.requestPayment({
						method: 'CARD',
						amount: { currency: 'KRW', value: parseInt(ret.amount, 10) },
						orderId: ret.order_id,
						orderName: ret.order_name,
						successUrl: ret.success_url,
						failUrl: ret.fail_url,
						customerName: ret.customer_name
					}).catch(function (e) {
						// 결제창 취소/실패 → 미결제 주문 정리
						exec_json('ddggshop.procDdggshopCancelOrder', { order_code: ret.order_id }, function () {});
						alert(dsl('js_pay_cancelled') + (e && e.message ? e.message : ''));
						if (btn) btn.disabled = false;
					});
				} catch (e) {
					alert(dsl('js_pay_window_error') + e.message);
					if (btn) btn.disabled = false;
				}
			} else {
				location.href = ret.result_url;
			}
		});
		// exec_json 실패 시 버튼 복구
		setTimeout(function () { if (btn && btn.disabled) { /* keep until redirect */ } }, 100);
	};

	/* 비회원 주문조회 */
	window.ddggshopLookup = function () {
		var code = val('ds-lookup-code'), pw = val('ds-lookup-pw');
		if (!code) { alert(dsl('js_need_order_no')); return; }
		exec_json('ddggshop.procDdggshopOrderLookup', { order_code: code, order_password: pw, shop_mid: val('ds-lookup-mid') }, function (ret) {
			location.href = ret.result_url;
		});
	};

	/* 상품 후기 */
	function reviewItemSrl() {
		var el = document.querySelector('.ds-reviews');
		return el ? el.getAttribute('data-item') : '';
	}
	window.ddggReviewSubmit = function () {
		var c = document.getElementById('rv-content');
		var content = c ? c.value.replace(/^\s+|\s+$/g, '') : '';
		if (!content) { alert(dsl('js_need_review')); return; }
		var r = document.getElementById('rv-rating');
		var fileInput = document.getElementById('rv-images');
		// 포토리뷰: 파일이 있으면 FormData(멀티파트)로 전송
		var fd = new FormData();
		fd.append('module', 'ddggshop');
		fd.append('act', 'procDdggshopWriteReview');
		fd.append('item_srl', reviewItemSrl());
		fd.append('rating', r ? r.value : 5);
		fd.append('content', content);
		if (fileInput && fileInput.files) {
			for (var i = 0; i < fileInput.files.length && i < 5; i++) fd.append('review_images[]', fileInput.files[i]);
		}
		// FormData(멀티파트) 전송은 exec_json('raw', ...) 사용 + CSRF 토큰을 필드로 전달
		fd.append('_rx_csrf_token', getCSRFToken());
		exec_json('raw', fd, function () { location.reload(); });
	};
	window.ddggReviewPhotoPreview = function (input) {
		var box = document.getElementById('rv-photo-names');
		var n = (input && input.files) ? input.files.length : 0;
		if (n > 5) { alert(dsl('js_photo_max')); }
		if (box) box.textContent = n ? (Math.min(n, 5) + dsl('js_photo_selected')) : '';
	};
	window.ddggReviewDelete = function (srl) {
		if (!confirm(dsl('js_review_del_confirm'))) return;
		exec_json('ddggshop.procDdggshopDeleteReview', { review_srl: srl }, function () { location.reload(); });
	};
	window.ddggImgZoom = function (src) {
		var ov = document.createElement('div');
		ov.className = 'ds-imgzoom';
		ov.innerHTML = '<img src="' + src + '" alt="" />';
		ov.addEventListener('click', function () { ov.parentNode && ov.parentNode.removeChild(ov); });
		document.body.appendChild(ov);
	};
	/* 교환/반품 신청 모달 */
	window.ddggClaim = function (code) {
		if (document.getElementById('ds-claim-ov')) return;
		var ov = document.createElement('div'); ov.id = 'ds-claim-ov'; ov.className = 'ds-claim-ov';
		ov.innerHTML = '<div class="ds-claim-box"><h3>' + dsl('js_claim_title') + '</h3>'
			+ '<div class="ds-claim-types"><label><input type="radio" name="dsctype" value="return" checked> ' + dsl('claim_return') + '</label><label><input type="radio" name="dsctype" value="exchange"> ' + dsl('claim_exchange') + '</label></div>'
			+ '<textarea id="ds-claim-reason" placeholder="' + dsl('js_need_reason') + '"></textarea>'
			+ '<div class="ds-claim-btns"><button type="button" class="ds-btn" data-x>' + dsl('cancel') + '</button><button type="button" class="ds-btn primary" data-go>' + dsl('js_apply_btn') + '</button></div></div>';
		(document.querySelector('.ddggshop') || document.body).appendChild(ov);
		var close = function () { if (ov.parentNode) ov.parentNode.removeChild(ov); };
		ov.addEventListener('click', function (e) { if (e.target === ov) close(); });
		ov.querySelector('[data-x]').addEventListener('click', close);
		ov.querySelector('[data-go]').addEventListener('click', function () {
			var t = ov.querySelector('input[name=dsctype]:checked').value;
			var reason = ov.querySelector('#ds-claim-reason').value.replace(/^\s+|\s+$/g, '');
			if (!reason) { alert(dsl('js_need_reason')); return; }
			exec_json('ddggshop.procDdggshopRequestClaim', { order_code: code, claim_type: t, reason: reason }, function () { alert(dsl('js_claim_submitted')); location.reload(); });
		});
	};

	/* 위시리스트(찜) 토글 */
	window.ddggToggleWishlist = function (item_srl, btn) {
		exec_json('ddggshop.procDdggshopToggleWishlist', { item_srl: item_srl }, function (ret) {
			var wished = ret && (ret.wished == 1 || ret.wished === '1' || ret.wished === true);
			if (btn && btn.id === 'ds-wish-btn') {
				if (wished) btn.classList.add('on'); else btn.classList.remove('on');
				var ico = btn.querySelector('.ds-wish-ico'); if (ico) ico.textContent = wished ? '♥' : '♡';
				var txt = btn.querySelector('.ds-wish-txt'); if (txt) txt.textContent = wished ? dsl('detail_wished') : dsl('detail_wish');
			} else if (btn && btn.className && btn.className.indexOf('ds-wish-remove') >= 0) {
				if (!wished) {
					var card = btn.closest ? btn.closest('.ds-card') : null;
					if (card && card.parentNode) card.parentNode.removeChild(card);
				}
			}
		}, function (err) {
			var msg = (err && err.message) ? err.message : '';
			if (msg.indexOf('로그인') >= 0 && window.ddggLoginOpen) { ddggLoginOpen(); }
			else if (msg) { alert(msg); }
		});
	};

	/* 간단 토스트 */
	function ddggToast(msg) {
		var t = document.createElement('div');
		t.className = 'ds-toast';
		t.textContent = msg;
		document.body.appendChild(t);
		requestAnimationFrame(function () { t.classList.add('show'); });
		setTimeout(function () {
			t.classList.remove('show');
			setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 250);
		}, 1800);
	}
	window.ddggToast = ddggToast;

	/* 상품 공유 — 모바일(지원 시): 네이티브 공유 시트 / PC: 링크 복사 후 토스트 */
	function ddggShareCopy(text) {
		var ok = false;
		var ta = document.createElement('textarea');
		ta.value = text; ta.style.position = 'fixed'; ta.style.top = '-1000px'; ta.style.opacity = '0';
		document.body.appendChild(ta); ta.focus(); ta.select();
		try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
		document.body.removeChild(ta);
		return ok;
	}
	function ddggIsMobile() {
		// 터치 우선 + 좁은 포인터(모바일/태블릿)일 때만 네이티브 공유 사용
		var coarse = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
		var ua = /Android|iPhone|iPad|iPod|Mobile|Opera Mini|IEMobile/i.test(navigator.userAgent || '');
		return !!(coarse || ua);
	}
	window.ddggShare = function (btn) {
		var title = (btn && btn.getAttribute('data-title')) || document.title || '';
		var url = location.href;
		// 모바일: 네이티브 공유 시트 / PC: 항상 링크 복사 후 토스트
		if (ddggIsMobile() && navigator.share) {
			navigator.share({ title: title, text: title, url: url }).catch(function () {});
			return;
		}
		var done = function () { ddggToast(dsl('js_url_copied')); };
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(url).then(done, function () { if (ddggShareCopy(url)) done(); });
		} else {
			ddggShareCopy(url); done();
		}
	};

	/* 최근 본 상품 리모콘 */
	window.ddggRecentToggle = function () {
		var el = document.getElementById('ds-recent-remote');
		if (!el) return;
		el.classList.toggle('collapsed');
		var col = el.classList.contains('collapsed');
		var btn = el.querySelector('.ds-rr-toggle');
		if (btn) btn.textContent = col ? '+' : '−';
		try { localStorage.setItem('ddgg_recent_collapsed', col ? '1' : '0'); } catch (e) {}
	};
	window.ddggScrollTop = function () {
		window.scrollTo({ top: 0, behavior: 'smooth' });
	};
	document.addEventListener('DOMContentLoaded', function () {
		try {
			if (localStorage.getItem('ddgg_recent_collapsed') === '1') {
				var el = document.getElementById('ds-recent-remote');
				if (el) { el.classList.add('collapsed'); var b = el.querySelector('.ds-rr-toggle'); if (b) b.textContent = '+'; }
			}
		} catch (e) {}
	});

	/* 별점 선택기 */
	document.addEventListener('DOMContentLoaded', function () {
		var box = document.getElementById('rv-stars');
		if (!box) return;
		var stars = box.querySelectorAll('span');
		function paint(v) { for (var i = 0; i < stars.length; i++) { if ((i + 1) <= v) stars[i].classList.add('on'); else stars[i].classList.remove('on'); } }
		for (var i = 0; i < stars.length; i++) {
			(function (s) {
				s.addEventListener('click', function () {
					var v = parseInt(s.getAttribute('data-v'), 10);
					var inp = document.getElementById('rv-rating');
					if (inp) inp.value = v;
					paint(v);
				});
			})(stars[i]);
		}
		paint(5);
	});

	/* 상품 문의 */
	function qnaItemSrl() {
		var el = document.querySelector('.ds-qna');
		return el ? el.getAttribute('data-item') : '';
	}
	window.ddggQnaSubmit = function () {
		var c = document.getElementById('qna-content');
		var content = c ? c.value.replace(/^\s+|\s+$/g, '') : '';
		if (!content) { alert(dsl('js_need_qna')); return; }
		var s = document.getElementById('qna-secret');
		exec_json('ddggshop.procDdggshopWriteQna', { item_srl: qnaItemSrl(), content: content, is_secret: (s && s.checked) ? 'Y' : 'N' }, function () {
			location.reload();
		});
	};
	window.ddggQnaDelete = function (srl) {
		if (!confirm(dsl('js_qna_del_confirm'))) return;
		exec_json('ddggshop.procDdggshopDeleteQna', { qna_srl: srl }, function () { location.reload(); });
	};

	/* 주문 취소 (배송준비 전) */
	window.ddggCancelOrder = function (code) {
		if (!confirm(dsl('js_cancel_order_confirm'))) return;
		exec_json('ddggshop.procDdggshopCancelMyOrder', { order_code: code }, function () { location.reload(); });
	};

	/* 상세 탭 전환 */
	window.ddggShowTab = function (key) {
		var nav = document.querySelector('.ds-tabnav');
		if (!nav) return;
		var tab = nav.querySelector('.ds-tab[data-tab="' + key + '"]');
		if (tab) tab.click();
		var tabs = document.querySelector('.ds-tabs');
		if (tabs && tabs.scrollIntoView) tabs.scrollIntoView({ behavior: 'smooth' });
	};
	document.addEventListener('DOMContentLoaded', function () {
		var navs = document.querySelectorAll('.ds-tabnav');
		for (var n = 0; n < navs.length; n++) {
			(function (nav) {
				var wrap = nav.parentNode;
				var tabs = nav.querySelectorAll('.ds-tab');
				for (var i = 0; i < tabs.length; i++) {
					tabs[i].addEventListener('click', function () {
						var key = this.getAttribute('data-tab');
						var bs = nav.querySelectorAll('.ds-tab');
						for (var j = 0; j < bs.length; j++) bs[j].classList.remove('on');
						this.classList.add('on');
						var ps = wrap.querySelectorAll('.ds-tabpane');
						for (var k = 0; k < ps.length; k++) {
							if (ps[k].getAttribute('data-pane') === key) ps[k].classList.add('on');
							else ps[k].classList.remove('on');
						}
					});
				}
			})(navs[n]);
		}
	});

	/* 모바일 검색 오버레이 */
	window.ddggSearchOpen = function () {
		var ov = document.getElementById('ds-search-overlay');
		if (!ov) return;
		ov.classList.add('on');
		var inp = document.getElementById('ds-msearch-input');
		if (inp) setTimeout(function () { inp.focus(); }, 50);
	};
	window.ddggSearchClose = function () {
		var ov = document.getElementById('ds-search-overlay');
		if (ov) ov.classList.remove('on');
	};

	/* 모바일 햄버거 드로어 */
	window.ddggDrawerOpen = function () {
		var d = document.getElementById('ds-drawer');
		if (d) { d.classList.add('on'); ddggOverlayLock(true); }
	};
	window.ddggDrawerClose = function () {
		var d = document.getElementById('ds-drawer');
		if (d && d.classList.contains('on')) { d.classList.remove('on'); ddggOverlayLock(false); }
	};

	/* 모바일 상품필터 드로어 */
	/* 가격대 직접 입력 적용 */
	window.ddggPriceApply = function (form) {
		var minEl = form.price_min, maxEl = form.price_max;
		var min = minEl ? minEl.value.replace(/[^0-9]/g, '') : '';
		var max = maxEl ? maxEl.value.replace(/[^0-9]/g, '') : '';
		if (min && max && parseInt(min, 10) > parseInt(max, 10)) { var t = min; min = max; max = t; }
		var base = form.getAttribute('data-base') || location.href;
		var qs = [];
		if (min) qs.push('price_min=' + min);
		if (max) qs.push('price_max=' + max);
		var sep = base.indexOf('?') >= 0 ? '&' : '?';
		location.href = base + (qs.length ? (sep + qs.join('&')) : '');
		return false;
	};

	window.ddggFilterOpen = function () {
		var s = document.getElementById('ds-fside'), b = document.getElementById('ds-filter-backdrop');
		if (s) s.classList.add('on');
		if (b) b.classList.add('on');
		ddggOverlayLock(true);
	};
	window.ddggFilterClose = function () {
		var s = document.getElementById('ds-fside'), b = document.getElementById('ds-filter-backdrop');
		if (!s || !s.classList.contains('on')) return;
		s.classList.remove('on');
		if (b) b.classList.remove('on');
		ddggOverlayLock(false);
	};

	/* 상품 옵션 (조합형) 선택 */
	var DDG_CHOSEN = [];
	function ddggOptFind(label) {
		if (!window.DDG_OPTIONS) return null;
		for (var i = 0; i < window.DDG_OPTIONS.length; i++) { if (window.DDG_OPTIONS[i].label === label) return window.DDG_OPTIONS[i]; }
		return null;
	}
	/* 마지막 셀렉트에 조합별 추가금액·품절 동적 표기 (앞 그룹 선택 기준) */
	window.ddggOptDecorate = function () {
		var sels = document.querySelectorAll('.ds-optselect');
		if (!sels.length) return;
		var last = sels[sels.length - 1];
		var prefix = [], ready = true;
		for (var i = 0; i < sels.length - 1; i++) { if (!sels[i].value) { ready = false; break; } prefix.push(sels[i].value); }
		for (var o = 0; o < last.options.length; o++) {
			var opt = last.options[o], v = opt.getAttribute('data-v');
			if (v === null) continue; // placeholder
			var t = v, so = false;
			if (ready) {
				var combo = ddggOptFind(prefix.concat([v]).join(' / '));
				if (combo) {
					if (combo.add > 0) t += ' (+' + combo.add.toLocaleString() + dsl('won') + ')';
					so = (window.DDG_USESTOCK === 'Y' && combo.stock <= 0);
					if (so) t += ' (' + dsl('soldout') + ')';
				}
			}
			opt.textContent = t; opt.disabled = so;
		}
	};
	window.ddggOptChange = function () { ddggOptPick(); ddggOptDecorate(); };
	document.addEventListener('DOMContentLoaded', function () { if (document.querySelector('.ds-optselect')) ddggOptDecorate(); });
	window.ddggOptPick = function () {
		var sels = document.querySelectorAll('.ds-optselect'), parts = [], all = true;
		for (var i = 0; i < sels.length; i++) { if (!sels[i].value) { all = false; break; } parts.push(sels[i].value); }
		if (!all) return;
		var label = parts.join(' / ');
		for (var j = 0; j < sels.length; j++) sels[j].value = '';
		var opt = ddggOptFind(label);
		if (!opt) { alert(dsl('js_opt_invalid')); return; }
		if (window.DDG_USESTOCK === 'Y' && opt.stock <= 0) { alert(dsl('js_opt_soldout')); return; }
		for (var k = 0; k < DDG_CHOSEN.length; k++) { if (DDG_CHOSEN[k].srl === opt.srl) { DDG_CHOSEN[k].qty++; ddggOptRender(); return; } }
		DDG_CHOSEN.push({ srl: opt.srl, label: opt.label, add: opt.add, qty: 1, stock: opt.stock });
		ddggOptRender();
	};
	function ddggOptRender() {
		var box = document.getElementById('ds-optchosen'); if (!box) return; box.innerHTML = '';
		var total = 0, base = window.DDG_BASEPRICE || 0;
		for (var i = 0; i < DDG_CHOSEN.length; i++) {
			var c = DDG_CHOSEN[i], unit = ddggDisc(base + c.add), sub = unit * c.qty; total += sub;
			var div = document.createElement('div'); div.className = 'ds-optline';
			div.innerHTML = '<div class="ds-optline-name">' + c.label + (c.add > 0 ? ' <span class="ds-optline-add">(+' + c.add.toLocaleString() + dsl('won') + ')</span>' : '') + '</div>'
				+ '<div class="ds-optline-qty"><button type="button" onclick="ddggOptQty(' + i + ',-1)">−</button><span>' + c.qty + '</span><button type="button" onclick="ddggOptQty(' + i + ',1)">+</button></div>'
				+ '<div class="ds-optline-price">' + sub.toLocaleString() + dsl('won') + '</div>'
				+ '<button type="button" class="ds-optline-x" onclick="ddggOptRemove(' + i + ')" aria-label="' + dsl('delete') + '">×</button>';
			box.appendChild(div);
		}
		var sum = document.getElementById('ds-optsum');
		if (sum) sum.innerHTML = DDG_CHOSEN.length ? ('<span>' + dsl('js_opt_total') + '</span><b>' + total.toLocaleString() + dsl('won') + '</b>') : '';
	}
	window.ddggOptQty = function (i, d) {
		if (!DDG_CHOSEN[i]) return;
		DDG_CHOSEN[i].qty += d;
		if (DDG_CHOSEN[i].qty < 1) DDG_CHOSEN[i].qty = 1;
		if (window.DDG_USESTOCK === 'Y' && DDG_CHOSEN[i].qty > DDG_CHOSEN[i].stock) { DDG_CHOSEN[i].qty = DDG_CHOSEN[i].stock; alert(dsl('js_out_of_stock')); }
		ddggOptRender();
	};
	window.ddggOptRemove = function (i) { DDG_CHOSEN.splice(i, 1); ddggOptRender(); };
	function ddggOptAddSeq(item_srl, idx, done) {
		if (idx >= DDG_CHOSEN.length) { done(); return; }
		var c = DDG_CHOSEN[idx];
		exec_json('ddggshop.procDdggshopAddCart', { item_srl: item_srl, option_srl: c.srl, qty: c.qty }, function () { ddggOptAddSeq(item_srl, idx + 1, done); });
	}
	window.ddggOptAddCart = function (item_srl) {
		if (!DDG_CHOSEN.length) { alert(dsl('js_select_option')); return; }
		ddggOptAddSeq(item_srl, 0, function () {
			if (confirm(dsl('js_added_go'))) location.href = shopUrl('dispDdggshopCart');
			else { DDG_CHOSEN = []; ddggOptRender(); }
		});
	};
	window.ddggOptBuyNow = function (item_srl) {
		if (!DDG_CHOSEN.length) { alert(dsl('js_select_option')); return; }
		if (DDG_CHOSEN.length > 1) { alert(dsl('js_buynow_single_option')); return; }
		var c = DDG_CHOSEN[0];
		location.href = shopUrl('dispDdggshopOrder', { buy_item_srl: item_srl, buy_option_srl: c.srl, buy_qty: c.qty });
	};

	/* 로그인 모달 */
	window.ddggLoginOpen = function () {
		var m = document.getElementById('ds-login-modal');
		if (!m) { location.href = shopBase() + '?act=dispMemberLoginForm'; return; }
		m.classList.add('on');
		ddggOverlayLock(true);
		var i = document.getElementById('ds-login-id');
		if (i) setTimeout(function () { i.focus(); }, 50);
	};
	window.ddggLoginClose = function () {
		var m = document.getElementById('ds-login-modal');
		if (m && m.classList.contains('on')) { m.classList.remove('on'); ddggOverlayLock(false); }
	};
	window.ddggLoginSubmit = function () {
		var id = document.getElementById('ds-login-id').value;
		var pw = document.getElementById('ds-login-pw').value;
		if (!id || !pw) { alert(dsl('js_need_login')); return false; }
		var keep = document.getElementById('ds-login-keep').checked ? 'Y' : 'N';
		exec_json('member.procMemberLogin', { user_id: id, password: pw, keep_signed: keep }, function () {
			location.reload();
		});
		return false;
	};
})();

/* ===== 메인 배너 슬라이더 (무한 루프) ===== */
(function(){
  function initSlider(){
    var root = document.getElementById('dsSlider');
    if(!root) return;
    var track = root.querySelector('.ds-slider-track');
    var slides = Array.prototype.slice.call(root.querySelectorAll('.ds-slide'));
    var n = slides.length;
    if(!track || n <= 1) return;
    var dotsWrap = root.querySelector('.ds-slider-dots');
    var TRANS = 'transform .45s ease';

    /* 앞뒤 클론으로 양방향 무한 루프: [last']·[0..n-1]·[first'] */
    var firstClone = slides[0].cloneNode(true);
    var lastClone = slides[n-1].cloneNode(true);
    firstClone.classList.add('clone'); lastClone.classList.add('clone');
    track.appendChild(firstClone);
    track.insertBefore(lastClone, slides[0]);

    var total = n + 2, idx = 1, timer = null, animating = false;
    function setX(anim){ track.style.transition = anim ? TRANS : 'none'; track.style.transform = 'translateX(-' + (idx*100) + '%)'; }
    function realIndex(){ return (idx - 1 + n) % n; }

    var dots = [];
    if(dotsWrap){
      for(var i=0;i<n;i++){
        var d = document.createElement('button');
        d.type = 'button';
        (function(k){ d.addEventListener('click', function(){ if(animating || (k+1)===idx) return; animating=true; idx=k+1; setX(true); syncDots(); restart(); }); })(i);
        dotsWrap.appendChild(d);
        dots.push(d);
      }
    }
    function syncDots(){ var r=realIndex(); for(var i=0;i<dots.length;i++) dots[i].className = (i===r) ? 'on' : ''; }
    function next(){ if(animating) return; animating=true; idx++; setX(true); syncDots(); }
    function prev(){ if(animating) return; animating=true; idx--; setX(true); syncDots(); }

    track.addEventListener('transitionend', function(){
      animating = false;
      if(idx >= total-1){ idx = 1; setX(false); }   /* 첫 클론 → 실제 첫 장으로 순간이동 */
      else if(idx <= 0){ idx = n; setX(false); }     /* 끝 클론 → 실제 끝 장으로 순간이동 */
      syncDots();
    });

    function start(){ timer = setInterval(next, 5000); }
    function stop(){ if(timer){ clearInterval(timer); timer=null; } }
    function restart(){ stop(); start(); }
    var pb = root.querySelector('.ds-slider-nav.prev'); if(pb) pb.addEventListener('click', function(e){ e.preventDefault(); prev(); restart(); });
    var nb = root.querySelector('.ds-slider-nav.next'); if(nb) nb.addEventListener('click', function(e){ e.preventDefault(); next(); restart(); });
    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', start);
    /* 터치 스와이프 */
    var sx=0;
    root.addEventListener('touchstart', function(e){ sx = e.touches[0].clientX; stop(); }, {passive:true});
    root.addEventListener('touchend', function(e){ var dx = e.changedTouches[0].clientX - sx; if(Math.abs(dx)>40){ dx<0?next():prev(); } start(); }, {passive:true});

    setX(false); syncDots(); start();
  }
  if(document.readyState !== 'loading') initSlider();
  else document.addEventListener('DOMContentLoaded', initSlider);
})();
