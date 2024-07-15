import VideoApp from './VideoApp.js';

document.addEventListener('DOMContentLoaded', (event) => {
    const videoApp = new VideoApp();

    const urlParams = new URLSearchParams(window.location.search);
    const feedUrl = urlParams.get('feed_url');

    if (feedUrl) {
        console.log('feed_url detected:', feedUrl);
        videoApp.feeds_from_feed_url(feedUrl);
    } else {
        videoApp.feeds_from_keyword('Lo-Fi'); // feed_urlがない場合のみ呼び出し
    }

    $('#keywordForm').on('submit', function (event) {
        event.preventDefault();
        console.log('keywordForm submit, keyword:', $('#keyword').val());
        videoApp.feeds_from_keyword($('#keyword').val());
    });

    $.get('/api/check-login', function (data) {
        console.log('check-login API called, data received:', data);
        if (data.loggedIn) {
            console.log('User is logged in, fetching playlists...');
            videoApp.updatePlaylistsDropdown();

            $('#playlists').on('change', function (event) {
                videoApp.handlePlaylistChange(event);
                var playlistId = $(this).val();
                console.log('Playlist changed, playlistId:', playlistId);
                if (playlistId) {
                    $.ajax({
                        url: '/api/playlist-videos',
                        method: 'GET',
                        data: { playlistId: playlistId },
                        success: function (data) {
                            console.log('playlist-videos API called, data received:', data);
                            try {
                                if (typeof data === 'string') {
                                    data = JSON.parse(data);
                                }

                                if (data.error) {
                                    throw new Error(data.error);
                                }

                                if (Array.isArray(data)) {
                                    let videoListHtml = '<ul>';
                                    let firstVideoId = null;
                                    data.forEach((video, index) => {
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
                                        videoApp.playVideo(firstVideoId);
                                    }

                                    // 再生ボタンにイベントリスナーを追加
                                    $('.play-button').on('click', function() {
                                        const videoId = $(this).data('video-id');
                                        videoApp.playVideo(videoId);
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
            });
        } else {
            console.log('User is not logged in, hiding playlists');
            $('#playlists-container').hide();
        }
    });

    // ページングリンクのイベントリスナーを追加
    $(document).on('click', '.prev-page, .next-page', function (event) {
        event.preventDefault();
        const pageToken = $(this).data('page-token');
        videoApp.feeds_from_keyword($('#keyword').val(), pageToken);
    });

    // タグクリック時のイベントリスナーを追加
    $(document).on('click', '.keyword-tag', function (event) {
        event.preventDefault();
        const keyword = $(this).text();
        console.log('Keyword tag clicked, keyword:', keyword);
        videoApp.feeds_from_keyword(keyword);
    });
});

window.onerror = function (message, source, lineno, colno, error) {
    if (source.includes('youtube')) {
        console.log('YouTube player error ignored:', message);
        return true; // true を返すとエラーが無視されます
    }
    return false; // 他のエラーは通常通り処理されます
};

