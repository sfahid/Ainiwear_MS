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
 if (form.querySelector('[name="action"]')?.value === 'chatgpt_connect') {
  event.preventDefault();
  if (form.dataset.busy === 'true') return;
  const status = document.getElementById('chatgpt-status');
  const link = document.getElementById('chatgpt-link');
  const label = button.textContent;
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 15000);
  form.dataset.busy = 'true'; button.disabled = true;
  button.textContent = 'Preparing sign-in…';
  status.hidden = false; status.className = 'alert';
  status.textContent = 'Preparing your ChatGPT sign-in link…'; link.hidden = true;
  try {
   const response = await fetch(form.getAttribute('action') || window.location.href, {
    method:'POST',body:new FormData(form),headers:{Accept:'application/json'},signal:controller.signal
   });
   if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Sign-in could not start. Reload Aini Wear and try again.');
   const result = await response.json();
   if (!response.ok || !result.ok) throw new Error(result.error || 'Sign-in could not start.');
   const url = new URL(result.url);
   if (url.protocol !== 'http:' || url.hostname !== '127.0.0.1' || !url.pathname.endsWith('/chatgpt.php')) throw new Error('Invalid sign-in address.');
   link.href = url.href; link.hidden = false;
   status.textContent = 'Sign-in is ready. Click Open ChatGPT sign-in below. If this app blocks the new tab, copy the link and paste it into Chrome or Edge. After authorizing Aini Wear, return here and refresh.';
  } catch (error) {
   status.className = 'alert error';
   status.textContent = error.name === 'AbortError' ? 'Sign-in preparation timed out. Reload and try again.' : error.message;
  } finally {
   clearTimeout(timer);form.dataset.busy = 'false';button.disabled = false;button.textContent = label;
  }
  return;
 }
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

// Filter a native destination selector using the saved rate list.
{
 const search=document.getElementById('shipping-search');
 const destination=document.getElementById('shipping-destination');
 const currency=document.getElementById('shipping-currency');
 if(search && destination && currency){
  const options=Array.from(destination.options).slice(1).map(option=>option.cloneNode(true));
  const status=document.getElementById('shipping-search-status');
  const aliases={'united states':'usa us america','united kingdom':'uk britain england','united arab emirates':'uae dubai','china':'cn','hong kong':'hk','canada':'ca','australia':'au','new zealand':'nz'};
  const synchronizeCurrency=()=>{
   const supported=(destination.selectedOptions[0]?.dataset.currencies||'').split(',').filter(Boolean);
   for(const option of currency.options)option.disabled=supported.length>0 && !supported.includes(option.value);
   if(supported.length && !supported.includes(currency.value))currency.value=supported.includes('PKR')?'PKR':supported[0];
  };
  search.addEventListener('input',()=>{
   const query=search.value.trim().toLowerCase();const previous=destination.value;
   const matches=options.filter(option=>{
    const name=option.textContent.toLowerCase();
    const keywords=Object.entries(aliases).filter(([key])=>name.includes(key)).map(([,value])=>value).join(' ');
    return (name+' '+keywords).includes(query);
   });
   destination.replaceChildren(new Option(matches.length?'Select destination':'No matching destinations',''),...matches.map(option=>option.cloneNode(true)));
   if(matches.some(option=>option.value===previous))destination.value=previous;
   else if(matches.length===1)destination.value=matches[0].value;
   status.textContent=matches.length?`${matches.length} destinations found. Choose a destination below.`:'No match. Try another country name.';
   synchronizeCurrency();
  });
  destination.addEventListener('change',synchronizeCurrency);synchronizeCurrency();
 }
}
