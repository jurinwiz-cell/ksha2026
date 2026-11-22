mod commands;
mod state;

use state::AppState;
use tauri::Manager;
use tauri_plugin_window_state::{StateFlags, WindowExt};

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_shell::init())
        .plugin(tauri_plugin_dialog::init())
        .plugin(tauri_plugin_fs::init())
        .plugin(
            tauri_plugin_window_state::Builder::default()
                .with_state_flags(StateFlags::SIZE | StateFlags::POSITION)
                .build(),
        )
        .setup(|app| {
            // 저장된 창 상태 복원 — 첫 실행 시 silently no-ops
            if let Some(w) = app.get_webview_window("main") {
                w.restore_state(StateFlags::SIZE | StateFlags::POSITION)
                    .ok();
            }
            Ok(())
        })
        .manage(AppState::default())
        .invoke_handler(tauri::generate_handler![
            commands::open_pdf,
            commands::save_pdf,
            commands::rotate_page,
            commands::delete_pages,
            commands::undo,
            commands::redo,
            commands::get_pages,
            commands::get_text_blocks,
            commands::replace_text,
            commands::tiff_to_pdf,
        ])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
