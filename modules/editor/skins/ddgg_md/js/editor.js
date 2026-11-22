/**
 * ddgg_md (TipTap, Notion-style) — bootstrap.
 *
 * Notion-like editing: markdown input shortcuts (built into StarterKit),
 * a slash (/) command menu, and a selection bubble menu. No persistent toolbar.
 * Wires into the same Rhymix editor contract as ddgg_editor.
 */
(function($) {
	"use strict";

	if (!window.DdggTiptap || !window.DdggTiptap.Editor) {
		if (window.console) console.error('[ddgg_md] TipTap bundle not loaded.');
		return;
	}
	var T = window.DdggTiptap;

	// --- Fidelity helpers & custom extensions (shared design with ddgg_editor) ---
	function styleAttr(cssProp, domProp) {
		return {
			default: null,
			parseHTML: function(el) { return el.style[domProp] || null; },
			renderHTML: function(attrs) { var v = attrs[domProp]; return v ? { style: cssProp + ':' + v } : {}; }
		};
	}
	function passAttr(name) {
		return {
			default: null,
			parseHTML: function(el) { return el.getAttribute(name); },
			renderHTML: function(attrs) { var o = {}; if (attrs[name] != null) o[name] = attrs[name]; return o; }
		};
	}
	var RxTextStyle = T.TextStyle.extend({
		addAttributes: function() {
			var parent = this.parent ? (this.parent() || {}) : {};
			return Object.assign({}, parent, {
				fontSize: styleAttr('font-size', 'fontSize'),
				fontFamily: styleAttr('font-family', 'fontFamily'),
				backgroundColor: styleAttr('background-color', 'backgroundColor')
			});
		}
	});
	var RxImage = T.Image.extend({
		addAttributes: function() {
			var parent = this.parent ? (this.parent() || {}) : {};
			return Object.assign({}, parent, {
				width: passAttr('width'), height: passAttr('height'), style: passAttr('style'), class: passAttr('class')
			});
		}
	});
	var RxIframe = T.Node.create({
		name: 'rxIframe', group: 'block', atom: true, selectable: true, draggable: true,
		addAttributes: function() {
			return { src: { default: null }, width: { default: null }, height: { default: null },
				frameborder: { default: '0' }, allow: { default: null }, allowfullscreen: { default: 'true' },
				loading: { default: null }, style: { default: null } };
		},
		parseHTML: function() { return [{ tag: 'iframe[src]' }]; },
		renderHTML: function(props) { return ['iframe', T.mergeAttributes(props.HTMLAttributes)]; }
	});
	var RxLinkCard = T.Node.create({
		name: 'rxLinkCard', group: 'block', atom: true, selectable: true, draggable: true,
		addAttributes: function() {
			return { href: { default: null }, image: { default: null }, title: { default: null }, desc: { default: null }, site: { default: null } };
		},
		parseHTML: function() {
			return [{ tag: 'a.og-card', priority: 60, getAttrs: function(el) {
				function txt(sel) { var n = el.querySelector(sel); return n ? n.textContent : null; }
				var img = el.querySelector('img.og-card-img');
				return {
					href: el.getAttribute('href'),
					image: el.getAttribute('data-image') || (img ? img.getAttribute('src') : null),
					title: el.getAttribute('data-title') || txt('.og-card-title'),
					desc: el.getAttribute('data-desc') || txt('.og-card-desc'),
					site: el.getAttribute('data-site') || txt('.og-card-site')
				};
			} }];
		},
		renderHTML: function(props) {
			var a = props.HTMLAttributes || {};
			var attrs = {
				class: 'og-card', href: a.href || '#', target: '_blank', rel: 'noopener noreferrer',
				'data-image': a.image || '', 'data-title': a.title || '', 'data-desc': a.desc || '', 'data-site': a.site || '',
				style: 'display:flex;text-decoration:none;color:inherit;border:1px solid #e1e6ec;border-radius:10px;overflow:hidden;background:#fff;max-width:520px;margin:10px 0;'
			};
			var imgNode = a.image
				? ['img', { class: 'og-card-img', src: a.image, alt: '', style: 'flex:0 0 140px;width:140px;height:112px;object-fit:cover;background:#eef1f4;' }]
				: ['span', { class: 'og-card-img og-card-img-empty', style: 'flex:0 0 6px;background:#1769b3;align-self:stretch;' }];
			return ['a', attrs, imgNode,
				['span', { class: 'og-card-body', style: 'display:flex;flex-direction:column;gap:4px;padding:12px 14px;min-width:0;' },
					['span', { class: 'og-card-title', style: 'font-weight:600;font-size:15px;color:#1b2430;' }, a.title || a.href || ''],
					['span', { class: 'og-card-desc', style: 'font-size:13px;color:#5a6573;' }, a.desc || ''],
					['span', { class: 'og-card-site', style: 'font-size:12px;color:#8a93a0;' }, a.site || '']
				]
			];
		}
	});

	// --- Embeds / link card -------------------------------------------------
	function videoEmbed(v) {
		if (/<iframe/i.test(v)) return v;
		var yt = v.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([\w-]{11})/);
		if (yt) return '<iframe src="https://www.youtube.com/embed/' + yt[1] + '" width="640" height="360" style="max-width:100%;height:auto;border:0;" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
		var vm = v.match(/vimeo\.com\/(\d+)/);
		if (vm) return '<iframe src="https://player.vimeo.com/video/' + vm[1] + '" width="640" height="360" style="max-width:100%;height:auto;border:0;" frameborder="0" allowfullscreen></iframe>';
		return null;
	}
	function hostOf(u) { try { return new URL(u).hostname.replace(/^www\./, ''); } catch (e) { return u; } }

	// After inserting an atom/media block, make sure there's a text line after it
	// (and move the cursor there) so the next keystroke doesn't overwrite it.
	function ensureBreak(editor) {
		try {
			var size = editor.state.doc.content.size;
			var sel = editor.state.selection;
			var $to = editor.state.doc.resolve(Math.min(sel.to, size));
			if (sel.node || !$to.parent || !$to.parent.isTextblock) {
				var pos = sel.to;
				editor.chain().insertContentAt(pos, { type: 'paragraph' }).setTextSelection(pos + 1).focus().run();
			}
		} catch (e) {}
	}
	function insertMedia(editor, content) {
		editor.chain().focus().insertContent(content).run();
		ensureBreak(editor);
	}
	function insertLinkCard(editor, url) {
		var base = window.request_uri || '/';
		function insert(d) {
			insertMedia(editor, { type: 'rxLinkCard', attrs: {
				href: (d && d.url) || url, title: (d && d.title) || url, desc: (d && d.description) || '',
				site: (d && d.site_name) || hostOf(url), image: (d && d.image) || null
			} });
		}
		$.ajax({ url: base, method: 'GET', data: { act: 'procDdggOgPreview', url: url }, dataType: 'json', timeout: 9000 })
			.done(function(res) { insert(res && res.success ? res.data : null); })
			.fail(function() { insert(null); });
	}

	// --- Slash command icons ------------------------------------------------
	function ic(p) { return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' + p + '</svg>'; }
	var SI = {
		text: ic('<line x1="5" y1="7" x2="19" y2="7"/><line x1="5" y1="12" x2="19" y2="12"/><line x1="5" y1="17" x2="13" y2="17"/>'),
		h1: ic('<path d="M4 6v12M12 6v12M4 12h8"/><path d="M17 9l2-1v10" stroke-width="1.5"/>'),
		h2: ic('<path d="M4 6v12M12 6v12M4 12h8"/><path d="M16 10a2 2 0 1 1 4 0c0 1.5-4 3-4 6h4" stroke-width="1.4"/>'),
		h3: ic('<path d="M4 6v12M12 6v12M4 12h8"/><path d="M16 9a2 2 0 1 1 3.2 1.6A2 2 0 1 1 16 15" stroke-width="1.4"/>'),
		bullet: ic('<circle cx="5" cy="7" r="1"/><circle cx="5" cy="12" r="1"/><circle cx="5" cy="17" r="1"/><line x1="9" y1="7" x2="19" y2="7"/><line x1="9" y1="12" x2="19" y2="12"/><line x1="9" y1="17" x2="19" y2="17"/>'),
		ordered: ic('<text x="2.5" y="9" font-size="7" stroke="none" fill="currentColor">1</text><text x="2.5" y="15" font-size="7" stroke="none" fill="currentColor">2</text><text x="2.5" y="21" font-size="7" stroke="none" fill="currentColor">3</text><line x1="9" y1="7" x2="19" y2="7"/><line x1="9" y1="13" x2="19" y2="13"/><line x1="9" y1="19" x2="19" y2="19"/>'),
		task: ic('<rect x="3" y="4" width="7" height="7" rx="1.5"/><path d="M4.5 7.5l1.5 1.5 2.5-3"/><line x1="13" y1="7.5" x2="20" y2="7.5"/><line x1="13" y1="16.5" x2="20" y2="16.5"/><rect x="3" y="13" width="7" height="7" rx="1.5"/>'),
		quote: ic('<path d="M7 7H5a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h2v-3M16 7h-2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h2v-3"/>'),
		code: ic('<path d="M8 8l-4 4 4 4"/><path d="M16 8l4 4-4 4"/><line x1="13.5" y1="5.5" x2="10.5" y2="18.5"/>'),
		hr: ic('<line x1="4" y1="12" x2="20" y2="12"/>'),
		table: ic('<rect x="3" y="4" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="9" y1="4" x2="9" y2="20"/>'),
		image: ic('<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="M21 16l-5-5L4 20"/>'),
		youtube: ic('<rect x="2" y="5" width="20" height="14" rx="4.5"/><path d="M10 8.8l5.2 3.2L10 15.2z" fill="currentColor" stroke="none"/>'),
		link: ic('<path d="M10 13a5 5 0 0 0 7.07 0l2.5-2.5a5 5 0 0 0-7.07-7.07l-1.2 1.2"/><path d="M14 11a5 5 0 0 0-7.07 0L4.43 13.5a5 5 0 0 0 7.07 7.07l1.2-1.2"/>')
	};

	function slashItems(actions) {
		return [
			{ title: '텍스트', desc: '일반 본문', icon: SI.text, keys: 'text 본문 텍스트 p', command: function(p) { p.editor.chain().focus().deleteRange(p.range).setParagraph().run(); } },
			{ title: '제목 1', desc: '큰 제목', icon: SI.h1, keys: 'h1 heading 제목1', command: function(p) { p.editor.chain().focus().deleteRange(p.range).toggleHeading({ level: 1 }).run(); } },
			{ title: '제목 2', desc: '중간 제목', icon: SI.h2, keys: 'h2 heading 제목2', command: function(p) { p.editor.chain().focus().deleteRange(p.range).toggleHeading({ level: 2 }).run(); } },
			{ title: '제목 3', desc: '작은 제목', icon: SI.h3, keys: 'h3 heading 제목3', command: function(p) { p.editor.chain().focus().deleteRange(p.range).toggleHeading({ level: 3 }).run(); } },
			{ title: '글머리 목록', desc: '• 불릿 리스트', icon: SI.bullet, keys: 'bullet ul list 목록 불릿', command: function(p) { p.editor.chain().focus().deleteRange(p.range).toggleBulletList().run(); } },
			{ title: '번호 목록', desc: '1. 번호 리스트', icon: SI.ordered, keys: 'ordered ol number 번호', command: function(p) { p.editor.chain().focus().deleteRange(p.range).toggleOrderedList().run(); } },
			{ title: '할 일 목록', desc: '체크박스', icon: SI.task, keys: 'task todo check 할일 체크', command: function(p) { p.editor.chain().focus().deleteRange(p.range).toggleTaskList().run(); } },
			{ title: '인용', desc: '인용구 블록', icon: SI.quote, keys: 'quote blockquote 인용', command: function(p) { p.editor.chain().focus().deleteRange(p.range).toggleBlockquote().run(); } },
			{ title: '코드', desc: '코드 블록', icon: SI.code, keys: 'code 코드', command: function(p) { p.editor.chain().focus().deleteRange(p.range).toggleCodeBlock().run(); } },
			{ title: '구분선', desc: '가로 구분선', icon: SI.hr, keys: 'hr divider 구분선', command: function(p) { p.editor.chain().focus().deleteRange(p.range).setHorizontalRule().run(); } },
			{ title: '표', desc: '3×3 표', icon: SI.table, keys: 'table 표', command: function(p) { p.editor.chain().focus().deleteRange(p.range).insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(); } },
			{ title: '이미지', desc: '이미지 업로드/삽입', icon: SI.image, keys: 'image 이미지 사진', command: function(p) { p.editor.chain().focus().deleteRange(p.range).run(); actions.uploadImage(); } },
			{ title: '유튜브', desc: '동영상 임베드', icon: SI.youtube, keys: 'youtube video 유튜브 동영상', command: function(p) {
				p.editor.chain().focus().deleteRange(p.range).run();
				var url = window.prompt('유튜브 링크를 붙여넣으세요'); if (url === null) return;
				var h = videoEmbed(url.trim()); if (!h) { alert('유효한 유튜브 링크가 아닙니다.'); return; }
				insertMedia(p.editor, h);
			} },
			{ title: '링크 카드', desc: 'OG 미리보기 카드', icon: SI.link, keys: 'link 링크 카드 og bookmark', command: function(p) {
				p.editor.chain().focus().deleteRange(p.range).run();
				var url = window.prompt('링크 URL을 입력하세요'); if (url === null) return;
				url = url.trim(); if (url === '') return; if (!/^https?:\/\//i.test(url)) url = 'https://' + url;
				insertLinkCard(p.editor, url);
			} }
		];
	}

	// --- Menu positioning (mobile-aware) ------------------------------------
	function isMobileView() { return window.matchMedia && window.matchMedia('(max-width: 600px)').matches; }
	function positionMenuPopup(popup, rect) {
		var pad = 6;
		if (isMobileView()) {
			// Pin above the on-screen keyboard, full width.
			popup.classList.add('ddgg-menu-mobile');
			var vv = window.visualViewport;
			var kb = vv ? Math.max(0, window.innerHeight - vv.height - vv.offsetTop) : 0;
			popup.style.left = '8px';
			popup.style.right = '8px';
			popup.style.width = 'auto';
			popup.style.top = 'auto';
			popup.style.bottom = (kb + 8) + 'px';
			return;
		}
		popup.classList.remove('ddgg-menu-mobile');
		popup.style.bottom = 'auto'; popup.style.right = 'auto'; popup.style.width = '';
		if (!rect) return;
		var ph = popup.offsetHeight, pw = popup.offsetWidth;
		var top = rect.bottom + pad, left = rect.left;
		if (top + ph > window.innerHeight) top = Math.max(pad, rect.top - ph - pad);
		if (left + pw > window.innerWidth) left = Math.max(pad, window.innerWidth - pw - pad);
		popup.style.top = top + 'px';
		popup.style.left = left + 'px';
	}

	// --- Slash command renderer (no external popup lib) ---------------------
	function makeSlashRenderer() {
		var popup, listEl, props, idx = 0;
		function buildList() {
			listEl.innerHTML = '';
			if (!props.items.length) {
				var empty = document.createElement('div'); empty.className = 'ddgg-slash-empty'; empty.textContent = '결과 없음';
				listEl.appendChild(empty); return;
			}
			props.items.forEach(function(it, i) {
				var row = document.createElement('button');
				row.type = 'button';
				row.className = 'ddgg-slash-item' + (i === idx ? ' is-active' : '');
				row.innerHTML = '<span class="ddgg-slash-ic">' + it.icon + '</span><span class="ddgg-slash-tx"><span class="ddgg-slash-t">' + it.title + '</span><span class="ddgg-slash-d">' + it.desc + '</span></span>';
				row.addEventListener('mousedown', function(e) { e.preventDefault(); });
				row.addEventListener('click', function(e) { e.preventDefault(); choose(i); });
				row.addEventListener('mousemove', function() { if (idx !== i) { idx = i; highlight(); } });
				listEl.appendChild(row);
			});
		}
		function highlight() {
			Array.prototype.forEach.call(listEl.children, function(c, i) { c.classList && c.classList.toggle('is-active', i === idx); });
			var active = listEl.children[idx];
			if (active && active.scrollIntoView) active.scrollIntoView({ block: 'nearest' });
		}
		function choose(i) { var it = props.items[i]; if (it) props.command(it); }
		function position(rect) { positionMenuPopup(popup, rect); }
		function onViewport() { if (popup && props && props.clientRect) positionMenuPopup(popup, props.clientRect()); }
		function destroy() {
			if (window.visualViewport) {
				window.visualViewport.removeEventListener('resize', onViewport);
				window.visualViewport.removeEventListener('scroll', onViewport);
			}
			if (popup && popup.parentNode) popup.parentNode.removeChild(popup);
			popup = null;
		}
		return {
			onStart: function(p) {
				props = p; idx = 0;
				popup = document.createElement('div'); popup.className = 'ddgg-slash';
				listEl = document.createElement('div'); listEl.className = 'ddgg-slash-list';
				popup.appendChild(listEl);
				document.body.appendChild(popup);
				buildList(); position(p.clientRect && p.clientRect());
				if (window.visualViewport) {
					window.visualViewport.addEventListener('resize', onViewport);
					window.visualViewport.addEventListener('scroll', onViewport);
				}
			},
			onUpdate: function(p) { props = p; idx = 0; buildList(); position(p.clientRect && p.clientRect()); },
			onKeyDown: function(p) {
				var e = p.event, n = props.items.length;
				if (e.key === 'ArrowDown') { if (n) { idx = (idx + 1) % n; highlight(); } return true; }
				if (e.key === 'ArrowUp') { if (n) { idx = (idx - 1 + n) % n; highlight(); } return true; }
				if (e.key === 'Enter') { if (n) choose(idx); return true; }
				if (e.key === 'Escape') { destroy(); return true; }
				return false;
			},
			onExit: function() { destroy(); }
		};
	}

	// Legacy editor-component placeholder — preserved verbatim (for existing posts).
	var RxComponent = T.Node.create({
		name: 'rxComponent', group: 'block', atom: true, selectable: true, draggable: true,
		addAttributes: function() { return { html: { default: '' } }; },
		parseHTML: function() {
			return [
				{ tag: 'img[editor_component]', priority: 70, getAttrs: function(el) { return { html: el.outerHTML }; } },
				{ tag: 'div[editor_component]', priority: 70, getAttrs: function(el) { return { html: el.outerHTML }; } }
			];
		},
		renderHTML: function(props) {
			var tpl = document.createElement('template');
			tpl.innerHTML = (props.node && props.node.attrs.html) || '';
			return tpl.content.firstChild || document.createElement('span');
		}
	});

	function buildExtensions(config, actions) {
		var SlashCommand = T.Extension.create({
			name: 'slashCommand',
			addProseMirrorPlugins: function() {
				return [T.Suggestion({
					editor: this.editor,
					char: '/',
					startOfLine: false,
					allowSpaces: false,
					command: function(args) { args.props.command({ editor: args.editor, range: args.range }); },
					items: function(p) {
						var q = (p.query || '').toLowerCase();
						var all = slashItems(actions);
						if (!q) return all;
						return all.filter(function(it) { return (it.title + ' ' + it.keys).toLowerCase().indexOf(q) !== -1; });
					},
					render: makeSlashRenderer
				})];
			}
		});

		var ext = [
			T.StarterKit.configure({ heading: { levels: [1, 2, 3, 4] } }),
			RxTextStyle,
			T.Color,
			T.TextAlign.configure({ types: ['heading', 'paragraph', 'image'] }),
			RxImage.configure({ inline: false, allowBase64: true }),
			RxIframe,
			RxLinkCard,
			RxComponent,
			T.Table.configure({ resizable: true }),
			T.TableRow, T.TableHeader, T.TableCell,
			T.TaskList,
			T.TaskItem.configure({ nested: true }),
			SlashCommand
		];
		if (T.GlobalDragHandle) {
			ext.push(T.GlobalDragHandle.configure({ dragHandleWidth: 54, scrollTreshold: 100 }));
		}
		if (T.Placeholder) {
			ext.push(T.Placeholder.configure({
				includeChildren: true,
				placeholder: function(o) {
					if (o.node && o.node.type && o.node.type.name === 'heading') return '제목';
					return config.placeholder || "'/' 명령어";
				}
			}));
		}
		return ext;
	}

	// --- Bubble menu (manual, no deps) --------------------------------------
	function setupBubble(editor) {
		var bar = document.createElement('div');
		bar.className = 'ddgg-bubble';
		bar.style.display = 'none';
		document.body.appendChild(bar);
		var updaters = [];
		function btn(html, title, onClick, isActive) {
			var b = document.createElement('button');
			b.type = 'button'; b.className = 'ddgg-bb'; b.innerHTML = html; b.title = title;
			b.addEventListener('mousedown', function(e) { e.preventDefault(); });
			b.addEventListener('click', function(e) { e.preventDefault(); onClick(); refresh(); });
			bar.appendChild(b);
			if (isActive) updaters.push(function() { b.classList.toggle('is-active', !!isActive()); });
			return b;
		}
		btn('<b>B</b>', '굵게', function() { editor.chain().focus().toggleBold().run(); }, function() { return editor.isActive('bold'); });
		btn('<i>I</i>', '기울임', function() { editor.chain().focus().toggleItalic().run(); }, function() { return editor.isActive('italic'); });
		btn('<u>U</u>', '밑줄', function() { editor.chain().focus().toggleUnderline().run(); }, function() { return editor.isActive('underline'); });
		btn('<s>S</s>', '취소선', function() { editor.chain().focus().toggleStrike().run(); }, function() { return editor.isActive('strike'); });
		btn('<code>&lt;&gt;</code>', '인라인 코드', function() { editor.chain().focus().toggleCode().run(); }, function() { return editor.isActive('code'); });
		var sep = document.createElement('span'); sep.className = 'ddgg-bb-sep'; bar.appendChild(sep);
		btn('🔗', '링크', function() {
			var prev = editor.getAttributes('link').href || '';
			var url = window.prompt('링크 URL (비우면 해제)', prev);
			if (url === null) return;
			if (url.trim() === '') { editor.chain().focus().extendMarkRange('link').unsetLink().run(); return; }
			editor.chain().focus().extendMarkRange('link').setLink({ href: url.trim() }).run();
		}, function() { return editor.isActive('link'); });
		// Color picker as an in-bubble preset palette. Native <input type=color>
		// steals editor focus and races with the bubble's show/hide; preset
		// swatches with preventDefault keep the selection intact.
		var COLORS = ['#000000', '#495057', '#e03131', '#e8590c', '#f08f00', '#2f9e44', '#1971c2', '#6741d9', '#c2255c', '#ffffff'];
		var colorBtn = document.createElement('button');
		colorBtn.type = 'button'; colorBtn.className = 'ddgg-bb ddgg-bb-colorbtn'; colorBtn.title = '글자색';
		colorBtn.innerHTML = '<span class="ddgg-bb-a">A</span>';
		bar.appendChild(colorBtn);
		var palette = document.createElement('div');
		palette.className = 'ddgg-bb-palette'; palette.style.display = 'none';
		COLORS.forEach(function(c) {
			var sw = document.createElement('button');
			sw.type = 'button'; sw.className = 'ddgg-bb-sw'; sw.title = c;
			sw.style.background = c;
			sw.addEventListener('mousedown', function(e) { e.preventDefault(); });
			sw.addEventListener('click', function(e) { e.preventDefault(); editor.chain().focus().setColor(c).run(); palette.style.display = 'none'; refresh(); });
			palette.appendChild(sw);
		});
		var resetSw = document.createElement('button');
		resetSw.type = 'button'; resetSw.className = 'ddgg-bb-sw ddgg-bb-reset'; resetSw.title = '기본색'; resetSw.textContent = '×';
		resetSw.addEventListener('mousedown', function(e) { e.preventDefault(); });
		resetSw.addEventListener('click', function(e) { e.preventDefault(); editor.chain().focus().unsetColor().run(); palette.style.display = 'none'; refresh(); });
		palette.appendChild(resetSw);
		colorBtn.addEventListener('mousedown', function(e) { e.preventDefault(); });
		colorBtn.addEventListener('click', function(e) { e.preventDefault(); palette.style.display = (palette.style.display === 'none') ? 'flex' : 'none'; });
		bar.appendChild(palette);

		function refresh() { updaters.forEach(function(fn) { fn(); }); }
		function hide() { bar.style.display = 'none'; if (palette) palette.style.display = 'none'; }
		function show() {
			// Don't hide while the user is interacting with the bubble itself
			// (e.g. the native color picker), which blurs the editor.
			if (bar.contains(document.activeElement)) return;
			var sel = editor.state.selection;
			if (sel.empty || !editor.isFocused) { hide(); return; }
			// Skip node selections (images/cards) and active block drags.
			if (sel.node) { hide(); return; }
			if (editor.view.dragging || editor.view.dom.classList.contains('dragging')) { hide(); return; }
			if (editor.isActive('codeBlock')) { hide(); return; }
			var start = editor.view.coordsAtPos(sel.from);
			var end = editor.view.coordsAtPos(sel.to);
			bar.style.display = 'flex';
			refresh();
			var pw = bar.offsetWidth, ph = bar.offsetHeight, pad = 8;
			var mid = (start.left + end.left) / 2;
			var left = Math.max(pad, Math.min(mid - pw / 2, window.innerWidth - pw - pad));
			var top = start.top - ph - 8;
			if (top < pad) top = end.bottom + 8;
			bar.style.left = left + 'px';
			bar.style.top = top + 'px';
		}
		editor.on('selectionUpdate', show);
		editor.on('transaction', function() { if (bar.style.display !== 'none') show(); });
		editor.on('blur', function() { setTimeout(function() { if (!bar.contains(document.activeElement)) hide(); }, 120); });
		editor.on('focus', show);
		editor.on('destroy', function() { if (bar.parentNode) bar.parentNode.removeChild(bar); });
	}

	// --- Standalone block menu (used by the + button) -----------------------
	function openBlockMenu(editor, range, rect, actions) {
		var items = slashItems(actions);
		var idx = 0;
		var popup = document.createElement('div'); popup.className = 'ddgg-slash';
		var listEl = document.createElement('div'); listEl.className = 'ddgg-slash-list';
		popup.appendChild(listEl);
		document.body.appendChild(popup);
		function hl() {
			Array.prototype.forEach.call(listEl.children, function(c, i) { c.classList.toggle('is-active', i === idx); });
			var a = listEl.children[idx]; if (a && a.scrollIntoView) a.scrollIntoView({ block: 'nearest' });
		}
		function choose(i) { var it = items[i]; close(); if (it) it.command({ editor: editor, range: range }); }
		function close() {
			document.removeEventListener('keydown', onKey, true);
			document.removeEventListener('mousedown', onDoc, true);
			if (popup.parentNode) popup.parentNode.removeChild(popup);
		}
		function onKey(e) {
			if (e.key === 'ArrowDown') { e.preventDefault(); idx = (idx + 1) % items.length; hl(); }
			else if (e.key === 'ArrowUp') { e.preventDefault(); idx = (idx - 1 + items.length) % items.length; hl(); }
			else if (e.key === 'Enter') { e.preventDefault(); choose(idx); }
			else if (e.key === 'Escape') { e.preventDefault(); close(); editor.commands.focus(); }
		}
		function onDoc(e) { if (!popup.contains(e.target)) close(); }
		items.forEach(function(it, i) {
			var row = document.createElement('button'); row.type = 'button';
			row.className = 'ddgg-slash-item' + (i === idx ? ' is-active' : '');
			row.innerHTML = '<span class="ddgg-slash-ic">' + it.icon + '</span><span class="ddgg-slash-tx"><span class="ddgg-slash-t">' + it.title + '</span><span class="ddgg-slash-d">' + it.desc + '</span></span>';
			row.addEventListener('mousedown', function(e) { e.preventDefault(); });
			row.addEventListener('click', function(e) { e.preventDefault(); choose(i); });
			row.addEventListener('mousemove', function() { if (idx !== i) { idx = i; hl(); } });
			listEl.appendChild(row);
		});
		positionMenuPopup(popup, rect);
		setTimeout(function() {
			document.addEventListener('keydown', onKey, true);
			document.addEventListener('mousedown', onDoc, true);
		}, 0);
	}

	// --- Drag handle augmentation (+ button & grip) -------------------------
	var GRIP_SVG = '<svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor"><circle cx="5" cy="3" r="1.3"/><circle cx="11" cy="3" r="1.3"/><circle cx="5" cy="8" r="1.3"/><circle cx="11" cy="8" r="1.3"/><circle cx="5" cy="13" r="1.3"/><circle cx="11" cy="13" r="1.3"/></svg>';
	function setupDragHandle(editor, el, actions) {
		function plusClick(handle) {
			var hr = handle.getBoundingClientRect();
			var info = editor.view.posAtCoords({ left: hr.right + 28, top: hr.top + hr.height / 2 });
			var pos = info ? info.pos : editor.state.selection.from;
			// Put the cursor at the end of the hovered line and open the menu
			// there — the chosen block applies to THIS line (no new line below).
			var endPos;
			try { endPos = editor.state.doc.resolve(pos).end(); } catch (e) { endPos = pos; }
			editor.chain().focus().setTextSelection(endPos).run();
			var cur = editor.state.selection.from;
			openBlockMenu(editor, { from: cur, to: cur }, hr, actions);
		}
		function augment() {
			var handle = el.querySelector('.drag-handle');
			if (!handle) return false;
			if (handle.__ddggAug) return true;
			handle.__ddggAug = true;
			handle.classList.add('ddgg-dh');
			handle.innerHTML = '<button type="button" class="ddgg-dh-plus" title="아래에 블록 추가 (/)">+</button><span class="ddgg-dh-grip" title="드래그하여 이동">' + GRIP_SVG + '</span>';
			var plus = handle.querySelector('.ddgg-dh-plus');
			plus.draggable = false;
			plus.addEventListener('mousedown', function(e) { e.stopPropagation(); });
			plus.addEventListener('dragstart', function(e) { e.preventDefault(); e.stopPropagation(); });
			plus.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); plusClick(handle); });
			// Keep the handle visible while the pointer is over it or its children
			// (the extension hides on editor mouseout; our injected children break its guard).
			handle.addEventListener('mouseenter', function() { handle.classList.remove('hide'); });
			handle.addEventListener('mousemove', function() { handle.classList.remove('hide'); });
			return true;
		}
		// After a drag-drop reorder, ProseMirror leaves a NodeSelection on the
		// moved block and the browser doesn't repaint it until the next click.
		// Collapsing the selection to a text cursor + refocusing mimics that
		// click and forces the redraw.
		function repaint() {
			requestAnimationFrame(function() {
				if (editor.isDestroyed) return;
				try {
					var max = editor.state.doc.content.size;
					var pos = Math.max(0, Math.min(editor.state.selection.from, max));
					editor.chain().focus().setTextSelection(pos).run();
				} catch (e) {}
			});
		}
		editor.view.dom.addEventListener('drop', repaint);
		editor.view.dom.addEventListener('dragend', repaint);

		if (!augment()) {
			var tries = 0;
			var iv = setInterval(function() { if (augment() || ++tries > 20) clearInterval(iv); }, 100);
		}
	}

	// --- Autosave -----------------------------------------------------------
	function onSavedSrl(ret_obj) {
		var seq = ret_obj.editor_sequence, primary_key = ret_obj.key;
		var form = $('#ddgg_md_instance_' + seq).closest('form')[0];
		if (form && ret_obj.document_srl !== 0 && form[primary_key]) {
			form[primary_key].value = ret_obj.document_srl;
			if (typeof reloadUploader === 'function') reloadUploader(seq);
		}
	}
	function setupAutosave(form, editor, seq, primary_key, content_input) {
		var titleEl = form.elements['_saved_doc_title'];
		if (typeof titleEl === 'undefined') return;
		var savedTitle = titleEl.value, savedContent = form.elements['_saved_doc_content'].value;
		if (savedTitle || savedContent) {
			if (window.confirm(form.elements['_saved_doc_message'].value)) {
				if (form.elements['title']) form.elements['title'].value = savedTitle;
				editor.commands.setContent(savedContent || '', false);
				if (content_input) content_input.value = savedContent;
				if (typeof exec_json === 'function') {
					exec_json('editor.procEditorLoadSavedDocument', { editor_sequence: seq, primary_key: primary_key, mid: (typeof current_mid !== 'undefined') ? current_mid : '' }, onSavedSrl);
				}
			} else if (typeof editorRemoveSavedDoc === 'function') { editorRemoveSavedDoc(); }
		}
		if (typeof editorEnableAutoSave === 'function') editorEnableAutoSave(form, seq);
	}

	// --- Color theme (auto / light / dark) ----------------------------------
	function resolveScheme() {
		var ht = document.documentElement.getAttribute('color-theme');
		if (ht === 'dark') return 'dark';
		if (ht === 'light') return 'light';
		if (typeof getColorScheme === 'function') { try { return getColorScheme() === 'dark' ? 'dark' : 'light'; } catch (e) {} }
		return (window.matchMedia && window.matchMedia('(prefers-color-scheme:dark)').matches) ? 'dark' : 'light';
	}
	function setupTheme(el, config) {
		function apply() {
			var mode = config.colorset;
			var resolved = (mode === 'light' || mode === 'dark') ? mode : resolveScheme();
			el.classList.remove('auto', 'light', 'dark');
			el.classList.add(resolved);
			document.documentElement.setAttribute('data-ddgg-theme', resolved);
		}
		apply();
		if (config.colorset !== 'light' && config.colorset !== 'dark') {
			try {
				var obs = new MutationObserver(apply);
				obs.observe(document.documentElement, { attributes: true, attributeFilter: ['color-theme', 'class'] });
				obs.observe(document.body, { attributes: true, attributeFilter: ['class'] });
			} catch (e) {}
			if (window.matchMedia) {
				var mm = window.matchMedia('(prefers-color-scheme:dark)');
				if (mm.addEventListener) mm.addEventListener('change', apply);
				else if (mm.addListener) mm.addListener(apply);
			}
		}
	}

	// --- Per-instance init --------------------------------------------------
	function initInstance(el) {
		var $el = $(el);
		var data = $el.data();
		var seq = data.editorSequence;
		var primary_key = data.editorPrimaryKeyName;
		var content_key = data.editorContentKeyName;
		var config = data.editorConfig || {};
		var $form = $el.closest('form');
		var form = $form[0];
		var content_input = $form.find('[name="' + content_key + '"]')[0] || null;
		var primary_input = $form.find('[name="' + primary_key + '"]')[0] || null;
		var initial = content_input ? content_input.value : '';

		setupTheme(el, config);

		el.innerHTML = '';
		var mount = document.createElement('div');
		mount.className = 'ddgg-md-editable';
		mount.style.minHeight = (config.height || 360) + 'px';
		el.appendChild(mount);

		var actions = {
			uploadImage: function() {
				var fileInput = document.querySelector('#xefu-container-' + seq + ' input[type=file]');
				if (fileInput) { fileInput.click(); return; }
				var url = window.prompt('이미지 URL'); if (url) { editor.chain().focus().setImage({ src: url }).run(); ensureBreak(editor); }
			}
		};

		var editor = new T.Editor({
			element: mount,
			extensions: buildExtensions(config, actions),
			content: initial,
			autofocus: !!config.focus,
			editorProps: { attributes: { class: 'rhymix_content xe_content' } },
			onUpdate: function(props) {
				var ed = (props && props.editor) ? props.editor : editor;
				if (content_input) content_input.value = ed.getHTML();
			}
		});

		// Put the min-height on the ProseMirror element itself so the whole
		// editing surface (including the empty area below the last block and
		// the left gutter) is part of the hover-detection area.
		try { editor.view.dom.style.minHeight = (config.height || 360) + 'px'; } catch (e) {}

		setupBubble(editor);
		setupDragHandle(editor, el, actions);

		// Source toggle (subtle, top-right)
		var srcWrap = document.createElement('div');
		srcWrap.className = 'ddgg-md-tools';
		var srcBtn = document.createElement('button');
		srcBtn.type = 'button'; srcBtn.className = 'ddgg-md-srcbtn'; srcBtn.title = 'HTML 소스';
		srcBtn.innerHTML = '&lt;/&gt;';
		var sourceTa = null;
		srcBtn.addEventListener('click', function(e) {
			e.preventDefault();
			if (sourceTa) {
				editor.commands.setContent(sourceTa.value, true);
				if (content_input) content_input.value = editor.getHTML();
				sourceTa.parentNode.removeChild(sourceTa); sourceTa = null;
				mount.style.display = ''; srcBtn.classList.remove('is-active');
			} else {
				sourceTa = document.createElement('textarea');
				sourceTa.className = 'ddgg-md-source';
				sourceTa.value = editor.getHTML();
				sourceTa.style.minHeight = mount.style.minHeight || '360px';
				sourceTa.addEventListener('input', function() { if (content_input) content_input.value = sourceTa.value; });
				mount.style.display = 'none';
				mount.parentNode.insertBefore(sourceTa, mount.nextSibling);
				srcBtn.classList.add('is-active');
			}
		});
		srcWrap.appendChild(srcBtn);
		el.insertBefore(srcWrap, el.firstChild);
		el.style.minHeight = '';

		window.__ddggEditors[seq] = editor;
		if (form) form.setAttribute('editor_sequence', seq);
		window.editorRelKeys[seq] = {
			primary: primary_input,
			content: content_input,
			func: function(s) { return editorGetContent(s); },
			pasteHTML: function(html) { editor.chain().focus().insertContent(html).run(); ensureBreak(editor); }
		};
		if (content_input) content_input.value = editor.getHTML();
		if (form) setupAutosave(form, editor, seq, primary_key, content_input);
	}

	$(function() {
		$('.rx_ddgg_md').each(function() {
			try { initInstance(this); }
			catch (e) { if (window.console) console.error('[ddgg_md] init failed', e); }
		});
	});

})(jQuery);
