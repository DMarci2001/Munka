(function(){let e=document.createElement(`link`).relList;if(e&&e.supports&&e.supports(`modulepreload`))return;for(let e of document.querySelectorAll(`link[rel="modulepreload"]`))n(e);new MutationObserver(e=>{for(let t of e)if(t.type===`childList`)for(let e of t.addedNodes)e.tagName===`LINK`&&e.rel===`modulepreload`&&n(e)}).observe(document,{childList:!0,subtree:!0});function t(e){let t={};return e.integrity&&(t.integrity=e.integrity),e.referrerPolicy&&(t.referrerPolicy=e.referrerPolicy),e.crossOrigin===`use-credentials`?t.credentials=`include`:e.crossOrigin===`anonymous`?t.credentials=`omit`:t.credentials=`same-origin`,t}function n(e){if(e.ep)return;e.ep=!0;let n=t(e);fetch(e.href,n)}})();var e=[],t=null;function n(t,n){let r=[],i=RegExp(`^`+t.replace(/:[^/]+/g,e=>(r.push(e.slice(1)),`([^/]+)`))+`$`);e.push({regex:i,keys:r,handler:n})}function r(e){t=e}function i(e){location.hash===`#`+e?o():location.hash=`#`+e}function a(){return location.hash.replace(/^#/,``)||`/`}function o(){let n=a();for(let t of e){let e=n.match(t.regex);if(e){let n={};t.keys.forEach((t,r)=>n[t]=decodeURIComponent(e[r+1])),t.handler(n);return}}t&&t()}function s(){window.addEventListener(`hashchange`,o),o()}var c=`/eszkoznyilvantartas/api/index.php`,l=class extends Error{};async function u(e,t,n){let r={method:e,credentials:`include`,headers:{}};n!==void 0&&(r.headers[`Content-Type`]=`application/json`,r.body=JSON.stringify(n));let i;try{i=await fetch(c+t,r)}catch{throw new l(`Hálózati hiba — a szerver nem érhető el.`)}let a=null;try{a=await i.json()}catch{}if(!a||typeof a.ok!=`boolean`)throw new l(`Váratlan szerverválasz (HTTP ${i.status}).`);if(!a.ok)throw new l(a.error||`Hiba történt (HTTP ${i.status}).`);return a.data}var d=e=>u(`GET`,e),f=(e,t,n)=>u(e,t,n),p={locations:[],departments:[],users:[],deviceTypes:[],attributeDefinitions:[],devices:[],pending:[],reservations:[],myPendingTransfers:[],currentUser:null},m={},h=new Set,g=new Set;async function _(){let e=await d(`/bootstrap`);p.locations=e.locations||[],p.departments=e.departments||[],p.users=e.users||[],p.deviceTypes=e.deviceTypes||[],p.attributeDefinitions=e.attributeDefinitions||[],p.devices=e.devices||[],p.pending=e.pending||[],p.reservations=e.reservations||[],p.myPendingTransfers=e.myPendingTransfers||[],p.currentUser=e.currentUser||null,m={},Le()}async function v(){let[e,t,n,r]=await Promise.all([d(`/devices`),d(`/pending`),d(`/reservations`),d(`/transfers/mine`)]);p.devices=e||[],p.pending=t||[],p.reservations=n||[],p.myPendingTransfers=r||[],m={},Le()}var y=()=>p.users,b=e=>p.users.find(t=>t.id===e)||null,x=()=>p.departments,ee=e=>p.departments.find(t=>t.id===e)||null,te=e=>ee(e)?.type===`raktár`,S=()=>p.locations,ne=e=>p.locations.find(t=>t.id===e)||null,re=()=>p.deviceTypes,ie=e=>p.deviceTypes.find(t=>t.id===e)||null,C=()=>p.devices,w=e=>p.devices.find(t=>t.device_id===e)||null,ae=e=>p.devices.find(t=>t.asset_tag?.toLowerCase()===String(e).trim().toLowerCase())||null,oe=e=>p.attributeDefinitions.filter(t=>t.device_type_id===e||t.device_type_id===null).sort((e,t)=>e.sort_order-t.sort_order),T=()=>p.currentUser,E=()=>p.currentUser?.auth||`user`,se={user:1,storekeeper:2,it_admin:3},D=(e,t)=>(se[e]||1)>=(se[t]||1);function ce(e){let t=w(e);return t?{holder:t.holder_id??null,location:t.location_id??null,department:t.department_id??null,since:t.since??null}:{holder:null,location:null,department:null,since:null}}var le=()=>p.pending,ue=()=>p.myPendingTransfers,de=e=>m[e]||[],fe=e=>Object.prototype.hasOwnProperty.call(m,e);async function pe(e){if(!(fe(e)||h.has(e))){h.add(e);try{m[e]=await d(`/devices/${e}/history`)||[]}catch{m[e]=[]}finally{h.delete(e),Le()}}}async function me(e){await f(`POST`,`/devices/move`,e),await v()}async function he(e,t,n=null,r=null,i=null,a=null){let o=await f(`POST`,`/devices/batch-check-out`,{device_ids:e,to_user_id:t,to_locations_id:n,to_departments_id:r,expected_return_date:i,notes:a});return await v(),o}async function ge(e,t,n=null){let r=await f(`POST`,`/devices/batch-transfer`,{device_ids:e,to_user_id:t,notes:n});return await v(),r}async function _e(e,t,n=null,r=null,i=null){let a=await f(`POST`,`/devices/batch-check-in`,{device_ids:e,to_locations_id:t,to_departments_id:n,condition_at_event:r,notes:i});return await v(),a}async function ve(e){await f(`POST`,`/checkins/${e}/confirm`),await v()}async function ye(e,t){await f(`POST`,`/checkins/${e}/reject`,{reason:t}),await v()}async function be(e){await f(`POST`,`/transfers/${e}/confirm`),await v()}async function xe(e,t){await f(`POST`,`/transfers/${e}/reject`,{reason:t}),await v()}async function Se(e,t){await f(`POST`,`/transfers/${e}/resolve`,{accept_rejection:t}),await v()}async function Ce(e,t=null){await f(`POST`,`/devices/${e}/reserve`,{notes:t}),await v()}async function we(e){await f(`POST`,`/devices/${e}/cancel-reservation`),await v()}async function Te(e,t=null,n=null,r=null){await f(`POST`,`/devices/${e}/send-to-repair`,{to_locations_id:t,to_departments_id:n,notes:r}),await v()}async function Ee(e,t,n,r=null){await f(`POST`,`/devices/${e}/return-from-repair`,{to_locations_id:t,to_departments_id:n,notes:r}),await v()}async function De(e,t=null){await f(`POST`,`/devices/${e}/mark-lost`,{notes:t}),await v()}async function Oe(e,t,n,r=null){await f(`POST`,`/devices/${e}/mark-found`,{to_locations_id:t,to_departments_id:n,notes:r}),await v()}async function ke(e){let t=await f(`POST`,`/devices`,e);return await v(),t}async function Ae(e,t){await f(`PATCH`,`/devices/${e}`,t),await v()}async function je(e,t){await f(`POST`,`/devices/${e}/retire`,{reason:t}),await v()}async function Me({address:e}){let t=await f(`POST`,`/locations`,{address:e});return await _(),t?.id}async function Ne({locations_id:e,name:t,type:n}){let r=await f(`POST`,`/departments`,{locations_id:e,name:t,type:n});return await _(),r?.id}async function Pe({type:e,description:t}){let n=await f(`POST`,`/device-types`,{type:e,description:t});return await _(),n?.id}async function Fe(e){let t=await f(`POST`,`/attribute-definitions`,e);return await _(),t?.id}function Ie(e){return g.add(e),()=>g.delete(e)}function Le(){for(let e of g)e()}var Re={Kiadva:{label:`Kiadva`,cls:`status-deployed`},Kivehető:{label:`Kivehető`,cls:`status-ready`},Lefoglalva:{label:`Lefoglalva`,cls:`status-reserved`},"Visszavétel folyamatban":{label:`Visszavétel folyamatban`,cls:`status-pending`},"Átadás folyamatban":{label:`Átadás folyamatban`,cls:`status-pending`},"Szerviz alatt":{label:`Szerviz alatt`,cls:`status-repair`},Elveszett:{label:`Elveszett`,cls:`status-lost`},Selejtezve:{label:`Selejtezve`,cls:`status-retired`}},ze=e=>Re[e]?.label||e||`—`,Be=e=>Re[e]?.cls||`status-default`;function O(e){return`<span class="status-badge ${Be(e)}">${ze(e)}</span>`}var Ve={check_out:`Kivétel`,check_in:`Leadás`,transfer:`Átadás`,stock_transfer:`Raktármozgatás`,send_to_repair:`Szervizbe küldés`,return_from_repair:`Szervizből visszahelyezés`,mark_lost:`Elveszettnek jelölés`,mark_found:`Megtalálva`},He=e=>Ve[e]||e,Ue={pending:`Függőben`,confirmed:`Megerősítve`,rejected:`Elutasítva`},We=e=>Ue[e]||e;function k(e,t){let n=t?ee(t):null,r=e?ne(e):null;return n&&r?`${n.name} · ${r.address}`:n?n.name:r?r.address:`—`}function Ge(e){if(!e)return`—`;let t=b(e);return t?t.full_name:`—`}function Ke(e){if(!e)return`—`;let t=e instanceof Date?e:new Date(e);return isNaN(t)?`—`:t.toLocaleDateString(`hu-HU`,{year:`numeric`,month:`2-digit`,day:`2-digit`})}function A(e){if(!e)return`—`;let t=e instanceof Date?e:new Date(e);return isNaN(t)?`—`:t.toLocaleString(`hu-HU`,{year:`numeric`,month:`2-digit`,day:`2-digit`,hour:`2-digit`,minute:`2-digit`})}function qe(e){if(!e)return`—`;let t=e instanceof Date?e:new Date(e);if(isNaN(t))return`—`;let n=t-Date.now(),r=Math.round(n/36e5);return r<=0?`lejárt`:r<24?`${r} óra múlva`:`${Math.round(r/24)} nap múlva`}function Je(e,t){return t==null||t===``?`—`:e.data_type===`boolean`?t?`Igen`:`Nem`:e.data_type===`date`?Ke(t):String(t)}function Ye(e){if(!e)return null;let t=new Date(e);if(isNaN(t))return null;let n=Math.round((t-Date.now())/864e5);return n<0?`overdue`:n<=30?`soon`:`ok`}function j(e){return e==null?``:String(e).replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`).replace(/"/g,`&quot;`).replace(/'/g,`&#39;`)}var M=(e,t=``)=>`<svg class="ico-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ${t}>${e}</svg>`,N={dashboard:M(`<rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/>`),inventory:M(`<path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/>`),my:M(`<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 12 0v1"/>`),pending:M(`<path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/>`),register:M(`<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/>`),search:M(`<circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/>`),check:M(`<path d="M20 6L9 17l-5-5"/>`),x:M(`<path d="M18 6L6 18M6 6l12 12"/>`),arrowRight:M(`<path d="M5 12h14M13 6l6 6-6 6"/>`),qr:M(`<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3M21 14v7M17 21h4M14 21h0"/>`),back:M(`<path d="M19 12H5M11 18l-6-6 6-6"/>`),bookmark:M(`<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>`),edit:M(`<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>`),repair:M(`<path d="M14.7 6.3a4 4 0 0 0-5 5L3 18l3 3 6.7-6.7a4 4 0 0 0 5-5l-2.8 2.8-2.1-2.1z"/>`),warning:M(`<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h0"/>`),building:M(`<rect x="4" y="2" width="16" height="20" rx="1"/><path d="M9 22v-4h6v4M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01"/>`),printer:M(`<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>`)},P;function F(e,t=`default`){P||(P=document.createElement(`div`),P.className=`toast-stack`,document.body.appendChild(P));let n=document.createElement(`div`);n.className=`toast-c ${t}`,n.innerHTML=`<span>${t===`success`?N.check:t===`error`?N.warning:``}</span><span>${e}</span>`,P.appendChild(n),setTimeout(()=>{n.style.transition=`opacity .25s, transform .25s`,n.style.opacity=`0`,n.style.transform=`translateX(20px)`,setTimeout(()=>n.remove(),260)},3200)}function Xe(){let e=window.frameElement;e&&(e.dataset.eszkozOrigStyle===void 0&&(e.dataset.eszkozOrigStyle=e.getAttribute(`style`)||``),e.style.cssText=`position:fixed;inset:0;width:100vw;height:100vh;z-index:99999;border:0;background:#fff;`)}function Ze(){let e=window.frameElement;!e||e.dataset.eszkozOrigStyle===void 0||(e.setAttribute(`style`,e.dataset.eszkozOrigStyle),delete e.dataset.eszkozOrigStyle)}function I({title:e,bodyHTML:t,confirmText:n=`Mentés`,confirmClass:r=`btn-primary`,onConfirm:i,onMount:a,wide:o=!1,closeOnBackdrop:s=!0}){Qe(),Xe();let c=document.createElement(`div`);c.className=`modal-backdrop-c`,c.innerHTML=`
    <div class="modal-c" style="${o?`max-width:680px`:``}">
      <div class="m-head">${e}<button class="close" data-close>&times;</button></div>
      <div class="m-body">${t}</div>
      <div class="m-foot">
        <button class="btn btn-outline" data-close>Mégse</button>
        ${i?`<button class="btn ${r}" data-confirm>${n}</button>`:``}
      </div>
    </div>`,document.body.appendChild(c);let l=c.querySelector(`.modal-c`),u=()=>{c.remove(),Ze()};c.querySelectorAll(`[data-close]`).forEach(e=>e.addEventListener(`click`,u)),s&&c.addEventListener(`mousedown`,e=>{e.target===c&&u()}),document.addEventListener(`keydown`,function e(t){t.key===`Escape`&&(u(),document.removeEventListener(`keydown`,e))});let d=c.querySelector(`[data-confirm]`);return d&&i&&d.addEventListener(`click`,async()=>{if(!d.disabled){d.disabled=!0;try{await i(l)===!1?d.disabled=!1:u()}catch(e){F(e.message||`Hiba történt`,`error`),d.disabled=!1}}}),a&&a(l),{close:u,root:l}}function Qe(){document.querySelectorAll(`.modal-backdrop-c`).forEach(e=>e.remove()),Ze()}function L(e){let t=ie(e.device_type_id),n=e.reservation||null,r=e.pending||null,i=e.calibration_due??e.attrs?.calibration_due??null;return{dev:e,type:t,typeName:t?.type||`—`,status:e.status,holderId:e.holder_id??null,holder:e.holder_id?b(e.holder_id):null,locationId:e.location_id??null,departmentId:e.department_id??null,since:e.since??null,reservation:n,reservedBy:n?b(n.reserved_by):null,pending:r,calibrationDue:i,calibrationFlag:Ye(i),lastModified:e.last_modified?String(e.last_modified).slice(0,10):null,lastCheckout:e.last_checkout_at?{event_timestamp:e.last_checkout_at}:null,lastReserved:n?{event_timestamp:n.reserved_at}:null,isFree:e.is_free??(e.status===`Kivehető`&&(e.department_id!==null||e.location_id!==null)),isLost:e.is_lost??e.status===`Elveszett`,inRepair:e.in_repair??e.status===`Szerviz alatt`}}var $e=0;function R(e){e.querySelectorAll(`select.form-select`).forEach(tt)}function et(e){e?._sselSync?.()}function tt(e){if(e._sselMounted)return;e._sselMounted=!0;let t=`ssel-`+ ++$e;e.style.display=`none`,e.setAttribute(`tabindex`,`-1`),e.setAttribute(`aria-hidden`,`true`);let n=document.createElement(`div`);n.className=`ssel`,n.innerHTML=`
    <input type="text" class="form-control ssel-input" id="${t}" autocomplete="off" spellcheck="false" />
    <span class="ssel-caret">▾</span>`,e.insertAdjacentElement(`afterend`,n);let r=n.querySelector(`.ssel-input`),i=document.createElement(`div`);i.className=`ssel-menu`,i.hidden=!0,document.body.appendChild(i);let a=[],o=-1,s=e=>e?e.text:``;function c(){let t=Array.from(e.options),n=t.filter(e=>e.value===``),r=t.filter(e=>e.value!==``);return r.sort((e,t)=>e.text.localeCompare(t.text,`hu`,{sensitivity:`base`,numeric:!0})),[...n,...r]}function l(){r.value=s(e.options[e.selectedIndex]),r.disabled=e.disabled}e._sselSync=l;function u(){let e=r.getBoundingClientRect();i.style.left=e.left+window.scrollX+`px`,i.style.top=e.bottom+window.scrollY+2+`px`,i.style.width=e.width+`px`}function d(t){let n=t.trim().toLocaleLowerCase(`hu`),r=c();a=n?r.filter(e=>e.text.toLocaleLowerCase(`hu`).includes(n)):r,o=a.indexOf(e.options[e.selectedIndex]),a.length?i.innerHTML=a.map((e,t)=>`<div class="ssel-item${t===o?` active`:``}" data-idx="${t}">${j(e.text)}</div>`).join(``):i.innerHTML=`<div class="ssel-empty">Nincs találat</div>`}function f(){Array.from(i.children).forEach((e,t)=>e.classList.toggle(`active`,t===o));let e=i.children[o];e&&e.scrollIntoView({block:`nearest`})}function p(t){if(!t)return;let n=Array.prototype.indexOf.call(e.options,t);n!==e.selectedIndex&&(e.selectedIndex=n,e.dispatchEvent(new Event(`input`,{bubbles:!0})),e.dispatchEvent(new Event(`change`,{bubbles:!0}))),_()}function m(e){n.contains(e.target)||i.contains(e.target)||_()}function h(e){e.target===i||e.target.nodeType===1&&i.contains(e.target)||_()}function g(){e.disabled||(u(),d(``),i.hidden=!1,n.classList.add(`open`),document.addEventListener(`mousedown`,m,!0),window.addEventListener(`scroll`,h,!0),window.addEventListener(`resize`,h))}function _(){i.hidden=!0,n.classList.remove(`open`),o=-1,document.removeEventListener(`mousedown`,m,!0),window.removeEventListener(`scroll`,h,!0),window.removeEventListener(`resize`,h),l()}r.addEventListener(`focus`,()=>{r.select(),g()}),r.addEventListener(`click`,()=>{i.hidden&&g()}),r.addEventListener(`input`,()=>{i.hidden&&g(),d(r.value)}),r.addEventListener(`keydown`,e=>{if(i.hidden){(e.key===`ArrowDown`||e.key===`ArrowUp`)&&(e.preventDefault(),g());return}e.key===`ArrowDown`?(e.preventDefault(),a.length&&(o=(o+1)%a.length,f())):e.key===`ArrowUp`?(e.preventDefault(),a.length&&(o=(o-1+a.length)%a.length,f())):e.key===`Enter`?(e.preventDefault(),o>=0&&p(a[o])):e.key===`Escape`&&(e.preventDefault(),_())}),i.addEventListener(`mousedown`,e=>e.preventDefault()),i.addEventListener(`click`,e=>{let t=e.target.closest(`.ssel-item[data-idx]`);t&&p(a[Number(t.dataset.idx)])}),new MutationObserver(()=>{l(),i.hidden||d(``)}).observe(e,{childList:!0}),l()}var nt=.55,rt=new WeakMap;function z(e){let t=e.querySelector(`table`);if(!t)return;t.style.transform=`none`,e.style.height=`auto`;let n=t.scrollWidth,r=t.scrollHeight,i=e.clientWidth;if(!n||!i)return;let a=Math.min(1,i/n);if(a<nt){t.style.transform=`none`,t.style.width=``,e.style.height=`auto`,e.style.overflowX=`auto`;return}e.style.overflowX=`hidden`,t.style.transformOrigin=`top left`,t.style.transform=`scale(${a})`,t.style.width=n+`px`,e.style.height=r*a+`px`}function it(e){if(rt.has(e))return;let t=new ResizeObserver(()=>z(e));t.observe(e),window.addEventListener(`resize`,()=>z(e)),rt.set(e,t)}function at(){let e=new Set,t=new Set;function n(){t.forEach(t=>t(e))}return{isSelected:t=>e.has(t),toggle(t){e.has(t)?e.delete(t):e.add(t),n()},clear(){e.clear(),n()},all:()=>[...e],size:()=>e.size,subscribe(e){return t.add(e),()=>t.delete(e)}}}function ot(e,t,{label:n,finalizeText:r=`Véglegesítés`,onFinalize:i,extraHTML:a=()=>``}){function o(){let o=t.all();if(!o.length){e.innerHTML=``,e.classList.remove(`bulk-action-bar-visible`);return}e.classList.add(`bulk-action-bar-visible`),e.innerHTML=`
      <div class="bulk-action-bar">
        <div class="bulk-action-bar-label">${j(n)} — ${o.length} kiválasztva</div>
        <div class="bulk-action-bar-chips">
          ${o.map(e=>`<span class="chip">${j(w(e)?.asset_tag||`#`+e)}<button type="button" data-remove="${e}">&times;</button></span>`).join(``)}
        </div>
        ${a()}
        <div class="bulk-action-bar-buttons">
          <button type="button" class="btn btn-outline btn-sm" data-cancel>Mégse</button>
          <button type="button" class="btn btn-primary btn-sm" data-finalize>${j(r)}</button>
        </div>
      </div>`,e.querySelectorAll(`[data-remove]`).forEach(e=>e.addEventListener(`click`,()=>t.toggle(Number(e.dataset.remove)))),e.querySelector(`[data-cancel]`)?.addEventListener(`click`,()=>t.clear()),e.querySelector(`[data-finalize]`)?.addEventListener(`click`,()=>i(t.all(),e))}return t.subscribe(o),o(),o}function st(e,t,n=()=>!0){e.querySelectorAll(`table`).forEach(e=>{let r=e.querySelector(`thead tr`);if(r&&!r.querySelector(`.bulk-th`)){let e=document.createElement(`th`);e.className=`bulk-th`,e.style.width=`32px`,r.insertBefore(e,r.firstChild)}e.querySelectorAll(`tbody tr[data-dev]`).forEach(e=>{let r=Number(e.dataset.dev);if(e.querySelector(`.bulk-td`))return;let i=document.createElement(`td`);i.className=`bulk-td`,n(r)&&(i.innerHTML=`<input type="checkbox" ${t.isSelected(r)?`checked`:``} />`,i.addEventListener(`click`,e=>e.stopPropagation()),i.querySelector(`input`).addEventListener(`change`,()=>t.toggle(r))),e.insertBefore(i,e.firstChild)})})}function ct(e,t){let n=e.filter(e=>e.ok).length,r=e.filter(e=>!e.ok);if(!r.length){t(`${n}/${e.length} eszköz sikeresen feldolgozva.`,`success`);return}let i=r.map(e=>`${w(e.device_id)?.asset_tag||`#`+e.device_id}: ${e.error}`).join(` · `);t(`${n}/${e.length} sikeres. Hiba: ${i}`,`error`)}var lt=`modulepreload`,ut=function(e,t){return new URL(e,t).href},dt={},ft=function(e,t,n){let r=Promise.resolve();if(t&&t.length>0){let e=document.getElementsByTagName(`link`),i=document.querySelector(`meta[property=csp-nonce]`),a=i?.nonce||i?.getAttribute(`nonce`);function o(e){return Promise.all(e.map(e=>Promise.resolve(e).then(e=>({status:`fulfilled`,value:e}),e=>({status:`rejected`,reason:e}))))}r=o(t.map(t=>{if(t=ut(t,n),t in dt)return;dt[t]=!0;let r=t.endsWith(`.css`),i=r?`[rel="stylesheet"]`:``;if(n)for(let n=e.length-1;n>=0;n--){let i=e[n];if(i.href===t&&(!r||i.rel===`stylesheet`))return}else if(document.querySelector(`link[href="${t}"]${i}`))return;let o=document.createElement(`link`);if(o.rel=r?`stylesheet`:lt,r||(o.as=`script`),o.crossOrigin=``,o.href=t,a&&o.setAttribute(`nonce`,a),document.head.appendChild(o),r)return new Promise((e,n)=>{o.addEventListener(`load`,e),o.addEventListener(`error`,()=>n(Error(`Unable to preload CSS for ${t}`)))})}))}function i(e){let t=new Event(`vite:preloadError`,{cancelable:!0});if(t.payload=e,window.dispatchEvent(t),!t.defaultPrevented)throw e}return r.then(t=>{for(let e of t||[])e.status===`rejected`&&i(e.reason);return e().catch(i)})},B={q:``,type:``,status:``,dept:``,loc:``,holder:``},V=at(),H=!1,pt=[`Kivehető`,`Kiadva`,`Lefoglalva`,`Visszavétel folyamatban`,`Átadás folyamatban`,`Szerviz alatt`,`Elveszett`,`Selejtezve`],U=null,mt=1;function ht(e){U===e?mt*=-1:(U=e,mt=1)}function W(e,t){return e==` `?`<th data-col="${e}" ></th>`:`<th data-col="${e}" style="cursor:pointer;user-select:none">${t} ${U===e?mt===1?`↑`:`↓`:`<span style="opacity:.99">↕</span>`}</th>`}function gt(e,t){switch(t){case`lastModified`:return e.lastModified||``;case`assetTag`:return e.dev.asset_tag||``;case`typeName`:return(e.typeName||``)+` `+(e.dev.model||``);case`status`:return e.status||``;case`holder`:return e.holder?e.holder.full_name:``;case`location`:return String(e.locationId||``)+String(e.departmentId||``);default:return``}}function _t(e){let t=D(E(),`storekeeper`),n=t||!!T()?.can_check_out,r=new Set(C().map(e=>e.holder_id).filter(e=>e!=null)),a=t?C().map(L).filter(e=>(e.calibrationFlag===`overdue`||e.calibrationFlag===`soon`)&&e.status!==`Selejtezve`).sort((e,t)=>new Date(e.calibrationDue)-new Date(t.calibrationDue)).slice(0,6):[];e.innerHTML=`
    <div class="content">
      <div class="toolbar">
        <div class="search">
          <span class="ico">${N.search}</span>
          <input class="form-control" id="f-q" placeholder="Keresés: azonosító, modell, gyártó, sorozatszám…" value="${j(B.q)}" />
        </div>
        <div class="select-wrap" style="max-width:170px">
          <select class="form-select" id="f-type">
            <option value="">Minden típus</option>
            ${re().map(e=>`<option value="${e.id}" ${String(e.id)===B.type?`selected`:``}>${j(e.type)}</option>`).join(``)}
          </select>
        </div>
        <div class="select-wrap" style="max-width:180px">
          <select class="form-select" id="f-status">
            <option value="">Minden státusz</option>
            ${pt.map(e=>`<option value="${e}" ${e===B.status?`selected`:``}>${j(ze(e))}</option>`).join(``)}
          </select>
        </div>
        <div class="select-wrap" style="max-width:180px">
          <select class="form-select" id="f_loc">
            <option value="">Minden helyszín</option>
            ${S().map(e=>`<option value="${e.id}" ${String(e.id)===B.loc?`selected`:``}>${j(e.address)}</option>`).join(``)}
          </select>
        </div>
        <div class="select-wrap" style="max-width:180px">
          <select class="form-select" id="f-dept">
            <option value="">Minden helyiség</option>
            ${x().map(e=>`<option value="${e.id}" ${String(e.id)===B.dept?`selected`:``}>${j(e.name)}</option>`).join(``)}
          </select>
        </div>
        <div class="select-wrap" style="max-width:180px">
          <select class="form-select" id="f-holder">
            <option value="">Minden birtokos</option>
            ${y().filter(e=>r.has(e.id)).map(e=>`<option value="${e.id}" ${String(e.id)===B.holder?`selected`:``}>${j(e.full_name)}</option>`).join(``)}
          </select>
        </div>

        <button class="btn btn-reset-filters-custom" id="btn-reset-filters">Szűrők törlése</button>
        <button class="btn btn-outline" id="btn-scan">${N.qr} Beolvasás</button>
        ${n?`<button class="btn btn-outline" id="btn-bulk-toggle">${H?`Tömeges kivétel — kilépés`:`Tömeges kivétel`}</button>`:``}
        ${t?`<button class="btn btn-primary" id="btn-new-device">${N.register} Új eszköz bevitele</button>`:``}
      </div>
      <div id="bulk-bar" class="bulk-action-bar-slot"></div>
      ${t?`
        <div class="panel" style="margin-bottom:16px">
          <div class="panel-head">Felülvizsgálandó eszközök</div>
          <div class="panel-body" style="padding:0">
            ${a.length?`
            <table class="grid">
              <tbody>
                ${a.map(e=>`
                  <tr data-dev="${e.dev.device_id}" style="cursor:pointer">
                    <td><span class="tag-mono">${j(e.dev.model)}</span><div class="cell-sub">${j(e.typeName)}</div></td>
                    <td>${O(e.status)}</td>
                    <td style="text-align:right">
                      <span class="attr-flag ${e.calibrationFlag}">${e.calibrationFlag===`overdue`?`Lejárt`:`Hamarosan`}</span>
                      <div class="cell-sub">${j(e.calibrationDue)}</div>
                    </td>
                  </tr>`).join(``)}
              </tbody>
            </table>`:`<div class="empty" style="padding:32px"><div>Nincs közelgő kalibráció.</div></div>`}
          </div>
        </div>`:``}
      <div id="inv-table"></div>
    </div>`;let o=e.querySelector(`#f-q`);o.addEventListener(`input`,()=>{B.q=o.value,G(e)}),e.querySelector(`#f-type`).addEventListener(`change`,t=>{B.type=t.target.value,G(e)}),e.querySelector(`#f-status`).addEventListener(`change`,t=>{B.status=t.target.value,G(e)}),e.querySelector(`#f_loc`).addEventListener(`change`,t=>{B.loc=t.target.value,G(e)}),e.querySelector(`#f-dept`).addEventListener(`change`,t=>{B.dept=t.target.value,G(e)}),e.querySelector(`#f-holder`).addEventListener(`change`,t=>{B.holder=t.target.value,G(e)});let s=e.querySelector(`#btn-new-device`);s&&s.addEventListener(`click`,()=>i(`/register`));let c=e.querySelector(`#btn-scan`);c&&c.addEventListener(`click`,()=>i(`/scan`));let l=e.querySelector(`#btn-reset-filters`);l&&l.addEventListener(`click`,()=>{B.q=``,B.type=``,B.status=``,B.loc=``,B.dept=``,B.holder=``,o.value=``,[`#f-type`,`#f-status`,`#f_loc`,`#f-dept`,`#f-holder`].forEach(t=>{let n=e.querySelector(t);n.value=``,et(n)}),G(e)}),e.querySelectorAll(`.panel [data-dev]`).forEach(e=>e.addEventListener(`click`,()=>i(`/device/`+e.dataset.dev))),R(e);let u=e.querySelector(`#btn-bulk-toggle`);u&&u.addEventListener(`click`,()=>{H=!H,H||V.clear(),G(e)}),n&&ot(e.querySelector(`#bulk-bar`),V,{label:`Tömeges kivétel`,finalizeText:`Kivétel véglegesítése`,onFinalize:e=>vt(e,t)}),G(e)}function vt(e,t){let n=T();I({title:`Tömeges kivétel (${e.length} eszköz)`,closeOnBackdrop:!1,bodyHTML:`
      ${t?`
      <div class="field">
        <label class="form-label">Kinek</label>
        <select class="form-select" name="to_user">${y().filter(e=>e.id!==n.id).map(e=>`<option value="${e.id}">${j(e.full_name)}</option>`).join(``)}</select>
      </div>`:`<div class="alert-soft" style="margin-bottom:15px">Az eszközöket <strong>magadnak</strong> veszed ki: ${j(n.full_name)}.</div>`}
      <div class="field">
        <label class="form-label">Hová (osztály / felhasználási hely)</label>
        <select class="form-select" name="to_location">${S().map(e=>`<option value="${e.id}">${j(e.address)}</option>`).join(``)}</select>
        <select class="form-select" name="to_dept"></select>
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés (opcionális)</label>
        <input type="text" class="form-control" name="notes" />
      </div>`,confirmText:`Kivétel`,onMount:e=>{let t=e.querySelector(`[name=to_location]`),n=e.querySelector(`[name=to_dept]`),r=()=>{let e=x().filter(e=>e.locations_id===Number(t.value)&&e.type!==`raktár`);n.innerHTML=e.length?e.map(e=>`<option value="${e.id}">${j(e.name)}</option>`).join(``):`<option value="">— nincs részleg ezen a helyszínen —</option>`};t.addEventListener(`change`,r),r(),R(e)},onConfirm:async n=>{ct(await he(e,t?Number(n.querySelector(`[name=to_user]`).value):T().id,Number(n.querySelector(`[name=to_location]`)?.value),Number(n.querySelector(`[name=to_dept]`).value)||null,null,n.querySelector(`[name=notes]`).value.trim()||null),F),V.clear(),H=!1}})}function G(e){let t=e.querySelector(`#inv-table`),n=C().map(L);D(E(),`storekeeper`)||(n=n.filter(e=>e.status===`Kivehető`));let r=B.q.trim().toLowerCase();if(r&&(n=n.filter(e=>[e.dev.asset_tag,e.dev.model,e.dev.manufacturer,e.dev.serial_number,e.typeName,Ge(e.holderId),k(e.locationId,e.departmentId)].filter(Boolean).some(e=>e.toLowerCase().includes(r)))),B.type&&(n=n.filter(e=>String(e.dev.device_type_id)===B.type)),B.status&&(n=n.filter(e=>e.status===B.status)),B.loc&&(n=n.filter(e=>String(e.locationId)===B.loc)),B.dept&&(n=n.filter(e=>String(e.departmentId)===B.dept)),B.holder&&(n=n.filter(e=>String(e.holderId)===B.holder)),U&&(n=[...n].sort((e,t)=>mt*gt(e,U).localeCompare(gt(t,U),`hu`,{sensitivity:`base`}))),!n.length){t.innerHTML=`<div class="table-wrap"><div class="empty"><div class="big">${N.search}</div><div>Nincs a szűrőnek megfelelő eszköz.</div></div></div>`;return}let a=[];for(let e=0;e<n.length;e+=25)a.push(n.slice(e,e+25));let o=e=>`
    <div class="table-wrap">
      <table class="grid">
        <thead><tr>
          ${W(`lastModified`,`Utoljára módosítva`)}${W(`assetTag`,`Leltári azonosító`)}${W(`typeName`,`Típus / modell`)}${W(`status`,`Státusz`)}
          ${W(`holder`,`Birtokos`)}${W(`location`,`Hely`)}${W(` `,` `)}<th></th>
        </tr></thead>
        <tbody>${e.map(bt).join(``)}</tbody>
      </table>
    </div>`,s=a.map((e,t)=>`.inv-pager:has(#inv-p${t+1}:checked) .page-section[data-page="${t+1}"]{display:block}.inv-pager:has(#inv-p${t+1}:checked) label[for="inv-p${t+1}"]{background:var(--brand);color:#fff;border-color:var(--brand-dark)}`).join(``);t.innerHTML=`
    <div class="muted" style="font-size:.82rem;margin-bottom:10px">${n.length} eszköz</div>
    <div class="inv-pager pager-root">
      <style>${s}</style>
      ${a.map((e,t)=>`<input type="radio" name="inv-page" id="inv-p${t+1}" class="page-radio"${t===0?` checked`:``}>`).join(``)}
      ${a.map((e,t)=>`<div class="page-section" data-page="${t+1}">${o(e)}</div>`).join(``)}
      ${a.length>1?`<div class="pager-nav">${a.map((e,t)=>`<label for="inv-p${t+1}" class="pager-btn">${t+1}</label>`).join(``)}</div>`:``}
    </div>`,t.querySelectorAll(`tbody tr`).forEach(e=>e.addEventListener(`click`,()=>i(`/device/`+e.dataset.dev))),t.querySelectorAll(`th[data-col]`).forEach(t=>t.addEventListener(`click`,()=>{ht(t.dataset.col),G(e)})),t.querySelectorAll(`[data-act="qr-label"]`).forEach(e=>e.addEventListener(`click`,e=>{e.stopPropagation();let t=Number(e.currentTarget.closest(`[data-dev]`).dataset.dev);ft(()=>import(`./qrLabel-C_D-sAIe.js`).then(e=>e.printQrLabel(t)),[],import.meta.url)})),H&&t.querySelectorAll(`.table-wrap`).forEach(e=>st(e,V,e=>yt(e)===`Kivehető`)),t.querySelectorAll(`.table-wrap`).forEach(e=>{it(e),z(e)}),t.querySelectorAll(`.page-radio`).forEach(e=>e.addEventListener(`change`,()=>{let n=t.querySelector(`.page-section[data-page="${e.id.replace(`inv-p`,``)}"]`)?.querySelector(`.table-wrap`);n&&z(n),H&&n&&st(n,V,e=>yt(e)===`Kivehető`)}))}function yt(e){return C().find(t=>t.device_id===e)?.status}function bt(e){let t=e.holder?j(e.holder.full_name):`<span class="muted">— raktáron —</span>`,n=e.reservation?`<div class="cell-sub">Foglalta: ${j(e.reservedBy?.full_name||``)}</div>`:``;return`
    <tr data-dev="${e.dev.device_id}">
      <td><span class="tag-mono">${j(e.lastModified)||`—`}</span></td>
      <td><span class="tag-mono">${j(e.dev.asset_tag)}</span></td>
      <td>${j(e.typeName)}<div class="cell-sub">${j(e.dev.manufacturer)} ${j(e.dev.model)}</div></td>
      <td>${O(e.status)}${n}</td>
      <td>${t}</td>
      <td>${j(k(e.locationId,e.departmentId))}</td>
      <td style="text-align:right">${N.arrowRight}</td>
      <td><button class="btn btn-outline" data-act="qr-label">${N.printer} Nyomtatás</button></td>
    </tr>`}function K(e=null){return S().map(t=>`<option value="${t.id}" ${t.id===e?`selected`:``}>${j(t.address)}</option>`).join(``)}function xt(e=null,t=null){return y().filter(t=>t.id!==e).map(e=>`<option value="${e.id}" ${e.id===t?`selected`:``}>${j(e.full_name)}</option>`).join(``)}function St(e=`Jó`){return`<select class="form-select" name="condition">${[`Jó`,`Kopott`,`Hibás`,`Ismeretlen`].map(t=>`<option ${t===e?`selected`:``}>${t}</option>`).join(``)}</select>`}function q(e,t=()=>!1,{fallbackToFirst:n=!0}={}){let r=e.querySelector(`[name=to_location]`),i=e.querySelector(`[name=to_dept]`),a=()=>{let e=x().filter(e=>e.locations_id===Number(r.value)),a=e.find(t)||(n?e[0]:void 0);e.length?a?i.innerHTML=e.map(e=>`<option value="${e.id}" ${e.id===a.id?`selected`:``}>${j(e.name)}</option>`).join(``):i.innerHTML=[`<option value="">— válassz részleget —</option>`].concat(e.map(e=>`<option value="${e.id}">${j(e.name)}</option>`)).join(``):i.innerHTML=`<option value="">— nincs részleg ezen a helyszínen —</option>`};r.addEventListener(`change`,a),a(),R(e)}function Ct(e){let t=w(e),n=D(E(),`storekeeper`),r=T();I({title:`Eszköz kivétele · <span class="tag-mono" style="margin-left:8px">${j(t.asset_tag)}</span>`,closeOnBackdrop:!1,bodyHTML:`
      ${n?`
      <div class="field">
        <label class="form-label">Kinek</label>
        <select class="form-select" name="to_user">${xt(null,r.id)}</select>
        <div class="hint">Raktárosként más nevében is kiadhatsz eszközt.</div>
      </div>`:`
      <div class="alert-soft" style="margin-bottom:15px">Az eszközt <strong>magadnak</strong> veszed ki: ${j(r.full_name)}.</div>`}
      <div class="field">
        <label class="form-label">Hová (osztály / felhasználási hely)</label>
        <select class="form-select" name="to_location">${K()}</select>
        <select class="form-select" name="to_dept"></select>
      </div>
      <div class="field">
        <label class="form-label">Várható visszahozatal (opcionális)</label>
        <input type="date" class="form-control" name="ret" />
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés (opcionális)</label>
        <input type="text" class="form-control" name="notes" placeholder="pl. kihelyezés a Kardiológiára" />
      </div>`,confirmText:`Kivétel`,onMount:e=>q(e,e=>e.type!==`raktár`),onConfirm:async t=>{let i=n?Number(t.querySelector(`[name=to_user]`).value):r.id,a=Number(t.querySelector(`[name=to_location]`)?.value),o=Number(t.querySelector(`[name=to_dept]`).value)||null;if(te(o))return F(`Kivételkor használati helyet (nem raktárt) válassz — a raktár a készletet jelenti.`,`error`),!1;let s=t.querySelector(`[name=ret]`).value,c=t.querySelector(`[name=notes]`).value.trim()||null;await me({device_id:e,event_type:`check_out`,to_user_id:i,to_locations_id:a,to_departments_id:o,expected_return_date:s||null,notes:c}),F(`Eszköz kivéve.`,`success`)}})}function wt(e){let t=w(e),n=E()===`user`;I({title:`Eszköz leadása · <span class="tag-mono" style="margin-left:8px">${j(t.asset_tag)}</span>`,closeOnBackdrop:!1,bodyHTML:`
      <div class="field">
        <label class="form-label">Hová — helyszín</label>
        <select class="form-select" name="to_location">${K()}</select>
      </div>
      <div class="field">
        <label class="form-label">Hová — raktár / részleg (opcionális)</label>
        <select class="form-select" name="to_dept"></select>
      </div>
      <div class="field">
        <label class="form-label">Állapot</label>
        ${St(t.condition)}
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés (opcionális)</label>
        <input type="text" class="form-control" name="notes" placeholder="pl. minden tartozékkal" />
      </div>
      ${n?`<div class="alert-warn-soft">A leadás <strong>raktáros megerősítésére</strong> vár, mielőtt az eszköz ismét kiadhatóvá válik.</div>`:``}`,confirmText:`Leadás`,onMount:e=>q(e,e=>e.type===`raktár`),onConfirm:async t=>{let r=Number(t.querySelector(`[name=to_location]`)?.value),i=Number(t.querySelector(`[name=to_dept]`).value)||null,a=t.querySelector(`[name=condition]`).value;await me({device_id:e,event_type:`check_in`,to_locations_id:r,to_departments_id:i,condition_at_event:a,notes:t.querySelector(`[name=notes]`).value.trim()||null}),F(n?`Visszavétel folyamatban — raktáros megerősítésére vár.`:`Eszköz visszavéve.`,`success`)}})}function Tt(e){let t=w(e),n=ce(e);I({title:`Eszköz átadása · <span class="tag-mono" style="margin-left:8px">${j(t.asset_tag)}</span>`,bodyHTML:`
      <div class="field">
        <label class="form-label">Kinek</label>
        <select class="form-select" name="to_user">${xt(n.holder)}</select>
        <div class="hint">Az eszköz közvetlenül az új birtokoshoz kerül; a helye változatlan marad.</div>
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés (opcionális)</label>
        <input type="text" class="form-control" name="notes" />
      </div>`,confirmText:`Átadás`,onMount:e=>R(e),onConfirm:async t=>{let r=Number(t.querySelector(`[name=to_user]`).value),i=t.querySelector(`[name=notes]`).value.trim()||null,a=E()===`user`;await me({device_id:e,event_type:`transfer`,to_user_id:r,to_locations_id:n.location,to_departments_id:n.department,notes:i}),F(a?`Átadás folyamatban — az átvevő megerősítésére vár.`:`Eszköz átadva.`,`success`)}})}function Et(e){let t=w(e),n=ce(e);I({title:`Raktármozgatás · <span class="tag-mono" style="margin-left:8px">${j(t.asset_tag)}</span>`,bodyHTML:`
      <div class="field">
        <label class="form-label">Honnan</label>
        <input class="form-control" value="${j(k(n.location,n.department))}" disabled />
      </div>
      <div class="field">
        <label class="form-label">Hová — helyszín</label>
        <select class="form-select" name="to_location">${K(n.location)}</select>
      </div>
      <div class="field">
        <label class="form-label">Hová — részleg</label>
        <select class="form-select" name="to_dept"></select>
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés (opcionális)</label>
        <input type="text" class="form-control" name="notes" />
      </div>`,confirmText:`Mozgatás`,onMount:e=>q(e,e=>e.type===`raktár`),onConfirm:async t=>{await me({device_id:e,event_type:`stock_transfer`,to_locations_id:Number(t.querySelector(`[name=to_location]`)?.value),to_departments_id:Number(t.querySelector(`[name=to_dept]`).value)||null,notes:t.querySelector(`[name=notes]`).value.trim()||null}),F(`Készlet áthelyezve.`,`success`)}})}async function Dt(e){try{await Ce(e),F(`Eszköz lefoglalva (3 napig).`,`success`)}catch(e){F(e.message,`error`)}}async function Ot(e){try{await we(e),F(`Foglalás lemondva.`,`success`)}catch(e){F(e.message,`error`)}}async function kt(e){try{await ve(e),F(`Visszavétel megerősítve.`,`success`)}catch(e){F(e.message,`error`)}}function At(e){I({title:`Visszavétel elutasítása`,bodyHTML:`
      <p class="muted" style="margin-top:0">Az eszköz nincs fizikailag a megadott helyen? Az elutasítással a birtoklás a felhasználónál marad.</p>
      <div class="field">
        <label class="form-label">Indok (kötelező)</label>
        <input type="text" class="form-control" name="reason" placeholder="pl. nincs a raktárban" />
      </div>`,confirmText:`Elutasítás`,confirmClass:`btn-danger`,onConfirm:async t=>{let n=t.querySelector(`[name=reason]`).value.trim();if(!n)return F(`Adj meg indokot.`,`error`),!1;await ye(e,n),F(`Visszavétel elutasítva.`,`success`)}})}async function jt(e){try{await be(e),F(`Átadás megerősítve.`,`success`)}catch(e){F(e.message,`error`)}}function Mt(e){I({title:`Átadás elutasítása`,bodyHTML:`
      <p class="muted" style="margin-top:0">Nem vetted át fizikailag az eszközt? Az elutasítással a birtoklás a küldőnél marad — a raktáros dönthet felülbírálásról.</p>
      <div class="field">
        <label class="form-label">Indok (kötelező)</label>
        <input type="text" class="form-control" name="reason" placeholder="pl. nem kaptam meg az eszközt" />
      </div>`,confirmText:`Elutasítás`,confirmClass:`btn-danger`,onConfirm:async t=>{let n=t.querySelector(`[name=reason]`).value.trim();if(!n)return F(`Adj meg indokot.`,`error`),!1;await xe(e,n),F(`Átadás elutasítva.`,`success`)}})}async function Nt(e){try{await Se(e,!0),F(`Elutasítás elfogadva.`,`success`)}catch(e){F(e.message,`error`)}}function Pt(e){I({title:`Átadás felülbírálása`,bodyHTML:`
      <p class="muted" style="margin-top:0">Az átvevő elutasította az átadást, de raktárosként felülbírálhatod: az átadás ekkor mégis végbemegy, az átvevő jóváhagyása nélkül.</p>`,confirmText:`Felülbírálás — átadás végrehajtása`,confirmClass:`btn-danger`,onConfirm:async()=>{await Se(e,!1),F(`Átadás felülbírálva és végrehajtva.`,`success`)}})}function Ft(e){I({title:`Szervizbe küldés`,bodyHTML:`
      <div class="field">
        <label class="form-label">Hibaleírás</label>
        <input class="form-control" name="notes" placeholder="pl. nem kapcsol be" />
      </div>
      <div class="field">
        <label class="form-label">Hová — helyszín</label>
        <select class="form-select" name="to_location">${K()}</select>
      </div>
      <div class="field">
        <label class="form-label">Hová — részleg</label>
        <select class="form-select" name="to_dept"></select>
      </div>`,confirmText:`Szervizbe`,onMount:e=>q(e,e=>e.type===`műhely`,{fallbackToFirst:!1}),onConfirm:async t=>{await Te(e,Number(t.querySelector(`[name=to_location]`).value),Number(t.querySelector(`[name=to_dept]`).value)||null,t.querySelector(`[name=notes]`).value.trim()||null),F(`Szervizbe küldve.`,`success`)}})}function It(e){I({title:`Szervizelve`,bodyHTML:`
      <div class="field">
        <label class="form-label">Helyszín</label>
        <select class="form-select" name="to_location">${K()}</select>
      </div>
      <div class="field">
        <label class="form-label">Részleg</label>
        <select class="form-select" name="to_dept"></select>
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés</label>
        <input class="form-control" name="notes" placeholder="pl. javítva" />
      </div>`,confirmText:`Visszahelyezés`,onMount:e=>q(e),onConfirm:async t=>{await Ee(e,Number(t.querySelector(`[name=to_location]`)?.value),Number(t.querySelector(`[name=to_dept]`).value)||null,t.querySelector(`[name=notes]`).value.trim()||null),F(`Javítva visszahelyezve.`,`success`)}})}function Lt(e){I({title:`Elveszettnek jelölés`,bodyHTML:`<div class="field"><label class="form-label">Megjegyzés</label><input class="form-control" name="notes" placeholder="pl. nem található 2 hete" /></div>`,confirmText:`Elveszett`,confirmClass:`btn-danger`,onConfirm:async t=>{await De(e,t.querySelector(`[name=notes]`).value.trim()||null),F(`Elveszettnek jelölve.`,`success`)}})}function Rt(e){I({title:`Találtnak jelölés`,bodyHTML:`<div class="field">
        <label class="form-label">Helyszín</label>
        <select class="form-select" name="to_location">${K()}</select>
      </div>
      <div class="field">
        <label class="form-label">Részleg</label>
        <select class="form-select" name="to_dept"></select>
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés</label>
        <input class="form-control" name="notes" placeholder="pl. javítva" />
      </div>`,confirmText:`Visszahelyezés`,onMount:e=>q(e),onConfirm:async t=>{await Oe(e,Number(t.querySelector(`[name=to_location]`)?.value),Number(t.querySelector(`[name=to_dept]`).value)||null,t.querySelector(`[name=notes]`).value.trim()||null),F(`Találtnak jelölve.`,`success`)}})}function zt(e){I({title:`Eszköz selejtezése`,bodyHTML:`<p class="muted" style="margin-top:0">Lágy törlés: az előzmény megmarad, az eszköz „Selejtezve" státuszba kerül.</p>
      <div class="field"><label class="form-label">Indok</label><input class="form-control" name="reason" placeholder="pl. nem javítható" /></div>`,confirmText:`Selejtezés`,confirmClass:`btn-danger`,onConfirm:async t=>{await je(e,t.querySelector(`[name=reason]`).value.trim()||null),F(`Eszköz selejtezve.`,`success`)}})}function Bt(e,{id:t}){let n=w(Number(t));if(!n){e.innerHTML=`<div class="content"><div class="empty"><div class="big">${N.warning}</div><div>Eszköz nem található.</div><div style="margin-top:14px"><button class="btn btn-outline" id="back">${N.back} Vissza a listához</button></div></div></div>`,e.querySelector(`#back`).addEventListener(`click`,()=>i(`/inventory`));return}let r=L(n),a=T(),o=E(),s=D(o,`storekeeper`),c=oe(n.device_type_id);pe(n.device_id);let l=fe(n.device_id),u=de(n.device_id);e.innerHTML=`
    <div class="content">
      <button class="btn btn-ghost btn-sm" id="back" style="margin-bottom:14px">${N.back} Eszközök</button>

      <div class="detail-head">
        <div class="titleblock">
          <h2>${j(r.typeName)} — ${j(n.manufacturer)} ${j(n.model)}</h2>
          <div class="pill-info"><span class="tag-mono" style="font-size:.95rem">${j(n.asset_tag)}</span>${O(r.status)}</div>
        </div>
        <div class="actions" id="actions"></div>
      </div>

      ${Vt(r,s)}

      <div class="detail-grid">
        <div style="display:flex; flex-direction:column; gap:18px">
          <div class="panel">
            <div class="panel-head">${j(r.typeName)}</div>
            <div class="panel-body">
              <dl class="kv">
                <dt>Birtokos</dt><dd>${r.holder?j(r.holder.full_name):`<span class="muted">— raktáron —</span>`}</dd>
                <dt>Hely</dt><dd>${j(k(r.locationId,r.departmentId))}</dd>
                <dt>Státusz óta</dt><dd>${r.since?A(r.since):`—`}</dd>
                <dt>Állapot</dt><dd>${j(n.condition||`—`)}</dd>
                <dt>Sorozatszám</dt><dd>${j(n.serial_number||`—`)}</dd>
                <dt>Gyártó / modell</dt><dd>${j(n.manufacturer||`—`)} ${j(n.model||``)}</dd>
                ${n.notes?`<dt>Megjegyzés</dt><dd>${j(n.notes)}</dd>`:``}
                ${c.map(e=>Ht(e,n.attrs?.[e.attribute_key])).join(``)}
              </dl>
            </div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head">Birtoklási előzmény</div>
          <div class="panel-body">
            ${l?Ut(u):`<div class="muted">Előzmény betöltése…</div>`}
          </div>
        </div>
      </div>
    </div>`,e.querySelector(`#back`).addEventListener(`click`,()=>i(`/inventory`)),Gt(e.querySelector(`#actions`),r,o,s,a),e.querySelectorAll(`[data-confirm-ev]`).forEach(e=>e.addEventListener(`click`,()=>kt(Number(e.dataset.confirmEv)))),e.querySelectorAll(`[data-reject-ev]`).forEach(e=>e.addEventListener(`click`,()=>At(Number(e.dataset.rejectEv))))}function Vt(e,t){if(e.pending){let n=b(e.pending.actor_user_id);return`<div class="alert-warn-soft" style="margin-bottom:18px; display:flex; align-items:center; gap:14px; flex-wrap:wrap">
      <span>${N.pending}</span>
      <span><strong>Visszavétel megerősítésre vár.</strong> Leadta: ${j(n?.full_name||`—`)}, ide: ${j(k(e.pending.to_locations_id,e.pending.to_departments_id))}.</span>
      ${t?`<span style="margin-left:auto; display:flex; gap:8px">
        <button class="btn btn-success btn-sm" data-confirm-ev="${e.pending.event_id}">${N.check} Megerősít</button>
        <button class="btn btn-danger btn-sm" data-reject-ev="${e.pending.event_id}">${N.x} Elutasít</button>
      </span>`:``}
    </div>`}return e.reservation?`<div class="alert-soft" style="margin-bottom:18px; display:flex; align-items:center; gap:12px">
      <span>${N.bookmark}</span>
      <span><strong>Lefoglalva.</strong> Foglalta: ${j(e.reservedBy?.full_name||`—`)} · lejár ${qe(e.reservation.expires_at)}.</span>
    </div>`:``}function Ht(e,t){let n=``;if(e.attribute_key===`calibration_due`){let e=Ye(t);e===`overdue`?n=`<span class="attr-flag overdue">Lejárt</span>`:e===`soon`&&(n=`<span class="attr-flag soon">Hamarosan</span>`)}return`<dt>${j(e.label)}</dt><dd>${j(Je(e,t))}${n}</dd>`}function Ut(e){if(!e.length)return`<div class="muted">Nincs előzmény.</div>`;let t=[];for(let n=0;n<e.length;n+=8)t.push(e.slice(n,n+8));return t.length===1?`<div class="timeline">${e.map(Wt).join(``)}</div>`:`
    <div class="hist-pager pager-root">
      <style>${t.map((e,t)=>`.hist-pager:has(#hist-p${t+1}:checked) .page-section[data-page="${t+1}"]{display:block}.hist-pager:has(#hist-p${t+1}:checked) label[for="hist-p${t+1}"]{background:var(--brand);color:#fff;border-color:var(--brand-dark)}`).join(``)}</style>
      ${t.map((e,t)=>`<input type="radio" name="hist-page" id="hist-p${t+1}" class="page-radio"${t===0?` checked`:``}>`).join(``)}
      ${t.map((e,t)=>`<div class="page-section" data-page="${t+1}"><div class="timeline">${e.map(Wt).join(``)}</div></div>`).join(``)}
      <div class="pager-nav">${t.map((e,t)=>`<label for="hist-p${t+1}" class="pager-btn">${t+1}</label>`).join(``)}</div>
    </div>`}function Wt(e){let t=b(e.actor_user_id),n=e.confirmation_status===`pending`?`pending`:e.confirmation_status===`rejected`?`rejected`:``,r=e.event_type===`mark_lost`||e.event_type===`mark_found`,i=[];!r&&e.from_user_id?i.push(b(e.from_user_id)?.full_name):!r&&(e.from_departments_id||e.from_locations_id)&&i.push(k(e.from_locations_id,e.from_departments_id));let a=e.to_user_id?b(e.to_user_id)?.full_name:k(e.to_locations_id,e.to_departments_id),o=e.confirmation_status===`confirmed`?``:` · <span class="muted">${We(e.confirmation_status)}</span>`;return`
    <div class="tl-item">
      <span class="tl-dot ${n}"></span>
      <div class="tl-head">${j(He(e.event_type))}${o}</div>
      <div class="tl-meta">${i.length?j(i.join(`, `))+` → `:``}${j(a||`—`)}</div>
      <div class="tl-meta">Végrehajtó: ${j(t?.full_name||`—`)}${e.notes?` · `+j(e.notes):``}</div>
      <div class="tl-time">${A(e.event_timestamp)}</div>
    </div>`}function Gt(e,t,n,r,i){let a=t.dev,o=[],s=t.holderId===i.id,c=t.reservation&&t.reservation.reserved_by===i.id,l=t.reservation&&t.reservation.reserved_by!==i.id,u=a.status===`Selejtezve`,d=a.status===`Elveszett`,f=r||!!i.can_check_out;!u&&!d?(t.isFree&&!l&&(f&&o.push(`<button class="btn btn-primary" data-act="checkout">${N.arrowRight} Kivétel</button>`),t.reservation||o.push(`<button class="btn btn-outline" data-act="reserve">${N.bookmark} Foglalás</button>`)),c&&(f&&o.push(`<button class="btn btn-primary" data-act="checkout">${N.arrowRight} Kivétel</button>`),o.push(`<button class="btn btn-outline" data-act="cancel-resv">Foglalás lemondása</button>`)),l&&r&&(o.push(`<button class="btn btn-primary" data-act="checkout">${N.arrowRight} Kivétel (felülírás)</button>`),o.push(`<button class="btn btn-outline" data-act="cancel-resv">Foglalás lemondása</button>`)),s&&!t.pending&&(o.push(`<button class="btn btn-primary" data-act="checkin">${N.back} Leadás</button>`),o.push(`<button class="btn btn-outline" data-act="transfer">Átadás</button>`)),r&&t.holderId&&!s&&(o.push(`<button class="btn btn-outline" data-act="checkin">${N.back} Kényszerített visszavétel</button>`),o.push(`<button class="btn btn-outline" data-act="transfer">Átadás</button>`)),r&&t.isFree&&o.push(`<button class="btn btn-outline" data-act="stock">${N.building} Raktármozgatás</button>`),r&&t.inRepair&&(o.push(`<button class="btn btn-outline" data-act="return-from-repair">${N.back} Visszahelyezés</button>`),o.push(`<button class="btn btn-outline" data-act="edit">${N.edit} Szerkesztés</button>`)),r&&!t.inRepair&&(o.push(`<button class="btn btn-outline" data-act="repair">${N.repair} Szervizbe</button>`),o.push(`<button class="btn btn-outline" data-act="edit">${N.edit} Szerkesztés</button>`),o.push(`<button class="btn btn-danger" data-act="more">⋯</button>`))):r&&!u&&(t.isLost&&o.push(`<button class="btn btn-primary" data-act="mark-found">${N.back} Visszahelyezés</button>`),o.push(`<button class="btn btn-outline" data-act="edit">${N.edit} Szerkesztés</button>`)),o.push(`<button class="btn btn-ghost btn-sm" data-act="qr-label" style="margin-left:auto">${N.qr} QR Címke</button>`),e.innerHTML=o.join(``)||`<span class="muted" style="font-size:.85rem">Nincs elérhető művelet ehhez az állapothoz.</span>`;let p=a.device_id,m={checkout:()=>Ct(p),checkin:()=>wt(p),transfer:()=>Tt(p),reserve:()=>Dt(p),"cancel-resv":()=>Ot(p),stock:()=>Et(p),repair:()=>Ft(p),"return-from-repair":()=>It(p),"mark-found":()=>Rt(p),edit:()=>ft(()=>import(`./register_device-DfMFeG28.js`).then(e=>e.dlgEditDevice(p)),[],import.meta.url),more:()=>Kt(e,p),"qr-label":()=>ft(()=>import(`./qrLabel-C_D-sAIe.js`).then(e=>e.dlgQrLabel(p)),[],import.meta.url)};e.querySelectorAll(`[data-act]`).forEach(e=>e.addEventListener(`click`,t=>{t.stopPropagation(),m[e.dataset.act]?.()}))}function Kt(e,t){let n=document.createElement(`div`);n.style.cssText=`position:absolute; margin-top:6px; background:#fff; border:1px solid var(--line); border-radius:10px; box-shadow:var(--shadow); padding:6px; z-index:50; display:flex; flex-direction:column; gap:2px`,n.innerHTML=`
    <button class="btn btn-ghost btn-sm" data-m="lost" style="justify-content:flex-start">${N.warning} Elveszettnek jelöl</button>
    <button class="btn btn-ghost btn-sm" data-m="retire" style="justify-content:flex-start; color:#c0392b">Selejtezés</button>`,e.appendChild(n),n.querySelector(`[data-m=lost]`).addEventListener(`click`,()=>{n.remove(),Lt(t)}),n.querySelector(`[data-m=retire]`).addEventListener(`click`,()=>{n.remove(),zt(t)}),setTimeout(()=>document.addEventListener(`click`,function e(){n.remove(),document.removeEventListener(`click`,e)}),0)}var qt=at(),J=!1;function Jt(e){let t=T(),n=D(E(),`storekeeper`)||!!t.can_check_out,r=C().map(L),a=r.filter(e=>e.holderId===t.id),o=new Set(a.map(e=>e.dev.device_id)),s=r.filter(e=>e.reservation&&e.reservation.reserved_by===t.id),c=ue();e.innerHTML=`
    <div class="content">
      ${c.length?`
      <h3 class="section-title">Rám váró átvételek</h3>
      <div class="table-wrap" style="margin-bottom:26px">
        <table class="grid">
          <thead><tr><th>Típus / modell</th><th>Küldő</th><th>Átadás időpontja</th><th style="text-align:right"> </th></tr></thead>
          <tbody>
            ${c.map(e=>{let t=w(e.device_id),n=b(e.from_user_id);return`
              <tr data-dev="${e.device_id}">
                <td>${j(t?.asset_tag||``)}<div class="cell-sub">${j(t?.manufacturer||``)} ${j(t?.model||``)}</div></td>
                <td>${j(n?.full_name||`—`)}</td>
                <td>${A(e.event_timestamp)}</td>
                <td style="text-align:right">
                  <div class="row-actions" style="justify-content:flex-end">
                    <button class="btn btn-success btn-sm" data-transfer-confirm="${e.event_id}">${N.check} Elfogad</button>
                    <button class="btn btn-danger btn-sm" data-transfer-reject="${e.event_id}">${N.x} Elutasít</button>
                  </div>
                </td>
              </tr>`}).join(``)}
          </tbody>
        </table>
      </div>`:``}

      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
        <h3 class="section-title" style="margin:0">Eszközök a birtokomban</h3>
        ${a.length?`<button class="btn btn-outline btn-sm" id="btn-bulk-toggle">${J?`Tömeges átadás/leadás — kilépés`:`Tömeges átadás / leadás`}</button>`:``}
      </div>
      <div id="bulk-bar" class="bulk-action-bar-slot"></div>
      ${a.length?`
      <div class="table-wrap" style="margin-bottom:26px">
        <table class="grid">
          <thead><tr><th>Kivétel időpontja</th><th>Típus / modell</th><th>Hely</th><th>Státusz</th><th style="text-align:right"> </th></tr></thead>
          <tbody>
            ${a.map(e=>`
              <tr data-dev="${e.dev.device_id}">
                <td><span class="tag-mono">${j(e.lastCheckout?new Date(e.lastCheckout.event_timestamp).toISOString().slice(0,10):null)}</span></td>
                <td>${j(e.typeName)}<div class="cell-sub">${j(e.dev.manufacturer)} ${j(e.dev.model)}</div></td>
                <td>${j(k(e.locationId,e.departmentId))}</td>
                <td>${O(e.status)}</td>
                <td style="text-align:right">
                  <div class="row-actions" style="justify-content:flex-end">
                    ${e.status===`Kiadva`?`<button class="btn btn-primary btn-sm" data-act="checkin" data-id="${e.dev.device_id}">Leadás</button>
                    <button class="btn btn-outline btn-sm" data-act="transfer" data-id="${e.dev.device_id}">Átadás</button>`:``}
                  </div>
                </td>
              </tr>`).join(``)}
          </tbody>
        </table>
      </div>`:`<div class="table-wrap" style="margin-bottom:26px"><div class="empty"><div class="big">${N.my}</div><div>Jelenleg nincs nálad eszköz.</div><div style="margin-top:12px"><button class="btn btn-outline" id="browse">Eszközök böngészése</button></div></div></div>`}

      <h3 class="section-title">Foglalásaim</h3>
      ${s.length?`
      <div class="table-wrap">
        <table class="grid">
          <thead><tr><th>Foglalás időpontja</th><th>Típus / modell</th><th>Lejár</th><th style="text-align:right"> </th></tr></thead>
          <tbody>
            ${s.map(e=>`
              <tr data-dev="${e.dev.device_id}">
                <td><span class="tag-mono">${j(e.lastReserved?new Date(e.lastReserved.event_timestamp).toISOString().slice(0,10):null)}</span></td>
                <td>${j(e.typeName)}<div class="cell-sub">${j(e.dev.manufacturer)} ${j(e.dev.model)}</div></td>
                <td>${qe(e.reservation.expires_at)}</td>
                <td style="text-align:right">
                  <div class="row-actions" style="justify-content:flex-end">
                    ${n?`<button class="btn btn-primary btn-sm" data-act="checkout" data-id="${e.dev.device_id}">Kivétel</button>`:``}
                    <button class="btn btn-outline btn-sm" data-act="cancel" data-id="${e.dev.device_id}">Lemondás</button>
                  </div>
                </td>
              </tr>`).join(``)}
          </tbody>
        </table>
      </div>`:`<div class="muted" style="font-size:.9rem">Nincs aktív foglalásod.</div>`}
    </div>`,e.querySelectorAll(`tbody tr`).forEach(e=>e.addEventListener(`click`,t=>{t.target.closest(`button`)||i(`/device/`+e.dataset.dev)})),e.querySelectorAll(`[data-act]`).forEach(e=>e.addEventListener(`click`,t=>{t.stopPropagation();let n=Number(e.dataset.id);({checkin:wt,transfer:Tt,checkout:Ct,cancel:Ot})[e.dataset.act]?.(n)})),e.querySelectorAll(`[data-transfer-confirm]`).forEach(e=>e.addEventListener(`click`,t=>{t.stopPropagation(),jt(Number(e.dataset.transferConfirm))})),e.querySelectorAll(`[data-transfer-reject]`).forEach(e=>e.addEventListener(`click`,t=>{t.stopPropagation(),Mt(Number(e.dataset.transferReject))})),e.querySelector(`#browse`)?.addEventListener(`click`,()=>i(`/inventory`));let l=e.querySelector(`#btn-bulk-toggle`);l&&l.addEventListener(`click`,()=>{J=!J,J||qt.clear(),Jt(e)});let u=e.querySelector(`#bulk-bar`);u&&ot(u,qt,{label:`Tömeges átadás / leadás`,finalizeText:`Következő`,onFinalize:e=>Yt(e)}),J&&e.querySelectorAll(`.table-wrap`).forEach(e=>st(e,qt,e=>o.has(e))),e.querySelectorAll(`.table-wrap`).forEach(e=>{it(e),z(e)})}function Yt(e){I({title:`Tömeges átadás / leadás (${e.length} eszköz)`,closeOnBackdrop:!1,bodyHTML:`
      <div class="field">
        <label class="form-label">Művelet</label>
        <select class="form-select" name="mode">
          <option value="transfer">Átadás — másik felhasználónak</option>
          <option value="checkin">Leadás — raktárba / helyiségbe</option>
        </select>
      </div>
      <div data-mode-fields="transfer" class="field">
        <label class="form-label">Kinek</label>
        <select class="form-select" name="to_user"></select>
      </div>
      <div data-mode-fields="checkin" class="field" style="display:none">
        <label class="form-label">Hová — helyszín</label>
        <select class="form-select" name="to_location"></select>
        <label class="form-label" style="margin-top:8px">Hová — raktár / részleg</label>
        <select class="form-select" name="to_dept"></select>
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés (opcionális)</label>
        <input type="text" class="form-control" name="notes" />
      </div>`,confirmText:`Véglegesítés`,onMount:e=>{let t=e.querySelector(`[name=mode]`),n=e.querySelector(`[name=to_user]`),r=e.querySelector(`[name=to_location]`),i=e.querySelector(`[name=to_dept]`),a=T();n.innerHTML=y().filter(e=>e.id!==a.id).map(e=>`<option value="${e.id}">${j(e.full_name)}</option>`).join(``),r.innerHTML=S().map(e=>`<option value="${e.id}">${j(e.address)}</option>`).join(``);let o=()=>{let e=x().filter(e=>e.locations_id===Number(r.value)&&e.type===`raktár`);i.innerHTML=e.length?e.map(e=>`<option value="${e.id}">${j(e.name)}</option>`).join(``):`<option value="">— nincs raktár-részleg ezen a helyszínen —</option>`};r.addEventListener(`change`,o),o();let s=()=>{e.querySelectorAll(`[data-mode-fields]`).forEach(e=>{e.style.display=e.dataset.modeFields===t.value?``:`none`})};t.addEventListener(`change`,s),s(),R(e)},onConfirm:async t=>{let n=t.querySelector(`[name=mode]`).value,r=t.querySelector(`[name=notes]`).value.trim()||null,i;i=n===`transfer`?await ge(e,Number(t.querySelector(`[name=to_user]`).value),r):await _e(e,Number(t.querySelector(`[name=to_location]`).value),Number(t.querySelector(`[name=to_dept]`).value)||null,null,r),ct(i,F),qt.clear(),J=!1}})}var Y=null,X=1;function Xt(e){Y===e?X*=-1:(Y=e,X=1)}function Zt(e,t){return`<th data-col="${e}" style="cursor:pointer;user-select:none">${t} ${Y===e?X===1?`↑`:`↓`:`<span style="opacity:.99">↕</span>`}</th>`}function Qt(e,t){let n=w(e.device_id),r=ie(n?.device_type_id),i=b(e.actor_user_id);switch(t){case`typeName`:return(r?.type||``)+` `+(n?.model||``);case`submitter`:return i?.full_name||``;case`to_location`:return String(e.to_locations_id||``)+String(e.to_departments_id||``);case`event_timestamp`:return e.event_timestamp instanceof Date?e.event_timestamp.toISOString():String(e.event_timestamp||``);case`condition_at_event`:return e.condition_at_event||``;default:return``}}function $t(e){e.innerHTML=`
    <div class="content">
      <h3 class="section-title">Ellenőrzésre vár</h3>
      <div class="alert-soft" style="margin-bottom:16px">A felhasználói leadások itt várnak fizikai ellenőrzésre — erősítsd meg, ha az eszköz valóban a megadott helyen van, vagy utasítsd el, ha nincs ott. Az átvevő által elutasított átadásoknál pedig eldöntheted: elfogadod az elutasítást, vagy felülbírálod és mégis végrehajtod az átadást.</div>
      <div id="pending-table"></div>
    </div>`,en(e)}function en(e){let t=e.querySelector(`#pending-table`),n=le();Y&&(n=[...n].sort((e,t)=>X*Qt(e,Y).localeCompare(Qt(t,Y),`hu`,{sensitivity:`base`}))),t.innerHTML=n.length?`
    <div class="table-wrap">
      <table class="grid">
        <thead><tr>
          <th>Típus</th>
          ${Zt(`typeName`,`Típus / modell`)}
          ${Zt(`submitter`,`Kezdeményezte`)}
          ${Zt(`to_location`,`Cél / helyiség`)}
          ${Zt(`event_timestamp`,`Időpont`)}
          <th>Állapot / indok</th>
          <th style="text-align:right">Döntés</th>
        </tr></thead>
        <tbody>
          ${n.map(nn).join(``)}
        </tbody>
      </table>
    </div>`:`<div class="table-wrap"><div class="empty"><div class="big">${N.check}</div><div>Nincs ellenőrzésre váró tétel.</div></div></div>`,t.querySelectorAll(`[data-dev]`).forEach(e=>e.addEventListener(`click`,t=>{t.target.closest(`button`)||i(`/device/`+e.dataset.dev)})),t.querySelectorAll(`[data-confirm]`).forEach(e=>e.addEventListener(`click`,t=>{t.stopPropagation(),kt(Number(e.dataset.confirm))})),t.querySelectorAll(`[data-reject]`).forEach(e=>e.addEventListener(`click`,t=>{t.stopPropagation(),At(Number(e.dataset.reject))})),t.querySelectorAll(`[data-accept-rejection]`).forEach(e=>e.addEventListener(`click`,t=>{t.stopPropagation(),Nt(Number(e.dataset.acceptRejection))})),t.querySelectorAll(`[data-override]`).forEach(e=>e.addEventListener(`click`,t=>{t.stopPropagation(),Pt(Number(e.dataset.override))})),t.querySelectorAll(`th[data-col]`).forEach(t=>t.addEventListener(`click`,()=>{Xt(t.dataset.col),en(e)})),t.querySelectorAll(`.table-wrap`).forEach(e=>{it(e),z(e)})}function tn(e){return e.kind===`rejected_transfer`?j(b(e.to_user_id)?.full_name||`—`):j(k(e.to_locations_id,e.to_departments_id))}function nn(e){let t=w(e.device_id),n=ie(t?.device_type_id),r=b(e.actor_user_id),i=e.kind===`rejected_transfer`;return`
    <tr data-dev="${e.device_id}">
      <td>${i?`<span class="status-badge status-lost">Átadás elutasítva</span>`:`<span class="status-badge status-pending">Visszavétel</span>`}</td>
      <td><span class="tag-mono"></span>${j(n?.type||``)}<div class="cell-sub">${j(t?.manufacturer||``)} · ${j(t?.model||``)}</div></td>
      <td>${j(r?.full_name||`—`)}</td>
      <td>${tn(e)}</td>
      <td>${A(e.event_timestamp)}</td>
      <td>${j((i?e.notes:e.condition_at_event)||`—`)}</td>
      <td style="text-align:right">
        <div class="row-actions" style="justify-content:flex-end">
          ${i?`
            <button class="btn btn-outline btn-sm" data-accept-rejection="${e.event_id}">Elutasítás elfogadása</button>
            <button class="btn btn-danger btn-sm" data-override="${e.event_id}">Felülbírálás</button>
          `:`
            <button class="btn btn-success btn-sm" data-confirm="${e.event_id}">${N.check} Megerősít</button>
            <button class="btn btn-danger btn-sm" data-reject="${e.event_id}">${N.x} Elutasít</button>
          `}
        </div>
      </td>
    </tr>`}function rn(e,t){let n=t??``,r=e.is_required?`<span style="color:#c0392b">*</span>`:``,i=`<label class="form-label">${j(e.label)} ${r}</label>`,a;if(e.data_type===`enum`){let t=(e.options||``).split(`,`).map(e=>e.trim());a=`<select class="form-select" data-attr="${e.attribute_key}">
      <option value="">— válassz —</option>
      ${t.map(e=>`<option ${e===n?`selected`:``}>${j(e)}</option>`).join(``)}
    </select>`}else a=e.data_type===`boolean`?`<select class="form-select" data-attr="${e.attribute_key}">
      <option value="" ${n===``?`selected`:``}>—</option>
      <option value="true" ${n===!0||n===`true`?`selected`:``}>Igen</option>
      <option value="false" ${n===!1||n===`false`?`selected`:``}>Nem</option>
    </select>`:e.data_type===`date`?`<input type="date" class="form-control" data-attr="${e.attribute_key}" value="${j(n)}" />`:e.data_type===`integer`||e.data_type===`decimal`?`<input type="number" class="form-control" data-attr="${e.attribute_key}" value="${j(n)}" ${e.data_type===`integer`?`step="1"`:`step="any"`} />`:`<input type="text" class="form-control" data-attr="${e.attribute_key}" value="${j(n)}" />`;return`<div class="field" data-type="${e.data_type}" data-required="${e.is_required}">${i}${a}</div>`}function an(e){let t={},n=null;return e.querySelectorAll(`[data-attr]`).forEach(e=>{let r=e.dataset.attr,i=e.closest(`.field`),a=i.dataset.type,o=i.dataset.required===`true`,s=e.value.trim();if(s===``){o&&(n||=`Kötelező mező hiányzik: ${i.querySelector(`.form-label`).textContent.replace(`*`,``).trim()}`);return}a===`integer`||a===`decimal`?t[r]=Number(s):a===`boolean`?t[r]=s===`true`:t[r]=s}),{attrs:t,error:n}}function on(e){if(!D(E(),`storekeeper`)){e.innerHTML=`<div class="content"><div class="empty"><div class="big">${N.warning}</div><div>Új eszköz regisztrálásához raktáros vagy IT-admin szerepkör kell.</div></div></div>`;return}let t=re(),n=S(),r=x();e.innerHTML=`
    <div class="content" style="max-width:760px">
      <button class="btn btn-ghost btn-sm" id="back" style="margin-bottom:14px">${N.back} Vissza</button>
      <div class="panel">
        <div class="panel-head">Új eszköz regisztrálása</div>
        <div class="panel-body">
          <div class="field">
            <label class="form-label">Eszköztípus *</label>
            <select class="form-select" id="r-type">
              <option value="">— válassz típust —</option>
              ${t.map(e=>`<option value="${e.id}">${j(e.type)}</option>`).join(``)}
            </select>
          </div>
          <div id="r-common" style="display:none">
            <div class="divider"></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px">
              <div class="field"><label class="form-label">Leltári azonosító *</label><input class="form-control" id="r-tag" placeholder="pl. BUD-LAP-00099" /></div>
              <div class="field"><label class="form-label">Sorozatszám</label><input class="form-control" id="r-serial" /></div>
              <div class="field"><label class="form-label">Gyártó</label><input class="form-control" id="r-manu" /></div>
              <div class="field"><label class="form-label">Modell</label><input class="form-control" id="r-model" /></div>
              <div class="field"><label class="form-label">Állapot</label>
                <select class="form-select" id="r-cond">${[`Jó`,`Kopott`,`Hibás`].map(e=>`<option>${e}</option>`).join(``)}</select>
              </div>
              <div class="field"><label class="form-label">Kezdeti elhelyezés *</label>
                <select class="form-select" id="r-loc">${n.map(e=>`<option value="${e.id}">${j(e.address)}</option>`).join(``)}</select>
                <select class="form-select" id="r-dept"></select>
              </div>
            </div>
            <div class="field"><label class="form-label">Megjegyzés</label><input class="form-control" id="r-notes" /></div>
            <div class="divider"></div>
            <div class="form-label" style="margin-bottom:10px; font-size:.9rem; color:var(--ink)">Típusspecifikus adatok</div>
            <div id="r-attrs" style="display:grid; grid-template-columns:1fr 1fr; gap:14px"></div>
            <div class="divider"></div>
            <div style="display:flex; gap:10px; justify-content:flex-end">
              <button class="btn btn-outline" id="r-cancel">Mégse</button>
              <button class="btn btn-primary" id="r-save">${N.register} Eszköz létrehozása</button>
            </div>
          </div>
        </div>
      </div>
    </div>`,e.querySelector(`#back`).addEventListener(`click`,()=>i(`/inventory`)),e.querySelector(`#r-cancel`).addEventListener(`click`,()=>i(`/inventory`));let a=e.querySelector(`#r-type`),o=e.querySelector(`#r-common`),s=e.querySelector(`#r-attrs`),c=e.querySelector(`#r-loc`),l=e.querySelector(`#r-dept`);function u(){let e=Number(c.value),t=r.filter(t=>t.locations_id===e);l.innerHTML=t.length?t.map(e=>`<option value="${e.id}" ${e.type===`raktár`?`selected`:``}>${j(e.name)}</option>`).join(``):`<option value="">— nincs részleg ezen a helyszínen —</option>`}c.addEventListener(`change`,u),u(),a.addEventListener(`change`,()=>{let e=Number(a.value);if(!e){o.style.display=`none`;return}o.style.display=`block`,s.innerHTML=oe(e).map(e=>rn(e,``)).join(``)||`<div class="muted">Nincs típusattribútum.</div>`,R(s)}),R(e);let d=e.querySelector(`#r-save`);d.addEventListener(`click`,async()=>{if(d.disabled)return;let t=Number(a.value),n=e.querySelector(`#r-tag`).value.trim();if(!n){F(`Adj meg leltári azonosítót.`,`error`);return}let{attrs:r,error:o}=an(s);if(o){F(o,`error`);return}d.disabled=!0;try{let a=await ke({device_type_id:t,asset_tag:n,serial_number:e.querySelector(`#r-serial`).value.trim(),manufacturer:e.querySelector(`#r-manu`).value.trim(),model:e.querySelector(`#r-model`).value.trim(),condition:e.querySelector(`#r-cond`).value,notes:e.querySelector(`#r-notes`).value.trim(),initial_location:Number(e.querySelector(`#r-loc`).value),initial_department:e.querySelector(`#r-dept`).value===``?null:Number(e.querySelector(`#r-dept`).value),attrs:r});F(`Eszköz létrehozva.`,`success`),i(`/device/`+a.device_id)}catch(e){F(e.message,`error`),d.disabled=!1}})}function sn(e){let t=w(e),n=oe(t.device_type_id);I({title:`Eszköz szerkesztése · <span class="tag-mono" style="margin-left:8px">${j(t.asset_tag)}</span>`,wide:!0,bodyHTML:`
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px">
        <div class="field"><label class="form-label">Gyártó</label><input class="form-control" id="e-manu" value="${j(t.manufacturer||``)}" /></div>
        <div class="field"><label class="form-label">Modell</label><input class="form-control" id="e-model" value="${j(t.model||``)}" /></div>
        <div class="field"><label class="form-label">Sorozatszám</label><input class="form-control" id="e-serial" value="${j(t.serial_number||``)}" /></div>
        <div class="field"><label class="form-label">Állapot</label>
          <select class="form-select" id="e-cond">${[`Jó`,`Kopott`,`Hibás`,`Ismeretlen`].map(e=>`<option ${e===t.condition?`selected`:``}>${e}</option>`).join(``)}</select>
        </div>
      </div>
      <div class="field"><label class="form-label">Megjegyzés</label><input class="form-control" id="e-notes" value="${j(t.notes||``)}" /></div>
      <div class="divider"></div>
      <div class="form-label" style="margin-bottom:10px; font-size:.9rem; color:var(--ink)">Típusspecifikus adatok</div>
      <div id="e-attrs" style="display:grid; grid-template-columns:1fr 1fr; gap:14px">
        ${n.map(e=>rn(e,t.attrs?.[e.attribute_key])).join(``)||`<div class="muted">Nincs típusattribútum.</div>`}
      </div>`,confirmText:`Mentés`,onMount:e=>R(e),onConfirm:async t=>{let{attrs:n,error:r}=an(t.querySelector(`#e-attrs`));if(r)return F(r,`error`),!1;await Ae(e,{manufacturer:t.querySelector(`#e-manu`).value.trim(),model:t.querySelector(`#e-model`).value.trim(),serial_number:t.querySelector(`#e-serial`).value.trim(),condition:t.querySelector(`#e-cond`).value,notes:t.querySelector(`#e-notes`).value.trim(),attrs:n}),F(`Eszköz frissítve.`,`success`)}})}function cn(e){if(!D(E(),`storekeeper`)){e.innerHTML=`<div class="content"><div class="empty"><div class="big">${N.warning}</div><div>Ehhez raktáros vagy IT-admin szerepkör kell.</div></div></div>`;return}let t=re(),n=S();e.innerHTML=`
    <div class="content" style="max-width:760px">
      <button class="btn btn-ghost btn-sm" id="back" style="margin-bottom:14px">${N.back} Vissza</button>
      <div class="panel">
        <div class="panel-head">Törzsadat bevitele</div>
        <div class="panel-body">
          <div class="field">
            <label class="form-label">Kategória *</label>
            <select class="form-select" id="rd-cat">
              <option value="">— válassz kategóriát —</option>
              <option value="location">Új helyszín</option>
              <option value="department">Új részleg / helyiség</option>
              <option value="device_type">Új eszköztípus</option>
              <option value="attr_general">Általános eszközattribútum</option>
              <option value="attr_type">Típusspecifikus eszközattribútum</option>
            </select>
          </div>

          <div id="form-location" style="display:none">
            <div class="divider"></div>
            <div class="field">
              <label class="form-label">Cím *</label>
              <input class="form-control" id="loc-address" placeholder="pl. 1095 Budapest, Soroksári út 12." />
            </div>
            ${Z(`Helyszín mentése`)}
          </div>

          <div id="form-department" style="display:none">
            <div class="divider"></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px">
              <div class="field">
                <label class="form-label">Helyszín *</label>
                <select class="form-select" id="dept-loc">
                  ${n.map(e=>`<option value="${e.id}">${j(e.address)}</option>`).join(``)}
                </select>
              </div>
              <div class="field">
                <label class="form-label">Típus *</label>
                <select class="form-select" id="dept-type">
                  <option value="osztály">Osztály</option>
                  <option value="raktár">Raktár</option>
                  <option value="recepció">Recepció</option>
                  <option value="műhely">Műhely</option>
                </select>
              </div>
              <div class="field" style="grid-column:1/-1">
                <label class="form-label">Név *</label>
                <input class="form-control" id="dept-name" placeholder="pl. Kardiológia" />
              </div>
            </div>
            ${Z(`Részleg mentése`)}
          </div>

          <div id="form-device_type" style="display:none">
            <div class="divider"></div>
            <div class="field">
              <label class="form-label">Típus neve *</label>
              <input class="form-control" id="dtype-name" placeholder="pl. Véroxigénmérő" />
            </div>
            <div class="field">
              <label class="form-label">Leírás</label>
              <input class="form-control" id="dtype-desc" placeholder="pl. Pulzoximeter készülék" />
            </div>
            ${Z(`Eszköztípus mentése`)}
          </div>

          <div id="form-attr_general" style="display:none">
            <div class="divider"></div>
            ${un(`ag`)}
            ${Z(`Attribútum mentése`)}
          </div>

          <div id="form-attr_type" style="display:none">
            <div class="divider"></div>
            <div class="field">
              <label class="form-label">Eszköztípus *</label>
              <select class="form-select" id="attr-type-sel">
                ${t.map(e=>`<option value="${e.id}">${j(e.type)}</option>`).join(``)}
              </select>
            </div>
            ${un(`at`)}
            ${Z(`Attribútum mentése`)}
          </div>
        </div>
      </div>
    </div>`,e.querySelector(`#back`).addEventListener(`click`,()=>i(`/`)),R(e);let r=e.querySelector(`#rd-cat`),a=[`location`,`department`,`device_type`,`attr_general`,`attr_type`];r.addEventListener(`change`,()=>{a.forEach(t=>{e.querySelector(`#form-${t}`).style.display=`none`}),r.value&&(e.querySelector(`#form-${r.value}`).style.display=`block`)}),[`ag`,`at`].forEach(t=>{let n=e.querySelector(`#${t}-data-type`),r=e.querySelector(`#${t}-options-row`);n.addEventListener(`change`,()=>{r.style.display=n.value===`enum`?`block`:`none`})});let o=e.querySelector(`#form-location .btn-primary`);o.addEventListener(`click`,()=>ln(o,async()=>{let t=e.querySelector(`#loc-address`).value.trim();if(!t){F(`Add meg a helyszín címét.`,`error`);return}try{await Me({address:t}),F(`Helyszín hozzáadva.`,`success`),e.querySelector(`#loc-address`).value=``}catch(e){F(e.message,`error`)}}));let s=e.querySelector(`#form-department .btn-primary`);s.addEventListener(`click`,()=>ln(s,async()=>{let t=Number(e.querySelector(`#dept-loc`).value),n=e.querySelector(`#dept-name`).value.trim();if(!t){F(`Nincs választható helyszín — előbb adj meg egyet.`,`error`);return}if(!n){F(`Add meg a részleg nevét.`,`error`);return}try{await Ne({locations_id:t,name:n,type:e.querySelector(`#dept-type`).value}),F(`Részleg hozzáadva.`,`success`),e.querySelector(`#dept-name`).value=``}catch(e){F(e.message,`error`)}}));let c=e.querySelector(`#form-device_type .btn-primary`);c.addEventListener(`click`,()=>ln(c,async()=>{let t=e.querySelector(`#dtype-name`).value.trim(),n=e.querySelector(`#dtype-desc`).value.trim();if(!t){F(`Add meg az eszköztípus nevét.`,`error`);return}try{await Pe({type:t,description:n}),F(`Eszköztípus hozzáadva.`,`success`),e.querySelector(`#dtype-name`).value=``,e.querySelector(`#dtype-desc`).value=``}catch(e){F(e.message,`error`)}})),[[`ag`,null],[`at`,`attr-type-sel`]].forEach(([t,n])=>{let r=e.querySelector(`#form-${t===`ag`?`attr_general`:`attr_type`} .btn-primary`);r.addEventListener(`click`,()=>ln(r,async()=>{let r=n?Number(e.querySelector(`#${n}`).value):null,i=e.querySelector(`#${t}-key`).value.trim(),a=e.querySelector(`#${t}-label`).value.trim();if(n&&!r){F(`Nincs választható eszköztípus — előbb adj meg egyet.`,`error`);return}if(!i){F(`Add meg az attribútum kulcsát.`,`error`);return}if(!a){F(`Add meg az attribútum feliratát.`,`error`);return}try{await Fe({device_type_id:r,attribute_key:i,label:a,data_type:e.querySelector(`#${t}-data-type`).value,is_required:e.querySelector(`#${t}-required`).value===`true`,options:e.querySelector(`#${t}-options`).value.trim(),sort_order:Number(e.querySelector(`#${t}-sort`).value)||0}),F(`Attribútum hozzáadva.`,`success`),e.querySelector(`#${t}-key`).value=``,e.querySelector(`#${t}-label`).value=``,e.querySelector(`#${t}-options`).value=``,e.querySelector(`#${t}-sort`).value=`0`}catch(e){F(e.message,`error`)}}))})}async function ln(e,t){if(!e.disabled){e.disabled=!0;try{await t()}finally{e.disabled=!1}}}function Z(e){return`<div style="display:flex; gap:10px; justify-content:flex-end; margin-top:14px">
    <button class="btn btn-primary">${N.register} ${j(e)}</button>
  </div>`}function un(e){return`
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px">
      <div class="field">
        <label class="form-label">Attribútum kulcs *</label>
        <input class="form-control" id="${e}-key" placeholder="pl. calibration_due" />
      </div>
      <div class="field">
        <label class="form-label">Felirat *</label>
        <input class="form-control" id="${e}-label" placeholder="pl. Következő kalibráció" />
      </div>
      <div class="field">
        <label class="form-label">Adattípus *</label>
        <select class="form-select" id="${e}-data-type">
          <option value="text">Szöveg</option>
          <option value="integer">Egész szám</option>
          <option value="decimal">Tizedes szám</option>
          <option value="date">Dátum</option>
          <option value="boolean">Igen/Nem</option>
          <option value="enum">Felsorolás (enum)</option>
        </select>
      </div>
      <div class="field">
        <label class="form-label">Kötelező?</label>
        <select class="form-select" id="${e}-required">
          <option value="false">Nem</option>
          <option value="true">Igen</option>
        </select>
      </div>
      <div class="field" id="${e}-options-row" style="grid-column:1/-1; display:none">
        <label class="form-label">Lehetséges értékek (vesszővel elválasztva)</label>
        <input class="form-control" id="${e}-options" placeholder="pl. Jó,Közepes,Rossz" />
      </div>
      <div class="field">
        <label class="form-label">Sorrend</label>
        <input type="number" class="form-control" id="${e}-sort" value="0" min="0" step="1" />
      </div>
    </div>`}function dn(e){let t=e.querySelector(`#scan-input`);t.focus(),t.addEventListener(`keydown`,e=>{if(e.key!==`Enter`)return;e.preventDefault();let n=t.value.trim();t.value=``,n&&fn(n),t.focus()})}function fn(e){let t=ae(e);if(!t)return F(`Ismeretlen azonosító: ${j(e)}`,`error`);let n=L(t),r=T(),i=D(E(),`storekeeper`);if([`Selejtezve`,`Elveszett`,`Szerviz alatt`].includes(n.status))return F(`Nem kezelhető: ${n.status}.`,`error`);if(n.pending)return F(`Visszavétel folyamatban — raktáros megerősítésére vár.`,`error`);if(n.holderId===r.id)return wt(t.device_id);if(n.holderId!==null)return i?wt(t.device_id):F(`Másnál van: ${n.holder?.full_name}.`,`error`);let a=n.reservation;return a&&a.reserved_by!==r.id&&!i?F(`Lefoglalva: ${n.reservedBy?.full_name}.`,`error`):Ct(t.device_id)}function pn(e,{tag:t}={}){e.innerHTML=`
    <div class="content">
      <div class="scan-wrap">
        <div class="scan-icon">${N.qr}</div>
        <input id="scan-input" class="form-control"
          autocomplete="off" spellcheck="false"
          placeholder="Olvasd be vagy gépeld az azonosítót…" />
        <p class="scan-hint">Nyomj 'Enter'-t, vagy olvasd be a vonalkódot.</p>
      </div>
    </div>`,dn(e),t&&(fn(decodeURIComponent(t)),i(`/scan`))}var mn={"/inventory":{title:`Eszközlista`,nav:`inventory`,render:_t},"/my":{title:`Eszközeim`,nav:`my`,render:Jt},"/pending":{title:`Ellenőrzésre vár`,nav:`pending`,render:$t,role:`storekeeper`},"/register-data":{title:`Adatbevitel`,nav:`register-data`,render:cn,role:`storekeeper`},"/register":{title:`Új eszköz bevitele`,nav:`register`,render:on,role:`storekeeper`},"/device/:id":{title:`Készülék részletei`,nav:`inventory`,render:Bt},"/scan":{title:`Beolvasás`,nav:`scan`,render:pn},"/scan/:tag":{title:`Beolvasás`,nav:`scan`,render:pn}},Q={key:`/`,params:{}};function hn(){document.getElementById(`app`).innerHTML=`
    <div class="app-shell">
      <div class="sidebar-overlay" id="sidebar-overlay"></div>
      <aside class="sidebar" id="sidebar">
        <nav class="nav-section" id="nav"></nav>
      </aside>
      <main class="main">
        <div class="topbar">
          <button class="btn-hamburger" id="btn-hamburger" aria-label="Menü">
            <span></span><span></span><span></span>
          </button>
          <div class="spacer"></div>
          
        </div>
        <div id="content"></div>
      </main>
    </div>`;let e=document.getElementById(`btn-hamburger`),t=document.getElementById(`sidebar-overlay`);function n(){document.getElementById(`app`).querySelector(`.app-shell`).classList.remove(`sidebar-open`)}e.addEventListener(`click`,()=>{document.getElementById(`app`).querySelector(`.app-shell`).classList.toggle(`sidebar-open`)}),t.addEventListener(`click`,n)}function gn(){let e=D(E(),`storekeeper`),t=le().length,n=ue().length,r=mn[Q.key]?.nav,a=[{key:`inventory`,path:`/inventory`,label:`Eszközlista`,ico:N.inventory},{key:`my`,path:`/my`,label:`Eszközeim`,ico:N.my,badge:n||null}],o=[{key:`pending`,path:`/pending`,label:`Leadott eszközök`,ico:N.pending,badge:t||null},{key:`register-data`,path:`/register-data`,label:`Adatbevitel`,ico:N.building}],s=e=>`
    <a class="nav-item ${e.key===r?`active`:``}" data-path="${e.path}">
      <span class="ico">${e.ico}</span><span>${e.label}</span>
      ${e.badge?`<span class="badge-count">${e.badge}</span>`:``}
    </a>`,c=document.getElementById(`nav`);c.innerHTML=`<div class="nav-label">Eszközök</div>`+a.map(s).join(``)+(e?`<div class="nav-label">Raktárkezelés</div>`+o.map(s).join(``):``),c.querySelectorAll(`[data-path]`).forEach(e=>e.addEventListener(`click`,()=>{i(e.dataset.path),document.getElementById(`app`).querySelector(`.app-shell`).classList.remove(`sidebar-open`)}))}function _n(){let e=T(),t=document.getElementById(`user-select`);t&&t.value!==String(e.id)&&(t.value=String(e.id))}function $(){if(!document.getElementById(`content`))return;let e=mn[Q.key];if(!e){i(`/inventory`);return}if(e?.role&&!D(E(),e.role)){i(`/`);return}gn(),_n();let t=document.getElementById(`content`);t.innerHTML=``,e.render(t,Q.params)}function vn(){n(`/`,()=>i(`/inventory`)),n(`/inventory`,()=>{Q={key:`/inventory`,params:{}},$()}),n(`/my`,()=>{Q={key:`/my`,params:{}},$()}),n(`/pending`,()=>{Q={key:`/pending`,params:{}},$()}),n(`/register`,()=>{Q={key:`/register`,params:{}},$()}),n(`/register-data`,()=>{Q={key:`/register-data`,params:{}},$()}),n(`/device/:id`,e=>{Q={key:`/device/:id`,params:e},$()}),n(`/scan`,()=>{Q={key:`/scan`,params:{}},$()}),n(`/scan/:tag`,e=>{Q={key:`/scan/:tag`,params:e},$()}),r(()=>i(`/`))}function yn(e){document.getElementById(`app`).innerHTML=`<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px">
       <div style="text-align:center;max-width:420px;color:var(--ink,#333)">${e}</div>
     </div>`}async function bn(){yn(`<div class="muted">Betöltés…</div>`);let e=new URLSearchParams(location.search),t=null;if(e.has(`sso`)){try{await f(`POST`,`/auth/sso`,{token:e.get(`sso`),username:e.get(`u`),timestamp:Number(e.get(`t`))})}catch(e){t=e.message}history.replaceState(null,``,location.pathname+location.hash)}try{await _()}catch{yn(`<div class="big">${N.warning}</div>
      <h2>A szerver nem érhető el</h2>
      <p class="muted">Nem sikerült betölteni az adatokat. Ellenőrizd, hogy fut-e a backend.</p>`);return}if(!T()){yn(`<div class="big">${N.my}</div>
      <h2>Bejelentkezés szükséges</h2>
      <p class="muted">Jelentkezz be a főoldalon az eszköznyilvántartó használatához.</p>
      ${t?`<p class="muted" style="font-size:.8rem;margin-top:8px">(SSO: ${j(t)})</p>`:``}`);return}hn(),vn(),Ie(()=>$()),s()}bn();export{j as a,I as i,on as n,w as o,ft as r,sn as t};