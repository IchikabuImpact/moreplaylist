// public/js/inline-scripts.js

document.addEventListener('DOMContentLoaded', () => {
    function updateButtonState(button, isPlaying) {
        button.classList.toggle('is-playing', isPlaying);
        button.classList.toggle('pause-button', isPlaying);
        button.classList.toggle('play-button', !isPlaying);
        button.setAttribute('aria-pressed', String(isPlaying));
        button.setAttribute('aria-label', isPlaying ? 'Pause' : 'Play');
    }

    document.querySelectorAll('.mp-playpause.play-button').forEach(button => {
        button.addEventListener('click', () => {
            updateButtonState(button, true);
            // 再生処理を実行
        });
    });

    document.querySelectorAll('.mp-playpause.pause-button').forEach(button => {
        button.addEventListener('click', () => {
            updateButtonState(button, false);
            // 一時停止処理を実行
        });
    });
});
