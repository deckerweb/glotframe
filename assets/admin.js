(function () {
 'use strict';
 const form=document.getElementById('gf-settings-form');
 const notify=()=>form?.dispatchEvent(new Event('gf-change'));
 if (window.jQuery?.fn.wpColorPicker) { jQuery('.gf-color').wpColorPicker({change:()=>setTimeout(notify,0),clear:()=>setTimeout(notify,0)}); }
 document.querySelectorAll('input[name="glotframe_settings[preset]"]').forEach(function(radio){function applyPreset(){
  const preset=glotframeAdmin.presets[radio.value];if(!preset)return;
  Object.entries(preset.colors).forEach(function([key,color]){const field=document.getElementById('gf-'+key);if(!field)return;
   if(window.jQuery?.fn.wpColorPicker) {jQuery(field).wpColorPicker('color',color);} else {field.value=color;}
  });
  document.getElementById('gf-palette-ready').value='1';
 } radio.addEventListener('change',applyPreset);radio.addEventListener('click',applyPreset);});
 const input = document.getElementById('gf-background_image');
 const preview = document.getElementById('gf-image-preview');
 document.getElementById('gf-image-choose')?.addEventListener('click', function () {
  if (!window.wp?.media) return;
  const picker = wp.media({title:glotframeAdmin.title,button:{text:glotframeAdmin.choose},library:{type:'image'},multiple:false});
  picker.on('select', function () {
   const image = picker.state().get('selection').first().toJSON();
   if (image.mime === 'image/svg+xml') return;
   input.value = String(image.id);
   preview.replaceChildren();
   const img = document.createElement('img');
   img.src = image.sizes?.thumbnail?.url || image.url;
   img.alt = image.alt || '';
   preview.append(img);notify();
  });
  picker.open();
 });
 document.getElementById('gf-image-remove')?.addEventListener('click', function () {input.value = '0';preview.replaceChildren();notify();});

 if(form){
  const bar=form.querySelector('.gf-savebar'),status=bar?.querySelector('.gf-save-status'),dot=bar?.querySelector('.gf-save-dot');
  const snapshot=()=>JSON.stringify(Array.from(new FormData(form)).filter(([name])=>name.startsWith('glotframe_settings[')&&name!=='glotframe_settings[palette_ready]').map(([name,value])=>[name,String(value)]).sort((a,b)=>(a[0]+a[1]).localeCompare(b[0]+b[1])));
  const baseline=snapshot();
  const dependent=()=>{
   const row=(key,show)=>{const target=document.getElementById('gf-'+key)?.closest('tr');if(target)target.hidden=!show;};
   const boxed=document.getElementById('gf-layout')?.value==='boxed';row('width',boxed);row('work_width',boxed&&!!document.getElementById('gf-wide_editor')?.checked);
   const toolbar=!!document.getElementById('gf-toolbar')?.checked;
   ['toolbar_title','toolbar_position','toolbar_projects','toolbar_subprojects'].forEach(key=>row(key,toolbar));
   const relative=toolbar&&['before','after'].includes(document.getElementById('gf-toolbar_position')?.value);row('toolbar_target',relative);const reference=document.getElementById('gf-toolbar_target');if(reference)reference.required=relative;
   const icon=form.querySelector('input[name="glotframe_settings[toolbar_icon]"]')?.closest('tr');if(icon)icon.hidden=!toolbar;
   row('toolbar_subprojects',toolbar&&!!document.getElementById('gf-toolbar_projects')?.checked);
   row('toolbar_depth',toolbar&&!!document.getElementById('gf-toolbar_projects')?.checked&&!!document.getElementById('gf-toolbar_subprojects')?.checked);
  };
  const layoutLinks=()=>{
   form.querySelectorAll('.gf-layout-edit').forEach(link=>{
    const field=document.getElementById(link.dataset.gfLayout);
    const url=field?glotframeAdmin.layoutLinks?.[field.value]:null;
    link.hidden=!url;
    if(url)link.href=url;else link.removeAttribute('href');
   });
   form.querySelectorAll('.gf-layout-unavailable').forEach(note=>{
    const field=document.getElementById(note.dataset.gfLayout);
    note.hidden=field?.value!==note.dataset.layoutId;
    if(field){if(note.hidden)field.removeAttribute('aria-describedby');else field.setAttribute('aria-describedby',note.id);}
   });
  };
  const update=()=>{const dirty=snapshot()!==baseline;if(status){status.textContent=dirty?glotframeAdmin.dirty:(glotframeAdmin.errors?glotframeAdmin.review:glotframeAdmin.clean);status.classList.toggle('gf-is-dirty',dirty);}if(dot)dot.hidden=!dirty;dependent();layoutLinks();};
  ['input','change','gf-change'].forEach(type=>form.addEventListener(type,()=>setTimeout(update,0)));
  form.addEventListener('reset',()=>setTimeout(update,0));
  let submitting=false;
  form.addEventListener('submit',event=>{if(submitting){event.preventDefault();return;}if(event.defaultPrevented)return;submitting=true;if(status)status.textContent=glotframeAdmin.saving;});
  document.addEventListener('keydown',event=>{
   if(!(event.ctrlKey||event.metaKey)||event.altKey||event.shiftKey||event.key.toLowerCase()!=='s'||document.querySelector('dialog[open]'))return;
   const button=bar?.querySelector('button[type="submit"]');
   if(!button||button.matches(':disabled'))return;
   event.preventDefault();
   if(!event.repeat&&!submitting&&snapshot()!==baseline)form.requestSubmit(button);
  });
  if(bar){bar.classList.add('gf-savebar--fixed');document.querySelector('.glotframe-admin')?.classList.add('gf-has-savebar');}
  update();
 }
}());
