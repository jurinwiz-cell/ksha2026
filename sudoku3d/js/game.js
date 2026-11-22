/**
 * 3D Sudoku - Game State & Controller
 * Manages board state, moves, undo/redo, notes, timer, hints, and victory detection.
 */

class SudokuGame {
    constructor() {
        this.mode = 'dionis';          // 'dionis' | 'surface' | 'hyper4'
        this.difficulty = 'medium';    // 'easy' | 'medium' | 'hard' | 'expert'
        this.isNoteMode = false;       // Pencil note mode toggle

        // Board data
        this.currentBoard = [];
        this.solution = [];
        this.initialClues = [];
        this.notes = [];               // Array of Sets for each cell
        this.selectedCell = null;
        this.conflicts = new Set();
        this.connectedCells = new Set();

        // History for Undo / Redo
        this.history = [];
        this.redoStack = [];

        // Game status
        this.isGameOver = false;
        this.isPaused = false;
        this.timerSeconds = 0;
        this.timerInterval = null;
        this.mistakes = 0;

        // Callback for UI updates
        this.onStateChange = null;
        this.onVictory = null;
    }

    startNewGame(mode = this.mode, difficulty = this.difficulty) {
        this.mode = mode;
        this.difficulty = difficulty;
        this.isGameOver = false;
        this.isPaused = false;
        this.mistakes = 0;
        this.history = [];
        this.redoStack = [];

        // Generate puzzle via Sudoku3DSolver
        const puzzleData = window.sudokuSolver.generate(this.mode, this.difficulty);
        this.currentBoard = [...puzzleData.puzzle];
        this.solution = [...puzzleData.solution];
        this.initialClues = [...puzzleData.initialClues];
        this.notes = Array.from({ length: puzzleData.totalCells }, () => new Set());
        this.selectedCell = null;
        this.conflicts = new Set();
        this.connectedCells = new Set();

        // Reset timer
        this.resetTimer();
        this.startTimer();

        this.notifyStateChange();
    }

    selectCell(cellIndex) {
        if (this.isGameOver || this.isPaused) return;
        this.selectedCell = cellIndex;
        this.connectedCells = window.sudokuSolver.getConnectedCells(this.mode, cellIndex);
        if (window.soundEngine) window.soundEngine.playSelect();
        this.notifyStateChange();
    }

    toggleNoteMode() {
        this.isNoteMode = !this.isNoteMode;
        this.notifyStateChange();
        return this.isNoteMode;
    }

    inputNumber(num) {
        if (this.isGameOver || this.isPaused || this.selectedCell === null) return;
        const idx = this.selectedCell;

        // Cannot change initial clue
        if (this.initialClues[idx]) {
            if (window.soundEngine) window.soundEngine.playError();
            return;
        }

        const maxNum = (this.mode === 'hyper4') ? 4 : 9;
        if (num < 1 || num > maxNum) return;

        if (this.isNoteMode) {
            // Note Mode: toggle candidate
            const cellNotes = this.notes[idx];
            const prevNotes = new Set(cellNotes);

            if (cellNotes.has(num)) {
                cellNotes.delete(num);
            } else {
                cellNotes.add(num);
            }

            this._pushHistory({
                type: 'note',
                cellIndex: idx,
                prevNotes,
                newNotes: new Set(cellNotes)
            });

            if (window.soundEngine) window.soundEngine.playNumber(num);
            this.notifyStateChange();
        } else {
            // Normal Number Mode
            const prevVal = this.currentBoard[idx];
            if (prevVal === num) return; // Same number already

            this._pushHistory({
                type: 'value',
                cellIndex: idx,
                prevVal,
                newVal: num,
                prevNotes: new Set(this.notes[idx])
            });

            this.currentBoard[idx] = num;
            // Clear notes for this cell upon filling
            this.notes[idx].clear();

            // Check conflicts
            this.conflicts = window.sudokuSolver.getConflicts(this.mode, this.currentBoard);

            if (this.conflicts.has(idx)) {
                this.mistakes++;
                if (window.soundEngine) window.soundEngine.playError();
            } else {
                if (window.soundEngine) window.soundEngine.playNumber(num);
            }

            this.checkVictory();
            this.notifyStateChange();
        }
    }

    clearSelectedCell() {
        if (this.isGameOver || this.isPaused || this.selectedCell === null) return;
        const idx = this.selectedCell;

        if (this.initialClues[idx]) return;

        if (this.currentBoard[idx] > 0 || this.notes[idx].size > 0) {
            this._pushHistory({
                type: 'value',
                cellIndex: idx,
                prevVal: this.currentBoard[idx],
                newVal: 0,
                prevNotes: new Set(this.notes[idx])
            });

            this.currentBoard[idx] = 0;
            this.notes[idx].clear();

            this.conflicts = window.sudokuSolver.getConflicts(this.mode, this.currentBoard);
            if (window.soundEngine) window.soundEngine.playErase();
            this.notifyStateChange();
        }
    }

    _pushHistory(action) {
        this.history.push(action);
        this.redoStack = []; // Clear redo stack on new action
    }

    undo() {
        if (this.history.length === 0 || this.isGameOver || this.isPaused) return;
        const action = this.history.pop();
        this.redoStack.push(action);

        const idx = action.cellIndex;
        if (action.type === 'value') {
            this.currentBoard[idx] = action.prevVal;
            this.notes[idx] = new Set(action.prevNotes);
        } else if (action.type === 'note') {
            this.notes[idx] = new Set(action.prevNotes);
        }

        this.conflicts = window.sudokuSolver.getConflicts(this.mode, this.currentBoard);
        this.selectedCell = idx;
        if (window.soundEngine) window.soundEngine.playSelect();
        this.notifyStateChange();
    }

    redo() {
        if (this.redoStack.length === 0 || this.isGameOver || this.isPaused) return;
        const action = this.redoStack.pop();
        this.history.push(action);

        const idx = action.cellIndex;
        if (action.type === 'value') {
            this.currentBoard[idx] = action.newVal;
            if (action.newVal > 0) this.notes[idx].clear();
        } else if (action.type === 'note') {
            this.notes[idx] = new Set(action.newNotes);
        }

        this.conflicts = window.sudokuSolver.getConflicts(this.mode, this.currentBoard);
        this.selectedCell = idx;
        if (window.soundEngine) window.soundEngine.playSelect();
        this.notifyStateChange();
    }

    useHint() {
        if (this.isGameOver || this.isPaused) return;

        const hint = window.sudokuSolver.findHint(this.mode, this.currentBoard, this.solution);
        if (!hint) return;

        const idx = hint.cellIndex;
        this.selectedCell = idx;
        this.connectedCells = window.sudokuSolver.getConnectedCells(this.mode, idx);

        this._pushHistory({
            type: 'value',
            cellIndex: idx,
            prevVal: this.currentBoard[idx],
            newVal: hint.value,
            prevNotes: new Set(this.notes[idx])
        });

        this.currentBoard[idx] = hint.value;
        this.notes[idx].clear();
        this.conflicts = window.sudokuSolver.getConflicts(this.mode, this.currentBoard);

        if (window.soundEngine) window.soundEngine.playHint();

        this.checkVictory();
        this.notifyStateChange();
        return hint;
    }

    solveGame() {
        if (this.isGameOver) return;
        this.currentBoard = [...this.solution];
        this.conflicts.clear();
        this.notes.forEach(set => set.clear());
        this.isGameOver = true;
        this.stopTimer();
        this.notifyStateChange();
    }

    checkVictory() {
        // Complete when no cells are empty and no conflicts exist
        const hasEmpty = this.currentBoard.some(v => v === 0);
        if (!hasEmpty && this.conflicts.size === 0) {
            this.isGameOver = true;
            this.stopTimer();

            // Save record to localStorage
            this.saveRecord();

            if (window.soundEngine) window.soundEngine.playVictory();
            if (this.onVictory) {
                this.onVictory({
                    timeSeconds: this.timerSeconds,
                    mistakes: this.mistakes,
                    mode: this.mode,
                    difficulty: this.difficulty
                });
            }
        }
    }

    saveRecord() {
        try {
            const key = `sudoku3d_record_${this.mode}_${this.difficulty}`;
            const bestTime = localStorage.getItem(key);
            if (!bestTime || this.timerSeconds < parseInt(bestTime, 10)) {
                localStorage.setItem(key, this.timerSeconds.toString());
            }
        } catch (e) {
            console.warn('Storage unavailable', e);
        }
    }

    getBestTime() {
        try {
            const key = `sudoku3d_record_${this.mode}_${this.difficulty}`;
            return localStorage.getItem(key);
        } catch (e) {
            return null;
        }
    }

    // ==========================================
    // Timer controls
    // ==========================================
    startTimer() {
        if (this.timerInterval) clearInterval(this.timerInterval);
        this.timerInterval = setInterval(() => {
            if (!this.isPaused && !this.isGameOver) {
                this.timerSeconds++;
                this.notifyStateChange(false); // fast update
            }
        }, 1000);
    }

    stopTimer() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
            this.timerInterval = null;
        }
    }

    resetTimer() {
        this.stopTimer();
        this.timerSeconds = 0;
    }

    togglePause() {
        if (this.isGameOver) return;
        this.isPaused = !this.isPaused;
        this.notifyStateChange();
        return this.isPaused;
    }

    formatTime(sec = this.timerSeconds) {
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
    }

    getNumberCounts() {
        const maxNum = (this.mode === 'hyper4') ? 4 : 9;
        const counts = Array(maxNum + 1).fill(0);
        this.currentBoard.forEach(v => {
            if (v > 0 && v <= maxNum) counts[v]++;
        });
        return counts;
    }

    notifyStateChange(fullUpdate = true) {
        if (this.onStateChange) {
            this.onStateChange(this, fullUpdate);
        }
    }
}

window.sudokuGame = new SudokuGame();
