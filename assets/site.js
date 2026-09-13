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

// Keep the Jax mark readable on phones.
const brandFix=document.createElement('style');
brandFix.textContent='@media(max-width:850px){.site-header{height:86px}.brand img{width:236px;height:68px}}@media(max-width:620px){.brand img{width:220px;height:64px}}.braga-ai-avatar{width:48px;height:48px;object-fit:contain;flex:0 0 auto;border-radius:14px;background:#fff;padding:2px}.braga-ai-launcher-avatar{width:58px;height:58px;object-fit:contain;display:block}.braga-ai-launcher-label{display:block;font-size:12px;line-height:1;font-weight:900;margin-top:2px}#chat-launcher{display:grid;place-items:center;gap:2px;padding:8px 12px!important;min-width:82px}.chat header{gap:10px}';
document.head.appendChild(brandFix);

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

// Match the Braga Remodeling AI identity. Prefer the same Braga mascot; if the
// production asset URL ever changes, fall back to the Jax brand image instead of
// leaving a broken avatar.
const BRAGA_MASCOT='https://bragaremodeling.com/assets/braga-mascot-cut.webp';
const JAX_LOGO='/assets/jax-tile-lvp-logo.webp';
function avatarImg(className){const img=document.createElement('img');img.src=BRAGA_MASCOT;img.alt='Braga AI assistant';img.className=className;img.addEventListener('error',()=>{if(img.src.endsWith('/assets/jax-tile-lvp-logo.webp'))return;img.src=JAX_LOGO;},{once:true});return img;}
if(launcher){launcher.replaceChildren(avatarImg('braga-ai-launcher-avatar'));const label=document.createElement('span');label.className='braga-ai-launcher-label';label.textContent='Ask Braga AI';launcher.appendChild(label);}
if(chat){const header=chat.querySelector('header');if(header&&!header.querySelector('.braga-ai-avatar'))header.prepend(avatarImg('braga-ai-avatar'));}

function toggleChat(open){if(!chat||!launcher)return;chat.hidden=!open;launcher.setAttribute('aria-expanded',String(open));if(open){chatInput?.focus();dataLayerPush('ai_chat_opened');}}
launcher?.addEventListener('click',()=>toggleChat(chat.hidden));close?.addEventListener('click',()=>toggleChat(false));
function addMessage(role,text){if(!messages)return;const p=document.createElement('p');p.className=role;p.textContent=text;messages.appendChild(p);messages.scrollTop=messages.scrollHeight;}

function localReply(input){
  const q=input.toLowerCase();
  if(isPt){
    if(/(area|atende|cidade|regiao|região|onde)/.test(q))return 'Atendemos Jacksonville, Jacksonville Beach, Ponte Vedra, Nocatee, St. Johns, St. Augustine, Orange Park, Fleming Island e Mandarin. Qual é a sua cidade ou ZIP code?';
    if(/(lvp|vinil|vinyl)/.test(q))return 'Sim. Instalamos LVP e vinyl plank, incluindo remoção do piso existente, preparação e nivelamento quando necessário. Aproximadamente quantos square feet tem o projeto?';
    if(/(tile|porcelanato|ceramica|cerâmica|azulejo)/.test(q))return 'Sim. Fazemos instalação de porcelain e ceramic tile em pisos, paredes, backsplash e shower tile. Qual área você quer revestir?';
    if(/(preco|preço|quanto|custo|valor|orcamento|orçamento|estimate)/.test(q))return 'O orçamento é gratuito e depende da metragem, piso existente e preparação necessária. Me diga o serviço e a metragem aproximada para eu começar.';
    if(/(telefone|ligar|contato|phone)/.test(q))return 'Você pode ligar ou enviar mensagem para (904) 520-1994. Também posso continuar por aqui e reunir os detalhes do projeto.';
    return 'Posso ajudar com LVP/vinyl ou tile em Jacksonville e região. Me diga qual serviço você precisa e a metragem aproximada.';
  }
  if(/(area|serve|service area|where|city|cities|location)/.test(q))return 'We serve Jacksonville, Jacksonville Beach, Ponte Vedra, Nocatee, St. Johns, St. Augustine, Orange Park, Fleming Island and Mandarin. What city or ZIP code is your project in?';
  if(/(lvp|vinyl|plank|spc)/.test(q))return 'Yes. We install LVP and vinyl plank, including existing-floor removal, preparation and leveling when needed. About how many square feet is the project?';
  if(/(tile|porcelain|ceramic|backsplash|shower)/.test(q))return 'Yes. We install porcelain and ceramic tile for floors, walls, backsplashes and showers. What area are you planning to tile?';
  if(/(price|cost|how much|estimate|quote)/.test(q))return 'Estimates are free. Final pricing depends on square footage, the existing floor and any preparation or leveling needed. Which service and about how many square feet?';
  if(/(phone|call|contact|text)/.test(q))return 'You can call or text us at (904) 520-1994. I can also keep gathering your project details here.';
  return 'I can help with LVP/vinyl or tile installation in Jacksonville and nearby areas. Which service do you need, and about how many square feet is the project?';
}

async function sendMessage(text){
  text=(text||'').trim();
  if(!text||!chatInput)return;
  addMessage('user',text);
  history.push({role:'user',content:text});
  chatInput.value='';
  chatInput.disabled=true;
  const controller=new AbortController();
  const timer=setTimeout(()=>controller.abort(),12000);
  try{
    const response=await fetch('/api/braga-ai.php',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({message:text,history:history.slice(-12)}),signal:controller.signal});
    let result=null;
    try{result=await response.json();}catch{}
    if(!response.ok||!result?.ok||!result?.reply)throw new Error(result?.message||`HTTP ${response.status}`);
    addMessage('assistant',result.reply);
    history.push({role:'assistant',content:result.reply});
  }catch(error){
    console.warn('[jax-braga-ai] backend unavailable; using local fallback',error);
    const reply=localReply(text);
    addMessage('assistant',reply);
    history.push({role:'assistant',content:reply});
  }finally{
    clearTimeout(timer);
    chatInput.disabled=false;
    chatInput.focus();
  }
}
chatForm?.addEventListener('submit',e=>{e.preventDefault();void sendMessage(chatInput?.value)});
document.querySelectorAll('[data-message]').forEach(button=>button.addEventListener('click',()=>void sendMessage(button.dataset.message)));
