const GA4_MEASUREMENT_ID='G-MQ4DLFWW0F';
// TODO(Oziel): booking page for the free measure (Calendly or Zoho Bookings), e.g. 'https://calendly.com/<you>/free-lvp-measure'.
// While empty, every .js-booking button opens a pre-filled text message to (904) 520-1994.
const BOOKING_URL='';
// TODO(Oziel): Instagram profile URL, e.g. 'https://www.instagram.com/<handle>/'. While empty, every Instagram link/card stays hidden.
const INSTAGRAM_URL='';

const ctaLocation=(el)=>el?.closest?.('header')?'header':el?.closest?.('footer')?'footer':el?.closest?.('.mobile-bar')?'mobile_bar':el?.closest?.('[data-cta-location]')?.getAttribute('data-cta-location')||'body';
// Map site events to Meta Pixel standard events (only when /assets/meta-pixel.js has an ID). No personal data is sent.
const META_EVENTS={generate_lead:'Lead',phone_click:'Contact',sms_click:'Contact',booking_click:'Schedule'};
const dataLayerPush=(event,params={})=>{
  const safe={site_brand:'jax_tile_lvp',page_path:location.pathname,language:document.documentElement.lang||'en-US',...params};
  window.dataLayer=window.dataLayer||[];
  window.dataLayer.push({event,...safe});
  if(typeof window.gtag==='function')window.gtag('event',event,{...safe,send_to:GA4_MEASUREMENT_ID});
  if(typeof window.fbq==='function'){if(META_EVENTS[event])window.fbq('track',META_EVENTS[event],{content_name:event,cta_location:safe.cta_location||''});else if(event==='calculator_estimate')window.fbq('trackCustom','CalculatorEstimate',{sqft_bucket:safe.sqft_bucket||''});}
};
const copy={sending:'Sending…',success:'Thank you! We got your request. Oziel will text you shortly.',error:'We could not send the request. Please call or text (904) 520-1994.',phone:'Please enter a valid 10-digit US phone number.',zip:'Please enter a 5-digit ZIP code.',name:'Please enter your name.'};

// Keep the Jax mark readable on phones.
const brandFix=document.createElement('style');
brandFix.textContent='@media(max-width:850px){.site-header{height:86px}.brand img{width:236px;height:68px}}@media(max-width:620px){.brand img{width:220px;height:64px}}';
document.head.appendChild(brandFix);

// Attribution is retained only for the lead backend/email. Do not send click IDs,
// contact details or free-text project information to GA4, GTM, Google Ads or Meta.
const params=new URLSearchParams(window.location.search);
const attribution={};
['utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','gbraid','wbraid','fbclid'].forEach(key=>{const value=params.get(key);if(value){attribution[key]=value;try{sessionStorage.setItem('jax_'+key,value)}catch{}}else{try{const saved=sessionStorage.getItem('jax_'+key);if(saved)attribution[key]=saved}catch{}}});

document.querySelectorAll('a[href^="tel:"]').forEach(link=>link.addEventListener('click',()=>dataLayerPush('phone_click',{cta_location:ctaLocation(link)})));
document.querySelectorAll('a[href^="sms:"]:not(.js-booking)').forEach(link=>link.addEventListener('click',()=>dataLayerPush('sms_click',{cta_location:ctaLocation(link)})));
document.querySelectorAll('a[href^="mailto:"]').forEach(link=>link.addEventListener('click',()=>dataLayerPush('email_click',{cta_location:ctaLocation(link)})));
document.querySelectorAll('.track-estimate').forEach(link=>link.addEventListener('click',()=>dataLayerPush('cta_click',{cta_name:'get_price',cta_location:ctaLocation(link)})));

// Free-measure booking (placeholder until BOOKING_URL is set).
document.querySelectorAll('.js-booking').forEach(link=>{if(BOOKING_URL){link.href=BOOKING_URL;link.target='_blank';link.rel='noopener';}link.addEventListener('click',()=>dataLayerPush('booking_click',{cta_location:ctaLocation(link),booking_type:BOOKING_URL?'calendar':'sms_placeholder'}));});
if(INSTAGRAM_URL){document.querySelectorAll('.js-instagram').forEach(link=>{link.href=INSTAGRAM_URL;link.hidden=false;});document.querySelectorAll('.js-instagram-card').forEach(el=>el.hidden=false);}
document.querySelectorAll('.js-instagram').forEach(link=>link.addEventListener('click',()=>dataLayerPush('social_click',{social_network:'instagram',cta_location:ctaLocation(link)})));

// ---------- 60-second price quiz ----------
const PRICING={promo:3.99,plus:4.49,premium:4.99,minSqft:500,shoeLF:2.99,lfPerSqftLow:.30,lfPerSqftHigh:.45,roomSqft:170,measureSlack:.10,removal:{carpet:[0,0],tile:[2.00,2.50],glued:[2.00,2.50],floating:[.75,.75],concrete:[0,0]},stairs:{'0':[0,0],few:[1,5],one:[12,14],two:[24,28]},stairLow:85,stairHigh:110};
const money=n=>'$'+Math.round(n).toLocaleString('en-US');
const quiz=document.querySelector('#lvp-quiz');
let calcSummary='';
if(quiz){
  const T={size:'~{s} sq ft',min:' (under the 500 sq ft package minimum)',from:'from ',base:'Floor + installation',rem:'Removal of current floor',stairs:'Stairs',shoe:'Shoe molding (Promo/Premium)',shoeUnsure:'Shoe molding, if wanted (Promo/Premium)'};
  const steps=[...quiz.querySelectorAll('.q-step')],result=quiz.querySelector('.q-result'),next=quiz.querySelector('.q-next'),back=quiz.querySelector('.q-back'),nav=quiz.querySelector('.q-nav'),bar=quiz.querySelector('.quiz-progress span'),now=quiz.querySelector('.q-now'),top=quiz.querySelector('.quiz-top');
  let step=1,started=false;
  const val=name=>quiz.querySelector(`input[name="${name}"]:checked`)?.value||'';
  const sqftOf=()=>{const typed=parseFloat(quiz.querySelector('#q-sqft')?.value);if(Number.isFinite(typed)&&typed>0)return typed;const r=parseFloat(val('q_rooms'));return r?r*PRICING.roomSqft:0;};
  const answered=n=>n===1?sqftOf()>0:n===2?!!val('q_floor'):n===3?!!val('q_stairs'):n===4?!!val('q_shoe'):true;
  const show=n=>{step=n;steps.forEach(s=>s.hidden=Number(s.dataset.step)!==n);const done=n>steps.length;result.hidden=!done;nav.hidden=done;top.hidden=done;back.hidden=n===1;if(!done){now.textContent=n;bar.style.width=(n/steps.length*100)+'%';next.disabled=!answered(n);}if(done)compute();quiz.dataset.step=String(n);};
  const go=n=>{show(n);dataLayerPush(n>steps.length?'quiz_complete':'quiz_step',n>steps.length?{cta_location:'quiz',sqft_bucket:bucket(sqftOf()),current_floor:val('q_floor'),stairs:val('q_stairs'),shoe_molding:val('q_shoe')}:{cta_location:'quiz',quiz_step:n});if(n>steps.length)dataLayerPush('calculator_estimate',{cta_location:'quiz',sqft_bucket:bucket(sqftOf()),quote_type:sqftOf()<PRICING.minSqft?'custom':'package'});quiz.scrollIntoView({behavior:'smooth',block:'start'});};
  const bucket=s=>s<500?'<500':s<1000?'500-999':s<1500?'1000-1499':s<2500?'1500-2499':'2500+';
  function compute(){
    const sqft=sqftOf(),hi=sqft*(1+PRICING.measureSlack),bL=Math.max(sqft,PRICING.minSqft),bH=Math.max(hi,PRICING.minSqft);
    const [rL,rH]=PRICING.removal[val('q_floor')]||[0,0],remL=sqft*rL,remH=hi*rH;
    const [sL,sH]=PRICING.stairs[val('q_stairs')]||[0,0],stL=sL*PRICING.stairLow,stH=sH*PRICING.stairHigh;
    const shoe=val('q_shoe'),lfL=Math.round(sqft*PRICING.lfPerSqftLow),lfH=Math.round(hi*PRICING.lfPerSqftHigh);
    const shL=shoe==='yes'?lfL*PRICING.shoeLF:0,shH=shoe==='no'?0:lfH*PRICING.shoeLF;
    const range=(a,b)=>Math.round(a)===Math.round(b)?money(a):`${money(a)}–${money(b)}`;
    const promo=[bL*PRICING.promo+remL+stL+shL,bH*PRICING.promo+remH+stH+shH];
    const plus=[bL*PRICING.plus+remL+stL,bH*PRICING.plus+remH+stH];
    const premium=bL*PRICING.premium+remL+stL+shL;
    quiz.querySelector('.q-size').textContent=T.size.replace('{s}',Math.round(sqft).toLocaleString('en-US'))+(sqft<PRICING.minSqft?T.min:'');
    const small=sqft<PRICING.minSqft;
    quiz.dataset.quote=small?'custom':'package';
    quiz.querySelector('.q-custom')?.toggleAttribute('hidden',!small);
    [quiz.querySelector('.q-packages'),quiz.querySelector('#calc-breakdown'),quiz.querySelector('.calc-note')].forEach(el=>el?.toggleAttribute('hidden',small));
    const formHead=document.querySelector('#calc-lead-form .form-heading'),formBtn=document.querySelector('#calc-lead-form button[type=submit]');
    if(formHead){formHead.dataset.def=formHead.dataset.def||formHead.textContent;formHead.textContent=small?'Leave your number and we’ll text you about your custom quote.':formHead.dataset.def;}
    if(formBtn){formBtn.dataset.def=formBtn.dataset.def||formBtn.textContent;formBtn.textContent=small?'Text Me a Custom Quote':formBtn.dataset.def;}
    if(small){calcSummary=`Quiz: ~${Math.round(sqft)} sq ft (UNDER 500 sq ft: CUSTOM QUOTE, no package price shown); floor ${val('q_floor')}; stairs ${val('q_stairs')}; shoe ${val('q_shoe')}`;const form=document.querySelector('#calc-lead-form');if(form){const s=form.querySelector('[name="sqft"]');if(s)s.value=Math.round(sqft);const map={carpet:'Carpet',tile:'Tile',glued:'Wood / laminate',floating:'Wood / laminate',concrete:'Bare concrete slab'};const cf=form.querySelector('[name="current_floor"]');if(cf&&map[val('q_floor')])cf.value=map[val('q_floor')];}return;}
    quiz.querySelector('.q-promo').textContent=range(...promo);
    quiz.querySelector('.q-plus').textContent=range(...plus);
    quiz.querySelector('.q-premium').textContent=T.from+money(premium);
    const list=quiz.querySelector('#calc-breakdown');list.replaceChildren();
    const lines=[[T.base+' (Promo)',bL*PRICING.promo,bH*PRICING.promo]];
    if(remH)lines.push([T.rem,remL,remH]);if(stH)lines.push([T.stairs+` (${sL}–${sH})`,stL,stH]);if(shH)lines.push([(shoe==='yes'?T.shoe:T.shoeUnsure)+` (~${lfL}–${lfH} LF)`,shL,shH]);
    lines.forEach(([label,a,b])=>{const li=document.createElement('li');const s=document.createElement('span');s.textContent=label;const v=document.createElement('b');v.textContent=range(a,b);li.append(s,v);list.appendChild(li);});
    calcSummary=`Quiz: ~${Math.round(sqft)} sq ft; floor ${val('q_floor')}; stairs ${val('q_stairs')}; shoe ${shoe}; Promo ${range(...promo)}; Plus ${range(...plus)}; Premium from ${money(premium)}`;
    const form=document.querySelector('#calc-lead-form');
    if(form){const s=form.querySelector('[name="sqft"]');if(s)s.value=Math.round(sqft);const map={carpet:'Carpet',tile:'Tile',glued:'Wood / laminate',floating:'Wood / laminate',concrete:'Bare concrete slab'};const cf=form.querySelector('[name="current_floor"]');if(cf&&map[val('q_floor')])cf.value=map[val('q_floor')];}
  }
  quiz.addEventListener('change',e=>{
    if(!started){started=true;dataLayerPush('calculator_start',{cta_location:'quiz'});}
    if(e.target.closest('.q-step')){next.disabled=!answered(step);if(e.target.type==='radio'){if(e.target.name==='q_rooms'){const t=quiz.querySelector('#q-sqft');if(t)t.value='';}setTimeout(()=>go(step+1),220);}}
  });
  quiz.querySelector('#q-sqft')?.addEventListener('input',e=>{if(!started){started=true;dataLayerPush('calculator_start',{cta_location:'quiz'});}if(e.target.value)quiz.querySelectorAll('input[name="q_rooms"]').forEach(r=>r.checked=false);next.disabled=!answered(1);});
  quiz.querySelector('#q-sqft')?.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();if(answered(1))go(2);}});
  next.addEventListener('click',()=>{if(answered(step))go(step+1);});
  back.addEventListener('click',()=>show(Math.max(1,step-1)));
  quiz.querySelector('.q-restart')?.addEventListener('click',()=>{quiz.querySelectorAll('input[type=radio]').forEach(r=>r.checked=false);const t=quiz.querySelector('#q-sqft');if(t)t.value='';show(1);});
  show(1);
}

// ---------- Lead forms (hero, calculator, inner pages) ----------
const loadedAt=Date.now();
const digits=s=>(s||'').replace(/\D/g,'');
const usPhone=s=>{let d=digits(s);if(d.length===11&&d[0]==='1')d=d.slice(1);return /^[2-9]\d{2}[2-9]\d{6}$/.test(d)?d:'';};
document.querySelectorAll('form.lead-form').forEach(form=>{
  const status=form.querySelector('.form-status');
  const formName=form.dataset.formName||form.id||'lead_form';
  Object.entries(attribution).forEach(([name,value])=>{let input=form.querySelector(`[name="${name}"]`);if(!input){input=document.createElement('input');input.type='hidden';input.name=name;form.appendChild(input)}input.value=value});
  let pageInput=form.querySelector('[name="page"]');if(!pageInput){pageInput=document.createElement('input');pageInput.type='hidden';pageInput.name='page';form.appendChild(pageInput)}if(!pageInput.value)pageInput.value=document.title;
  const setStatus=(text,cls)=>{if(status){status.textContent=text;status.className='form-status'+(cls?' '+cls:'');}};
  form.addEventListener('submit',async e=>{
    e.preventDefault();
    if(form.dataset.sending==='1')return;
    const phone=form.elements.phone?.value||'',zip=(form.elements.zip?.value||'').trim();
    if(!(form.elements.name?.value||'').trim()){setStatus(copy.name,'error');form.elements.name?.focus();return;}
    if(!usPhone(phone)){setStatus(copy.phone,'error');form.elements.phone?.focus();return;}
    if(!/^\d{5}$/.test(zip)){setStatus(copy.zip,'error');form.elements.zip?.focus();return;}
    const elapsed=form.querySelector('[name="elapsed_ms"]');if(elapsed)elapsed.value=String(Date.now()-loadedAt);
    let quote=form.querySelector('[name="quote_summary"]');
    if(form.id==='calc-lead-form'&&calcSummary){if(!quote){quote=document.createElement('input');quote.type='hidden';quote.name='quote_summary';form.appendChild(quote);}quote.value=calcSummary;}
    form.dataset.sending='1';setStatus(copy.sending,'');
    dataLayerPush('lead_form_submit',{service:'LVP Installed',form_name:formName});
    try{
      const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json'}});
      const result=await response.json();
      if(!response.ok||!result.ok){const err=new Error(result.message||'Unable to send');err.field=result.field;throw err;}
      setStatus(copy.success,'success');
      if(result.trackConversion)dataLayerPush('generate_lead',{service:'LVP Installed',form_name:formName,source_page:document.title,lead_source:form.id==='calc-lead-form'?'price_quiz':'website_form'});
      form.reset();
    }catch(err){
      setStatus(err.field&&copy[err.field]?copy[err.field]:copy.error,'error');
      dataLayerPush('lead_form_error',{form_name:formName,error_field:err.field||'server'});
    }finally{form.dataset.sending='0';}
  });
});
