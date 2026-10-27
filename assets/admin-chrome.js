(function () {
 'use strict';
 if(window.glotframeChromeReady)return;
 window.glotframeChromeReady=true;
 /** Initialize shared footer behavior once and preserve native documentation links. @returns {void} */
 const start=()=>{
  const footer=document.getElementById('gf-native-footer'),content=document.getElementById('posts-filter')?.closest('.wrap');
  if(footer&&content){content.append(footer);footer.classList.remove('gf-native-footer-fallback');}
  const dialog=document.getElementById('gf-changelog-dialog'),native=window.HTMLDialogElement?.prototype;
  const nativeClose=native?.close;
  const help=document.getElementById('gf-help');
  if(help&&location.hash==='#gf-help')help.open=true;
  let opener=null;
  document.addEventListener('click',
   /** Open the local history dialog only after a successful, visible native opening.
    * @param {MouseEvent} event Captured click on a shared footer link.
    * @returns {void} Keeps the original URL available on failure.
    */
   event=>{
   const link=event.target.closest?.('.gf-admin-footer a');if(!link)return;
   if(help&&new URL(link.href,location.href).hash==='#gf-help')help.open=true;
   if(!link.matches('[data-gf-dialog="gf-changelog-dialog"]'))return;
   if(!dialog||!native?.showModal||!native?.close){return;}
   try{
    dialog.hidden=false;native.showModal.call(dialog);
    const bounds=dialog.getBoundingClientRect(),style=getComputedStyle(dialog);
    if(!dialog.open||!bounds.width||!bounds.height||style.display==='none'||style.visibility==='hidden'||Number(style.opacity)===0)throw new Error('Dialog unavailable');
    opener=link;event.preventDefault();
   }catch(_){if(dialog.open&&nativeClose){try{nativeClose.call(dialog);}catch(_close){/* The real documentation URL remains the fallback. */}}dialog.hidden=true;}
  },true);
  if(dialog&&native?.close){
   dialog.addEventListener('close',/** Hide the closed dialog and restore its invoking link. @returns {void} */()=>{dialog.hidden=true;if(opener?.isConnected)opener.focus({preventScroll:true});});
   dialog.addEventListener('click',/** Close on the backdrop while preserving clicks inside the document. @param {MouseEvent} event Native dialog click. @returns {void} */event=>{if(event.target!==dialog)return;const r=dialog.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)native.close.call(dialog);});
  }
 };
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
}());
