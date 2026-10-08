document.addEventListener('click', async event => {
 const button = event.target.closest('[data-copy]');
 if (!button) return;
 const field = document.getElementById(button.dataset.copy);
 const status = document.getElementById('copy-status');
 try { await navigator.clipboard.writeText(field.value); status.textContent = 'Copied.'; }
 catch { field.focus(); field.select(); status.textContent = 'Press Ctrl+C to copy the selected draft.'; }
});
document.addEventListener('submit', async event => {
 const form = event.target;
 const button = event.submitter;
 if (!button) return;
 if (form.querySelector('[name="action"]')?.value !== 'ai') {
  button.disabled = true;
  button.textContent = 'Saving…';
  return;
 }
 event.preventDefault();
 if (form.dataset.busy === 'true') return;
 const status = document.getElementById('ai-status');
 const cancel = document.getElementById('ai-cancel');
 const originalLabel = button.textContent;
 const controller = new AbortController();
 let cancelled = false;
 const stop = () => { cancelled = true; controller.abort(); };
 cancel.addEventListener('click', stop);
 cancel.hidden = false;
 form.dataset.busy = 'true';
 button.disabled = true;
 button.textContent = 'Reading file / preparing draft…';
 status.textContent = 'Waiting for AI. This can take up to 45 seconds.';
 status.className = 'alert';
 status.hidden = false;
 const started = Date.now();
 const timer = setTimeout(() => controller.abort(), 55000);
 const ticker = setInterval(() => {
  status.textContent = `Waiting for AI · ${Math.floor((Date.now() - started) / 1000)} seconds. You can cancel the wait.`;
 }, 1000);
 try {
  // A hidden field named "action" shadows the form.action DOM property.
  const endpoint = form.getAttribute('action') || window.location.href;
  const response = await fetch(endpoint, {
   method: 'POST', body: new FormData(form),
   headers: { Accept: 'application/json' }, signal: controller.signal
  });
  if (!response.headers.get('content-type')?.includes('application/json')) {
   throw new Error('The server did not return an AI result. Reload and sign in again; if this repeats, check the server logs.');
  }
  const result = await response.json();
  if (!response.ok || !result.ok) throw new Error(result.error || 'AI request failed. Try again.');
  const redirect = new URL(result.redirect, window.location.href);
  if (redirect.origin !== window.location.origin) throw new Error('Invalid result address.');
  window.location.assign(redirect.href);
 } catch (error) {
  status.className = 'alert error';
  status.textContent = error.name === 'AbortError'
   ? (cancelled ? 'Wait cancelled. The server may finish the current request within 45 seconds. Nothing was applied to an order.' : 'AI took too long. Try a smaller file, or check your API settings. Nothing was applied to an order.')
   : error.message;
 } finally {
  clearTimeout(timer);
  clearInterval(ticker);
  cancel.removeEventListener('click', stop);
  cancel.hidden = true;
  form.dataset.busy = 'false';
  button.disabled = false;
  button.textContent = originalLabel;
 }
});
window.addEventListener('pageshow', event => {
 if (event.persisted) window.location.reload();
});
