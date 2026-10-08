document.addEventListener('click', async event => {
 const button = event.target.closest('[data-copy]');
 if (!button) return;
 const field = document.getElementById(button.dataset.copy);
 const status = document.getElementById('copy-status');
 try { await navigator.clipboard.writeText(field.value); status.textContent = 'Copied.'; }
 catch { field.focus(); field.select(); status.textContent = 'Press Ctrl+C to copy the selected draft.'; }
});
document.addEventListener('submit', event => {
 const button = event.submitter;
 if (!button) return;
 button.disabled = true;
 button.textContent = button.textContent.includes('Generate') ? 'Preparing draft…' : 'Saving…';
});
