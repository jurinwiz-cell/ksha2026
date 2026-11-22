/**
 * ddgg_editor (TipTap) — bootstrap.
 *
 * Mounts a TipTap (ProseMirror) editor onto each `.rx_ddgg_editor` container
 * and wires it into the Rhymix editor contract:
 *   - keeps the hidden content <input> in sync on every change (submit path)
 *   - registers editorRelKeys[seq] (primary / content / func / pasteHTML)
 *   - exposes the instance via window.__ddggEditors[seq] for _getCkeInstance()
 *   - integrates autosave (editorEnableAutoSave / editorRemoveSavedDoc)
 */
(function($) {
	"use strict";

	if (!window.DdggTiptap || !window.DdggTiptap.Editor) {
		if (window.console) console.error('[ddgg_editor] TipTap bundle not loaded.');
		return;
	}
	var T = window.DdggTiptap;

	// --- Fidelity helpers ---------------------------------------------------
	function styleAttr(cssProp, domProp) {
		return {
			default: null,
			parseHTML: function(el) { return el.style[domProp] || null; },
			renderHTML: function(attrs) {
				var v = attrs[domProp];
				return v ? { style: cssProp + ':' + v } : {};
			}
		};
	}
	function passAttr(name) {
		return {
			default: null,
			parseHTML: function(el) { return el.getAttribute(name); },
			renderHTML: function(attrs) {
				var o = {};
				if (attrs[name] != null) o[name] = attrs[name];
				return o;
			}
		};
	}

	// --- Custom extensions to preserve legacy CKEditor markup ---------------
	var RxTextStyle = T.TextStyle.extend({
		addAttributes: function() {
			var parent = this.parent ? (this.parent() || {}) : {};
			return Object.assign({}, parent, {
				fontSize:        styleAttr('font-size', 'fontSize'),
				fontFamily:      styleAttr('font-family', 'fontFamily'),
				backgroundColor: styleAttr('background-color', 'backgroundColor')
			});
		}
	});
	var RxImage = T.Image.extend({
		addAttributes: function() {
			var parent = this.parent ? (this.parent() || {}) : {};
			return Object.assign({}, parent, {
				width:  passAttr('width'),
				height: passAttr('height'),
				style:  passAttr('style'),
				class:  passAttr('class')
			});
		}
	});
	var RxIframe = T.Node.create({
		name: 'rxIframe',
		group: 'block',
		atom: true,
		selectable: true,
		draggable: true,
		addAttributes: function() {
			return {
				src:             { default: null },
				width:           { default: null },
				height:          { default: null },
				frameborder:     { default: '0' },
				allow:           { default: null },
				allowfullscreen: { default: 'true' },
				loading:         { default: null },
				style:           { default: null }
			};
		},
		parseHTML: function() { return [{ tag: 'iframe[src]' }]; },
		renderHTML: function(props) { return ['iframe', T.mergeAttributes(props.HTMLAttributes)]; }
	});

	// OG link-preview card (atom block). Round-trips via data-* and visible content.
	var RxLinkCard = T.Node.create({
		name: 'rxLinkCard',
		group: 'block',
		atom: true,
		selectable: true,
		draggable: true,
		addAttributes: function() {
			return {
				href:  { default: null },
				image: { default: null },
				title: { default: null },
				desc:  { default: null },
				site:  { default: null }
			};
		},
		parseHTML: function() {
			return [{
				tag: 'a.og-card',
				priority: 60,
				getAttrs: function(el) {
					function txt(sel) { var n = el.querySelector(sel); return n ? n.textContent : null; }
					var img = el.querySelector('img.og-card-img');
					return {
						href:  el.getAttribute('href'),
						image: el.getAttribute('data-image') || (img ? img.getAttribute('src') : null),
						title: el.getAttribute('data-title') || txt('.og-card-title'),
						desc:  el.getAttribute('data-desc') || txt('.og-card-desc'),
						site:  el.getAttribute('data-site') || txt('.og-card-site')
					};
				}
			}];
		},
		renderHTML: function(props) {
			var a = props.HTMLAttributes || {};
			// Inline styles so the card also renders on the public document view
			// (the editor skin CSS is not loaded there).
			var attrs = {
				class: 'og-card',
				href: a.href || '#',
				target: '_blank',
				rel: 'noopener noreferrer',
				'data-image': a.image || '',
				'data-title': a.title || '',
				'data-desc': a.desc || '',
				'data-site': a.site || '',
				style: 'display:flex;text-decoration:none;color:inherit;border:1px solid #e1e6ec;border-radius:10px;overflow:hidden;background:#fff;max-width:520px;margin:10px 0;'
			};
			var imgNode = a.image
				? ['img', { class: 'og-card-img', src: a.image, alt: '', style: 'flex:0 0 140px;width:140px;height:112px;object-fit:cover;background:#eef1f4;' }]
				: ['span', { class: 'og-card-img og-card-img-empty', style: 'flex:0 0 6px;background:#1769b3;align-self:stretch;' }];
			return ['a', attrs,
				imgNode,
				['span', { class: 'og-card-body', style: 'display:flex;flex-direction:column;gap:4px;padding:12px 14px;min-width:0;' },
					['span', { class: 'og-card-title', style: 'font-weight:600;font-size:15px;color:#1b2430;' }, a.title || a.href || ''],
					['span', { class: 'og-card-desc', style: 'font-size:13px;color:#5a6573;' }, a.desc || ''],
					['span', { class: 'og-card-site', style: 'font-size:12px;color:#8a93a0;' }, a.site || '']
				]
			];
		}
	});

	// Legacy editor-component placeholder (<img|div editor_component="...">).
	// Preserves the entire element verbatim so the server can compile it.
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

	function buildExtensions(config) {
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
			T.TableRow,
			T.TableHeader,
			T.TableCell
		];
		if (T.Placeholder && config.placeholder) {
			ext.push(T.Placeholder.configure({ placeholder: config.placeholder }));
		}
		return ext;
	}

	// --- Embed transforms ---------------------------------------------------
	function videoEmbed(v) {
		if (/<iframe/i.test(v)) return v;
		var yt = v.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([\w-]{11})/);
		if (yt) return '<iframe src="https://www.youtube.com/embed/' + yt[1] + '" width="640" height="360" style="max-width:100%;height:auto;border:0;" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
		var vm = v.match(/vimeo\.com\/(\d+)/);
		if (vm) return '<iframe src="https://player.vimeo.com/video/' + vm[1] + '" width="640" height="360" style="max-width:100%;height:auto;border:0;" frameborder="0" allowfullscreen></iframe>';
		return null;
	}

	// After inserting an atom/media block, ensure a text line follows it (and
	// move the cursor there) so the next keystroke doesn't overwrite it.
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

	// --- OG link-preview card -----------------------------------------------
	function hostOf(u) { try { return new URL(u).hostname.replace(/^www\./, ''); } catch (e) { return u; } }
	function insertLinkCard(editor, url) {
		var base = window.request_uri || '/';
		function insert(d) {
			insertMedia(editor, {
				type: 'rxLinkCard',
				attrs: {
					href:  (d && d.url) || url,
					title: (d && d.title) || url,
					desc:  (d && d.description) || '',
					site:  (d && d.site_name) || hostOf(url),
					image: (d && d.image) || null
				}
			});
		}
		$.ajax({ url: base, method: 'GET', data: { act: 'procDdggOgPreview', url: url }, dataType: 'json', timeout: 9000 })
			.done(function(res) { insert(res && res.success ? res.data : null); })
			.fail(function() { insert(null); });
	}
	// --- Icons (monochrome line SVG, inherit currentColor) ------------------
	var ICONS = {
		image:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="M21 16l-5-5L4 20"/></svg>',
		emoji:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 14.5s1.3 1.8 3.5 1.8 3.5-1.8 3.5-1.8"/><line x1="9" y1="9.5" x2="9.01" y2="9.5"/><line x1="15" y1="9.5" x2="15.01" y2="9.5"/></svg>',
		link:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.07 0l2.5-2.5a5 5 0 0 0-7.07-7.07l-1.2 1.2"/><path d="M14 11a5 5 0 0 0-7.07 0L4.43 13.5a5 5 0 0 0 7.07 7.07l1.2-1.2"/></svg>',
		youtube:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="4.5"/><path d="M10 8.8l5.2 3.2L10 15.2z" fill="currentColor" stroke="none"/></svg>',
		source: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 8l-4 4 4 4"/><path d="M16 8l4 4-4 4"/><line x1="13.5" y1="5.5" x2="10.5" y2="18.5"/></svg>',
		alignLeft:   '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="10" x2="13" y2="10"/><line x1="4" y1="14" x2="18" y2="14"/><line x1="4" y1="18" x2="11" y2="18"/></svg>',
		alignCenter: '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="7" y1="10" x2="17" y2="10"/><line x1="5" y1="14" x2="19" y2="14"/><line x1="8" y1="18" x2="16" y2="18"/></svg>',
		alignRight:  '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="11" y1="10" x2="20" y2="10"/><line x1="6" y1="14" x2="20" y2="14"/><line x1="13" y1="18" x2="20" y2="18"/></svg>',
		component:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5a2 2 0 1 1 4 0v1h3a1 1 0 0 1 1 1v3h1a2 2 0 1 1 0 4h-1v3a1 1 0 0 1-1 1h-3v-1a2 2 0 1 0-4 0v1H5a1 1 0 0 1-1-1v-3H3a2 2 0 1 1 0-4h1V7a1 1 0 0 1 1-1h3V5z"/></svg>'
	};

	var EMOJIS =['😀','😁','😂','🤣','😊','😍','😎','🥰','😉','😏','😢','😭','😡','😱','🤔','😴','🥳','😅','😬','🙄',
		'👍','👎','👏','🙏','🙌','💪','👀','✌️','🤝','👋','❤️','🔥','✨','⭐','💯','🎉','🎁','💡','📌','✅','❌','⚠️','💬','🚀','☕','🍀','🌟','😮','😆','🤗'];

	// --- Popups -------------------------------------------------------------
	function closePopup(bar) {
		if (bar.__popup) {
			bar.__popup.remove();
			bar.__popup = null;
			if (bar.__popupBtn) bar.__popupBtn.classList.remove('is-active');
			bar.__popupBtn = null;
			if (bar.__popupDocHandler) {
				document.removeEventListener('mousedown', bar.__popupDocHandler);
				bar.__popupDocHandler = null;
			}
		}
	}
	function togglePopup(bar, btn, build) {
		if (bar.__popupBtn === btn) { closePopup(bar); return; }
		closePopup(bar);
		var pop = document.createElement('div');
		pop.className = 'ddgg-popup';
		build(pop, function() { closePopup(bar); });
		bar.appendChild(pop);
		pop.style.left = Math.max(4, btn.offsetLeft) + 'px';
		pop.style.top = (btn.offsetTop + btn.offsetHeight + 6) + 'px';
		bar.__popup = pop;
		bar.__popupBtn = btn;
		btn.classList.add('is-active');
		var handler = function(e) {
			if (!pop.contains(e.target) && e.target !== btn && !btn.contains(e.target)) closePopup(bar);
		};
		bar.__popupDocHandler = handler;
		setTimeout(function() { document.addEventListener('mousedown', handler); }, 0);
	}

	// --- Toolbar ------------------------------------------------------------
	var FONT_SIZES = ['12px', '13px', '14px', '16px', '18px', '20px', '24px', '28px', '32px', '40px'];

	function buildToolbar(editor, config, actions) {
		var bar = document.createElement('div');
		bar.className = 'ddgg-toolbar';
		var row1 = document.createElement('div'); row1.className = 'ddgg-row ddgg-row1';
		var row2 = document.createElement('div'); row2.className = 'ddgg-row ddgg-row2';
		bar.appendChild(row1);
		if (config.toolbar !== 'simple') bar.appendChild(row2);
		var updaters = [];

		function mkBtn(parent, o) {
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'ddgg-btn' + (o.cls ? (' ' + o.cls) : '');
			b.innerHTML = o.html;
			if (o.title) b.title = o.title;
			b.addEventListener('mousedown', function(e) { e.preventDefault(); });
			b.addEventListener('click', function(e) { e.preventDefault(); o.onClick(editor, b); });
			parent.appendChild(b);
			if (o.isActive) updaters.push(function() { b.classList.toggle('is-active', !!o.isActive(editor)); });
			return b;
		}
		function sep(parent) { var s = document.createElement('span'); s.className = 'ddgg-sep'; parent.appendChild(s); }

		// ===== ROW 1 — primary actions (icon over label) =====
		function mkTool(o) {
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'ddgg-tool' + (o.cls ? (' ' + o.cls) : '');
			b.innerHTML = '<span class="ddgg-ic">' + o.icon + '</span><span class="ddgg-lb">' + o.label + '</span>';
			if (o.title) b.title = o.title;
			b.addEventListener('mousedown', function(e) { e.preventDefault(); });
			b.addEventListener('click', function(e) { e.preventDefault(); o.onClick(editor, b); });
			row1.appendChild(b);
			if (o.isActive) updaters.push(function() { b.classList.toggle('is-active', !!o.isActive(editor)); });
			return b;
		}

		mkTool({ icon: ICONS.image, label: '이미지', title: '이미지 첨부', onClick: function() { actions.uploadImage(); } });
		mkTool({ icon: ICONS.emoji, label: '이모지', title: '이모지', onClick: function(ed, b) {
			togglePopup(bar, b, function(pop, close) {
				pop.classList.add('ddgg-popup-emoji');
				EMOJIS.forEach(function(em) {
					var eb = document.createElement('button');
					eb.type = 'button'; eb.className = 'ddgg-emoji'; eb.textContent = em;
					eb.addEventListener('mousedown', function(ev) { ev.preventDefault(); });
					eb.addEventListener('click', function(ev) { ev.preventDefault(); ed.chain().focus().insertContent(em).run(); close(); });
					pop.appendChild(eb);
				});
			});
		}});
		mkTool({ icon: ICONS.link, label: '링크', title: '링크 미리보기 카드', onClick: function(ed) {
			var url = window.prompt('링크 URL을 입력하세요 (OG 미리보기 카드로 삽입됩니다)');
			if (url === null) return;
			url = url.trim();
			if (url === '') return;
			if (!/^https?:\/\//i.test(url)) url = 'https://' + url;
			insertLinkCard(ed, url);
		}});
		mkTool({ icon: ICONS.youtube, label: '유튜브', title: '유튜브 임베드', onClick: function(ed) {
			var url = window.prompt('유튜브 링크를 붙여넣으세요');
			if (url === null) return;
			var html = videoEmbed(url.trim());
			if (!html) { alert('유효한 유튜브 링크가 아닙니다.'); return; }
			insertMedia(ed, html);
		}});
		mkTool({ icon: ICONS.source, label: '소스', title: 'HTML 소스', cls: 'ddgg-btn-source', onClick: function(ed, b) { actions.toggleSource(b); } });

		// Enabled editor components (emoticon, image_gallery, poll_maker, ...)
		if (config.enable_component && config.components && config.components.length) {
			config.components.forEach(function(comp) {
				mkTool({
					icon: comp.icon ? '<img class="ddgg-ic-img" src="' + comp.icon + '" alt="" onerror="this.style.display=\'none\'">' : ICONS.component,
					label: comp.title,
					title: comp.title,
					onClick: function() { actions.component(comp.name); }
				});
			});
		}

		// ===== ROW 2 — secondary (formatting) =====
		if (config.toolbar !== 'simple') {
			// font size
			var sizeSel = document.createElement('select');
			sizeSel.className = 'ddgg-select';
			var baseSize = (config.content_font_size && config.content_font_size !== 'none') ? parseInt(config.content_font_size, 10) : 16;
			var optDefault = document.createElement('option');
			optDefault.value = ''; optDefault.textContent = baseSize + 'px';
			sizeSel.appendChild(optDefault);
			FONT_SIZES.forEach(function(s) {
				var op = document.createElement('option'); op.value = s; op.textContent = s; sizeSel.appendChild(op);
			});
			sizeSel.addEventListener('change', function() {
				if (sizeSel.value) editor.chain().focus().setMark('textStyle', { fontSize: sizeSel.value }).run();
				else editor.chain().focus().setMark('textStyle', { fontSize: null }).run();
			});
			row2.appendChild(sizeSel);
			updaters.push(function() { sizeSel.value = editor.getAttributes('textStyle').fontSize || ''; });
			sep(row2);

			mkBtn(row2, { html: '<b>B</b>', title: '굵게', isActive: function(e) { return e.isActive('bold'); }, onClick: function(e) { e.chain().focus().toggleBold().run(); } });
			mkBtn(row2, { html: '<i>I</i>', title: '기울임', isActive: function(e) { return e.isActive('italic'); }, onClick: function(e) { e.chain().focus().toggleItalic().run(); } });
			mkBtn(row2, { html: '<u>U</u>', title: '밑줄', isActive: function(e) { return e.isActive('underline'); }, onClick: function(e) { e.chain().focus().toggleUnderline().run(); } });
			mkBtn(row2, { html: '<s>S</s>', title: '취소선', isActive: function(e) { return e.isActive('strike'); }, onClick: function(e) { e.chain().focus().toggleStrike().run(); } });

			// text color
			var color = document.createElement('input');
			color.type = 'color'; color.className = 'ddgg-color'; color.title = '글자색'; color.value = '#000000';
			color.addEventListener('input', function() { editor.chain().focus().setColor(color.value).run(); });
			row2.appendChild(color);
			sep(row2);

			mkBtn(row2, { html: 'H2', title: '제목2', isActive: function(e) { return e.isActive('heading', { level: 2 }); }, onClick: function(e) { e.chain().focus().toggleHeading({ level: 2 }).run(); } });
			mkBtn(row2, { html: 'H3', title: '제목3', isActive: function(e) { return e.isActive('heading', { level: 3 }); }, onClick: function(e) { e.chain().focus().toggleHeading({ level: 3 }).run(); } });
			mkBtn(row2, { html: '❝', title: '인용', isActive: function(e) { return e.isActive('blockquote'); }, onClick: function(e) { e.chain().focus().toggleBlockquote().run(); } });
			mkBtn(row2, { html: '&lt;/&gt;', title: '코드블록', isActive: function(e) { return e.isActive('codeBlock'); }, onClick: function(e) { e.chain().focus().toggleCodeBlock().run(); } });
			sep(row2);

			mkBtn(row2, { html: '• ', title: '글머리 목록', isActive: function(e) { return e.isActive('bulletList'); }, onClick: function(e) { e.chain().focus().toggleBulletList().run(); } });
			mkBtn(row2, { html: '1.', title: '번호 목록', isActive: function(e) { return e.isActive('orderedList'); }, onClick: function(e) { e.chain().focus().toggleOrderedList().run(); } });
			mkBtn(row2, { html: '▦', title: '표 삽입', onClick: function(e) { e.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(); } });
			sep(row2);

			mkBtn(row2, { html: ICONS.alignLeft, title: '왼쪽 정렬', isActive: function(e) { return e.isActive({ textAlign: 'left' }); }, onClick: function(e) { e.chain().focus().setTextAlign('left').run(); } });
			mkBtn(row2, { html: ICONS.alignCenter, title: '가운데 정렬', isActive: function(e) { return e.isActive({ textAlign: 'center' }); }, onClick: function(e) { e.chain().focus().setTextAlign('center').run(); } });
			mkBtn(row2, { html: ICONS.alignRight, title: '오른쪽 정렬', isActive: function(e) { return e.isActive({ textAlign: 'right' }); }, onClick: function(e) { e.chain().focus().setTextAlign('right').run(); } });
			sep(row2);

			mkBtn(row2, { html: '↶', title: '실행 취소', onClick: function(e) { e.chain().focus().undo().run(); } });
			mkBtn(row2, { html: '↷', title: '다시 실행', onClick: function(e) { e.chain().focus().redo().run(); } });
		}

		bar.__update = function() { updaters.forEach(function(fn) { fn(); }); };
		return bar;
	}

	// --- Autosave (self-contained; no dependency on editor.app.js) ----------
	function onSavedSrl(ret_obj) {
		var seq = ret_obj.editor_sequence;
		var primary_key = ret_obj.key;
		var form = $('#ddgg_editor_instance_' + seq).closest('form')[0];
		if (form && ret_obj.document_srl !== 0 && form[primary_key]) {
			form[primary_key].value = ret_obj.document_srl;
			if (typeof reloadUploader === 'function') reloadUploader(seq);
		}
	}
	function setupAutosave(form, editor, seq, primary_key, content_input) {
		var titleEl = form.elements['_saved_doc_title'];
		if (typeof titleEl === 'undefined') return;
		var savedTitle = titleEl.value;
		var savedContent = form.elements['_saved_doc_content'].value;
		if (savedTitle || savedContent) {
			if (window.confirm(form.elements['_saved_doc_message'].value)) {
				if (form.elements['title']) form.elements['title'].value = savedTitle;
				editor.commands.setContent(savedContent || '', false);
				if (content_input) content_input.value = savedContent;
				if (typeof exec_json === 'function') {
					exec_json('editor.procEditorLoadSavedDocument', {
						editor_sequence: seq,
						primary_key: primary_key,
						mid: (typeof current_mid !== 'undefined') ? current_mid : ''
					}, onSavedSrl);
				}
			} else if (typeof editorRemoveSavedDoc === 'function') {
				editorRemoveSavedDoc();
			}
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
		mount.className = 'ddgg-editable';
		mount.style.minHeight = (config.height || 300) + 'px';

		var editor = new T.Editor({
			element: mount,
			extensions: buildExtensions(config),
			content: initial,
			autofocus: !!config.focus,
			editorProps: { attributes: { class: 'rhymix_content xe_content' } },
			onUpdate: function(props) {
				var ed = (props && props.editor) ? props.editor : editor;
				if (content_input) content_input.value = ed.getHTML();
				if (toolbar && toolbar.__update) toolbar.__update();
			},
			onSelectionUpdate: function() { if (toolbar && toolbar.__update) toolbar.__update(); }
		});

		// Actions that need instance scope (DOM refs).
		var sourceTa = null;
		var actions = {
			uploadImage: function() {
				var fileInput = document.querySelector('#xefu-container-' + seq + ' input[type=file]');
				if (fileInput) { fileInput.click(); return; }
				var url = window.prompt('이미지 URL');
				if (url) { editor.chain().focus().setImage({ src: url }).run(); ensureBreak(editor); }
			},
			component: function(name) {
				// Opens the standard Rhymix editor-component popup; it inserts back
				// via editorRelKeys[seq].pasteHTML (already wired in this skin).
				if (typeof window.openComponent === 'function') { window.openComponent(name, seq); }
				else if (typeof openComponent === 'function') { openComponent(name, seq); }
			},
			toggleSource: function(btn) {
				if (sourceTa) {
					editor.commands.setContent(sourceTa.value, true);
					if (content_input) content_input.value = editor.getHTML();
					sourceTa.parentNode.removeChild(sourceTa);
					sourceTa = null;
					mount.style.display = '';
					if (btn) btn.classList.remove('is-active');
				} else {
					sourceTa = document.createElement('textarea');
					sourceTa.className = 'ddgg-source';
					sourceTa.value = editor.getHTML();
					sourceTa.style.minHeight = mount.style.minHeight || '300px';
					sourceTa.addEventListener('input', function() { if (content_input) content_input.value = sourceTa.value; });
					mount.style.display = 'none';
					mount.parentNode.insertBefore(sourceTa, mount.nextSibling);
					if (btn) btn.classList.add('is-active');
				}
			}
		};

		var toolbar = config.hide_toolbar ? null : buildToolbar(editor, config, actions);
		if (toolbar) el.appendChild(toolbar);
		el.appendChild(mount);
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
		$('.rx_ddgg_editor').each(function() {
			try { initInstance(this); }
			catch (e) { if (window.console) console.error('[ddgg_editor] init failed', e); }
		});
	});

})(jQuery);
