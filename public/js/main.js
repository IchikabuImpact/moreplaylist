var ytVideoApp = {
    feeds_from_keyword: function(keyword) {
        console.log('feeds_from_keyword called with keyword:', keyword);
        $('#videoInfo').removeData('current-video-id');  // キーワード検索時に現在の動画IDをリセット
        $.ajax({
            url: '/api/videos',
            method: 'GET',
            data: { keyword: keyword },
            success: function(data) {
                console.log('feeds_from_keyword success, data received:', data);
                try {
                    if (typeof data === 'string') {
                        data = JSON.parse(data);
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
                            ytVideoApp.playVideo(firstVideoId);
                        }
                        ytVideoApp.attachClickEvents();
                    } else {
                        throw new Error('Invalid data format');
                    }
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                    $('#video-list').html('<p>Error parsing video data.</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error: ' + textStatus + ': ' + errorThrown);
                $('#video-list').html('<p>Failed to load videos. ' + textStatus + ': ' + errorThrown + '</p>');
            }
        });
    },
    playVideo: function(videoId) {
        console.log('playVideo called with videoId:', videoId);
        var playerUrl = 'https://www.youtube.com/embed/' + videoId + '?rel=1&showinfo=0&controls=1&autoplay=1&enablejsapi=1&allowScriptAccess=always&origin=https://moreplaylist.appstarrocks.com&widgetid=1';
        $('#player').attr('src', playerUrl);
    },
    attachClickEvents: function() {
        console.log('attachClickEvents called');
        $('#video-list a').on('click', function(event) {
            event.preventDefault();
            var videoId = $(this).data('video-id');
            console.log('Video link clicked, videoId:', videoId);
            $('#videoInfo').data('current-video-id', videoId);  // 動画IDを設定
            ytVideoApp.playVideo(videoId);
        });
    },
    updatePlaylistsDropdown: function() {
        $.get('/api/playlists', function(data) {
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
                data.forEach(function(playlist) {
                    // 非公開プレイリストも表示する
                    $select.append($('<option></option>')
                        .attr('value', playlist.playlistId)
                        .text(playlist.title + (playlist.status === 'private' ? ' (非公開)' : '')));
                });

                console.log('Playlists updated:', data);

            } catch (e) {
                console.error('Error:', e);
                $('#playlists').after('<p class="error">プレイリストを取得できませんでした。ログインしてください。</p>');
            }
        });
    }
};

window.addEventListener("load", function(){
    console.log('window load event');
    window.cookieconsent.initialise({
        "palette": {
            "popup": {
                "background": "#000"
            },
            "button": {
                "background": "#f1d600"
            }
        },
        "theme": "classic",
        "content": {
            "message": "This website uses cookies to ensure you get the best experience on our website.",
            "dismiss": "Got it!",
            "link": "Learn more",
            "href": "/cookie-policy"
        }
    });
});

console.log('main.js loaded');

$(document).ready(function() {
    console.log('document ready');
    $('#keywordForm').on('submit', function(event) {
        event.preventDefault();
        console.log('keywordForm submit, keyword:', $('#keyword').val());
        ytVideoApp.feeds_from_keyword($('#keyword').val());
    });
    ytVideoApp.feeds_from_keyword('Lo-Fi');

    // ユーザーがログインしているかどうかをチェックするAPIを呼び出す
    $.get('/api/check-login', function(data) {
        console.log('check-login API called, data received:', data);
        if (data.loggedIn) {
            console.log('User is logged in, fetching playlists...');
            ytVideoApp.updatePlaylistsDropdown();

            $('#playlists').on('change', function() {
                var playlistId = $(this).val();
                console.log('Playlist changed, playlistId:', playlistId);
                if (playlistId) {
                    $.ajax({
                        url: '/api/playlist-videos',
                        method: 'GET',
                        data: { playlistId: playlistId },
                        success: function(data) {
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
                                        ytVideoApp.playVideo(firstVideoId);
                                    }
                                } else {
                                    throw new Error('Invalid data format');
                                }
                            } catch (e) {
                                console.error('Error parsing JSON:', e);
                                $('#video-list').html('<p>Error parsing video data.</p>');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error('AJAX Error: ' + textStatus + ': ' + errorThrown);
                            $('#video-list').html('<p>Failed to load videos. ' + textStatus + ': ' + errorThrown + '</p>');
                        }
                    });

                    // 共有URLを生成するAPIを呼び出す
                    $.ajax({
                        url: '/api/generate-share-url',
                        method: 'GET',
                        data: { playlistId: playlistId },
                        success: function(data) {
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
                        error: function(jqXHR, textStatus, errorThrown) {
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

    $('#video-list').on('click', 'a', function(e) {
        e.preventDefault();
        const videoId = $(this).data('video-id');
        console.log('Video link clicked, videoId:', videoId);
        $('#videoInfo').data('current-video-id', videoId);  // 動画IDを設定
        ytVideoApp.playVideo(videoId);
    });

    // 新しい再生リストを作成し、動画を追加する
    $('#add_to_new_playlist').on('click', function() {
        var videoId = $('#videoInfo').data('current-video-id');  // 動画IDを取得
        var playlistTitle = $('#new_playlist_title').val().trim();
        var privacyStatus = $('#new_playlist_privacy').val();

        console.log('Add to new playlist clicked, videoId:', videoId, 'playlistTitle:', playlistTitle, 'privacyStatus:', privacyStatus);

        if (!videoId || !playlistTitle) {
            alert('Please select a video and enter a playlist name.');
            return;
        }

        var params = JSON.stringify({
            video_id: videoId,
            playlist_title: playlistTitle,
            privacyStatus: privacyStatus
        });

        $.ajax({
            url: '/api/add-playlist',
            type: 'POST',
            data: params,
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    alert(data.success);
                    ytVideoApp.updatePlaylistsDropdown();  // プレイリストのドロップダウンを更新
                } else if (data.error) {
                    alert('Error: ' + data.error);
                } else {
                    alert('Unknown error occurred');
                }
            },
            error: function(xhr, status, error) {
                alert('Failed to add video to new playlist: ' + error);
            }
        });
    });

    $('#add_to_existing_playlist').on('click', function() {
        var videoId = $('#videoInfo').data('current-video-id');  // 動画IDを取得
        var playlistId = $('#playlists').val();

        console.log('Add to existing playlist clicked, videoId:', videoId, 'playlistId:', playlistId);

        if (!videoId || !playlistId) {
            alert('Please select a video and a playlist.');
            return;
        }

        var params = JSON.stringify({
            video_id: videoId,
            playlistId: playlistId
        });

        $.ajax({
            url: '/api/add-to-existing-playlist',
            type: 'POST',
            data: params,
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    alert(data.success);
                } else {
                    alert('Error: ' + data.error);
                }
            },
            error: function(xhr, status, error) {
                alert('Failed to add video to existing playlist: ' + error);
            }
        });
    });

    // キーワード検索時に現在の動画IDをリセット
    $('#keyword').on('input', function() {
        $('#videoInfo').removeData('current-video-id');
    });

    // シャッフル再生機能の追加
    $('#shuffleButton').on('click', function() {
        const videoIds = Array.from(document.querySelectorAll('#video-list a[data-video-id]')).map(a => a.getAttribute('data-video-id'));
        shuffleArray(videoIds);
        playVideos(videoIds);
    });

    function shuffleArray(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
    }

    function playVideos(videoIds) {
        let currentVideoIndex = 0;

        function playNextVideo() {
            if (currentVideoIndex < videoIds.length) {
                const videoId = videoIds[currentVideoIndex];
                $('#player').attr('src', `https://www.youtube.com/embed/${videoId}?autoplay=1`);
                currentVideoIndex++;
                $('#player').on('ended', playNextVideo);
            }
        }

        playNextVideo();
    }

    // ログアウトリンクのイベントリスナーを追加
    document.getElementById('logoutLink').addEventListener('click', function(event) {
        event.preventDefault();
        alert("logoutLink");
        window.location.href = '/logout';
    });
});

document.addEventListener('DOMContentLoaded', (event) => {
    document.getElementById('logoutLink').addEventListener('click', function() {
        window.location.href = '/logout';
    });
});

