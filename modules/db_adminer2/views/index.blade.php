<div class="x_page-header">
    <h1>DB Adminer</h1>
</div>

<section class="section">
    <h1>Adminer<button type="button" class="snToggle x_icon-chevron-up">Toggle this section</button></h1>

    <p>아래 버튼을 클릭하면 새 탭에서 Adminer가 열립니다.</p>
    <a href="{{ $launcher_url }}" target="_blank" rel="noopener noreferrer" class="x_btn x_btn-primary">Adminer 열기</a>
</section>

<form method="post" action="./" class="x_form-horizontal">
    @csrf
    <input type="hidden" name="module" value="admin">
    <input type="hidden" name="act" value="procDb_adminerAdminSaveConfig">

    <section class="section">
        <h1>설정<button type="button" class="snToggle x_icon-chevron-up">Toggle this section</button></h1>

        @if ($access_password_set)
        <div class="x_control-group">
            <label class="x_control-label">비밀번호 변경</label>
            <div class="x_controls">
                <label class="x_inline">
                    <input id="change_password_toggle" type="checkbox" name="change_password" value="Y">
                    비밀번호 변경
                </label>
                <p class="x_help-block">현재 비밀번호가 설정되어 있습니다. 변경하거나 삭제하려면 체크하세요.</p>
            </div>
        </div>
        <div class="x_control-group">
            <label for="access_password" class="x_control-label">새 비밀번호</label>
            <div class="x_controls">
                <input id="access_password" type="password" name="access_password" autocomplete="new-password" disabled>
                <p class="x_help-block">새 비밀번호를 입력하세요. 비워두면 비밀번호가 제거됩니다.</p>
            </div>
        </div>
        @else
        <div class="x_control-group">
            <label for="access_password" class="x_control-label">비밀번호</label>
            <div class="x_controls">
                <input id="access_password" type="password" name="access_password" autocomplete="new-password">
                <p class="x_help-block">DB Adminer 접근 시 요구할 비밀번호입니다. DB 비밀번호와는 별개입니다. 비워두면 비밀번호 없이 접근합니다.</p>
            </div>
        </div>
        @endif

        <div class="x_control-group">
            <label for="session_ttl" class="x_control-label">세션 유지 기간</label>
            <div class="x_controls">
                <input id="session_ttl" type="number" name="session_ttl" min="0" value="{{ $session_ttl }}">
                <p class="x_help-block">Adminer 접속 후 세션을 유지할 시간(초)입니다. 0을 입력하면 브라우저 세션이 유지되는 한 계속 유효합니다.</p>
            </div>
        </div>
    </section>

    <div class="btnArea x_clearfix">
        <span class="x_btn-group x_pull-right">
            <button type="submit" class="x_btn x_btn-primary">저장</button>
        </span>
    </div>
</form>

<script>
@if ($access_password_set)
(function () {
    var toggle = document.getElementById('change_password_toggle');
    var input = document.getElementById('access_password');
    if (toggle && input) {
        toggle.addEventListener('change', function () {
            input.disabled = !this.checked;
            if (!this.checked) {
                input.value = '';
            }
        });
    }
}());
@endif
</script>

