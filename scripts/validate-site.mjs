import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const errors = [];
const checked = [];
const legacyIds = ['GTM-MF6BLDTV','AW-18110072514','G-STY53PJMCS','1432051588726720'];
const productionIds = ['GTM-W6P5NQ2X','AW-18023260184','G-MQ4DLFWW0F'];

function err(msg){ errors.push(msg); }
function read(rel){ return fs.readFileSync(path.join(root, rel), 'utf8'); }
function exists(rel){ return fs.existsSync(path.join(root, rel)); }
function walk(dir=''){
  const abs = path.join(root, dir);
  return fs.readdirSync(abs,{withFileTypes:true}).flatMap(e=>{
    const rel = path.posix.join(dir,e.name);
    if(['.git','node_modules'].includes(e.name)) return [];
    return e.isDirectory()?walk(rel):[rel];
  });
}
function urlToFile(urlPath){
  let p = urlPath.split('#')[0].split('?')[0];
  if(!p || p==='/') return 'index.html';
  p = p.replace(/^\//,'');
  if(p.endsWith('/')) return `${p}index.html`;
  return p;
}

const files = walk();
const htmlFiles = files.filter(f=>f.endsWith('.html'));
const runtimeFiles = files.filter(f=>/\.(html|js|php)$/.test(f));

for(const rel of runtimeFiles){
  const text = read(rel);
  for(const id of legacyIds){
    if(text.includes(id)) err(`${rel}: forbidden legacy/cross-business ID ${id}`);
  }
}

for(const rel of htmlFiles){
  const html = read(rel);
  const is404 = rel === '404.html';
  checked.push(rel);
  if(!/<title>[^<]+<\/title>/i.test(html)) err(`${rel}: missing <title>`);
  if(!is404 && !/<meta\s+name=["']description["'][^>]+content=["'][^"']+/i.test(html)) err(`${rel}: missing meta description`);
  if(!is404 && !/<link\s+rel=["']canonical["'][^>]+href=["']https:\/\/jaxtileandlvp\.com\//i.test(html)) err(`${rel}: missing/invalid canonical`);
  if(!/<h1[\s>][\s\S]*?<\/h1>/i.test(html)) err(`${rel}: missing H1`);
  if(!is404){
    if(!html.includes('/assets/google-tracking.js')) err(`${rel}: production tracking bootstrap missing`);
    if(!html.includes('GTM-W6P5NQ2X')) err(`${rel}: GTM noscript ID missing`);
  }

  const jsonLd = [...html.matchAll(/<script[^>]+type=["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/gi)];
  for(const [i,m] of jsonLd.entries()){
    try{ JSON.parse(m[1]); } catch(e){ err(`${rel}: JSON-LD block ${i+1} invalid: ${e.message}`); }
  }

  for(const m of html.matchAll(/href=["']([^"']+)["']/gi)){
    const href = m[1];
    if(!href.startsWith('/') || href.startsWith('//')) continue;
    const target = urlToFile(href);
    if(!exists(target)) err(`${rel}: broken internal link ${href} -> ${target}`);
  }
}

for(const required of [
  'index.html','vinyl-flooring-jacksonville/index.html','tile-installation-jacksonville/index.html',
  'floor-removal-preparation/index.html','service-areas/index.html','projects/index.html',
  'contact/index.html','privacy/index.html','robots.txt','sitemap.xml',
  'assets/google-tracking.js','assets/site.js','api/lead.php'
]) if(!exists(required)) err(`required file missing: ${required}`);

if(exists('assets/google-tracking.js')){
  const tracking = read('assets/google-tracking.js');
  for(const id of productionIds) if(!tracking.includes(id)) err(`assets/google-tracking.js: required production ID missing: ${id}`);
}

if(exists('assets/site.js')){
  const site = read('assets/site.js');
  if(site.includes('phone_number:')) err('assets/site.js: phone number must not be sent to analytics');
  if(/dataLayerPush\([^\n]*\.\.\.attribution/.test(site)) err('assets/site.js: click IDs/UTMs must not be sent to analytics');
  if(!site.includes("dataLayerPush('generate_lead'")) err('assets/site.js: generate_lead event missing');
  if(!site.includes('if(result.trackConversion)')) err('assets/site.js: generate_lead must be gated by backend trackConversion');
}

if(exists('api/lead.php')){
  const lead = read('api/lead.php');
  if(!lead.includes('trackConversion')) err('api/lead.php: conversion-safe response missing');
  if(!lead.includes('Tile Installation')) err('api/lead.php: tile leads must be an allowed service');
  if(!lead.includes('JAX_ZOHO_CLIENT_ID')) err('api/lead.php: Zoho credentials must be read from the environment');
  if(/client_secret'\s*=>\s*'[^'.\s][^']*'/.test(lead) || /refresh_token'\s*=>\s*'[^'.\s][^']*'/.test(lead)) err('api/lead.php: hardcoded Zoho secret');
}

if(exists('robots.txt')){
  const robots = read('robots.txt');
  if(!/Sitemap:\s*https:\/\/jaxtileandlvp\.com\/sitemap\.xml/i.test(robots)) err('robots.txt: sitemap declaration missing');
  if(!/Disallow:\s*\/api\//i.test(robots)) err('robots.txt: /api/ should be crawl-blocked');
}

function canonicalHref(html){
  const tag = html.match(/<link\b[^>]*\brel=["']canonical["'][^>]*>/i);
  if(!tag) return '';
  const href = tag[0].match(/\bhref=["']([^"']+)["']/i);
  return href ? href[1] : '';
}

if(exists('sitemap.xml')){
  const sitemap = read('sitemap.xml');
  const locs = [...sitemap.matchAll(/<loc>(https:\/\/jaxtileandlvp\.com\/[^<]*)<\/loc>/g)].map(m=>m[1]);
  if(!locs.length) err('sitemap.xml: no jaxtileandlvp.com URLs found');
  const requiredLocs = [
    'https://jaxtileandlvp.com/',
    'https://jaxtileandlvp.com/vinyl-flooring-jacksonville/',
    'https://jaxtileandlvp.com/tile-installation-jacksonville/',
  ];
  for(const url of requiredLocs) if(!locs.includes(url)) err(`sitemap.xml: missing canonical URL ${url}`);
  for(const url of locs){
    if(/\.html(?:$|\?)/i.test(url)) err(`sitemap.xml: non-canonical .html URL ${url}`);
    const p = new URL(url).pathname;
    if(p !== '/' && !p.endsWith('/')) err(`sitemap.xml: URL must be the trailing-slash canonical form: ${url}`);
    const target = urlToFile(p);
    if(!exists(target)) err(`sitemap.xml: URL has no matching file: ${url} -> ${target}`);
    else if(canonicalHref(read(target)) !== url) err(`${target}: canonical must equal sitemap URL ${url}`);
  }
}

for(const rel of ['vinyl-flooring-jacksonville/index.html','tile-installation-jacksonville/index.html']){
  if(!exists(rel)) continue;
  const html = read(rel);
  if(!html.includes('FAQPage')) err(`${rel}: FAQPage JSON-LD missing`);
  if(!/<details[\s>]/i.test(html)) err(`${rel}: visible FAQ missing`);
  if(!html.includes('Braga Remodeling')) err(`${rel}: must state the page is operated by Braga Remodeling`);
}

for(const rel of htmlFiles){
  if(rel === '404.html') continue;
  const html = read(rel);
  if(/AggregateRating|reviewCount|ratingValue/i.test(html)) err(`${rel}: self-serving rating markup is not allowed`);
  if(!html.includes('(904) 520-1994') && !html.includes('+1-904-520-1994') && !html.includes('+19045201994')) err(`${rel}: NAP phone missing`);
}

if(errors.length){
  console.error(`Site validation failed with ${errors.length} issue(s):`);
  for(const e of errors) console.error(`- ${e}`);
  process.exit(1);
}
console.log(`Site validation passed: ${checked.length} HTML files checked; tracking, sitemap, robots, JSON-LD and internal links are consistent.`);
