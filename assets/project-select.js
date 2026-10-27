/* Enhance only GlotPress's parent-project field; the native select remains the fallback. */
(function () {
 'use strict';
 const select = document.getElementById('project[parent_project_id]');
 if (!select || typeof window.TomSelect !== 'function' || select.tomselect) return;
 const strings = window.glotframeProjectSelect;
 const ancestors = [];
 const options = Array.from(select.options, option => {
  const match = option.textContent.match(/^(-*)(.*)$/s);
  const depth = match[1].length;
  const name = match[2].trim();
  ancestors.length = depth;
  const path = ancestors.filter(Boolean).concat(name).join(' / ');
  if (option.value !== '') ancestors[depth] = name;
  return { value: option.value, text: option.value === '' ? option.textContent : path, disabled: option.disabled };
 });
 const label = Array.from(document.querySelectorAll('label')).find(item => item.htmlFor === select.id);
 let instance;
 try {
  instance = new window.TomSelect(select, {
   options, create: false, allowEmptyOption: true, maxItems: 1, maxOptions: null,
   searchField: ['text'], sortField: [{ field: '$order' }], selectOnTab: false,
   render: {
    no_results: function (data, escape) { return '<div class="no-results">' + escape(strings.empty) + '</div>'; }
   },
   onInitialize: function () {
    this.control_input.placeholder = strings.search;
   }
  });
  // Tom Select links the original label; keep an explicit accessible name as well.
  if (label) instance.control_input.setAttribute('aria-label', label.textContent.trim());
 } catch (error) {
  // Failed enhancement must never block the native project form.
  if (select.tomselect) select.tomselect.destroy();
 }
})();
