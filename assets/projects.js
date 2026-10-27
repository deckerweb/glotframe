/* Enhance existing GlotPress nodes, retaining its links, descriptions and permissions. */
(function(){
 'use strict';
 const c=window.glotframeProjects;
 if(!c)return;
 const main=document.getElementById('glotframe-main');
 // Older installations may omit the #projects wrapper entirely. Match native root URLs first.
 if(main&&document.body.classList.contains('glotframe-view-projects')&&!main.querySelector('#projects')){
  const paths=new Set((c.roots||[]).map(url=>new URL(url,location.href).pathname.replace(/\/$/,'')));
  const candidates=Array.from(main.querySelectorAll('ul,ol,dl')).filter(list=>!list.closest('.breadcrumb,.gf-data-block,.actionlist'));
  const list=candidates.find(list=>Array.from(list.querySelectorAll('a[href]')).some(a=>paths.has(new URL(a.href,location.href).pathname.replace(/\/$/,''))))||candidates[0];
  if(list)list.id='projects';
 }
 if(document.body.classList.contains('glotframe-view-projects')){const heading=main?.querySelector('#projects>h2')||main?.querySelector(':scope>h2');if(heading&&c.rootLabel)heading.textContent=c.rootLabel;}

 document.querySelectorAll('#glotframe-main #projects>dl,#glotframe-main #sub-projects>dl,#glotframe-main dl#projects,#glotframe-main dl#sub-projects').forEach(list=>{
  const nodes=Array.from(list.children);
  nodes.forEach(node=>{if(node.tagName!=='DT')return;const card=document.createElement('div');card.className='gf-project-card';list.insertBefore(card,node);const next=node.nextElementSibling;card.append(node);if(next&&next.tagName==='DD')card.append(next);});
  list.classList.add('gf-project-collection');
 });
 document.querySelectorAll('#glotframe-main #projects>ul,#glotframe-main #sub-projects>ul,#glotframe-main ul#projects,#glotframe-main ul#sub-projects').forEach(list=>{list.classList.add('gf-project-collection');Array.from(list.children).forEach(item=>item.classList.add('gf-project-card'));});
 document.querySelectorAll('body.glotframe .gf-project-collection').forEach((list,index)=>{
  if(list.dataset.gfReady)return;list.dataset.gfReady='1';
  const cards=Array.from(list.children).filter(e=>e.classList.contains('gf-project-card'));
  if(!cards.length)return;
  list.id=list.id||'gf-projects-'+index;
  const controls=document.createElement('div');controls.className='gf-project-controls';
  const label=document.createElement('label');label.textContent=c.search;
  const input=document.createElement('input');input.type='search';input.setAttribute('aria-controls',list.id);label.append(input);controls.append(label);
  const group=document.createElement('div');group.className='gf-project-views';group.setAttribute('role','group');group.setAttribute('aria-label',c.viewLabel);
  let saved='';try{saved=localStorage.getItem('glotframe-project-view:'+c.site)||'';}catch(error){}
  let view=list.dataset.gfView||(['list','grid'].includes(saved)?saved:c.view);
  const buttons={};
  const apply=()=>{list.classList.toggle('gf-view-grid',view==='grid');list.classList.toggle('gf-view-list',view!=='grid');Object.entries(buttons).forEach(([value,button])=>button.setAttribute('aria-pressed',String(value===view)));};
  ['list','grid'].forEach(value=>{const button=document.createElement('button');button.type='button';button.textContent=c[value];button.setAttribute('aria-controls',list.id);button.addEventListener('click',()=>{view=value;apply();try{localStorage.setItem('glotframe-project-view:'+c.site,value);}catch(error){}});buttons[value]=button;group.append(button);});controls.append(group);
  const empty=document.createElement('p');empty.textContent=c.empty;empty.className='gf-project-empty';empty.hidden=true;empty.setAttribute('role','status');
  const normalize=value=>value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase();
  input.addEventListener('input',()=>{const query=normalize(input.value.trim());let found=0;cards.forEach(card=>{card.hidden=!normalize(card.textContent).includes(query);if(!card.hidden)found++;});empty.hidden=found>0;});
  list.before(controls);list.after(empty);apply();
 });
})();
