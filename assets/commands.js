(function () {
 'use strict';
 if (!window.wp?.commands?.store || !window.wp?.data?.dispatch || !window.glotframeCommands) return;
 const actions = wp.data.dispatch(wp.commands.store);
 if (typeof actions.registerCommand !== 'function') return;
 glotframeCommands.entries.forEach(command => {
  actions.registerCommand({
   name: command.name,
   label: command.label,
   callback: ({close} = {}) => { close?.(); window.location.assign(command.url); }
  });
 });
}());
