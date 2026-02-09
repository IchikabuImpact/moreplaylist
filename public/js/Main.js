import VideoApp from './VideoApp.js';
import { rpcCall, RpcError } from './apiClient.js';

document.addEventListener('DOMContentLoaded', (event) => {
    const videoApp = new VideoApp();

    const urlParams = new URLSearchParams(window.location.search);
    const feedUrl = urlParams.get('feed_url');

    if (feedUrl) {
        console.log('feed_url detected:', feedUrl);
        // サーバーサイドからの動画リストが存在するか確認
        const serverRenderedVideos = []; // サーバーサイドから渡された動画リストがここに格納される想定

        if (serverRenderedVideos.length > 0) {
            videoApp.renderVideos(serverRenderedVideos);
            if (serverRenderedVideos[0] && serverRenderedVideos[0].videoId) {
                videoApp.playVideo(serverRenderedVideos[0].videoId);
            }
        } else {
            videoApp.feeds_from_feed_url(feedUrl);
        }
    } else {
        videoApp.feeds_from_keyword('lo fi jazz'); // feed_urlがない場合のみ呼び出し
    }

    $('#keywordForm').on('submit', function (event) {
        event.preventDefault();
        console.log('keywordForm submit, keyword:', $('#keyword').val());
        videoApp.feeds_from_keyword($('#keyword').val());
    });

    const initializeLoginState = async () => {
        try {
            const data = await rpcCall('auth.status', {});
            console.log('auth.status RPC called, data received:', data);
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
                                                    <button class="mp-playpause play-button" type="button" data-video-id="${video.videoId}" aria-label="Play" aria-pressed="false"></button>
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
                                        videoApp.attachClickEvents();
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

                $('#add_to_new_playlist').on('click', function () {
                    const playlistName = $('#new_playlist_title').val();
                    const playlistPrivacy = $('#new_playlist_privacy').val();
                    const videoId = $('#videoInfo').data('current-video-id'); // 再生中の動画IDを取得
                    if (playlistName && videoId) {
                       videoApp.createPlaylist(playlistName, playlistPrivacy, videoId);
                    } else {
                        alert('Please enter a name for the new playlist and ensure a video is playing.');
                    }
                });

            } else {
                console.log('User is not logged in, hiding playlists');
                $('#playlists-container').hide();
            }
        } catch (error) {
            if (error instanceof RpcError && error.code === 40100) {
                console.log('User is not logged in, hiding playlists');
                $('#playlists-container').hide();
            } else {
                console.error('auth.status RPC error:', error);
            }
        }
    };

    initializeLoginState();

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
