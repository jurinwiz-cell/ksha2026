var lavalicWnd = null;

function isLavalicOpened() {
    if (!lavalicWnd || lavalicWnd.closed) return false;
    return true;
}

function lavalicOpen(mid, base_url) {
    var target_url = (base_url || "./") + "widgets/lavalic_show/tpl/player.php?mid=" + encodeURIComponent(mid);

    // 사용자가 창 크기를 늘리거나 줄일 수 있도록 resizable=1로 변경 (두 곳 모두 적용)
    if (!isLavalicOpened()) {
        lavalicWnd = window.open(target_url, "lavalicWnd", "width=470,height=565,toolbar=0,directories=0,status=0,menubar=0,scrollbars=0,resizable=1");
        if (lavalicWnd && !lavalicWnd.opener) lavalicWnd.opener = self;
    } else {
        try {
            lavalicWnd.location.href = target_url;
        } catch(e) {
            // 접근 불가 시 새로 열기
            lavalicWnd = window.open(target_url, "lavalicWnd", "width=470,height=565,toolbar=0,directories=0,status=0,menubar=0,scrollbars=0,resizable=1");
        }
        lavalicWnd.focus();
    }
}