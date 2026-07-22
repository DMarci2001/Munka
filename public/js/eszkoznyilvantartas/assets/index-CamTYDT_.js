(function(){let e=document.createElement(`link`).relList;if(e&&e.supports&&e.supports(`modulepreload`))return;for(let e of document.querySelectorAll(`link[rel="modulepreload"]`))n(e);new MutationObserver(e=>{for(let t of e)if(t.type===`childList`)for(let e of t.addedNodes)e.tagName===`LINK`&&e.rel===`modulepreload`&&n(e)}).observe(document,{childList:!0,subtree:!0});function t(e){let t={};return e.integrity&&(t.integrity=e.integrity),e.referrerPolicy&&(t.referrerPolicy=e.referrerPolicy),e.crossOrigin===`use-credentials`?t.credentials=`include`:e.crossOrigin===`anonymous`?t.credentials=`omit`:t.credentials=`same-origin`,t}function n(e){if(e.ep)return;e.ep=!0;let n=t(e);fetch(e.href,n)}})();var e=[],t=null;function n(t,n){let r=[],i=RegExp(`^`+t.replace(/:[^/]+/g,e=>(r.push(e.slice(1)),`([^/]+)`))+`$`);e.push({regex:i,keys:r,handler:n})}function r(e){t=e}function i(e){location.hash===`#`+e?o():location.hash=`#`+e}function a(){return location.hash.replace(/^#/,``)||`/`}function o(){let n=a();for(let t of e){let e=n.match(t.regex);if(e){let n={};t.keys.forEach((t,r)=>n[t]=decodeURIComponent(e[r+1])),t.handler(n);return}}t&&t()}function s(){window.addEventListener(`hashchange`,o),o()}var c=`/eszkoznyilvantartas/api/index.php`,l=class extends Error{};async function u(e,t,n){let r={method:e,credentials:`include`,headers:{}};n!==void 0&&(r.headers[`Content-Type`]=`application/json`,r.body=JSON.stringify(n));let i;try{i=await fetch(c+t,r)}catch{throw new l(`Hálózati hiba — a szerver nem érhető el.`)}let a=null;try{a=await i.json()}catch{}if(!a||typeof a.ok!=`boolean`)throw new l(`Váratlan szerverválasz (HTTP ${i.status}).`);if(!a.ok)throw new l(a.error||`Hiba történt (HTTP ${i.status}).`);return a.data}var d=e=>u(`GET`,e),f=(e,t,n)=>u(e,t,n),p={locations:[],departments:[],users:[],deviceTypes:[],attributeDefinitions:[],devices:[],pending:[],reservations:[],currentUser:null},m={},h=new Set,g=new Set;async function _(){let e=await d(`/bootstrap`);p.locations=e.locations||[],p.departments=e.departments||[],p.users=e.users||[],p.deviceTypes=e.deviceTypes||[],p.attributeDefinitions=e.attributeDefinitions||[],p.devices=e.devices||[],p.pending=e.pending||[],p.reservations=e.reservations||[],p.currentUser=e.currentUser||null,m={},je()}async function v(){let[e,t,n]=await Promise.all([d(`/devices`),d(`/pending`),d(`/reservations`)]);p.devices=e||[],p.pending=t||[],p.reservations=n||[],m={},je()}var ee=()=>p.users,y=e=>p.users.find(t=>t.id===e)||null,te=()=>p.departments,ne=e=>p.departments.find(t=>t.id===e)||null,re=e=>ne(e)?.type===`raktár`,b=()=>p.locations,ie=e=>p.locations.find(t=>t.id===e)||null,ae=()=>p.deviceTypes,oe=e=>p.deviceTypes.find(t=>t.id===e)||null,x=()=>p.devices,S=e=>p.devices.find(t=>t.device_id===e)||null,se=e=>p.devices.find(t=>t.asset_tag?.toLowerCase()===String(e).trim().toLowerCase())||null,ce=e=>p.attributeDefinitions.filter(t=>t.device_type_id===e||t.device_type_id===null).sort((e,t)=>e.sort_order-t.sort_order),C=()=>p.currentUser,w=()=>p.currentUser?.auth||`user`,le={user:1,storekeeper:2,it_admin:3},T=(e,t)=>(le[e]||1)>=(le[t]||1);function ue(e){let t=S(e);return t?{holder:t.holder_id??null,location:t.location_id??null,department:t.department_id??null,since:t.since??null}:{holder:null,location:null,department:null,since:null}}var de=()=>p.pending,fe=e=>m[e]||[],pe=e=>Object.prototype.hasOwnProperty.call(m,e);async function me(e){if(!(pe(e)||h.has(e))){h.add(e);try{m[e]=await d(`/devices/${e}/history`)||[]}catch{m[e]=[]}finally{h.delete(e),je()}}}async function E(e){await f(`POST`,`/devices/move`,e),await v()}async function he(e){await f(`POST`,`/checkins/${e}/confirm`),await v()}async function ge(e,t){await f(`POST`,`/checkins/${e}/reject`,{reason:t}),await v()}async function _e(e,t=null){await f(`POST`,`/devices/${e}/reserve`,{notes:t}),await v()}async function ve(e){await f(`POST`,`/devices/${e}/cancel-reservation`),await v()}async function ye(e,t=null,n=null,r=null){await f(`POST`,`/devices/${e}/send-to-repair`,{to_locations_id:t,to_departments_id:n,notes:r}),await v()}async function be(e,t,n,r=null){await f(`POST`,`/devices/${e}/return-from-repair`,{to_locations_id:t,to_departments_id:n,notes:r}),await v()}async function xe(e,t=null){await f(`POST`,`/devices/${e}/mark-lost`,{notes:t}),await v()}async function Se(e,t,n,r=null){await f(`POST`,`/devices/${e}/mark-found`,{to_locations_id:t,to_departments_id:n,notes:r}),await v()}async function Ce(e){let t=await f(`POST`,`/devices`,e);return await v(),t}async function we(e,t){await f(`PATCH`,`/devices/${e}`,t),await v()}async function Te(e,t){await f(`POST`,`/devices/${e}/retire`,{reason:t}),await v()}async function Ee({address:e}){let t=await f(`POST`,`/locations`,{address:e});return await _(),t?.id}async function De({locations_id:e,name:t,type:n}){let r=await f(`POST`,`/departments`,{locations_id:e,name:t,type:n});return await _(),r?.id}async function Oe({type:e,description:t}){let n=await f(`POST`,`/device-types`,{type:e,description:t});return await _(),n?.id}async function ke(e){let t=await f(`POST`,`/attribute-definitions`,e);return await _(),t?.id}function Ae(e){return g.add(e),()=>g.delete(e)}function je(){for(let e of g)e()}var Me={Kiadva:{label:`Kiadva`,cls:`status-deployed`},Kivehető:{label:`Kivehető`,cls:`status-ready`},Lefoglalva:{label:`Lefoglalva`,cls:`status-reserved`},"Visszavétel folyamatban":{label:`Visszavétel folyamatban`,cls:`status-pending`},"Szerviz alatt":{label:`Szerviz alatt`,cls:`status-repair`},Elveszett:{label:`Elveszett`,cls:`status-lost`},Selejtezve:{label:`Selejtezve`,cls:`status-retired`}},Ne=e=>Me[e]?.label||e||`—`,Pe=e=>Me[e]?.cls||`status-default`;function D(e){return`<span class="status-badge ${Pe(e)}">${Ne(e)}</span>`}var Fe={check_out:`Kivétel`,check_in:`Leadás`,transfer:`Átadás`,stock_transfer:`Raktármozgatás`,send_to_repair:`Szervizbe küldés`,return_from_repair:`Szervizből visszahelyezés`,mark_lost:`Elveszettnek jelölés`,mark_found:`Megtalálva`},Ie=e=>Fe[e]||e,Le={pending:`Függőben`,confirmed:`Megerősítve`,rejected:`Elutasítva`},Re=e=>Le[e]||e;function O(e,t){let n=t?ne(t):null,r=e?ie(e):null;return n&&r?`${n.name} · ${r.address}`:n?n.name:r?r.address:`—`}function ze(e){if(!e)return`—`;let t=y(e);return t?t.full_name:`—`}function Be(e){if(!e)return`—`;let t=e instanceof Date?e:new Date(e);return isNaN(t)?`—`:t.toLocaleDateString(`hu-HU`,{year:`numeric`,month:`2-digit`,day:`2-digit`})}function Ve(e){if(!e)return`—`;let t=e instanceof Date?e:new Date(e);return isNaN(t)?`—`:t.toLocaleString(`hu-HU`,{year:`numeric`,month:`2-digit`,day:`2-digit`,hour:`2-digit`,minute:`2-digit`})}function He(e){if(!e)return`—`;let t=e instanceof Date?e:new Date(e);if(isNaN(t))return`—`;let n=t-Date.now(),r=Math.round(n/36e5);return r<=0?`lejárt`:r<24?`${r} óra múlva`:`${Math.round(r/24)} nap múlva`}function Ue(e,t){return t==null||t===``?`—`:e.data_type===`boolean`?t?`Igen`:`Nem`:e.data_type===`date`?Be(t):String(t)}function We(e){if(!e)return null;let t=new Date(e);if(isNaN(t))return null;let n=Math.round((t-Date.now())/864e5);return n<0?`overdue`:n<=30?`soon`:`ok`}function k(e){return e==null?``:String(e).replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`).replace(/"/g,`&quot;`).replace(/'/g,`&#39;`)}var A=(e,t=``)=>`<svg class="ico-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ${t}>${e}</svg>`,j={dashboard:A(`<rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/>`),inventory:A(`<path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/>`),my:A(`<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 12 0v1"/>`),pending:A(`<path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/>`),register:A(`<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/>`),search:A(`<circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/>`),check:A(`<path d="M20 6L9 17l-5-5"/>`),x:A(`<path d="M18 6L6 18M6 6l12 12"/>`),arrowRight:A(`<path d="M5 12h14M13 6l6 6-6 6"/>`),qr:A(`<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3M21 14v7M17 21h4M14 21h0"/>`),back:A(`<path d="M19 12H5M11 18l-6-6 6-6"/>`),bookmark:A(`<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>`),edit:A(`<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>`),repair:A(`<path d="M14.7 6.3a4 4 0 0 0-5 5L3 18l3 3 6.7-6.7a4 4 0 0 0 5-5l-2.8 2.8-2.1-2.1z"/>`),warning:A(`<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h0"/>`),building:A(`<rect x="4" y="2" width="16" height="20" rx="1"/><path d="M9 22v-4h6v4M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01"/>`),printer:A(`<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>`)},M;function N(e,t=`default`){M||(M=document.createElement(`div`),M.className=`toast-stack`,document.body.appendChild(M));let n=document.createElement(`div`);n.className=`toast-c ${t}`,n.innerHTML=`<span>${t===`success`?j.check:t===`error`?j.warning:``}</span><span>${e}</span>`,M.appendChild(n),setTimeout(()=>{n.style.transition=`opacity .25s, transform .25s`,n.style.opacity=`0`,n.style.transform=`translateX(20px)`,setTimeout(()=>n.remove(),260)},3200)}function Ge(){let e=window.frameElement;e&&(e.dataset.eszkozOrigStyle===void 0&&(e.dataset.eszkozOrigStyle=e.getAttribute(`style`)||``),e.style.cssText=`position:fixed;inset:0;width:100vw;height:100vh;z-index:99999;border:0;background:#fff;`)}function Ke(){let e=window.frameElement;!e||e.dataset.eszkozOrigStyle===void 0||(e.setAttribute(`style`,e.dataset.eszkozOrigStyle),delete e.dataset.eszkozOrigStyle)}function P({title:e,bodyHTML:t,confirmText:n=`Mentés`,confirmClass:r=`btn-primary`,onConfirm:i,onMount:a,wide:o=!1,closeOnBackdrop:s=!0}){qe(),Ge();let c=document.createElement(`div`);c.className=`modal-backdrop-c`,c.innerHTML=`
    <div class="modal-c" style="${o?`max-width:680px`:``}">
      <div class="m-head">${e}<button class="close" data-close>&times;</button></div>
      <div class="m-body">${t}</div>
      <div class="m-foot">
        <button class="btn btn-outline" data-close>Mégse</button>
        ${i?`<button class="btn ${r}" data-confirm>${n}</button>`:``}
      </div>
    </div>`,document.body.appendChild(c);let l=c.querySelector(`.modal-c`),u=()=>{c.remove(),Ke()};c.querySelectorAll(`[data-close]`).forEach(e=>e.addEventListener(`click`,u)),s&&c.addEventListener(`mousedown`,e=>{e.target===c&&u()}),document.addEventListener(`keydown`,function e(t){t.key===`Escape`&&(u(),document.removeEventListener(`keydown`,e))});let d=c.querySelector(`[data-confirm]`);return d&&i&&d.addEventListener(`click`,async()=>{if(!d.disabled){d.disabled=!0;try{await i(l)===!1?d.disabled=!1:u()}catch(e){N(e.message||`Hiba történt`,`error`),d.disabled=!1}}}),a&&a(l),{close:u,root:l}}function qe(){document.querySelectorAll(`.modal-backdrop-c`).forEach(e=>e.remove()),Ke()}function F(e){let t=oe(e.device_type_id),n=e.reservation||null,r=e.pending||null,i=e.calibration_due??e.attrs?.calibration_due??null;return{dev:e,type:t,typeName:t?.type||`—`,status:e.status,holderId:e.holder_id??null,holder:e.holder_id?y(e.holder_id):null,locationId:e.location_id??null,departmentId:e.department_id??null,since:e.since??null,reservation:n,reservedBy:n?y(n.reserved_by):null,pending:r,calibrationDue:i,calibrationFlag:We(i),lastModified:e.last_modified?String(e.last_modified).slice(0,10):null,lastCheckout:e.last_checkout_at?{event_timestamp:e.last_checkout_at}:null,lastReserved:n?{event_timestamp:n.reserved_at}:null,isFree:e.is_free??(e.status===`Kivehető`&&(e.department_id!==null||e.location_id!==null)),isLost:e.is_lost??e.status===`Elveszett`,inRepair:e.in_repair??e.status===`Szerviz alatt`}}var Je=0;function I(e){e.querySelectorAll(`select.form-select`).forEach(Xe)}function Ye(e){e?._sselSync?.()}function Xe(e){if(e._sselMounted)return;e._sselMounted=!0;let t=`ssel-`+ ++Je;e.style.display=`none`,e.setAttribute(`tabindex`,`-1`),e.setAttribute(`aria-hidden`,`true`);let n=document.createElement(`div`);n.className=`ssel`,n.innerHTML=`
    <input type="text" class="form-control ssel-input" id="${t}" autocomplete="off" spellcheck="false" />
    <span class="ssel-caret">▾</span>`,e.insertAdjacentElement(`afterend`,n);let r=n.querySelector(`.ssel-input`),i=document.createElement(`div`);i.className=`ssel-menu`,i.hidden=!0,document.body.appendChild(i);let a=[],o=-1,s=e=>e?e.text:``;function c(){let t=Array.from(e.options),n=t.filter(e=>e.value===``),r=t.filter(e=>e.value!==``);return r.sort((e,t)=>e.text.localeCompare(t.text,`hu`,{sensitivity:`base`,numeric:!0})),[...n,...r]}function l(){r.value=s(e.options[e.selectedIndex]),r.disabled=e.disabled}e._sselSync=l;function u(){let e=r.getBoundingClientRect();i.style.left=e.left+window.scrollX+`px`,i.style.top=e.bottom+window.scrollY+2+`px`,i.style.width=e.width+`px`}function d(t){let n=t.trim().toLocaleLowerCase(`hu`),r=c();a=n?r.filter(e=>e.text.toLocaleLowerCase(`hu`).includes(n)):r,o=a.indexOf(e.options[e.selectedIndex]),a.length?i.innerHTML=a.map((e,t)=>`<div class="ssel-item${t===o?` active`:``}" data-idx="${t}">${k(e.text)}</div>`).join(``):i.innerHTML=`<div class="ssel-empty">Nincs találat</div>`}function f(){Array.from(i.children).forEach((e,t)=>e.classList.toggle(`active`,t===o));let e=i.children[o];e&&e.scrollIntoView({block:`nearest`})}function p(t){if(!t)return;let n=Array.prototype.indexOf.call(e.options,t);n!==e.selectedIndex&&(e.selectedIndex=n,e.dispatchEvent(new Event(`input`,{bubbles:!0})),e.dispatchEvent(new Event(`change`,{bubbles:!0}))),_()}function m(e){n.contains(e.target)||i.contains(e.target)||_()}function h(e){e.target===i||e.target.nodeType===1&&i.contains(e.target)||_()}function g(){e.disabled||(u(),d(``),i.hidden=!1,n.classList.add(`open`),document.addEventListener(`mousedown`,m,!0),window.addEventListener(`scroll`,h,!0),window.addEventListener(`resize`,h))}function _(){i.hidden=!0,n.classList.remove(`open`),o=-1,document.removeEventListener(`mousedown`,m,!0),window.removeEventListener(`scroll`,h,!0),window.removeEventListener(`resize`,h),l()}r.addEventListener(`focus`,()=>{r.select(),g()}),r.addEventListener(`click`,()=>{i.hidden&&g()}),r.addEventListener(`input`,()=>{i.hidden&&g(),d(r.value)}),r.addEventListener(`keydown`,e=>{if(i.hidden){(e.key===`ArrowDown`||e.key===`ArrowUp`)&&(e.preventDefault(),g());return}e.key===`ArrowDown`?(e.preventDefault(),a.length&&(o=(o+1)%a.length,f())):e.key===`ArrowUp`?(e.preventDefault(),a.length&&(o=(o-1+a.length)%a.length,f())):e.key===`Enter`?(e.preventDefault(),o>=0&&p(a[o])):e.key===`Escape`&&(e.preventDefault(),_())}),i.addEventListener(`mousedown`,e=>e.preventDefault()),i.addEventListener(`click`,e=>{let t=e.target.closest(`.ssel-item[data-idx]`);t&&p(a[Number(t.dataset.idx)])}),new MutationObserver(()=>{l(),i.hidden||d(``)}).observe(e,{childList:!0}),l()}var Ze=`modulepreload`,Qe=function(e,t){return new URL(e,t).href},$e={},L=function(e,t,n){let r=Promise.resolve();if(t&&t.length>0){let e=document.getElementsByTagName(`link`),i=document.querySelector(`meta[property=csp-nonce]`),a=i?.nonce||i?.getAttribute(`nonce`);function o(e){return Promise.all(e.map(e=>Promise.resolve(e).then(e=>({status:`fulfilled`,value:e}),e=>({status:`rejected`,reason:e}))))}r=o(t.map(t=>{if(t=Qe(t,n),t in $e)return;$e[t]=!0;let r=t.endsWith(`.css`),i=r?`[rel="stylesheet"]`:``;if(n)for(let n=e.length-1;n>=0;n--){let i=e[n];if(i.href===t&&(!r||i.rel===`stylesheet`))return}else if(document.querySelector(`link[href="${t}"]${i}`))return;let o=document.createElement(`link`);if(o.rel=r?`stylesheet`:Ze,r||(o.as=`script`),o.crossOrigin=``,o.href=t,a&&o.setAttribute(`nonce`,a),document.head.appendChild(o),r)return new Promise((e,n)=>{o.addEventListener(`load`,e),o.addEventListener(`error`,()=>n(Error(`Unable to preload CSS for ${t}`)))})}))}function i(e){let t=new Event(`vite:preloadError`,{cancelable:!0});if(t.payload=e,window.dispatchEvent(t),!t.defaultPrevented)throw e}return r.then(t=>{for(let e of t||[])e.status===`rejected`&&i(e.reason);return e().catch(i)})},R={q:``,type:``,status:``,dept:``,loc:``,holder:``},et=[`Kivehető`,`Kiadva`,`Lefoglalva`,`Visszavétel folyamatban`,`Szerviz alatt`,`Elveszett`,`Selejtezve`],z=null,B=1;function tt(e){z===e?B*=-1:(z=e,B=1)}function V(e,t){return e==` `?`<th data-col="${e}" ></th>`:`<th data-col="${e}" style="cursor:pointer;user-select:none">${t} ${z===e?B===1?`↑`:`↓`:`<span style="opacity:.99">↕</span>`}</th>`}function nt(e,t){switch(t){case`lastModified`:return e.lastModified||``;case`assetTag`:return e.dev.asset_tag||``;case`typeName`:return(e.typeName||``)+` `+(e.dev.model||``);case`status`:return e.status||``;case`holder`:return e.holder?e.holder.full_name:``;case`location`:return String(e.locationId||``)+String(e.departmentId||``);default:return``}}function rt(e){let t=T(w(),`storekeeper`),n=new Set(x().map(e=>e.holder_id).filter(e=>e!=null)),r=t?x().map(F).filter(e=>(e.calibrationFlag===`overdue`||e.calibrationFlag===`soon`)&&e.status!==`Selejtezve`).sort((e,t)=>new Date(e.calibrationDue)-new Date(t.calibrationDue)).slice(0,6):[];e.innerHTML=`
    <div class="content">
      <div class="toolbar">
        <div class="search">
          <span class="ico">${j.search}</span>
          <input class="form-control" id="f-q" placeholder="Keresés: azonosító, modell, gyártó, sorozatszám…" value="${k(R.q)}" />
        </div>
        <div class="select-wrap" style="max-width:170px">
          <select class="form-select" id="f-type">
            <option value="">Minden típus</option>
            ${ae().map(e=>`<option value="${e.id}" ${String(e.id)===R.type?`selected`:``}>${k(e.type)}</option>`).join(``)}
          </select>
        </div>
        <div class="select-wrap" style="max-width:180px">
          <select class="form-select" id="f-status">
            <option value="">Minden státusz</option>
            ${et.map(e=>`<option value="${e}" ${e===R.status?`selected`:``}>${k(Ne(e))}</option>`).join(``)}
          </select>
        </div>
        <div class="select-wrap" style="max-width:180px">
          <select class="form-select" id="f_loc">
            <option value="">Minden helyszín</option>
            ${b().map(e=>`<option value="${e.id}" ${String(e.id)===R.loc?`selected`:``}>${k(e.address)}</option>`).join(``)}
          </select>
        </div>
        <div class="select-wrap" style="max-width:180px">
          <select class="form-select" id="f-dept">
            <option value="">Minden helyiség</option>
            ${te().map(e=>`<option value="${e.id}" ${String(e.id)===R.dept?`selected`:``}>${k(e.name)}</option>`).join(``)}
          </select>
        </div>
        <div class="select-wrap" style="max-width:180px">
          <select class="form-select" id="f-holder">
            <option value="">Minden birtokos</option>
            ${ee().filter(e=>n.has(e.id)).map(e=>`<option value="${e.id}" ${String(e.id)===R.holder?`selected`:``}>${k(e.full_name)}</option>`).join(``)}
          </select>
        </div>

        <button class="btn btn-reset-filters-custom" id="btn-reset-filters">Szűrők törlése</button>
        <button class="btn btn-outline" id="btn-scan">${j.qr} Beolvasás</button>
        ${t?`<button class="btn btn-primary" id="btn-new-device">${j.register} Új eszköz bevitele</button>`:``}
      </div>
      ${t?`
        <div class="panel" style="margin-bottom:16px">
          <div class="panel-head">Felülvizsgálandó eszközök</div>
          <div class="panel-body" style="padding:0">
            ${r.length?`
            <table class="grid">
              <tbody>
                ${r.map(e=>`
                  <tr data-dev="${e.dev.device_id}" style="cursor:pointer">
                    <td><span class="tag-mono">${k(e.dev.model)}</span><div class="cell-sub">${k(e.typeName)}</div></td>
                    <td>${D(e.status)}</td>
                    <td style="text-align:right">
                      <span class="attr-flag ${e.calibrationFlag}">${e.calibrationFlag===`overdue`?`Lejárt`:`Hamarosan`}</span>
                      <div class="cell-sub">${k(e.calibrationDue)}</div>
                    </td>
                  </tr>`).join(``)}
              </tbody>
            </table>`:`<div class="empty" style="padding:32px"><div>Nincs közelgő kalibráció.</div></div>`}
          </div>
        </div>`:``}
      <div id="inv-table"></div>
    </div>`;let a=e.querySelector(`#f-q`);a.addEventListener(`input`,()=>{R.q=a.value,H(e)}),e.querySelector(`#f-type`).addEventListener(`change`,t=>{R.type=t.target.value,H(e)}),e.querySelector(`#f-status`).addEventListener(`change`,t=>{R.status=t.target.value,H(e)}),e.querySelector(`#f_loc`).addEventListener(`change`,t=>{R.loc=t.target.value,H(e)}),e.querySelector(`#f-dept`).addEventListener(`change`,t=>{R.dept=t.target.value,H(e)}),e.querySelector(`#f-holder`).addEventListener(`change`,t=>{R.holder=t.target.value,H(e)});let o=e.querySelector(`#btn-new-device`);o&&o.addEventListener(`click`,()=>i(`/register`));let s=e.querySelector(`#btn-scan`);s&&s.addEventListener(`click`,()=>i(`/scan`));let c=e.querySelector(`#btn-reset-filters`);c&&c.addEventListener(`click`,()=>{R.q=``,R.type=``,R.status=``,R.loc=``,R.dept=``,R.holder=``,a.value=``,[`#f-type`,`#f-status`,`#f_loc`,`#f-dept`,`#f-holder`].forEach(t=>{let n=e.querySelector(t);n.value=``,Ye(n)}),H(e)}),e.querySelectorAll(`.panel [data-dev]`).forEach(e=>e.addEventListener(`click`,()=>i(`/device/`+e.dataset.dev))),I(e),H(e)}function H(e){let t=e.querySelector(`#inv-table`),n=x().map(F);T(w(),`storekeeper`)||(n=n.filter(e=>e.status===`Kivehető`));let r=R.q.trim().toLowerCase();if(r&&(n=n.filter(e=>[e.dev.asset_tag,e.dev.model,e.dev.manufacturer,e.dev.serial_number,e.typeName,ze(e.holderId),O(e.locationId,e.departmentId)].filter(Boolean).some(e=>e.toLowerCase().includes(r)))),R.type&&(n=n.filter(e=>String(e.dev.device_type_id)===R.type)),R.status&&(n=n.filter(e=>e.status===R.status)),R.loc&&(n=n.filter(e=>String(e.locationId)===R.loc)),R.dept&&(n=n.filter(e=>String(e.departmentId)===R.dept)),R.holder&&(n=n.filter(e=>String(e.holderId)===R.holder)),z&&(n=[...n].sort((e,t)=>B*nt(e,z).localeCompare(nt(t,z),`hu`,{sensitivity:`base`}))),!n.length){t.innerHTML=`<div class="table-wrap"><div class="empty"><div class="big">${j.search}</div><div>Nincs a szűrőnek megfelelő eszköz.</div></div></div>`;return}let a=[];for(let e=0;e<n.length;e+=25)a.push(n.slice(e,e+25));let o=e=>`
    <div class="table-wrap">
      <table class="grid">
        <thead><tr>
          ${V(`lastModified`,`Utoljára módosítva`)}${V(`assetTag`,`Leltári azonosító`)}${V(`typeName`,`Típus / modell`)}${V(`status`,`Státusz`)}
          ${V(`holder`,`Birtokos`)}${V(`location`,`Hely`)}${V(` `,` `)}<th></th>
        </tr></thead>
        <tbody>${e.map(it).join(``)}</tbody>
      </table>
    </div>`,s=a.map((e,t)=>`.inv-pager:has(#inv-p${t+1}:checked) .page-section[data-page="${t+1}"]{display:block}.inv-pager:has(#inv-p${t+1}:checked) label[for="inv-p${t+1}"]{background:var(--brand);color:#fff;border-color:var(--brand-dark)}`).join(``);t.innerHTML=`
    <div class="muted" style="font-size:.82rem;margin-bottom:10px">${n.length} eszköz</div>
    <div class="inv-pager pager-root">
      <style>${s}</style>
      ${a.map((e,t)=>`<input type="radio" name="inv-page" id="inv-p${t+1}" class="page-radio"${t===0?` checked`:``}>`).join(``)}
      ${a.map((e,t)=>`<div class="page-section" data-page="${t+1}">${o(e)}</div>`).join(``)}
      ${a.length>1?`<div class="pager-nav">${a.map((e,t)=>`<label for="inv-p${t+1}" class="pager-btn">${t+1}</label>`).join(``)}</div>`:``}
    </div>`,t.querySelectorAll(`tbody tr`).forEach(e=>e.addEventListener(`click`,()=>i(`/device/`+e.dataset.dev))),t.querySelectorAll(`th[data-col]`).forEach(t=>t.addEventListener(`click`,()=>{tt(t.dataset.col),H(e)})),t.querySelectorAll(`[data-act="qr-label"]`).forEach(e=>e.addEventListener(`click`,e=>{e.stopPropagation();let t=Number(e.currentTarget.closest(`[data-dev]`).dataset.dev);L(()=>import(`./qrLabel-4m1JF80t.js`).then(e=>e.printQrLabel(t)),[],import.meta.url)}))}function it(e){let t=e.holder?k(e.holder.full_name):`<span class="muted">— raktáron —</span>`,n=e.reservation?`<div class="cell-sub">Foglalta: ${k(e.reservedBy?.full_name||``)}</div>`:``;return`
    <tr data-dev="${e.dev.device_id}">
      <td><span class="tag-mono">${k(e.lastModified)||`—`}</span></td>
      <td><span class="tag-mono">${k(e.dev.asset_tag)}</span></td>
      <td>${k(e.typeName)}<div class="cell-sub">${k(e.dev.manufacturer)} ${k(e.dev.model)}</div></td>
      <td>${D(e.status)}${n}</td>
      <td>${t}</td>
      <td>${k(O(e.locationId,e.departmentId))}</td>
      <td style="text-align:right">${j.arrowRight}</td>
      <td><button class="btn btn-outline" data-act="qr-label">${j.printer} Nyomtatás</button></td>
    </tr>`}function U(e=null){return b().map(t=>`<option value="${t.id}" ${t.id===e?`selected`:``}>${k(t.address)}</option>`).join(``)}function at(e=null,t=null){return ee().filter(t=>t.id!==e).map(e=>`<option value="${e.id}" ${e.id===t?`selected`:``}>${k(e.full_name)}</option>`).join(``)}function ot(e=`Jó`){return`<select class="form-select" name="condition">${[`Jó`,`Kopott`,`Hibás`,`Ismeretlen`].map(t=>`<option ${t===e?`selected`:``}>${t}</option>`).join(``)}</select>`}function W(e,t=()=>!1){let n=e.querySelector(`[name=to_location]`),r=e.querySelector(`[name=to_dept]`),i=()=>{let e=te().filter(e=>e.locations_id===Number(n.value)),i=e.find(t)||e[0];r.innerHTML=e.length?e.map(e=>`<option value="${e.id}" ${i&&e.id===i.id?`selected`:``}>${k(e.name)}</option>`).join(``):`<option value="">— nincs részleg ezen a helyszínen —</option>`};n.addEventListener(`change`,i),i(),I(e)}function st(e){let t=S(e),n=T(w(),`storekeeper`),r=C();P({title:`Eszköz kivétele · <span class="tag-mono" style="margin-left:8px">${k(t.asset_tag)}</span>`,closeOnBackdrop:!1,bodyHTML:`
      ${n?`
      <div class="field">
        <label class="form-label">Kinek</label>
        <select class="form-select" name="to_user">${at(null,r.id)}</select>
        <div class="hint">Raktárosként más nevében is kiadhatsz eszközt.</div>
      </div>`:`
      <div class="alert-soft" style="margin-bottom:15px">Az eszközt <strong>magadnak</strong> veszed ki: ${k(r.full_name)}.</div>`}
      <div class="field">
        <label class="form-label">Hová (osztály / felhasználási hely)</label>
        <select class="form-select" name="to_location">${U()}</select>
        <select class="form-select" name="to_dept"></select>
      </div>
      <div class="field">
        <label class="form-label">Várható visszahozatal (opcionális)</label>
        <input type="date" class="form-control" name="ret" />
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés (opcionális)</label>
        <input type="text" class="form-control" name="notes" placeholder="pl. kihelyezés a Kardiológiára" />
      </div>`,confirmText:`Kivétel`,onMount:e=>W(e,e=>e.type!==`raktár`),onConfirm:async t=>{let i=n?Number(t.querySelector(`[name=to_user]`).value):r.id,a=Number(t.querySelector(`[name=to_location]`)?.value),o=Number(t.querySelector(`[name=to_dept]`).value)||null;if(re(o))return N(`Kivételkor használati helyet (nem raktárt) válassz — a raktár a készletet jelenti.`,`error`),!1;let s=t.querySelector(`[name=ret]`).value,c=t.querySelector(`[name=notes]`).value.trim()||null;await E({device_id:e,event_type:`check_out`,to_user_id:i,to_locations_id:a,to_departments_id:o,expected_return_date:s||null,notes:c}),N(`Eszköz kivéve.`,`success`)}})}function G(e){let t=S(e),n=w()===`user`;P({title:`Eszköz leadása · <span class="tag-mono" style="margin-left:8px">${k(t.asset_tag)}</span>`,closeOnBackdrop:!1,bodyHTML:`
      <div class="field">
        <label class="form-label">Hová — helyszín</label>
        <select class="form-select" name="to_location">${U()}</select>
      </div>
      <div class="field">
        <label class="form-label">Hová — raktár / részleg (opcionális)</label>
        <select class="form-select" name="to_dept"></select>
      </div>
      <div class="field">
        <label class="form-label">Állapot</label>
        ${ot(t.condition)}
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés (opcionális)</label>
        <input type="text" class="form-control" name="notes" placeholder="pl. minden tartozékkal" />
      </div>
      ${n?`<div class="alert-warn-soft">A leadás <strong>raktáros megerősítésére</strong> vár, mielőtt az eszköz ismét kiadhatóvá válik.</div>`:``}`,confirmText:`Leadás`,onMount:e=>W(e,e=>e.type===`raktár`),onConfirm:async t=>{let r=Number(t.querySelector(`[name=to_location]`)?.value),i=Number(t.querySelector(`[name=to_dept]`).value)||null,a=t.querySelector(`[name=condition]`).value;await E({device_id:e,event_type:`check_in`,to_locations_id:r,to_departments_id:i,condition_at_event:a,notes:t.querySelector(`[name=notes]`).value.trim()||null}),N(n?`Visszavétel folyamatban — raktáros megerősítésére vár.`:`Eszköz visszavéve.`,`success`)}})}function ct(e){let t=S(e),n=ue(e);P({title:`Eszköz átadása · <span class="tag-mono" style="margin-left:8px">${k(t.asset_tag)}</span>`,bodyHTML:`
      <div class="field">
        <label class="form-label">Kinek</label>
        <select class="form-select" name="to_user">${at(n.holder)}</select>
        <div class="hint">Az eszköz közvetlenül az új birtokoshoz kerül; a helye változatlan marad.</div>
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés (opcionális)</label>
        <input type="text" class="form-control" name="notes" />
      </div>`,confirmText:`Átadás`,onMount:e=>I(e),onConfirm:async t=>{let r=Number(t.querySelector(`[name=to_user]`).value),i=t.querySelector(`[name=notes]`).value.trim()||null;await E({device_id:e,event_type:`transfer`,to_user_id:r,to_locations_id:n.location,to_departments_id:n.department,notes:i}),N(`Eszköz átadva.`,`success`)}})}function lt(e){let t=S(e),n=ue(e);P({title:`Raktármozgatás · <span class="tag-mono" style="margin-left:8px">${k(t.asset_tag)}</span>`,bodyHTML:`
      <div class="field">
        <label class="form-label">Honnan</label>
        <input class="form-control" value="${k(O(n.location,n.department))}" disabled />
      </div>
      <div class="field">
        <label class="form-label">Hová — helyszín</label>
        <select class="form-select" name="to_location">${U(n.location)}</select>
      </div>
      <div class="field">
        <label class="form-label">Hová — részleg</label>
        <select class="form-select" name="to_dept"></select>
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés (opcionális)</label>
        <input type="text" class="form-control" name="notes" />
      </div>`,confirmText:`Mozgatás`,onMount:e=>W(e,e=>e.type===`raktár`),onConfirm:async t=>{let n=Number(t.querySelector(`[name=to_location]`)?.value),r=Number(t.querySelector(`[name=to_dept]`).value);if(!r)return N(`Ezen a helyszínen nincs választható részleg.`,`error`),!1;await E({device_id:e,event_type:`stock_transfer`,to_locations_id:n,to_departments_id:r,notes:t.querySelector(`[name=notes]`).value.trim()||null}),N(`Készlet áthelyezve.`,`success`)}})}async function ut(e){try{await _e(e),N(`Eszköz lefoglalva (3 napig).`,`success`)}catch(e){N(e.message,`error`)}}async function dt(e){try{await ve(e),N(`Foglalás lemondva.`,`success`)}catch(e){N(e.message,`error`)}}async function ft(e){try{await he(e),N(`Visszavétel megerősítve.`,`success`)}catch(e){N(e.message,`error`)}}function pt(e){P({title:`Visszavétel elutasítása`,bodyHTML:`
      <p class="muted" style="margin-top:0">Az eszköz nincs fizikailag a megadott helyen? Az elutasítással a birtoklás a felhasználónál marad.</p>
      <div class="field">
        <label class="form-label">Indok (kötelező)</label>
        <input type="text" class="form-control" name="reason" placeholder="pl. nincs a raktárban" />
      </div>`,confirmText:`Elutasítás`,confirmClass:`btn-danger`,onConfirm:async t=>{let n=t.querySelector(`[name=reason]`).value.trim();if(!n)return N(`Adj meg indokot.`,`error`),!1;await ge(e,n),N(`Visszavétel elutasítva.`,`success`)}})}function mt(e){P({title:`Szervizbe küldés`,bodyHTML:`
      <div class="field">
        <label class="form-label">Hibaleírás</label>
        <input class="form-control" name="notes" placeholder="pl. nem kapcsol be" />
      </div>
      <div class="field">
        <label class="form-label">Hová — helyszín</label>
        <select class="form-select" name="to_location">${U()}</select>
      </div>
      <div class="field">
        <label class="form-label">Hová — részleg</label>
        <select class="form-select" name="to_dept"></select>
      </div>`,confirmText:`Szervizbe`,onMount:e=>W(e,e=>e.type===`műhely`),onConfirm:async t=>{let n=Number(t.querySelector(`[name=to_location]`).value),r=Number(t.querySelector(`[name=to_dept]`).value);if(!r)return N(`Ezen a helyszínen nincs választható részleg.`,`error`),!1;await ye(e,n,r,t.querySelector(`[name=notes]`).value.trim()||null),N(`Szervizbe küldve.`,`success`)}})}function ht(e){P({title:`Szervizelve`,bodyHTML:`
      <div class="field">
        <label class="form-label">Helyszín</label>
        <select class="form-select" name="to_location">${U()}</select>
      </div>
      <div class="field">
        <label class="form-label">Részleg</label>
        <select class="form-select" name="to_dept"></select>
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés</label>
        <input class="form-control" name="notes" placeholder="pl. javítva" />
      </div>`,confirmText:`Visszahelyezés`,onMount:e=>W(e),onConfirm:async t=>{let n=Number(t.querySelector(`[name=to_location]`)?.value),r=Number(t.querySelector(`[name=to_dept]`).value);if(!r)return N(`Ezen a helyszínen nincs választható részleg.`,`error`),!1;await be(e,n,r,t.querySelector(`[name=notes]`).value.trim()||null),N(`Javítva visszahelyezve.`,`success`)}})}function gt(e){P({title:`Elveszettnek jelölés`,bodyHTML:`<div class="field"><label class="form-label">Megjegyzés</label><input class="form-control" name="notes" placeholder="pl. nem található 2 hete" /></div>`,confirmText:`Elveszett`,confirmClass:`btn-danger`,onConfirm:async t=>{await xe(e,t.querySelector(`[name=notes]`).value.trim()||null),N(`Elveszettnek jelölve.`,`success`)}})}function _t(e){P({title:`Találtnak jelölés`,bodyHTML:`<div class="field">
        <label class="form-label">Helyszín</label>
        <select class="form-select" name="to_location">${U()}</select>
      </div>
      <div class="field">
        <label class="form-label">Részleg</label>
        <select class="form-select" name="to_dept"></select>
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés</label>
        <input class="form-control" name="notes" placeholder="pl. javítva" />
      </div>`,confirmText:`Visszahelyezés`,onMount:e=>W(e),onConfirm:async t=>{let n=Number(t.querySelector(`[name=to_location]`)?.value),r=Number(t.querySelector(`[name=to_dept]`).value);if(!r)return N(`Ezen a helyszínen nincs választható részleg.`,`error`),!1;await Se(e,n,r,t.querySelector(`[name=notes]`).value.trim()||null),N(`Találtnak jelölve.`,`success`)}})}function vt(e){P({title:`Eszköz selejtezése`,bodyHTML:`<p class="muted" style="margin-top:0">Lágy törlés: az előzmény megmarad, az eszköz „Selejtezve" státuszba kerül.</p>
      <div class="field"><label class="form-label">Indok</label><input class="form-control" name="reason" placeholder="pl. nem javítható" /></div>`,confirmText:`Selejtezés`,confirmClass:`btn-danger`,onConfirm:async t=>{await Te(e,t.querySelector(`[name=reason]`).value.trim()||null),N(`Eszköz selejtezve.`,`success`)}})}function yt(e,{id:t}){let n=S(Number(t));if(!n){e.innerHTML=`<div class="content"><div class="empty"><div class="big">${j.warning}</div><div>Eszköz nem található.</div><div style="margin-top:14px"><button class="btn btn-outline" id="back">${j.back} Vissza a listához</button></div></div></div>`,e.querySelector(`#back`).addEventListener(`click`,()=>i(`/inventory`));return}let r=F(n),a=C(),o=w(),s=T(o,`storekeeper`),c=ce(n.device_type_id);me(n.device_id);let l=pe(n.device_id),u=fe(n.device_id);e.innerHTML=`
    <div class="content">
      <button class="btn btn-ghost btn-sm" id="back" style="margin-bottom:14px">${j.back} Eszközök</button>

      <div class="detail-head">
        <div class="titleblock">
          <h2>${k(r.typeName)} — ${k(n.manufacturer)} ${k(n.model)}</h2>
          <div class="pill-info"><span class="tag-mono" style="font-size:.95rem">${k(n.asset_tag)}</span>${D(r.status)}</div>
        </div>
        <div class="actions" id="actions"></div>
      </div>

      ${bt(r,s)}

      <div class="detail-grid">
        <div style="display:flex; flex-direction:column; gap:18px">
          <div class="panel">
            <div class="panel-head">Általános adatok</div>
            <div class="panel-body">
              <dl class="kv">
                <dt>Birtokos</dt><dd>${r.holder?k(r.holder.full_name):`<span class="muted">— raktáron —</span>`}</dd>
                <dt>Hely</dt><dd>${k(O(r.locationId,r.departmentId))}</dd>
                <dt>Státusz óta</dt><dd>${r.since?Ve(r.since):`—`}</dd>
                <dt>Állapot</dt><dd>${k(n.condition||`—`)}</dd>
                <dt>Sorozatszám</dt><dd>${k(n.serial_number||`—`)}</dd>
                <dt>Gyártó / modell</dt><dd>${k(n.manufacturer||`—`)} ${k(n.model||``)}</dd>
                ${n.notes?`<dt>Megjegyzés</dt><dd>${k(n.notes)}</dd>`:``}
              </dl>
            </div>
          </div>

          <div class="panel">
            <div class="panel-head">${k(r.typeName)} — típusspecifikus adatok</div>
            <div class="panel-body">
              ${c.length?`<dl class="kv">${c.map(e=>xt(e,n.attrs?.[e.attribute_key])).join(``)}</dl>`:`<div class="muted">Nincs típusattribútum.</div>`}
            </div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head">Birtoklási előzmény</div>
          <div class="panel-body">
            ${l?St(u):`<div class="muted">Előzmény betöltése…</div>`}
          </div>
        </div>
      </div>
    </div>`,e.querySelector(`#back`).addEventListener(`click`,()=>i(`/inventory`)),wt(e.querySelector(`#actions`),r,o,s,a),e.querySelectorAll(`[data-confirm-ev]`).forEach(e=>e.addEventListener(`click`,()=>ft(Number(e.dataset.confirmEv)))),e.querySelectorAll(`[data-reject-ev]`).forEach(e=>e.addEventListener(`click`,()=>pt(Number(e.dataset.rejectEv))))}function bt(e,t){if(e.pending){let n=y(e.pending.actor_user_id);return`<div class="alert-warn-soft" style="margin-bottom:18px; display:flex; align-items:center; gap:14px; flex-wrap:wrap">
      <span>${j.pending}</span>
      <span><strong>Visszavétel megerősítésre vár.</strong> Leadta: ${k(n?.full_name||`—`)}, ide: ${k(O(e.pending.to_locations_id,e.pending.to_departments_id))}.</span>
      ${t?`<span style="margin-left:auto; display:flex; gap:8px">
        <button class="btn btn-success btn-sm" data-confirm-ev="${e.pending.event_id}">${j.check} Megerősít</button>
        <button class="btn btn-danger btn-sm" data-reject-ev="${e.pending.event_id}">${j.x} Elutasít</button>
      </span>`:``}
    </div>`}return e.reservation?`<div class="alert-soft" style="margin-bottom:18px; display:flex; align-items:center; gap:12px">
      <span>${j.bookmark}</span>
      <span><strong>Lefoglalva.</strong> Foglalta: ${k(e.reservedBy?.full_name||`—`)} · lejár ${He(e.reservation.expires_at)}.</span>
    </div>`:``}function xt(e,t){let n=``;if(e.attribute_key===`calibration_due`){let e=We(t);e===`overdue`?n=`<span class="attr-flag overdue">Lejárt</span>`:e===`soon`&&(n=`<span class="attr-flag soon">Hamarosan</span>`)}return`<dt>${k(e.label)}</dt><dd>${k(Ue(e,t))}${n}</dd>`}function St(e){if(!e.length)return`<div class="muted">Nincs előzmény.</div>`;let t=[];for(let n=0;n<e.length;n+=8)t.push(e.slice(n,n+8));return t.length===1?`<div class="timeline">${e.map(Ct).join(``)}</div>`:`
    <div class="hist-pager pager-root">
      <style>${t.map((e,t)=>`.hist-pager:has(#hist-p${t+1}:checked) .page-section[data-page="${t+1}"]{display:block}.hist-pager:has(#hist-p${t+1}:checked) label[for="hist-p${t+1}"]{background:var(--brand);color:#fff;border-color:var(--brand-dark)}`).join(``)}</style>
      ${t.map((e,t)=>`<input type="radio" name="hist-page" id="hist-p${t+1}" class="page-radio"${t===0?` checked`:``}>`).join(``)}
      ${t.map((e,t)=>`<div class="page-section" data-page="${t+1}"><div class="timeline">${e.map(Ct).join(``)}</div></div>`).join(``)}
      <div class="pager-nav">${t.map((e,t)=>`<label for="hist-p${t+1}" class="pager-btn">${t+1}</label>`).join(``)}</div>
    </div>`}function Ct(e){let t=y(e.actor_user_id),n=e.confirmation_status===`pending`?`pending`:e.confirmation_status===`rejected`?`rejected`:``,r=e.event_type===`mark_lost`||e.event_type===`mark_found`,i=[];!r&&e.from_user_id?i.push(y(e.from_user_id)?.full_name):!r&&(e.from_departments_id||e.from_locations_id)&&i.push(O(e.from_locations_id,e.from_departments_id));let a=e.to_user_id?y(e.to_user_id)?.full_name:O(e.to_locations_id,e.to_departments_id),o=e.confirmation_status===`confirmed`?``:` · <span class="muted">${Re(e.confirmation_status)}</span>`;return`
    <div class="tl-item">
      <span class="tl-dot ${n}"></span>
      <div class="tl-head">${k(Ie(e.event_type))}${o}</div>
      <div class="tl-meta">${i.length?k(i.join(`, `))+` → `:``}${k(a||`—`)}</div>
      <div class="tl-meta">Végrehajtó: ${k(t?.full_name||`—`)}${e.notes?` · `+k(e.notes):``}</div>
      <div class="tl-time">${Ve(e.event_timestamp)}</div>
    </div>`}function wt(e,t,n,r,i){let a=t.dev,o=[],s=t.holderId===i.id,c=t.reservation&&t.reservation.reserved_by===i.id,l=t.reservation&&t.reservation.reserved_by!==i.id,u=a.status===`Selejtezve`,d=a.status===`Elveszett`;!u&&!d?(t.isFree&&!l&&(o.push(`<button class="btn btn-primary" data-act="checkout">${j.arrowRight} Kivétel</button>`),t.reservation||o.push(`<button class="btn btn-outline" data-act="reserve">${j.bookmark} Foglalás</button>`)),c&&(o.push(`<button class="btn btn-primary" data-act="checkout">${j.arrowRight} Kivétel</button>`),o.push(`<button class="btn btn-outline" data-act="cancel-resv">Foglalás lemondása</button>`)),l&&r&&(o.push(`<button class="btn btn-primary" data-act="checkout">${j.arrowRight} Kivétel (felülírás)</button>`),o.push(`<button class="btn btn-outline" data-act="cancel-resv">Foglalás lemondása</button>`)),s&&!t.pending&&(o.push(`<button class="btn btn-primary" data-act="checkin">${j.back} Leadás</button>`),o.push(`<button class="btn btn-outline" data-act="transfer">Átadás</button>`)),r&&t.holderId&&!s&&(o.push(`<button class="btn btn-outline" data-act="checkin">${j.back} Kényszerített visszavétel</button>`),o.push(`<button class="btn btn-outline" data-act="transfer">Átadás</button>`)),r&&t.isFree&&o.push(`<button class="btn btn-outline" data-act="stock">${j.building} Raktármozgatás</button>`),r&&t.inRepair&&(o.push(`<button class="btn btn-outline" data-act="return-from-repair">${j.back} Visszahelyezés</button>`),o.push(`<button class="btn btn-outline" data-act="edit">${j.edit} Szerkesztés</button>`)),r&&!t.inRepair&&(o.push(`<button class="btn btn-outline" data-act="repair">${j.repair} Szervizbe</button>`),o.push(`<button class="btn btn-outline" data-act="edit">${j.edit} Szerkesztés</button>`),o.push(`<button class="btn btn-danger" data-act="more">⋯</button>`))):r&&!u&&(t.isLost&&o.push(`<button class="btn btn-primary" data-act="mark-found">${j.back} Visszahelyezés</button>`),o.push(`<button class="btn btn-outline" data-act="edit">${j.edit} Szerkesztés</button>`)),o.push(`<button class="btn btn-ghost btn-sm" data-act="qr-label" style="margin-left:auto">${j.qr} QR Címke</button>`),e.innerHTML=o.join(``)||`<span class="muted" style="font-size:.85rem">Nincs elérhető művelet ehhez az állapothoz.</span>`;let f=a.device_id,p={checkout:()=>st(f),checkin:()=>G(f),transfer:()=>ct(f),reserve:()=>ut(f),"cancel-resv":()=>dt(f),stock:()=>lt(f),repair:()=>mt(f),"return-from-repair":()=>ht(f),"mark-found":()=>_t(f),edit:()=>L(()=>import(`./register_device-NJN_dG1n.js`).then(e=>e.dlgEditDevice(f)),[],import.meta.url),more:()=>Tt(e,f),"qr-label":()=>L(()=>import(`./qrLabel-4m1JF80t.js`).then(e=>e.dlgQrLabel(f)),[],import.meta.url)};e.querySelectorAll(`[data-act]`).forEach(e=>e.addEventListener(`click`,t=>{t.stopPropagation(),p[e.dataset.act]?.()}))}function Tt(e,t){let n=document.createElement(`div`);n.style.cssText=`position:absolute; margin-top:6px; background:#fff; border:1px solid var(--line); border-radius:10px; box-shadow:var(--shadow); padding:6px; z-index:50; display:flex; flex-direction:column; gap:2px`,n.innerHTML=`
    <button class="btn btn-ghost btn-sm" data-m="lost" style="justify-content:flex-start">${j.warning} Elveszettnek jelöl</button>
    <button class="btn btn-ghost btn-sm" data-m="retire" style="justify-content:flex-start; color:#c0392b">Selejtezés</button>`,e.appendChild(n),n.querySelector(`[data-m=lost]`).addEventListener(`click`,()=>{n.remove(),gt(t)}),n.querySelector(`[data-m=retire]`).addEventListener(`click`,()=>{n.remove(),vt(t)}),setTimeout(()=>document.addEventListener(`click`,function e(){n.remove(),document.removeEventListener(`click`,e)}),0)}function Et(e){let t=C(),n=x().map(F),r=n.filter(e=>e.holderId===t.id),a=n.filter(e=>e.reservation&&e.reservation.reserved_by===t.id);e.innerHTML=`
    <div class="content">
      <h3 class="section-title">Eszközök a birtokomban</h3>
      ${r.length?`
      <div class="table-wrap" style="margin-bottom:26px">
        <table class="grid">
          <thead><tr><th>Kivétel időpontja</th><th>Típus / modell</th><th>Hely</th><th>Státusz</th><th style="text-align:right"> </th></tr></thead>
          <tbody>
            ${r.map(e=>`
              <tr data-dev="${e.dev.device_id}">
                <td><span class="tag-mono">${k(e.lastCheckout?new Date(e.lastCheckout.event_timestamp).toISOString().slice(0,10):null)}</span></td>
                <td>${k(e.typeName)}<div class="cell-sub">${k(e.dev.manufacturer)} ${k(e.dev.model)}</div></td>
                <td>${k(O(e.locationId,e.departmentId))}</td>
                <td>${D(e.status)}</td>
                <td style="text-align:right">
                  <div class="row-actions" style="justify-content:flex-end">
                    ${e.status===`Kiadva`?`<button class="btn btn-primary btn-sm" data-act="checkin" data-id="${e.dev.device_id}">Leadás</button>
                    <button class="btn btn-outline btn-sm" data-act="transfer" data-id="${e.dev.device_id}">Átadás</button>`:``}
                  </div>
                </td>
              </tr>`).join(``)}
          </tbody>
        </table>
      </div>`:`<div class="table-wrap" style="margin-bottom:26px"><div class="empty"><div class="big">${j.my}</div><div>Jelenleg nincs nálad eszköz.</div><div style="margin-top:12px"><button class="btn btn-outline" id="browse">Eszközök böngészése</button></div></div></div>`}

      <h3 class="section-title">Foglalásaim</h3>
      ${a.length?`
      <div class="table-wrap">
        <table class="grid">
          <thead><tr><th>Foglalás időpontja</th><th>Típus / modell</th><th>Lejár</th><th style="text-align:right"> </th></tr></thead>
          <tbody>
            ${a.map(e=>`
              <tr data-dev="${e.dev.device_id}">
                <td><span class="tag-mono">${k(e.lastReserved?new Date(e.lastReserved.event_timestamp).toISOString().slice(0,10):null)}</span></td>
                <td>${k(e.typeName)}<div class="cell-sub">${k(e.dev.manufacturer)} ${k(e.dev.model)}</div></td>
                <td>${He(e.reservation.expires_at)}</td>
                <td style="text-align:right">
                  <div class="row-actions" style="justify-content:flex-end">
                    <button class="btn btn-primary btn-sm" data-act="checkout" data-id="${e.dev.device_id}">Kivétel</button>
                    <button class="btn btn-outline btn-sm" data-act="cancel" data-id="${e.dev.device_id}">Lemondás</button>
                  </div>
                </td>
              </tr>`).join(``)}
          </tbody>
        </table>
      </div>`:`<div class="muted" style="font-size:.9rem">Nincs aktív foglalásod.</div>`}
    </div>`,e.querySelectorAll(`tbody tr`).forEach(e=>e.addEventListener(`click`,()=>i(`/device/`+e.dataset.dev))),e.querySelectorAll(`[data-act]`).forEach(e=>e.addEventListener(`click`,t=>{t.stopPropagation();let n=Number(e.dataset.id);({checkin:G,transfer:ct,checkout:st,cancel:dt})[e.dataset.act]?.(n)})),e.querySelector(`#browse`)?.addEventListener(`click`,()=>i(`/inventory`))}var K=null,q=1;function Dt(e){K===e?q*=-1:(K=e,q=1)}function J(e,t){return`<th data-col="${e}" style="cursor:pointer;user-select:none">${t} ${K===e?q===1?`↑`:`↓`:`<span style="opacity:.99">↕</span>`}</th>`}function Ot(e,t){let n=S(e.device_id),r=oe(n?.device_type_id),i=y(e.actor_user_id);switch(t){case`typeName`:return(r?.type||``)+` `+(n?.model||``);case`submitter`:return i?.full_name||``;case`to_location`:return String(e.to_locations_id||``)+String(e.to_departments_id||``);case`event_timestamp`:return e.event_timestamp instanceof Date?e.event_timestamp.toISOString():String(e.event_timestamp||``);case`condition_at_event`:return e.condition_at_event||``;default:return``}}function kt(e){e.innerHTML=`
    <div class="content">
      <h3 class="section-title">Ellenőrzésre váró visszavételek</h3>
      <div class="alert-soft" style="margin-bottom:16px">A felhasználói leadások itt várnak fizikai ellenőrzésre. Erősítsd meg, ha az eszköz valóban a megadott helyen van; utasítsd el, ha nincs ott — ekkor a birtoklás a felhasználónál marad.</div>
      <div id="pending-table"></div>
    </div>`,At(e)}function At(e){let t=e.querySelector(`#pending-table`),n=de();K&&(n=[...n].sort((e,t)=>q*Ot(e,K).localeCompare(Ot(t,K),`hu`,{sensitivity:`base`}))),t.innerHTML=n.length?`
    <div class="table-wrap">
      <table class="grid">
        <thead><tr>
          ${J(`typeName`,`Típus / modell`)}
          ${J(`submitter`,`Leadta`)}
          ${J(`to_location`,`Helyiség`)}
          ${J(`event_timestamp`,`Leadás időpontja`)}
          ${J(`condition_at_event`,`Állapot`)}
          <th style="text-align:right">Döntés</th>
        </tr></thead>
        <tbody>
          ${n.map(jt).join(``)}
        </tbody>
      </table>
    </div>`:`<div class="table-wrap"><div class="empty"><div class="big">${j.check}</div><div>Nincs ellenőrzésre váró visszavétel.</div></div></div>`,t.querySelectorAll(`[data-dev]`).forEach(e=>e.addEventListener(`click`,t=>{t.target.closest(`button`)||i(`/device/`+e.dataset.dev)})),t.querySelectorAll(`[data-confirm]`).forEach(e=>e.addEventListener(`click`,t=>{t.stopPropagation(),ft(Number(e.dataset.confirm))})),t.querySelectorAll(`[data-reject]`).forEach(e=>e.addEventListener(`click`,t=>{t.stopPropagation(),pt(Number(e.dataset.reject))})),t.querySelectorAll(`th[data-col]`).forEach(t=>t.addEventListener(`click`,()=>{Dt(t.dataset.col),At(e)}))}function jt(e){let t=S(e.device_id),n=oe(t?.device_type_id),r=y(e.actor_user_id);return`
    <tr data-dev="${e.device_id}">
      <td><span class="tag-mono"></span>${k(n?.type||``)}<div class="cell-sub">${k(t?.manufacturer||``)} · ${k(t?.model||``)}</div></td>
      <td>${k(r?.full_name||`—`)}</td>
      <td>${k(O(e.to_locations_id,e.to_departments_id))}</td>
      <td>${Ve(e.event_timestamp)}</td>
      <td>${k(e.condition_at_event||`—`)}</td>
      <td style="text-align:right">
        <div class="row-actions" style="justify-content:flex-end">
          <button class="btn btn-success btn-sm" data-confirm="${e.event_id}">${j.check} Megerősít</button>
          <button class="btn btn-danger btn-sm" data-reject="${e.event_id}">${j.x} Elutasít</button>
        </div>
      </td>
    </tr>`}function Mt(e,t){let n=t??``,r=e.is_required?`<span style="color:#c0392b">*</span>`:``,i=`<label class="form-label">${k(e.label)} ${r}</label>`,a;if(e.data_type===`enum`){let t=(e.options||``).split(`,`).map(e=>e.trim());a=`<select class="form-select" data-attr="${e.attribute_key}">
      <option value="">— válassz —</option>
      ${t.map(e=>`<option ${e===n?`selected`:``}>${k(e)}</option>`).join(``)}
    </select>`}else a=e.data_type===`boolean`?`<select class="form-select" data-attr="${e.attribute_key}">
      <option value="" ${n===``?`selected`:``}>—</option>
      <option value="true" ${n===!0||n===`true`?`selected`:``}>Igen</option>
      <option value="false" ${n===!1||n===`false`?`selected`:``}>Nem</option>
    </select>`:e.data_type===`date`?`<input type="date" class="form-control" data-attr="${e.attribute_key}" value="${k(n)}" />`:e.data_type===`integer`||e.data_type===`decimal`?`<input type="number" class="form-control" data-attr="${e.attribute_key}" value="${k(n)}" ${e.data_type===`integer`?`step="1"`:`step="any"`} />`:`<input type="text" class="form-control" data-attr="${e.attribute_key}" value="${k(n)}" />`;return`<div class="field" data-type="${e.data_type}" data-required="${e.is_required}">${i}${a}</div>`}function Nt(e){let t={},n=null;return e.querySelectorAll(`[data-attr]`).forEach(e=>{let r=e.dataset.attr,i=e.closest(`.field`),a=i.dataset.type,o=i.dataset.required===`true`,s=e.value.trim();if(s===``){o&&(n||=`Kötelező mező hiányzik: ${i.querySelector(`.form-label`).textContent.replace(`*`,``).trim()}`);return}a===`integer`||a===`decimal`?t[r]=Number(s):a===`boolean`?t[r]=s===`true`:t[r]=s}),{attrs:t,error:n}}function Pt(e){if(!T(w(),`storekeeper`)){e.innerHTML=`<div class="content"><div class="empty"><div class="big">${j.warning}</div><div>Új eszköz regisztrálásához raktáros vagy IT-admin szerepkör kell.</div></div></div>`;return}let t=ae(),n=b(),r=te();e.innerHTML=`
    <div class="content" style="max-width:760px">
      <button class="btn btn-ghost btn-sm" id="back" style="margin-bottom:14px">${j.back} Vissza</button>
      <div class="panel">
        <div class="panel-head">Új eszköz regisztrálása</div>
        <div class="panel-body">
          <div class="field">
            <label class="form-label">Eszköztípus *</label>
            <select class="form-select" id="r-type">
              <option value="">— válassz típust —</option>
              ${t.map(e=>`<option value="${e.id}">${k(e.type)}</option>`).join(``)}
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
                <select class="form-select" id="r-loc">${n.map(e=>`<option value="${e.id}">${k(e.address)}</option>`).join(``)}</select>
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
              <button class="btn btn-primary" id="r-save">${j.register} Eszköz létrehozása</button>
            </div>
          </div>
        </div>
      </div>
    </div>`,e.querySelector(`#back`).addEventListener(`click`,()=>i(`/inventory`)),e.querySelector(`#r-cancel`).addEventListener(`click`,()=>i(`/inventory`));let a=e.querySelector(`#r-type`),o=e.querySelector(`#r-common`),s=e.querySelector(`#r-attrs`),c=e.querySelector(`#r-loc`),l=e.querySelector(`#r-dept`);function u(){let e=Number(c.value),t=r.filter(t=>t.locations_id===e);l.innerHTML=t.length?t.map(e=>`<option value="${e.id}" ${e.type===`raktár`?`selected`:``}>${k(e.name)}</option>`).join(``):`<option value="">— nincs részleg ezen a helyszínen —</option>`}c.addEventListener(`change`,u),u(),a.addEventListener(`change`,()=>{let e=Number(a.value);if(!e){o.style.display=`none`;return}o.style.display=`block`,s.innerHTML=ce(e).map(e=>Mt(e,``)).join(``)||`<div class="muted">Nincs típusattribútum.</div>`,I(s)}),I(e);let d=e.querySelector(`#r-save`);d.addEventListener(`click`,async()=>{if(d.disabled)return;let t=Number(a.value),n=e.querySelector(`#r-tag`).value.trim();if(!n){N(`Adj meg leltári azonosítót.`,`error`);return}let{attrs:r,error:o}=Nt(s);if(o){N(o,`error`);return}d.disabled=!0;try{let a=await Ce({device_type_id:t,asset_tag:n,serial_number:e.querySelector(`#r-serial`).value.trim(),manufacturer:e.querySelector(`#r-manu`).value.trim(),model:e.querySelector(`#r-model`).value.trim(),condition:e.querySelector(`#r-cond`).value,notes:e.querySelector(`#r-notes`).value.trim(),initial_location:Number(e.querySelector(`#r-loc`).value),initial_department:e.querySelector(`#r-dept`).value===``?null:Number(e.querySelector(`#r-dept`).value),attrs:r});N(`Eszköz létrehozva.`,`success`),i(`/device/`+a.device_id)}catch(e){N(e.message,`error`),d.disabled=!1}})}function Ft(e){let t=S(e),n=ce(t.device_type_id);P({title:`Eszköz szerkesztése · <span class="tag-mono" style="margin-left:8px">${k(t.asset_tag)}</span>`,wide:!0,bodyHTML:`
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px">
        <div class="field"><label class="form-label">Gyártó</label><input class="form-control" id="e-manu" value="${k(t.manufacturer||``)}" /></div>
        <div class="field"><label class="form-label">Modell</label><input class="form-control" id="e-model" value="${k(t.model||``)}" /></div>
        <div class="field"><label class="form-label">Sorozatszám</label><input class="form-control" id="e-serial" value="${k(t.serial_number||``)}" /></div>
        <div class="field"><label class="form-label">Állapot</label>
          <select class="form-select" id="e-cond">${[`Jó`,`Kopott`,`Hibás`,`Ismeretlen`].map(e=>`<option ${e===t.condition?`selected`:``}>${e}</option>`).join(``)}</select>
        </div>
      </div>
      <div class="field"><label class="form-label">Megjegyzés</label><input class="form-control" id="e-notes" value="${k(t.notes||``)}" /></div>
      <div class="divider"></div>
      <div class="form-label" style="margin-bottom:10px; font-size:.9rem; color:var(--ink)">Típusspecifikus adatok</div>
      <div id="e-attrs" style="display:grid; grid-template-columns:1fr 1fr; gap:14px">
        ${n.map(e=>Mt(e,t.attrs?.[e.attribute_key])).join(``)||`<div class="muted">Nincs típusattribútum.</div>`}
      </div>`,confirmText:`Mentés`,onMount:e=>I(e),onConfirm:async t=>{let{attrs:n,error:r}=Nt(t.querySelector(`#e-attrs`));if(r)return N(r,`error`),!1;await we(e,{manufacturer:t.querySelector(`#e-manu`).value.trim(),model:t.querySelector(`#e-model`).value.trim(),serial_number:t.querySelector(`#e-serial`).value.trim(),condition:t.querySelector(`#e-cond`).value,notes:t.querySelector(`#e-notes`).value.trim(),attrs:n}),N(`Eszköz frissítve.`,`success`)}})}function It(e){if(!T(w(),`storekeeper`)){e.innerHTML=`<div class="content"><div class="empty"><div class="big">${j.warning}</div><div>Ehhez raktáros vagy IT-admin szerepkör kell.</div></div></div>`;return}let t=ae(),n=b();e.innerHTML=`
    <div class="content" style="max-width:760px">
      <button class="btn btn-ghost btn-sm" id="back" style="margin-bottom:14px">${j.back} Vissza</button>
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
            ${X(`Helyszín mentése`)}
          </div>

          <div id="form-department" style="display:none">
            <div class="divider"></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px">
              <div class="field">
                <label class="form-label">Helyszín *</label>
                <select class="form-select" id="dept-loc">
                  ${n.map(e=>`<option value="${e.id}">${k(e.address)}</option>`).join(``)}
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
            ${X(`Részleg mentése`)}
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
            ${X(`Eszköztípus mentése`)}
          </div>

          <div id="form-attr_general" style="display:none">
            <div class="divider"></div>
            ${Lt(`ag`)}
            ${X(`Attribútum mentése`)}
          </div>

          <div id="form-attr_type" style="display:none">
            <div class="divider"></div>
            <div class="field">
              <label class="form-label">Eszköztípus *</label>
              <select class="form-select" id="attr-type-sel">
                ${t.map(e=>`<option value="${e.id}">${k(e.type)}</option>`).join(``)}
              </select>
            </div>
            ${Lt(`at`)}
            ${X(`Attribútum mentése`)}
          </div>
        </div>
      </div>
    </div>`,e.querySelector(`#back`).addEventListener(`click`,()=>i(`/`)),I(e);let r=e.querySelector(`#rd-cat`),a=[`location`,`department`,`device_type`,`attr_general`,`attr_type`];r.addEventListener(`change`,()=>{a.forEach(t=>{e.querySelector(`#form-${t}`).style.display=`none`}),r.value&&(e.querySelector(`#form-${r.value}`).style.display=`block`)}),[`ag`,`at`].forEach(t=>{let n=e.querySelector(`#${t}-data-type`),r=e.querySelector(`#${t}-options-row`);n.addEventListener(`change`,()=>{r.style.display=n.value===`enum`?`block`:`none`})});let o=e.querySelector(`#form-location .btn-primary`);o.addEventListener(`click`,()=>Y(o,async()=>{let t=e.querySelector(`#loc-address`).value.trim();if(!t){N(`Add meg a helyszín címét.`,`error`);return}try{await Ee({address:t}),N(`Helyszín hozzáadva.`,`success`),e.querySelector(`#loc-address`).value=``}catch(e){N(e.message,`error`)}}));let s=e.querySelector(`#form-department .btn-primary`);s.addEventListener(`click`,()=>Y(s,async()=>{let t=Number(e.querySelector(`#dept-loc`).value),n=e.querySelector(`#dept-name`).value.trim();if(!t){N(`Nincs választható helyszín — előbb adj meg egyet.`,`error`);return}if(!n){N(`Add meg a részleg nevét.`,`error`);return}try{await De({locations_id:t,name:n,type:e.querySelector(`#dept-type`).value}),N(`Részleg hozzáadva.`,`success`),e.querySelector(`#dept-name`).value=``}catch(e){N(e.message,`error`)}}));let c=e.querySelector(`#form-device_type .btn-primary`);c.addEventListener(`click`,()=>Y(c,async()=>{let t=e.querySelector(`#dtype-name`).value.trim(),n=e.querySelector(`#dtype-desc`).value.trim();if(!t){N(`Add meg az eszköztípus nevét.`,`error`);return}try{await Oe({type:t,description:n}),N(`Eszköztípus hozzáadva.`,`success`),e.querySelector(`#dtype-name`).value=``,e.querySelector(`#dtype-desc`).value=``}catch(e){N(e.message,`error`)}})),[[`ag`,null],[`at`,`attr-type-sel`]].forEach(([t,n])=>{let r=e.querySelector(`#form-${t===`ag`?`attr_general`:`attr_type`} .btn-primary`);r.addEventListener(`click`,()=>Y(r,async()=>{let r=n?Number(e.querySelector(`#${n}`).value):null,i=e.querySelector(`#${t}-key`).value.trim(),a=e.querySelector(`#${t}-label`).value.trim();if(n&&!r){N(`Nincs választható eszköztípus — előbb adj meg egyet.`,`error`);return}if(!i){N(`Add meg az attribútum kulcsát.`,`error`);return}if(!a){N(`Add meg az attribútum feliratát.`,`error`);return}try{await ke({device_type_id:r,attribute_key:i,label:a,data_type:e.querySelector(`#${t}-data-type`).value,is_required:e.querySelector(`#${t}-required`).value===`true`,options:e.querySelector(`#${t}-options`).value.trim(),sort_order:Number(e.querySelector(`#${t}-sort`).value)||0}),N(`Attribútum hozzáadva.`,`success`),e.querySelector(`#${t}-key`).value=``,e.querySelector(`#${t}-label`).value=``,e.querySelector(`#${t}-options`).value=``,e.querySelector(`#${t}-sort`).value=`0`}catch(e){N(e.message,`error`)}}))})}async function Y(e,t){if(!e.disabled){e.disabled=!0;try{await t()}finally{e.disabled=!1}}}function X(e){return`<div style="display:flex; gap:10px; justify-content:flex-end; margin-top:14px">
    <button class="btn btn-primary">${j.register} ${k(e)}</button>
  </div>`}function Lt(e){return`
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
    </div>`}function Rt(e){let t=e.querySelector(`#scan-input`);t.focus(),t.addEventListener(`keydown`,e=>{if(e.key!==`Enter`)return;e.preventDefault();let n=t.value.trim();t.value=``,n&&zt(n),t.focus()})}function zt(e){let t=se(e);if(!t)return N(`Ismeretlen azonosító: ${k(e)}`,`error`);let n=F(t),r=C(),i=T(w(),`storekeeper`);if([`Selejtezve`,`Elveszett`,`Szerviz alatt`].includes(n.status))return N(`Nem kezelhető: ${n.status}.`,`error`);if(n.pending)return N(`Visszavétel folyamatban — raktáros megerősítésére vár.`,`error`);if(n.holderId===r.id)return G(t.device_id);if(n.holderId!==null)return i?G(t.device_id):N(`Másnál van: ${n.holder?.full_name}.`,`error`);let a=n.reservation;return a&&a.reserved_by!==r.id&&!i?N(`Lefoglalva: ${n.reservedBy?.full_name}.`,`error`):st(t.device_id)}function Bt(e,{tag:t}={}){e.innerHTML=`
    <div class="content">
      <div class="scan-wrap">
        <div class="scan-icon">${j.qr}</div>
        <input id="scan-input" class="form-control"
          autocomplete="off" spellcheck="false"
          placeholder="Olvasd be vagy gépeld az azonosítót…" />
        <p class="scan-hint">Nyomj 'Enter'-t, vagy olvasd be a vonalkódot.</p>
      </div>
    </div>`,Rt(e),t&&(zt(decodeURIComponent(t)),i(`/scan`))}var Vt={"/inventory":{title:`Eszközlista`,nav:`inventory`,render:rt},"/my":{title:`Nálam`,nav:`my`,render:Et},"/pending":{title:`Ellenőrzésre vár`,nav:`pending`,render:kt,role:`storekeeper`},"/register-data":{title:`Adatbevitel`,nav:`register-data`,render:It,role:`storekeeper`},"/register":{title:`Új eszköz bevitele`,nav:`register`,render:Pt,role:`storekeeper`},"/device/:id":{title:`Készülék részletei`,nav:`inventory`,render:yt},"/scan":{title:`Beolvasás`,nav:`scan`,render:Bt},"/scan/:tag":{title:`Beolvasás`,nav:`scan`,render:Bt}},Z={key:`/`,params:{}};function Ht(){document.getElementById(`app`).innerHTML=`
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
    </div>`;let e=document.getElementById(`btn-hamburger`),t=document.getElementById(`sidebar-overlay`);function n(){document.getElementById(`app`).querySelector(`.app-shell`).classList.remove(`sidebar-open`)}e.addEventListener(`click`,()=>{document.getElementById(`app`).querySelector(`.app-shell`).classList.toggle(`sidebar-open`)}),t.addEventListener(`click`,n)}function Ut(){let e=T(w(),`storekeeper`),t=de().length,n=Vt[Z.key]?.nav,r=[{key:`inventory`,path:`/inventory`,label:`Eszközlista`,ico:j.inventory},{key:`my`,path:`/my`,label:`Nálam`,ico:j.my}],a=[{key:`pending`,path:`/pending`,label:`Leadott eszközök`,ico:j.pending,badge:t||null},{key:`register-data`,path:`/register-data`,label:`Adatbevitel`,ico:j.building}],o=e=>`
    <a class="nav-item ${e.key===n?`active`:``}" data-path="${e.path}">
      <span class="ico">${e.ico}</span><span>${e.label}</span>
      ${e.badge?`<span class="badge-count">${e.badge}</span>`:``}
    </a>`,s=document.getElementById(`nav`);s.innerHTML=`<div class="nav-label">Eszközök</div>`+r.map(o).join(``)+(e?`<div class="nav-label">Raktárkezelés</div>`+a.map(o).join(``):``),s.querySelectorAll(`[data-path]`).forEach(e=>e.addEventListener(`click`,()=>{i(e.dataset.path),document.getElementById(`app`).querySelector(`.app-shell`).classList.remove(`sidebar-open`)}))}function Wt(){let e=C(),t=document.getElementById(`user-select`);t&&t.value!==String(e.id)&&(t.value=String(e.id))}function Q(){if(!document.getElementById(`content`))return;let e=Vt[Z.key];if(!e){i(`/inventory`);return}if(e?.role&&!T(w(),e.role)){i(`/`);return}Ut(),Wt();let t=document.getElementById(`content`);t.innerHTML=``,e.render(t,Z.params)}function Gt(){n(`/`,()=>i(`/inventory`)),n(`/inventory`,()=>{Z={key:`/inventory`,params:{}},Q()}),n(`/my`,()=>{Z={key:`/my`,params:{}},Q()}),n(`/pending`,()=>{Z={key:`/pending`,params:{}},Q()}),n(`/register`,()=>{Z={key:`/register`,params:{}},Q()}),n(`/register-data`,()=>{Z={key:`/register-data`,params:{}},Q()}),n(`/device/:id`,e=>{Z={key:`/device/:id`,params:e},Q()}),n(`/scan`,()=>{Z={key:`/scan`,params:{}},Q()}),n(`/scan/:tag`,e=>{Z={key:`/scan/:tag`,params:e},Q()}),r(()=>i(`/`))}function $(e){document.getElementById(`app`).innerHTML=`<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px">
       <div style="text-align:center;max-width:420px;color:var(--ink,#333)">${e}</div>
     </div>`}async function Kt(){$(`<div class="muted">Betöltés…</div>`);let e=new URLSearchParams(location.search),t=null;if(e.has(`sso`)){try{await f(`POST`,`/auth/sso`,{token:e.get(`sso`),username:e.get(`u`),timestamp:Number(e.get(`t`))})}catch(e){t=e.message}history.replaceState(null,``,location.pathname+location.hash)}try{await _()}catch{$(`<div class="big">${j.warning}</div>
      <h2>A szerver nem érhető el</h2>
      <p class="muted">Nem sikerült betölteni az adatokat. Ellenőrizd, hogy fut-e a backend.</p>`);return}if(!C()){$(`<div class="big">${j.my}</div>
      <h2>Bejelentkezés szükséges</h2>
      <p class="muted">Jelentkezz be a főoldalon az eszköznyilvántartó használatához.</p>
      ${t?`<p class="muted" style="font-size:.8rem;margin-top:8px">(SSO: ${k(t)})</p>`:``}`);return}Ht(),Gt(),Ae(()=>Q()),s()}Kt();export{k as a,P as i,Pt as n,S as o,L as r,Ft as t};