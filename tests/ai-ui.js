// Run from project root: node tests/ai-ui.js
const vm = require('node:vm');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const source = fs.readFileSync(process.cwd() + '/public/assets/app.js', 'utf8');

async function scenario(name, kind) {
 const events = {}, clickHandlers = {};
 const button = { disabled: false, textContent: 'Generate draft' };
 const status = { hidden: true, className: '', textContent: '' };
 const cancel = { hidden: true, addEventListener: (t, f) => clickHandlers[t] = f, removeEventListener: t => delete clickHandlers[t] };
 // Reproduce the browser's named-control collision with <input name="action">.
 const form = { action: {toString:()=> '[object HTMLInputElement]'}, getAttribute:()=> null, dataset: {}, querySelector: () => ({value:'ai'}) };
 let redirect = '', calls = 0, timeout;
 const window = {addEventListener:()=>{},location:{href:'http://localhost/index.php?page=assistant',origin:'http://localhost',assign:url=>redirect=url}};
 const fetch = async (url, options) => {
  assert.equal(url,'http://localhost/index.php?page=assistant','Upload must target the page URL, never the hidden action input');
  calls++;
  if(kind === 'network') throw new Error('Network error');
  if(kind === 'cancel' || kind === 'timeout' || kind === 'duplicate') {
   return new Promise((resolve,reject) => {
    options.signal.addEventListener('abort',()=>reject(Object.assign(new Error(),{name:'AbortError'})));
    if(kind === 'cancel') clickHandlers.click();
    if(kind === 'timeout') timeout();
    if(kind === 'duplicate') {
     events.submit({target:form,submitter:button,preventDefault:()=>{}});
     resolve({ok:false,headers:{get:()=> 'application/json'},json:async()=>({ok:false,error:'Rate limited'})});
    }
   });
  }
  if(kind === 'html') return {headers:{get:()=> 'text/html'}};
  return {ok:kind === 'success',headers:{get:()=> 'application/json'},json:async()=>kind==='success'?{ok:true,redirect:'index.php?page=assistant'}:{ok:false,error:'AI is not configured. Open AI setup.'}};
 };
 vm.runInNewContext(source, {
  document:{addEventListener:(name,handler)=>events[name]=handler,getElementById:id=>id==='ai-status'?status:cancel},
  window,fetch,AbortController,URL,Date,FormData:class {},
  setTimeout:f=>{timeout=f;return 1},clearTimeout:()=>{},setInterval:()=>2,clearInterval:()=>{},navigator:{}
 });
 await events.submit({target:form,submitter:button,preventDefault:()=>{}});
 assert.equal(button.disabled,false);assert.equal(button.textContent,'Generate draft');assert.equal(form.dataset.busy,'false');assert.equal(cancel.hidden,true);assert.equal(calls,1);
 if(kind === 'success')assert.equal(redirect,'http://localhost/index.php?page=assistant');
 else {assert.equal(status.className,'alert error');assert.ok(status.textContent.length>0);}
 console.log('PASS: '+name);
}
(async()=>{
 for(const [name,kind] of [['Configuration error releases busy UI','config'],['Network failure releases busy UI','network'],['Server HTML failure is readable','html'],['Cancellation releases busy UI','cancel'],['Timeout releases busy UI','timeout'],['Duplicate submission sends one request','duplicate'],['Success navigates to reviewed draft','success']])await scenario(name,kind);
 console.log('7 AI UI regression checks passed.');
})().catch(error=>{console.error(error);process.exitCode=1;});
