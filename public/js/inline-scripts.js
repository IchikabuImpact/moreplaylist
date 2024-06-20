// public/js/inline-scripts.js

document.addEventListener('DOMContentLoaded', () => {
    function updateButtonState(button, isPlaying) {
        if (isPlaying) {
            button.src = '/images/pause_button.png';
            button.classList.remove('play-button');
            button.classList.add('pause-button');
        } else {
            button.src = '/images/play_button.png';
            button.classList.remove('pause-button');
            button.classList.add('play-button');
        }
    }

    document.querySelectorAll('.play-button').forEach(button => {
        button.addEventListener('click', () => {
            updateButtonState(button, true);
            // 再生処理を実行
        });
    });

    document.querySelectorAll('.pause-button').forEach(button => {
        button.addEventListener('click', () => {
            updateButtonState(button, false);
            // 一時停止処理を実行
        });
    });
});

