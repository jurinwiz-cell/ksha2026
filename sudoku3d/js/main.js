/**
 * 3D Sudoku - UI Controller & Main Entry Point
 * Wires together 3D Viewport, Game State, Audio, and UI elements.
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize 3D Viewport
    const container = document.getElementById('viewport-container');
    const view3d = new Sudoku3DView(container);
    window.view3d = view3d;

    const game = window.sudokuGame;
    const sound = window.soundEngine;

    // Toast notification helper
    const showToast = (message, duration = 2500) => {
        const toast = document.getElementById('toast');
        if (!toast) return;
        toast.textContent = message;
        toast.classList.add('show');
        clearTimeout(toast._timer);
        toast._timer = setTimeout(() => {
            toast.classList.remove('show');
        }, duration);
    };

    // 2. Setup Game Event Listeners
    view3d.onCellSelect = (cellIndex) => {
        game.selectCell(cellIndex);
    };

    game.onStateChange = (gameState, fullUpdate) => {
        // Update timer
        const timerElem = document.getElementById('timer-display');
        if (timerElem) timerElem.textContent = game.formatTime();

        const mistakesElem = document.getElementById('mistakes-count');
        if (mistakesElem) mistakesElem.textContent = game.mistakes;

        if (!fullUpdate) return;

        // Update 3D visual representations
        view3d.updateCells(gameState);

        // Update Undo / Redo button states
        const btnUndo = document.getElementById('btn-undo');
        if (btnUndo) btnUndo.disabled = game.history.length === 0;

        const btnRedo = document.getElementById('btn-redo');
        if (btnRedo) btnRedo.disabled = game.redoStack.length === 0;

        // Update Note Mode button UI
        const btnNote = document.getElementById('btn-note');
        if (btnNote) {
            if (game.isNoteMode) {
                btnNote.classList.add('active');
            } else {
                btnNote.classList.remove('active');
            }
        }

        // Update Numpad count indicators
        const counts = game.getNumberCounts();
        const maxNum = (game.mode === 'hyper4') ? 4 : 9;
        const totalTarget = (game.mode === 'dionis') ? 3 : (game.mode === 'surface' ? 6 : 16);

        for (let n = 1; n <= 9; n++) {
            const btn = document.getElementById(`numpad-${n}`);
            const countBadge = document.getElementById(`count-${n}`);
            if (btn) {
                if (n > maxNum) {
                    btn.style.display = 'none';
                } else {
                    btn.style.display = 'flex';
                    const used = counts[n] || 0;
                    if (countBadge) countBadge.textContent = `${used}/${totalTarget}`;
                    if (used >= totalTarget) {
                        btn.classList.add('completed');
                    } else {
                        btn.classList.remove('completed');
                    }
                }
            }
        }
    };

    // 3. Victory Handler
    game.onVictory = (result) => {
        // Fire confetti
        if (window.confetti) {
            window.confetti({
                particleCount: 150,
                spread: 80,
                origin: { y: 0.6 }
            });
            setTimeout(() => {
                window.confetti({
                    particleCount: 100,
                    angle: 60,
                    spread: 55,
                    origin: { x: 0 }
                });
                window.confetti({
                    particleCount: 100,
                    angle: 120,
                    spread: 55,
                    origin: { x: 1 }
                });
            }, 300);
        }

        const modal = document.getElementById('victory-modal');
        const victoryTime = document.getElementById('victory-time');
        const victoryMistakes = document.getElementById('victory-mistakes');
        const victoryBest = document.getElementById('victory-best');

        if (victoryTime) victoryTime.textContent = game.formatTime(result.timeSeconds);
        if (victoryMistakes) victoryMistakes.textContent = result.mistakes;

        const best = game.getBestTime();
        if (victoryBest) victoryBest.textContent = best ? game.formatTime(parseInt(best, 10)) : '-';

        if (modal) modal.classList.add('active');
    };

    // 4. UI Controls Binding

    // Mode Selector
    const modeSelect = document.getElementById('mode-select');
    if (modeSelect) {
        modeSelect.addEventListener('change', (e) => {
            const mode = e.target.value;
            updateSliceFilterUI(mode);
            view3d.buildBoard(mode);
            game.startNewGame(mode, game.difficulty);
            showToast(`모드 변경: ${getModeName(mode)}`);
        });
    }

    // Difficulty Selector
    const diffSelect = document.getElementById('difficulty-select');
    if (diffSelect) {
        diffSelect.addEventListener('change', (e) => {
            const diff = e.target.value;
            game.startNewGame(game.mode, diff);
            showToast(`난이도 변경: ${getDiffName(diff)}`);
        });
    }

    // New Game Button
    const btnNewGame = document.getElementById('btn-new-game');
    if (btnNewGame) {
        btnNewGame.addEventListener('click', () => {
            view3d.buildBoard(game.mode);
            game.startNewGame();
            showToast('새로운 퍼즐이 생성되었습니다!');
        });
    }

    // Explode Slider
    const explodeSlider = document.getElementById('explode-slider');
    const explodeVal = document.getElementById('explode-val');
    if (explodeSlider) {
        explodeSlider.addEventListener('input', (e) => {
            const val = parseFloat(e.target.value);
            view3d.setExplodeFactor(val);
            if (explodeVal) explodeVal.textContent = `${Math.round(val * 100)}%`;
        });
    }

    // Camera Presets
    document.getElementById('btn-view-reset')?.addEventListener('click', () => {
        view3d.resetCamera();
    });
    document.getElementById('btn-view-top')?.addEventListener('click', () => {
        view3d.setTopView();
    });
    document.getElementById('btn-view-front')?.addEventListener('click', () => {
        view3d.setFrontView();
    });
    document.getElementById('btn-view-side')?.addEventListener('click', () => {
        view3d.setSideView();
    });
    document.getElementById('btn-auto-rotate')?.addEventListener('click', (e) => {
        const isRotating = view3d.toggleAutoRotate();
        e.currentTarget.classList.toggle('active', isRotating);
    });

    // Sound & BGM toggles
    const btnSound = document.getElementById('btn-sound');
    if (btnSound) {
        btnSound.addEventListener('click', () => {
            const on = sound.toggleSound();
            btnSound.classList.toggle('muted', !on);
            btnSound.querySelector('.icon').textContent = on ? '🔊' : '🔇';
            showToast(on ? '효과음 켜짐' : '효과음 꺼짐');
        });
    }

    const btnBgm = document.getElementById('btn-bgm');
    if (btnBgm) {
        btnBgm.addEventListener('click', () => {
            const on = sound.toggleBGM();
            btnBgm.classList.toggle('active', on);
            btnBgm.querySelector('.icon').textContent = on ? '🎵' : '🎼';
            showToast(on ? '배경음악 켜짐' : '배경음악 꺼짐');
        });
    }

    // Action Buttons
    document.getElementById('btn-undo')?.addEventListener('click', () => game.undo());
    document.getElementById('btn-redo')?.addEventListener('click', () => game.redo());
    document.getElementById('btn-clear')?.addEventListener('click', () => game.clearSelectedCell());
    document.getElementById('btn-note')?.addEventListener('click', () => {
        const active = game.toggleNoteMode();
        showToast(active ? '연필 메모 모드 ON' : '연필 메모 모드 OFF');
    });

    document.getElementById('btn-hint')?.addEventListener('click', () => {
        const hint = game.useHint();
        if (hint) {
            showToast(`💡 ${hint.reason}`);
        } else {
            showToast('더 이상 빈 셀이 없거나 힌트를 찾을 수 없습니다.');
        }
    });

    document.getElementById('btn-solve')?.addEventListener('click', () => {
        if (confirm('현재 퍼즐의 정답을 모두 확인하시겠습니까?')) {
            game.solveGame();
            showToast('정답이 완성되었습니다.');
        }
    });

    // Pause Modal
    const btnPause = document.getElementById('btn-pause');
    const pauseModal = document.getElementById('pause-modal');
    if (btnPause && pauseModal) {
        btnPause.addEventListener('click', () => {
            const paused = game.togglePause();
            pauseModal.classList.toggle('active', paused);
        });
        document.getElementById('btn-resume')?.addEventListener('click', () => {
            game.togglePause();
            pauseModal.classList.remove('active');
        });
    }

    // Rules Modal
    const btnRules = document.getElementById('btn-rules');
    const rulesModal = document.getElementById('rules-modal');
    if (btnRules && rulesModal) {
        btnRules.addEventListener('click', () => rulesModal.classList.add('active'));
        document.getElementById('btn-close-rules')?.addEventListener('click', () => rulesModal.classList.remove('active'));
    }

    // Victory modal close / play again
    document.getElementById('btn-play-again')?.addEventListener('click', () => {
        document.getElementById('victory-modal')?.classList.remove('active');
        view3d.buildBoard(game.mode);
        game.startNewGame();
    });

    // Numpad clicks (1 to 9)
    for (let n = 1; n <= 9; n++) {
        const btn = document.getElementById(`numpad-${n}`);
        if (btn) {
            btn.addEventListener('click', () => {
                game.inputNumber(n);
            });
        }
    }

    // Keyboard Shortcuts
    window.addEventListener('keydown', (e) => {
        // Prevent default for game keys
        if (['Space', 'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.code) && e.target === document.body) {
            e.preventDefault();
        }

        // Numbers 1-9
        if (e.key >= '1' && e.key <= '9') {
            game.inputNumber(parseInt(e.key, 10));
            return;
        }

        // Erase
        if (e.key === 'Backspace' || e.key === 'Delete') {
            game.clearSelectedCell();
            return;
        }

        // Note mode toggle
        if (e.key.toLowerCase() === 'n') {
            game.toggleNoteMode();
            return;
        }

        // Hint
        if (e.key.toLowerCase() === 'h') {
            game.useHint();
            return;
        }

        // Undo (Z or Ctrl+Z)
        if (e.code === 'KeyZ' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            game.undo();
            return;
        }
        if (e.code === 'KeyY' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            game.redo();
            return;
        }

        // Space: Reset camera
        if (e.code === 'Space') {
            view3d.resetCamera();
            return;
        }

        // R: Toggle auto-rotate
        if (e.key.toLowerCase() === 'r') {
            const btn = document.getElementById('btn-auto-rotate');
            const isRotating = view3d.toggleAutoRotate();
            if (btn) btn.classList.toggle('active', isRotating);
            return;
        }

        // E: Toggle explode
        if (e.key.toLowerCase() === 'e') {
            const newFactor = view3d.targetExplodeFactor > 0.1 ? 0.0 : 0.6;
            view3d.setExplodeFactor(newFactor);
            if (explodeSlider) explodeSlider.value = newFactor;
            if (explodeVal) explodeVal.textContent = `${Math.round(newFactor * 100)}%`;
            return;
        }

        // Escape: Pause
        if (e.key === 'Escape') {
            if (rulesModal?.classList.contains('active')) {
                rulesModal.classList.remove('active');
            } else {
                const paused = game.togglePause();
                pauseModal?.classList.toggle('active', paused);
            }
        }
    });

    // Slice Filter UI Management
    function setupSliceFilters() {
        const filterContainer = document.getElementById('slice-filter-buttons');
        if (!filterContainer) return;

        filterContainer.addEventListener('click', (e) => {
            const btn = e.target.closest('.slice-btn');
            if (!btn) return;

            filterContainer.querySelectorAll('.slice-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const axis = btn.dataset.axis;
            const layer = parseInt(btn.dataset.layer || '0', 10);
            view3d.setSliceFilter(axis, layer);
            view3d.updateCells(game);
        });
    }

    function updateSliceFilterUI(mode) {
        const filterContainer = document.getElementById('slice-filter-buttons');
        if (!filterContainer) return;

        filterContainer.innerHTML = '';

        if (mode === 'surface') {
            filterContainer.innerHTML = `
                <button class="slice-btn active" data-axis="all">전체 (All)</button>
                <button class="slice-btn" data-axis="face" data-layer="0">앞면 (Front)</button>
                <button class="slice-btn" data-axis="face" data-layer="1">우측 (Right)</button>
                <button class="slice-btn" data-axis="face" data-layer="2">뒷면 (Back)</button>
                <button class="slice-btn" data-axis="face" data-layer="3">좌측 (Left)</button>
                <button class="slice-btn" data-axis="face" data-layer="4">윗면 (Top)</button>
                <button class="slice-btn" data-axis="face" data-layer="5">아랫면 (Bottom)</button>
            `;
        } else if (mode === 'hyper4') {
            filterContainer.innerHTML = `
                <button class="slice-btn active" data-axis="all">전체</button>
                <button class="slice-btn" data-axis="z" data-layer="0">Z: 1층</button>
                <button class="slice-btn" data-axis="z" data-layer="1">Z: 2층</button>
                <button class="slice-btn" data-axis="z" data-layer="2">Z: 3층</button>
                <button class="slice-btn" data-axis="z" data-layer="3">Z: 4층</button>
            `;
        } else {
            // Dionis 3x3x3
            filterContainer.innerHTML = `
                <button class="slice-btn active" data-axis="all">전체 큐브</button>
                <div class="slice-group">
                    <span class="slice-label">Z축(층):</span>
                    <button class="slice-btn" data-axis="z" data-layer="0">하단 (Z0)</button>
                    <button class="slice-btn" data-axis="z" data-layer="1">중단 (Z1)</button>
                    <button class="slice-btn" data-axis="z" data-layer="2">상단 (Z2)</button>
                </div>
                <div class="slice-group">
                    <span class="slice-label">Y축(행):</span>
                    <button class="slice-btn" data-axis="y" data-layer="0">Y0</button>
                    <button class="slice-btn" data-axis="y" data-layer="1">Y1</button>
                    <button class="slice-btn" data-axis="y" data-layer="2">Y2</button>
                </div>
                <div class="slice-group">
                    <span class="slice-label">X축(열):</span>
                    <button class="slice-btn" data-axis="x" data-layer="0">X0</button>
                    <button class="slice-btn" data-axis="x" data-layer="1">X1</button>
                    <button class="slice-btn" data-axis="x" data-layer="2">X2</button>
                </div>
            `;
        }

        view3d.setSliceFilter('all', 0);
    }

    function getModeName(mode) {
        if (mode === 'surface') return '6면 루빅스 표면 스도쿠';
        if (mode === 'hyper4') return '4x4x4 하이퍼큐브 스도쿠';
        return '3x3x3 Dionis 큐브 스도쿠';
    }

    function getDiffName(diff) {
        const names = { easy: '쉬움', medium: '보통', hard: '어려움', expert: '전문가' };
        return names[diff] || diff;
    }

    // Initialize UI and Game
    setupSliceFilters();
    updateSliceFilterUI('dionis');
    view3d.buildBoard('dionis');
    game.startNewGame('dionis', 'medium');
    showToast('3D 스도쿠에 오신 것을 환영합니다! 셀을 클릭하여 숫자를 채워보세요.');
});
