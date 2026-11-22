use std::sync::Mutex;

use rpdf_core::types::document::Document;
use rpdf_edit::commands::{Command, CommandStack};
use rpdf_serializer::PageSource;

/// 현재 열린 PDF 문서의 편집 상태.
///
/// `doc`과 `sources`는 항상 같은 페이지 수를 유지해야 한다.
/// `stack`의 undo 깊이와 `sources_undo` 높이도 항상 동일하게 유지한다.
pub struct PdfState {
    pub doc: Document,
    pub sources: Vec<PageSource>,
    pub stack: CommandStack,
    pub sources_undo: Vec<Vec<PageSource>>,
    pub sources_redo: Vec<Vec<PageSource>>,
}

const MAX_UNDO_DEPTH: usize = 100;

impl PdfState {
    /// 새 `PdfState`를 생성한다.
    ///
    /// `doc`과 `sources`는 `load_document_tracked`의 결과여야 한다.
    pub fn new(doc: Document, sources: Vec<PageSource>) -> Self {
        Self {
            doc,
            sources,
            stack: CommandStack::new(MAX_UNDO_DEPTH),
            sources_undo: Vec::new(),
            sources_redo: Vec::new(),
        }
    }

    /// 커맨드를 실행하고 sources 스냅샷을 push한다.
    ///
    /// sources 변경 여부와 무관하게 항상 `sources_undo`에 push해
    /// `CommandStack`과 높이 일치를 보장한다.
    pub fn execute_cmd(
        &mut self,
        cmd: Box<dyn Command>,
        new_sources: Option<Vec<PageSource>>,
    ) -> Result<(), String> {
        self.stack
            .execute(cmd, &mut self.doc)
            .map_err(|e| e.to_string())?;
        self.sources_undo.push(self.sources.clone()); // 항상 push — CommandStack과 높이 일치 보장
        self.sources_redo.clear();
        if let Some(s) = new_sources {
            self.sources = s;
        }
        Ok(())
    }
}

/// Tauri 앱 전역 상태.
///
/// `pdf`는 파일이 열리지 않은 경우 `None`이다.
#[derive(Default)]
pub struct AppState {
    pub pdf: Mutex<Option<PdfState>>,
}
