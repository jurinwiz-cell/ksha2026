/**
 * 3D Sudoku - Audio Engine (Web Audio API Synthesizer)
 * No external audio files needed; 100% synthesized pure tones.
 */

class SoundEngine {
    constructor() {
        this.ctx = null;
        this.soundEnabled = true;
        this.bgmEnabled = false;
        this.bgmNode = null;
        this.bgmGain = null;
        this.bgmTimer = null;
        
        // Scale notes for numbers 1 to 9 (Pentatonic / Major scale: C4 to D5)
        this.numberFrequencies = [
            261.63, // 1: C4
            293.66, // 2: D4
            329.63, // 3: E4
            349.23, // 4: F4
            392.00, // 5: G4
            440.00, // 6: A4
            493.88, // 7: B4
            523.25, // 8: C5
            587.33  // 9: D5
        ];
    }

    init() {
        if (!this.ctx) {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            this.ctx = new AudioContext();
        }
        if (this.ctx.state === 'suspended') {
            this.ctx.resume();
        }
    }

    toggleSound() {
        this.soundEnabled = !this.soundEnabled;
        return this.soundEnabled;
    }

    toggleBGM() {
        this.init();
        this.bgmEnabled = !this.bgmEnabled;
        if (this.bgmEnabled) {
            this.startBGM();
        } else {
            this.stopBGM();
        }
        return this.bgmEnabled;
    }

    // Play a gentle click when selecting a cell
    playSelect() {
        if (!this.soundEnabled) return;
        this.init();

        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();

        osc.type = 'sine';
        osc.frequency.setValueAtTime(600, this.ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(300, this.ctx.currentTime + 0.05);

        gain.gain.setValueAtTime(0.08, this.ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + 0.05);

        osc.connect(gain);
        gain.connect(this.ctx.destination);

        osc.start();
        osc.stop(this.ctx.currentTime + 0.05);
    }

    // Play pitch-matched musical chime for number input
    playNumber(num) {
        if (!this.soundEnabled || num < 1 || num > 9) return;
        this.init();

        const freq = this.numberFrequencies[num - 1];
        const osc = this.ctx.createOscillator();
        const oscHarmonic = this.ctx.createOscillator();
        const gain = this.ctx.createGain();

        // Warm electric piano / marimba tone
        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, this.ctx.currentTime);

        oscHarmonic.type = 'triangle';
        oscHarmonic.frequency.setValueAtTime(freq * 2, this.ctx.currentTime);

        gain.gain.setValueAtTime(0.18, this.ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + 0.35);

        osc.connect(gain);
        oscHarmonic.connect(gain);
        gain.connect(this.ctx.destination);

        osc.start();
        oscHarmonic.start();
        osc.stop(this.ctx.currentTime + 0.35);
        oscHarmonic.stop(this.ctx.currentTime + 0.35);
    }

    // Play erase tone
    playErase() {
        if (!this.soundEnabled) return;
        this.init();

        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();

        osc.type = 'triangle';
        osc.frequency.setValueAtTime(350, this.ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(150, this.ctx.currentTime + 0.12);

        gain.gain.setValueAtTime(0.1, this.ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + 0.12);

        osc.connect(gain);
        gain.connect(this.ctx.destination);

        osc.start();
        osc.stop(this.ctx.currentTime + 0.12);
    }

    // Play error / conflict buzz
    playError() {
        if (!this.soundEnabled) return;
        this.init();

        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();

        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(130, this.ctx.currentTime);
        osc.frequency.setValueAtTime(110, this.ctx.currentTime + 0.08);

        gain.gain.setValueAtTime(0.15, this.ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + 0.25);

        osc.connect(gain);
        gain.connect(this.ctx.destination);

        osc.start();
        osc.stop(this.ctx.currentTime + 0.25);
    }

    // Hint sound (sparkle)
    playHint() {
        if (!this.soundEnabled) return;
        this.init();

        const notes = [523.25, 659.25, 783.99, 1046.50]; // C5, E5, G5, C6
        notes.forEach((freq, idx) => {
            const osc = this.ctx.createOscillator();
            const gain = this.ctx.createGain();
            const startTime = this.ctx.currentTime + idx * 0.07;

            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, startTime);

            gain.gain.setValueAtTime(0.12, startTime);
            gain.gain.exponentialRampToValueAtTime(0.001, startTime + 0.25);

            osc.connect(gain);
            gain.connect(this.ctx.destination);

            osc.start(startTime);
            osc.stop(startTime + 0.25);
        });
    }

    // Victory Fanfare
    playVictory() {
        if (!this.soundEnabled) return;
        this.init();

        const melody = [
            { f: 523.25, t: 0.0, d: 0.15 }, // C5
            { f: 523.25, t: 0.15, d: 0.15 },// C5
            { f: 523.25, t: 0.3, d: 0.15 }, // C5
            { f: 659.25, t: 0.45, d: 0.3 },  // E5
            { f: 783.99, t: 0.75, d: 0.2 },  // G5
            { f: 1046.5, t: 0.95, d: 0.6 }   // C6
        ];

        melody.forEach(m => {
            const osc = this.ctx.createOscillator();
            const gain = this.ctx.createGain();
            const startTime = this.ctx.currentTime + m.t;

            osc.type = 'triangle';
            osc.frequency.setValueAtTime(m.f, startTime);

            gain.gain.setValueAtTime(0.2, startTime);
            gain.gain.exponentialRampToValueAtTime(0.001, startTime + m.d);

            osc.connect(gain);
            gain.connect(this.ctx.destination);

            osc.start(startTime);
            osc.stop(startTime + m.d);
        });
    }

    // Ambient Chill BGM loop
    startBGM() {
        if (this.bgmTimer) return;
        const chords = [
            [261.63, 329.63, 392.00], // C maj
            [220.00, 261.63, 329.63], // A min
            [174.61, 220.00, 261.63], // F maj
            [196.00, 246.94, 293.66]  // G maj
        ];
        let chordIndex = 0;

        const playNextChord = () => {
            if (!this.bgmEnabled) return;
            const chord = chords[chordIndex % chords.length];
            chordIndex++;

            chord.forEach(freq => {
                const osc = this.ctx.createOscillator();
                const filter = this.ctx.createBiquadFilter();
                const gain = this.ctx.createGain();

                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, this.ctx.currentTime);

                filter.type = 'lowpass';
                filter.frequency.setValueAtTime(600, this.ctx.currentTime);

                gain.gain.setValueAtTime(0.001, this.ctx.currentTime);
                gain.gain.linearRampToValueAtTime(0.02, this.ctx.currentTime + 1.2);
                gain.gain.linearRampToValueAtTime(0.001, this.ctx.currentTime + 3.8);

                osc.connect(filter);
                filter.connect(gain);
                gain.connect(this.ctx.destination);

                osc.start();
                osc.stop(this.ctx.currentTime + 4.0);
            });

            this.bgmTimer = setTimeout(playNextChord, 3800);
        };

        playNextChord();
    }

    stopBGM() {
        if (this.bgmTimer) {
            clearTimeout(this.bgmTimer);
            this.bgmTimer = null;
        }
    }
}

window.soundEngine = new SoundEngine();
