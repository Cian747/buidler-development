(function () {
  const h = wp.element.createElement;
  const { useEffect, useState, useRef } = wp.element;
  const api = wp.apiFetch;
  api.use(api.createNonceMiddleware(IB_CONFIG.nonce));

  // Widget registry: each widget has a label and a default attribute schema.
  const WIDGETS = {
    container:{label:'Container',attrs:{padding:'var(--ib-space-lg)',gap:'var(--ib-space-md)',flexDirection:'column',alignItems:'stretch',justifyContent:'flex-start'}},
    heading:{label:'Heading',attrs:{text:'Your heading',tag:'h2'}},
    text:{label:'Text',attrs:{text:'Write your content here.'}},
    image:{label:'Image',attrs:{mediaId:0,alt:''}},
    button:{label:'Button',attrs:{text:'Learn more',url:'#',target:'_self',padding:'12px'}},
    spacer:{label:'Spacer',attrs:{height:'32px'}},
    divider:{label:'Divider',attrs:{}}
  };
  const uid=()=> 'ib-'+Math.random().toString(36).slice(2,10);
  const clone=x=>JSON.parse(JSON.stringify(x));
  const make=type=>({id:uid(),type,attrs:clone(WIDGETS[type].attrs),children:[]});
  function find(nodes,id){for(const n of nodes||[]){if(n.id===id)return n;const x=find(n.children,id);if(x)return x;}return null;}
  function update(nodes,id,fn){return (nodes||[]).map(n=>n.id===id?fn(n):Object.assign({},n,{children:update(n.children,id,fn)}));}
  function remove(nodes,id){return (nodes||[]).filter(n=>n.id!==id).map(n=>Object.assign({},n,{children:remove(n.children,id)}));}
  function insert(nodes,parentId,item){if(!parentId)return (nodes||[]).concat([item]);return (nodes||[]).map(n=>n.id===parentId?Object.assign({},n,{children:(n.children||[]).concat([item])}):Object.assign({},n,{children:insert(n.children,parentId,item)}));}
  function moveNode(nodes,id,targetId,asChild){const moving=find(nodes,id);if(!moving||id===targetId)return nodes;const stripped=remove(nodes,id);if(!targetId)return stripped.concat([moving]);if(asChild){return update(stripped,targetId,n=>Object.assign({},n,{children:(n.children||[]).concat([moving])}));}
    let placed=false;function after(list){const out=[];for(const n of list||[]){out.push(Object.assign({},n,{children:after(n.children)}));if(n.id===targetId){out.push(moving);placed=true;}}return out;}const result=after(stripped);return placed?result:result.concat([moving]);}
  function reorder(nodes,id,delta){let done=false;function walk(list){const a=(list||[]).slice();const i=a.findIndex(n=>n.id===id);if(i>=0){const j=i+delta;if(j>=0&&j<a.length){[a[i],a[j]]=[a[j],a[i]];}done=true;return a;}return a.map(n=>Object.assign({},n,{children:walk(n.children)}));}return walk(nodes);}
  function treeRows(nodes,selected,select,level){return (nodes||[]).flatMap(n=>[h('button',{key:n.id,className:'ib-tree-row '+(selected===n.id?'is-active':''),style:{paddingLeft:(10+level*16)+'px'},onClick:()=>select(n.id)},(n.type==='container'?'▣ ':'◇ ')+WIDGETS[n.type].label),...(n.type==='container'?treeRows(n.children,selected,select,level+1):[])]);}
  function Preview({node,selected,onSelect,onDropNode,onDragStart,device}){
    const base=node.attrs||{};const a=Object.assign({},base,(base.responsive&&base.responsive[device])||{});const style={color:a.color||undefined,backgroundColor:a.background||undefined,fontSize:a.fontSize||undefined,padding:a.padding||undefined,margin:a.margin||undefined,width:a.width||undefined,maxWidth:a.maxWidth||undefined,minHeight:node.type==='spacer'?a.height:(a.minHeight||undefined),gap:a.gap||undefined,flexDirection:a.flexDirection||undefined,alignItems:a.alignItems||undefined,justifyContent:a.justifyContent||undefined,borderRadius:a.borderRadius||undefined,borderWidth:a.borderWidth||undefined,borderStyle:a.borderWidth?'solid':undefined,borderColor:a.borderColor||undefined,boxShadow:a.boxShadow==='none'? 'none':a.boxShadow||undefined};
    const props={className:'ib-preview-node ib-node-'+node.type+' '+(selected===node.id?'is-selected':''),style,onClick:e=>{e.stopPropagation();onSelect(node.id);},key:node.id,draggable:true,onDragStart:e=>onDragStart(e,node.id),onDragOver:e=>{if(node.type==='container'){e.preventDefault();e.currentTarget.classList.add('ib-drop-target');}},onDragLeave:e=>e.currentTarget.classList.remove('ib-drop-target'),onDrop:e=>{e.preventDefault();e.stopPropagation();e.currentTarget.classList.remove('ib-drop-target');onDropNode(e,node.id,node.type==='container');}};
    if(node.type==='container')return h('div',props,(node.children||[]).length?node.children.map(n=>h(Preview,{node:n,selected,onSelect,onDropNode,onDragStart,device,key:n.id})):h('div',{className:'ib-empty-container'},'Drop elements here or use Add inside in settings'));
    if(node.type==='heading')return h('div',props,h(a.tag||'h2',null,a.text||''));
    if(node.type==='text')return h('div',props,h('p',null,a.text||''));
    if(node.type==='button')return h('div',props,h('span',{className:'ib-button',style:{background:a.background||'var(--ib-primary, #17365D)',color:a.color||'#fff',padding:a.padding||'12px',borderRadius:'var(--ib-border-radius, 8px)'}},a.text||'Button'));
    if(node.type==='image')return h('div',props,a.mediaUrl?h('img',{src:a.mediaUrl,alt:a.alt||''}):h('div',{className:'ib-image-placeholder'},'Choose an image in settings'));
    if(node.type==='spacer')return h('div',Object.assign({},props,{style:Object.assign({},style,{height:a.height||'32px'})}),'Spacer');
    return h('div',props,h('hr'));
  }
  function App(){
    const [doc,setDoc]=useState({version:1,nodes:[],settings:{}}),[selected,setSelected]=useState(null),[status,setStatus]=useState('Loading…'),[device,setDevice]=useState('desktop'),[history,setHistory]=useState([]),[future,setFuture]=useState([]),[globalStyles,setGlobalStyles]=useState(IB_CONFIG.globalDefaults),[globalConfigured,setGlobalConfigured]=useState(false),[globalStatus,setGlobalStatus]=useState('Loading site design system…'),[globalDirty,setGlobalDirty]=useState(false);
    const dragId=useRef(null), clipboard=useRef(null), docRef=useRef(doc);docRef.current=doc;
    useEffect(()=>{
      Promise.all([
        api({url:IB_CONFIG.restUrl,method:'GET'}),
        api({url:IB_CONFIG.globalStylesUrl,method:'GET'})
      ]).then(([loadedDoc,response])=>{
        setDoc(loadedDoc);docRef.current=loadedDoc;setStatus('Ready');
        let settings=response.settings||IB_CONFIG.globalDefaults;
        // Preserve v0.4 document-level design values as a migration suggestion, not as a silent write.
        const legacy=(loadedDoc.settings||{}).globalStyles||{};
        if(!response.configured && (legacy.colors||legacy.fontFamily)){
          settings=clone(settings);
          settings.colors=Object.assign({},settings.colors||{},legacy.colors||{});
          if(legacy.fontFamily){settings.typography.bodyFontFamily=legacy.fontFamily;settings.typography.headingFontFamily=legacy.fontFamily;}
          setGlobalStatus('Legacy page styles loaded. Save below to make them site-wide.');
          setGlobalDirty(true);
        } else { setGlobalStatus(response.configured?'Site design system loaded':'Using default design system.'); }
        setGlobalStyles(settings);setGlobalConfigured(!!response.configured);
      }).catch(error=>{
        setStatus('Could not load document or design system');
        setGlobalStatus(error&&error.message?error.message:'Could not load site design system');
      });
    },[]);
    const current=find(doc.nodes,selected);
    function commit(next){setHistory(hs=>hs.concat([clone(docRef.current)]).slice(-50));setFuture([]);setDoc(next);docRef.current=next;}
    function add(type,parent){const item=make(type);commit(Object.assign({},docRef.current,{nodes:insert(docRef.current.nodes,parent||null,item)}));setSelected(item.id);}
    function attr(key,value){if(!selected)return;const responsiveKeys=['fontSize','padding','margin','width','height','gap','maxWidth','minHeight','borderRadius','color','background'];const next=Object.assign({},docRef.current,{nodes:update(docRef.current.nodes,selected,n=>{const attrs=Object.assign({},n.attrs);if(device!=='desktop'&&responsiveKeys.includes(key)){attrs.responsive=Object.assign({},attrs.responsive||{});attrs.responsive[device]=Object.assign({},attrs.responsive[device]||{},{[key]:value});}else{attrs[key]=value;}return Object.assign({},n,{attrs});})});commit(next);}
    function removeSelected(){if(!selected)return;commit(Object.assign({},docRef.current,{nodes:remove(docRef.current.nodes,selected)}));setSelected(null);}
    function duplicate(){if(!current)return;const copy=clone(current);function regenerate(n){n.id=uid();n.children=(n.children||[]).map(c=>{regenerate(c);return c;});return n;}regenerate(copy);const next=moveNode(docRef.current.nodes,selected,selected,false);const parentInsert=(list,target,item)=>{const arr=(list||[]).slice();for(let i=0;i<arr.length;i++){if(arr[i].id===target){arr.splice(i+1,0,item);return {nodes:arr,done:true};}if(arr[i].children&&arr[i].children.length){const result=parentInsert(arr[i].children,target,item);if(result.done){arr[i]=Object.assign({},arr[i],{children:result.nodes});return {nodes:arr,done:true};}}}return {nodes:arr,done:false};};const result=parentInsert(docRef.current.nodes,selected,copy);commit(Object.assign({},docRef.current,{nodes:result.done?result.nodes:docRef.current.nodes.concat([copy])}));setSelected(copy.id);}
    function copyNode(){if(current)clipboard.current=clone(current);}
    function pasteNode(){if(!clipboard.current)return;const copy=clone(clipboard.current);function regen(n){n.id=uid();n.children=(n.children||[]).map(c=>{regen(c);return c;});}regen(copy);commit(Object.assign({},docRef.current,{nodes:insert(docRef.current.nodes,current&&current.type==='container'?current.id:null,copy)}));setSelected(copy.id);}
    function undo(){if(!history.length)return;const previous=history[history.length-1];setFuture(f=>[clone(docRef.current),...f].slice(0,50));setHistory(h=>h.slice(0,-1));setDoc(previous);docRef.current=previous;}
    function redo(){if(!future.length)return;const next=future[0];setHistory(h=>h.concat([clone(docRef.current)]));setFuture(f=>f.slice(1));setDoc(next);docRef.current=next;}
    function save(){setStatus('Saving…');api({url:IB_CONFIG.restUrl,method:'POST',data:{document:docRef.current}}).then(()=>setStatus('Saved')).catch(()=>setStatus('Save failed — changes remain in this editor'));}
    function imagePicker(){const frame=wp.media({title:'Select image',button:{text:'Use image'},multiple:false,library:{type:'image'}});frame.on('select',()=>{const a=frame.state().get('selection').first().toJSON();attr('mediaId',a.id);attr('mediaUrl',a.url);});frame.open();}
    function field(label,key,type,options){const value=((device!=='desktop'&&current.attrs.responsive&&current.attrs.responsive[device]&&current.attrs.responsive[device][key]!==undefined)?current.attrs.responsive[device][key]:(current.attrs||{})[key])||'';if(type==='image')return h('div',{className:'ib-field'},h('label',null,label),h('button',{className:'button',onClick:imagePicker},'Choose image'),current.attrs.mediaUrl?h('small',null,current.attrs.mediaUrl):null);if(type==='select')return h('div',{className:'ib-field'},h('label',null,label),h('select',{value,onChange:e=>attr(key,e.target.value)},options.map(v=>h('option',{key:v,value:v},v))));return h('div',{className:'ib-field'},h('label',null,label),h('input',{type:type||'text',value,onChange:e=>attr(key,e.target.value)}));}
    function readPath(object,path){return path.split('.').reduce((value,key)=>value&&value[key]!==undefined?value[key]:undefined,object);}
    function writePath(object,path,value){const next=clone(object||{});const keys=path.split('.');let target=next;keys.slice(0,-1).forEach(key=>{if(!target[key]||typeof target[key]!=='object')target[key]={};target=target[key];});target[keys[keys.length-1]]=value;return next;}
    function updateGlobal(path,value){setGlobalStyles(current=>writePath(current,path,value));setGlobalDirty(true);setGlobalStatus('Unsaved site-wide changes');}
    function saveGlobalStyles(){setGlobalStatus('Saving site design system…');api({url:IB_CONFIG.globalStylesUrl,method:'POST',data:{settings:globalStyles}}).then(response=>{setGlobalStyles(response.settings||globalStyles);setGlobalConfigured(true);setGlobalDirty(false);setGlobalStatus('Site design system saved');}).catch(error=>{setGlobalStatus(error&&error.message?error.message:'Could not save site design system. Administrator access is required.');});}
    function clearLegacyPageStyles(){const settings=Object.assign({},docRef.current.settings||{});delete settings.globalStyles;commit(Object.assign({},docRef.current,{settings}));setGlobalStatus('Page-level v0.4 design settings removed in the editor. Click Save page to persist this change.');}
    function globalInput(label,path,type,options,help){const value=readPath(globalStyles,path)||'';let control;if(type==='select'){control=h('select',{value,onChange:e=>updateGlobal(path,e.target.value)},options.map(item=>h('option',{key:item.value,value:item.value},item.label)));}else{control=h('input',{type:type||'text',value,onChange:e=>updateGlobal(path,e.target.value),step:type==='number'?'0.1':undefined,min:type==='number'?'0.9':undefined,max:type==='number'?'2.5':undefined});}return h('div',{className:'ib-field'},h('label',null,label),control,help?h('small',{className:'ib-help'},help):null);}
    function globalPanel(){
      const c=globalStyles.colors||{},t=globalStyles.typography||{},sp=globalStyles.spacing||{},b=globalStyles.borders||{},sh=globalStyles.shadows||{};
      if(!IB_CONFIG.canManageGlobalStyles){return h('div',null,h('strong',null,'Site design system'),h('p',{className:'description'},'You can view the shared design system, but only a site administrator can change it.'),h('p',null,'Primary: '+c.primary),h('p',null,'Secondary: '+c.secondary),h('p',null,'Body font: '+t.bodyFontFamily),h('p',{role:'status'},globalStatus));}
      const fontOptions=['system-ui, sans-serif','Arial, sans-serif','Georgia, serif','Tahoma, sans-serif','Verdana, sans-serif','Trebuchet MS, sans-serif','Times New Roman, serif','Courier New, monospace'].map(v=>({value:v,label:v}));
      const weightOptions=['300','400','500','600','700','800'].map(v=>({value:v,label:v}));
      const shadowOptions=[{value:'none',label:'None'},{value:'0 1px 3px rgba(0,0,0,0.12)',label:'Small'},{value:'0 4px 12px rgba(0,0,0,0.12)',label:'Medium'},{value:'0 10px 30px rgba(0,0,0,0.15)',label:'Large'}];
      return h('div',{className:'ib-global-panel'},
        h('strong',null,'Site-wide design system'),
        h('p',{className:'description'},'These settings are shared by all Independent Builder pages on this WordPress site. Save changes to publish them site-wide.'),
        (docRef.current.settings&&docRef.current.settings.globalStyles&&Object.keys(docRef.current.settings.globalStyles).length)?h('div',{className:'ib-legacy-notice'},h('strong',null,'Legacy page-level styles detected'),h('p',null,'This v0.4 page has its own older design tokens. They are preserved so the page keeps its appearance, and they can override shared site-wide tokens. Removing them will let this page inherit the shared design system. Element-level styles such as a manually set text colour or font size may still override global values.'),h('button',{className:'button',onClick:clearLegacyPageStyles},'Remove legacy page-level styles')):null,
        h('h3',null,'Brand colours'),
        globalInput('Primary colour','colors.primary','color'),globalInput('Secondary colour','colors.secondary','color'),globalInput('Accent colour','colors.accent','color'),globalInput('Text colour','colors.text','color'),globalInput('Background colour','colors.background','color'),
        h('h3',null,'Typography'),
        globalInput('Body font','typography.bodyFontFamily','select',fontOptions),globalInput('Heading font','typography.headingFontFamily','select',fontOptions),globalInput('Body font size','typography.bodyFontSize'),globalInput('Body font weight','typography.bodyFontWeight','select',weightOptions),globalInput('Body line height','typography.bodyLineHeight','number'),globalInput('Heading font weight','typography.headingFontWeight','select',weightOptions),globalInput('Heading line height','typography.headingLineHeight','number'),
        globalInput('H1 size','typography.h1FontSize'),globalInput('H2 size','typography.h2FontSize'),globalInput('H3 size','typography.h3FontSize'),globalInput('H4 size','typography.h4FontSize'),
        h('h3',null,'Spacing scale'),
        globalInput('Extra small','spacing.xs'),globalInput('Small','spacing.sm'),globalInput('Medium','spacing.md'),globalInput('Large','spacing.lg'),globalInput('Extra large','spacing.xl'),globalInput('XXL','spacing.xxl'),
        h('p',{className:'description'},'Layout controls can use shared spacing variables, for example var(--ib-space-md) or var(--ib-space-lg).'),
        h('h3',null,'Borders and shadows'),
        globalInput('Border radius','borders.radius'),globalInput('Border width','borders.width'),globalInput('Border colour','borders.color','color'),globalInput('Small shadow','shadows.sm','select',shadowOptions),globalInput('Medium shadow','shadows.md','select',shadowOptions),globalInput('Large shadow','shadows.lg','select',shadowOptions),
        h('p',{className:'ib-global-status',role:'status'},globalStatus),
        h('button',{className:'button button-primary',onClick:saveGlobalStyles,disabled:!globalDirty},globalDirty?'Save site design system':'Site design system saved'),
        h('p',{className:'description'},globalConfigured?'Changes apply to all builder-managed pages after saving and refreshing the frontend.':'Saving creates the shared design system for this site.')
      );
    }
    function fields(){if(!current){return globalPanel();}const t=current.type;let controls=[];
      if(t==='heading')controls=[field('Text','text'),field('Heading level','tag','select',['h1','h2','h3','h4']),field('Font size','fontSize'),field('Text colour','color','color')];
      if(t==='text')controls=[field('Text','text'),field('Font size','fontSize'),field('Text colour','color','color')];
      if(t==='button')controls=[field('Label','text'),field('URL','url'),field('Background','background','color'),field('Text colour','color','color'),field('Padding','padding')];
      if(t==='container')controls=[field('Padding','padding'),field('Gap','gap'),field('Background','background','color'),field('Width','width'),field('Max width','maxWidth'),field('Direction','flexDirection','select',['column','row']),field('Align items','alignItems','select',['stretch','flex-start','center','flex-end']),field('Justify content','justifyContent','select',['flex-start','center','flex-end','space-between','space-around']),field('Border radius','borderRadius'),field('Border width','borderWidth'),field('Border colour','borderColor','color'),field('Shadow','boxShadow','select',['none','0 2px 8px rgba(0,0,0,.12)','0 4px 16px rgba(0,0,0,.16)'])];
      if(t==='image')controls=[field('Image','media','image'),field('Alt text','alt')];if(t==='spacer')controls=[field('Height','height')];
      return h('div',null,h('strong',null,WIDGETS[t].label),controls,h('div',{className:'ib-actions'},h('button',{className:'button',onClick:()=>commit(Object.assign({},docRef.current,{nodes:reorder(docRef.current.nodes,selected,-1)}))},'Move up'),h('button',{className:'button',onClick:()=>commit(Object.assign({},docRef.current,{nodes:reorder(docRef.current.nodes,selected,1)}))},'Move down'),h('button',{className:'button',onClick:duplicate},'Duplicate'),h('button',{className:'button',onClick:copyNode},'Copy'),h('button',{className:'button',onClick:pasteNode,disabled:!clipboard.current},'Paste'),h('button',{className:'button button-link-delete',onClick:removeSelected},'Delete')),
      t==='container'?h('div',{className:'ib-field'},h('label',null,'Add inside this container'),Object.keys(WIDGETS).filter(k=>k!=='container').map(k=>h('button',{key:k,className:'button ib-small-add',onClick:()=>add(k,current.id)},'+ '+WIDGETS[k].label))):null);
    }
    function dragStart(e,id){dragId.current=id;e.dataTransfer.setData('text/ib-node',id);e.dataTransfer.effectAllowed='move';}
    function dropNode(e,targetId,asChild){const id=e.dataTransfer.getData('text/ib-node')||dragId.current;if(!id||id===targetId)return;commit(Object.assign({},docRef.current,{nodes:moveNode(docRef.current.nodes,id,targetId,asChild)}));setSelected(id);dragId.current=null;}
    return h('div',{className:'ib-app'},
      h('header',{className:'ib-topbar'},h('strong',null,'Independent Builder'),h('span',null,IB_CONFIG.title),h('div',{className:'ib-top-actions'},h('button',{className:'button',onClick:undo,disabled:!history.length},'Undo'),h('button',{className:'button',onClick:redo,disabled:!future.length},'Redo'),h('span',{role:'status'},status),h('button',{className:'button button-primary',onClick:save},'Save page'),h('a',{className:'button',href:IB_CONFIG.adminUrl+'post.php?post='+IB_CONFIG.postId+'&action=edit'},'Exit editor'))),
      h('div',{className:'ib-workspace'},
        h('aside',{className:'ib-sidebar ib-elements'},h('h2',null,'Elements'),Object.keys(WIDGETS).map(k=>h('button',{key:k,className:'ib-element-button',onClick:()=>add(k,null)},'+ '+WIDGETS[k].label)),h('h2',{className:'ib-navigator-title'},'Navigator'),h('div',{className:'ib-navigator'},treeRows(doc.nodes,selected,setSelected,0)),h('p',{className:'description'},'Drag elements in the canvas to reorder or place inside containers.')),
        h('main',{className:'ib-stage'},h('div',{className:'ib-devicebar'},['desktop','tablet','mobile'].map(v=>h('button',{key:v,className:device===v?'is-active':'',onClick:()=>setDevice(v)},v))),h('div',{className:'ib-canvas-wrap ib-'+device,style:{'--ib-primary':(globalStyles.colors||{}).primary||'#17365D','--ib-secondary':(globalStyles.colors||{}).secondary||'#2874A6','--ib-accent':(globalStyles.colors||{}).accent||'#E3A72F','--ib-text':(globalStyles.colors||{}).text||'#333333','--ib-background':(globalStyles.colors||{}).background||'#FFFFFF','--ib-font-family':(globalStyles.typography||{}).bodyFontFamily||'system-ui, sans-serif','--ib-font-body':(globalStyles.typography||{}).bodyFontFamily||'system-ui, sans-serif','--ib-font-heading':(globalStyles.typography||{}).headingFontFamily||'system-ui, sans-serif','--ib-font-size-body':(globalStyles.typography||{}).bodyFontSize||'16px','--ib-font-size-h1':(globalStyles.typography||{}).h1FontSize||'40px','--ib-font-size-h2':(globalStyles.typography||{}).h2FontSize||'32px','--ib-font-size-h3':(globalStyles.typography||{}).h3FontSize||'26px','--ib-font-size-h4':(globalStyles.typography||{}).h4FontSize||'21px','--ib-space-xs':(globalStyles.spacing||{}).xs||'4px','--ib-space-sm':(globalStyles.spacing||{}).sm||'8px','--ib-space-md':(globalStyles.spacing||{}).md||'16px','--ib-space-lg':(globalStyles.spacing||{}).lg||'24px','--ib-space-xl':(globalStyles.spacing||{}).xl||'40px','--ib-space-xxl':(globalStyles.spacing||{}).xxl||'64px','--ib-font-weight-body':(globalStyles.typography||{}).bodyFontWeight||'400','--ib-font-weight-heading':(globalStyles.typography||{}).headingFontWeight||'700','--ib-line-height-body':(globalStyles.typography||{}).bodyLineHeight||'1.6','--ib-line-height-heading':(globalStyles.typography||{}).headingLineHeight||'1.2','--ib-border-radius':(globalStyles.borders||{}).radius||'8px','--ib-border-width':(globalStyles.borders||{}).width||'1px','--ib-border-color':(globalStyles.borders||{}).color||'#D0D5DD','--ib-shadow-sm':(globalStyles.shadows||{}).sm||'none','--ib-shadow-md':(globalStyles.shadows||{}).md||'none','--ib-shadow-lg':(globalStyles.shadows||{}).lg||'none'},onClick:()=>setSelected(null),onDragOver:e=>e.preventDefault(),onDrop:e=>{e.preventDefault();const id=e.dataTransfer.getData('text/ib-node')||dragId.current;if(id){commit(Object.assign({},docRef.current,{nodes:moveNode(docRef.current.nodes,id,null,false)}));setSelected(id);}}},h('div',{className:'ib-canvas'},doc.nodes.length?doc.nodes.map(n=>h(Preview,{node:n,selected,onSelect:setSelected,onDropNode:dropNode,onDragStart:dragStart,device,key:n.id})):h('div',{className:'ib-canvas-empty'},'Start building — add an element from the left panel.')))),
        h('aside',{className:'ib-sidebar ib-settings'},h('h2',null,'Settings'),fields())
      ));
  }
  document.addEventListener('DOMContentLoaded',()=>{const root=document.getElementById('ib-editor-root');if(root){const app=h(App);if(wp.element.createRoot)wp.element.createRoot(root).render(app);else wp.element.render(app,root);}});
})();