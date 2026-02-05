import { describe, it, expect } from 'vitest';
import { loadScript } from './helpers/loadScript.js';

const baseDom = `
  <button class="mp-playpause play-button" id="play" aria-label="Play" aria-pressed="false"></button>
  <button class="mp-playpause pause-button is-playing" id="pause" aria-label="Pause" aria-pressed="true"></button>
`;

describe('inline-scripts characterization', () => {
  it('toggles play/pause button state on click', () => {
    document.body.innerHTML = baseDom;

    loadScript('public/js/inline-scripts.js');
    document.dispatchEvent(new Event('DOMContentLoaded'));

    const playButton = document.getElementById('play');
    const pauseButton = document.getElementById('pause');

    playButton.click();
    expect(playButton.classList.contains('pause-button')).toBe(true);
    expect(playButton.classList.contains('is-playing')).toBe(true);
    expect(playButton.getAttribute('aria-label')).toBe('Pause');
    expect(playButton.getAttribute('aria-pressed')).toBe('true');

    pauseButton.click();
    expect(pauseButton.classList.contains('play-button')).toBe(true);
    expect(pauseButton.classList.contains('is-playing')).toBe(false);
    expect(pauseButton.getAttribute('aria-label')).toBe('Play');
    expect(pauseButton.getAttribute('aria-pressed')).toBe('false');
  });
});
