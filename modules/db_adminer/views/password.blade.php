<div class="x_page-header">
    <h1>DB Adminer</h1>
</div>

<section class="section">
    <h1>비밀번호 인증<button type="button" class="snToggle x_icon-chevron-up">Toggle this section</button></h1>

    <p class="x_alert x_alert-info">DB Adminer 접근을 위한 비밀번호를 입력하세요.</p>

    <form method="post" action="./">
        @csrf
        <input type="hidden" name="module" value="admin">
        <input type="hidden" name="act" value="procDb_adminerAdminVerifyPassword">

        <div class="x_control-group">
            <label for="access_password" class="x_control-label">비밀번호</label>
            <div class="x_controls">
                <input id="access_password" type="password" name="access_password" required autocomplete="current-password">
                <p class="x_help-block">DB Adminer 관리자 설정에서 지정한 비밀번호입니다.</p>
            </div>
        </div>

        <div class="btnArea x_clearfix">
            <span class="x_btn-group x_pull-right">
                <button type="submit" class="x_btn x_btn-primary">확인</button>
            </span>
        </div>
    </form>
</section>
