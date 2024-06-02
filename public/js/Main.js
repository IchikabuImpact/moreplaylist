import VideoApp from './VideoApp.js';

document.addEventListener('DOMContentLoaded', (event) => {
    const videoApp = new VideoApp();

    $('#keywordForm').on('submit', function (event) {
        event.preventDefault();
        console.log('keywordForm submit, keyword:', $('#keyword').val());
        videoApp.feeds_from_keyword($('#keyword').val());
    });
    videoApp.feeds_from_keyword('Lo-Fi');

    $.get('/api/check-login', function (data) {
        console.log('check-login API called, data received:', data);
        if (data.loggedIn) {
            console.log('User is logged in, fetching playlists...');
            videoApp.updatePlaylistsDropdown();

            $('#playlists').on('change', function () {
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
                                                <a href="#" data-video-id="${video.videoId}">${video.title}</a>
                                            </li>
                                        `;
                                    });
                                    videoListHtml += '</ul>';
                                    $('#video-list').html(videoListHtml);
                                    if (firstVideoId) {
                                        videoApp.playVideo(firstVideoId);
                                    }
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

                    $.ajax({
                        url: '/api/generate-share-url',
                        method: 'GET',
                        data: { playlistId: playlistId },
                        success: function (data) {
                            console.log('generate-share-url API called, data received:', data);
                            try {
                                if (typeof data === 'string') {
                                    data = JSON.parse(data);
                                }

                                if (data.share_url) {
                                    $('#feed_share_url').val(data.share_url);
                                } else {
                                    throw new Error('Share URL not provided');
                                }
                            } catch (e) {
                                console.error('Error parsing JSON:', e);
                                alert('Error generating share URL.');
                            }
                        },
                        error: function (jqXHR, textStatus, errorThrown) {
                            console.error('AJAX Error: ' + textStatus + ': ' + errorThrown);
                            alert('Failed to generate share URL. ' + textStatus + ': ' + errorThrown);
                        }
                    });
                }
            });
        } else {
            console.log('User is not logged in, hiding playlists');
            // 非ログイン時はプレイリストを非表示にする
            $('#playlists-container').hide();
        }
    });
});
window.onerror = function (message, source, lineno, colno, error) {
    if (source.includes('youtube')) {
        console.log('YouTube player error ignored:', message);
        return true; // true を返すとエラーが無視されます
    }
    return false; // 他のエラーは通常通り処理されます
};

