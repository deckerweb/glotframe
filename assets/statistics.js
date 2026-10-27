(function(){
 'use strict';
 if(typeof window.TomSelect!=='function')return;
 document.querySelectorAll('[data-gf-project-filter]').forEach(select=>{if(select.tomselect)return;try{new TomSelect(select,{plugins:['remove_button'],create:false,maxItems:null,maxOptions:100,placeholder:window.glotframeStatistics?.all||'',hidePlaceholder:true});}catch(error){select.tomselect?.destroy();}});
})();
