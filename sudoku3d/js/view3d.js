/**
 * 3D Sudoku - Three.js 3D Viewport & Rendering Engine
 * Handles 3D meshes, procedural canvas textures, raycasting,
 * explode animation, slice filters, and dynamic lighting.
 */

class Sudoku3DView {
    constructor(containerElement) {
        this.container = containerElement;
        this.scene = null;
        this.camera = null;
        this.renderer = null;
        this.controls = null;

        // Meshes and interaction
        this.cellMeshes = [];
        this.cellGroup = null;
        this.raycaster = new THREE.Raycaster();
        this.mouse = new THREE.Vector2(-1000, -1000);
        this.hoveredCellIndex = null;
        this.selectedCellIndex = null;

        // Pointer drag detection
        this.pointerDownPos = { x: 0, y: 0 };
        this.isDragging = false;

        // Explode view factor (0.0 to 1.5)
        this.explodeFactor = 0.35;
        this.targetExplodeFactor = 0.35;

        // Slice filter: { axis: 'all' | 'x' | 'y' | 'z', layer: 0 | 1 | 2 }
        this.sliceFilter = { axis: 'all', layer: 0 };

        // Callback for cell selection
        this.onCellSelect = null;

        // Cache canvas textures
        this.textureCache = new Map();

        // Color palettes
        this.colors = {
            baseClue: '#182436',       // Fixed clue background
            baseUser: '#162828',       // User input background
            baseEmpty: '#0f1722',      // Empty cell background
            borderNormal: '#2a3b53',   // Subtle border
            borderClue: '#3f5677',     // Clue border
            selected: '#f59e0b',       // Glowing warm amber for selected
            connected: '#312e81',      // Deep indigo for connected group
            connectedBorder: '#6366f1',// Violet border
            matchingNumber: '#065f46', // Emerald green for same numbers
            matchingBorder: '#10b981',
            conflict: '#881337',       // Rose red for conflict/error
            conflictBorder: '#f43f5e',
            textClue: '#f8fafc',       // Crisp white for clues
            textUser: '#38bdf8',       // Neon cyan for user numbers
            textNote: '#94a3b8'        // Light slate for pencil notes
        };

        this.init();
    }

    init() {
        const width = this.container.clientWidth;
        const height = this.container.clientHeight;

        // 1. Scene
        this.scene = new THREE.Scene();
        this.scene.background = null; // Transparent, let CSS gradient shine through

        // 2. Camera
        this.camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 100);
        this.camera.position.set(5.5, 4.8, 6.5);

        // 3. Renderer
        this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        this.renderer.setSize(width, height);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        this.container.appendChild(this.renderer.domElement);

        // 4. Orbit Controls
        this.controls = new THREE.OrbitControls(this.camera, this.renderer.domElement);
        this.controls.enableDamping = true;
        this.controls.dampingFactor = 0.08;
        this.controls.minDistance = 3.0;
        this.controls.maxDistance = 20.0;
        this.controls.autoRotate = false;
        this.controls.autoRotateSpeed = 1.0;

        // 5. Lighting
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.7);
        this.scene.add(ambientLight);

        const dirLight1 = new THREE.DirectionalLight(0xffffff, 0.8);
        dirLight1.position.set(8, 12, 10);
        this.scene.add(dirLight1);

        const dirLight2 = new THREE.DirectionalLight(0x60a5fa, 0.4);
        dirLight2.position.set(-8, -5, -8);
        this.scene.add(dirLight2);

        // 6. Cell Group
        this.cellGroup = new THREE.Group();
        this.scene.add(this.cellGroup);

        // 7. Event Listeners
        window.addEventListener('resize', () => this.onResize());
        this.setupInteractions();

        // 8. Animation Loop
        this.animate = this.animate.bind(this);
        requestAnimationFrame(this.animate);
    }

    setupInteractions() {
        const dom = this.renderer.domElement;

        dom.addEventListener('pointerdown', (e) => {
            this.pointerDownPos.x = e.clientX;
            this.pointerDownPos.y = e.clientY;
            this.isDragging = false;
        });

        dom.addEventListener('pointermove', (e) => {
            const dist = Math.hypot(e.clientX - this.pointerDownPos.x, e.clientY - this.pointerDownPos.y);
            if (dist > 5) this.isDragging = true;

            const rect = dom.getBoundingClientRect();
            this.mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
            this.mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;
        });

        dom.addEventListener('pointerup', (e) => {
            if (!this.isDragging) {
                this.handleClick(e);
            }
        });

        // Touch support
        dom.addEventListener('touchstart', (e) => {
            if (e.touches.length === 1) {
                this.pointerDownPos.x = e.touches[0].clientX;
                this.pointerDownPos.y = e.touches[0].clientY;
                this.isDragging = false;
            }
        });
    }

    handleClick(e) {
        const rect = this.renderer.domElement.getBoundingClientRect();
        const x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
        const y = -((e.clientY - rect.top) / rect.height) * 2 + 1;

        this.raycaster.setFromCamera(new THREE.Vector2(x, y), this.camera);
        // Only intersect visible cells
        const visibleMeshes = this.cellMeshes.filter(m => m.visible && m.material.opacity > 0.3);
        const intersects = this.raycaster.intersectObjects(visibleMeshes);

        if (intersects.length > 0) {
            const hitMesh = intersects[0].object;
            const cellIndex = hitMesh.userData.cellIndex;
            if (this.onCellSelect) {
                this.onCellSelect(cellIndex);
            }
        }
    }

    onResize() {
        if (!this.container) return;
        const width = this.container.clientWidth;
        const height = this.container.clientHeight;
        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(width, height);
    }

    // ==========================================
    // Build 3D Mesh Board for Given Mode
    // ==========================================
    buildBoard(mode = 'dionis') {
        // Clear previous meshes
        while (this.cellGroup.children.length > 0) {
            const obj = this.cellGroup.children[0];
            if (obj.geometry) obj.geometry.dispose();
            if (obj.material) {
                if (Array.isArray(obj.material)) obj.material.forEach(m => m.dispose());
                else obj.material.dispose();
            }
            this.cellGroup.remove(obj);
        }
        this.cellMeshes = [];
        this.textureCache.clear();

        if (mode === 'surface') {
            this._buildSurfaceBoard();
        } else if (mode === 'hyper4') {
            this._buildHyper4Board();
        } else {
            this._buildDionisBoard();
        }

        this.updateCellPositions();
    }

    _buildDionisBoard() {
        // 3x3x3 grid, cell size 0.85
        const geom = new THREE.BoxGeometry(0.85, 0.85, 0.85);

        for (let z = 0; z < 3; z++) {
            for (let y = 0; y < 3; y++) {
                for (let x = 0; x < 3; x++) {
                    const cellIndex = x + y * 3 + z * 9;
                    const basePos = new THREE.Vector3(x - 1, y - 1, z - 1);

                    const mat = new THREE.MeshStandardMaterial({
                        color: 0x1e293b,
                        roughness: 0.3,
                        metalness: 0.1,
                        transparent: true,
                        opacity: 0.95
                    });

                    const mesh = new THREE.Mesh(geom, mat);
                    mesh.castShadow = true;
                    mesh.receiveShadow = true;
                    mesh.userData = {
                        cellIndex,
                        coords: { x, y, z },
                        basePos
                    };

                    this.cellGroup.add(mesh);
                    this.cellMeshes[cellIndex] = mesh;
                }
            }
        }
    }

    _buildSurfaceBoard() {
        // 6 faces, 3x3 each
        const geom = new THREE.BoxGeometry(0.85, 0.85, 0.85);
        const faceConfigs = [
            { normal: new THREE.Vector3(0, 0, 1), center: new THREE.Vector3(0, 0, 1.05) },   // Front
            { normal: new THREE.Vector3(1, 0, 0), center: new THREE.Vector3(1.05, 0, 0) },   // Right
            { normal: new THREE.Vector3(0, 0, -1), center: new THREE.Vector3(0, 0, -1.05) }, // Back
            { normal: new THREE.Vector3(-1, 0, 0), center: new THREE.Vector3(-1.05, 0, 0) }, // Left
            { normal: new THREE.Vector3(0, 1, 0), center: new THREE.Vector3(0, 1.05, 0) },   // Top
            { normal: new THREE.Vector3(0, -1, 0), center: new THREE.Vector3(0, -1.05, 0) }  // Bottom
        ];

        let cellIndex = 0;
        faceConfigs.forEach((face, fIdx) => {
            for (let row = 0; row < 3; row++) {
                for (let col = 0; col < 3; col++) {
                    let basePos = new THREE.Vector3();
                    const u = col - 1;
                    const v = 1 - row;

                    if (fIdx === 0) basePos.set(u, v, 1);
                    else if (fIdx === 1) basePos.set(1, v, -u);
                    else if (fIdx === 2) basePos.set(-u, v, -1);
                    else if (fIdx === 3) basePos.set(-1, v, u);
                    else if (fIdx === 4) basePos.set(u, 1, -v);
                    else if (fIdx === 5) basePos.set(u, -1, v);

                    const mat = new THREE.MeshStandardMaterial({
                        color: 0x1e293b,
                        roughness: 0.3,
                        metalness: 0.1,
                        transparent: true,
                        opacity: 0.95
                    });

                    const mesh = new THREE.Mesh(geom, mat);
                    mesh.castShadow = true;
                    mesh.receiveShadow = true;
                    mesh.userData = {
                        cellIndex,
                        coords: { face: fIdx, row, col },
                        basePos
                    };

                    this.cellGroup.add(mesh);
                    this.cellMeshes[cellIndex] = mesh;
                    cellIndex++;
                }
            }
        });
    }

    _buildHyper4Board() {
        // 4x4x4 grid, cell size 0.65
        const geom = new THREE.BoxGeometry(0.65, 0.65, 0.65);

        for (let z = 0; z < 4; z++) {
            for (let y = 0; y < 4; y++) {
                for (let x = 0; x < 4; x++) {
                    const cellIndex = x + y * 4 + z * 16;
                    const basePos = new THREE.Vector3((x - 1.5) * 0.8, (y - 1.5) * 0.8, (z - 1.5) * 0.8);

                    const mat = new THREE.MeshStandardMaterial({
                        color: 0x1e293b,
                        roughness: 0.3,
                        metalness: 0.1,
                        transparent: true,
                        opacity: 0.95
                    });

                    const mesh = new THREE.Mesh(geom, mat);
                    mesh.userData = {
                        cellIndex,
                        coords: { x, y, z },
                        basePos
                    };

                    this.cellGroup.add(mesh);
                    this.cellMeshes[cellIndex] = mesh;
                }
            }
        }
    }

    // ==========================================
    // Procedural Dynamic Canvas Texture for Cells
    // ==========================================
    generateCellTexture(value, isClue, notes, styleType) {
        const cacheKey = `${value}_${isClue}_${Array.from(notes || []).join(',')}_${styleType}`;
        if (this.textureCache.has(cacheKey)) {
            return this.textureCache.get(cacheKey);
        }

        const size = 256;
        const canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;
        const ctx = canvas.getContext('2d');

        // Background color based on style
        let bgColor = this.colors.baseEmpty;
        let borderColor = this.colors.borderNormal;
        let borderWidth = 6;

        if (styleType === 'conflict') {
            bgColor = this.colors.conflict;
            borderColor = this.colors.conflictBorder;
            borderWidth = 10;
        } else if (styleType === 'selected') {
            bgColor = '#1e1b4b'; // Deep violet
            borderColor = this.colors.selected;
            borderWidth = 12;
        } else if (styleType === 'matching') {
            bgColor = this.colors.matchingNumber;
            borderColor = this.colors.matchingBorder;
            borderWidth = 8;
        } else if (styleType === 'connected') {
            bgColor = this.colors.connected;
            borderColor = this.colors.connectedBorder;
            borderWidth = 6;
        } else if (isClue) {
            bgColor = this.colors.baseClue;
            borderColor = this.colors.borderClue;
        } else if (value > 0) {
            bgColor = this.colors.baseUser;
            borderColor = '#0e7490';
        }

        // Draw rounded rectangle background
        ctx.fillStyle = bgColor;
        ctx.fillRect(0, 0, size, size);

        // Draw border
        ctx.strokeStyle = borderColor;
        ctx.lineWidth = borderWidth;
        ctx.strokeRect(borderWidth / 2, borderWidth / 2, size - borderWidth, size - borderWidth);

        // Render main number or notes
        if (value > 0) {
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.font = `bold ${isClue ? '118px' : '108px'} 'Segoe UI', system-ui, sans-serif`;

            // Text glow / color
            if (isClue) {
                ctx.fillStyle = this.colors.textClue;
            } else {
                ctx.fillStyle = this.colors.textUser;
                ctx.shadowColor = '#38bdf8';
                ctx.shadowBlur = 10;
            }

            ctx.fillText(value.toString(), size / 2, size / 2 + 5);
        } else if (notes && notes.size > 0) {
            // Render 3x3 mini grid for notes
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.font = "bold 26px 'Segoe UI', system-ui, sans-serif";
            ctx.fillStyle = this.colors.textNote;

            for (let n = 1; n <= 9; n++) {
                if (notes.has(n)) {
                    const row = Math.floor((n - 1) / 3);
                    const col = (n - 1) % 3;
                    const x = 50 + col * 78;
                    const y = 50 + row * 78;
                    ctx.fillText(n.toString(), x, y);
                }
            }
        }

        const texture = new THREE.CanvasTexture(canvas);
        texture.anisotropy = 4;
        this.textureCache.set(cacheKey, texture);
        return texture;
    }

    // ==========================================
    // Update All Cells Visuals (Numbers, Colors)
    // ==========================================
    updateCells(gameState) {
        const {
            mode,
            currentBoard,
            initialClues,
            notes,
            selectedCell,
            conflicts,
            connectedCells
        } = gameState;

        this.selectedCellIndex = selectedCell;
        const selectedValue = (selectedCell !== null && selectedCell !== undefined && selectedCell >= 0)
            ? currentBoard[selectedCell] : 0;

        this.cellMeshes.forEach((mesh, idx) => {
            if (!mesh) return;
            const val = currentBoard[idx] || 0;
            const isClue = initialClues[idx] || false;
            const cellNotes = notes[idx] || new Set();

            // Determine cell highlight status
            let styleType = 'normal';
            if (conflicts && conflicts.has(idx)) {
                styleType = 'conflict';
            } else if (idx === selectedCell) {
                styleType = 'selected';
            } else if (selectedValue > 0 && val === selectedValue) {
                styleType = 'matching';
            } else if (connectedCells && connectedCells.has(idx)) {
                styleType = 'connected';
            }

            // Generate or get cached texture
            const texture = this.generateCellTexture(val, isClue, cellNotes, styleType);

            // Apply texture to mesh material
            mesh.material.map = texture;
            mesh.material.needsUpdate = true;

            // Handle slice filter opacity
            this._applySliceFilter(mesh, idx, mode);
        });
    }

    _applySliceFilter(mesh, idx, mode) {
        const { axis, layer } = this.sliceFilter;
        let isMatch = true;

        if (axis !== 'all') {
            const coords = mesh.userData.coords;
            if (mode === 'surface') {
                if (axis === 'face') isMatch = (coords.face === layer);
            } else {
                if (axis === 'x') isMatch = (coords.x === layer);
                else if (axis === 'y') isMatch = (coords.y === layer);
                else if (axis === 'z') isMatch = (coords.z === layer);
            }
        }

        if (isMatch) {
            mesh.material.opacity = 0.95;
            mesh.material.transparent = false;
            mesh.visible = true;
        } else {
            // Ghost / faded mode
            mesh.material.opacity = 0.12;
            mesh.material.transparent = true;
            mesh.visible = true;
        }
    }

    setSliceFilter(axis, layer) {
        this.sliceFilter = { axis, layer };
    }

    setExplodeFactor(factor) {
        this.targetExplodeFactor = Math.max(0.0, Math.min(1.5, factor));
    }

    updateCellPositions() {
        const factor = 1.0 + this.explodeFactor * 0.75;
        this.cellMeshes.forEach(mesh => {
            if (!mesh) return;
            const base = mesh.userData.basePos;
            mesh.position.set(base.x * factor, base.y * factor, base.z * factor);
        });
    }

    // ==========================================
    // Camera View Presets
    // ==========================================
    resetCamera() {
        this.camera.position.set(5.5, 4.8, 6.5);
        this.controls.target.set(0, 0, 0);
        this.controls.update();
    }

    setTopView() {
        this.camera.position.set(0, 8.5, 0.01);
        this.controls.target.set(0, 0, 0);
        this.controls.update();
    }

    setFrontView() {
        this.camera.position.set(0, 0, 8.5);
        this.controls.target.set(0, 0, 0);
        this.controls.update();
    }

    setSideView() {
        this.camera.position.set(8.5, 0, 0);
        this.controls.target.set(0, 0, 0);
        this.controls.update();
    }

    toggleAutoRotate() {
        this.controls.autoRotate = !this.controls.autoRotate;
        return this.controls.autoRotate;
    }

    // ==========================================
    // Animation Loop
    // ==========================================
    animate() {
        requestAnimationFrame(this.animate);

        // Smoothly interpolate explode factor
        if (Math.abs(this.explodeFactor - this.targetExplodeFactor) > 0.001) {
            this.explodeFactor += (this.targetExplodeFactor - this.explodeFactor) * 0.12;
            this.updateCellPositions();
        }

        // OrbitControls update
        this.controls.update();

        // Render
        this.renderer.render(this.scene, this.camera);
    }
}

window.Sudoku3DView = Sudoku3DView;
