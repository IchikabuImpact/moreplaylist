import { rpcCall, RpcError } from './apiClient.js';

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
        const playlistsDropdown = document.getElementById('playlists');
        const shuffleButton = document.getElementById('shuffleButton');

        if (logoutLink) {
            logoutLink.addEventListener('click', this.handleLogout);
        }

        if (copyButton) {
            copyButton.addEventListener('click', this.handleCopy);
        }

        if (playlistsDropdown) {
            playlistsDropdown.addEventListener('change', this.handlePlaylistChange.bind(this));
        }

        if (shuffleButton) {
            shuffleButton.addEventListener('click', this.shufflePlay.bind(this));
        }
    }
async createPlaylist(playlistName, playlistPrivacy, videoId) {
    try {
        const response = await fetch('/api/add-playlist', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                new_playlist_title: playlistName, 
                new_playlist_privacy: playlistPrivacy, 
                video_id: videoId 
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        console.log('Playlist created:', data);
        this.updatePlaylistsDropdown();
    } catch (error) {
        console.error('Error creating playlist:', error);
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

    async generateShortUrl(originalUrl) {
        console.log('Generating short URL for:', originalUrl);
        try {
            const response = await fetch('/shorten-url.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ originalUrl: originalUrl })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            console.log('Short URL data:', data);

            if (data.link) {
                return data.link;
            } else {
                throw new Error('Failed to generate short URL');
            }
        } catch (error) {
            console.error('Error:', error);
            throw error;
        }
    }

    async handlePlaylistChange(event) {
        const playlistId = event.target.value;
        if (playlistId) {
            const originalUrl = `${window.location.origin}/Index?feed_url=${encodeURIComponent(`https://www.youtube.com/playlist?list=${playlistId}`)}`;
            const hiddenOriginal = document.getElementById('share_url_shorten');
            if (hiddenOriginal) {
                hiddenOriginal.value = originalUrl;
            }
            try {
                const shortUrl = await this.generateShortUrl(originalUrl);
                document.getElementById('feed_share_url').value = shortUrl;
            } catch (error) {
                console.error('Failed to generate short URL:', error);
                document.getElementById('feed_share_url').value = originalUrl;
            }
        }
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
                        let videoListHtml = '<div id="playlistarea"><p>Playlist</p></div><ul>';
                        let firstVideoId = null;
                        data.videos.forEach((video, index) => {
                            if (index === 0) {
                                firstVideoId = video.videoId;
                            }
                            videoListHtml += `
                                <li>
                                    <button class="mp-playpause play-button" type="button" data-video-id="${video.videoId}" aria-label="Play" aria-pressed="false"></button>
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
        var playerUrl = `https://www.youtube.com/embed/${videoId}?rel=1&showinfo=0&controls=1&autoplay=1&enablejsapi=1&allowScriptAccess=always&origin=${window.location.origin}&widgetid=1`;
        $('#player').attr('src', playerUrl);
    }

    attachClickEvents() {
        console.log('attachClickEvents called');
        $('#video-list').off('click', 'button.mp-playpause.play-button');
        $('#video-list').off('click', 'button.mp-playpause.pause-button');

        $('#video-list').on('click', 'button.mp-playpause.play-button', (event) => {
            event.preventDefault();
            const button = event.target;
            const videoId = $(button).data('video-id');
            console.log('Play button clicked, videoId:', videoId);
            $('#videoInfo').data('current-video-id', videoId);
            this.playVideo(videoId);
            this.resetPauseButtons();
            this.updateButtonState(button, true);
        });

        $('#video-list').on('click', 'button.mp-playpause.pause-button', (event) => {
            event.preventDefault();
            const button = event.target;
            console.log('Pause button clicked');
            this.updateButtonState(button, false);
            // 一時停止処理を実行（必要に応じて追加）
            // 一時停止がこれじゃないと動かないので暫定的に対応
            document.getElementById('player').contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*');

        });
    }

    resetPauseButtons() {
        $('#video-list button.mp-playpause.pause-button').each((index, button) => {
            this.updateButtonState(button, false);
        });
    }

    updateButtonState(button, isPlaying) {
        button.classList.toggle('is-playing', isPlaying);
        button.classList.toggle('pause-button', isPlaying);
        button.classList.toggle('play-button', !isPlaying);
        button.setAttribute('aria-pressed', String(isPlaying));
        button.setAttribute('aria-label', isPlaying ? 'Pause' : 'Play');
    }

    updatePlaylistsDropdown() {
        const loadPlaylists = async () => {
            try {
                const data = await rpcCall('playlist.list', {});
                console.log('playlist.list RPC called, data received:', data);

                var $select = $('#playlists');
                $select.empty();
                data.playlists.forEach(function (playlist) {
                    $select.append($('<option></option>')
                        .attr('value', playlist.playlistId)
                        .text(playlist.title + (playlist.status === 'private' ? ' (非公開)' : '')));
                });

                console.log('Playlists updated:', data);
            } catch (error) {
                console.error('playlist.list RPC error:', error);
                if (error instanceof RpcError && error.code === 40100) {
                    this.showLoginPrompt(error.data?.loginUrl || '/Index/oauth');
                } else {
                    this.showLoginPrompt('/Index/oauth');
                }
            }
        };

        loadPlaylists();
    }

    showLoginPrompt(loginUrl) {
        const $playlists = $('#playlists');
        $('#playlist-login-prompt').remove();
        $playlists.after(
            `<p id="playlist-login-prompt" class="error">ログインしてください: <a href="${loginUrl}">Login</a></p>`
        );
    }

    shufflePlay() {
        console.log('Shuffle play button clicked');
        const videoItems = $('#video-list').find('li');
        if (videoItems.length > 0) {
            const randomIndex = Math.floor(Math.random() * videoItems.length);
            const randomVideoId = $(videoItems[randomIndex]).find('.mp-playpause').data('video-id');
            this.playVideo(randomVideoId);
        } else {
            console.error('No videos found');
            alert('No videos available to shuffle play.');
        }
    }

feeds_from_feed_url(feedUrl) {
    console.log('feeds_from_feed_url called with feedUrl:', feedUrl);
    $('#videoInfo').removeData('current-video-id');
    $.ajax({
        url: '/api/videos',
        method: 'GET',
        data: { feed_url: feedUrl },
        success: (data) => {
            console.log('feeds_from_feed_url success, data received:', data);
            try {
                if (typeof data === 'string') {
                    data = JSON.parse(data);
                }

                if (Array.isArray(data.videos)) {
                    let videoListHtml = '<div id="playlistarea"><p>Playlist</p></div><ul>';
                    let firstVideoId = null;
                    data.videos.forEach((video, index) => {
                        if (index === 0) {
                            firstVideoId = video.videoId;
                        }
                        videoListHtml += `
                            <li>
                                <button class="mp-playpause play-button" type="button" data-video-id="${video.videoId}" aria-label="Play" aria-pressed="false"></button>
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
                        this.feeds_from_feed_url(feedUrl, pageToken);
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





}
