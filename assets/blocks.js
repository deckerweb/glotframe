/* Four dynamic blocks, with native server previews and no saved frontend HTML. */
(function (wp, config) {
 'use strict';
 if(!config)return;
 const el=wp.element.createElement, Fragment=wp.element.Fragment;
 const {InspectorControls,useBlockProps}=wp.blockEditor;
 const {PanelBody,SelectControl,ComboboxControl,ToggleControl,TextControl,RangeControl,Placeholder}=wp.components;
 const SSR=wp.serverSideRender.default||wp.serverSideRender;
 const labels=config.labels;
 const attributes={projectId:{type:'integer',default:0},source:{type:'string',default:'roots',enum:['roots','children']},view:{type:'string',default:'list',enum:['list','grid']},sort:{type:'string',default:'asc',enum:['asc','desc']},activeOnly:{type:'boolean',default:false},description:{type:'boolean',default:true},locale:{type:'string',default:''},limit:{type:'integer',default:5}};
 ['project-list','current-project','project-actions','progress','statistics'].forEach(kind=>{
  wp.blocks.registerBlockType('glotframe/'+kind,{
   apiVersion:3,title:labels[kind],category:'glotframe',icon:'translation',attributes,supports:{html:false},
   edit:function(props){
    const a=props.attributes,set=props.setAttributes,fields=[];
    fields.push(el(ComboboxControl,{key:'project',label:labels.project,value:String(a.projectId),options:[{value:'0',label:labels.automatic},...config.projects.map(p=>({value:String(p.value),label:p.label}))],onChange:value=>set({projectId:parseInt(value,10)||0})}));
    if(kind==='project-list'){
     [['source','source',[['roots',labels.roots],['children',labels.children]]],['view','view',[['list',labels.list],['grid',labels.grid]]],['sort','sort',[['asc',labels.asc],['desc',labels.desc]]]].forEach(([key,label,choices])=>fields.push(el(SelectControl,{key,label:labels[label],value:a[key],options:choices.map(([value,label])=>({value,label})),onChange:value=>set({[key]:value})})));
     fields.push(el(ToggleControl,{key:'active',label:labels.active,checked:a.activeOnly,onChange:value=>set({activeOnly:value})}));
    }
    if(kind==='current-project'||kind==='project-list')fields.push(el(ToggleControl,{key:'description',label:labels.description,checked:a.description,onChange:value=>set({description:value})}));
    if(kind==='progress'){fields.push(el(TextControl,{key:'locale',label:labels.locale,value:a.locale,onChange:value=>set({locale:value})}));fields.push(el(RangeControl,{key:'limit',label:labels.limit,value:a.limit,min:1,max:20,onChange:value=>set({limit:value})}));}
    const needsProject=!a.projectId && (kind==='current-project'||kind==='progress'||kind==='statistics'||(kind==='project-list'&&a.source==='children'));
    return el(Fragment,null,el(InspectorControls,null,el(PanelBody,{title:labels.settings},fields)),el('div',useBlockProps(),needsProject?el(Placeholder,{label:labels[kind]},labels.preview):el(SSR,{block:'glotframe/'+kind,attributes:a,httpMethod:'POST'})));
   },save:function(){return null;}
  });
 });
})(window.wp,window.glotframeBlocks);
