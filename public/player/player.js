
(function () {
	'use strict';

	const PLAYBACK_URL = window.PLAYER_ENDPOINTS && window.PLAYER_ENDPOINTS.playback;
	const MONITOR_IP = window.MONITOR_IP || '';

	const MIN_IMAGE_DISPLAY = 3;
	const MIN_TEXT_DISPLAY = 5;
	const MIN_URL_DISPLAY = 10;
	const FLASH_FALLBACK_SECS = 5;
	const MISSING_MEDIA_SKIP = 2000;
	const VIDEO_START_TIMEOUT = 15000;
	const EMPTY_RETRY_MS = 60000;
	const FAIL_ADVANCE_MS = 500;
	const CROSSFADE_MS = 200;
	const IFRAME_REVEAL_MS = 300;

	const state = {
		items: [],
		index: 0,
		currentTimer: null,
		currentVideo: null,
		currentImg: null,
		currentIframe: null,
		retryTimer: null,
		preloadedUrls: new Set(),
	};

	// ------------------------------------------------------------------ utils

	function log(...args) {
		console.log('[player]', ...args);
	}

	function err(...args) {
		console.error('[player]', ...args);
	}

	function stage() {
		return document.getElementById('stage');
	}

	function emptyEl() {
		return document.getElementById('empty');
	}

	function show(el) {
		if (el) el.style.display = 'flex';
	}

	function hide(el) {
		if (el) el.style.display = 'none';
	}

	// An absolutely-positioned wrapper that fades in. Stacking a new layer
	// over the old content hides the "black flash" while the next item loads.
	function makeLayer() {
		const layer = document.createElement('div');
		layer.className = 'content-layer';
		layer.style.position = 'absolute';
		layer.style.inset = '0';
		layer.style.display = 'flex';
		layer.style.alignItems = 'center';
		layer.style.justifyContent = 'center';
		layer.style.opacity = '0';
		layer.style.transition = 'opacity ' + CROSSFADE_MS + 'ms ease';
		return layer;
	}

	function revealLayer(layer) {
		// rAF ensures the initial opacity:0 is committed before transition runs.
		requestAnimationFrame(() => {
			layer.style.opacity = '1';
		});
	}

	function removeOldAfterCrossfade(oldEls) {
		setTimeout(() => {
			oldEls.forEach(el => el.remove());
		}, CROSSFADE_MS + 20);
	}

	function buildMissingFallback(kind, name) {
		const div = document.createElement('div');
		div.className = 'content-missing';

		const icon = document.createElement('div');
		icon.className = 'content-missing-icon';
		icon.textContent = '⚠';

		const h = document.createElement('h2');
		h.textContent = kind + ' unavailable';

		const p = document.createElement('p');
		p.textContent = name || '(unnamed)';

		div.append(icon, h, p);
		return div;
	}

	// -------------------------------------------------------------- preload

	function preloadNext() {
		if (state.items.length < 2) return;

		const next = state.items[(state.index + 1) % state.items.length];
		if (!next || next.ContentType !== 1 || !next.Contentpath) return;
		if (state.preloadedUrls.has(next.Contentpath)) return;

		state.preloadedUrls.add(next.Contentpath);
		const img = new Image();
		img.src = next.Contentpath;
		log('preload', next.Contentpath);
	}

	// -------------------------------------------------------------- playlist

	function fetchPlaylist() {
		if (!PLAYBACK_URL) {
			err('PLAYER_ENDPOINTS.playback missing');
			return Promise.resolve([]);
		}

		const url = new URL(PLAYBACK_URL, window.location.origin);
		if (MONITOR_IP) url.searchParams.set('ip', MONITOR_IP);

		return fetch(url.toString(), {
			headers: {
				Accept: 'application/json'
			}
		})
			.then((r) => {
				if (!r.ok) throw new Error('HTTP ' + r.status);
				return r.json();
			})
			.then((payload) => {
				const data = payload.Data || {};
				const items = data.MonitorContents || data.ShareContents || [];
				updateHeader(data);
				return items;
			})
			.catch((e) => {
				err('fetch failed', e.message);
				return [];
			});
	}

	function updateHeader(data) {
		const msgEl = document.getElementById('daily-message');
		const dateEl = document.getElementById('persian-datetime');

		if (data.DailyMessage) {
			msgEl.textContent = data.DailyMessage;
			show(msgEl);
		} else {
			hide(msgEl);
		}

		if (data.MonitorDateTime) {
			dateEl.textContent = data.MonitorDateTime.Full ||
				data.MonitorDateTime.TodayDate ||
				'--';
		}
	}

	// ---------------------------------------------------------------- cleanup

	function cleanupCurrent() {
		if (state.currentTimer) {
			clearTimeout(state.currentTimer);
			state.currentTimer = null;
		}
		if (state.currentVideo) {
			try {
				state.currentVideo.pause();
				state.currentVideo.removeAttribute('src');
				state.currentVideo.load();
			} catch (_) { }
			state.currentVideo = null;
		}
		if (state.currentImg) {
			state.currentImg.onerror = null;
			state.currentImg.onload = null;
			state.currentImg = null;
		}
		if (state.currentIframe) {
			state.currentIframe.src = 'about:blank';
			state.currentIframe = null;
		}
	}

	// ---------------------------------------------------------------- control

	function playPlaylist() {
		if (state.items.length === 0) {
			showEmpty();
			return;
		}
		state.index = 0;
		playCurrent();
	}

	function playCurrent() {
		if (state.items.length === 0) {
			showEmpty();
			return;
		}
		if (state.index >= state.items.length) {
			state.index = 0;
		}

		const item = state.items[state.index];
		log('play', item.ContentId, 'type=' + item.ContentType, item.ContentName);
		cleanupCurrent();

		switch (item.ContentType) {
			case 1:
				return playImage(item);
			case 2:
				return playVideo(item);
			case 3:
				return playText(item);
			case 4:
				return playFlash(item);
			case 5:
				return playUrl(item);
			default:
				err('unknown content type', item.ContentType);
				return advance();
		}
	}

	function advance() {
		cleanupCurrent();
		state.index++;
		state.currentTimer = setTimeout(playCurrent, FAIL_ADVANCE_MS);
	}

	// -------------------------------------------------------------- empty state

	function showEmpty() {
		hide(document.getElementById('boot'));
		stage().innerHTML = '';
		show(emptyEl());
		if (state.retryTimer) clearTimeout(state.retryTimer);
		state.retryTimer = setTimeout(boot, EMPTY_RETRY_MS);
	}

	// ------------------------------------------------------------ item players

	function playImage(item) {
		const oldEls = Array.from(stage().children);
		const layer = makeLayer();

		const img = document.createElement('img');
		img.alt = item.ContentName || '';
		img.src = item.Contentpath;
		img.style.maxWidth = '100%';
		img.style.maxHeight = '100%';
		img.style.objectFit = 'contain';
		state.currentImg = img;

		const seconds = Math.max(MIN_IMAGE_DISPLAY, Number(item.DisplayTime) || 0);

		img.onerror = () => {
			err('image 404/failed', item.Contentpath);
			layer.innerHTML = '';
			layer.appendChild(buildMissingFallback('Image', item.ContentName));
			revealLayer(layer);
			removeOldAfterCrossfade(oldEls);
			state.currentTimer = setTimeout(advance, MISSING_MEDIA_SKIP);
		};
		img.onload = () => {
			revealLayer(layer);
			removeOldAfterCrossfade(oldEls);
			state.currentTimer = setTimeout(advance, seconds * 1000);
		};

		layer.appendChild(img);
		stage().appendChild(layer);
		preloadNext();
	}

	function playVideo(item) {
		const oldEls = Array.from(stage().children);
		const layer = makeLayer();

		const video = document.createElement('video');
		video.src = item.Contentpath;
		video.autoplay = true;
		video.muted = true;
		video.playsInline = true;
		video.preload = 'auto';
		video.controls = false;
		video.style.maxWidth = '100%';
		video.style.maxHeight = '100%';
		video.style.objectFit = 'contain';
		state.currentVideo = video;

		let revealed = false;

		function revealOnce() {
			if (revealed) return;
			revealed = true;
			revealLayer(layer);
			removeOldAfterCrossfade(oldEls);
		}

		const startTimeout = setTimeout(() => {
			if (revealed) return;
			err('video did not start in time', item.Contentpath);
			layer.innerHTML = '';
			layer.appendChild(buildMissingFallback('Video', item.ContentName));
			revealOnce();
			setTimeout(advance, MISSING_MEDIA_SKIP);
		}, VIDEO_START_TIMEOUT);

		video.onerror = () => {
			clearTimeout(startTimeout);
			err('video error', item.Contentpath, video.error && video.error.code);
			layer.innerHTML = '';
			layer.appendChild(buildMissingFallback('Video', item.ContentName));
			revealOnce();
			setTimeout(advance, MISSING_MEDIA_SKIP);
		};
		video.onloadeddata = revealOnce;
		video.onplaying = () => {
			clearTimeout(startTimeout);
			revealOnce();
			log('video playing');
		};
		video.onended = () => {
			clearTimeout(startTimeout);
			advance();
		};

		layer.appendChild(video);
		stage().appendChild(layer);
		preloadNext();

		const p = video.play();
		if (p && typeof p.catch === 'function') {
			p.catch((e) => {
				clearTimeout(startTimeout);
				err('video.play() rejected', e && e.message);
				layer.innerHTML = '';
				layer.appendChild(buildMissingFallback('Video', item.ContentName));
				revealOnce();
				setTimeout(advance, MISSING_MEDIA_SKIP);
			});
		}
	}

	function playText(item) {
		const oldEls = Array.from(stage().children);
		const layer = makeLayer();

		const div = document.createElement('div');
		div.className = 'content-text';
		div.textContent = item.ContentName || '';

		layer.appendChild(div);
		stage().appendChild(layer);
		revealLayer(layer);
		removeOldAfterCrossfade(oldEls);

		const seconds = Math.max(MIN_TEXT_DISPLAY, Number(item.DisplayTime) || 0);
		state.currentTimer = setTimeout(advance, seconds * 1000);
		preloadNext();
	}

	function playFlash(item) {
		const oldEls = Array.from(stage().children);
		const layer = makeLayer();

		const div = document.createElement('div');
		div.className = 'content-fallback';
		const h = document.createElement('h2');
		h.textContent = 'Flash content';
		const p1 = document.createElement('p');
		p1.textContent = 'Flash is no longer supported by modern browsers.';
		const p2 = document.createElement('p');
		const s = document.createElement('small');
		s.textContent = item.ContentName || '';
		p2.appendChild(s);
		div.append(h, p1, p2);

		layer.appendChild(div);
		stage().appendChild(layer);
		revealLayer(layer);
		removeOldAfterCrossfade(oldEls);

		state.currentTimer = setTimeout(advance, FLASH_FALLBACK_SECS * 1000);
		preloadNext();
	}

	function playUrl(item) {
		const oldEls = Array.from(stage().children);
		const layer = makeLayer();
		layer.style.display = 'block';

		const wrap = document.createElement('div');
		wrap.className = 'content-url';

		const iframe = document.createElement('iframe');
		iframe.className = 'content-iframe';
		iframe.src = item.Url || item.Contentpath || '';
		iframe.sandbox = 'allow-scripts allow-same-origin';
		iframe.referrerPolicy = 'no-referrer';
		state.currentIframe = iframe;

		const link = document.createElement('a');
		link.className = 'content-url-link';
		link.href = item.Url || '#';
		link.target = '_blank';
		link.rel = 'noopener noreferrer';
		link.textContent = 'Open: ' + (item.Url || 'unknown');

		wrap.append(iframe, link);
		layer.appendChild(wrap);
		stage().appendChild(layer);

		// iframe `onload` fires for a blank doc too — small delay so the user
		// doesn't see a flash of white before the remote page paints.
		let revealed = false;

		function revealOnce() {
			if (revealed) return;
			revealed = true;
			revealLayer(layer);
			removeOldAfterCrossfade(oldEls);
		}
		iframe.addEventListener('load', () => setTimeout(revealOnce, IFRAME_REVEAL_MS));
		setTimeout(revealOnce, 2000); // hard fallback

		const seconds = Math.max(MIN_URL_DISPLAY, Number(item.DisplayTime) || 0);
		state.currentTimer = setTimeout(advance, seconds * 1000);
		preloadNext();
	}

	// reverb

	// ------------------------------------------------------------------ reverb

	function connectReverb() {
		if (!window.REVERB || !window.REVERB.wsUrl) {
			log('reverb disabled — no wsUrl');
			return;
		}

		const url = window.REVERB.wsUrl +
			'?protocol=7&client=js&version=8.4.0&flash=false';

		log('reverb connecting', url);
		let ws;
		try {
			ws = new WebSocket(url);
		} catch (e) {
			err('reverb WebSocket ctor failed', e);
			setTimeout(connectReverb, 5000);
			return;
		}

		let pingTimer = null;

		ws.onopen = () => log('reverb socket open');

		ws.onmessage = (msg) => {
			let data;
			try { data = JSON.parse(msg.data); } catch (_) { return; }

			switch (data.event) {
				case 'pusher:connection_established': {
					ws.send(JSON.stringify({
						event: 'pusher:subscribe',
						data: { channel: window.REVERB.channel },
					}));
					pingTimer = setInterval(() => {
						try {
							ws.send(JSON.stringify({ event: 'pusher:ping', data: {} }));
						} catch (_) { }
					}, 30000);
					break;
				}
				case 'pusher_internal:subscription_succeeded':
					log('reverb subscribed to', window.REVERB.channel);
					break;
				case 'PlaylistRefresh': {
					let payload = {};
					try { payload = JSON.parse(data.data || '{}'); } catch (_) { }
					const target = payload.ip;
					if (target === null || target === undefined || target === MONITOR_IP) {
						log('reverb refresh received', target);
						boot();
					}
					break;
				}
			}
		};

		ws.onclose = () => {
			log('reverb closed — reconnecting in 5s');
			if (pingTimer) { clearInterval(pingTimer); pingTimer = null; }
			setTimeout(connectReverb, 5000);
		};

		ws.onerror = (e) => err('reverb error', e);
	}

	// -------------------------------------------------------------------- boot

	let reverbStarted = false;

	function boot() {
		log('boot');
		hide(emptyEl());
		hide(document.getElementById('boot'));
		cleanupCurrent();
		if (state.retryTimer) {
			clearTimeout(state.retryTimer);
			state.retryTimer = null;
		}

		fetchPlaylist().then((items) => {
			state.items = items;
			log('playlist loaded', items.length, 'items');
			playPlaylist();
		});

		if (!reverbStarted) {
			reverbStarted = true;
			connectReverb();
		}

	}

	document.addEventListener('DOMContentLoaded', boot);
})();
