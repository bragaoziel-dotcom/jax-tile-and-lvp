const GA4_MEASUREMENT_ID='G-MQ4DLFWW0F';
const ctaLocation=(el)=>el?.closest?.('header')?'header':el?.closest?.('footer')?'footer':el?.closest?.('[data-cta-location]')?.getAttribute('data-cta-location')||'body';
const dataLayerPush=(event,params={})=>{
  const safe={site_brand:'jax_tile_lvp',page_path:location.pathname,language:document.documentElement.lang||'en-US',...params};
  window.dataLayer=window.dataLayer||[];
  window.dataLayer.push({event,...safe});
  if(typeof window.gtag==='function')window.gtag('event',event,{...safe,send_to:GA4_MEASUREMENT_ID});
};
const isPt=(document.documentElement.lang||'').toLowerCase().startsWith('pt');
const copy=isPt?{sending:'Enviando…',success:'Obrigado — recebemos seu pedido. Entraremos em contato em breve.',error:'Não foi possível enviar agora. Ligue para (904) 520-1994.'}:{sending:'Sending…',success:'Thank you — your request was received. We will contact you shortly.',error:'We could not send the request. Please call (904) 520-1994.'};

// Attribution is retained only for the lead backend/email. Do not send click IDs,
// contact details or free-text project information to GA4, GTM or Google Ads.
const params=new URLSearchParams(window.location.search);
const attribution={};
['utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','gbraid','wbraid'].forEach(key=>{const value=params.get(key);if(value){attribution[key]=value;try{sessionStorage.setItem('jax_'+key,value)}catch{}}else{try{const saved=sessionStorage.getItem('jax_'+key);if(saved)attribution[key]=saved}catch{}}});

document.querySelectorAll('a[href^="tel:"]').forEach(link=>link.addEventListener('click',()=>dataLayerPush('phone_click',{cta_location:ctaLocation(link)})));
document.querySelectorAll('a[href^="sms:"]').forEach(link=>link.addEventListener('click',()=>dataLayerPush('sms_click',{cta_location:ctaLocation(link)})));
document.querySelectorAll('a[href^="mailto:"]').forEach(link=>link.addEventListener('click',()=>dataLayerPush('email_click',{cta_location:ctaLocation(link)})));
document.querySelectorAll('.track-estimate').forEach(link=>link.addEventListener('click',()=>dataLayerPush('cta_click',{cta_name:'free_estimate',cta_location:ctaLocation(link)})));

const leadForm=document.querySelector('#lead-form');
const formStatus=document.querySelector('#form-status');
if(leadForm){Object.entries(attribution).forEach(([name,value])=>{let input=leadForm.querySelector(`[name="${name}"]`);if(!input){input=document.createElement('input');input.type='hidden';input.name=name;leadForm.appendChild(input)}input.value=value});let pageInput=leadForm.querySelector('[name="page"]');if(!pageInput){pageInput=document.createElement('input');pageInput.type='hidden';pageInput.name='page';leadForm.appendChild(pageInput)}if(!pageInput.value)pageInput.value=document.title;}
leadForm?.addEventListener('submit',async e=>{e.preventDefault();if(leadForm.dataset.sending==='1')return;leadForm.dataset.sending='1';if(formStatus){formStatus.textContent=copy.sending;formStatus.className='';}dataLayerPush('lead_form_submit',{service:leadForm.elements.service?.value||''});try{const response=await fetch(leadForm.action,{method:'POST',body:new FormData(leadForm),headers:{Accept:'application/json'}});const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.message||'Unable to send');if(formStatus){formStatus.textContent=copy.success;formStatus.className='success';}if(result.trackConversion)dataLayerPush('generate_lead',{service:leadForm.elements.service?.value||'',source_page:document.title,lead_source:'website_form'});leadForm.reset();}catch(err){if(formStatus){formStatus.textContent=copy.error;formStatus.className='error';}dataLayerPush('lead_form_error');}finally{leadForm.dataset.sending='0';}});

const launcher=document.querySelector('#chat-launcher'),chat=document.querySelector('#braga-chat'),close=document.querySelector('#chat-close'),messages=document.querySelector('#chat-messages'),chatForm=document.querySelector('#chat-form'),chatInput=document.querySelector('#chat-input');
let history=[];
function toggleChat(open){if(!chat||!launcher)return;chat.hidden=!open;launcher.setAttribute('aria-expanded',String(open));if(open){chatInput?.focus();dataLayerPush('ai_chat_opened');}}
launcher?.addEventListener('click',()=>toggleChat(chat.hidden));close?.addEventListener('click',()=>toggleChat(false));
function addMessage(role,text){if(!messages)return;const p=document.createElement('p');p.className=role;p.textContent=text;messages.appendChild(p);messages.scrollTop=messages.scrollHeight;}
async function sendMessage(text){text=(text||'').trim();if(!text||!chatInput)return;addMessage('user',text);history.push({role:'user',content:text});chatInput.value='';chatInput.disabled=true;try{const response=await fetch('/api/braga-ai.php',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({message:text,history:history.slice(-12)})});const result=await response.json();if(!response.ok||!result.ok)throw new Error();addMessage('assistant',result.reply);history.push({role:'assistant',content:result.reply});}catch{addMessage('assistant',isPt?'Estou temporariamente indisponível. Ligue para (904) 520-1994 ou use o formulário de orçamento.':'I am temporarily unavailable. Please call (904) 520-1994 or use the free estimate form.');}finally{chatInput.disabled=false;chatInput.focus();}}
chatForm?.addEventListener('submit',e=>{e.preventDefault();sendMessage(chatInput?.value)});document.querySelectorAll('[data-message]').forEach(button=>button.addEventListener('click',()=>sendMessage(button.dataset.message)));
