/* Keep the native GlotPress editor behavior, with a scoped theme-conflict recovery. */
document.body.classList.replace('no-js', 'js');
document.addEventListener('click', function (event) {
 const control=event.target instanceof Element ? event.target.closest('button.close, a.close') : null;
 const row=control?.closest('#glotframe-main table.translations tr.editor');
 const api=window.$gp?.editor;
 const current=api?.current;
 if(!row || !current || current[0]!==row || typeof api.hooks?.cancel!=='function') return;
 const preview=current.preview?.[0];
 const fields=Array.from(row.querySelectorAll('textarea')).filter(field=>field.name==='translation['+current.original_id+'][]');
 const values=current.original_translations;
 // A legacy anchor must not jump to the top when the native delegated handler is blocked.
 if(control.tagName==='A') event.preventDefault();
 setTimeout(function () {
  // Give GlotPress's own delegated listener first opportunity to restore its snapshot.
  if(api.current===current && getComputedStyle(row).display!=='none') api.hooks.cancel.call(control,event);
  if(api.current!==current && Array.isArray(values) && fields.length===values.length) {
   fields.forEach((field,index)=>{field.value=values[index];});
  }
  // Some theme styles can override jQuery's inline display:none. Only repair after cancel.
  if(api.current!==current && getComputedStyle(row).display!=='none') {
   row.style.setProperty('display','none','important');
   if(preview) preview.style.setProperty('display','table-row','important');
  }
 },0);
},true);

// Older GlotPress markup uses th.priority or identifies the column through its body cell.
const glotframeTable = document.querySelector('#glotframe-main table.translations');
if (glotframeTable) {
 const cell = glotframeTable.querySelector('tr.preview td.priority');
 const heading = glotframeTable.querySelector('thead th.gp-column-priority, thead th.priority') ||
  (cell && glotframeTable.tHead?.rows[0]?.cells[cell.cellIndex]);
 if (heading) {
  heading.classList.add('glotframe-priority-heading');
  heading.textContent = 'Prio';
 }
}
// Retain native GP actions while arranging the panel and exposing its expanded state.
const glotframeActions=document.getElementById('project-actions-toggle');
if(glotframeActions){
 const parent=glotframeActions.parentElement;parent.classList.add('gf-project-actions-panel');
 const panel=parent.querySelector('div.project-actions');
 if(panel){panel.id=panel.id||'gf-native-project-actions';glotframeActions.setAttribute('aria-controls',panel.id);const update=()=>glotframeActions.setAttribute('aria-expanded',String(getComputedStyle(panel).display!=='none'));new MutationObserver(update).observe(panel,{attributes:true,attributeFilter:['style','class']});update();}
}
document.querySelectorAll('#glotframe-main table.locales,#glotframe-main table.locale-sub-projects,#glotframe-main table.glossary').forEach(table=>{
 if(!table.parentElement.classList.contains('gf-table-scroll')){const wrap=document.createElement('div');wrap.className='gf-table-scroll';wrap.tabIndex=0;wrap.setAttribute('role','region');wrap.setAttribute('aria-label',table.querySelector('thead')?.textContent.trim().replace(/\s+/g,' ')||'GlotPress');table.before(wrap);wrap.append(table);}
 table.querySelectorAll('thead th[rowspan]').forEach(cell=>cell.removeAttribute('rowspan'));
 table.querySelectorAll('tbody td').forEach(cell=>{if(/^\d[\d.,\s]*$/.test(cell.textContent.trim()))cell.classList.add('gf-number');});
 if(table.classList.contains('locales'))table.querySelectorAll('tbody td:nth-child(2)').forEach(cell=>cell.dir='auto');
});
document.querySelectorAll('#glotframe-main input[name="new_glossary_entry[term]"]').forEach(input=>input.closest('form')?.classList.add('gf-glossary-form'));
// Repair raw native UI identifiers only, never project names or translated string content.
const glotframeLabels=window.glotframeProjects?.labels||{};
document.querySelectorAll('#glotframe-main #legend>div').forEach(item=>{const key=item.textContent.trim().toLowerCase();if(glotframeLabels[key])item.textContent=glotframeLabels[key];});
document.querySelectorAll('#glotframe-main select').forEach(select=>{if(!/priority/.test(select.name+' '+select.id))return;Array.from(select.options).forEach(option=>{const key=option.textContent.trim().toLowerCase();if(glotframeLabels[key])option.textContent=glotframeLabels[key];});});
