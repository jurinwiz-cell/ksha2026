/**
 * ddgg_md (TipTap, Notion-style) — global interface functions.
 * Shares the window.__ddggEditors registry; wraps TipTap behind the
 * CKEditor-compatible surface used by the file uploader & components.
 */
"use strict";

window.__ddggEditors = window.__ddggEditors || {};

function _getDdggInstance(editor_sequence) {
	return window.__ddggEditors[editor_sequence] || null;
}

function _getCkeInstance(editor_sequence) {
	var ed = _getDdggInstance(editor_sequence);
	if (!ed) {
		return null;
	}
	return {
		getData: function() { return ed.getHTML(); },
		setData: function(content) { ed.commands.setContent(content || '', false); },
		insertHtml: function(content) {
			ed.chain().focus().insertContent(content).run();
			// Ensure a text line follows inserted media (image/iframe/card) so the
			// next keystroke doesn't overwrite the just-inserted block.
			try {
				var size = ed.state.doc.content.size;
				var sel = ed.state.selection;
				var $to = ed.state.doc.resolve(Math.min(sel.to, size));
				if (sel.node || !$to.parent || !$to.parent.isTextblock) {
					var pos = sel.to;
					ed.chain().insertContentAt(pos, { type: 'paragraph' }).setTextSelection(pos + 1).focus().run();
				}
			} catch (e) {}
		},
		getText: function() { return ed.getText(); },
		getSelection: function() {
			var sel = window.getSelection && window.getSelection();
			return { getSelectedText: function() { return sel ? String(sel) : ''; } };
		}
	};
}

function _getCkeContainer(editor_sequence) {
	return jQuery('#ddgg_md_instance_' + editor_sequence);
}

function editorGetContent(editor_sequence) {
	var ed = _getDdggInstance(editor_sequence);
	return ed ? ed.getHTML() : '';
}

function editorReplaceHTML(iframe_obj, content) {
	var editor_sequence = parseInt(String(iframe_obj.id).replace(/^.*_/, ''), 10);
	var inst = _getCkeInstance(editor_sequence);
	if (inst) { inst.insertHtml(content); }
}

function editorGetIFrame(editor_sequence) {
	return jQuery('#ddgg_md_instance_' + editor_sequence).get(0);
}
