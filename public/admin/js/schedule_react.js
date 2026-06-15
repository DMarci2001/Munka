"use strict";

const { useState, useEffect, useMemo, useCallback, useRef } = React;

const HMM_CONFIG = window.HMM_SCHEDULE_CONFIG || {
  url: "index.php?page=workschedule",
  offset: 0,
  adminName: "Admin"
};

/* ---- segédek -------------------------------------------------------- */
const toMin = (t) => { const [h, m] = (t || "0:0").split(":").map(Number); return h * 60 + m; };
const HU_MONTHS    = ["január","február","március","április","május","június","július","augusztus","szeptember","október","november","december"];
const HU_MON_SHORT = ["jan.","febr.","márc.","ápr.","máj.","jún.","júl.","aug.","szept.","okt.","nov.","dec."];
const HU_DAYS      = ["Hétfő","Kedd","Szerda","Csütörtök","Péntek","Szombat","Vasárnap"];
const HU_DAYS_UP   = ["HÉTFŐ","KEDD","SZERDA","CSÜTÖRTÖK","PÉNTEK","SZOMBAT","VASÁRNAP"];
const HU_DAYS_1    = ["H","K","Sze","Cs","P","Szo","V"];

function isoWeekMonday(year, week) {
  const s = new Date(Date.UTC(year, 0, 1 + (week - 1) * 7));
  const dow = s.getUTCDay();
  if (dow <= 4) s.setUTCDate(s.getUTCDate() - dow + 1); else s.setUTCDate(s.getUTCDate() + 8 - dow);
  return s;
}
const iso = (dt) => `${dt.getUTCFullYear()}-${String(dt.getUTCMonth()+1).padStart(2,"0")}-${String(dt.getUTCDate()).padStart(2,"0")}`;
const fmtShortISO = (s) => { if (!s) return ""; const [,m,d] = s.split("-").map(Number); return `${HU_MON_SHORT[m-1]} ${d}.`; };
const weekRange = (year, week) => {
  const mon = isoWeekMonday(year, week);
  const sun = new Date(mon); sun.setUTCDate(sun.getUTCDate()+6);
  const fmt = (d) => `${HU_MON_SHORT[d.getUTCMonth()]} ${d.getUTCDate()}.`;
  return `${fmt(mon)} – ${fmt(sun)}`;
};
const datesBetween = (start, end) => {
  const out = []; let d = new Date(start+"T00:00:00Z"); const last = new Date(end+"T00:00:00Z");
  while (d<=last) { out.push(iso(d)); d.setUTCDate(d.getUTCDate()+1); }
  return out;
};
const dur = (from, to) => { const m = toMin(to)-toMin(from); if (m<=0) return "—"; const h=Math.floor(m/60),mm=m%60; return `${h?h+" óra":""}${h&&mm?" ":""}${mm?mm+" perc":""}`.trim()||"0 perc"; };
const EMPTY_BOARD = [[], [], [], [], [], [], []];

/* ---- ünnepnapok ----------------------------------------------------- */
const HOLIDAYS = {
  "2026-01-01":"Újév","2026-03-15":"Nemzeti ünnep","2026-04-03":"Nagypéntek","2026-04-06":"Húsvéthétfő",
  "2026-05-01":"A munka ünnepe","2026-05-25":"Pünkösdhétfő","2026-08-20":"Államalapítás",
  "2026-10-23":"1956 emléknapja","2026-11-01":"Mindenszentek","2026-12-25":"Karácsony","2026-12-26":"Karácsony 2. napja",
  "2027-01-01":"Újév","2027-03-15":"Nemzeti ünnep","2027-05-01":"A munka ünnepe",
};
const holidayOf = (dateStr) => HOLIDAYS[dateStr] || null;

/* ---- kategóriák ----------------------------------------------------- */
const CATS = {
  belso:     { label:"Belső rendelések", color:"var(--brand)",  type:"Rendelés",       icon:"users"    },
  kulso:     { label:"Külső rendelések", color:"var(--green)",  type:"Külső rendelés", icon:"building" },
  kiszallas: { label:"Kiszállások",      color:"var(--purple)", type:"Kiszállás",      icon:"truck"    },
};
const CAT_ORDER = ["belso","kulso","kiszallas"];

/* ---- szabadság típusok ------------------------------------------------ */
const VACATION_TYPES = ["Szabadság","Betegszabadság","Képzés","Egyéb"];
const VACATION_TYPE_COLORS = {
  "Szabadság":      "var(--brand)",
  "Betegszabadság": "var(--danger)",
  "Képzés":         "var(--blue)",
  "Egyéb":          "var(--purple)",
};

/* ---- alaprajz ------------------------------------------------------- */
const FLOORS = [
  { name:"Földszint", rooms:["Recepció","Labor","Rtg","UH","Mammográfia","Rendelő 2. fogl.","Rendelő 4. fogl.","Rendelő 5. fogl.","Rendelő 6. fogl."] },
  { name:"Emelet I.", rooms:["Emelt I.","Kardiológia","Kardiológia II.","Ortopédia/Kisműtő","Bőrgyógyászat","Szemészet","Fül-orr-gégészet","Nőgyógyászat","Fogászat"] },
];
const SHORT = {"Rendelő 2. fogl.":"Rend. 2.","Rendelő 4. fogl.":"Rend. 4.","Rendelő 5. fogl.":"Rend. 5.","Rendelő 6. fogl.":"Rend. 6.","Ortopédia/Kisműtő":"Ortopédia","Fül-orr-gégészet":"Fül-orr","Kardiológia II.":"Kardio II.","Mammográfia":"Mammo.","Bőrgyógyászat":"Bőrgyógy."};
const isInternalRoom = (t) => FLOORS.some((f) => f.rooms.includes(t));

/* ---- stílusok ------------------------------------------------------- */
const Styles = () => (
  <style>{`
    @import url('https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700;12..96,800&family=Manrope:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600&display=swap');
    .mb-root{
      --blue:#3b82f6; --blue-soft:rgba(59,130,246,.16);
      --purple:#a855f7; --purple-soft:rgba(168,85,247,.16);
      --green:#10b981; --green-soft:rgba(16,185,129,.15);
      --amber:#f59e0b;
      --danger:#ef4444; --danger-ink:#ef4444; --danger-soft:rgba(239,68,68,.15);
      font-family:'Manrope',sans-serif; color:var(--ink); background:var(--bg);
    }
    .mb-root.mb-dark{
      --bg:#0c0f15; --sidebar:#0a0d12; --surface:#151a23; --surface-2:#1b212c;
      --card:#171d27; --card-hover:#1c2330; --border:#262d39; --border-soft:#1d2330;
      --ink:#e8ecf2; --muted:#8b94a3; --faint:#5e6878;
      --danger-ink:#fca5a5; --scroll:#2c3442; --weekend:#11161e;
      --brand:#b3473a; --brand-ink:#e09a90; --brand-soft:rgba(179,71,58,.20);
      --map-bg:#0f141c; --map-room:#181f2a; --map-room-stroke:#2a323e;
      --map-road:#222a36; --map-road2:#1a212c; --map-bldg:#161d27; --map-bldg-stroke:#252d39;
    }
    .mb-root.mb-light{
      --bg:#f3f5f8; --sidebar:#ffffff; --surface:#ffffff; --surface-2:#f1f4f7;
      --card:#ffffff; --card-hover:#f7f9fc; --border:#e3e8ef; --border-soft:#eef1f5;
      --ink:#1a2230; --muted:#5c6675; --faint:#9aa3b1;
      --danger-ink:#dc2626; --scroll:#cdd4dd; --weekend:#eef1f6;
      --brand:#9c3328; --brand-ink:#8e2f23; --brand-soft:rgba(156,51,40,.12);
      --map-bg:#eef1f5; --map-room:#ffffff; --map-room-stroke:#dde3ea;
      --map-road:#d2d8e0; --map-road2:#e6eaf0; --map-bldg:#eef1f5; --map-bldg-stroke:#dde3ea;
    }
    .mb-root *{ box-sizing:border-box; }
    .mb-display{ font-family:'Bricolage Grotesque',sans-serif; letter-spacing:-.01em; }
    .mb-mono{ font-family:'IBM Plex Mono',monospace; font-variant-numeric:tabular-nums; }
    .mb-scroll{ scrollbar-width:thin; scrollbar-color:var(--scroll) transparent; }
    .mb-scroll::-webkit-scrollbar{ height:10px; width:10px; }
    .mb-scroll::-webkit-scrollbar-thumb{ background:var(--scroll); border-radius:99px; border:3px solid transparent; background-clip:padding-box; }
    .mb-tcard{ transition:transform .12s ease, box-shadow .12s ease, border-color .12s ease, background .12s ease; cursor:pointer; }
    .mb-tcard:hover{ transform:translateY(-1px); box-shadow:0 10px 22px -14px rgba(0,0,0,.55); }
    .mb-nav:hover{ background:var(--surface-2); color:var(--ink); }
    .mb-add:hover{ background:var(--surface-2); color:var(--ink); border-color:var(--faint); }
    .mb-btn{ transition:background .12s, border-color .12s, color .12s; }
    .mb-btn:hover{ background:var(--surface-2); }
    .mb-prim:hover{ filter:brightness(1.08); }
    .mb-pulse{ animation:mbpulse 2.4s ease-in-out infinite; }
    @keyframes mbpulse{ 0%,100%{opacity:1} 50%{opacity:.5} }
    .mb-pop{ animation:mbpop .18s cubic-bezier(.2,.9,.3,1.2) both; }
    @keyframes mbpop{ from{opacity:0; transform:scale(.97) translateY(8px)} to{opacity:1; transform:none} }
    .mb-back{ animation:mbback .2s ease both; }
    @keyframes mbback{ from{opacity:0} to{opacity:1} }
    .mb-toast{ animation:mbtoast .3s ease both; }
    @keyframes mbtoast{ from{opacity:0; transform:translateY(12px)} to{opacity:1; transform:none} }
    .mb-in{ width:100%; outline:none; background:var(--surface-2); border:1px solid var(--border); color:var(--ink); border-radius:9px; }
    .mb-in:focus{ border-color:var(--brand); }
    .mb-in::placeholder{ color:var(--faint); }
    .mb-dark input[type=time]::-webkit-calendar-picker-indicator,.mb-dark input[type=date]::-webkit-calendar-picker-indicator{ filter:invert(.7); }
    @media print{
      @page{ size:landscape; margin:8mm; }
      .mb-no-print{ display:none !important; }
      .mb-sidebar{ display:none !important; }
      .mb-board{ height:auto !important; overflow:visible !important; flex-wrap:wrap; }
      .mb-colbody{ overflow:visible !important; max-height:none !important; }
    }
  `}</style>
);

/* ---- ikonok --------------------------------------------------------- */
const S = (path, w=18) => (p) => (<svg viewBox="0 0 24 24" width={w} height={w} fill="none" {...p}>{path}</svg>);
const Ico = {
  overview: S(<><rect x="3" y="3" width="7" height="7" rx="1.5" stroke="currentColor" strokeWidth="1.7"/><rect x="14" y="3" width="7" height="7" rx="1.5" stroke="currentColor" strokeWidth="1.7"/><rect x="3" y="14" width="7" height="7" rx="1.5" stroke="currentColor" strokeWidth="1.7"/><rect x="14" y="14" width="7" height="7" rx="1.5" stroke="currentColor" strokeWidth="1.7"/></>),
  calendar: S(<><rect x="3.5" y="5" width="17" height="16" rx="2.5" stroke="currentColor" strokeWidth="1.7"/><path d="M3.5 9.5h17M8 3v4M16 3v4" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"/></>),
  board:    S(<><rect x="3.5" y="4" width="17" height="16" rx="2" stroke="currentColor" strokeWidth="1.7"/><path d="M9 4v16M15 4v16" stroke="currentColor" strokeWidth="1.7"/></>),
  list:     S(<><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round"/></>),
  menu:     S(<><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round"/></>),
  alert:    S(<><path d="M12 3 2.5 20h19L12 3Z" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round"/><path d="M12 10v4M12 17h.01" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"/></>),
  place:    S(<><path d="M12 21s7-6.4 7-11a7 7 0 1 0-14 0c0 4.6 7 11 7 11Z" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round"/><circle cx="12" cy="10" r="2.4" stroke="currentColor" strokeWidth="1.7"/></>),
  doctor:   S(<><path d="M6 4v5a4 4 0 0 0 8 0V4" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"/><path d="M10 17v1a4 4 0 0 0 8 0v-2" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"/><circle cx="18" cy="13" r="2.2" stroke="currentColor" strokeWidth="1.7"/></>),
  users:    S(<><circle cx="9" cy="8" r="3.2" stroke="currentColor" strokeWidth="1.7"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0M16 5.2a3.2 3.2 0 0 1 0 5.6M17.5 20a5.5 5.5 0 0 0-3-4.9" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"/></>),
  building: S(<><rect x="5" y="3" width="14" height="18" rx="1.5" stroke="currentColor" strokeWidth="1.7"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2M10 21v-2.5h4V21" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"/></>),
  truck:    S(<><path d="M3 6.5h10v9H3zM13 9.5h4l3 3v3h-7z" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round"/><circle cx="7" cy="17.5" r="1.8" stroke="currentColor" strokeWidth="1.6"/><circle cx="17" cy="17.5" r="1.8" stroke="currentColor" strokeWidth="1.6"/></>),
  sun:      S(<><circle cx="12" cy="12" r="4" stroke="currentColor" strokeWidth="1.7"/><path d="M12 2v2M12 20v2M4 12H2M22 12h-2M5 5l1.5 1.5M17.5 17.5 19 19M19 5l-1.5 1.5M6.5 17.5 5 19" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"/></>),
  moon:     S(<><path d="M20 13.5A8 8 0 1 1 10.5 4a6.5 6.5 0 0 0 9.5 9.5Z" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round"/></>),
  chart:    S(<><path d="M4 20V10M10 20V4M16 20v-6M20 20H4" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"/></>),
  gear:     S(<><circle cx="12" cy="12" r="3" stroke="currentColor" strokeWidth="1.7"/><path d="M12 2.5v3M12 18.5v3M21.5 12h-3M5.5 12h-3M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1M18.4 18.4l-2.1-2.1M7.7 7.7 5.6 5.6" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"/></>),
  copy:     S(<><rect x="9" y="9" width="11" height="11" rx="2" stroke="currentColor" strokeWidth="1.6"/><path d="M5 15V5a2 2 0 0 1 2-2h8" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"/></>),
  bell:     S(<><path d="M6 9a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6Z" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round"/><path d="M10 20a2 2 0 0 0 4 0" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"/></>, 19),
  person:   S(<><circle cx="12" cy="8" r="3.4" stroke="currentColor" strokeWidth="1.7"/><path d="M5 20a7 7 0 0 1 14 0" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"/></>, 14),
  pin:      S(<><path d="M12 21s7-6.4 7-11a7 7 0 1 0-14 0c0 4.6 7 11 7 11Z" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round"/><circle cx="12" cy="10" r="2.4" stroke="currentColor" strokeWidth="1.7"/></>, 14),
  help:     S(<><circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="1.7"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.4 2.3c-.8.3-.9 1-.9 1.7M12 17h.01" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"/></>, 19),
  filter:   S(<><path d="M4 5h16l-6 7v6l-4 2v-8L4 5Z" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round"/></>, 15),
  search:   S(<><circle cx="11" cy="11" r="6.5" stroke="currentColor" strokeWidth="1.8"/><path d="m20 20-3.5-3.5" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round"/></>, 16),
  left:     S(<><path d="M14 6l-6 6 6 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></>, 16),
  right:    S(<><path d="M10 6l6 6-6 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></>, 16),
  chevDown: S(<><path d="m6 9 6 6 6-6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></>, 16),
  chevUp:   S(<><path d="m6 15 6-6 6 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></>, 16),
  x:        S(<><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round"/></>, 14),
  plus:     S(<><path d="M12 5v14M5 12h14" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/></>, 14),
  trash:    S(<><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"/></>, 15),
  clock:    S(<><circle cx="12" cy="12" r="8.5" stroke="currentColor" strokeWidth="1.7"/><path d="M12 7.5V12l3 2" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"/></>, 14),
  ext:      S(<><path d="M14 5h5v5M19 5l-8 8M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"/></>, 13),
  send:     S(<><path d="M4 11.5 20 4l-7.5 16-2.2-6.3L4 11.5Z" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round"/></>, 15),
  cmd:      S(<><path d="M9 6a2 2 0 1 0-2 2h10a2 2 0 1 0-2-2v12a2 2 0 1 0 2-2H7a2 2 0 1 0 2 2V6Z" stroke="currentColor" strokeWidth="1.5"/></>, 12),
  info:     S(<><circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="1.6"/><path d="M12 11v5M12 8h.01" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"/></>, 14),
  repeat:   S(<><path d="M4 8a5 5 0 0 1 5-5h7l-2.5-2.5M20 16a5 5 0 0 1-5 5H8l2.5 2.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" transform="translate(0,1)"/></>, 13),
  save:     S(<><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round"/><path d="M17 21v-8H7v8M7 3v5h8" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"/></>, 15),
  refresh:  S(<><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"/><path d="M3 3v5h5" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"/></>, 16),
};

/* ---- HMM logó ------------------------------------------------------- */
function LogoMark({ size=34 }) {
  return (
    <svg viewBox="0 0 48 48" width={size} height={size} style={{ flexShrink:0 }}>
      <circle cx="24" cy="24" r="20.5" fill="none" stroke="var(--brand)" strokeWidth="4.5"/>
      <path d="M20 11 h8 v9 h9 v8 h-9 v9 h-8 v-9 h-9 v-8 h9 z" fill="var(--brand)"/>
    </svg>
  );
}

/* ---- ütközés-elemzés ------------------------------------------------ */
function analyzeDays(days, vacationsByDay) {
  const set = new Set(); const det = {};
  days.forEach((day, di) => {
    const slots = [];
    day.forEach((b) => (b.staff||[]).forEach((s) => slots.push({ key:`${di}:${b.id}`, p:s.name, workerId:s.workerId, s:toMin(s.from), e:toMin(s.to), from:s.from, to:s.to })));
    for (let i=0; i<slots.length; i++) for (let j=i+1; j<slots.length; j++) {
      const x=slots[i], y=slots[j];
      if (x.p===y.p && x.key!==y.key && x.s<y.e && y.s<x.e) {
        set.add(x.key); set.add(y.key);
        (det[x.key]||=[]).push({ p:y.p, from:y.from, to:y.to });
        (det[y.key]||=[]).push({ p:x.p, from:x.from, to:x.to });
      }
    }
    const vacs = (vacationsByDay && vacationsByDay[di]) || [];
    if (vacs.length) {
      slots.forEach((x) => {
        const v = vacs.find((vv) => vv.workerId && vv.workerId===x.workerId);
        if (v) {
          set.add(x.key);
          (det[x.key]||=[]).push({ p:x.p, vac:true, status:v.status });
        }
      });
    }
  });
  return { set, det };
}

/* ---- térkép --------------------------------------------------------- */
function FloorPlan({ active }) {
  const cols=3, boxH=28, gx=7, gy=7, padX=12, labelH=19, fGap=13, W=300;
  const boxW=(W-padX*2-gx*(cols-1))/cols;
  const placed={}; const bands=[]; let y=8;
  FLOORS.forEach((fl) => { bands.push({ name:fl.name, y }); y+=labelH; fl.rooms.forEach((r,i) => { const col=i%cols, row=Math.floor(i/cols); placed[r]={ x:padX+col*(boxW+gx), y:y+row*(boxH+gy), w:boxW, h:boxH }; }); y+=Math.ceil(fl.rooms.length/cols)*(boxH+gy)-gy+fGap; });
  const H=y, act=placed[active];
  return (
    <svg viewBox={`0 0 ${W} ${H}`} width="100%" style={{ display:"block" }}>
      <rect x="2" y="2" width={W-4} height={H-4} rx="12" fill="var(--map-bg)" stroke="var(--border)"/>
      {bands.map((b) => <text key={b.name} x={padX} y={b.y+12} className="mb-display" fontSize="10.5" fontWeight="700" fill="var(--faint)" letterSpacing=".05em">{b.name.toUpperCase()}</text>)}
      {Object.entries(placed).map(([r,p]) => { const on=r===active; return (<g key={r}><rect x={p.x} y={p.y} width={p.w} height={p.h} rx="6" fill={on?"var(--brand)":"var(--map-room)"} stroke={on?"var(--brand)":"var(--map-room-stroke)"} strokeWidth="1"/><text x={p.x+p.w/2} y={p.y+p.h/2+3} textAnchor="middle" fontSize="8.3" fontWeight={on?700:500} fontFamily="Manrope,sans-serif" fill={on?"#fff":"var(--muted)"}>{SHORT[r]||r}</text></g>); })}
      {act && (<g transform={`translate(${act.x+act.w-5},${act.y})`} className="mb-pop"><circle cx="0" cy="0" r="8" fill="var(--brand)" stroke="var(--map-bg)" strokeWidth="1.5"/><path d="M0 -3.4C-2.1-3.4-3.6-1.8-3.6.2-3.6 2.3 0 4.8 0 4.8 0 4.8 3.6 2.3 3.6.2 3.6-1.8 2.1-3.4 0-3.4Z" fill="#fff"/><circle cx="0" cy="0.2" r="1.25" fill="var(--brand)"/></g>)}
    </svg>
  );
}

function StreetMap({ address }) {
  return (
    <svg viewBox="0 0 300 150" width="100%" style={{ display:"block" }}>
      <rect x="2" y="2" width="296" height="146" rx="12" fill="var(--map-bg)" stroke="var(--border)"/>
      <g stroke="var(--map-road)" strokeWidth="6"><path d="M0 55 H300 M0 105 H300 M70 0 V150 M200 0 V150"/></g>
      <g stroke="var(--map-road2)" strokeWidth="2"><path d="M0 30 H300 M0 80 H300 M0 130 H300 M35 0 V150 M135 0 V150 M250 0 V150"/></g>
      {[[20,15,40,28],[95,12,80,30],[215,15,55,30],[20,68,38,30],[100,68,60,28],[210,118,60,24]].map((b,i) => <rect key={i} x={b[0]} y={b[1]} width={b[2]} height={b[3]} rx="3" fill="var(--map-bldg)" stroke="var(--map-bldg-stroke)"/>)}
      <g transform="translate(150,78)" className="mb-pop"><circle cx="0" cy="0" r="14" fill="var(--purple-soft)"/><path d="M0-10C-5-10-9-6-9-1-9 5 0 13 0 13 0 13 9 5 9-1 9-6 5-10 0-10Z" fill="var(--purple)" stroke="var(--map-bg)" strokeWidth="1.2"/><circle cx="0" cy="-1" r="3.2" fill="var(--map-bg)"/></g>
      {address && <text x="150" y="140" textAnchor="middle" fontSize="9.5" fontWeight="600" fontFamily="Manrope,sans-serif" fill="var(--muted)">{address}</text>}
    </svg>
  );
}

function RoomMap({ booking }) {
  if (booking.cat==="belso" && isInternalRoom(booking.title)) return <FloorPlan active={booking.title}/>;
  const addr = booking.title.includes("–") ? booking.title.split("–").slice(1).join("–").trim() : booking.title;
  return <StreetMap address={addr}/>;
}

/* ---- Combobox ------------------------------------------------------- */
function Combobox({ value, onChange, options, placeholder, kind, compact }) {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState("");
  const color  = kind==="d" ? "var(--blue)"   : "var(--purple)";
  const soft   = kind==="d" ? "var(--blue-soft)" : "var(--purple-soft)";
  const list   = options.filter((o) => o.toLowerCase().includes(q.toLowerCase()));
  return (
    <div className="relative">
      <button type="button" onClick={() => { setOpen((v)=>!v); setQ(""); }} className="mb-in flex items-center justify-between gap-1.5 text-left" style={{ padding:compact?"7px 8px":"10px 12px", borderColor:open?"var(--brand)":"var(--border)" }}>
        <span className="flex items-center gap-1.5 min-w-0"><span style={{ color, flexShrink:0 }}>{kind==="d"?Ico.doctor({width:13,height:13}):Ico.person({width:13,height:13})}</span><span className="truncate" style={{ fontSize:13, fontWeight:value?600:400, color:value?"var(--ink)":"var(--faint)" }}>{value||placeholder}</span></span>
        <span style={{ color:"var(--faint)", flexShrink:0 }}>{Ico.chevDown({width:13,height:13})}</span>
      </button>
      {open && (<>
        <div className="fixed inset-0 z-40" onClick={() => setOpen(false)}/>
        <div className="mb-pop absolute z-50 mt-1.5 w-full rounded-xl overflow-hidden" style={{ background:"var(--surface)", border:"1px solid var(--border)", boxShadow:"0 20px 44px -16px rgba(0,0,0,.5)", minWidth:200 }}>
          <div className="relative p-2" style={{ borderBottom:"1px solid var(--border)" }}><span className="absolute left-4 top-1/2 -translate-y-1/2" style={{ color:"var(--faint)" }}>{Ico.search()}</span><input autoFocus value={q} onChange={(e)=>setQ(e.target.value)} placeholder="Keresés…" className="mb-in py-2 pl-9 pr-3" style={{ fontSize:13 }}/></div>
          <div className="mb-scroll" style={{ maxHeight:190, overflowY:"auto", padding:4 }}>
            {value && <button type="button" onClick={() => { onChange(null); setOpen(false); }} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left" style={{ fontSize:12.5, color:"var(--faint)" }}>{Ico.x()} Eltávolítás</button>}
            {list.length===0 && <div className="px-3 py-3" style={{ fontSize:12.5, color:"var(--faint)" }}>Nincs találat.</div>}
            {list.map((o) => (<button key={o} type="button" onClick={() => { onChange(o); setOpen(false); }} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left" style={{ fontSize:13.5, fontWeight:o===value?700:500, background:o===value?soft:"transparent", color:o===value?color:"var(--ink)" }}><span style={{ color, flexShrink:0 }}>{kind==="d"?Ico.doctor({width:13,height:13}):Ico.person({width:13,height:13})}</span><span className="truncate">{o}</span></button>))}
          </div>
        </div>
      </>)}
    </div>
  );
}

function MiniSelect({ value, onChange, options }) {
  return (<div className="relative"><select value={value} onChange={(e)=>onChange(e.target.value)} className="mb-in appearance-none py-2.5 pl-3 pr-8" style={{ fontSize:13.5, fontWeight:600 }}>{options.map((o)=><option key={o.v} value={o.v}>{o.l}</option>)}</select><span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2" style={{ color:"var(--faint)" }}>{Ico.chevDown({width:14,height:14})}</span></div>);
}
function TimeBox({ value, onChange }) { return <input type="time" value={value} onChange={(e)=>onChange(e.target.value)} className="mb-mono mb-in py-2 px-1.5 text-center" style={{ width:74, fontSize:12.5 }}/>; }

function Check({ checked, onChange, label }) {
  return (<button type="button" onClick={()=>onChange(!checked)} className="flex items-center gap-2"><span className="flex items-center justify-center rounded" style={{ width:18, height:18, background:checked?"var(--brand)":"var(--surface-2)", border:`1px solid ${checked?"var(--brand)":"var(--border)"}`, flexShrink:0 }}>{checked && <svg viewBox="0 0 24 24" width="13" height="13" fill="none"><path d="M5 12.5l4 4 10-10" stroke="#fff" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"/></svg>}</span><span style={{ fontSize:13, fontWeight:500 }}>{label}</span></button>);
}
function Radio({ selected, onSelect, label }) {
  return (<button type="button" onClick={onSelect} className="flex items-center gap-2"><span className="flex items-center justify-center rounded-full" style={{ width:18, height:18, border:`2px solid ${selected?"var(--brand)":"var(--border)"}`, flexShrink:0 }}>{selected && <span style={{ width:9, height:9, borderRadius:99, background:"var(--brand)" }}/>}</span><span style={{ fontSize:13, fontWeight:500 }}>{label}</span></button>);
}
function Toggle({ on, onChange }) { return (<button type="button" onClick={()=>onChange(!on)} style={{ width:40, height:22, borderRadius:99, background:on?"var(--brand)":"var(--border)", position:"relative", flexShrink:0, transition:"background .15s" }}><span style={{ position:"absolute", top:2, left:on?20:2, width:18, height:18, borderRadius:99, background:"#fff", transition:"left .15s", boxShadow:"0 1px 2px rgba(0,0,0,.3)" }}/></button>); }

/* ---- StaffEditor (workerId-t is kezel) ------------------------------ */
function StaffEditor({ role, items, onChange, slotFrom, slotTo, workerList }) {
  const nameOptions = (workerList || []).map((w) => w.nev || w.name || "");
  const accent = role==="d" ? "var(--blue)" : "var(--purple)";
  const add = () => onChange([...items, { role, name:null, workerId:null, from:slotFrom, to:slotTo }]);
  const upd = (i, patch) => onChange(items.map((x,j) => j===i ? { ...x, ...patch } : x));
  const del2 = (i) => onChange(items.filter((_,j) => j!==i));
  const handleNameChange = (i, name) => {
    const found = (workerList||[]).find((w) => (w.nev||w.name||"") === name);
    upd(i, { name, workerId: found ? found.id : null });
  };
  return (
    <div className="flex flex-col gap-2">
      {items.map((s, i) => { const bad = toMin(s.from)>=toMin(s.to); return (
        <div key={i} className="flex items-center gap-1.5">
          <div className="flex-1 min-w-0"><Combobox compact value={s.name} onChange={(v)=>handleNameChange(i,v)} options={nameOptions} placeholder={role==="d"?"Orvos…":"Asszisztens…"} kind={role}/></div>
          <TimeBox value={s.from} onChange={(v)=>upd(i,{from:v})}/><span style={{ color:bad?"var(--danger)":"var(--faint)", fontWeight:700 }}>–</span><TimeBox value={s.to} onChange={(v)=>upd(i,{to:v})}/>
          <button onClick={()=>del2(i)} className="mb-btn flex h-8 w-8 items-center justify-center rounded-lg" style={{ color:"var(--faint)", flexShrink:0 }}>{Ico.trash()}</button>
        </div>); })}
      {items.length===0 && <div style={{ fontSize:12, color:"var(--faint)" }}>Nincs hozzárendelve.</div>}
      <button onClick={add} className="mb-add flex items-center justify-center gap-1 rounded-lg py-2" style={{ fontSize:12.5, fontWeight:600, color:accent, border:`1px dashed ${accent}`, transition:"all .12s" }}>{Ico.plus()} {role==="d"?"Orvos":"Asszisztens"} hozzáadása</button>
    </div>
  );
}

/* ---- EditModal ------------------------------------------------------ */
function EditModal({ ctx, onClose, onSave, onDelete, dayDates, onMap, doctorList, assistantList, places, saving }) {
  const b = ctx.booking;
  const [from, setFrom]     = useState(b ? b.from : "08:00");
  const [to, setTo]         = useState(b ? b.to   : "16:00");
  const [note, setNote]     = useState(b ? b.note : "");
  const [docs, setDocs]     = useState(b ? (b.staff||[]).filter((s)=>s.role==="d") : []);
  const [nurses, setNurses] = useState(b ? (b.staff||[]).filter((s)=>s.role==="n") : []);
  const [selectedDays, setSelectedDays] = useState(() => new Set([ctx.day]));
  const [cat, setCat]       = useState(b ? b.cat : ctx.cat || "belso");
  const [titleInput, setTitleInput]     = useState("");
  const [addressInput, setAddressInput] = useState(b ? (b.address||"") : "");
  const [dateStart, setDateStart] = useState(() => iso(dayDates[ctx.day]));
  const [dateEnd,   setDateEnd]   = useState(() => iso(dayDates[ctx.day]));

  const title    = b ? b.title    : titleInput;
  const address  = addressInput;
  const dateStr  = b ? b.date     : (ctx.date || iso(dayDates[ctx.day]));
  const badTime  = toMin(from) >= toMin(to);
  const badRange = !b && cat==="kiszallas" && dateStart > dateEnd;
  const noDoc    = cat!=="kiszallas" && docs.length === 0;
  const blocked  = badTime || badRange || (!b && title.trim()==="");
  const locSug   = useMemo(() => cat==="belso"
    ? Array.from(new Set(FLOORS.flatMap((f)=>f.rooms)))
    : Array.from(new Set((places||[]).map((p)=>p.megnev).filter(Boolean))), [cat, places]);

  useEffect(() => { const h=(e)=>e.key==="Escape"&&onClose(); document.addEventListener("keydown",h); return ()=>document.removeEventListener("keydown",h); }, [onClose]);

  const toggleDay = (di) => setSelectedDays((prev) => {
    const next = new Set(prev);
    if (next.has(di)) { if (next.size>1) next.delete(di); } else next.add(di);
    return next;
  });
  const selectAllDays = () => setSelectedDays(new Set([0,1,2,3,4,5,6]));
  const pickedDi = Array.from(selectedDays).sort((a,b2)=>a-b2)[0] ?? ctx.day;
  const pickDate = (val) => {
    const di = dayDates.findIndex((d)=>iso(d)===val);
    if (di!==-1) setSelectedDays(new Set([di]));
  };

  const save = () => {
    const staff = [...docs, ...nurses].filter((s)=>s.name && s.workerId);
    const dates = b ? [dateStr] : (cat==="kiszallas" ? datesBetween(dateStart, dateEnd) : Array.from(selectedDays).sort().map((di)=>iso(dayDates[di])));
    const rec = { id:b?b.id:null, tipusId:b?b.tipusId:null, date:dateStr, dates, cat, title, address, staff, from, to, note };
    onSave(rec);
  };

  const Row = ({ icon, label, children }) => (
    <div className="flex gap-2.5">
      <span className="mt-0.5" style={{ color:"var(--faint)", flexShrink:0 }}>{icon}</span>
      <div className="min-w-0"><div style={{ fontSize:11, color:"var(--faint)", fontWeight:600 }}>{label}</div><div style={{ fontSize:13, fontWeight:600, marginTop:1 }}>{children}</div></div>
    </div>
  );

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center p-4 sm:p-6 mb-scroll" style={{ overflowY:"auto" }}>
      <div className="mb-back fixed inset-0" style={{ background:"rgba(4,6,10,.55)", backdropFilter:"blur(4px)" }} onClick={onClose}/>
      <div className="mb-pop relative w-full rounded-2xl overflow-hidden" style={{ maxWidth:840, background:"var(--surface)", border:"1px solid var(--border)", boxShadow:"0 50px 100px -28px rgba(0,0,0,.6)", marginTop:16, marginBottom:16 }}>
        <div className="flex items-center justify-between gap-3 px-6 py-4" style={{ borderBottom:"1px solid var(--border)" }}>
          <div>
            <h2 className="mb-display" style={{ fontSize:19, fontWeight:700 }}>{title || "Új rendelés"}</h2>
            <div className="flex items-center gap-2 mt-1">
              <Badge text={CATS[cat]?.type||cat} color={CATS[cat]?.color||"var(--muted)"}/>
              <span className="mb-mono" style={{ fontSize:11.5, color:"var(--faint)" }}>{fmtShortISO(dateStr)}</span>
            </div>
          </div>
          <button onClick={onClose} className="mb-btn flex h-8 w-8 items-center justify-center rounded-lg" style={{ color:"var(--muted)", background:"var(--surface-2)" }}>{Ico.x()}</button>
        </div>
        <div className="grid gap-7 px-6 py-5 mb-scroll" style={{ gridTemplateColumns:"minmax(0,1.85fr) minmax(0,1fr)", maxHeight:"74vh", overflowY:"auto" }}>
          <div className="flex flex-col gap-4">
            {b ? (
              <>
                <Field label={cat==="kulso"?"Külső rendelés neve":"Helyiség"}>
                  <div className="mb-in px-3 py-2.5" style={{ fontSize:13.5, fontWeight:600, color:"var(--muted)", borderStyle:"dashed" }}>{title}</div>
                </Field>
                <Field label="Rendelő">
                  <input list="mb-loc-list" value={addressInput} onChange={(e)=>setAddressInput(e.target.value)} placeholder="Válassz a helyiségek közül vagy írd be…" className="mb-in px-3 py-2.5" style={{ fontSize:13.5, fontWeight:600 }}/>
                  <datalist id="mb-loc-list">{locSug.map((l)=><option key={l} value={l}/>)}</datalist>
                </Field>
              </>
            ) : (
              <>
                <Field label="Rendelés">
                  <input value={titleInput} onChange={(e)=>setTitleInput(e.target.value)} placeholder="pl. Szemészeti szűrés" className="mb-in px-3 py-2.5" style={{ fontSize:13.5, fontWeight:600 }}/>
                </Field>
                <Field label="Rendelő">
                  <input list="mb-loc-list" value={addressInput} onChange={(e)=>setAddressInput(e.target.value)} placeholder="Válassz a helyiségek közül vagy írd be…" className="mb-in px-3 py-2.5" style={{ fontSize:13.5, fontWeight:600 }}/>
                  <datalist id="mb-loc-list">{locSug.map((l)=><option key={l} value={l}/>)}</datalist>
                </Field>
                <Field label="Kategória">
                  <MiniSelect value={cat} onChange={setCat} options={CAT_ORDER.map((c)=>({ v:c, l:CATS[c].label }))}/>
                </Field>
              </>
            )}
            {!b && cat==="kiszallas" && (
              <div className="rounded-xl p-3" style={{ background:"var(--surface-2)", border:"1px solid var(--border)" }}>
                <div className="flex items-center gap-1.5 mb-2" style={{ fontSize:12.5, fontWeight:700 }}><span style={{ color:"var(--purple)" }}>{Ico.repeat()}</span> Ismétlődő időszak</div>
                <div className="grid grid-cols-2 gap-3">
                  <Field label="Kezdő dátum"><input type="date" value={dateStart} onChange={(e)=>setDateStart(e.target.value)} className="mb-in px-3 py-2.5 mb-mono" style={{ fontSize:13, borderColor:badRange?"var(--danger)":"var(--border)" }}/></Field>
                  <Field label="Záró dátum"><input type="date" value={dateEnd} onChange={(e)=>setDateEnd(e.target.value)} className="mb-in px-3 py-2.5 mb-mono" style={{ fontSize:13, borderColor:badRange?"var(--danger)":"var(--border)" }}/></Field>
                </div>
                <p style={{ fontSize:11.5, color:"var(--muted)", marginTop:6 }}>Az időszak minden napján megjelenik (hétvégén is). Pl. {fmtShortISO(dateStart)} – {fmtShortISO(dateEnd)}.</p>
                {badRange && <p style={{ fontSize:11.5, color:"var(--danger-ink)", marginTop:4 }}>A záró dátum legyen későbbi.</p>}
              </div>
            )}
            {!b && cat!=="kiszallas" && (
              <Field label="Napok">
                <div className="flex items-center gap-1.5 flex-wrap">
                  {HU_DAYS_1.map((d, di) => (
                    <button key={di} type="button" onClick={()=>toggleDay(di)} className="rounded-lg px-2.5 py-1.5" style={{ fontSize:12.5, fontWeight:700, color:selectedDays.has(di)?"#fff":"var(--muted)", background:selectedDays.has(di)?"var(--brand)":"var(--surface-2)", border:`1px solid ${selectedDays.has(di)?"var(--brand)":"var(--border)"}` }}>{d}</button>
                  ))}
                  <button type="button" onClick={selectAllDays} className="mb-btn rounded-lg px-2.5 py-1.5" style={{ fontSize:12.5, fontWeight:600, color:"var(--brand-ink)", border:"1px dashed var(--brand)" }}>Minden nap</button>
                </div>
              </Field>
            )}
            <Field label="Időpont (teljes idősáv)">
              <div className="flex items-center gap-2.5">
                <div className="relative flex-1"><span className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color:"var(--faint)" }}>{Ico.clock()}</span><input type="time" value={from} onChange={(e)=>setFrom(e.target.value)} className="mb-mono mb-in py-2.5 pl-9 pr-2" style={{ fontSize:13.5, borderColor:badTime?"var(--danger)":"var(--border)" }}/></div>
                <span style={{ color:"var(--faint)", fontWeight:700 }}>–</span>
                <div className="relative flex-1"><span className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color:"var(--faint)" }}>{Ico.clock()}</span><input type="time" value={to} onChange={(e)=>setTo(e.target.value)} className="mb-mono mb-in py-2.5 pl-9 pr-2" style={{ fontSize:13.5, borderColor:badTime?"var(--danger)":"var(--border)" }}/></div>
              </div>
              {badTime && <p style={{ fontSize:11.5, color:"var(--danger-ink)", marginTop:4 }}>A befejezés legyen későbbi a kezdésnél.</p>}
            </Field>
            <div>
              <div className="flex items-center gap-1.5 mb-1.5" style={{ fontSize:12.5, fontWeight:600, color:"var(--muted)" }}><span style={{ color:"var(--blue)" }}>{Ico.doctor({width:13,height:13})}</span> Orvosok</div>
              <StaffEditor role="d" items={docs} onChange={setDocs} slotFrom={from} slotTo={to} workerList={doctorList}/>
            </div>
            <div>
              <div className="flex items-center gap-1.5 mb-1.5" style={{ fontSize:12.5, fontWeight:600, color:"var(--muted)" }}><span style={{ color:"var(--purple)" }}>{Ico.person({width:13,height:13})}</span> Asszisztensek</div>
              <StaffEditor role="n" items={nurses} onChange={setNurses} slotFrom={from} slotTo={to} workerList={assistantList}/>
            </div>
            <Field label="Megjegyzés (nem kötelező)">
              <div className="relative"><textarea value={note} maxLength={200} onChange={(e)=>setNote(e.target.value)} rows={2} placeholder="pl. EKG, terheléses vizsgálat" className="mb-in px-3 py-2.5" style={{ fontSize:13.5, resize:"none", fontWeight:500 }}/><span className="absolute bottom-2 right-3 mb-mono" style={{ fontSize:11, color:"var(--faint)" }}>{note.length} / 200</span></div>
            </Field>
            {noDoc && <div className="flex items-center gap-2 rounded-lg px-3 py-2" style={{ background:"var(--danger-soft)", color:"var(--danger-ink)", fontSize:12, fontWeight:600 }}>{Ico.alert({width:14,height:14})} Nincs orvos hozzárendelve – a kártya pirosan jelenik meg.</div>}
          </div>
          <div className="flex flex-col gap-4">
            <div style={{ fontSize:12, fontWeight:700 }}>Részletek</div>
            <div className="flex flex-col gap-3.5">
              <Row icon={Ico.calendar({width:15,height:15})} label={(!b && cat==="kiszallas") ? "Időszak" : "Dátum"}>
                {b ? (<>{fmtShortISO(dateStr)} · {HU_DAYS[ctx.day]}</>) : cat==="kiszallas" ? (
                  dateStart===dateEnd ? fmtShortISO(dateStart) : <>{fmtShortISO(dateStart)} – {fmtShortISO(dateEnd)} (minden nap)</>
                ) : (
                  <input type="date" value={iso(dayDates[pickedDi])} min={iso(dayDates[0])} max={iso(dayDates[6])} onChange={(e)=>pickDate(e.target.value)} className="mb-mono mb-in px-2 py-1.5" style={{ fontSize:12.5, fontWeight:600 }}/>
                )}
              </Row>
              <Row icon={Ico.clock({width:15,height:15})} label="Időtartam">{dur(from,to)}</Row>
              <Row icon={Ico.users({width:15,height:15})} label="Személyzet">{docs.length} orvos · {nurses.length} asszisztens</Row>
              <Row icon={Ico.place({width:15,height:15})} label="Helyszín">
                <div>{title||"—"}</div>
                {!!address && <div style={{ color:"var(--muted)", fontWeight:500, marginTop:1 }}>{address}</div>}
                {title && b && <button onClick={()=>onMap({cat,title})} className="flex items-center gap-1 mt-0.5" style={{ fontSize:12, fontWeight:600, color:"var(--brand-ink)" }}>Hely megtekintése {Ico.ext()}</button>}
              </Row>
              <Row icon={Ico.alert({width:15,height:15})} label="Státusz"><Badge text={noDoc?"Hiányos":"Aktív"} color={noDoc?"var(--danger)":"var(--green)"}/></Row>
            </div>
          </div>
        </div>
        <div className="flex items-center justify-between gap-3 px-6 py-4" style={{ borderTop:"1px solid var(--border)", background:"var(--bg)" }}>
          <div>{b && <button onClick={()=>onDelete(b.id)} className="flex items-center gap-1.5 rounded-lg px-3 py-2" style={{ fontSize:13, fontWeight:600, color:"var(--danger-ink)" }}>{Ico.trash()} Beosztás törlése</button>}</div>
          <div className="flex items-center gap-2">
            <button onClick={onClose} className="mb-btn rounded-lg px-4 py-2.5" style={{ fontSize:13.5, fontWeight:600, color:"var(--muted)", border:"1px solid var(--border)" }}>Mégse</button>
            <button disabled={blocked||saving} onClick={save} className="mb-prim flex items-center gap-1.5 rounded-lg px-5 py-2.5" style={{ fontSize:13.5, fontWeight:700, color:"#fff", background:(blocked||saving)?"var(--faint)":"var(--brand)", cursor:(blocked||saving)?"not-allowed":"pointer" }}>
              {saving ? "Mentés…" : <>{Ico.save()} {b ? "Mentés" : "Hozzáadás"}</>}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

function Field({ label, children }) { return (<label className="block"><span className="block mb-1.5" style={{ fontSize:12.5, fontWeight:500, color:"var(--muted)" }}>{label}</span>{children}</label>); }
function Badge({ text, color }) { return <span className="inline-block rounded-md px-2 py-0.5" style={{ fontSize:11.5, fontWeight:700, color, background:`color-mix(in srgb, ${color} 16%, transparent)` }}>{text}</span>; }
function Tag({ color, label }) { return (<span className="flex items-center gap-2" style={{ fontSize:12.5, color:"var(--muted)" }}><span style={{ width:11, height:11, borderRadius:3, background:color, display:"inline-block" }}/>{label}</span>); }

/* ---- HétMásolás Modal ---------------------------------------------- */
function CopyWeekModal({ year, week, monday, onClose, onCopy }) {
  const options = Array.from({ length:8 }, (_,i) => week+1+i);
  const [targets, setTargets]   = useState(new Set([week+1, week+2, week+3]));
  const [overwrite, setOverwrite] = useState(true);
  const [q, setQ]               = useState("");
  const toggle = (set, key, setter) => { const n=new Set(set); n.has(key)?n.delete(key):n.add(key); setter(n); };
  useEffect(() => { const h=(e)=>e.key==="Escape"&&onClose(); document.addEventListener("keydown",h); return ()=>document.removeEventListener("keydown",h); }, [onClose]);
  const filtered = options.filter((w) => String(w).includes(q.trim()));
  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center p-4 sm:p-6 mb-scroll" style={{ overflowY:"auto" }}>
      <div className="mb-back fixed inset-0" style={{ background:"rgba(4,6,10,.55)", backdropFilter:"blur(4px)" }} onClick={onClose}/>
      <div className="mb-pop relative w-full rounded-2xl overflow-hidden" style={{ maxWidth:600, background:"var(--surface)", border:"1px solid var(--border)", boxShadow:"0 50px 100px -28px rgba(0,0,0,.6)", marginTop:20 }}>
        <div className="flex items-center justify-between gap-3 px-6 py-4" style={{ borderBottom:"1px solid var(--border)" }}><h2 className="mb-display" style={{ fontSize:19, fontWeight:700 }}>Hét másolása</h2><button onClick={onClose} className="mb-btn flex h-8 w-8 items-center justify-center rounded-lg" style={{ color:"var(--muted)", background:"var(--surface-2)" }}>{Ico.x()}</button></div>
        <div className="px-6 py-5 flex flex-col gap-5">
          <div className="flex items-center justify-between gap-4">
            <span style={{ fontSize:13.5, fontWeight:600, color:"var(--muted)" }}>Forrás hét</span>
            <div className="flex items-center gap-2 rounded-lg px-3 py-2" style={{ background:"var(--surface-2)", border:"1px solid var(--border)" }}><span style={{ color:"var(--faint)" }}>{Ico.calendar({width:15,height:15})}</span><span style={{ fontSize:13.5, fontWeight:700 }}>{year}. {week}. hét</span><span className="mb-mono" style={{ fontSize:11.5, color:"var(--faint)" }}>{weekRange(year,week)}</span></div>
          </div>
          <div>
            <div style={{ fontSize:13.5, fontWeight:600, color:"var(--muted)", marginBottom:8 }}>Célhetek</div>
            <div className="rounded-xl overflow-hidden" style={{ border:"1px solid var(--border)" }}>
              <div className="relative p-2" style={{ borderBottom:"1px solid var(--border)" }}><span className="absolute left-4 top-1/2 -translate-y-1/2" style={{ color:"var(--faint)" }}>{Ico.search()}</span><input value={q} onChange={(e)=>setQ(e.target.value)} placeholder="Keresés…" className="mb-in py-2 pl-9 pr-3" style={{ fontSize:13 }}/></div>
              <div className="mb-scroll" style={{ maxHeight:160, overflowY:"auto", padding:8 }}>
                {filtered.map((w) => (<div key={w} className="px-1 py-1.5"><Check checked={targets.has(w)} onChange={()=>toggle(targets,w,setTargets)} label={`${year}. ${w}. hét  ·  ${weekRange(year,w)}`}/></div>))}
              </div>
            </div>
            {targets.size>0 && <div className="flex flex-wrap gap-1.5 mt-2">{Array.from(targets).sort((a,b)=>a-b).map((w)=>(<span key={w} className="flex items-center gap-1 rounded-md px-2 py-1" style={{ fontSize:12, fontWeight:600, background:"var(--brand-soft)", color:"var(--brand-ink)" }}>{w}. hét <button onClick={()=>toggle(targets,w,setTargets)} style={{ color:"var(--brand-ink)" }}>{Ico.x({width:12,height:12})}</button></span>))}</div>}
          </div>
          <div className="flex items-center justify-between gap-4 py-3" style={{ borderTop:"1px solid var(--border)", borderBottom:"1px solid var(--border)" }}>
            <span style={{ fontSize:13.5, fontWeight:600 }}>Felülírás, ha már van beosztás</span>
            <Toggle on={overwrite} onChange={setOverwrite}/>
          </div>
        </div>
        <div className="flex items-center justify-end gap-2 px-6 py-4" style={{ borderTop:"1px solid var(--border)", background:"var(--bg)" }}>
          <button onClick={onClose} className="mb-btn rounded-lg px-4 py-2.5" style={{ fontSize:13.5, fontWeight:600, color:"var(--muted)", border:"1px solid var(--border)" }}>Mégse</button>
          <button disabled={targets.size===0} onClick={()=>onCopy(week, Array.from(targets), overwrite)} className="mb-prim flex items-center gap-1.5 rounded-lg px-5 py-2.5" style={{ fontSize:13.5, fontWeight:700, color:"#fff", background:targets.size===0?"var(--faint)":"var(--brand)", cursor:targets.size===0?"not-allowed":"pointer" }}>{Ico.copy()} Másolás</button>
        </div>
      </div>
    </div>
  );
}

/* ---- TérképPopover -------------------------------------------------- */
function MapPopover({ booking, onClose }) {
  useEffect(() => { const h=(e)=>e.key==="Escape"&&onClose(); document.addEventListener("keydown",h); return ()=>document.removeEventListener("keydown",h); }, [onClose]);
  return (
    <div className="fixed inset-0 flex items-center justify-center p-4" style={{ zIndex:60 }}>
      <div className="mb-back fixed inset-0" style={{ background:"rgba(4,6,10,.5)", backdropFilter:"blur(3px)" }} onClick={onClose}/>
      <div className="mb-pop relative w-full rounded-2xl overflow-hidden" style={{ maxWidth:360, background:"var(--surface)", border:"1px solid var(--border)", boxShadow:"0 40px 80px -24px rgba(0,0,0,.6)" }}>
        <div className="flex items-center justify-between px-4 py-3" style={{ borderBottom:"1px solid var(--border)" }}><span className="flex items-center gap-2 min-w-0" style={{ fontSize:13.5, fontWeight:700 }}><span style={{ color:CATS[booking.cat]?.color||"var(--muted)" }}>{Ico.pin()}</span><span className="truncate">{booking.title}</span></span><button onClick={onClose} className="mb-btn flex h-7 w-7 items-center justify-center rounded-lg" style={{ color:"var(--muted)", background:"var(--surface-2)" }}>{Ico.x()}</button></div>
        <div className="p-4"><RoomMap booking={booking}/></div>
      </div>
    </div>
  );
}

/* ---- Kártya --------------------------------------------------------- */
function RedBadge({ text }) { return <span className="rounded px-1.5 py-0.5" style={{ fontFamily:"Manrope", fontSize:10, fontWeight:700, color:"var(--danger-ink)", background:"var(--danger-soft)" }}>{text}</span>; }

function Card({ b, conflict, overlap, onOpen, onMap, query, roleFilter }) {
  const q = query.trim().toLowerCase();
  const docs   = (b.staff||[]).filter((s)=>s.role==="d");
  const nurses = (b.staff||[]).filter((s)=>s.role==="n");
  const noDoc  = b.cat!=="kiszallas" && docs.length===0;
  const red    = noDoc||conflict;
  const accent = red?"var(--danger)":(CATS[b.cat]?.color||"var(--muted)");
  const names  = (b.staff||[]).map((s)=>s.name).filter(Boolean);
  const hit    = q && [b.title,...names].some((x)=>x&&x.toLowerCase().includes(q));
  const overlapDouble = (overlap||[]).filter((o)=>!o.vac);
  const overlapVac    = (overlap||[]).filter((o)=>o.vac);
  const StaffChip = ({ s, color, soft, icon }) => {
    const diff = s.from!==b.from||s.to!==b.to;
    const onVac = overlapVac.some((o)=>o.p===s.name);
    return (<span className="flex items-center gap-1 rounded-md px-1.5 py-0.5" style={{ background:onVac?"var(--danger-soft)":soft, color:onVac?"var(--danger-ink)":color, fontSize:11.5, fontWeight:600, maxWidth:"100%" }}><span style={{ flexShrink:0 }}>{icon}</span><span className="truncate">{s.name}</span>{diff && <span className="mb-mono" style={{ fontSize:10, opacity:.85, flexShrink:0 }}>{s.from}–{s.to}</span>}</span>);
  };
  return (
    <div className="mb-tcard relative rounded-lg" onClick={onOpen} style={{ background:red?`color-mix(in srgb,var(--danger) 13%,var(--card))`:"var(--card)", border:`1px solid ${red?"var(--danger)":"var(--border)"}`, borderLeft:`3px solid ${accent}`, padding:"8px 9px 9px 10px", boxShadow:hit?"0 0 0 2px var(--brand)":"none" }}>
      <button onClick={(e)=>{e.stopPropagation();onMap();}} title="Hely a térképen" className="absolute right-1.5 top-1.5 flex h-6 w-6 items-center justify-center rounded-md" style={{ color:"var(--faint)" }}>{Ico.place({width:14,height:14})}</button>
      <div className="mb-mono flex items-center gap-1.5 flex-wrap pr-6" style={{ fontSize:11.5, color:"var(--muted)", fontWeight:500 }}><span>{b.from} – {b.to}</span>{overlapDouble.length>0&&<RedBadge text="Ütközés"/>}{overlapVac.length>0&&<RedBadge text="Szabadságon"/>}{noDoc&&<RedBadge text="Nincs orvos"/>}</div>
      <div className="truncate mt-0.5" style={{ fontSize:13.5, fontWeight:700 }}>{b.title}</div>
      {!!b.address && <div className="truncate" style={{ fontSize:11.5, color:"var(--muted)", fontWeight:600 }}>{b.address}</div>}
      <div className="flex flex-wrap gap-1.5 mt-1.5">
        {roleFilter!=="n"&&docs.map((s,i)  => <StaffChip key={"d"+i} s={s} color="var(--blue)"   soft="var(--blue-soft)"   icon={Ico.doctor({width:12,height:12})}/>)}
        {roleFilter!=="d"&&nurses.map((s,i) => <StaffChip key={"n"+i} s={s} color="var(--purple)" soft="var(--purple-soft)" icon={Ico.person({width:12,height:12})}/>)}
      </div>
      {b.note&&!conflict&&<div className="mt-1.5" style={{ fontSize:11, color:"var(--faint)" }}>{b.note}</div>}
      {overlapDouble[0]&&<div className="mt-1.5" style={{ fontSize:11, fontWeight:600, color:"var(--danger-ink)" }}>Átfedés: {overlapDouble[0].p} {overlapDouble[0].from}–{overlapDouble[0].to}</div>}
      {overlapVac[0]&&<div className="mt-1.5" style={{ fontSize:11, fontWeight:600, color:"var(--danger-ink)" }}>{overlapVac[0].p} szabadságon van{overlapVac[0].status===0?" (függő kérelem)":""}</div>}
    </div>
  );
}

/* ---- Csoport -------------------------------------------------------- */
function Group({ cat, di, items, collapsed, onToggle, conf, onOpenCard, onMap, query, roleFilter }) {
  const c = CATS[cat]; const catIcon = Ico[c.icon];
  return (
    <div className="rounded-xl overflow-hidden" style={{ background:"var(--surface)", border:"1px solid var(--border-soft)" }}>
      <button onClick={onToggle} className="flex w-full items-center justify-between px-3 py-2.5" style={{ borderBottom:collapsed?"none":"1px solid var(--border-soft)", background:`color-mix(in srgb,${c.color} 8%,transparent)` }}>
        <span className="flex items-center gap-2"><span style={{ color:c.color, flexShrink:0 }}>{catIcon({width:15,height:15})}</span><span style={{ fontSize:13, fontWeight:700 }}>{c.label}</span><span className="rounded-md px-1.5" style={{ fontSize:11, fontWeight:700, color:"var(--muted)", background:"var(--surface-2)" }}>{items.length}</span></span>
        <span style={{ color:"var(--faint)" }}>{collapsed?Ico.chevDown({width:16,height:16}):Ico.chevUp({width:16,height:16})}</span>
      </button>
      {!collapsed && (<div className="flex flex-col gap-1.5 p-2">
        {items.map((b) => <Card key={b.id} b={b} conflict={conf.set.has(`${di}:${b.id}`)} overlap={conf.det[`${di}:${b.id}`]} query={query} roleFilter={roleFilter} onOpen={()=>onOpenCard(b)} onMap={()=>onMap(b)}/>)}
        {items.length===0 && <div className="px-1 py-2" style={{ fontSize:12, color:"var(--faint)" }}>Nincs rendelés.</div>}
      </div>)}
    </div>
  );
}

/* ---- Listás nézet ---------------------------------------------------- */
function ListView({ weekDays, dayDates, conf, matches, collapsed, onToggle, onOpenCard, onMap }) {
  const Th = ({ children }) => <th className="mb-display" style={{ textAlign:"left", padding:"9px 12px", fontSize:11, fontWeight:700, letterSpacing:".04em", color:"var(--faint)", textTransform:"uppercase", borderBottom:"1px solid var(--border)", whiteSpace:"nowrap" }}>{children}</th>;
  const Td = ({ children, mono, style }) => <td className={mono?"mb-mono":""} style={{ padding:"9px 12px", fontSize:13, verticalAlign:"middle", ...style }}>{children}</td>;

  return (
    <div className="mb-scroll px-4 lg:px-6 py-4 flex flex-col gap-3" style={{ flex:"1 1 auto", minHeight:0, overflowY:"auto" }}>
      {HU_DAYS.map((_, di) => {
        const rows = weekDays[di].filter((b)=>matches(b,di)).sort((a,b)=>toMin(a.from)-toMin(b.from));
        const dayConflict = weekDays[di].some((b)=>conf.set.has(`${di}:${b.id}`));
        const hol  = holidayOf(iso(dayDates[di]));
        const rest = di===6 || !!hol;
        const key  = `list:${di}`;
        const isCollapsed = !!collapsed[key];
        return (
          <div key={di} className="rounded-xl overflow-hidden" style={{ border:"1px solid var(--border-soft)", background:"var(--surface)" }}>
            <button onClick={()=>onToggle(key)} className="flex w-full items-center justify-between px-3 py-2.5" style={{ borderBottom:isCollapsed?"none":"1px solid var(--border-soft)", background:(di>=5||hol)?"var(--weekend)":"transparent" }}>
              <div className="flex items-baseline gap-2">
                <span className="mb-display" style={{ fontSize:13.5, fontWeight:700, letterSpacing:".03em", color:rest?"var(--danger)":"var(--ink)" }}>{HU_DAYS_UP[di]}</span>
                <span className="flex items-center justify-center rounded-md" style={{ minWidth:18, height:18, padding:"0 4px", fontSize:11, fontWeight:700, color:"var(--muted)", background:"var(--surface-2)" }}>{rows.length}</span>
                {hol && <span style={{ fontSize:11, fontWeight:700, color:"var(--danger-ink)" }}>{hol}</span>}
              </div>
              <div className="flex items-center gap-2">
                {dayConflict && <span className="mb-pulse" style={{ color:"var(--danger)" }} title="Ütközés">{Ico.alert({width:14,height:14})}</span>}
                <span className="mb-mono" style={{ fontSize:11, color:rest?"var(--danger-ink)":"var(--faint)", textTransform:"uppercase" }}>{HU_MON_SHORT[dayDates[di].getUTCMonth()]} {dayDates[di].getUTCDate()}.</span>
                <span style={{ color:"var(--faint)" }}>{isCollapsed?Ico.chevDown({width:16,height:16}):Ico.chevUp({width:16,height:16})}</span>
              </div>
            </button>
            {!isCollapsed && (
              <table className="w-full" style={{ borderCollapse:"collapse" }}>
                <thead><tr>
                  <Th>Időpont</Th><Th>Kategória</Th><Th>Helyszín</Th><Th>Orvos(ok)</Th><Th>Asszisztens(ek)</Th><Th>Megjegyzés</Th>
                </tr></thead>
                <tbody>
                  {rows.map((b) => {
                    const docs   = (b.staff||[]).filter((s)=>s.role==="d");
                    const nurses = (b.staff||[]).filter((s)=>s.role==="n");
                    const noDoc  = docs.length===0;
                    const isConf = conf.set.has(`${di}:${b.id}`);
                    const overlap = conf.det[`${di}:${b.id}`]||[];
                    const hasDouble = overlap.some((o)=>!o.vac);
                    const hasVac    = overlap.some((o)=>o.vac);
                    const red    = noDoc || isConf;
                    return (
                      <tr key={b.id} onClick={()=>onOpenCard(b,di)} style={{ borderBottom:"1px solid var(--border-soft)", background:red?"color-mix(in srgb,var(--danger) 10%,transparent)":"transparent", cursor:"pointer" }}>
                        <Td mono>{b.from} – {b.to}</Td>
                        <Td><Badge text={CATS[b.cat]?.type||b.cat} color={CATS[b.cat]?.color||"var(--muted)"}/></Td>
                        <Td>
                          <div className="flex items-center gap-1.5 flex-wrap">
                            <span style={{ fontWeight:600 }}>{b.title}</span>
                            <button onClick={(e)=>{ e.stopPropagation(); onMap(b); }} title="Hely a térképen" style={{ color:"var(--faint)" }}>{Ico.place({width:13,height:13})}</button>
                            {hasDouble && <RedBadge text="Ütközés"/>}
                            {hasVac && <RedBadge text="Szabadságon"/>}
                            {noDoc && <RedBadge text="Nincs orvos"/>}
                          </div>
                        </Td>
                        <Td>{docs.length ? docs.map((d)=>d.name).join(", ") : <span style={{ color:"var(--faint)" }}>—</span>}</Td>
                        <Td>{nurses.length ? nurses.map((n)=>n.name).join(", ") : <span style={{ color:"var(--faint)" }}>—</span>}</Td>
                        <Td style={{ color:"var(--faint)" }}>{b.note||""}</Td>
                      </tr>
                    );
                  })}
                  {rows.length===0 && <tr><td colSpan={6} style={{ padding:"24px 12px", textAlign:"center", color:"var(--faint)", fontSize:12.5 }}>Nincs találat.</td></tr>}
                </tbody>
              </table>
            )}
          </div>
        );
      })}
    </div>
  );
}

/* ---- Ütközés nézet ---------------------------------------------------- */
function ConflictView({ weekDays, conf, catFilter, onOpenCard, onMap }) {
  const items = [];
  weekDays.forEach((day, di) => {
    day.forEach((b) => {
      if (catFilter!=="all" && b.cat!==catFilter) return;
      const key = `${di}:${b.id}`;
      if (conf.set.has(key)) items.push({ b, di, overlaps: conf.det[key]||[] });
    });
  });
  items.sort((a,b) => (a.di-b.di) || (toMin(a.b.from)-toMin(b.b.from)));

  if (items.length===0) {
    return (
      <div className="flex items-center justify-center" style={{ color:"var(--muted)", flex:"1 1 auto", minHeight:0 }}>
        <div className="flex flex-col items-center gap-3">
          <span style={{ color:"var(--green)" }}>{Ico.alert({width:32,height:32})}</span>
          <div style={{ fontSize:14, fontWeight:600 }}>Nincs ütközés ezen a héten.</div>
        </div>
      </div>
    );
  }

  return (
    <div className="mb-scroll px-4 lg:px-6 py-4 flex flex-col gap-2.5" style={{ flex:"1 1 auto", minHeight:0, overflowY:"auto", maxWidth:760 }}>
      {items.map(({ b, di, overlaps }) => {
        const hasDouble = overlaps.some((o)=>!o.vac);
        const hasVac    = overlaps.some((o)=>o.vac);
        return (
        <div key={`${di}-${b.id}`} onClick={()=>onOpenCard(b,di)} className="mb-tcard rounded-lg p-3" style={{ background:"color-mix(in srgb,var(--danger) 13%,var(--card))", border:"1px solid var(--danger)" }}>
          <div className="flex items-center justify-between gap-2 flex-wrap">
            <div className="flex items-center gap-2 flex-wrap">
              {hasDouble && <RedBadge text="Ütközés"/>}
              {hasVac && <RedBadge text="Szabadságon"/>}
              <Badge text={CATS[b.cat]?.type||b.cat} color={CATS[b.cat]?.color||"var(--muted)"}/>
              <span style={{ fontSize:12, fontWeight:700, color:"var(--muted)" }}>{HU_DAYS[di]}, {fmtShortISO(b.date)}</span>
              <span className="mb-mono" style={{ fontSize:12, color:"var(--muted)" }}>{b.from}–{b.to}</span>
            </div>
            <button onClick={(e)=>{ e.stopPropagation(); onMap(b); }} title="Hely a térképen" style={{ color:"var(--faint)" }}>{Ico.place({width:14,height:14})}</button>
          </div>
          <div style={{ fontSize:14, fontWeight:700, marginTop:5 }}>{b.title}</div>
          <div className="flex flex-col gap-0.5 mt-1.5">
            {overlaps.map((o,i) => o.vac
              ? <div key={i} style={{ fontSize:12, fontWeight:600, color:"var(--danger-ink)" }}>{o.p} szabadságon van{o.status===0?" (függő kérelem)":""}</div>
              : <div key={i} style={{ fontSize:12, fontWeight:600, color:"var(--danger-ink)" }}>Átfedés: {o.p} ({o.from}–{o.to})</div>
            )}
          </div>
        </div>
        );
      })}
    </div>
  );
}

/* ---- LoadingBlock ---------------------------------------------------- */
function LoadingBlock({ label }) {
  return (
    <div className="flex items-center justify-center" style={{ color:"var(--muted)", flex:"1 1 auto", minHeight:0 }}>
      <div className="flex flex-col items-center gap-3">
        <div className="mb-pulse">{Ico.refresh({width:32,height:32})}</div>
        <div style={{ fontSize:14, fontWeight:600 }}>{label}</div>
      </div>
    </div>
  );
}

/* ---- StaffView / StaffModal (Munkatársak) ---------------------------- */
function StaffView({ setToast, newSignal }) {
  const [data, setData]       = useState(null);
  const [loading, setLoading] = useState(true);
  const [query, setQuery]     = useState("");
  const [modal, setModal]     = useState(null);
  const [saving, setSaving]   = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    fetch(`${HMM_CONFIG.url}&getstaff=1`).then((r)=>r.json()).then((d)=>{ setData(d); setLoading(false); }).catch(()=>setLoading(false));
  }, []);
  useEffect(() => { load(); }, [load]);

  useEffect(() => { if (newSignal) setModal({ worker:null, roleid:(data?.roles||[])[0]?.id }); }, [newSignal]);

  const post = async (params) => {
    const body = new URLSearchParams(params);
    const resp = await fetch(HMM_CONFIG.url, { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:body.toString() });
    return resp.json();
  };

  const save = async (rec) => {
    setSaving(true);
    try {
      const result = await post({
        savestaff:"1", id:rec.id||"0", roleid:rec.roleid, nev:rec.nev, teljesnev:rec.teljesnev,
        email:rec.email, tel:rec.tel, smsert:rec.smsert?"1":"", emailert:rec.emailert?"1":"",
        efo:rec.efo?"1":"", beouserid:rec.beouserid||"0",
      });
      if (result.status==="ok") { await load(); setModal(null); setToast("Munkatárs mentve!"); }
      else setToast("Hiba: "+(result.message||"Ismeretlen hiba"));
    } catch(e) { setToast("Hálózati hiba!"); }
    finally { setSaving(false); }
  };

  const remove = async (id) => {
    setSaving(true);
    try {
      const result = await post({ deletestaff:"1", id });
      if (result.status==="ok") { await load(); setModal(null); setToast("Munkatárs törölve."); }
      else setToast("Hiba: "+(result.message||"Ismeretlen hiba"));
    } catch(e) { setToast("Hálózati hiba!"); }
    finally { setSaving(false); }
  };

  if (loading || !data) return <LoadingBlock label="Munkatársak betöltése…"/>;

  const q = query.trim().toLowerCase();
  const groups = (data.roles||[]).map((role) => ({
    role,
    workers: (data.workers||[]).filter((w) => w.roleid===role.id && (!q || `${w.teljesnev} ${w.nev} ${w.email} ${w.tel}`.toLowerCase().includes(q))),
  }));

  return (
    <div className="mb-scroll px-4 lg:px-6 py-4" style={{ flex:"1 1 auto", minHeight:0, overflowY:"auto" }}>
      <div className="relative max-w-xs mb-3">
        <span className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color:"var(--faint)" }}>{Ico.search()}</span>
        <input value={query} onChange={(e)=>setQuery(e.target.value)} placeholder="Keresés…" className="mb-in py-2 pl-9 pr-3" style={{ fontSize:13 }}/>
      </div>
      <div className="flex flex-col gap-3" style={{ maxWidth:760 }}>
        {groups.map(({ role, workers }) => (
          <div key={role.id} className="rounded-xl overflow-hidden" style={{ background:"var(--surface)", border:"1px solid var(--border-soft)" }}>
            <div className="flex items-center justify-between px-3 py-2.5" style={{ borderBottom:"1px solid var(--border-soft)", background:"color-mix(in srgb,var(--brand) 8%,transparent)" }}>
              <span className="flex items-center gap-2">
                <span style={{ color:"var(--brand)" }}>{Ico.users({width:15,height:15})}</span>
                <span style={{ fontSize:13, fontWeight:700 }}>{role.megnev}</span>
                <span className="rounded-md px-1.5" style={{ fontSize:11, fontWeight:700, color:"var(--muted)", background:"var(--surface-2)" }}>{workers.length}</span>
              </span>
              <button onClick={()=>setModal({ worker:null, roleid:role.id })} className="mb-add flex items-center gap-1 rounded-lg px-2.5 py-1.5" style={{ fontSize:12, fontWeight:600, color:"var(--brand-ink)", border:"1px dashed var(--brand)" }}>{Ico.plus()} Új</button>
            </div>
            <div className="flex flex-col gap-1.5 p-2">
              {workers.map((w) => (
                <div key={w.id} onClick={()=>setModal({ worker:w })} className="mb-tcard flex items-center justify-between gap-3 rounded-lg" style={{ background:"var(--card)", border:"1px solid var(--border)", padding:"8px 10px" }}>
                  <div className="min-w-0">
                    <div className="truncate" style={{ fontSize:13.5, fontWeight:700 }}>{w.teljesnev||w.nev}</div>
                    <div className="flex flex-wrap gap-x-3" style={{ fontSize:11.5, color:"var(--muted)" }}>
                      {w.email && <span>{w.email}</span>}
                      {w.tel && <span>{w.tel}</span>}
                    </div>
                  </div>
                  <div className="flex items-center gap-1.5 flex-shrink-0">
                    {!!w.efo && <Badge text="EFO" color="var(--purple)"/>}
                    {!!w.onVacation && <Badge text="Szabadságon" color="var(--danger)"/>}
                    {!!w.smsert && <Badge text="SMS" color="var(--blue)"/>}
                    {!!w.emailert && <Badge text="Email" color="var(--green)"/>}
                  </div>
                </div>
              ))}
              {workers.length===0 && <div className="px-1 py-2" style={{ fontSize:12, color:"var(--faint)" }}>Nincs munkatárs.</div>}
            </div>
          </div>
        ))}
      </div>
      {modal && <StaffModal ctx={modal} roles={data.roles} users={data.users} onClose={()=>setModal(null)} onSave={save} onDelete={remove} saving={saving}/>}
    </div>
  );
}

function StaffModal({ ctx, roles, users, onClose, onSave, onDelete, saving }) {
  const w = ctx.worker;
  const [nev, setNev]             = useState(w ? w.nev : "");
  const [teljesnev, setTeljesnev] = useState(w ? w.teljesnev : "");
  const [roleid, setRoleid]       = useState(w ? w.roleid : ctx.roleid);
  const [email, setEmail]         = useState(w ? w.email : "");
  const [tel, setTel]             = useState(w ? w.tel : "");
  const [smsert, setSmsert]       = useState(w ? !!w.smsert : true);
  const [emailert, setEmailert]   = useState(w ? !!w.emailert : true);
  const [efo, setEfo]             = useState(w ? !!w.efo : false);
  const [beouserid, setBeouserid] = useState(() => (users||[]).find((u)=>u.beouserid===(w?w.id:-1))?.id || 0);

  useEffect(() => { const h=(e)=>e.key==="Escape"&&onClose(); document.addEventListener("keydown",h); return ()=>document.removeEventListener("keydown",h); }, [onClose]);

  const invalid = nev.trim()==="";
  const save = () => onSave({ id:w?w.id:0, nev, teljesnev, roleid, email, tel, smsert, emailert, efo, beouserid });

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center p-4 sm:p-6 mb-scroll" style={{ overflowY:"auto" }}>
      <div className="mb-back fixed inset-0" style={{ background:"rgba(4,6,10,.55)", backdropFilter:"blur(4px)" }} onClick={onClose}/>
      <div className="mb-pop relative w-full rounded-2xl overflow-hidden" style={{ maxWidth:480, background:"var(--surface)", border:"1px solid var(--border)", boxShadow:"0 50px 100px -28px rgba(0,0,0,.6)", marginTop:40 }}>
        <div className="flex items-center justify-between gap-3 px-6 py-4" style={{ borderBottom:"1px solid var(--border)" }}>
          <h2 className="mb-display" style={{ fontSize:18, fontWeight:700 }}>{w ? (w.teljesnev||w.nev) : "Új munkatárs"}</h2>
          <button onClick={onClose} className="mb-btn flex h-8 w-8 items-center justify-center rounded-lg" style={{ color:"var(--muted)", background:"var(--surface-2)" }}>{Ico.x()}</button>
        </div>
        <div className="flex flex-col gap-3.5 px-6 py-5">
          <Field label="Rövid név">
            <input value={nev} onChange={(e)=>setNev(e.target.value)} className="mb-in px-3 py-2.5" style={{ fontSize:13.5 }}/>
          </Field>
          <Field label="Teljes név">
            <input value={teljesnev} onChange={(e)=>setTeljesnev(e.target.value)} className="mb-in px-3 py-2.5" style={{ fontSize:13.5 }}/>
          </Field>
          <Field label="Típus">
            <MiniSelect value={String(roleid)} onChange={(v)=>setRoleid(Number(v))} options={(roles||[]).map((r)=>({ v:String(r.id), l:r.megnev }))}/>
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Telefon"><input value={tel} onChange={(e)=>setTel(e.target.value)} className="mb-in px-3 py-2.5" style={{ fontSize:13.5 }}/></Field>
            <Field label="Email"><input value={email} onChange={(e)=>setEmail(e.target.value)} className="mb-in px-3 py-2.5" style={{ fontSize:13.5 }}/></Field>
          </div>
          <Field label="Rendszerfelhasználó">
            <MiniSelect value={String(beouserid)} onChange={(v)=>setBeouserid(Number(v))} options={[{ v:"0", l:"Nincs összekapcsolva" }, ...(users||[]).map((u)=>({ v:String(u.id), l:u.nev }))]}/>
          </Field>
          <div className="flex items-center gap-5 mt-1">
            <Check checked={smsert} onChange={setSmsert} label="SMS értesítés"/>
            <Check checked={emailert} onChange={setEmailert} label="Email értesítés"/>
          </div>
          <div className="flex items-center justify-between rounded-lg px-3 py-2.5" style={{ background:"var(--surface-2)" }}>
            <span style={{ fontSize:13, fontWeight:500 }}>EFO (egyszerűsített foglalkoztatás)</span>
            <Toggle on={efo} onChange={setEfo}/>
          </div>
        </div>
        <div className="flex items-center justify-between gap-3 px-6 py-4" style={{ borderTop:"1px solid var(--border)", background:"var(--bg)" }}>
          <div>{w && <button onClick={()=>onDelete(w.id)} className="flex items-center gap-1.5 rounded-lg px-3 py-2" style={{ fontSize:13, fontWeight:600, color:"var(--danger-ink)" }}>{Ico.trash()} Törlés</button>}</div>
          <div className="flex items-center gap-2">
            <button onClick={onClose} className="mb-btn rounded-lg px-4 py-2.5" style={{ fontSize:13.5, fontWeight:600, color:"var(--muted)", border:"1px solid var(--border)" }}>Mégse</button>
            <button disabled={invalid||saving} onClick={save} className="mb-prim flex items-center gap-1.5 rounded-lg px-5 py-2.5" style={{ fontSize:13.5, fontWeight:700, color:"#fff", background:(invalid||saving)?"var(--faint)":"var(--brand)", cursor:(invalid||saving)?"not-allowed":"pointer" }}>{saving?"Mentés…":<>{Ico.save()} Mentés</>}</button>
          </div>
        </div>
      </div>
    </div>
  );
}

/* ---- PlacesView / LocationModal (Munkahelyek) ------------------------ */
function PlacesView({ setToast, newSignal }) {
  const [data, setData]       = useState(null);
  const [loading, setLoading] = useState(true);
  const [modal, setModal]     = useState(null);
  const [saving, setSaving]   = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    fetch(`${HMM_CONFIG.url}&getplaces=1`).then((r)=>r.json()).then((d)=>{ setData(d); setLoading(false); }).catch(()=>setLoading(false));
  }, []);
  useEffect(() => { load(); }, [load]);

  useEffect(() => { if (newSignal) void addNew("1","0"); }, [newSignal]);

  const post = async (params) => {
    const body = new URLSearchParams(params);
    const resp = await fetch(HMM_CONFIG.url, { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:body.toString() });
    return resp.json();
  };

  const save = async (rec) => {
    setSaving(true);
    try {
      const result = await post({ saveplace:"1", id:rec.id, megnev:rec.megnev, cim:rec.cim, sorrend:rec.sorrend });
      if (result.status==="ok") { await load(); setModal(null); setToast("Helyszín mentve!"); }
      else setToast("Hiba: "+(result.message||"Ismeretlen hiba"));
    } catch(e) { setToast("Hálózati hiba!"); }
    finally { setSaving(false); }
  };

  const remove = async (id) => {
    setSaving(true);
    try {
      const result = await post({ deleteplace:"1", id });
      if (result.status==="ok") { await load(); setModal(null); setToast("Helyszín törölve."); }
      else setToast("Hiba: "+(result.message||"Ismeretlen hiba"));
    } catch(e) { setToast("Hálózati hiba!"); }
    finally { setSaving(false); }
  };

  const addNew = async (roleid, kulso) => {
    const result = await post({ addplace:"1", roleid, kulso });
    if (result.status==="ok") await load();
  };

  const addKiszallas = async ({ megnev, cim, org }) => {
    const result = await post({ addplace:"1", roleid:"1", kulso:"0", kiszallas:"1", megnev, cim, org });
    if (result.status==="ok") { await load(); setModal(null); setToast("Kiszállás létrehozva!"); }
    else setToast("Hiba: "+(result.message||"Ismeretlen hiba"));
  };

  const reorder = async (id, direction) => {
    await post({ orderplace:"1", id, direction });
    await load();
  };

  if (loading || !data) return <LoadingBlock label="Helyszínek betöltése…"/>;

  const groupsMap = new Map();
  (data.places||[]).forEach((p) => {
    const key = p.kiszallas===1 ? "kiszallas" : (p.kulso===0 ? `belso-${p.roleid}` : "kulso");
    if (!groupsMap.has(key)) groupsMap.set(key, { kulso:p.kulso, kiszallas:p.kiszallas||0, roleid:p.roleid, places:[] });
    groupsMap.get(key).places.push(p);
  });
  if (!groupsMap.has("kiszallas")) groupsMap.set("kiszallas", { kulso:0, kiszallas:1, roleid:1, places:[] });
  const groups = Array.from(groupsMap.values()).sort((a,b) => (a.kiszallas||0) - (b.kiszallas||0));

  return (
    <div className="mb-scroll px-4 lg:px-6 py-4" style={{ flex:"1 1 auto", minHeight:0, overflowY:"auto" }}>
      <div className="flex flex-col gap-3" style={{ maxWidth:760 }}>
        {groups.map((g) => {
          let label = g.kiszallas ? "Kiszállások" : (g.kulso===0 ? "Belső" : "Külső");
          if (!g.kiszallas && g.kulso===0 && g.roleid===1) label += " - Orvos";
          if (!g.kiszallas && g.kulso===0 && g.roleid===3) label += " - Egyéb";
          const accent = g.kiszallas ? "var(--purple)" : (g.kulso===0 ? "var(--brand)" : "var(--green)");
          const groupIcon = g.kiszallas ? "truck" : "building";
          return (
            <div key={g.kiszallas?"kiszallas":`${g.kulso}-${g.roleid}`} className="rounded-xl overflow-hidden" style={{ background:"var(--surface)", border:"1px solid var(--border-soft)" }}>
              <div className="flex items-center justify-between px-3 py-2.5" style={{ borderBottom:"1px solid var(--border-soft)", background:`color-mix(in srgb,${accent} 8%,transparent)` }}>
                <span className="flex items-center gap-2">
                  <span style={{ color:accent }}>{Ico[groupIcon]({width:15,height:15})}</span>
                  <span style={{ fontSize:13, fontWeight:700 }}>{label}</span>
                  <span className="rounded-md px-1.5" style={{ fontSize:11, fontWeight:700, color:"var(--muted)", background:"var(--surface-2)" }}>{g.places.length}</span>
                </span>
                <button onClick={()=>g.kiszallas ? setModal({ kiszallas:true }) : addNew(g.roleid, g.kulso)} className="mb-add flex items-center gap-1 rounded-lg px-2.5 py-1.5" style={{ fontSize:12, fontWeight:600, color:"var(--brand-ink)", border:"1px dashed var(--brand)" }}>{Ico.plus()} Új</button>
              </div>
              <div className="flex flex-col gap-1.5 p-2">
                {g.places.map((p, idx) => (
                  <div key={p.id} className="mb-tcard flex items-center justify-between gap-3 rounded-lg" style={{ background:"var(--card)", border:"1px solid var(--border)", padding:"8px 10px" }}>
                    <div className="min-w-0 flex-1" onClick={()=>setModal({ place:p })} style={{ cursor:"pointer" }}>
                      <div className="flex items-center gap-1.5">
                        <div className="truncate" style={{ fontSize:13.5, fontWeight:700 }}>{p.megnev}</div>
                        {(g.kiszallas || g.kulso===1) && <Badge text={p.org||"HMM"} color={p.org==="Keltexmed"?"var(--purple)":"var(--brand)"}/>}
                      </div>
                      {!!p.cim && <div className="truncate" style={{ fontSize:11.5, color:"var(--muted)" }}>{p.cim}</div>}
                    </div>
                    <div className="flex items-center gap-1 flex-shrink-0">
                      <button onClick={()=>reorder(p.id,"up")} disabled={idx===0} className="mb-btn flex h-7 w-7 items-center justify-center rounded-md" style={{ color:idx===0?"var(--faint)":"var(--muted)", border:"1px solid var(--border)", opacity:idx===0?.4:1 }}>{Ico.chevUp({width:14,height:14})}</button>
                      <button onClick={()=>reorder(p.id,"down")} disabled={idx===g.places.length-1} className="mb-btn flex h-7 w-7 items-center justify-center rounded-md" style={{ color:idx===g.places.length-1?"var(--faint)":"var(--muted)", border:"1px solid var(--border)", opacity:idx===g.places.length-1?.4:1 }}>{Ico.chevDown({width:14,height:14})}</button>
                    </div>
                  </div>
                ))}
                {g.places.length===0 && <div className="px-1 py-2" style={{ fontSize:12, color:"var(--faint)" }}>{g.kiszallas ? "Nincs kiszállás." : "Nincs helyszín."}</div>}
              </div>
            </div>
          );
        })}
      </div>
      {modal && modal.place && <LocationModal ctx={modal} onClose={()=>setModal(null)} onSave={save} onDelete={remove} saving={saving}/>}
      {modal && modal.kiszallas && <NewKiszallasModal places={data.places||[]} onClose={()=>setModal(null)} onSave={addKiszallas} saving={saving}/>}
    </div>
  );
}

function LocationModal({ ctx, onClose, onSave, onDelete, saving }) {
  const p = ctx.place;
  const [megnev, setMegnev] = useState(p.megnev||"");
  const [cim, setCim]       = useState(p.cim||"");
  const [org, setOrg]       = useState(p.org||"HMM");

  useEffect(() => { const h=(e)=>e.key==="Escape"&&onClose(); document.addEventListener("keydown",h); return ()=>document.removeEventListener("keydown",h); }, [onClose]);

  const invalid = megnev.trim()==="";
  const save = () => onSave({ id:p.id, megnev, cim, sorrend:p.sorrend, org });

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center p-4 sm:p-6 mb-scroll" style={{ overflowY:"auto" }}>
      <div className="mb-back fixed inset-0" style={{ background:"rgba(4,6,10,.55)", backdropFilter:"blur(4px)" }} onClick={onClose}/>
      <div className="mb-pop relative w-full rounded-2xl overflow-hidden" style={{ maxWidth:460, background:"var(--surface)", border:"1px solid var(--border)", boxShadow:"0 50px 100px -28px rgba(0,0,0,.6)", marginTop:40 }}>
        <div className="flex items-center justify-between gap-3 px-6 py-4" style={{ borderBottom:"1px solid var(--border)" }}>
          <h2 className="mb-display" style={{ fontSize:18, fontWeight:700 }}>{p.megnev||"Új helyszín"}</h2>
          <button onClick={onClose} className="mb-btn flex h-8 w-8 items-center justify-center rounded-lg" style={{ color:"var(--muted)", background:"var(--surface-2)" }}>{Ico.x()}</button>
        </div>
        <div className="flex flex-col gap-3.5 px-6 py-5">
          <Field label="Megnevezés">
            <input value={megnev} onChange={(e)=>setMegnev(e.target.value)} className="mb-in px-3 py-2.5" style={{ fontSize:13.5 }}/>
          </Field>
          {(p.kulso===1 || p.kiszallas===1) && (
            <Field label="Cím">
              <div className="flex items-center gap-2">
                <input value={cim} onChange={(e)=>setCim(e.target.value)} className="mb-in px-3 py-2.5" style={{ fontSize:13.5 }}/>
                {!!cim && <a href={`https://www.google.com/maps/place/${encodeURIComponent(cim)}`} target="_blank" rel="noreferrer" style={{ color:"var(--faint)", flexShrink:0 }}>{Ico.place({width:16,height:16})}</a>}
              </div>
            </Field>
          )}
          {(p.kulso===1 || p.kiszallas===1) && (
            <Field label="Szervező">
              <MiniSelect value={org} onChange={setOrg} options={[{v:"HMM",l:"HMM"},{v:"Keltexmed",l:"Keltexmed"}]}/>
            </Field>
          )}
        </div>
        <div className="flex items-center justify-between gap-3 px-6 py-4" style={{ borderTop:"1px solid var(--border)", background:"var(--bg)" }}>
          <button onClick={()=>onDelete(p.id)} className="flex items-center gap-1.5 rounded-lg px-3 py-2" style={{ fontSize:13, fontWeight:600, color:"var(--danger-ink)" }}>{Ico.trash()} Törlés</button>
          <div className="flex items-center gap-2">
            <button onClick={onClose} className="mb-btn rounded-lg px-4 py-2.5" style={{ fontSize:13.5, fontWeight:600, color:"var(--muted)", border:"1px solid var(--border)" }}>Mégse</button>
            <button disabled={invalid||saving} onClick={save} className="mb-prim flex items-center gap-1.5 rounded-lg px-5 py-2.5" style={{ fontSize:13.5, fontWeight:700, color:"#fff", background:(invalid||saving)?"var(--faint)":"var(--brand)", cursor:(invalid||saving)?"not-allowed":"pointer" }}>{saving?"Mentés…":<>{Ico.save()} Mentés</>}</button>
          </div>
        </div>
      </div>
    </div>
  );
}

function NewKiszallasModal({ places, onClose, onSave, saving }) {
  const [megnev, setMegnev] = useState("");
  const [cim, setCim]       = useState("");
  const [org, setOrg]       = useState("HMM");

  useEffect(() => { const h=(e)=>e.key==="Escape"&&onClose(); document.addEventListener("keydown",h); return ()=>document.removeEventListener("keydown",h); }, [onClose]);

  const suggestions = useMemo(() => Array.from(new Set(
    (places||[]).filter((p)=>p.kiszallas===1 || p.kulso===1).map((p)=>p.megnev)
  )), [places]);

  const invalid = megnev.trim()==="";
  const save = () => onSave({ megnev:megnev.trim(), cim:cim.trim(), org });

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center p-4 sm:p-6 mb-scroll" style={{ overflowY:"auto" }}>
      <div className="mb-back fixed inset-0" style={{ background:"rgba(4,6,10,.55)", backdropFilter:"blur(4px)" }} onClick={onClose}/>
      <div className="mb-pop relative w-full rounded-2xl overflow-hidden" style={{ maxWidth:460, background:"var(--surface)", border:"1px solid var(--border)", boxShadow:"0 50px 100px -28px rgba(0,0,0,.6)", marginTop:40 }}>
        <div className="flex items-center justify-between gap-3 px-6 py-4" style={{ borderBottom:"1px solid var(--border)" }}>
          <h2 className="mb-display" style={{ fontSize:18, fontWeight:700 }}>Új kiszállás</h2>
          <button onClick={onClose} className="mb-btn flex h-8 w-8 items-center justify-center rounded-lg" style={{ color:"var(--muted)", background:"var(--surface-2)" }}>{Ico.x()}</button>
        </div>
        <div className="flex flex-col gap-3.5 px-6 py-5">
          <Field label="Kiszállás neve">
            <Combobox value={megnev||null} onChange={(v)=>setMegnev(v||"")} options={suggestions} placeholder="pl. Mentőállomás – Szeged" kind="n"/>
            <input value={megnev} onChange={(e)=>setMegnev(e.target.value)} placeholder="Vagy írd be a nevet…" className="mb-in px-3 py-2.5 mt-1.5" style={{ fontSize:13.5 }}/>
          </Field>
          <Field label="Cím">
            <input value={cim} onChange={(e)=>setCim(e.target.value)} className="mb-in px-3 py-2.5" style={{ fontSize:13.5 }}/>
          </Field>
          <Field label="Szervező">
            <MiniSelect value={org} onChange={setOrg} options={[{v:"HMM",l:"HMM"},{v:"Keltexmed",l:"Keltexmed"}]}/>
          </Field>
        </div>
        <div className="flex items-center justify-end gap-2 px-6 py-4" style={{ borderTop:"1px solid var(--border)", background:"var(--bg)" }}>
          <button onClick={onClose} className="mb-btn rounded-lg px-4 py-2.5" style={{ fontSize:13.5, fontWeight:600, color:"var(--muted)", border:"1px solid var(--border)" }}>Mégse</button>
          <button disabled={invalid||saving} onClick={save} className="mb-prim flex items-center gap-1.5 rounded-lg px-5 py-2.5" style={{ fontSize:13.5, fontWeight:700, color:"#fff", background:(invalid||saving)?"var(--faint)":"var(--brand)", cursor:(invalid||saving)?"not-allowed":"pointer" }}>{saving?"Mentés…":<>{Ico.save()} Létrehozás</>}</button>
        </div>
      </div>
    </div>
  );
}

/* ---- VacationsView / VacationModal (Szabadságok) --------------------- */
function VacationsView({ setToast }) {
  const [data, setData]           = useState(null);
  const [loading, setLoading]     = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [busyId, setBusyId]       = useState(null);

  const load = useCallback(() => {
    setLoading(true);
    fetch(`${HMM_CONFIG.url}&getvacations=1`).then((r)=>r.json()).then((d)=>{ setData(d); setLoading(false); }).catch(()=>setLoading(false));
  }, []);
  useEffect(() => { load(); }, [load]);

  const post = async (params) => {
    const body = new URLSearchParams(params);
    const resp = await fetch(HMM_CONFIG.url, { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:body.toString() });
    return resp.json();
  };

  const addVacation = async (rec) => {
    const result = await post({ addvacation:"1", workerid:rec.workerid, tol:rec.tol, ig:rec.ig, tipus:rec.tipus });
    if (result.status==="ok") { await load(); setModalOpen(false); setToast("Szabadság rögzítve!"); }
    else setToast("Hiba: "+(result.message||"Ismeretlen hiba"));
  };

  const setStatus = async (groupid, status) => {
    setBusyId(groupid);
    const result = await post({ setvacationgroupstatus:"1", groupid, status });
    if (result.status==="ok") await load();
    else setToast("Hiba: "+(result.message||"Ismeretlen hiba"));
    setBusyId(null);
  };

  const remove = async (groupid) => {
    setBusyId(groupid);
    const result = await post({ deletevacation:"1", groupid });
    if (result.status==="ok") { await load(); setToast("Szabadság törölve."); }
    else setToast("Hiba: "+(result.message||"Ismeretlen hiba"));
    setBusyId(null);
  };

  if (loading || !data) return <LoadingBlock label="Szabadságok betöltése…"/>;

  const vacations = data.vacations || [];
  const sections = [
    { key:"pending",  label:"Függő kérelmek",          color:"var(--brand)", items:vacations.filter((v)=>v.status===0||v.status===-1) },
    { key:"approved", label:"Elfogadott szabadságok",  color:"var(--green)", items:vacations.filter((v)=>v.status===1) },
    { key:"rejected", label:"Elutasított kérelmek",    color:"var(--danger)", items:vacations.filter((v)=>v.status===2) },
  ];

  return (
    <div className="mb-scroll px-4 lg:px-6 py-4" style={{ flex:"1 1 auto", minHeight:0, overflowY:"auto" }}>
      <div className="flex items-center justify-between gap-3 mb-3" style={{ maxWidth:760 }}>
        <div style={{ fontSize:12.5, color:"var(--muted)" }}>Munkatársak szabadságkérelmeinek kezelése.</div>
        <button onClick={()=>setModalOpen(true)} className="mb-add flex items-center gap-1 rounded-lg px-3 py-2" style={{ fontSize:12.5, fontWeight:600, color:"var(--brand-ink)", border:"1px dashed var(--brand)" }}>{Ico.plus()} Új szabadság</button>
      </div>
      <div className="flex flex-col gap-3" style={{ maxWidth:760 }}>
        {sections.map((sec) => (
          <div key={sec.key} className="rounded-xl overflow-hidden" style={{ background:"var(--surface)", border:"1px solid var(--border-soft)" }}>
            <div className="flex items-center gap-2 px-3 py-2.5" style={{ borderBottom:"1px solid var(--border-soft)", background:`color-mix(in srgb,${sec.color} 8%,transparent)` }}>
              <span style={{ color:sec.color }}>{Ico.sun({width:15,height:15})}</span>
              <span style={{ fontSize:13, fontWeight:700 }}>{sec.label}</span>
              <span className="rounded-md px-1.5" style={{ fontSize:11, fontWeight:700, color:"var(--muted)", background:"var(--surface-2)" }}>{sec.items.length}</span>
            </div>
            <div className="flex flex-col gap-1.5 p-2">
              {sec.items.map((v) => (
                <div key={v.groupid} className="flex items-center justify-between gap-3 rounded-lg flex-wrap" style={{ background:"var(--card)", border:"1px solid var(--border)", padding:"8px 10px" }}>
                  <div className="min-w-0">
                    <div className="flex items-center gap-1.5">
                      <div className="truncate" style={{ fontSize:13.5, fontWeight:700 }}>{v.workerName}</div>
                      <Badge text={v.tipus||"Szabadság"} color={VACATION_TYPE_COLORS[v.tipus]||VACATION_TYPE_COLORS["Szabadság"]}/>
                    </div>
                    <div className="mb-mono" style={{ fontSize:11.5, color:"var(--muted)" }}>
                      {fmtShortISO(v.from)}{v.to!==v.from?` – ${fmtShortISO(v.to)}`:""} · {v.days} nap{v.status===-1?" · vegyes állapot":""}
                    </div>
                  </div>
                  <div className="flex items-center gap-1.5 flex-shrink-0">
                    {sec.key==="pending" && (<>
                      <button disabled={busyId===v.groupid} onClick={()=>setStatus(v.groupid,1)} className="rounded-lg px-2.5 py-1.5" style={{ fontSize:12, fontWeight:600, color:"#fff", background:"var(--green)" }}>Elfogadás</button>
                      <button disabled={busyId===v.groupid} onClick={()=>setStatus(v.groupid,2)} className="rounded-lg px-2.5 py-1.5" style={{ fontSize:12, fontWeight:600, color:"#fff", background:"var(--danger)" }}>Elutasítás</button>
                    </>)}
                    {(sec.key==="approved"||sec.key==="rejected") && (
                      <button disabled={busyId===v.groupid} onClick={()=>setStatus(v.groupid,0)} className="mb-btn rounded-lg px-2.5 py-1.5" style={{ fontSize:12, fontWeight:600, color:"var(--muted)", border:"1px solid var(--border)" }}>Visszavonás</button>
                    )}
                    <button disabled={busyId===v.groupid} onClick={()=>remove(v.groupid)} className="mb-btn flex h-8 w-8 items-center justify-center rounded-lg" style={{ color:"var(--danger-ink)" }}>{Ico.trash()}</button>
                  </div>
                </div>
              ))}
              {sec.items.length===0 && <div className="px-1 py-2" style={{ fontSize:12, color:"var(--faint)" }}>Nincs ilyen szabadság.</div>}
            </div>
          </div>
        ))}
      </div>
      {modalOpen && <VacationModal workers={data.workers} onClose={()=>setModalOpen(false)} onSave={addVacation}/>}
    </div>
  );
}

function VacationModal({ workers, onClose, onSave }) {
  const grouped = useMemo(() => {
    const m = new Map();
    (workers||[]).forEach((w) => { if (!m.has(w.rolenev)) m.set(w.rolenev, []); m.get(w.rolenev).push(w); });
    return Array.from(m.entries());
  }, [workers]);

  const [workerid, setWorkerid] = useState(workers?.[0]?.id ? String(workers[0].id) : "");
  const [tol, setTol]           = useState("");
  const [ig, setIg]             = useState("");
  const [tipus, setTipus]       = useState("Szabadság");
  const [saving, setSaving]     = useState(false);

  useEffect(() => { const h=(e)=>e.key==="Escape"&&onClose(); document.addEventListener("keydown",h); return ()=>document.removeEventListener("keydown",h); }, [onClose]);

  const invalid = !workerid || !tol || !ig || tol > ig;
  const save = async () => { setSaving(true); await onSave({ workerid, tol, ig, tipus }); setSaving(false); };

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center p-4 sm:p-6 mb-scroll" style={{ overflowY:"auto" }}>
      <div className="mb-back fixed inset-0" style={{ background:"rgba(4,6,10,.55)", backdropFilter:"blur(4px)" }} onClick={onClose}/>
      <div className="mb-pop relative w-full rounded-2xl overflow-hidden" style={{ maxWidth:460, background:"var(--surface)", border:"1px solid var(--border)", boxShadow:"0 50px 100px -28px rgba(0,0,0,.6)", marginTop:40 }}>
        <div className="flex items-center justify-between gap-3 px-6 py-4" style={{ borderBottom:"1px solid var(--border)" }}>
          <h2 className="mb-display" style={{ fontSize:18, fontWeight:700 }}>Új szabadság</h2>
          <button onClick={onClose} className="mb-btn flex h-8 w-8 items-center justify-center rounded-lg" style={{ color:"var(--muted)", background:"var(--surface-2)" }}>{Ico.x()}</button>
        </div>
        <div className="flex flex-col gap-3.5 px-6 py-5">
          <Field label="Munkatárs">
            <select value={workerid} onChange={(e)=>setWorkerid(e.target.value)} className="mb-in px-3 py-2.5" style={{ fontSize:13.5, fontWeight:600 }}>
              {grouped.map(([rolenev, ws]) => (
                <optgroup key={rolenev} label={rolenev}>
                  {ws.map((w) => <option key={w.id} value={w.id}>{w.nev}</option>)}
                </optgroup>
              ))}
            </select>
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Kezdő nap"><input type="date" value={tol} onChange={(e)=>setTol(e.target.value)} className="mb-in px-3 py-2.5" style={{ fontSize:13.5 }}/></Field>
            <Field label="Utolsó nap"><input type="date" value={ig} onChange={(e)=>setIg(e.target.value)} className="mb-in px-3 py-2.5" style={{ fontSize:13.5 }}/></Field>
          </div>
          <Field label="Típus">
            <MiniSelect value={tipus} onChange={setTipus} options={VACATION_TYPES.map((t)=>({ v:t, l:t }))}/>
          </Field>
          {tol && ig && tol > ig && <p style={{ fontSize:11.5, color:"var(--danger-ink)" }}>A kezdő nap nem lehet később, mint az utolsó nap.</p>}
        </div>
        <div className="flex items-center justify-end gap-2 px-6 py-4" style={{ borderTop:"1px solid var(--border)", background:"var(--bg)" }}>
          <button onClick={onClose} className="mb-btn rounded-lg px-4 py-2.5" style={{ fontSize:13.5, fontWeight:600, color:"var(--muted)", border:"1px solid var(--border)" }}>Mégse</button>
          <button disabled={invalid||saving} onClick={save} className="mb-prim flex items-center gap-1.5 rounded-lg px-5 py-2.5" style={{ fontSize:13.5, fontWeight:700, color:"#fff", background:(invalid||saving)?"var(--faint)":"var(--brand)", cursor:(invalid||saving)?"not-allowed":"pointer" }}>{saving?"Mentés…":<>{Ico.save()} Mentés</>}</button>
        </div>
      </div>
    </div>
  );
}

/* ---- NotifyView (Értesítések) ---------------------------------------- */
function NotifyView({ setToast }) {
  const [data, setData]       = useState(null);
  const [loading, setLoading] = useState(true);
  const [checks, setChecks]   = useState({});
  const [sending, setSending] = useState(false);
  const [message, setMessage] = useState("");
  const [naplo, setNaplo]     = useState(null);

  const load = useCallback(() => {
    setLoading(true);
    fetch(`${HMM_CONFIG.url}&getnotifications=1`).then((r)=>r.json()).then((d)=>{
      setData(d);
      const init = {};
      (d.items||[]).forEach((it) => { init[it.id] = { sms: it.smsDefault, email: it.emailDefault }; });
      setChecks(init);
      setLoading(false);
    }).catch(()=>setLoading(false));
  }, []);
  useEffect(() => { load(); }, [load]);

  const loadNaplo = useCallback(() => {
    fetch(`${HMM_CONFIG.url}&getnaplo=1`).then((r)=>r.json()).then((d)=>setNaplo(d.items||[])).catch(()=>setNaplo([]));
  }, []);
  useEffect(() => { loadNaplo(); }, [loadNaplo]);

  const toggle = (id, field) => setChecks((c) => ({ ...c, [id]: { ...c[id], [field]: !c[id]?.[field] } }));

  const setAll = (field, value) => setChecks((c) => {
    const next = { ...c };
    (data.items||[]).forEach((it) => {
      if (value && field==="sms"   && !it.phone) return;
      if (value && field==="email" && !it.email) return;
      next[it.id] = { ...next[it.id], [field]: value };
    });
    return next;
  });

  const send = async () => {
    setSending(true);
    let sent = 0;
    for (const it of (data.items||[])) {
      const c = checks[it.id] || {};
      if (!c.sms && !c.email) continue;
      try {
        const body = new URLSearchParams({ sendnotify:"1", workerid:it.id, sms:c.sms?"1":"", email:c.email?"1":"" });
        const resp = await fetch(HMM_CONFIG.url, { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:body.toString() });
        const result = await resp.json();
        if (result.status==="ok") sent++;
      } catch(e) { /* ignore, continue with the rest */ }
    }
    setSending(false);
    setToast(sent>0 ? `${sent} munkatárs értesítve.` : "Nem történt küldés.");
    await load();
    loadNaplo();
  };

  const sendBulk = async () => {
    const recipients = (data.items||[]).map((it)=>{ const c=checks[it.id]||{}; return { workerId:it.id, sms:!!c.sms, email:!!c.email }; }).filter((r)=>r.sms||r.email);
    if (!message.trim() || recipients.length===0) return;
    setSending(true);
    try {
      const body = new URLSearchParams({ sendbulknotify:"1", message, recipients:JSON.stringify(recipients) });
      const resp = await fetch(HMM_CONFIG.url, { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:body.toString() });
      const result = await resp.json();
      if (result.status==="ok") { setToast(`Üzenet kiküldve (${result.sent} címzés).`); setMessage(""); }
      else setToast("Hiba: "+(result.message||"Ismeretlen hiba"));
    } catch(e) { setToast("Hálózati hiba!"); }
    finally { setSending(false); loadNaplo(); }
  };

  if (loading || !data) return <LoadingBlock label="Értesítések betöltése…"/>;

  const items = data.items || [];
  const hasSelection = items.some((it) => { const c=checks[it.id]||{}; return c.sms||c.email; });

  const NAPLO_ICON = { send:"send", copy:"copy", vacation:"sun" };

  return (
    <div className="mb-scroll px-4 lg:px-6 py-4" style={{ flex:"1 1 auto", minHeight:0, overflowY:"auto" }}>
      <div className="flex flex-col gap-3" style={{ maxWidth:760 }}>
        <Field label="Egyéni üzenet (a kijelölt munkatársaknak)">
          <textarea value={message} onChange={(e)=>setMessage(e.target.value)} rows={3} placeholder="Írd ide az üzenetet…" className="mb-in px-3 py-2.5" style={{ fontSize:13.5, resize:"none", fontWeight:500 }}/>
        </Field>
        {items.length===0 ? (
          <div className="rounded-xl px-4 py-6 text-center" style={{ background:"var(--surface)", border:"1px solid var(--border-soft)", color:"var(--muted)", fontSize:13.5 }}>Nem történt változás a beosztásban.</div>
        ) : (<>
          <div className="rounded-xl overflow-hidden" style={{ background:"var(--surface)", border:"1px solid var(--border-soft)" }}>
            <div className="flex items-center justify-between gap-2 px-3 py-2.5 flex-wrap" style={{ borderBottom:"1px solid var(--border-soft)", background:"color-mix(in srgb,var(--brand) 8%,transparent)" }}>
              <span className="flex items-center gap-2">
                <span style={{ color:"var(--brand)" }}>{Ico.bell({width:15,height:15})}</span>
                <span style={{ fontSize:13, fontWeight:700 }}>Beosztás-változás miatt értesítendő munkatársak</span>
                <span className="rounded-md px-1.5" style={{ fontSize:11, fontWeight:700, color:"var(--muted)", background:"var(--surface-2)" }}>{items.length}</span>
              </span>
              <div className="flex items-center gap-3 flex-wrap" style={{ fontSize:11.5, fontWeight:600, color:"var(--muted)" }}>
                <span className="flex items-center gap-1.5">SMS: <button onClick={()=>setAll("sms",true)} className="rounded-md px-1.5 py-0.5" style={{ color:"var(--brand-ink)", background:"var(--surface-2)" }}>Mind</button><button onClick={()=>setAll("sms",false)} className="rounded-md px-1.5 py-0.5" style={{ color:"var(--muted)", background:"var(--surface-2)" }}>Egyik se</button></span>
                <span className="flex items-center gap-1.5">Email: <button onClick={()=>setAll("email",true)} className="rounded-md px-1.5 py-0.5" style={{ color:"var(--brand-ink)", background:"var(--surface-2)" }}>Mind</button><button onClick={()=>setAll("email",false)} className="rounded-md px-1.5 py-0.5" style={{ color:"var(--muted)", background:"var(--surface-2)" }}>Egyik se</button></span>
              </div>
            </div>
            <div className="flex flex-col gap-1.5 p-2">
              {items.map((it) => { const c = checks[it.id]||{}; return (
                <div key={it.id} className="flex items-center justify-between gap-4 rounded-lg flex-wrap" style={{ background:"var(--card)", border:"1px solid var(--border)", padding:"8px 10px" }}>
                  <div style={{ fontSize:13.5, fontWeight:700 }}>{it.name}</div>
                  <div className="flex items-center gap-4 flex-wrap">
                    <Check checked={!!c.sms} onChange={()=>toggle(it.id,"sms")} label={it.phone ? `SMS (${it.phone})` : "SMS (nincs megadva telefonszám)"}/>
                    <Check checked={!!c.email} onChange={()=>toggle(it.id,"email")} label={it.email ? `Email (${it.email})` : "Email (nincs megadva email cím)"}/>
                  </div>
                </div>
              ); })}
            </div>
          </div>
          <div className="flex items-center gap-2 flex-wrap">
            <button onClick={send} disabled={!hasSelection||sending} className="mb-prim flex items-center gap-1.5 rounded-lg px-5 py-2.5" style={{ fontSize:13.5, fontWeight:700, color:"#fff", background:(!hasSelection||sending)?"var(--faint)":"var(--brand)", cursor:(!hasSelection||sending)?"not-allowed":"pointer" }}>{sending?"Küldés…":<>{Ico.send()} Beosztás-értesítés kiküldése</>}</button>
            <button onClick={sendBulk} disabled={!hasSelection||!message.trim()||sending} className="mb-prim flex items-center gap-1.5 rounded-lg px-5 py-2.5" style={{ fontSize:13.5, fontWeight:700, color:"#fff", background:(!hasSelection||!message.trim()||sending)?"var(--faint)":"var(--purple)", cursor:(!hasSelection||!message.trim()||sending)?"not-allowed":"pointer" }}>{sending?"Küldés…":<>{Ico.send()} Egyéni üzenet kiküldése</>}</button>
          </div>
        </>)}

        <div className="rounded-xl overflow-hidden mt-2" style={{ background:"var(--surface)", border:"1px solid var(--border-soft)" }}>
          <div className="flex items-center gap-2 px-3 py-2.5" style={{ borderBottom:"1px solid var(--border-soft)", background:"color-mix(in srgb,var(--muted) 8%,transparent)" }}>
            <span style={{ color:"var(--muted)" }}>{Ico.clock({width:15,height:15})}</span>
            <span style={{ fontSize:13, fontWeight:700 }}>Napló</span>
          </div>
          <div className="flex flex-col gap-1.5 p-2">
            {(naplo||[]).map((n,i) => (
              <div key={i} className="flex items-center gap-2.5 rounded-lg" style={{ background:"var(--card)", border:"1px solid var(--border)", padding:"8px 10px" }}>
                <span style={{ color:"var(--faint)", flexShrink:0 }}>{Ico[NAPLO_ICON[n.tipus]||"bell"]({width:14,height:14})}</span>
                <div className="min-w-0 flex-1 truncate" style={{ fontSize:12.5, fontWeight:600 }}>{n.cim}</div>
                <div className="mb-mono flex-shrink-0" style={{ fontSize:11, color:"var(--faint)" }}>{n.letrehozva}</div>
              </div>
            ))}
            {(naplo||[]).length===0 && <div className="px-1 py-2" style={{ fontSize:12, color:"var(--faint)" }}>Még nincs napló-bejegyzés.</div>}
          </div>
        </div>
      </div>
    </div>
  );
}

/* ---- Kis komponensek ----------------------------------------------- */
function IconBtn({ children, onClick, badge, title }) {
  return (<button onClick={onClick} title={title} className="mb-btn relative flex h-9 w-9 items-center justify-center rounded-lg" style={{ color:"var(--muted)" }}>{children}{badge>0&&<span className="absolute -top-0.5 -right-0.5 flex items-center justify-center rounded-full" style={{ minWidth:16, height:16, padding:"0 4px", fontSize:10, fontWeight:700, color:"#fff", background:"var(--danger)", border:"2px solid var(--surface)" }}>{badge}</span>}</button>);
}
/* ================================================================
 *  FŐ KOMPONENS
 * ================================================================ */
function MunkaidoBeosztas() {
  const [theme,        setTheme]        = useState("light");
  const [sidebarOpen,  setSidebarOpen]  = useState(true);
  const [weekOffset,   setWeekOffset]   = useState(HMM_CONFIG.offset || 0);
  const [weekData,     setWeekData]     = useState(null);
  const [loading,      setLoading]      = useState(true);
  const [saving,       setSaving]       = useState(false);
  const [doctors,      setDoctors]      = useState([]);   // [{id, nev}]
  const [assistants,   setAssistants]   = useState([]);   // [{id, nev}]
  const [query,        setQuery]        = useState("");
  const roleFilter = "all";
  const [catFilter,    setCatFilter]    = useState("all");
  const onlyConflicts = false;
  const [showCatMenu,  setShowCatMenu]  = useState(false);
  const [nav,          setNav]          = useState("board");
  const [collapsed,    setCollapsed]    = useState({});
  const [modal,        setModal]        = useState(null);
  const [mapBk,        setMapBk]        = useState(null);
  const [copyOpen,     setCopyOpen]     = useState(false);
  const [toast,        setToast]        = useState(null);
  const [staffNewSignal, setStaffNewSignal] = useState(0);
  const [placeNewSignal, setPlaceNewSignal] = useState(0);

  /* ---- adatlekérés ---- */
  const fetchWeek = useCallback((offset) => {
    setLoading(true);
    fetch(`${HMM_CONFIG.url}&getweekdata=1&offset=${offset}`)
      .then((r) => r.json())
      .then((data) => {
        setWeekData(data);
        setDoctors(data.doctorsWithId    || []);
        setAssistants(data.assistantsWithId || []);
        setLoading(false);
      })
      .catch((err) => { console.error("fetchWeek:", err); setLoading(false); });
  }, []);

  useEffect(() => { fetchWeek(weekOffset); }, [weekOffset, fetchWeek]);

  /* ---- derivált értékek ---- */
  const year   = weekData?.year   ?? new Date().getFullYear();
  const week   = weekData?.week   ?? 1;
  const parity = week % 2 === 0 ? "Páros hét" : "Páratlan hét";
  const monday = weekData?.monday ?? null;

  const dayDates = useMemo(() => {
    if (!monday) return HU_DAYS.map(() => new Date(Date.UTC(2026,0,1)));
    return HU_DAYS.map((_,i) => { const d=new Date(monday+"T00:00:00Z"); d.setUTCDate(d.getUTCDate()+i); return d; });
  }, [monday]);

  /* ---- mini havi naptár (oldalsáv) ---- */
  const [calCursor, setCalCursor] = useState(() => { const d=new Date(); return new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), 1)); });
  useEffect(() => {
    if (!monday) return;
    const d = new Date(monday+"T00:00:00Z");
    setCalCursor(new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), 1)));
  }, [monday]);
  const shiftCalMonth = (delta) => setCalCursor((c)=> new Date(Date.UTC(c.getUTCFullYear(), c.getUTCMonth()+delta, 1)));
  const calCells = useMemo(() => {
    const first = new Date(Date.UTC(calCursor.getUTCFullYear(), calCursor.getUTCMonth(), 1));
    const dow = (first.getUTCDay()+6)%7;
    const start = new Date(first); start.setUTCDate(start.getUTCDate()-dow);
    return Array.from({length:42},(_,i)=>{ const d=new Date(start); d.setUTCDate(start.getUTCDate()+i); return d; });
  }, [calCursor]);
  const selectedWeekSet = useMemo(() => new Set(dayDates.map(iso)), [dayDates]);
  const todayIso = iso(new Date());
  const gotoDate = (dt) => {
    if (!monday) return;
    const baseMonday = new Date(monday+"T00:00:00Z");
    baseMonday.setUTCDate(baseMonday.getUTCDate() - weekOffset*7);
    const diffDays = Math.round((dt.getTime() - baseMonday.getTime()) / 86400000);
    setWeekOffset(Math.floor(diffDays/7));
  };

  const weekDays = useMemo(() => {
    if (!weekData) return EMPTY_BOARD;
    return weekData.days.map((d) => d.bookings);
  }, [weekData]);

  const vacationsByDay = useMemo(() => {
    if (!weekData) return EMPTY_BOARD;
    return weekData.days.map((d) => d.vacations || []);
  }, [weekData]);

  const conf = useMemo(() => analyzeDays(weekDays, vacationsByDay), [weekDays, vacationsByDay]);

  useEffect(() => { if (!toast) return; const t=setTimeout(()=>setToast(null),4200); return ()=>clearTimeout(t); }, [toast]);

  /* ---- szűrés ---- */
  const q = query.trim().toLowerCase();
  const conflictView = onlyConflicts || nav==="conflicts";
  const matches = (b, di) => {
    if (conflictView && !conf.set.has(`${di}:${b.id}`)) return false;
    if (catFilter!=="all" && b.cat!==catFilter) return false;
    if (q) { const names=(b.staff||[]).map((s)=>s.name); if (![b.title,...names].some((x)=>x&&x.toLowerCase().includes(q))) return false; }
    return true;
  };
  const filtering = conflictView || !!q;

  /* ---- mentés ---- */
  const saveBooking = useCallback(async (rec) => {
    const staff = (rec.staff||[]).filter((s)=>s.name && s.workerId);
    const dates = (rec.dates && rec.dates.length) ? rec.dates : [rec.date];
    setSaving(true);
    try {
      let tipusId = rec.tipusId;
      if (!tipusId) {
        const body = new URLSearchParams({ addplace:"1", roleid:"1", kulso: rec.cat==="kulso"?"1":"0", kiszallas: rec.cat==="kiszallas"?"1":"0", org:"HMM", megnev: rec.title||"", cim: rec.address||"", megj: rec.note||"" });
        const resp = await fetch(HMM_CONFIG.url, { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:body.toString() });
        const result = await resp.json();
        if (result.status!=="ok") { setToast("Hiba: "+(result.message||"Ismeretlen hiba")); setSaving(false); return; }
        tipusId = result.id;
      } else if (rec.id) {
        const body = new URLSearchParams({ updateplaceaddress:"1", id:tipusId, cim: rec.address||"", megj: rec.note||"" });
        await fetch(HMM_CONFIG.url, { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:body.toString() });
      }
      let allOk = true;
      for (const datum of dates) {
        const body = new URLSearchParams({ savebooking:"1", tipusid:tipusId||"", datum:datum||"", staff:JSON.stringify(staff) });
        const resp = await fetch(HMM_CONFIG.url, { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:body.toString() });
        const result = await resp.json();
        if (result.status!=="ok") { allOk = false; setToast("Hiba: "+(result.message||"Ismeretlen hiba")); break; }
      }
      if (allOk) {
        await fetchWeek(weekOffset);
        setModal(null);
        setToast(dates.length>1 ? `Beosztás mentve ${dates.length} napra.` : "Beosztás mentve!");
      }
    } catch(e) { setToast("Hálózati hiba a mentés során!"); }
    finally { setSaving(false); }
  }, [weekOffset, fetchWeek]);

  const deleteBooking = useCallback(async (bookingId) => {
    let booking = null;
    for (const day of (weekData?.days||[])) { booking=day.bookings.find((b)=>b.id===bookingId); if(booking) break; }
    if (!booking) return;
    await saveBooking({ ...booking, staff:[] });
  }, [weekData, saveBooking]);

  /* ---- hét másolása ---- */
  const copyWeek = useCallback(async (sourceWeek, targets, overwrite) => {
    const sourceMonday = monday;
    if (!sourceMonday) return;
    let done = 0;
    for (const targetWeek of targets) {
      const targetDate = iso(isoWeekMonday(year, targetWeek));
      if (targetDate===sourceMonday) continue;
      try {
        const resp = await fetch(`${HMM_CONFIG.url}&copyweekjson=1&copyfrom=${sourceMonday}&copyto=${targetDate}&overwrite=${overwrite?1:0}`);
        const result = await resp.json();
        if (result.status==="ok") done++;
      } catch(e) { console.error("copyWeek error:", e); }
    }
    await fetchWeek(weekOffset);
    setCopyOpen(false);
    setToast(`${sourceWeek}. hét átmásolva ${done} hétre.`);
  }, [monday, year, weekOffset, fetchWeek]);

  /* ---- navigáció ---- */
  const NAV = [
    { id:"board",     icon:Ico.board,    label:"Tábla nézet" },
    { id:"list",      icon:Ico.list,     label:"Listás nézet" },
    { id:"conflicts", icon:Ico.alert,    label:"Ütközések", badge:conf.set.size },
    { id:"workplaces",icon:Ico.building, label:"Munkahelyek" },
    { id:"workers",   icon:Ico.doctor,   label:"Munkatársak" },
    { id:"vacations", icon:Ico.sun,      label:"Szabadságok" },
    { id:"copy",      icon:Ico.copy,     label:"Hét másolása" },
    { id:"notify",    icon:Ico.bell,     label:"Értesítések" },
    { id:"print",     icon:Ico.list,     label:"Nyomtatás" },
  ];
  const onNav = (id) => {
    if (id==="copy") { setCopyOpen(true); return; }
    if (["print"].includes(id)) {
      window.location.href = `${HMM_CONFIG.url}&subpage=${id}`;
      return;
    }
    setNav(id);
  };

  /* ------------------------------------------------------------------ */
  return (
    <div className={`mb-root mb-${theme} min-h-screen w-full`} style={{ height:"100vh", overflow:"hidden" }}>
      <Styles/>
      <div className="flex" style={{ height:"100%" }}>
        {/* SIDEBAR */}
        <aside className="mb-sidebar mb-no-print flex" style={{ width:sidebarOpen?232:0, transition:"width .28s ease", flexShrink:0, overflow:"hidden", borderRight:sidebarOpen?"1px solid var(--border)":"none" }}>
          <div className="flex flex-col" style={{ width:232, height:"100%", background:"var(--sidebar)" }}>
            <div className="px-4 py-3.5 flex flex-col gap-2" style={{ borderBottom:"1px solid var(--border)" }}>
              <div className="flex items-center gap-2.5"><LogoMark size={34}/><div className="leading-none"><div className="mb-display" style={{ fontSize:18, fontWeight:800, color:"var(--brand-ink)", letterSpacing:".02em" }}>HMM</div><div style={{ fontSize:8, fontWeight:700, letterSpacing:".22em", color:"var(--muted)", marginTop:3 }}>HUNGÁRIA&nbsp;MED-M</div></div></div>
              <div style={{ fontSize:12.5, fontWeight:600, color:"var(--muted)" }}>Munkaidő beosztás</div>
            </div>
            <nav className="flex-1 overflow-y-auto mb-scroll px-2.5 py-3 flex flex-col gap-0.5">
              <a href="index.php" className="mb-nav flex items-center gap-3 rounded-lg px-3 py-2 mb-1.5" style={{ fontSize:13.5, fontWeight:500, color:"var(--muted)", textDecoration:"none", borderBottom:"1px solid var(--border)", paddingBottom:10 }}><span style={{ color:"var(--faint)", flexShrink:0 }}>{Ico.left({width:18,height:18})}</span><span className="flex-1 truncate">Vissza az adminba</span></a>
              {NAV.map((n) => { const active=nav===n.id; return (<button key={n.id} onClick={()=>onNav(n.id)} className="mb-nav flex items-center gap-3 rounded-lg px-3 py-2 text-left" style={{ fontSize:13.5, fontWeight:active?700:500, color:active?"var(--brand-ink)":"var(--muted)", background:active?"var(--brand-soft)":"transparent", boxShadow:active?"inset 2px 0 0 var(--brand)":"none" }}><span style={{ color:active?"var(--brand)":"var(--faint)", flexShrink:0 }}>{n.icon({width:18,height:18})}</span><span className="flex-1 truncate">{n.label}</span>{n.badge>0&&<span className="rounded-full px-1.5" style={{ fontSize:10.5, fontWeight:700, color:"var(--danger-ink)", background:"var(--danger-soft)" }}>{n.badge}</span>}</button>); })}
            </nav>
            <div className="px-3 py-3" style={{ borderTop:"1px solid var(--border)" }}>
              <div className="flex items-center justify-between mb-2">
                <button onClick={()=>shiftCalMonth(-1)} className="mb-btn flex h-7 w-7 items-center justify-center rounded-md" style={{ color:"var(--muted)", border:"1px solid var(--border)" }}>{Ico.left({width:14,height:14})}</button>
                <span style={{ fontSize:12.5, fontWeight:700 }}>{HU_MONTHS[calCursor.getUTCMonth()]} {calCursor.getUTCFullYear()}</span>
                <button onClick={()=>shiftCalMonth(1)} className="mb-btn flex h-7 w-7 items-center justify-center rounded-md" style={{ color:"var(--muted)", border:"1px solid var(--border)" }}>{Ico.right({width:14,height:14})}</button>
              </div>
              <div className="grid grid-cols-7 gap-1 text-center">
                {HU_DAYS_1.map((dd)=><div key={dd} style={{ fontSize:10.5, color:"var(--faint)", fontWeight:600 }}>{dd}</div>)}
                {calCells.map((dt,i)=>{
                  const ds = iso(dt);
                  const inMonth = dt.getUTCMonth()===calCursor.getUTCMonth();
                  const hol = !!holidayOf(ds);
                  const rest = dt.getUTCDay()===0 || hol;
                  const isToday = ds===todayIso;
                  const inSelWeek = selectedWeekSet.has(ds);
                  return (
                    <button key={i} type="button" onClick={()=>gotoDate(dt)} className="mx-auto flex items-center justify-center rounded-full" style={{
                      width:24, height:24, fontSize:12, fontWeight:isToday?800:700,
                      color: isToday ? "#fff" : !inMonth ? "var(--faint)" : rest ? "var(--danger-ink)" : "var(--muted)",
                      background: isToday ? "var(--brand)" : inSelWeek ? "var(--brand-soft)" : "transparent",
                      opacity: inMonth?1:.45
                    }}>{dt.getUTCDate()}</button>
                  );
                })}
              </div>
            </div>
          </div>
        </aside>

        {/* FŐ TARTALOM */}
        <div className="flex-1 flex flex-col" style={{ minWidth:0, minHeight:0 }}>
          {/* FEJLÉC */}
          <header className="mb-no-print flex items-center gap-4 px-4 lg:px-6" style={{ height:56, borderBottom:"1px solid var(--border)", background:"var(--surface)", flexShrink:0 }}>
            <IconBtn onClick={()=>setSidebarOpen((v)=>!v)} title={sidebarOpen?"Menü elrejtése":"Menü megnyitása"}>{Ico.menu({width:19,height:19})}</IconBtn>
            <div className="md:hidden"><LogoMark size={28}/></div>
            <div className="relative flex-1 max-w-xl mx-auto">
              <span className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color:"var(--faint)" }}>{Ico.search()}</span>
              <input value={query} onChange={(e)=>setQuery(e.target.value)} placeholder="Keresés orvosra, asszisztensre, helyiségre…" className="mb-in py-2 pl-10 pr-16" style={{ fontSize:13.5 }}/>
              <span className="absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center gap-0.5 rounded-md px-1.5 py-0.5" style={{ fontSize:11, color:"var(--faint)", border:"1px solid var(--border)" }}>{Ico.cmd()} K</span>
            </div>
            <div className="flex items-center gap-1.5">
              <IconBtn onClick={()=>setTheme((t)=>t==="dark"?"light":"dark")} title="Téma váltása">{theme==="dark"?Ico.sun({width:18,height:18}):Ico.moon({width:18,height:18})}</IconBtn>
              <IconBtn onClick={()=>onNav("conflicts")} badge={conf.set.size}>{Ico.bell()}</IconBtn>
              <IconBtn title="Súgó">{Ico.help()}</IconBtn>
              <div className="flex items-center gap-2 pl-2 ml-1" style={{ borderLeft:"1px solid var(--border)" }}>
                <div className="flex h-8 w-8 items-center justify-center rounded-full" style={{ background:"var(--brand)", color:"#fff", fontSize:12, fontWeight:700 }}>{(HMM_CONFIG.adminName||"A").charAt(0).toUpperCase()}</div>
                <div className="hidden lg:block leading-tight"><div style={{ fontSize:12.5, fontWeight:700 }}>{HMM_CONFIG.adminName||"Admin"}</div><div style={{ fontSize:11, color:"var(--muted)" }}>Adminisztrátor</div></div>
              </div>
            </div>
          </header>

          {/* ESZKÖZTÁR */}
          <div className="flex flex-wrap items-center gap-3 px-4 lg:px-6 py-3" style={{ borderBottom:"1px solid var(--border)", flexShrink:0 }}>
            <h1 className="mb-display" style={{ fontSize:20, fontWeight:700 }}>{nav==="workers"?"Munkatársak":nav==="workplaces"?"Munkahelyek":nav==="vacations"?"Szabadságok":nav==="notify"?"Értesítések":"Munkaidő beosztás"}</h1>
            {!["workers","workplaces","vacations","notify"].includes(nav) && (<>
            <div className="flex items-center gap-2 ml-1">
              <button onClick={()=>setWeekOffset((w)=>w-1)} className="mb-btn flex h-8 w-8 items-center justify-center rounded-lg" style={{ color:"var(--muted)", border:"1px solid var(--border)" }}>{Ico.left()}</button>
              <button onClick={()=>setWeekOffset((w)=>w+1)} className="mb-btn flex h-8 w-8 items-center justify-center rounded-lg" style={{ color:"var(--muted)", border:"1px solid var(--border)" }}>{Ico.right()}</button>
              <span style={{ color:"var(--faint)" }}>{Ico.calendar({width:16,height:16})}</span>
              <span className="mb-display" style={{ fontSize:15, fontWeight:700 }}>{year}. {week}. hét</span>
              <span className="rounded-md px-2 py-0.5" style={{ fontSize:11.5, fontWeight:700, color:"var(--green)", background:"var(--green-soft)" }}>{parity}</span>
              {weekOffset!==0 && <button onClick={()=>setWeekOffset(0)} className="rounded-md px-2 py-0.5" style={{ fontSize:11.5, fontWeight:600, color:"var(--brand-ink)", background:"var(--brand-soft)" }}>Aktuális</button>}
            </div>
            <div className="flex items-center gap-2 ml-auto">
              {/* Kategória */}
              <div className="relative">
                <button onClick={()=>setShowCatMenu((v)=>!v)} className="mb-btn flex items-center gap-1.5 rounded-lg px-3 py-2" style={{ fontSize:13, fontWeight:600, color:"var(--muted)", border:"1px solid var(--border)" }}>Kategória: <span style={{ color:"var(--ink)" }}>{catFilter==="all"?"Összes":CATS[catFilter]?.label}</span> {Ico.chevDown({width:14,height:14})}</button>
                {showCatMenu && (<><div className="fixed inset-0 z-40" onClick={()=>setShowCatMenu(false)}/><div className="mb-pop absolute right-0 z-50 mt-1.5 rounded-xl p-1.5" style={{ width:210, background:"var(--surface)", border:"1px solid var(--border)", boxShadow:"0 20px 44px -16px rgba(0,0,0,.5)" }}>{[{v:"all",l:"Összes"},...CAT_ORDER.map((c)=>({v:c,l:CATS[c].label}))].map((o)=>(<button key={o.v} onClick={()=>{ setCatFilter(o.v); setShowCatMenu(false); }} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left" style={{ fontSize:13, fontWeight:catFilter===o.v?700:500, color:catFilter===o.v?"var(--brand-ink)":"var(--ink)", background:catFilter===o.v?"var(--brand-soft)":"transparent" }}>{o.v!=="all"&&<span style={{ width:8, height:8, borderRadius:2, background:CATS[o.v].color }}/>}{o.l}</button>))}</div></>)}
              </div>
              <button onClick={()=>setCopyOpen(true)} className="mb-btn flex h-9 w-9 items-center justify-center rounded-lg" style={{ color:"var(--muted)", border:"1px solid var(--border)" }} title="Hét másolása">{Ico.copy({width:16,height:16})}</button>
              {nav==="board" && <button onClick={()=>setModal({day:0, cat:"belso", booking:null})} className="mb-prim flex items-center gap-1.5 rounded-lg px-3.5 py-2" style={{ fontSize:13, fontWeight:700, color:"#fff", background:"var(--brand)" }}>{Ico.plus()} Új rendelés</button>}
            </div>
            </>)}
            {nav==="workers" && <button onClick={()=>setStaffNewSignal((s)=>s+1)} className="mb-prim ml-auto flex items-center gap-1.5 rounded-lg px-3.5 py-2" style={{ fontSize:13, fontWeight:700, color:"#fff", background:"var(--brand)" }}>{Ico.plus()} Új munkatárs</button>}
            {nav==="workplaces" && <button onClick={()=>setPlaceNewSignal((s)=>s+1)} className="mb-prim ml-auto flex items-center gap-1.5 rounded-lg px-3.5 py-2" style={{ fontSize:13, fontWeight:700, color:"#fff", background:"var(--brand)" }}>{Ico.plus()} Új helyszín</button>}
          </div>

          {/* TÁBLA */}
          <div className="mb-board flex-1 mb-scroll" style={nav==="board" ? { overflow:"auto" } : { display:"flex", flexDirection:"column", overflowX:"hidden", overflowY:"hidden", minHeight:0 }}>
            {loading ? (
              <div className="flex items-center justify-center" style={{ color:"var(--muted)", flex:"1 1 auto", minHeight:0 }}>
                <div className="flex flex-col items-center gap-3">
                  <div className="mb-pulse">{Ico.refresh({width:32,height:32})}</div>
                  <div style={{ fontSize:14, fontWeight:600 }}>Beosztás betöltése…</div>
                </div>
              </div>
            ) : nav==="list" ? (
              <ListView weekDays={weekDays} dayDates={dayDates} conf={conf} matches={matches} collapsed={collapsed} onToggle={(key)=>setCollapsed((p)=>({...p,[key]:!p[key]}))} onOpenCard={(b,di)=>setModal({ day:di, cat:b.cat, booking:b, date:b.date })} onMap={(b)=>setMapBk(b)}/>
            ) : nav==="conflicts" ? (
              <ConflictView weekDays={weekDays} conf={conf} catFilter={catFilter} onOpenCard={(b,di)=>setModal({ day:di, cat:b.cat, booking:b, date:b.date })} onMap={(b)=>setMapBk(b)}/>
            ) : nav==="workers" ? (
              <StaffView setToast={setToast} newSignal={staffNewSignal}/>
            ) : nav==="workplaces" ? (
              <PlacesView setToast={setToast} newSignal={placeNewSignal}/>
            ) : nav==="vacations" ? (
              <VacationsView setToast={setToast}/>
            ) : nav==="notify" ? (
              <NotifyView setToast={setToast}/>
            ) : (
              <div className="flex items-start gap-3 px-4 lg:px-6 py-4" style={{ minWidth:"min-content", minHeight:"min-content" }}>
                {HU_DAYS.map((day, di) => {
                  const dayConflict = weekDays[di].some((b)=>conf.set.has(`${di}:${b.id}`));
                  const hol  = holidayOf(iso(dayDates[di]));
                  const rest = di===6 || !!hol;
                  return (
                    <div key={day} className="mb-col flex flex-col rounded-xl" style={{ width:278, flexShrink:0, background:(di>=5||hol)?"var(--weekend)":"var(--bg)", border:"1px solid var(--border-soft)" }}>
                      <div className="px-3 py-2.5" style={{ borderBottom:"1px solid var(--border)" }}>
                        <div className="flex items-center justify-between">
                          <div className="flex items-baseline gap-2">
                            <span className="mb-display" style={{ fontSize:13.5, fontWeight:700, letterSpacing:".03em", color:rest?"var(--danger)":"var(--ink)" }}>{HU_DAYS_UP[di]}</span>
                            <span className="flex items-center justify-center rounded-md" style={{ minWidth:18, height:18, padding:"0 4px", fontSize:11, fontWeight:700, color:"var(--muted)", background:"var(--surface-2)" }}>{weekDays[di].length}</span>
                          </div>
                          <div className="flex items-center gap-1.5">
                            {dayConflict && <span className="mb-pulse" style={{ color:"var(--danger)" }} title="Ütközés">{Ico.alert({width:14,height:14})}</span>}
                            <span className="mb-mono" style={{ fontSize:11, color:rest?"var(--danger-ink)":"var(--faint)", textTransform:"uppercase" }}>{HU_MON_SHORT[dayDates[di].getUTCMonth()]} {dayDates[di].getUTCDate()}.</span>
                          </div>
                        </div>
                        {hol && <div className="flex items-center gap-1 mt-1" style={{ fontSize:10.5, fontWeight:700, color:"var(--danger-ink)" }}><span style={{ width:6, height:6, borderRadius:99, background:"var(--danger)", display:"inline-block" }}/> {hol} · munkaszüneti nap</div>}
                      </div>
                      <div className="mb-colbody flex flex-col gap-2.5 p-2.5">
                        {CAT_ORDER.filter((c)=>catFilter==="all"||c===catFilter).map((cat) => {
                          const items = weekDays[di].filter((b)=>b.cat===cat && matches(b,di));
                          const key   = `${di}:${cat}`;
                          if (filtering && items.length===0) return null;
                          return (<Group key={cat} cat={cat} di={di} items={items} collapsed={!!collapsed[key]} onToggle={()=>setCollapsed((p)=>({...p,[key]:!p[key]}))} conf={conf} query={query} roleFilter={roleFilter} onOpenCard={(b)=>setModal({ day:di, cat:b.cat, booking:b, date:b.date })} onMap={(b)=>setMapBk(b)}/>);
                        })}
                        {filtering && weekDays[di].filter((b)=>matches(b,di)).length===0 && <div className="text-center py-6" style={{ fontSize:12, color:"var(--faint)" }}>Nincs találat.</div>}
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* MODÁLOK */}
      {modal && <EditModal ctx={modal} dayDates={dayDates} onClose={()=>setModal(null)} onSave={saveBooking} onDelete={deleteBooking} onMap={(b)=>setMapBk(b)} doctorList={doctors} assistantList={assistants} places={weekData?.places||[]} saving={saving}/>}
      {copyOpen && <CopyWeekModal year={year} week={week} monday={monday} onClose={()=>setCopyOpen(false)} onCopy={copyWeek}/>}
      {mapBk && <MapPopover booking={mapBk} onClose={()=>setMapBk(null)}/>}

      {/* TOAST */}
      {toast && (
        <div className="mb-toast fixed left-1/2 -translate-x-1/2 flex items-center gap-2 rounded-xl px-4 py-3" style={{ bottom:24, zIndex:70, background:"var(--surface)", border:"1px solid var(--border)", boxShadow:"0 24px 50px -16px rgba(0,0,0,.55)", maxWidth:"92vw" }}>
          <span className="flex h-7 w-7 items-center justify-center rounded-full" style={{ background:"var(--green-soft)", color:"var(--green)" }}><svg viewBox="0 0 24 24" width="15" height="15" fill="none"><path d="M5 12.5l4 4 10-10" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"/></svg></span>
          <span style={{ fontSize:13, fontWeight:600 }}>{toast}</span>
          <button onClick={()=>setToast(null)} className="ml-1" style={{ color:"var(--faint)" }}>{Ico.x()}</button>
        </div>
      )}
    </div>
  );
}

/* ---- Mountolás ------------------------------------------------------ */
const _root = ReactDOM.createRoot(document.getElementById("hmm-schedule-root"));
_root.render(<MunkaidoBeosztas/>);