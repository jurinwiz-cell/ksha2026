<?php
/**
 * @file    widgets/lavalic_show/tpl/player.php
 * @brief   Lavalic HTML5 플레이어 (audio + YouTube) - 초정밀 유튜브 스펙 버전
 **/


$mid = isset($_GET['mid']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['mid']) : '';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$xml_url  = $protocol . '://' . $host . '/?mid=' . urlencode($mid);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lavalic Player</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

html {
    height: 100%;
    background: #1a1a2e;
}

body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #1a1a2e;
    color: #eee;
    width: 100%;
    height: 100%;
    min-width: 500px;
    min-height: 620px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* ── 상단 플레이어 영역 ── */
#player-area {
    position: relative;
    width: 100%;
    height: 320px; 
    background: #16213e;
    flex: 1; 
    min-height: 240px;
    max-height: 70%; 
}

/* YouTube 컨테이너 및 내부 iframe이 부모 영역을 100% 꽉 채우도록 보정 */
#yt-container {
    width: 100%;
    height: 100%;
    padding: 6px 8px 0; /* 상단 6px, 좌우 8px 여백 지정 */
    box-sizing: border-box;
}
#yt-container iframe, 
#yt-container div {
    width: 100% !important;
    height: 100% !important;
}

#audio-visual {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
}
#audio-visual.hidden { display: none; }

/* 플레이어 자체 진행바 고정 배치 */
#progress-wrap {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    padding: 0 12px 10px;
    z-index: 3;
}

#title-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    max-width: 100%;
    padding: 0 20px;
}

#track-title {
    font-size: 15px;
    font-weight: bold;
    color: #e94560;
    text-align: center;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
#track-artist {
    font-size: 12px;
    color: #aaa;
}

/* 버퍼링 스피너 CSS */
#buffer-spinner {
    width: 14px;
    height: 14px;
    border: 2px solid rgba(233, 69, 96, 0.3);
    border-top: 2px solid #e94560;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    display: none;
    flex-shrink: 0;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* 시각화 바 애니메이션 */
#bars {
    display: flex;
    align-items: flex-end;
    gap: 3px;
    height: 50px;
}
#bars span {
    display: inline-block;
    width: 5px;
    background: #e94560;
    border-radius: 2px;
    animation: bounce 0.8s ease-in-out infinite alternate;
}
#bars span:nth-child(2) { animation-delay: 0.1s;  height: 30px; }
#bars span:nth-child(3) { animation-delay: 0.2s;  height: 45px; }
#bars span:nth-child(4) { animation-delay: 0.3s;  height: 20px; }
#bars span:nth-child(5) { animation-delay: 0.4s;  height: 40px; }
#bars span:nth-child(6) { animation-delay: 0.15s; height: 35px; }
#bars span:nth-child(7) { animation-delay: 0.25s; height: 50px; }
#bars span:nth-child(8) { animation-delay: 0.05s; height: 25px; }
@keyframes bounce {
    from { transform: scaleY(0.3); opacity: 0.6; }
    to   { transform: scaleY(1);   opacity: 1; }
}
#bars.paused span { animation-play-state: paused; }

/* 진행바 */
#progress-bg {
    width: 100%;
    height: 4px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 2px;
    cursor: pointer;
    position: relative;
    transition: height 0.1s ease;
}
#progress-bg:hover,
#progress-bg.dragging {
    height: 6px;
}

#progress-fill {
    height: 100%;
    width: 0%;
    background: #e94560;
    border-radius: 2px;
    position: absolute;
    top: 0;
    left: 0;
}

#progress-handle {
    position: absolute;
    top: 50%;
    left: 0%;
    width: 12px;
    height: 12px;
    background: #e94560;
    border-radius: 50%;
    transform: translate(-50%, -50%) scale(0);
    cursor: pointer;
    transition: transform 0.1s ease;
    box-shadow: 0 0 4px rgba(0,0,0,0.5);
}

#progress-bg:hover #progress-handle,
#progress-bg.dragging #progress-handle {
    transform: translate(-50%, -50%) scale(1.2);
}

#time-info {
    display: flex;
    justify-content: space-between;
    font-size: 10px;
    color: #888;
    margin-top: 5px;
}

/* ── 컨트롤 영역 ── */
#controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    padding: 10px 0 8px;
    background: #16213e;
    flex-shrink: 0;
}
.ctrl-btn {
    background: none;
    border: none;
    color: #ccc;
    cursor: pointer;
    font-size: 18px;
    padding: 4px;
    transition: color 0.2s;
    line-height: 1;
}
.ctrl-btn:hover { color: #e94560; }
.ctrl-btn.active { color: #e94560; }
#btn-play { font-size: 28px; color: #e94560; }
#btn-play:hover { color: #ff6b8a; }

.toggle-btn {
    position: relative;
    opacity: 0.55;
    transition: opacity 0.2s, background 0.2s;
}
.toggle-btn:hover { opacity: 0.85; }
.toggle-btn.active {
    opacity: 1;
    background: rgba(233,69,96,0.18);
    border-radius: 6px;
}
.toggle-btn.active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 50%;
    transform: translateX(-50%);
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #e94560;
}
.repeat-badge {
    position: absolute;
    top: -3px;
    right: -9px;
    min-width: 14px;
    height: 12px;
    padding: 0 3px;
    font-size: 7px;
    font-weight: bold;
    line-height: 12px;
    text-align: center;
    white-space: nowrap;
    color: #fff;
    background: #e94560;
    border-radius: 6px;
    display: none;
}
.repeat-badge.show { display: block; }

#volume-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-left: 8px;
}
#volume-wrap span { font-size: 14px; }
#vol-slider {
    -webkit-appearance: none;
    width: 70px;
    height: 3px;
    background: #555;
    border-radius: 2px;
    outline: none;
    cursor: pointer;
}
#vol-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #e94560;
    cursor: pointer;
}

/* ── 플레이리스트 ── */
#playlist {
    flex: 1;
    overflow-y: auto;
    background: #0f3460;
}
#playlist::-webkit-scrollbar { width: 4px; }
#playlist::-webkit-scrollbar-thumb { background: #e94560; border-radius: 2px; }

.pl-item {
    display: flex;
    align-items: center;
    padding: 7px 12px;
    cursor: pointer;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    transition: background 0.15s;
    gap: 8px;
}
.pl-item:hover { background: rgba(233,69,96,0.15); }
.pl-item.active { background: rgba(233,69,96,0.25); }
.pl-num {
    font-size: 11px;
    color: #888;
    width: 20px;
    flex-shrink: 0;
    text-align: right;
}
.pl-item.active .pl-num { color: #e94560; }
.pl-type {
    font-size: 10px;
    width: 20px;
    text-align: center;
    flex-shrink: 0;
}
.pl-info { flex: 1; overflow: hidden; }
.pl-title {
    font-size: 12px;
    color: #ddd;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pl-item.active .pl-title { color: #e94560; font-weight: bold; }
.pl-artist { font-size: 10px; color: #888; }

#status-bar {
    padding: 4px 12px;
    font-size: 10px;
    color: #666;
    background: #1a1a2e;
    flex-shrink: 0;
}

/* 별표 버튼 스타일 */
.pl-star {
    font-size: 14px;
    color: #ccc;
    cursor: pointer;
    width: 24px;
    text-align: center;
    flex-shrink: 0;
    transition: color 0.15s, transform 0.1s;
}
.pl-star:hover {
    transform: scale(1.2);
    color: #ffd700;
}
.pl-star.active {
    color: #ffd700;
}

/* 컨트롤 영역 선택 모드 버튼 스타일 */
#btn-fav-mode {
    font-size: 16px;
}
#btn-fav-mode.active {
    color: #ffd700;
    background: rgba(255, 215, 0, 0.15);
    border-radius: 6px;
}

@media (max-width: 500px) {
    body {
        min-width: 0;
        min-height: 100vh;
    }
    #player-area {
        height: auto;
        aspect-ratio: 16 / 9;
    }
    #controls {
        gap: 8px;
    }
    #volume-wrap {
        gap: 4px;
        margin-left: 4px;
    }
    #vol-slider {
        width: 50px;
    }
}
</style>
</head>

<body>

<div id="player-area">
    <div id="yt-container" style="display:none; width:100%; height:100%;"></div>
    <div id="audio-visual">
        <div id="title-container">
            <div id="buffer-spinner" title="버퍼링 중..."></div>
            <div id="track-title">Loading...</div>
        </div>
        <div id="track-artist"></div>
        <div id="bars" class="paused">
            <span style="height:20px"></span><span></span><span></span>
            <span></span><span></span><span></span><span></span><span></span>
        </div>
    </div>
    <div id="progress-wrap">
        <div id="progress-bg">
            <div id="progress-fill"></div>
            <div id="progress-handle"></div>
        </div>
        <div id="time-info">
            <span id="time-cur">0:00</span>
            <span id="time-dur">0:00</span>
        </div>
    </div>
</div>

<div id="controls">
    <button class="ctrl-btn toggle-btn" id="btn-fav-mode" title="Play Selected Only">☆</button>
    <button class="ctrl-btn toggle-btn" id="btn-shuffle" title="Shuffle">🔀</button>
    <button class="ctrl-btn" id="btn-prev" title="Previous">⏮</button>
    <button class="ctrl-btn" id="btn-play" title="Play">▶</button>
    <button class="ctrl-btn" id="btn-next" title="Next">⏭</button>
    <button class="ctrl-btn toggle-btn" id="btn-repeat" title="Repeat">🔁<span class="repeat-badge" id="repeat-badge"></span></button>
    <div id="volume-wrap">
        <span>🔊</span>
        <input type="range" id="vol-slider" min="0" max="100" step="1" value="80">
    </div>
</div>

<div id="playlist"></div>
<div id="status-bar">Loading playlist...</div>

<audio id="audio-el" preload="none"></audio>

<script>
// ── YouTube IFrame API ──
var ytPlayer      = null;
var ytReady       = false;
var ytApiLoaded   = false;
var ytPendingLoad = null;

function loadYouTubeAPI() {
    if (ytApiLoaded) return;
    ytApiLoaded = true;
    var tag = document.createElement('script');
    tag.src = 'https://www.youtube.com/iframe_api';
    document.head.appendChild(tag);
}

window.onYouTubeIframeAPIReady = function() {
    ytReady = true;
    if (ytPendingLoad) {
        createYTPlayer(ytPendingLoad.vid, ytPendingLoad.autoPlay, ytPendingLoad.vol);
        ytPendingLoad = null;
    }
};

// ── 유틸 ──
function getYouTubeId(url) {
    var m = url.match(/(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_\-]{11})/);
    return m ? m[1] : null;
}

function isYouTube(url) {
    return /youtube\.com|youtu\.be/.test(url);
}

function formatTime(sec) {
    sec = Math.floor(sec || 0);
    var m = Math.floor(sec / 60);
    var s = sec % 60;
    return m + ':' + (s < 10 ? '0' : '') + s;
}

function getVolume() {
    return parseInt(document.getElementById('vol-slider').value, 10);
}

// ── 상태 ──
var playlist    = [];
var currentIdx  = 0;
var isPlaying   = false;
var isShuffle   = false;
var REPEAT_OFF  = 0;
var REPEAT_ALL  = 1;
var REPEAT_ONE  = 2;
var repeatMode  = REPEAT_OFF;
var settings    = {};
var currentMode = '';

// 즐겨찾기(선택) 목록 및 모드 관리
var favorites   = JSON.parse(localStorage.getItem('lavalic_favs') || '[]'); 
var isFavMode   = false; 

var audioEl       = document.getElementById('audio-el');
var ytContainer   = document.getElementById('yt-container');
var audioVisual   = document.getElementById('audio-visual');
var bars          = document.getElementById('bars');
var trackTitle   = document.getElementById('track-title');
var trackArtist  = document.getElementById('track-artist');
var progressWrap = document.getElementById('progress-wrap');
var progressBg   = document.getElementById('progress-bg');
var progressFill = document.getElementById('progress-fill');
var progressHandle = document.getElementById('progress-handle');
var timeCur      = document.getElementById('time-cur');
var timeDur      = document.getElementById('time-dur');
var plContainer  = document.getElementById('playlist');
var statusBar    = document.getElementById('status-bar');
var btnPlay      = document.getElementById('btn-play');
var btnRepeat    = document.getElementById('btn-repeat');
var repeatBadge  = document.getElementById('repeat-badge');
var volSlider    = document.getElementById('vol-slider');
var spinner      = document.getElementById('buffer-spinner');
var btnFavMode    = document.getElementById('btn-fav-mode');

window.isProgressDragging = false;

// ── XML 로드 ──
var xmlUrl = <?php echo json_encode($xml_url); ?>;

function loadPlaylist() {
    fetch(xmlUrl)
        .then(function(r) { return r.text(); })
        .then(function(text) {
            var parser = new DOMParser();
            var xml    = parser.parseFromString(text, 'text/xml');

            var s = xml.querySelector('settings');
            if (s) {
                settings.autoPlay   = (s.querySelector('first_auto_play')   || {}).textContent === 'Y';
                settings.randomList = (s.querySelector('first_random_list') || {}).textContent === 'Y';
                settings.randomPlay = (s.querySelector('first_random_play') || {}).textContent === 'Y';
                document.title = (s.querySelector('browser_title') || {}).textContent || 'Lavalic';

                var fontSizeRaw = (s.querySelector('font_size') || {}).textContent || '';
                var fontSizeNum = parseFloat(fontSizeRaw);
                if (!isNaN(fontSizeNum) && fontSizeNum > 0) {
                    document.documentElement.style.setProperty('--lv-font-base', fontSizeNum + 'px');
                }
            }

            var items = xml.querySelectorAll('music');
            playlist = [];
            items.forEach(function(item) {
                var src = (item.querySelector('src') || {}).textContent || '';
                if (!src.trim()) return;
                playlist.push({
                    srl    : item.getAttribute('srl'),
                    title  : (item.querySelector('title')  || {}).textContent || '(제목 없음)',
                    artist : (item.querySelector('artist') || {}).textContent || '',
                    src    : src.trim(),
                    lyric  : (item.querySelector('lyric')  || {}).textContent || ''
                });
            });

            if (playlist.length === 0) {
                statusBar.textContent = 'No tracks found in the playlist.';
                return;
            }

            if (settings.randomList) shufflePlaylist();
            isShuffle = settings.randomPlay;
            document.getElementById('btn-shuffle').classList.toggle('active', isShuffle);

            renderPlaylist();
            loadYouTubeAPI();

            // 처음 시작할 때 선택 모드가 켜져 있다면, 즐겨찾기된 첫 번째 곡의 인덱스를 찾습니다.
            var startIdx = 0;
            if (isFavMode) {
                var firstFav = playlist.findIndex(function(t) { return favorites.includes(t.src); });
                if (firstFav > -1) startIdx = firstFav;
            }

            loadTrack(startIdx, settings.autoPlay); 
            statusBar.textContent = playlist.length + 'tracks loaded successfully';
        })
        .catch(function(e) {
            statusBar.textContent = 'Failed to load playlist: ' + e.message;
        });
}

function shufflePlaylist() {
    for (var i = playlist.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var tmp = playlist[i]; playlist[i] = playlist[j]; playlist[j] = tmp;
    }
}

// ── 플레이리스트 렌더 ──
function renderPlaylist() {
    plContainer.innerHTML = '';
    playlist.forEach(function(track, idx) {
        var isFav = favorites.includes(track.src);
        var starChar = isFav ? '★' : '☆';
        var starClass = isFav ? 'pl-star active' : 'pl-star';

        var div = document.createElement('div');
        div.className = 'pl-item' + (idx === currentIdx ? ' active' : '');
        
        div.innerHTML =
            '<span class="' + starClass + '" data-idx="' + idx + '">' + starChar + '</span>' +
            '<span class="pl-num">' + (idx + 1) + '</span>' +
            '<span class="pl-type">' + (isYouTube(track.src) ? '▶' : '♪') + '</span>' +
            '<div class="pl-info">' +
                '<div class="pl-title">' + escHtml(track.title) + '</div>' +
                '<div class="pl-artist">' + escHtml(track.artist) + '</div>' +
            '</div>';
            
        div.addEventListener('click', function(e) { 
            loadTrack(idx, true); 
        });
        
        div.querySelector('.pl-star').addEventListener('click', function(e) {
            e.stopPropagation();
            toggleFavorite(idx);
        });

        plContainer.appendChild(div);
    });
}

function toggleFavorite(idx) {
    var track = playlist[idx];
    var favIdx = favorites.indexOf(track.src);
    
    if (favIdx > -1) {
        favorites.splice(favIdx, 1);
    } else {
        favorites.push(track.src);
    }
    
    localStorage.setItem('lavalic_favs', JSON.stringify(favorites));
    renderPlaylist();

	if (isFavMode && favIdx > -1 && idx === currentIdx) {
        var nextFav = playlist.findIndex(function(t) { return favorites.includes(t.src); });
        if (nextFav > -1) {
            loadTrack(nextFav, isPlaying);
        }
    }
}

// 다음 트랙 인덱스 계산 (선택 모드 및 셔플 연동)
function getNextIdx() {
    if (playlist.length === 0) return 0;
    if (isShuffle) return getRandomFavIdx();
    
    var next = currentIdx;
    for (var i = 0; i < playlist.length; i++) {
        next = (next + 1) % playlist.length;
        if (!isFavMode || favorites.includes(playlist[next].src)) {
            return next;
        }
    }
    return 0;
}

// 이전 트랙 인덱스 계산 (선택 모드 및 셔플 연동)
function getPrevIdx() {
    if (playlist.length === 0) return 0;
    if (isShuffle) return getRandomFavIdx();
    
    var prev = currentIdx;
    for (var i = 0; i < playlist.length; i++) {
        prev = (prev - 1 + playlist.length) % playlist.length;
        if (!isFavMode || favorites.includes(playlist[prev].src)) {
            return prev;
        }
    }
    return 0;
}

// 랜덤 트랙 인덱스 계산 (선택 모드 반영)
function getRandomFavIdx() {
    var validIndices = [];
    playlist.forEach(function(track, idx) {
        if (!isFavMode || favorites.includes(track.src)) {
            if (idx !== currentIdx) validIndices.push(idx);
        }
    });
    
    if (validIndices.length === 0) return currentIdx;
    return validIndices[Math.floor(Math.random() * validIndices.length)];
}

function updatePlaylistActive() {
    var items = plContainer.querySelectorAll('.pl-item');
    items.forEach(function(el, idx) {
        el.classList.toggle('active', idx === currentIdx);
    });
    if (items[currentIdx]) items[currentIdx].scrollIntoView({ block: 'nearest' });
}

// HTML 엔티티 이스케이프
function escHtml(str) {
    return (str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── 트랙 로드 ──
function loadTrack(idx, autoPlay) {
    currentIdx = idx;
    var track  = playlist[idx];

    trackTitle.textContent   = track.title;
    trackArtist.textContent  = track.artist;
    progressFill.style.width = '0%';
    if (progressHandle) progressHandle.style.left = '0%';
    timeCur.textContent = '0:00';
    timeDur.textContent = '0:00';
    spinner.style.display = 'none';

    updatePlaylistActive();
    statusBar.textContent = (idx + 1) + ' / ' + playlist.length + ' — ' + track.title;

    if (isYouTube(track.src)) {
        currentMode = 'youtube';
        progressWrap.style.display = 'none'; // [수정] 유튜브 모드일 때 자체 재생바 완전히 숨김
        playYouTube(track.src, autoPlay);
    } else {
        currentMode = 'audio';
        progressWrap.style.display = 'block'; // [수정] 오디오(MP3) 모드일 때 자체 재생바 노출
        playAudio(track.src, autoPlay);
    }
}

// ── Audio 재생 및 버퍼링 핸들링 ──
function playAudio(src, autoPlay) {
    destroyYTPlayer();
    ytContainer.style.display = 'none';
    audioVisual.classList.remove('hidden');

    audioEl.src    = src;
    audioEl.volume = getVolume() / 100;

    if (autoPlay) {
        audioEl.play()
            .then(function() { setPlayingState(true); })
            .catch(function() { setPlayingState(false); });
    } else {
        setPlayingState(false);
    }
}

audioEl.addEventListener('waiting', function() {
    if (currentMode === 'audio') spinner.style.display = 'inline-block';
});

audioEl.addEventListener('playing', function() {
    spinner.style.display = 'none';
});

audioEl.addEventListener('timeupdate', function() {
    if (!audioEl.duration || window.isProgressDragging || currentMode !== 'audio') return; // [수정] 오디오 모드가 아닐 때 타임 업데이트 차단
    var percent = (audioEl.currentTime / audioEl.duration * 100); 
    progressFill.style.width = percent + '%'; 
    if (progressHandle) progressHandle.style.left = percent + '%';
    timeCur.textContent = formatTime(audioEl.currentTime);
    timeDur.textContent = formatTime(audioEl.duration);
});

audioEl.addEventListener('ended', function() { onTrackEnd(); });

// ── 실시간 탐색 연동 시스템 ──
function seekToPosition(clientX, isFinal) {
    var rect = progressBg.getBoundingClientRect();
    var clickX = clientX - rect.left;
    var ratio = clickX / rect.width;
    
    ratio = Math.min(Math.max(ratio, 0), 1);
    ratio = Math.round(ratio * 10000) / 10000;

    var percent = (ratio * 100) + '%';
    progressFill.style.width = percent;
    if (progressHandle) progressHandle.style.left = percent;

    if (currentMode === 'youtube') {
        if (!ytPlayer || typeof ytPlayer.seekTo !== 'function') return;
        var ytDur = ytPlayer.getDuration();
        if (ytDur) {
            ytPlayer.seekTo(ratio * ytDur, isFinal);
            timeCur.textContent = formatTime(ratio * ytDur);
        }
    } else {
        if (!audioEl.duration) return;
        audioEl.currentTime = ratio * audioEl.duration;
        timeCur.textContent = formatTime(ratio * audioEl.duration);
    }
}

function endDrag(clientX) {
    if (!window.isProgressDragging) return;
    window.isProgressDragging = false;
    progressBg.classList.remove('dragging');
    
    if (clientX !== undefined) {
        seekToPosition(clientX, true);
    }
}

progressBg.addEventListener('mousedown', function(e) {
    if (currentMode === 'youtube') return; // [수정] 유튜브 모드일 때 마우스 다운 차단
    window.isProgressDragging = true;
    progressBg.classList.add('dragging');
    seekToPosition(e.clientX, false);
});

document.addEventListener('mousemove', function(e) {
    if (!window.isProgressDragging || currentMode === 'youtube') return; // [수정] 유튜브 모드일 때 마우스 무브 차단
    seekToPosition(e.clientX, false);
});

document.addEventListener('mouseup', function(e) {
    if (!window.isProgressDragging) return;
    endDrag(e.clientX);
});

progressBg.addEventListener('touchstart', function(e) {
    if (currentMode === 'youtube') return; // [수정] 유튜브 모드일 때 터치 스타트 차단
    window.isProgressDragging = true;
    progressBg.classList.add('dragging');
    seekToPosition(e.touches[0].clientX, false);
}, {passive: true});

document.addEventListener('touchmove', function(e) {
    if (!window.isProgressDragging || currentMode === 'youtube') return; // [수정] 유튜브 모드일 때 터치 무브 차단
    if (e.cancelable) e.preventDefault(); 
    seekToPosition(e.touches[0].clientX, false);
}, {passive: false});

document.addEventListener('touchend', function(e) {
    if (!window.isProgressDragging) return;
    if (e.changedTouches && e.changedTouches[0]) {
        endDrag(e.changedTouches[0].clientX);
    } else {
        endDrag();
    }
});

// ── 볼륨 슬라이더 연동 ──
volSlider.addEventListener('input', function() {
    var vol = getVolume();
    audioEl.volume = vol / 100;
    if (ytPlayer && typeof ytPlayer.setVolume === 'function') {
        ytPlayer.setVolume(vol);
    }
});

// ── YouTube 재생 및 상태 모니터링 ──
function playYouTube(url, autoPlay) {
    audioEl.pause();
    audioEl.src = '';
    audioVisual.classList.add('hidden');
    spinner.style.display = 'none';

    ytContainer.innerHTML = '<div id="yt-player"></div>';
    ytContainer.style.display = 'block';

    var vid = getYouTubeId(url);
    var vol = getVolume();

    if (ytReady) {
        createYTPlayer(vid, autoPlay, vol);
    } else {
        ytPendingLoad = { vid: vid, autoPlay: autoPlay, vol: vol };
    }
}

function createYTPlayer(vid, autoPlay, vol) {
    destroyYTPlayer();

    ytPlayer = new YT.Player('yt-player', {
        width  : '100%',
        height : '100%', 
        videoId: vid,
        playerVars: {
            autoplay      : autoPlay ? 1 : 0,
            controls      : 1,
            rel           : 0,
            modestbranding: 1,
            fs            : 0
        },
        events: {
            onReady: function(e) {
                e.target.setVolume(vol);
                if (autoPlay) {
                    e.target.playVideo();
                }
                setPlayingState(autoPlay);
            },
            onStateChange: function(e) {
                if (e.data === YT.PlayerState.BUFFERING) {
                    statusBar.textContent = "유튜브 데이터를 가져오는 중...";
                }
                if (e.data === YT.PlayerState.ENDED)   { onTrackEnd(); }
                if (e.data === YT.PlayerState.PLAYING) { setPlayingState(true); }
                if (e.data === YT.PlayerState.PAUSED)  { setPlayingState(false); }
            }
        }
    });
}

function destroyYTPlayer() {
    if (ytPlayer) {
        try { ytPlayer.destroy(); } catch(e) {}
        ytPlayer = null;
    }
}

// 유튜브 상태 시간 트래킹 최적화
setInterval(function() {
    if (currentMode !== 'youtube' || !ytPlayer || typeof ytPlayer.getCurrentTime !== 'function' || window.isProgressDragging) return;
    var cur = ytPlayer.getCurrentTime() || 0;
    var dur = ytPlayer.getDuration() || 0;
    if (dur > 0) {
        var percent = (cur / dur * 100); 
        progressFill.style.width = percent + '%'; 
        if (progressHandle) progressHandle.style.left = percent + '%';
        timeCur.textContent = formatTime(cur);
        timeDur.textContent = formatTime(dur);
    }
}, 250);

// ── 재생 상태 제어 ──
function setPlayingState(playing) {
    isPlaying = playing;
    btnPlay.textContent = playing ? '⏸' : '▶';
    bars.classList.toggle('paused', !playing);
}

function onTrackEnd() {
    setPlayingState(false);

    if (repeatMode === REPEAT_ONE) {
        loadTrack(currentIdx, true);
        return;
    }
    
    var next = getNextIdx();
    
    if (next > currentIdx) {
        loadTrack(next, true);
    } else if (repeatMode === REPEAT_ALL) {
        loadTrack(next, true);
    } else {
        loadTrack(next, false);
    }
}

// ── 버튼 이벤트 리스너 통합 정리 (중복 제거 완료 및 선택재생 함수 정상 맵핑) ──
btnPlay.addEventListener('click', function() {
    if (!playlist.length) return;

    if (currentMode === 'youtube') {
        if (!ytPlayer || typeof ytPlayer.getPlayerState !== 'function') return;
        var state = ytPlayer.getPlayerState();
        if (state === YT.PlayerState.PLAYING) {
            ytPlayer.pauseVideo();
        } else {
            ytPlayer.playVideo();
        }
    } else {
        if (isPlaying) {
            audioEl.pause();
            setPlayingState(false);
        } else {
            audioEl.play()
                .then(function() { setPlayingState(true); })
                .catch(function() {});
        }
    }
});

document.getElementById('btn-prev').addEventListener('click', function() {
    if (!playlist.length) return;
    loadTrack(getPrevIdx(), true);
});

document.getElementById('btn-next').addEventListener('click', function() {
    if (!playlist.length) return;
    loadTrack(getNextIdx(), true);
});

document.getElementById('btn-repeat').addEventListener('click', function() {
    repeatMode = (repeatMode + 1) % 3;
    updateRepeatButton();
});

document.getElementById('btn-shuffle').addEventListener('click', function() {
    isShuffle = !isShuffle;
    this.classList.toggle('active', isShuffle);
});

// 선택 모드 토글 버튼 이벤트 [실시간 자동 강제 로드 로직 추가]
btnFavMode.addEventListener('click', function() {
    isFavMode = !isFavMode;
    this.classList.toggle('active', isFavMode);
    this.textContent = isFavMode ? '★' : '☆';
    this.title = isFavMode ? "Favorite Mode On" : "Favorite Mode Off";
    
    if (isFavMode) {
        statusBar.textContent = "Selected tracks playback mode activated.";
        
        // ── [★ 이번에 추가된 핵심 로직 ★] ──
        // 선택 모드를 활성화했을 때, 현재 걸려있는 곡이 선택곡(favorites)에 포함되지 않는다면
        // 전체 목록을 다 뒤져서 즐겨찾기에 포함된 첫 번째 곡을 찾아 즉시 대기(Load)시킵니다.
        var currentTrack = playlist[currentIdx];
        if (!currentTrack || !favorites.includes(currentTrack.src)) {
            var firstFavIdx = playlist.findIndex(function(t) { return favorites.includes(t.src); });
            if (firstFavIdx > -1) {
                // 두 번째 인자를 false로 주어, 화면과 오디오 소스만 첫 선택곡으로 바꾸고 
                // 사용자가 ▶ 버튼을 누를 때까지 재생 대기 상태를 유지합니다.
                loadTrack(firstFavIdx, false); 
            }
        }
    } else {
        statusBar.textContent = "Returned to standard playback mode.";
    }
});

function updateRepeatButton() {
    btnRepeat.classList.toggle('active', repeatMode !== REPEAT_OFF);
    if (repeatMode === REPEAT_ONE) {
        repeatBadge.textContent = '1';
        repeatBadge.classList.add('show');
        btnRepeat.title = 'Repeat: One Track';
    } else if (repeatMode === REPEAT_ALL) {
        repeatBadge.textContent = 'ALL';
        repeatBadge.classList.add('show');
        btnRepeat.title = 'Repeat: All Tracks';
    } else {
        repeatBadge.classList.remove('show');
        btnRepeat.title = 'Repeat: Off';
    }
}

// ── 시작 ──
// 처음 켤 때는 항상 전체 곡 재생 모드(디폴트)로 시작합니다.
isFavMode = false;
btnFavMode.classList.remove('active');
btnFavMode.textContent = '☆';
btnFavMode.title = "Favorite Mode Off";

loadPlaylist();
</script>
</body>
</html>