import { describe, it, expect } from 'vitest';
import { loadScript } from './helpers/loadScript.js';

const baseDom = `
  <img class="play-button" id="play" src="/images/play_button.png" />
  <img class="pause-button" id="pause" src="/images/pause_button.png" />
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
    expect(playButton.getAttribute('src')).toBe('/images/pause_button.png');

    pauseButton.click();
    expect(pauseButton.classList.contains('play-button')).toBe(true);
    expect(pauseButton.getAttribute('src')).toBe('/images/play_button.png');
  });
});
