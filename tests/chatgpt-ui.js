const vm=require('node:vm'),fs=require('node:fs'),assert=require('node:assert/strict');
const source=fs.readFileSync(process.cwd()+'/public/assets/app.js','utf8');
async function test(kind){
 const events={},button={disabled:false,textContent:'Continue with ChatGPT'},status={},link={hidden:true};
 const form={dataset:{},querySelector:()=>({value:'chatgpt_connect'}),getAttribute:()=>null};
 let timeout,calls=0;
 vm.runInNewContext(source,{document:{addEventListener:(n,h)=>events[n]=h,getElementById:id=>id==='chatgpt-status'?status:link},window:{addEventListener:()=>{},location:{href:'http://localhost/index.php?page=settings'}},URL,AbortController,FormData:class{},setTimeout:f=>{timeout=f;return 1},clearTimeout:()=>{},navigator:{},fetch:async(url,opts)=>{
  calls++;assert.equal(url,'http://localhost/index.php?page=settings');
  if(kind==='duplicate')events.submit({target:form,submitter:button,preventDefault:()=>{}});
  if(kind==='network')throw new Error('Network failed');
  if(kind==='timeout')return new Promise((res,rej)=>{opts.signal.addEventListener('abort',()=>rej(Object.assign(new Error(),{name:'AbortError'})));timeout();});
  return {ok:true,headers:{get:()=>kind==='html'?'text/html':'application/json'},json:async()=>({ok:true,url:kind==='unsafe'?'https://attacker.invalid/':'http://127.0.0.1/aini-wear/public/chatgpt.php?begin=fixture'})};
 }});
 await events.submit({target:form,submitter:button,preventDefault:()=>{}});
 assert.equal(button.disabled,false);assert.equal(button.textContent,'Continue with ChatGPT');assert.equal(form.dataset.busy,'false');assert.equal(calls,1);
 if(['success','duplicate'].includes(kind)){assert.equal(link.hidden,false);assert.match(status.textContent,/Chrome or Edge/);}else{assert.equal(link.hidden,true);assert.equal(status.className,'alert error');}
 console.log('PASS ChatGPT sign-in '+kind);
}
(async()=>{for(const kind of ['success','duplicate','network','timeout','html','unsafe'])await test(kind);})().catch(e=>{console.error(e);process.exitCode=1;});
