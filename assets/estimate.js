// Photo estimator: rooms + photos + live price. Submits through /api/lead.php (same pipeline as the other forms).
(()=>{
const form=document.getElementById('est-form');if(!form)return;
const INSTALL=3.99;
const REMOVAL={none:{label:'No removal (bare floor / we\u2019ll check)',rate:0},carpet:{label:'Carpet & pad removal',rate:0.50},floating:{label:'Floating LVP / laminate removal',rate:0.75},tile:{label:'Tile or glued-down removal (from)',rate:2.00}};
const MAX_PHOTOS=12;
const roomsEl=document.getElementById('rooms'),linesEl=document.getElementById('est-lines'),sumEl=document.getElementById('est-sum');
const status=form.querySelector('.form-status');
const loadedAt=Date.now();let seq=0;
const money=n=>'$'+Math.round(n).toLocaleString('en-US');
const rate=n=>'$'+n.toFixed(2);
const photos=new Map(); // roomId -> [{blob,url}]
const push=(e,p)=>{try{dataLayerPush(e,p)}catch{}};

function addRoom(){
  const id=++seq;photos.set(id,[]);
  const el=document.createElement('div');el.className='room';el.dataset.id=id;
  el.innerHTML=`<div class="room-head"><input name="room_name" value="Room ${id}" maxlength="40" aria-label="Room name"><button type="button" class="room-remove">Remove</button></div>
<label class="photo-btn">📷 Take or add photos<input type="file" accept="image/*" capture="environment" multiple></label><div class="thumbs"></div>
<div class="dims"><label>Length (ft)<input class="len" type="number" inputmode="decimal" min="0" max="200" step="0.5" placeholder="12"></label><span class="x">×</span><label>Width (ft)<input class="wid" type="number" inputmode="decimal" min="0" max="200" step="0.5" placeholder="14"></label></div>
<p class="or">— or —</p><label>Total sq ft for this room<input class="sq" type="number" inputmode="numeric" min="0" max="20000" step="1" placeholder="e.g. 250"></label>
<div class="opts" role="radiogroup" aria-label="Current floor removal">${Object.entries(REMOVAL).map(([k,v])=>`<label><input type="radio" name="rem${id}" value="${k}" ${k==='none'?'checked':''}> ${v.label}${v.rate?` · ${rate(v.rate)}/sq ft`:''}</label>`).join('')}</div>
<p class="room-sub">0 sq ft</p>`;
  roomsEl.appendChild(el);
  el.querySelector('.room-remove').onclick=()=>{if(roomsEl.children.length<=1)return;photos.get(id).forEach(p=>URL.revokeObjectURL(p.url));photos.delete(id);el.remove();calc();};
  el.querySelectorAll('input').forEach(i=>i.addEventListener('input',calc));
  el.querySelector('.len').addEventListener('input',()=>el.querySelector('.sq').value='');
  el.querySelector('.wid').addEventListener('input',()=>el.querySelector('.sq').value='');
  el.querySelector('.sq').addEventListener('input',()=>{el.querySelector('.len').value='';el.querySelector('.wid').value='';});
  el.querySelector('input[type=file]').addEventListener('change',async e=>{
    const files=[...e.target.files];e.target.value='';
    for(const f of files){
      if(totalPhotos()>=MAX_PHOTOS){setStatus(`Up to ${MAX_PHOTOS} photos total, please.`,'error');break;}
      try{const blob=await shrink(f);photos.get(id).push({blob,url:URL.createObjectURL(blob)});}catch{setStatus('One photo could not be read. Try another.','error');}
    }
    renderThumbs(el,id);push('estimate_photo_add',{photo_count:totalPhotos()});
  });
  calc();
}
function renderThumbs(el,id){const t=el.querySelector('.thumbs');t.replaceChildren();photos.get(id).forEach((p,i)=>{const f=document.createElement('figure');f.innerHTML=`<img alt="Room photo ${i+1}" src="${p.url}"><button type="button" aria-label="Remove photo">×</button>`;f.querySelector('button').onclick=()=>{URL.revokeObjectURL(p.url);photos.get(id).splice(i,1);renderThumbs(el,id);};t.appendChild(f);});}
const totalPhotos=()=>[...photos.values()].reduce((a,l)=>a+l.length,0);
// Resize on the phone (max 1600px JPEG) so uploads stay small and fast.
function shrink(file){return new Promise((res,rej)=>{const img=new Image();const u=URL.createObjectURL(file);img.onload=()=>{const k=Math.min(1,1600/Math.max(img.width,img.height));const c=document.createElement('canvas');c.width=Math.round(img.width*k);c.height=Math.round(img.height*k);c.getContext('2d').drawImage(img,0,0,c.width,c.height);URL.revokeObjectURL(u);c.toBlob(b=>b?res(b):rej(),'image/jpeg',0.78);};img.onerror=()=>{URL.revokeObjectURL(u);rej();};img.src=u;});}

function rooms(){return [...roomsEl.children].map((el,i)=>{
  const len=+el.querySelector('.len').value||0,wid=+el.querySelector('.wid').value||0,sqIn=+el.querySelector('.sq').value||0;
  const sq=Math.round(sqIn>0?sqIn:len*wid);const rem=el.querySelector('input[type=radio]:checked')?.value||'none';
  return {id:+el.dataset.id,el,name:(el.querySelector('[name=room_name]').value||`Room ${i+1}`).trim(),len,wid,sq,rem,dims:sqIn>0?`${sq} sq ft`:(len&&wid?`${len} x ${wid} ft = ${sq} sq ft`:'size not given')};
});}
function totals(){const rs=rooms();const sq=rs.reduce((a,r)=>a+r.sq,0);const rem={};rs.forEach(r=>{if(r.rem!=='none'&&r.sq)rem[r.rem]=(rem[r.rem]||0)+r.sq;});
  // Under 500 sq ft the site's small-room flat prices apply (they include carpet removal & haul-off).
  const tier=sq>0&&sq<500?[[150,895,'up to 150 sq ft'],[250,1195,'151-250 sq ft'],[350,1495,'251-350 sq ft'],[499,1895,'351-499 sq ft']].find(t=>sq<=t[0]):null;
  const lines=tier?[[`Small-room flat price (${tier[2]}, carpet removal included)`,tier[1]]]:[[`Install ${sq.toLocaleString('en-US')} sq ft × ${rate(INSTALL)}`,sq*INSTALL]];
  Object.entries(rem).forEach(([k,s])=>{if(tier&&k==='carpet')return;lines.push([`${REMOVAL[k].label} ${s.toLocaleString('en-US')} sq ft × ${rate(REMOVAL[k].rate)}`,s*REMOVAL[k].rate]);});
  return {rs,sq,lines,total:lines.reduce((a,l)=>a+l[1],0)};}
function calc(){const t=totals();t.rs.forEach(r=>r.el.querySelector('.room-sub').textContent=`${r.sq.toLocaleString('en-US')} sq ft`);
  linesEl.replaceChildren(...t.lines.map(([l,v])=>{const li=document.createElement('li');li.innerHTML=`<span></span><span>${money(v)}</span>`;li.firstChild.textContent=l;return li;}));
  sumEl.textContent=money(t.total);
}
function setStatus(m,c){status.textContent=m;status.className='form-status '+(c||'');}
document.getElementById('add-room').onclick=()=>{addRoom();push('estimate_room_add',{room_count:roomsEl.children.length});};
addRoom();

const usPhone=v=>{let d=v.replace(/\D/g,'');if(d.length===11&&d[0]==='1')d=d.slice(1);return /^[2-9]\d{2}[2-9]\d{6}$/.test(d);};
form.addEventListener('submit',async e=>{
  e.preventDefault();if(form.dataset.sending==='1')return;
  const t=totals();const f=form.elements;
  if(!f.name.value.trim()){setStatus('Please enter your name.','error');f.name.focus();return;}
  if(!usPhone(f.phone.value)){setStatus('Please enter a valid 10-digit US phone number.','error');f.phone.focus();return;}
  if(!/^\d{5}$/.test(f.zip.value.trim())){setStatus('Please enter a 5-digit ZIP code.','error');f.zip.focus();return;}
  if(!t.sq&&!totalPhotos()){setStatus('Add a room size or at least one photo.','error');return;}
  const color=f.color_pick.value||'';
  const summary=['PHOTO ESTIMATOR',...t.rs.map(r=>`- ${r.name}: ${r.dims}; removal: ${r.rem==='none'?'none':REMOVAL[r.rem].label+' '+rate(REMOVAL[r.rem].rate)+'/sq ft'}; photos: ${photos.get(r.id).length}`),...t.lines.map(([l,v])=>`${l} = ${money(v)}`),`ESTIMATED TOTAL: ${money(t.total)} (shown to customer; leveling/molding/stairs/dumpster quoted separately)`,`Color: ${color||'not chosen'}`].join('\n');
  const fd=new FormData();
  [['name',f.name.value],['phone',f.phone.value],['zip',f.zip.value.trim()],['email',f.email.value],['website',f.website.value]].forEach(([k,v])=>fd.append(k,v));
  fd.append('service','LVP Installed');fd.append('page','Photo estimator');fd.append('sqft',String(t.sq));
  const rems=new Set(t.rs.map(r=>r.rem));fd.append('current_floor',rems.has('carpet')?'Carpet':rems.has('tile')?'Tile':rems.has('floating')?'Wood / laminate':'Other / not sure');
  fd.append('color',color);fd.append('estimate_summary',summary);fd.append('estimated_total',String(Math.round(t.total)));fd.append('elapsed_ms',String(Date.now()-loadedAt));
  try{['utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','gbraid','wbraid','fbclid'].forEach(k=>{const v=new URLSearchParams(location.search).get(k)||sessionStorage.getItem('jax_'+k);if(v)fd.append(k,v);});}catch{}
  t.rs.forEach(r=>photos.get(r.id).forEach((p,i)=>fd.append('photos[]',p.blob,`${r.name.replace(/[^a-z0-9]+/gi,'-').toLowerCase()||'room'}-${i+1}.jpg`)));
  form.dataset.sending='1';setStatus(totalPhotos()?'Uploading photos…':'Sending…','');
  push('lead_form_submit',{service:'LVP Installed',form_name:'photo-estimator'});
  try{
    const response=await fetch(form.action,{method:'POST',body:fd,headers:{Accept:'application/json'}});
    const result=await response.json();
    if(!response.ok||!result.ok)throw new Error(result.message||'Unable to send');
    setStatus('Thank you! We got your rooms and photos. Oziel will text you your written quote shortly.','success');
    if(result.trackConversion){if(typeof window.jaxTrackLeadConversion==='function')window.jaxTrackLeadConversion();push('generate_lead',{service:'LVP Installed',form_name:'photo-estimator',source_page:document.title,lead_source:'photo_estimator'});}
  }catch(err){setStatus(err.message&&err.message!=='Unable to send'?err.message:'We could not send the request. Please call or text (904) 520-1994.','error');push('lead_form_error',{form_name:'photo-estimator'});}
  finally{form.dataset.sending='0';}
});
})();
