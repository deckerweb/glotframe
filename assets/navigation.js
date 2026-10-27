/* External submenu target enhancement; the destination stays usable without JavaScript. */
(function () {
 const menu=document.querySelector('#toplevel_page_glotframe .wp-submenu');
 const link=menu?.lastElementChild?.querySelector('a');
 if(link && !link.href.includes('wp-admin/')) {link.target='_blank';link.rel='noopener noreferrer';link.parentElement.classList.add('glotframe-launch');}
}());
