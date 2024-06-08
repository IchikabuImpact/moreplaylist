export default class VideoApp {
    constructor() {
        this.init();
    }

    init() {
        this.setupEventListeners();
    }

    setupEventListeners() {
        const logoutLink = document.getElementById('logoutLink');
        const copyButton = document.getElementById('copyButton');

        if (logoutLink) {
            logoutLink.addEventListener('click', this.handleLogout);
        }

        if (copyButton) {
            copyButton.addEventListener('click', this.handleCopy);
        }
    }

    handleLogout(event) {
        event.preventDefault();
        window.location.href = '/logout';
    }

    handleCopy(event) {
        event.preventDefault();
        const copyText = document.getElementById('feed_share_url').value;
        navigator.clipboard.writeText(copyText).then(() => {
            alert('URLがクリップボードにコピーされました: ' + copyText);
        }).catch(err => {
            console.error('クリップボードにコピーできませんでした: ', err);
        });
    }

    feeds_from_keyword(keyword, pageToken = '') {
        console.log('feeds_from_keyword called with keyword:', keyword, 'and pageToken:', pageToken);
        $('#videoInfo').removeData('current-video-id');
        $.ajax({
            url: '/api/videos',
            method: 'GET',
            data: { keyword: keyword, pageToken: pageToken },
            success: (data) => {
                console.log('feeds_from_keyword success, data received:', data);
                try {
                    if (typeof data === 'string') {
                        data = JSON.parse(data);
                    }

                    if (Array.isArray(data.videos)) {
                        let videoListHtml = '<ul>';
                        let firstVideoId = null;
                        data.videos.forEach((video, index) => {
                            if (index === 0) {
                                firstVideoId = video.videoId;
                            }
                            videoListHtml += `
                                <li>
                                    <img src="/images/play_button.png" alt="Play" class="play-button" data-video-id="${video.videoId}">
                                    <span>${video.title}</span>
                                </li>
                            `;
                        });
                        videoListHtml += '</ul>';
                        $('#video-list').html(videoListHtml);
                        if (firstVideoId) {
                            this.playVideo(firstVideoId);
                        }
                        this.attachClickEvents();

                        // ページングリンクの生成
                        let paginationHtml = '';
                        if (data.prevPageToken) {
                            paginationHtml += `<a href="#" class="prev-page" data-page-token="${data.prevPageToken}">Previous</a>`;
                        }
                        if (data.prevPageToken && data.nextPageToken) {
                            paginationHtml += ' | ';
                        }
                        if (data.nextPageToken) {
                            paginationHtml += `<a href="#" class="next-page" data-page-token="${data.nextPageToken}">Next</a>`;
                        }
                        $('#pagination').html(paginationHtml);

                        // ページングリンクのイベントリスナーを追加
                        $('.prev-page, .next-page').on('click', (event) => {
                            event.preventDefault();
                            const pageToken = $(event.target).data('page-token');
                            this.feeds_from_keyword(keyword, pageToken);
                        });

                    } else {
                        throw new Error('Invalid data format');
                    }
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                    $('#video-list').html('<p>Error parsing video data.</p>');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error: ' + textStatus + ': ' + errorThrown);
                $('#video-list').html('<p>Failed to load videos. ' + textStatus + ': ' + errorThrown + '</p>');
            }
        });
    }

    playVideo(videoId) {
        console.log('playVideo called with videoId:', videoId);
        var playerUrl = 'https://www.youtube.com/embed/' + videoId + '?rel=1&showinfo=0&controls=1&autoplay=1&enablejsapi=1&allowScriptAccess=always&origin=https://moreplaylist.appstarrocks.com&widgetid=1';
        $('#player').attr('src', playerUrl);
    }

    attachClickEvents() {
        console.log('attachClickEvents called');
        $('#video-list').on('click', 'img.play-button', (event) => {
            event.preventDefault();
            const button = event.target;
            const videoId = $(button).data('video-id');
            console.log('Play button clicked, videoId:', videoId);
            $('#videoInfo').data('current-video-id', videoId);
            this.playVideo(videoId);
            this.resetPauseButtons();
            this.updateButtonState(button, true);
        });

        $('#video-list').on('click', 'img.pause-button', (event) => {
            event.preventDefault();
            const button = event.target;
            console.log('Pause button clicked');
            this.updateButtonState(button, false);
            // 一時停止処理を実行（必要に応じて追加）
        });
    }

    resetPauseButtons() {
        $('#video-list img.pause-button').each((index, button) => {
            this.updateButtonState(button, false);
        });
    }

    updateButtonState(button, isPlaying) {
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

    updatePlaylistsDropdown() {
        $.get('/api/playlists', (data) => {
            console.log('playlists API called, data received:', data);
            try {
                if (typeof data === 'string') {
                    data = JSON.parse(data);
                }

                if (data.error) {
                    throw new Error(data.error);
                }

                var $select = $('#playlists');
                $select.empty();
                data.forEach(function (playlist) {
                    $select.append($('<option></option>')
                        .attr('value', playlist.playlistId)
                        .text(playlist.title + (playlist.status === 'private' ? ' (非公開)' : '')));
                });

                console.log('Playlists updated:', data);

            } catch (e) {
                console.error('Error:', e);
                $('#playlists').after('<p class="error">please login</p>');
            }
        });
    }
}

