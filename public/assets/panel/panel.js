(function () {
	'use strict';

	const cfg = window.PANEL_CONFIG;
	if (!cfg) return;

	const form = document.getElementById('upload-form');
	if (!form) return;

	const typeSelect = document.getElementById('content_type');
	const fileRow = document.getElementById('file-row');
	const fileInput = document.getElementById('file');
	const urlFields = document.getElementById('url-fields');
	const urlInput = form.querySelector('[name="url"]');
	const durationRow = document.getElementById('duration-row');
	const durationInput = document.getElementById('display_time');
	const msgEl = document.getElementById('form-message');

	function syncFields() {
		const isUrl = typeSelect.value === '5';
		const isVideo = typeSelect.value === '2';

		fileRow.style.display = isUrl ? 'none' : 'block';
		fileInput.required = !isUrl;

		urlFields.style.display = isUrl ? 'block' : 'none';
		urlInput.required = isUrl;

		// Video advances on `ended` — duration is not applicable.
		// Disable the input (not just hide it) so the field is not submitted;
		// the request validation marks it as `prohibited` for video.
		durationRow.style.display = isVideo ? 'none' : 'block';
		durationInput.disabled = isVideo;
	}
	typeSelect.addEventListener('change', syncFields);
	syncFields();

	function buildUrl(base) {
		const url = new URL(base, window.location.origin);
		if (cfg.isDev && cfg.ip) url.searchParams.set('ip', cfg.ip);
		return url.toString();
	}

	function showMsg(text, isError) {
		msgEl.textContent = text;
		msgEl.className = isError ? 'error' : 'success';
	}

	form.addEventListener('submit', async (e) => {
		e.preventDefault();
		showMsg('', false);

		const btn = form.querySelector('button[type="submit"]');
		btn.disabled = true;
		btn.textContent = 'Uploading…';

		try {
			const response = await fetch(buildUrl(cfg.uploadUrl), {
				method: 'POST',
				headers: { Accept: 'application/json' },
				body: new FormData(form),
			});

			if (!response.ok) {
				const body = await response.json().catch(() => ({}));
				const message = body.Message
					|| (body.errors ? Object.values(body.errors).flat().join(' ') : `HTTP ${response.status}`);
				throw new Error(message);
			}

			showMsg('Uploaded. Player will refresh in ~1s. Reloading…', false);
			setTimeout(() => window.location.reload(), 800);
		} catch (err) {
			showMsg(err.message || 'Upload failed', true);
			btn.disabled = false;
			btn.textContent = 'Upload';
		}
	});

	document.querySelectorAll('.delete-btn').forEach((btn) => {
		btn.addEventListener('click', async () => {
			if (!confirm('Delete this content? The player will refresh.')) return;

			const id = btn.dataset.id;
			btn.disabled = true;
			btn.textContent = 'Deleting…';

			try {
				const target = cfg.deleteUrlTemplate.replace('/ID', '/' + id);
				const response = await fetch(buildUrl(target), {
					method: 'DELETE',
					headers: { Accept: 'application/json' },
				});

				if (!response.ok) {
					const body = await response.json().catch(() => ({}));
					throw new Error(body.Message || `HTTP ${response.status}`);
				}

				window.location.reload();
			} catch (err) {
				alert(err.message || 'Delete failed');
				btn.disabled = false;
				btn.textContent = 'Delete';
			}
		});
	});
})();