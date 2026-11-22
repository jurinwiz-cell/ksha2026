use crate::commands::{CommandError, traits::Command};
use rpdf_core::types::document::Document;

/// 스택 높이 동기화용 no-op 커맨드.
///
/// `replace_text`처럼 `CommandStack`을 우회하는 편집 후
/// sentinel로 사용해 `CommandStack`과 `sources_undo` 높이를 일치시킨다.
pub struct NoOpCommand;

impl Command for NoOpCommand {
    fn execute(&self, _doc: &mut Document) -> Result<(), CommandError> {
        Ok(())
    }

    fn undo(&self, _doc: &mut Document) -> Result<(), CommandError> {
        Ok(())
    }

    fn name(&self) -> &'static str {
        "NoOp"
    }
}
