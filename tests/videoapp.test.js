import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import VideoApp from '../public/js/VideoApp.js';

const baseDom = `
  <a id="logoutLink" href="/logout">logout</a>
  <button id="copyButton">copy</button>
  <select id="playlists"></select>
  <button id="shuffleButton">shuffle</button>
  <input id="feed_share_url" />
  <div id="videoInfo"></div>
  <div id="video-list"></div>
  <div id="pagination"></div>
  <iframe id="player"></iframe>
`;

describe('VideoApp characterization', () => {
  let app;

  beforeEach(() => {
    document.body.innerHTML = baseDom;
    app = new VideoApp();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('playVideo sets the YouTube embed URL with origin', () => {
    app.playVideo('abc123');
    const src = document.getElementById('player').getAttribute('src');
    expect(src).toContain('https://www.youtube.com/embed/abc123');
    expect(src).toContain(`origin=${window.location.origin}`);
  });

  it('updateButtonState toggles play/pause classes and aria state', () => {
    const button = document.createElement('button');
    button.classList.add('play-button');

    app.updateButtonState(button, true);
    expect(button.classList.contains('pause-button')).toBe(true);
    expect(button.classList.contains('play-button')).toBe(false);
    expect(button.classList.contains('is-playing')).toBe(true);
    expect(button.getAttribute('aria-label')).toBe('Pause');
    expect(button.getAttribute('aria-pressed')).toBe('true');

    app.updateButtonState(button, false);
    expect(button.classList.contains('play-button')).toBe(true);
    expect(button.classList.contains('pause-button')).toBe(false);
    expect(button.classList.contains('is-playing')).toBe(false);
    expect(button.getAttribute('aria-label')).toBe('Play');
    expect(button.getAttribute('aria-pressed')).toBe('false');
  });

  it('handlePlaylistChange writes the shortened URL on success', async () => {
    const shortener = vi
      .spyOn(app, 'generateShortUrl')
      .mockResolvedValue('https://short.example/abc');

    await app.handlePlaylistChange({ target: { value: 'PL123' } });

    const value = document.getElementById('feed_share_url').value;
    expect(value).toBe('https://short.example/abc');
    expect(shortener).toHaveBeenCalledWith(
      `${window.location.origin}/Index?feed_url=${encodeURIComponent(
        'https://www.youtube.com/playlist?list=PL123'
      )}`
    );
  });

  it('handlePlaylistChange falls back to the original URL on error', async () => {
    vi.spyOn(app, 'generateShortUrl').mockRejectedValue(new Error('fail'));

    await app.handlePlaylistChange({ target: { value: 'PL999' } });

    const value = document.getElementById('feed_share_url').value;
    expect(value).toBe(
      `${window.location.origin}/Index?feed_url=${encodeURIComponent(
        'https://www.youtube.com/playlist?list=PL999'
      )}`
    );
  });

  it('shufflePlay plays a random video from the list', () => {
    const list = document.getElementById('video-list');
    list.innerHTML = `
      <ul>
        <li><button class="mp-playpause play-button" data-video-id="id-one"></button></li>
        <li><button class="mp-playpause play-button" data-video-id="id-two"></button></li>
      </ul>
    `;

    const playSpy = vi.spyOn(app, 'playVideo').mockImplementation(() => {});
    vi.spyOn(Math, 'random').mockReturnValue(0);

    app.shufflePlay();

    expect(playSpy).toHaveBeenCalledWith('id-one');
  });

  it('feeds_from_keyword renders videos, pagination, and triggers playback', () => {
    const ajaxSpy = vi
      .spyOn(globalThis.$, 'ajax')
      .mockImplementation(({ success }) => {
        success({
          videos: [
            { videoId: 'vid1', title: 'First' },
            { videoId: 'vid2', title: 'Second' }
          ],
          prevPageToken: 'prev',
          nextPageToken: 'next'
        });
      });

    app.feeds_from_keyword('lofi');

    expect(ajaxSpy).toHaveBeenCalledWith(
      expect.objectContaining({
        url: '/api/videos',
        method: 'GET',
        data: { keyword: 'lofi', pageToken: '' }
      })
    );

    const listHtml = document.getElementById('video-list').innerHTML;
    expect(listHtml).toContain('data-video-id="vid1"');
    expect(listHtml).toContain('data-video-id="vid2"');

    const paginationHtml = document.getElementById('pagination').innerHTML;
    expect(paginationHtml).toContain('prev-page');
    expect(paginationHtml).toContain('next-page');

    const src = document.getElementById('player').getAttribute('src');
    expect(src).toContain('vid1');
  });

  it('feeds_from_feed_url sends feed_url and pageToken then renders videos', () => {
    const ajaxSpy = vi
      .spyOn(globalThis.$, 'ajax')
      .mockImplementation(({ success }) => {
        success({
          videos: [
            { videoId: 'feed-vid1', title: 'Feed First' },
            { videoId: 'feed-vid2', title: 'Feed Second' }
          ],
          prevPageToken: null,
          nextPageToken: 'next-feed-page'
        });
      });

    app.feeds_from_feed_url('https://www.youtube.com/playlist?list=PL123');

    expect(ajaxSpy).toHaveBeenCalledWith(
      expect.objectContaining({
        url: '/api/videos',
        method: 'GET',
        data: {
          feed_url: 'https://www.youtube.com/playlist?list=PL123',
          pageToken: ''
        }
      })
    );

    const listHtml = document.getElementById('video-list').innerHTML;
    expect(listHtml).toContain('data-video-id="feed-vid1"');
    expect(listHtml).toContain('data-video-id="feed-vid2"');

    const paginationHtml = document.getElementById('pagination').innerHTML;
    expect(paginationHtml).toContain('next-page');

    const src = document.getElementById('player').getAttribute('src');
    expect(src).toContain('feed-vid1');
  });

  it('createPlaylist posts JSON and refreshes playlists', async () => {
    const updateSpy = vi.spyOn(app, 'updatePlaylistsDropdown').mockImplementation(() => {});
    globalThis.fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ status: 'ok' })
    });

    await app.createPlaylist('My List', 'private', 'video-123');

    expect(globalThis.fetch).toHaveBeenCalledWith('/api/add-playlist', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        new_playlist_title: 'My List',
        new_playlist_privacy: 'private',
        video_id: 'video-123'
      })
    });
    expect(updateSpy).toHaveBeenCalled();
  });

  it('generateShortUrl returns the link from the API response', async () => {
    globalThis.fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ link: 'https://short.link/xyz' })
    });

    const result = await app.generateShortUrl('https://example.com');

    expect(result).toBe('https://short.link/xyz');
    expect(globalThis.fetch).toHaveBeenCalledWith('/shorten-url.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ originalUrl: 'https://example.com' })
    });
  });
});
