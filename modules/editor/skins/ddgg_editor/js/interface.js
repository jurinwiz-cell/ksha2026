/**
 * ddgg_editor (TipTap) — global interface functions.
 *
 * These globals are the contract the rest of Rhymix uses to talk to the editor.
 * They wrap the live TipTap instance (stored in window.__ddggEditors[seq] by
 * editor.js) behind the same surface CKEditor exposed, so the existing file
 * uploader and editor components keep working unchanged.
 */
"use strict";

window.__ddggEditors = window.__ddggEditors || {};

function _getDdggInstance(editor_sequence) {
	return window.__ddggEditors[editor_sequence] || null;
}

/**
 * CKEditor-compatible instance shim.
 * The file uploader (jquery.fileupload) and components call:
 *   _getCkeInstance(seq).insertHtml(html, 'unfiltered_html')
 *   _getCkeInstance(seq).getData() / .setData(html)
 */
function _getCkeInstance(editor_sequence) {
	var ed = _getDdggInstance(editor_sequence);
	if (!ed) {
		return null;
	}
	return {
		getData: function() {
			return ed.getHTML();
		},
		setData: function(content) {
			ed.commands.setContent(content || '', false);
		},
		insertHtml: function(content) {
			ed.chain().focus().insertContent(content).run();
			// Ensure a text line follows inserted media so the next keystroke
			// doesn't overwrite the just-inserted block (image/iframe/card).
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
		getText: function() {
			return ed.getText();
		},
		getSelection: function() {
			// Minimal shim for legacy callers asking for selected text.
			var sel = window.getSelection && window.getSelection();
			return {
				getSelectedText: function() {
					return sel ? String(sel) : '';
				}
			};
		}
	};
}

function _getCkeContainer(editor_sequence) {
	return jQuery('#ddgg_editor_instance_' + editor_sequence);
}

/**
 * Returns the HTML to be submitted. Matches CKEditor behaviour: raw HTML,
 * no entity-escaping. Overrides the generic editor_common.js implementation.
 */
function editorGetContent(editor_sequence) {
	var ed = _getDdggInstance(editor_sequence);
	return ed ? ed.getHTML() : '';
}

/* ---- Deprecated legacy shims kept for backward compatibility ---- */

function editorReplaceHTML(iframe_obj, content) {
	var editor_sequence = parseInt(String(iframe_obj.id).replace(/^.*_/, ''), 10);
	var inst = _getCkeInstance(editor_sequence);
	if (inst) {
		inst.insertHtml(content);
	}
}

function editorGetIFrame(editor_sequence) {
	return jQuery('#ddgg_editor_instance_' + editor_sequence).get(0);
}
