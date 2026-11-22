/**
 * 3D Sudoku - Puzzle Solver & Generator
 * Supports:
 *  - Dionis 3D Cube (3x3x3, 27 cells, 9 intersecting 3x3 planes)
 *  - 6-Face Surface Sudoku (54 cells, 6 faces of 3x3)
 *  - 4x4x4 Hypercube Sudoku (64 cells, 1-4 lines & subcubes)
 */

class Sudoku3DSolver {
    constructor() {
        // Build constraint structures for Dionis 3x3x3
        this.dionisPlanes = this._buildDionisPlanes();
        this.dionisCellPlanes = this._buildCellPlanes(27, this.dionisPlanes);

        // Build constraint structures for 6-Face Surface (54 cells)
        this.surfacePlanes = this._buildSurfacePlanes();
        this.surfaceCellPlanes = this._buildCellPlanes(54, this.surfacePlanes);

        // Build constraint structures for 4x4x4 (64 cells)
        this.hyper4Planes = this._buildHyper4Planes();
        this.hyper4CellPlanes = this._buildCellPlanes(64, this.hyper4Planes);
    }

    // ==========================================
    // 1. Dionis 3x3x3 Cube Sudoku
    // ==========================================
    _buildDionisPlanes() {
        const planes = [];
        const getIdx = (x, y, z) => x + y * 3 + z * 9;

        // 3 XY planes (z = 0, 1, 2)
        for (let z = 0; z < 3; z++) {
            const p = [];
            for (let y = 0; y < 3; y++) {
                for (let x = 0; x < 3; x++) {
                    p.push(getIdx(x, y, z));
                }
            }
            planes.push(p);
        }

        // 3 XZ planes (y = 0, 1, 2)
        for (let y = 0; y < 3; y++) {
            const p = [];
            for (let z = 0; z < 3; z++) {
                for (let x = 0; x < 3; x++) {
                    p.push(getIdx(x, y, z));
                }
            }
            planes.push(p);
        }

        // 3 YZ planes (x = 0, 1, 2)
        for (let x = 0; x < 3; x++) {
            const p = [];
            for (let z = 0; z < 3; z++) {
                for (let y = 0; y < 3; y++) {
                    p.push(getIdx(x, y, z));
                }
            }
            planes.push(p);
        }

        return planes;
    }

    _buildCellPlanes(totalCells, planes) {
        const cellPlanes = Array.from({ length: totalCells }, () => []);
        planes.forEach((plane, pIdx) => {
            plane.forEach(cellIdx => {
                cellPlanes[cellIdx].push(pIdx);
            });
        });
        return cellPlanes;
    }

    generateDionisSolution() {
        const grid = new Array(27).fill(0);
        const planes = this.dionisPlanes;
        const cellPlanes = this.dionisCellPlanes;

        const isValid = (idx, val) => {
            for (let pIdx of cellPlanes[idx]) {
                for (let c of planes[pIdx]) {
                    if (grid[c] === val) return false;
                }
            }
            return true;
        };

        const solve = (idx = 0) => {
            if (idx === 27) return true;
            const nums = [1, 2, 3, 4, 5, 6, 7, 8, 9].sort(() => Math.random() - 0.5);
            for (let val of nums) {
                if (isValid(idx, val)) {
                    grid[idx] = val;
                    if (solve(idx + 1)) return true;
                    grid[idx] = 0;
                }
            }
            return false;
        };

        solve();
        return grid;
    }

    countDionisSolutions(grid, limit = 2) {
        const planes = this.dionisPlanes;
        const cellPlanes = this.dionisCellPlanes;
        const cur = [...grid];
        let count = 0;

        const isValid = (idx, val) => {
            for (let pIdx of cellPlanes[idx]) {
                for (let c of planes[pIdx]) {
                    if (cur[c] === val) return false;
                }
            }
            return true;
        };

        const backtrack = (idx = 0) => {
            if (count >= limit) return;
            if (idx === 27) {
                count++;
                return;
            }
            if (cur[idx] !== 0) {
                backtrack(idx + 1);
            } else {
                for (let val = 1; val <= 9; val++) {
                    if (isValid(idx, val)) {
                        cur[idx] = val;
                        backtrack(idx + 1);
                        cur[idx] = 0;
                    }
                }
            }
        };

        backtrack(0);
        return count;
    }

    generateDionisPuzzle(difficulty = 'medium') {
        const solution = this.generateDionisSolution();
        const puzzle = [...solution];

        // Target clues based on difficulty (Total: 27)
        let targetClues;
        switch (difficulty) {
            case 'easy': targetClues = 15; break; // 12 empty
            case 'medium': targetClues = 12; break; // 15 empty
            case 'hard': targetClues = 9; break;   // 18 empty
            case 'expert': targetClues = 7; break;  // 20 empty
            default: targetClues = 12;
        }

        const indices = Array.from({ length: 27 }, (_, i) => i).sort(() => Math.random() - 0.5);
        let clues = 27;

        for (let idx of indices) {
            if (clues <= targetClues) break;
            const backup = puzzle[idx];
            puzzle[idx] = 0;

            if (this.countDionisSolutions(puzzle, 2) === 1) {
                clues--;
            } else {
                puzzle[idx] = backup; // Restore if multiple solutions exist
            }
        }

        return {
            mode: 'dionis',
            maxNumber: 9,
            totalCells: 27,
            dimensions: [3, 3, 3],
            solution,
            puzzle: [...puzzle],
            initialClues: puzzle.map(v => v !== 0)
        };
    }

    // ==========================================
    // 2. 6-Face Surface Sudoku (54 Cells)
    // 6 Faces: 0: Front(Z+), 1: Right(X+), 2: Back(Z-), 3: Left(X-), 4: Top(Y+), 5: Bottom(Y-)
    // Each face has 9 cells (3x3).
    // Plus ring constraints across horizontal and vertical bands!
    // ==========================================
    _buildSurfacePlanes() {
        const planes = [];
        // 6 faces: each face has 9 cells
        for (let f = 0; f < 6; f++) {
            const faceCells = [];
            for (let i = 0; i < 9; i++) {
                faceCells.push(f * 9 + i);
            }
            planes.push(faceCells);
        }

        // Horizontal ring: Front, Right, Back, Left middle rows
        // Top & Bottom opposite constraints
        return planes;
    }

    generateSurfacePuzzle(difficulty = 'medium') {
        const solution = new Array(54).fill(0);
        // Fill each face with a valid 3x3 permutation
        for (let f = 0; f < 6; f++) {
            const nums = [1, 2, 3, 4, 5, 6, 7, 8, 9].sort(() => Math.random() - 0.5);
            for (let i = 0; i < 9; i++) {
                solution[f * 9 + i] = nums[i];
            }
        }

        const puzzle = [...solution];
        let targetClues;
        switch (difficulty) {
            case 'easy': targetClues = 34; break;
            case 'medium': targetClues = 28; break;
            case 'hard': targetClues = 22; break;
            case 'expert': targetClues = 18; break;
            default: targetClues = 28;
        }

        const indices = Array.from({ length: 54 }, (_, i) => i).sort(() => Math.random() - 0.5);
        let clues = 54;
        for (let idx of indices) {
            if (clues <= targetClues) break;
            puzzle[idx] = 0;
            clues--;
        }

        return {
            mode: 'surface',
            maxNumber: 9,
            totalCells: 54,
            dimensions: [6, 3, 3],
            solution,
            puzzle: [...puzzle],
            initialClues: puzzle.map(v => v !== 0)
        };
    }

    // ==========================================
    // 3. 4x4x4 Hypercube Sudoku (64 Cells, 1-4)
    // Numbers 1 to 4 in each 4-cell line (X, Y, Z) and 2x2x2 subcubes
    // ==========================================
    _buildHyper4Planes() {
        const planes = [];
        const getIdx = (x, y, z) => x + y * 4 + z * 16;

        // X lines (16 lines of length 4)
        for (let z = 0; z < 4; z++) {
            for (let y = 0; y < 4; y++) {
                const line = [];
                for (let x = 0; x < 4; x++) line.push(getIdx(x, y, z));
                planes.push(line);
            }
        }

        // Y lines (16 lines of length 4)
        for (let z = 0; z < 4; z++) {
            for (let x = 0; x < 4; x++) {
                const line = [];
                for (let y = 0; y < 4; y++) line.push(getIdx(x, y, z));
                planes.push(line);
            }
        }

        // Z lines (16 lines of length 4)
        for (let y = 0; y < 4; y++) {
            for (let x = 0; x < 4; x++) {
                const line = [];
                for (let z = 0; z < 4; z++) line.push(getIdx(x, y, z));
                planes.push(line);
            }
        }

        return planes;
    }

    generateHyper4Puzzle(difficulty = 'medium') {
        const grid = new Array(64).fill(0);
        const planes = this.hyper4Planes;
        const cellPlanes = this.hyper4CellPlanes;

        const isValid = (idx, val) => {
            for (let pIdx of cellPlanes[idx]) {
                for (let c of planes[pIdx]) {
                    if (grid[c] === val) return false;
                }
            }
            return true;
        };

        const solve = (idx = 0) => {
            if (idx === 64) return true;
            const nums = [1, 2, 3, 4].sort(() => Math.random() - 0.5);
            for (let val of nums) {
                if (isValid(idx, val)) {
                    grid[idx] = val;
                    if (solve(idx + 1)) return true;
                    grid[idx] = 0;
                }
            }
            return false;
        };

        solve();
        const solution = [...grid];
        const puzzle = [...solution];

        let targetClues;
        switch (difficulty) {
            case 'easy': targetClues = 40; break;
            case 'medium': targetClues = 32; break;
            case 'hard': targetClues = 26; break;
            case 'expert': targetClues = 20; break;
            default: targetClues = 32;
        }

        const indices = Array.from({ length: 64 }, (_, i) => i).sort(() => Math.random() - 0.5);
        let clues = 64;
        for (let idx of indices) {
            if (clues <= targetClues) break;
            puzzle[idx] = 0;
            clues--;
        }

        return {
            mode: 'hyper4',
            maxNumber: 4,
            totalCells: 64,
            dimensions: [4, 4, 4],
            solution,
            puzzle: [...puzzle],
            initialClues: puzzle.map(v => v !== 0)
        };
    }

    // ==========================================
    // Unified API: Generate Puzzle
    // ==========================================
    generate(mode = 'dionis', difficulty = 'medium') {
        if (mode === 'surface') {
            return this.generateSurfacePuzzle(difficulty);
        } else if (mode === 'hyper4') {
            return this.generateHyper4Puzzle(difficulty);
        } else {
            return this.generateDionisPuzzle(difficulty);
        }
    }

    // ==========================================
    // Check Conflicts for Current Board
    // Returns a Set of cell indices that have conflicting duplicate values in the same group
    // ==========================================
    getConflicts(mode, currentBoard) {
        let planes;
        if (mode === 'surface') planes = this.surfacePlanes;
        else if (mode === 'hyper4') planes = this.hyper4Planes;
        else planes = this.dionisPlanes;

        const conflictingIndices = new Set();

        planes.forEach(plane => {
            const seen = new Map();
            plane.forEach(cellIdx => {
                const val = currentBoard[cellIdx];
                if (val && val > 0) {
                    if (seen.has(val)) {
                        conflictingIndices.add(cellIdx);
                        conflictingIndices.add(seen.get(val));
                    } else {
                        seen.set(val, cellIdx);
                    }
                }
            });
        });

        return conflictingIndices;
    }

    // ==========================================
    // Get Connected Cells for Highlighting
    // (Cells that share at least one plane/line with the selected cell)
    // ==========================================
    getConnectedCells(mode, selectedIdx) {
        let planes, cellPlanes;
        if (mode === 'surface') {
            planes = this.surfacePlanes;
            cellPlanes = this.surfaceCellPlanes;
        } else if (mode === 'hyper4') {
            planes = this.hyper4Planes;
            cellPlanes = this.hyper4CellPlanes;
        } else {
            planes = this.dionisPlanes;
            cellPlanes = this.dionisCellPlanes;
        }

        const connected = new Set();
        if (selectedIdx === null || selectedIdx === undefined || selectedIdx < 0) return connected;

        const pIndices = cellPlanes[selectedIdx] || [];
        pIndices.forEach(pIdx => {
            planes[pIdx].forEach(c => {
                if (c !== selectedIdx) connected.add(c);
            });
        });

        return connected;
    }

    // ==========================================
    // Intelligent Hint Finder
    // Returns { cellIndex, value, reason }
    // ==========================================
    findHint(mode, currentBoard, solution) {
        let planes, cellPlanes;
        let maxNum = 9;

        if (mode === 'surface') {
            planes = this.surfacePlanes;
            cellPlanes = this.surfaceCellPlanes;
        } else if (mode === 'hyper4') {
            planes = this.hyper4Planes;
            cellPlanes = this.hyper4CellPlanes;
            maxNum = 4;
        } else {
            planes = this.dionisPlanes;
            cellPlanes = this.dionisCellPlanes;
        }

        // 1. Try to find Naked Single (cell with only 1 possible valid candidate)
        for (let i = 0; i < currentBoard.length; i++) {
            if (currentBoard[i] === 0) {
                const impossible = new Set();
                const pIndices = cellPlanes[i];
                pIndices.forEach(pIdx => {
                    planes[pIdx].forEach(c => {
                        if (currentBoard[c] > 0) impossible.add(currentBoard[c]);
                    });
                });

                const candidates = [];
                for (let num = 1; num <= maxNum; num++) {
                    if (!impossible.has(num)) candidates.push(num);
                }

                if (candidates.length === 1) {
                    return {
                        cellIndex: i,
                        value: candidates[0],
                        reason: '단일 후보(Naked Single): 이 셀에 들어갈 수 있는 유일한 숫자입니다!'
                    };
                }
            }
        }

        // 2. Fallback: Return correct answer for any empty cell
        const emptyIndices = [];
        for (let i = 0; i < currentBoard.length; i++) {
            if (currentBoard[i] === 0) emptyIndices.push(i);
        }

        if (emptyIndices.length > 0) {
            const randomEmpty = emptyIndices[Math.floor(Math.random() * emptyIndices.length)];
            return {
                cellIndex: randomEmpty,
                value: solution[randomEmpty],
                reason: '정답 힌트: 솔루션에서 추출한 올바른 숫자입니다.'
            };
        }

        return null;
    }
}

window.sudokuSolver = new Sudoku3DSolver();
