"use strict";

// #region Konfiguráció és segédfüggvények
// Globális beállítások, dátum/idő helperek, ünnepnapok, kategória- és szabadságtípus-definíciók, alaprajz adatok.
const { useState, useEffect, useMemo, useCallback, useRef } = React;

const HMM_CONFIG = window.HMM_SCHEDULE_CONFIG || {
  url: "index.php?page=workschedule",
  offset: 0,
  adminName: "Admin",
  tenant: "hmm"
};
const IS_KELTEX = (HMM_CONFIG.tenant || "hmm") === "keltexmed";

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
const isoWeekFromDate = (dateStr) => {
  const d = new Date(dateStr + "T00:00:00Z");
  const dow = d.getUTCDay() || 7;
  const thu = new Date(d); thu.setUTCDate(thu.getUTCDate() + 4 - dow);
  const yearStart = new Date(Date.UTC(thu.getUTCFullYear(), 0, 1));
  return { year: thu.getUTCFullYear(), week: Math.ceil(((thu - yearStart) / 86400000 + 1) / 7) };
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
  belso:       { label:"Belső rendelések",       color:"var(--brand)",  type:"Rendelés",       icon:"users"    },
  belso_egyeb: { label:"Belső - Irodai / egyéb", color:"var(--brand)",  type:"Irodai",         icon:"home"     },
  kulso:       { label:"Külső rendelések",       color:"var(--green)",  type:"Külső rendelés", icon:"building" },
  kiszallas:   { label:"Kiszállások",            color:"var(--purple)", type:"Kiszállás",      icon:"truck"    },
};
const CAT_ORDER = ["belso","belso_egyeb","kulso","kiszallas"];
const KELTEX_COLOR = "#21D2DC";
const orgColor = (org) => org === "Keltexmed" ? KELTEX_COLOR : "var(--brand)";

/* ---- szabadság típusok ------------------------------------------------ */
const VACATION_TYPES = ["Szabadság","Betegszabadság","Képzés","Egyéb"];
const VACATION_TYPE_COLORS = {
  "Szabadság":      "var(--brand)",
  "Betegszabadság": "var(--danger)",
  "Képzés":         "var(--blue)",
  "Egyéb":          "var(--purple)",
  "Elérhető":       "#2563eb",
};

/* ---- alaprajz ------------------------------------------------------- */
const FLOORS = [
  { name:"Földszint", rooms:["Recepció","Labor","Rtg","UH","Mammográfia","Rendelő 2. fogl.","Rendelő 4. fogl.","Rendelő 5. fogl.","Rendelő 6. fogl."] },
  { name:"Emelet I.", rooms:["Emelt I.","Kardiológia","Kardiológia II.","Ortopédia/Kisműtő","Bőrgyógyászat","Szemészet","Fül-orr-gégészet","Nőgyógyászat","Fogászat"] },
];
const SHORT = {"Rendelő 2. fogl.":"Rend. 2.","Rendelő 4. fogl.":"Rend. 4.","Rendelő 5. fogl.":"Rend. 5.","Rendelő 6. fogl.":"Rend. 6.","Ortopédia/Kisműtő":"Ortopédia","Fül-orr-gégészet":"Fül-orr","Kardiológia II.":"Kardio II.","Mammográfia":"Mammo.","Bőrgyógyászat":"Bőrgyógy."};
const isInternalRoom = (t) => FLOORS.some((f) => f.rooms.includes(t));

/* ---- stílusok ------------------------------------------------------- */
// #endregion

// #region Stílusok és ikonok
/** Globális CSS (egyedi property-k, fontok, animációk) a teljes munkabeosztás UI-hoz. */
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
      --danger-ink:#fca5a5; --scroll:#2c3442; --weekend:#11161e; --board-bg:#060810;
      --brand:#b3473a; --brand-ink:#e09a90; --brand-soft:rgba(179,71,58,.20);
      --map-bg:#0f141c; --map-room:#181f2a; --map-room-stroke:#2a323e;
      --map-road:#222a36; --map-road2:#1a212c; --map-bldg:#161d27; --map-bldg-stroke:#252d39;
    }
    .mb-root.mb-light{
      --bg:#f3f5f8; --sidebar:#ffffff; --surface:#ffffff; --surface-2:#f1f4f7;
      --card:#ffffff; --card-hover:#f7f9fc; --border:#e3e8ef; --border-soft:#eef1f5;
      --ink:#1a2230; --muted:#5c6675; --faint:#9aa3b1;
      --danger-ink:#dc2626; --scroll:#cdd4dd; --weekend:#eef1f6; --board-bg:#d6dae3;
      --brand:#9c3328; --brand-ink:#8e2f23; --brand-soft:rgba(156,51,40,.12);
      --map-bg:#eef1f5; --map-room:#ffffff; --map-room-stroke:#dde3ea;
      --map-road:#d2d8e0; --map-road2:#e6eaf0; --map-bldg:#eef1f5; --map-bldg-stroke:#dde3ea;
    }
    .mb-root.mb-dark.mb-keltex{
      --brand:#1ec8d4; --brand-ink:#6de8ef; --brand-soft:rgba(30,200,212,.20);
    }
    .mb-root.mb-light.mb-keltex{
      --brand:#1598a4; --brand-ink:#0e7580; --brand-soft:rgba(21,152,164,.12);
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
    .mb-board{ background:var(--board-bg); }
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
/** Ikon-gyár: egy SVG path-ból React ikonkomponenst csinál (méretezhető). */
const S = (path, w=18) => (p) => (<svg viewBox="0 0 24 24" width={w} height={w} fill="none" {...p}>{path}</svg>);
/** Az összes felületen használt SVG ikon gyűjteménye, kulcs szerint elérhető (Ico.trash, Ico.plus, stb). */
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
  home:     S(<><path d="M3 11.5 12 3l9 8.5V20a1 1 0 0 1-1 1H15v-5h-6v5H4a1 1 0 0 1-1-1V11.5Z" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round"/></>),
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
  eye:      S(<><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z" stroke="currentColor" strokeWidth="1.7"/><circle cx="12" cy="12" r="3" stroke="currentColor" strokeWidth="1.7"/></>, 16),
  eyeOff:   S(<><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24M1 1l22 22" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"/></>, 16),
  logout:   S(<><path d="M16 17l5-5-5-5M21 12H9M13 7V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5a2 2 0 0 0 2-2v-1" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"/></>, 16),
  lock:     S(<><rect x="5" y="11" width="14" height="10" rx="2" stroke="currentColor" strokeWidth="1.7"/><path d="M8 11V7a4 4 0 0 1 8 0v4" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"/></>, 16),
};

/* ---- HMM logó ------------------------------------------------------- */
// #endregion

// #region Kis megjelenítő komponensek (logó, ütközés-elemzés, térkép, form primitívek)
/** HMM logó SVG jelvény. */
function LogoMark({ size=34 }) {
  return (
    <svg viewBox="0 0 48 48" width={size} height={size} style={{ flexShrink:0 }}>
      <circle cx="24" cy="24" r="20.5" fill="none" stroke="var(--brand)" strokeWidth="4.5"/>
      <path d="M20 11 h8 v9 h9 v8 h-9 v9 h-8 v-9 h-9 v-8 h9 z" fill="var(--brand)"/>
    </svg>
  );
}

/* ---- ütközés-elemzés ------------------------------------------------ */
/**
 * Az ütközés-elemzés algoritmusa: megnézi egy hét napjaira, hogy ugyanaz a munkatárs
 * két rendelésbe van-e egyszerre beosztva (időbeli átfedés), vagy szabadságon van-e
 * miközben be van osztva. Visszaadja az érintett rendelés-kulcsok halmazát (set) és
 * a részletes ütközés-adatokat (det) kulcsonként.
 */
function analyzeDays(days, vacationsByDay) {
  const set = new Set(); const det = {};
  days.forEach((day, di) => {
    const slots = [];
    day.forEach((b) => { if (b.aktiv === 0) return; (b.staff||[]).forEach((s) => slots.push({ key:`${di}:${b.id}`, p:s.name, workerId:s.workerId, s:toMin(s.from), e:toMin(s.to), from:s.from, to:s.to, title:b.title, ac:!!s.acceptedConflict })); });
    for (let i=0; i<slots.length; i++) for (let j=i+1; j<slots.length; j++) {
      const x=slots[i], y=slots[j];
      if (x.p===y.p && x.key!==y.key && x.s<y.e && y.s<x.e && !x.ac && !y.ac) {
        set.add(x.key); set.add(y.key);
        (det[x.key]||=[]).push({ p:y.p, workerId:x.workerId, from:y.from, to:y.to, title:y.title, key:y.key });
        (det[y.key]||=[]).push({ p:x.p, workerId:y.workerId, from:x.from, to:x.to, title:x.title, key:x.key });
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
/** Emeleti alaprajz SVG-je, az aktív (kiválasztott) szoba kiemelésével. Belső rendelések helyszín-térképe. */
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

/** Sematikus utcatérkép SVG, külső rendelések/kiszállások címének vizuális jelzésére. */
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

/** Kiválasztja a megfelelő térképet: belső szoba esetén FloorPlan, egyébként StreetMap. */
function RoomMap({ booking }) {
  if (booking.cat==="belso" && isInternalRoom(booking.title)) return <FloorPlan active={booking.title}/>;
  const addr = booking.title.includes("–") ? booking.title.split("–").slice(1).join("–").trim() : booking.title;
  return <StreetMap address={addr}/>;
}

/* ---- Combobox ------------------------------------------------------- */
/** Kereshető legördülő választó (orvos/asszisztens/irodai munkatárs kiválasztásához). */
function Combobox({ value, onChange, options, placeholder, kind, compact }) {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState("");
  const color  = kind==="d" ? "var(--blue)"   : kind==="e" ? "var(--green)"  : "var(--purple)";
  const soft   = kind==="d" ? "var(--blue-soft)" : kind==="e" ? "var(--green-soft)" : "var(--purple-soft)";
  const list   = options.filter((o) => o.toLowerCase().includes(q.toLowerCase()));
  return (
    <div className="relative">
      <button type="button" onClick={() => { setOpen((v)=>!v); setQ(""); }} className="mb-in flex items-center justify-between gap-1.5 text-left" style={{ padding:compact?"7px 8px":"10px 12px", borderColor:open?"var(--brand)":"var(--border)" }}>
        <span className="flex items-center gap-1.5 min-w-0"><span style={{ color, flexShrink:0 }}>{kind==="d"?Ico.doctor({width:13,height:13}):kind==="e"?Ico.building({width:13,height:13}):Ico.person({width:13,height:13})}</span><span className="truncate" style={{ fontSize:13, fontWeight:value?600:400, color:value?"var(--ink)":"var(--faint)" }}>{value||placeholder}</span></span>
        <span style={{ color:"var(--faint)", flexShrink:0 }}>{Ico.chevDown({width:13,height:13})}</span>
      </button>
      {open && (<>
        <div className="fixed inset-0 z-40" onClick={() => setOpen(false)}/>
        <div className="mb-pop absolute z-50 mt-1.5 w-full rounded-xl overflow-hidden" style={{ background:"var(--surface)", border:"1px solid var(--border)", boxShadow:"0 20px 44px -16px rgba(0,0,0,.5)", minWidth:200 }}>
          <div className="relative p-2" style={{ borderBottom:"1px solid var(--border)" }}><span className="absolute left-4 top-1/2 -translate-y-1/2" style={{ color:"var(--faint)" }}>{Ico.search()}</span><input autoFocus value={q} onChange={(e)=>setQ(e.target.value)} placeholder="Keresés…" className="mb-in py-2 pl-9 pr-3" style={{ fontSize:13 }}/></div>
          <div className="mb-scroll" style={{ maxHeight:190, overflowY:"auto", padding:4 }}>
            {value && <button type="button" onClick={() => { onChange(null); setOpen(false); }} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left" style={{ fontSize:12.5, color:"var(--faint)" }}>{Ico.x()} Eltávolítás</button>}
            {list.length===0 && <div className="px-3 py-3" style={{ fontSize:12.5, color:"var(--faint)" }}>Nincs találat.</div>}
            {list.map((o) => (<button key={o} type="button" onClick={() => { onChange(o); setOpen(false); }} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left" style={{ fontSize:13.5, fontWeight:o===value?700:500, background:o===value?soft:"transparent", color:o===value?color:"var(--ink)" }}><span style={{ color, flexShrink:0 }}>{kind==="d"?Ico.doctor({width:13,height:13}):kind==="e"?Ico.building({width:13,height:13}):Ico.person({width:13,height:13})}</span><span className="truncate">{o}</span></button>))}
          </div>
        </div>
      </>)}
    </div>
  );
}

/** Egyszerű natív &lt;select&gt; egyedi nyíl-ikonnal. */
function MiniSelect({ value, onChange, options }) {
  return (<div className="relative"><select value={value} onChange={(e)=>onChange(e.target.value)} className="mb-in appearance-none py-2.5 pl-3 pr-8" style={{ fontSize:13.5, fontWeight:600 }}>{options.map((o)=><option key={o.v} value={o.v}>{o.l}</option>)}</select><span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2" style={{ color:"var(--faint)" }}>{Ico.chevDown({width:14,height:14})}</span></div>);
}
/** Időpont beviteli mező (HH:MM). */
function TimeBox({ value, onChange }) { return <input type="time" value={value} onChange={(e)=>onChange(e.target.value)} className="mb-mono mb-in py-2 px-2 text-center" style={{ width:86, fontSize:12.5 }}/>; }

/** Egyedi checkbox jelölőnégyzet felirattal. */
function Check({ checked, onChange, label }) {
  return (<button type="button" onClick={()=>onChange(!checked)} className="flex items-center gap-2"><span className="flex items-center justify-center rounded" style={{ width:18, height:18, background:checked?"var(--brand)":"var(--surface-2)", border:`1px solid ${checked?"var(--brand)":"var(--border)"}`, flexShrink:0 }}>{checked && <svg viewBox="0 0 24 24" width="13" height="13" fill="none"><path d="M5 12.5l4 4 10-10" stroke="#fff" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"/></svg>}</span><span style={{ fontSize:13, fontWeight:500 }}>{label}</span></button>);
}
/** Egyedi rádiógomb felirattal. */
function Radio({ selected, onSelect, label }) {
  return (<button type="button" onClick={onSelect} className="flex items-center gap-2"><span className="flex items-center justify-center rounded-full" style={{ width:18, height:18, border:`2px solid ${selected?"var(--brand)":"var(--border)"}`, flexShrink:0 }}>{selected && <span style={{ width:9, height:9, borderRadius:99, background:"var(--brand)" }}/>}</span><span style={{ fontSize:13, fontWeight:500 }}>{label}</span></button>);
}
/** Be/ki kapcsoló (switch) csúszkával. */
function Toggle({ on, onChange }) { return (<button type="button" onClick={()=>onChange(!on)} style={{ width:40, height:22, borderRadius:99, background:on?"var(--brand)":"var(--border)", position:"relative", flexShrink:0, transition:"background .15s" }}><span style={{ position:"absolute", top:2, left:on?20:2, width:18, height:18, borderRadius:99, background:"#fff", transition:"left .15s", boxShadow:"0 1px 2px rgba(0,0,0,.3)" }}/></button>); }
// #endregion

// #region Beosztás szerkesztő modal (StaffEditor, EditModal)
/* ---- StaffEditor (workerId-t is kezel) ------------------------------ */
/** Napi rendeléshez rendelt személyzet (orvos/asszisztens/irodai/jármű) szerkesztő listája egy szerepkörre. */
function StaffEditor({ role, items, onChange, slotFrom, slotTo, workerList }) {
  const nameOptions = (workerList || []).map((w) => w.nev || w.name || "");
  const accent = role==="d" ? "var(--blue)" : role==="e" ? "var(--green)" : "var(--purple)";
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
          <div className="flex-1 min-w-0"><Combobox compact value={s.name} onChange={(v)=>handleNameChange(i,v)} options={nameOptions} placeholder={role==="d"?"Orvos…":role==="e"?"Irodai munkatárs…":"Asszisztens…"} kind={role}/></div>
          <TimeBox value={s.from} onChange={(v)=>upd(i,{from:v})}/><span style={{ color:bad?"var(--danger)":"var(--faint)", fontWeight:700 }}>–</span><TimeBox value={s.to} onChange={(v)=>upd(i,{to:v})}/>
          <button onClick={()=>del2(i)} className="mb-btn flex h-8 w-8 items-center justify-center rounded-lg" style={{ color:"var(--faint)", flexShrink:0 }}>{Ico.trash()}</button>
        </div>); })}
      {items.length===0 && <div style={{ fontSize:12, color:"var(--faint)" }}>Nincs hozzárendelve.</div>}
      <button onClick={add} className="mb-add flex items-center justify-center gap-1 rounded-lg py-2" style={{ fontSize:12.5, fontWeight:600, color:accent, border:`1px dashed ${accent}`, transition:"all .12s" }}>{Ico.plus()} {role==="d"?"Orvos":role==="e"?"Irodai munkatárs":"Asszisztens"} hozzáadása</button>
    </div>
  );
}

/* ---- EditModal ------------------------------------------------------ */
/**
 * A rendelés/beosztás létrehozó és szerkesztő modal — a legnagyobb komponens.
 * Kezeli: időpont, típus (belső/külső/kiszállás), személyzet hozzárendelés,
 * kvóta/túlóra ellenőrzés, ismétlődő (multi-day / kiszállás időszak) mentés,
 * aktív/inaktív és flag (hiányzó info) kapcsolók. `embedded` módban panelként
 * (overlay nélkül) is használható — ezt a ConflictPairModal alkalmazza.
 */
function EditModal({ ctx, onClose, onSave, onDelete, dayDates, onMap, doctorList, assistantList, egyebList, vehicleList, places, saving, onToggleAktiv, onToggleFlag, vacPerDay, monthHours, weekWorkerHours, embedded }) {
  const b = ctx.booking;
  const [from, setFrom]     = useState(b ? b.from : "08:00");
  const [to, setTo]         = useState(b ? b.to   : "16:00");
  const [note, setNote]     = useState(b ? b.note : "");
  const [docs, setDocs]     = useState(b ? (b.staff||[]).filter((s)=>s.role==="d") : []);
  const [nurses, setNurses] = useState(b ? (b.staff||[]).filter((s)=>s.role==="n") : []);
  const [egyebek, setEgyebek] = useState(b ? (b.staff||[]).filter((s)=>s.role==="e") : []);
  const [jarmu,  setJarmu]  = useState(b ? (b.staff||[]).filter((s)=>s.role==="v") : []);
  const [aktiv, setAktiv]   = useState(b ? (b.aktiv !== 0) : true);
  const [selectedDays, setSelectedDays] = useState(() => new Set([ctx.day]));
  const [cat, setCat]       = useState(b ? b.cat : ctx.cat || "belso");
  const [org, setOrg]       = useState(b ? (b.org||"HMM") : "HMM");
  const [titleInput, setTitleInput]     = useState("");
  const [addressInput, setAddressInput] = useState(b ? (b.address||"") : "");
  const [rendInput,     setRendInput]     = useState(b ? (b.rendelo||"") : "");
  const [napok,         setNapok]         = useState(b ? (b.napok ?? 127) : 127);
  const [ktartoNev,     setKtartoNev]     = useState(b ? (b.ktarto_nev||"") : "");
  const [ktartoTel,     setKtartoTel]     = useState(b ? (b.ktarto_tel||"") : "");
  const [ktartoEmail,   setKtartoEmail]   = useState(b ? (b.ktarto_email||"") : "");
  const [dateStart, setDateStart] = useState(() => iso(dayDates[ctx.day]));
  const [dateEnd,   setDateEnd]   = useState(() => iso(dayDates[ctx.day]));
  const [multiApply, setMultiApply] = useState(false);
  const [multiFrom, setMultiFrom] = useState(b ? b.date : iso(dayDates[ctx.day]));
  const [multiTo,   setMultiTo]   = useState(b ? b.date : iso(dayDates[ctx.day]));

  const title    = b ? b.title    : titleInput;
  const address  = addressInput;
  const dateStr  = b ? b.date     : (ctx.date || iso(dayDates[ctx.day]));
  const badTime  = toMin(from) >= toMin(to);
  const badRange = !b && cat==="kiszallas" && dateStart > dateEnd;
  const badMultiRange = !!(b && b.cat==="kiszallas" && multiApply && multiFrom > multiTo);
  const noDoc    = cat!=="kiszallas" && (b ? b.orvosKell!==0 : true) && docs.length === 0;
  const blocked  = badTime || badRange || badMultiRange || (!b && title.trim()==="");
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
  const onVacToday = (vacPerDay||[])[pickedDi] || new Set();
  const availDoctors  = (doctorList||[]).filter((w) => !onVacToday.has(w.id));
  const availNurses   = (assistantList||[]).filter((w) => !onVacToday.has(w.id));
  const availEgyebek  = (egyebList||[]).filter((w) => !onVacToday.has(w.id));
  const availVehicles = (vehicleList||[]);
  const bookingHours  = toMin(to) > toMin(from) ? (toMin(to) - toMin(from)) / 60.0 : 0;
  const overWorkers   = [...docs, ...nurses, ...egyebek].filter((s) => {
    if (!s.workerId) return false;
    const mh = monthHours && monthHours[s.workerId];
    if (!mh || mh.quota == null) return false;
    if (mh.munkaora_tipus === "heti") {
      const weekH = (weekWorkerHours && weekWorkerHours[s.workerId]) || 0;
      return weekH > mh.quota || (weekH + bookingHours) > mh.quota;
    }
    return (mh.booked + bookingHours) > mh.quota;
  });
  const pickDate = (val) => {
    const di = dayDates.findIndex((d)=>iso(d)===val);
    if (di!==-1) setSelectedDays(new Set([di]));
  };

  const save = () => {
    const staff = [...docs, ...nurses, ...egyebek, ...jarmu].filter((s)=>s.name && s.workerId);
    const dates = b
      ? (multiApply && b.cat==="kiszallas" ? datesBetween(multiFrom, multiTo) : [dateStr])
      : (cat==="kiszallas" ? datesBetween(dateStart, dateEnd) : Array.from(selectedDays).sort().map((di)=>iso(dayDates[di])));
    const rec = { id:b?b.id:null, tipusId:b?b.tipusId:null, date:dateStr, dates, cat, org, title, address, rendelo:rendInput, napok, ktarto_nev:ktartoNev, ktarto_tel:ktartoTel, ktarto_email:ktartoEmail, staff, from, to, note, validfrom:cat==="kiszallas"?dateStart:"", validto:cat==="kiszallas"?dateEnd:"" };
    onSave(rec);
  };

  const Row = ({ icon, label, children }) => (
    <div className="flex gap-2.5">
      <span className="mt-0.5" style={{ color:"var(--faint)", flexShrink:0 }}>{icon}</span>
      <div className="min-w-0"><div style={{ fontSize:11, color:"var(--faint)", fontWeight:600 }}>{label}</div><div style={{ fontSize:13, fontWeight:600, marginTop:1 }}>{children}</div></div>
    </div>
  );

  const panel = (
      <div className="mb-pop relative w-full rounded-2xl overflow-hidden" style={{ maxWidth:840, background:"var(--surface)", border:"1px solid var(--border)", boxShadow:"0 50px 100px -28px rgba(0,0,0,.6)", marginTop:embedded?0:16, marginBottom:embedded?0:16 }}>
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
                <Field label="Rendelés">
                  <div className="mb-in px-3 py-2.5" style={{ fontSize:13.5, fontWeight:600, color:"var(--muted)", borderStyle:"dashed" }}>{title}</div>
                </Field>
                <Field label="Rendelő">
                  <input value={rendInput} onChange={(e)=>setRendInput(e.target.value)} placeholder="pl. Rendelő 1, Ultrahang szoba" className="mb-in px-3 py-2.5" style={{ fontSize:13.5, fontWeight:600 }}/>
                </Field>
              </>
            ) : (
              <>
                <Field label="Rendelés">
                  <input value={titleInput} onChange={(e)=>setTitleInput(e.target.value)} placeholder="pl. Szemészeti szűrés" className="mb-in px-3 py-2.5" style={{ fontSize:13.5, fontWeight:600 }}/>
                </Field>
                <Field label="Rendelő">
                  <input value={rendInput} onChange={(e)=>setRendInput(e.target.value)} placeholder="pl. Rendelő 1, Ultrahang szoba" className="mb-in px-3 py-2.5" style={{ fontSize:13.5, fontWeight:600 }}/>
                </Field>
                <div className="flex gap-4 flex-wrap">
                  <Field label="Típus">
                    <SegBtn value={cat} onChange={setCat} options={CAT_ORDER.map((c)=>({ v:c, l:CATS[c].label }))}/>
                  </Field>
                  {cat !== "belso" && (
                    <Field label="Rendelő / cég">
                      <SegBtn value={org} onChange={setOrg} options={[{v:"HMM",l:"HMM"},{v:"Keltexmed",l:"Keltexmed"}]}/>
                    </Field>
                  )}
                </div>
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
            {overWorkers.length>0 && <div className="flex items-center gap-2 rounded-lg px-3 py-2" style={{ background:"color-mix(in srgb,var(--danger) 12%,transparent)", border:"1px solid color-mix(in srgb,var(--danger) 30%,transparent)" }}>
              <span style={{ color:"var(--danger)", flexShrink:0 }}>{Ico.alert({width:14,height:14})}</span>
              <span style={{ fontSize:12, fontWeight:600, color:"var(--danger-ink)" }}>Kvótát túllépi: {overWorkers.map((s)=>s.name).join(", ")}</span>
            </div>}
            <Field label="Időpont (teljes idősáv)">
              <div className="flex items-center gap-2.5">
                <div className="relative flex-1"><span className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color:"var(--faint)" }}>{Ico.clock()}</span><input type="time" value={from} onChange={(e)=>setFrom(e.target.value)} className="mb-mono mb-in py-2.5 pl-9 pr-2" style={{ fontSize:13.5, borderColor:badTime?"var(--danger)":"var(--border)" }}/></div>
                <span style={{ color:"var(--faint)", fontWeight:700 }}>–</span>
                <div className="relative flex-1"><span className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color:"var(--faint)" }}>{Ico.clock()}</span><input type="time" value={to} onChange={(e)=>setTo(e.target.value)} className="mb-mono mb-in py-2.5 pl-9 pr-2" style={{ fontSize:13.5, borderColor:badTime?"var(--danger)":"var(--border)" }}/></div>
              </div>
              {badTime && <p style={{ fontSize:11.5, color:"var(--danger-ink)", marginTop:4 }}>A befejezés legyen későbbi a kezdésnél.</p>}
            </Field>
            <div>
              <div className="flex items-center gap-1.5 mb-1.5" style={{ fontSize:12.5, fontWeight:600, color:"var(--muted)" }}><span style={{ color:"var(--blue)" }}>{Ico.doctor({width:13,height:13})}</span> Orvosok {onVacToday.size>0&&<span style={{ fontSize:11, color:"var(--muted)" }}>({onVacToday.size} szabadságon)</span>}</div>
              <StaffEditor role="d" items={docs} onChange={setDocs} slotFrom={from} slotTo={to} workerList={availDoctors}/>
            </div>
            <div>
              <div className="flex items-center gap-1.5 mb-1.5" style={{ fontSize:12.5, fontWeight:600, color:"var(--muted)" }}><span style={{ color:"var(--purple)" }}>{Ico.person({width:13,height:13})}</span> Asszisztensek</div>
              <StaffEditor role="n" items={nurses} onChange={setNurses} slotFrom={from} slotTo={to} workerList={availNurses}/>
            </div>
            {(egyebList||[]).length>0 && <div>
              <div className="flex items-center gap-1.5 mb-1.5" style={{ fontSize:12.5, fontWeight:600, color:"var(--muted)" }}><span style={{ color:"var(--green)" }}>{Ico.building({width:13,height:13})}</span> Irodai munkatársak</div>
              <StaffEditor role="e" items={egyebek} onChange={setEgyebek} slotFrom={from} slotTo={to} workerList={availEgyebek}/>
            </div>}
            {cat==="kiszallas" && (vehicleList||[]).length>0 && <div>
              <div className="flex items-center gap-1.5 mb-1.5" style={{ fontSize:12.5, fontWeight:600, color:"var(--muted)" }}><span style={{ color:"var(--faint)" }}>{Ico.truck({width:13,height:13})}</span> Járművek</div>
              <StaffEditor role="v" items={jarmu} onChange={setJarmu} slotFrom={from} slotTo={to} workerList={availVehicles}/>
            </div>}
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
              {b && b.cat==="kiszallas" && (
                <div className="rounded-xl p-3 flex flex-col gap-2" style={{ background:"var(--surface-2)", border:"1px solid var(--border)" }}>
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" checked={multiApply} onChange={(e)=>setMultiApply(e.target.checked)} style={{ accentColor:"var(--purple)", width:14, height:14 }}/>
                    <span className="flex items-center gap-1.5" style={{ fontSize:12.5, fontWeight:700 }}><span style={{ color:"var(--purple)" }}>{Ico.repeat()}</span> Alkalmaz más napokra is</span>
                  </label>
                  {multiApply && (<>
                    <div className="grid grid-cols-2 gap-2 mt-1">
                      <Field label="Kezdő dátum"><input type="date" value={multiFrom} onChange={(e)=>setMultiFrom(e.target.value)} className="mb-in px-3 py-2 mb-mono" style={{ fontSize:13, borderColor:badMultiRange?"var(--danger)":"var(--border)" }}/></Field>
                      <Field label="Záró dátum"><input type="date" value={multiTo} onChange={(e)=>setMultiTo(e.target.value)} className="mb-in px-3 py-2 mb-mono" style={{ fontSize:13, borderColor:badMultiRange?"var(--danger)":"var(--border)" }}/></Field>
                    </div>
                    {badMultiRange && <p style={{ fontSize:11.5, color:"var(--danger-ink)", margin:0 }}>A záró dátum legyen későbbi.</p>}
                    {!badMultiRange && multiFrom && multiTo && <p style={{ fontSize:11.5, color:"var(--muted)", margin:0 }}>A személyzet {datesBetween(multiFrom,multiTo).length} napra kerül mentésre ({fmtShortISO(multiFrom)} – {fmtShortISO(multiTo)}).</p>}
                  </>)}
                </div>
              )}
              <Row icon={Ico.clock({width:15,height:15})} label="Időtartam">{dur(from,to)}</Row>
              <Row icon={Ico.users({width:15,height:15})} label="Személyzet">{docs.length} orvos · {nurses.length} asszisztens</Row>
              <Row icon={Ico.place({width:15,height:15})} label="Helyszín">
                <div className="flex items-center gap-1.5 flex-wrap">
                  <span style={{ fontWeight:600 }}>{title||"—"}</span>
                  {b && (b.cat==="kulso"||b.cat==="kiszallas") && <Badge text={org||"HMM"} color={orgColor(org)}/>}
                </div>
                {!!rendInput && <div style={{ fontSize:12, color:"var(--muted)", marginTop:1 }}>{rendInput}</div>}
              </Row>
              {b && (b.cat==="kulso"||b.cat==="kiszallas") && (
                <Field label="Szervező">
                  <SegBtn value={org} onChange={setOrg} options={[{v:"HMM",l:"HMM"},{v:"Keltexmed",l:"Keltexmed"}]}/>
                </Field>
              )}
              {(cat==="kulso"||cat==="kiszallas") && (
                <div className="rounded-xl p-3 flex flex-col gap-2.5" style={{ background:"var(--surface-2)", border:"1px solid var(--border)" }}>
                  <div style={{ fontSize:12, fontWeight:700, color:"var(--muted)" }}>Kapcsolattartó</div>
                  <Field label="Név">
                    <input value={ktartoNev} onChange={(e)=>setKtartoNev(e.target.value)} placeholder="pl. Kovács Péter" className="mb-in px-3 py-2" style={{ fontSize:13 }}/>
                  </Field>
                  <Field label="Telefon">
                    <input value={ktartoTel} onChange={(e)=>setKtartoTel(e.target.value)} placeholder="+36 30 123 4567" className="mb-in px-3 py-2" style={{ fontSize:13 }}/>
                  </Field>
                  <Field label="E-mail">
                    <input value={ktartoEmail} onChange={(e)=>setKtartoEmail(e.target.value)} placeholder="pelda@email.hu" className="mb-in px-3 py-2" style={{ fontSize:13 }}/>
                  </Field>
                </div>
              )}
              <Field label="Cím (Google Maps)">
                <div className="flex items-center gap-1.5">
                  <input value={addressInput} onChange={(e)=>setAddressInput(e.target.value)} placeholder="pl. Budapest, Váci út 45" className="mb-in px-3 py-2" style={{ fontSize:13, fontWeight:500 }}/>
                  {!!addressInput && <a href={`https://www.google.com/maps/search/${encodeURIComponent(addressInput)}`} target="_blank" rel="noreferrer" title="Megnyitás Google Maps-ben" style={{ color:"var(--brand-ink)", flexShrink:0 }}>{Ico.place({width:17,height:17})}</a>}
                </div>
              </Field>
              {b && <Field label="Aktív napok">
                <NapokSelector value={napok} onChange={setNapok}/>
              </Field>}
              <Row icon={Ico.alert({width:15,height:15})} label="Státusz">
                <div className="flex items-center gap-2 flex-wrap">
                  <Badge text={noDoc?"Hiányos":aktiv?"Aktív":"Inaktív"} color={noDoc?"var(--danger)":aktiv?"var(--green)":"var(--faint)"}/>
                  {b && <button onClick={()=>{ const next=!aktiv; setAktiv(next); onToggleAktiv&&onToggleAktiv({...b, aktiv:aktiv?1:0}); }} className="flex items-center gap-1 rounded-lg px-2 py-1" style={{ fontSize:11.5, fontWeight:600, color:aktiv?"var(--muted)":"var(--brand-ink)", background:"var(--surface-2)", border:"1px solid var(--border)" }}>{aktiv?<>{Ico.eyeOff({width:12,height:12})} Inaktiválás</>:<>{Ico.eye({width:12,height:12})} Aktiválás</>}</button>}
                  {b && <button onClick={()=>onToggleFlag&&onToggleFlag(b)} className="flex items-center gap-1 rounded-lg px-2 py-1" style={{ fontSize:11.5, fontWeight:600, color:b.flagged?"#f97316":"var(--muted)", background:b.flagged?"#fff7ed":"var(--surface-2)", border:`1px solid ${b.flagged?"#f97316":"var(--border)"}` }}>! {b.flagged?"Jelölés levétele":"Hiányzó info"}</button>}
                </div>
              </Row>
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
  );
  if (embedded) return panel;
  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center p-4 sm:p-6 mb-scroll" style={{ overflowY:"auto" }}>
      <div className="mb-back fixed inset-0" style={{ background:"rgba(4,6,10,.55)", backdropFilter:"blur(4px)" }} onClick={onClose}/>
      {panel}
    </div>
  );
}

// #endregion

// #region Tábla nézet elemek (Field, Badge, Th/Td/Tag, CopyWeekModal, MapPopover, Card, Group, ListView)
/** Form mező címkével. */
function Field({ label, children }) { return (<label className="block"><span className="block mb-1.5" style={{ fontSize:12.5, fontWeight:500, color:"var(--muted)" }}>{label}</span>{children}</label>); }
/** Színes jelvény (kategória, szervező, státusz jelzésére). */
function Badge({ text, color }) { return <span className="inline-block rounded-md px-2 py-0.5" style={{ fontSize:11.5, fontWeight:700, color, background:`color-mix(in srgb, ${color} 16%, transparent)` }}>{text}</span>; }
/** Táblázat fejléc cella. */
function Th({ children }) { return <th className="mb-display" style={{ textAlign:"left", padding:"9px 12px", fontSize:11, fontWeight:700, letterSpacing:".04em", color:"var(--faint)", textTransform:"uppercase", borderBottom:"1px solid var(--border)", whiteSpace:"nowrap" }}>{children}</th>; }
/** Táblázat adat cella. */
function Td({ children, mono, style }) { return <td className={mono?"mb-mono":""} style={{ padding:"9px 12px", fontSize:13, verticalAlign:"middle", ...style }}>{children}</td>; }
/** Színes pötty + felirat (jelmagyarázathoz). */
function Tag({ color, label }) { return (<span className="flex items-center gap-2" style={{ fontSize:12.5, color:"var(--muted)" }}><span style={{ width:11, height:11, borderRadius:3, background:color, display:"inline-block" }}/>{label}</span>); }

/* ---- HétMásolás Modal ---------------------------------------------- */
/** Hét másolása modal: kiválasztott hetet másol egy vagy több célhét(ek)re, felülírás opcióval. */
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
/** Egy rendelés helyszínét mutató térkép popover (RoomMap becsomagolva). */
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
/** Kis piros státusz-jelvény (pl. "Ütközés", "Nincs orvos"). */
function RedBadge({ text }) { return <span className="rounded px-1.5 py-0.5" style={{ fontFamily:"Manrope", fontSize:10, fontWeight:700, color:"var(--danger-ink)", background:"var(--danger-soft)" }}>{text}</span>; }

/**
 * Egy rendelés/beosztás kártyája a tábla nézetben: mutatja az időpontot, személyzetet,
 * ütközés/túlóra/hiányzó-orvos jelzéseket, aktiválás és flag gombokat.
 */
function Card({ b, conflict, overlap, onOpen, onMap, query, roleFilter, onToggleAktiv, onToggleFlag, onDismissConflict, weekWorkerHours, monthHours }) {
  const q = query.trim().toLowerCase();
  const inactive = b.aktiv === 0;
  const docs   = (b.staff||[]).filter((s)=>s.role==="d");
  const nurses = (b.staff||[]).filter((s)=>s.role==="n");
  const egyebs = (b.staff||[]).filter((s)=>s.role==="e");
  const noDoc  = !inactive && b.cat!=="kiszallas" && b.cat!=="belso_egyeb" && b.orvosKell!==0 && docs.length===0;
  const overQuota = !inactive && (b.staff||[]).some((s) => {
    if (!s.workerId) return false;
    const mh = monthHours && monthHours[s.workerId];
    if (!mh || mh.quota == null) return false;
    if (mh.munkaora_tipus === "heti") return ((weekWorkerHours && weekWorkerHours[s.workerId]) || 0) > mh.quota;
    return mh.booked > mh.quota;
  });
  const red    = noDoc||conflict;
  const accent = inactive?"var(--faint)":(red?"var(--danger)":(CATS[b.cat]?.color||"var(--muted)"));
  const names  = (b.staff||[]).map((s)=>s.name).filter(Boolean);
  const hit    = q && [b.title,...names].some((x)=>x&&x.toLowerCase().includes(q));
  const overlapDouble = (overlap||[]).filter((o)=>!o.vac);
  const overlapVac    = (overlap||[]).filter((o)=>o.vac);
  const hasStaff = (b.staff||[]).length > 0;
  const StaffChip = ({ s, color, soft, icon }) => {
    const diff = s.from!==b.from||s.to!==b.to;
    const onVac = overlapVac.some((o)=>o.p===s.name);
    return (<span className="flex items-center gap-1 rounded-md px-1.5 py-0.5" style={{ background:onVac?"var(--danger-soft)":soft, color:onVac?"var(--danger-ink)":color, fontSize:11.5, fontWeight:600, maxWidth:"100%" }}><span style={{ flexShrink:0 }}>{icon}</span><span className="truncate">{s.name}</span>{diff && <span className="mb-mono" style={{ fontSize:10, opacity:.85, flexShrink:0 }}>{s.from}–{s.to}</span>}</span>);
  };
  const flagged = !!b.flagged;
  const cardBg = inactive ? "var(--surface-2)" : flagged ? "#fff7ed" : red ? `color-mix(in srgb,var(--danger) 13%,var(--card))` : (b.color ? `color-mix(in srgb,${b.color} 18%,var(--card))` : "var(--card)");
  const cardBorder = inactive ? "var(--border-soft)" : flagged ? "#f97316" : red ? "var(--danger)" : (b.color ? `color-mix(in srgb,${b.color} 50%,var(--border))` : "var(--border)");
  return (
    <div className="mb-tcard relative rounded-xl" onClick={onOpen} style={{ background:cardBg, border:`1px solid ${cardBorder}`, padding:"9px 10px 10px 11px", outline:hit?"2px solid var(--brand)":"none", opacity:inactive?.55:1 }}>
      <button onClick={(e)=>{e.stopPropagation();onToggleFlag&&onToggleFlag(b);}} title={flagged?"Jelölés eltávolítása":"Hiányzó info jelölése"} className="absolute right-14 top-1.5 flex h-6 w-6 items-center justify-center rounded-md" style={{ color:flagged?"#f97316":"var(--faint)", background:flagged?"#fff7ed":"transparent", fontWeight:700, fontSize:13 }}>!</button>
      <button onClick={(e)=>{e.stopPropagation();onToggleAktiv&&onToggleAktiv(b);}} title={inactive?"Aktiválás":"Inaktiválás"} className="absolute right-8 top-1.5 flex h-6 w-6 items-center justify-center rounded-md" style={{ color:inactive?"var(--brand)":"var(--faint)" }}>{inactive?Ico.eye({width:14,height:14}):Ico.eyeOff({width:14,height:14})}</button>
      <button onClick={(e)=>{e.stopPropagation();b.address?window.open(`https://www.google.com/maps/search/${encodeURIComponent(b.address)}`,"_blank"):onMap();}} title="Hely a térképen" className="absolute right-1.5 top-1.5 flex h-6 w-6 items-center justify-center rounded-md" style={{ color:b.address?"var(--brand)":"var(--faint)" }}>{Ico.place({width:14,height:14})}</button>
      <div className="mb-mono flex items-center gap-1.5 flex-wrap pr-14" style={{ fontSize:11.5, color:"var(--muted)", fontWeight:500 }}><span>{b.from} – {b.to}</span>{!inactive&&overlapDouble.length>0&&<RedBadge text="Ütközés"/>}{!inactive&&overlapVac.length>0&&<RedBadge text="Szabadságon"/>}{noDoc&&<RedBadge text="Nincs orvos"/>}{overQuota&&<span className="rounded px-1.5 py-0.5" style={{ fontFamily:"Manrope", fontSize:10, fontWeight:700, color:"#92400e", background:"#fef3c7" }}>Túlóra</span>}</div>
      <div className="flex items-center gap-1.5 mt-0.5 flex-wrap">
        <div className="truncate" style={{ fontSize:13.5, fontWeight:700, color:inactive?"var(--muted)":"var(--ink)" }}>{b.title}</div>
        {(b.cat==="kulso"||b.cat==="kiszallas") && <Badge text={b.org||"HMM"} color={orgColor(b.org)}/>}
      </div>
      {!!b.address && <div className="truncate" style={{ fontSize:11.5, color:"var(--faint)", fontWeight:600 }}>{b.address}</div>}
      {!inactive && <div className="flex flex-wrap gap-1.5 mt-1.5">
        {roleFilter!=="n"&&docs.map((s,i)   => <StaffChip key={"d"+i} s={s} color="var(--blue)"   soft="var(--blue-soft)"   icon={Ico.doctor({width:12,height:12})}/>)}
        {roleFilter!=="d"&&nurses.map((s,i)  => <StaffChip key={"n"+i} s={s} color="var(--purple)" soft="var(--purple-soft)" icon={Ico.person({width:12,height:12})}/>)}
        {egyebs.map((s,i)                    => <StaffChip key={"e"+i} s={s} color="var(--green)"  soft="var(--green-soft)"  icon={Ico.building({width:12,height:12})}/>)}
      </div>}
      {b.note&&!conflict&&!inactive&&<div className="mt-1.5" style={{ fontSize:11, color:"var(--faint)" }}>{b.note}</div>}
      {!inactive&&overlapDouble.map((o,i)=>(
        <div key={i} className="mt-1.5 flex items-center justify-between gap-2">
          <span style={{ fontSize:11, fontWeight:600, color:"var(--danger-ink)" }}>Átfedés: {o.p} {o.from}–{o.to}{o.title ? ` · ${o.title}` : ""}</span>
          <button onClick={(e)=>{e.stopPropagation();onDismissConflict&&onDismissConflict(b,o.workerId);}} className="rounded px-1.5 py-0.5" style={{ fontSize:10, fontWeight:700, color:"var(--danger-ink)", background:"var(--danger-soft)", flexShrink:0 }}>Elfogad</button>
        </div>
      ))}
      {!inactive&&overlapVac[0]&&<div className="mt-1.5" style={{ fontSize:11, fontWeight:600, color:"var(--danger-ink)" }}>{overlapVac[0].p} szabadságon van{overlapVac[0].status===0?" (függő kérelem)":""}</div>}
    </div>
  );
}

/* ---- Csoport -------------------------------------------------------- */
/** Egy nap egy kategóriájának (belső/külső/kiszállás stb.) összecsukható kártya-csoportja. */
function Group({ cat, di, items, collapsed, onToggle, conf, onOpenCard, onMap, query, roleFilter, onToggleAktiv, onToggleFlag, onDismissConflict, weekWorkerHours, monthHours }) {
  const c = CATS[cat]; const catIcon = Ico[c.icon];
  return (
    <div className="rounded-xl overflow-hidden" style={{ background:"var(--surface)", border:"1px solid var(--border-soft)" }}>
      <button onClick={onToggle} className="flex w-full items-center justify-between px-3 py-2.5" style={{ borderBottom:collapsed?"none":"1px solid var(--border-soft)", background:`color-mix(in srgb,${c.color} 15%,transparent)` }}>
        <span className="flex items-center gap-2"><span style={{ color:c.color, flexShrink:0 }}>{catIcon({width:15,height:15})}</span><span className="mb-display" style={{ fontSize:13, fontWeight:700, letterSpacing:".04em", color:c.color }}>{c.label}</span><span className="rounded-md px-1.5" style={{ fontSize:11, fontWeight:700, color:"var(--muted)", background:"var(--surface-2)" }}>{items.length}</span></span>
        <span style={{ color:"var(--faint)" }}>{collapsed?Ico.chevDown({width:16,height:16}):Ico.chevUp({width:16,height:16})}</span>
      </button>
      {!collapsed && (<div className="flex flex-col gap-1.5 p-2">
        {items.map((b) => <Card key={b.id} b={b} conflict={conf.set.has(`${di}:${b.id}`)} overlap={conf.det[`${di}:${b.id}`]} query={query} roleFilter={roleFilter} onOpen={()=>onOpenCard(b)} onMap={()=>onMap(b)} onToggleAktiv={onToggleAktiv} onToggleFlag={onToggleFlag} onDismissConflict={onDismissConflict} weekWorkerHours={weekWorkerHours} monthHours={monthHours}/>)}
        {items.length===0 && <div className="px-1 py-2" style={{ fontSize:12, color:"var(--faint)" }}>Nincs rendelés.</div>}
      </div>)}
    </div>
  );
}

/* ---- Listás nézet ---------------------------------------------------- */
/** A heti beosztás táblázatos (lista) nézete, naponkénti bontásban. */
function ListView({ weekDays, dayDates, conf, matches, collapsed, onToggle, onOpenCard, onMap, onToggleAktiv, onDismissConflict }) {
  return (
    <div className="px-4 lg:px-6 py-4 flex flex-col gap-3">
      {HU_DAYS.map((_, di) => {
        const rows = weekDays[di].filter((b)=>matches(b,di)).slice().sort((a,b)=>CAT_ORDER.indexOf(a.cat)-CAT_ORDER.indexOf(b.cat));
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
                    const noDoc  = b.orvosKell!==0 && docs.length===0;
                    const isConf = conf.set.has(`${di}:${b.id}`);
                    const overlap = conf.det[`${di}:${b.id}`]||[];
                    const hasDouble = overlap.some((o)=>!o.vac);
                    const hasVac    = overlap.some((o)=>o.vac);
                    const inactive = b.aktiv === 0;
                    const red    = !inactive && (noDoc || isConf);
                    return (
                      <tr key={b.id} onClick={()=>onOpenCard(b,di)} style={{ borderBottom:"1px solid var(--border-soft)", background:inactive?"var(--surface-2)":(red?"color-mix(in srgb,var(--danger) 10%,transparent)":"transparent"), cursor:"pointer", opacity:inactive?.55:1 }}>
                        <Td mono>{b.from} – {b.to}</Td>
                        <Td><Badge text={CATS[b.cat]?.type||b.cat} color={inactive?"var(--faint)":(CATS[b.cat]?.color||"var(--muted)")}/></Td>
                        <Td>
                          <div className="flex items-center gap-1.5 flex-wrap">
                            <span style={{ fontWeight:600 }}>{b.title}</span>
                            <button onClick={(e)=>{ e.stopPropagation(); b.address?window.open(`https://www.google.com/maps/search/${encodeURIComponent(b.address)}`,"_blank"):onMap(b); }} title="Hely a térképen" style={{ color:b.address?"var(--brand)":"var(--faint)" }}>{Ico.place({width:13,height:13})}</button>
                            <button onClick={(e)=>{ e.stopPropagation(); onToggleAktiv&&onToggleAktiv(b); }} title={inactive?"Aktiválás":"Inaktiválás"} style={{ color:inactive?"var(--brand)":"var(--faint)" }}>{inactive?Ico.eye({width:13,height:13}):Ico.eyeOff({width:13,height:13})}</button>
                            {!inactive&&overlap.filter((o)=>!o.vac).map((o,i)=>(
                              <span key={i} className="flex items-center gap-1">
                                <RedBadge text={`Ütközés: ${o.p}`}/>
                                <button onClick={(e)=>{e.stopPropagation();onDismissConflict&&onDismissConflict(b,o.workerId);}} className="rounded px-1.5 py-0.5" style={{ fontSize:10, fontWeight:700, color:"var(--danger-ink)", background:"var(--danger-soft)", flexShrink:0 }}>Elfogad</button>
                              </span>
                            ))}
                            {!inactive&&hasVac && <RedBadge text="Szabadságon"/>}
                            {!inactive&&noDoc && <RedBadge text="Nincs orvos"/>}
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

// #endregion

// #region Ütközés kezelés (ConflictPairModal, ConflictCard, ConflictView)
/* ---- Ütközés pár modal ------------------------------------------------ */
/** Egymással ütköző rendeléseket egymás mellett, egyszerre szerkeszthető EditModal panelekben jelenít meg. */
function ConflictPairModal({ cluster, onClose, onSave, onDelete, onMap, dayDates, doctorList, assistantList, egyebList, vehicleList, places, saving, onToggleAktiv, onToggleFlag, vacPerDay, monthHours, weekWorkerHours }) {
  useEffect(() => { const h=(e)=>e.key==="Escape"&&onClose(); document.addEventListener("keydown",h); return ()=>document.removeEventListener("keydown",h); }, [onClose]);
  const cols = cluster.length;
  return (
    <div className="fixed inset-0 z-50 mb-scroll" style={{ overflowY:"auto", padding:"16px" }}>
      <div className="mb-back fixed inset-0" style={{ background:"rgba(4,6,10,.72)", backdropFilter:"blur(6px)" }} onClick={onClose}/>
      <div className="relative" style={{ maxWidth: cols===1 ? 860 : cols===2 ? 1720 : "100%", margin:"0 auto" }}>
        <div className="flex items-center justify-between mb-3">
          <span className="flex items-center gap-2" style={{ fontSize:13.5, fontWeight:700, color:"#fff" }}>{Ico.alert({width:15,height:15})} Ütköző rendelések</span>
          <button onClick={onClose} className="flex h-8 w-8 items-center justify-center rounded-lg" style={{ color:"#fff", background:"rgba(255,255,255,.15)" }}>{Ico.x()}</button>
        </div>
        <div className="grid gap-4" style={{ gridTemplateColumns:`repeat(${cols},minmax(0,1fr))` }}>
          {cluster.map(({ b, di }) => (
            <EditModal key={`${di}-${b.id}`} embedded={true}
              ctx={{ day:di, cat:b.cat, booking:b, date:b.date }}
              dayDates={dayDates} onClose={onClose}
              onSave={(rec)=>{ onSave(rec); onClose(); }}
              onDelete={(id)=>{ onDelete(id); onClose(); }}
              onMap={onMap} doctorList={doctorList} assistantList={assistantList}
              egyebList={egyebList} vehicleList={vehicleList} places={places}
              saving={saving} onToggleAktiv={onToggleAktiv} onToggleFlag={onToggleFlag}
              vacPerDay={vacPerDay} monthHours={monthHours} weekWorkerHours={weekWorkerHours}/>
          ))}
        </div>
      </div>
    </div>
  );
}

/* ---- Ütközés nézet ---------------------------------------------------- */
/** Egy ütköző/problémás rendelés kártyája az Ütközések nézetben (dupla-foglalás, szabadság-ütközés, hiányzó orvos). */
function ConflictCard({ b, di, overlaps, sectionKey, onOpenCard, onMap }) {
  const docs   = (b.staff||[]).filter((s)=>s.role==="d");
  const nurses = (b.staff||[]).filter((s)=>s.role==="n");
  return (
    <div onClick={()=>onOpenCard(b,di)} className="mb-tcard rounded-xl" style={{ background:"color-mix(in srgb, var(--danger) 10%, var(--surface))", border:"1px solid var(--danger)", padding:"12px 14px", cursor:"pointer" }}>
      <div className="flex items-center justify-between gap-2 flex-wrap">
        <div className="flex items-center gap-2 flex-wrap" style={{ fontWeight:700, fontSize:13.5 }}>
          <span style={{ color:"var(--danger)" }}>{Ico.alert({width:15,height:15})}</span>
          {b.title}
          <Badge text={CATS[b.cat]?.type||b.cat} color={CATS[b.cat]?.color||"var(--muted)"}/>
          <button onClick={(e)=>{ e.stopPropagation(); b.address?window.open(`https://www.google.com/maps/search/${encodeURIComponent(b.address)}`,"_blank"):onMap(b); }} title="Hely a térképen" style={{ color:b.address?"var(--brand)":"var(--faint)" }}>{Ico.place({width:13,height:13})}</button>
        </div>
        <span className="mb-mono" style={{ fontSize:12, color:"var(--muted)" }}>{HU_DAYS[di]}, {fmtShortISO(b.date)} · {b.from}–{b.to}</span>
      </div>
      <div className="flex items-center gap-3 flex-wrap" style={{ fontSize:12, color:"var(--muted)", marginTop:4 }}>
        <span>Orvos(ok): {docs.length ? docs.map((d)=>d.name).join(", ") : "—"}</span>
        <span>Asszisztens(ek): {nurses.length ? nurses.map((n)=>n.name).join(", ") : "—"}</span>
      </div>
      <div style={{ fontSize:12, color:"var(--danger-ink)", marginTop:4, fontWeight:600 }}>
        {overlaps.map((o,i) => o.vac
          ? <div key={i}>{o.p} szabadságon van{o.status===0?" (függő kérelem)":""}</div>
          : <div key={i}>Időbeli átfedés: {o.p} ({o.from}–{o.to})</div>
        )}
        {sectionKey==="noDoc" && <div>Nincs orvos hozzárendelve</div>}
      </div>
    </div>
  );
}

/** Az "Ütközések" nézet: csoportosítja a heti problémás rendeléseket (ütközés / szabadságon / nincs orvos) szekciónként. */
function ConflictView({ weekDays, conf, catFilter, query, collapsed, onToggle, onOpenCard, onOpenPair, onMap }) {
  const q = (query||"").trim().toLowerCase();
  const groups = { double: [], vac: [], noDoc: [] };
  weekDays.forEach((day, di) => {
    day.forEach((b) => {
      if (catFilter!=="all" && b.cat!==catFilter) return;
      if (q) { const names=(b.staff||[]).map((s)=>s.name); if (![b.title,...names].some((x)=>x&&x.toLowerCase().includes(q))) return; }
      const key      = `${di}:${b.id}`;
      const overlaps = conf.det[key] || [];
      const hasDouble = overlaps.some((o)=>!o.vac);
      const hasVac    = overlaps.some((o)=>o.vac);
      const noDoc     = b.cat!=="kiszallas" && b.cat!=="belso_egyeb" && b.orvosKell!==0 && (b.staff||[]).filter((s)=>s.role==="d").length===0;
      const entry = { b, di, key, overlaps };
      if (hasDouble) groups.double.push(entry);
      if (hasVac)    groups.vac.push(entry);
      if (noDoc)     groups.noDoc.push(entry);
    });
  });

  // a "Ütközés" csoportban az egymással ütköző rendeléseket egy klaszterbe gyűjtjük,
  // hogy egymás alatt jelenjenek meg
  const byKey = new Map(groups.double.map((e)=>[e.key, e]));
  const visited = new Set();
  const doubleClusters = [];
  groups.double.forEach((entry) => {
    if (visited.has(entry.key)) return;
    const cluster = []; const queue = [entry.key];
    while (queue.length) {
      const k = queue.shift();
      if (visited.has(k)) continue;
      visited.add(k);
      const e = byKey.get(k);
      if (!e) continue;
      cluster.push(e);
      e.overlaps.forEach((o) => { if (!o.vac && o.key && !visited.has(o.key)) queue.push(o.key); });
    }
    cluster.sort((a,b) => (a.di-b.di) || (toMin(a.b.from)-toMin(b.b.from)));
    doubleClusters.push(cluster);
  });
  doubleClusters.sort((a,b) => (a[0].di-b[0].di) || (toMin(a[0].b.from)-toMin(b[0].b.from)));

  const sections = [
    { key:"double", label:"Ütközés",     count:groups.double.length, items:groups.double },
    { key:"vac",     label:"Szabadságon", count:groups.vac.length,     items:groups.vac },
    { key:"noDoc",   label:"Nincs orvos", count:groups.noDoc.length,   items:groups.noDoc },
  ];

  if (sections.every((sec)=>sec.items.length===0)) {
    return (
      <div className="flex flex-col items-center justify-center gap-3" style={{ color:"var(--muted)", padding:"80px 20px" }}>
        <span style={{ color:"var(--green)" }}>{Ico.alert({width:32,height:32})}</span>
        <div style={{ fontSize:14, fontWeight:600 }}>Nincs ütközés ezen a héten.</div>
      </div>
    );
  }

  return (
    <div className="px-4 lg:px-6 py-4 flex flex-col gap-3">
      {sections.map((sec) => {
        if (sec.items.length===0) return null;
        const key  = `conf:${sec.key}`;
        const isCollapsed = !!collapsed[key];
        const rows = sec.key==="double" ? null : sec.items.slice().sort((a,b) => (a.di-b.di) || (toMin(a.b.from)-toMin(b.b.from)));
        return (
          <div key={sec.key} className="rounded-xl overflow-hidden" style={{ border:"1px solid var(--border-soft)", background:"var(--surface)" }}>
            <button onClick={()=>onToggle(key)} className="flex w-full items-center justify-between px-3 py-2.5" style={{ borderBottom:isCollapsed?"none":"1px solid var(--border-soft)" }}>
              <div className="flex items-baseline gap-2">
                <span className="mb-pulse" style={{ color:"var(--danger)" }}>{Ico.alert({width:14,height:14})}</span>
                <span className="mb-display" style={{ fontSize:13.5, fontWeight:700, letterSpacing:".03em", color:"var(--danger-ink)" }}>{sec.label.toUpperCase()}</span>
                <span className="flex items-center justify-center rounded-md" style={{ minWidth:18, height:18, padding:"0 4px", fontSize:11, fontWeight:700, color:"var(--muted)", background:"var(--surface-2)" }}>{sec.count}</span>
              </div>
              <span style={{ color:"var(--faint)" }}>{isCollapsed?Ico.chevDown({width:16,height:16}):Ico.chevUp({width:16,height:16})}</span>
            </button>
            {!isCollapsed && sec.key==="double" && (
              <div className="flex flex-col gap-2.5 p-2.5">
                {doubleClusters.map((cluster, ci) => (
                  <div key={ci} className="flex flex-col gap-1.5">
                    {cluster.map(({ b, di, overlaps }) => <ConflictCard key={`${di}-${b.id}`} b={b} di={di} overlaps={overlaps} sectionKey={sec.key} onOpenCard={()=>onOpenPair?onOpenPair(cluster):onOpenCard(b,di)} onMap={onMap}/>)}
                  </div>
                ))}
              </div>
            )}
            {!isCollapsed && sec.key!=="double" && (
              <div className="flex flex-col gap-1.5 p-2.5">
                {rows.map(({ b, di, overlaps }) => <ConflictCard key={`${di}-${b.id}`} b={b} di={di} overlaps={overlaps} sectionKey={sec.key} onOpenCard={onOpenCard} onMap={onMap}/>)}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}

// #endregion

// #region Munkatársak (LoadingBlock megosztott betöltés-jelző, StaffView, StaffModal)
/* ---- LoadingBlock ---------------------------------------------------- */
/** Általános betöltés-jelző (pörgő ikon + felirat), több nézet is használja. */
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
/** Szerepkör (orvos/asszisztens/irodai) megjelenítési adatai (címke, ikon, szín). */
const ROLE_DISPLAY = {
  1: { label:"Orvosok",            icon:"doctor",   color:"var(--blue)"   },
  2: { label:"Asszisztensek",      icon:"person",   color:"var(--purple)" },
  3: { label:"Irodai munkatársak", icon:"building", color:"var(--green)"  },
};
/** Lekéri egy szerepkör megjelenítési adatait, ismeretlen szerepkörnél alapértelmezettet ad vissza. */
const getRoleDisplay = (role) => ROLE_DISPLAY[role.id] || { label:role.megnev, icon:"truck", color:"var(--faint)" };

/** Munkatársak listája szerepkör szerint csoportosítva, kereséssel, inaktiválással, felvitel/szerkesztés modallal. */
function StaffView({ setToast, newSignal, query: searchQuery, onStaffSaved }) {
  const [data, setData]           = useState(null);
  const [loading, setLoading]     = useState(true);
  const [query, setQuery]         = useState("");
  const [modal, setModal]         = useState(null);
  const [saving, setSaving]       = useState(false);
  const [secCollapsed, setSecCollapsed] = useState({});
  const [showInactive, setShowInactive] = useState(false);

  const initialSignal = useRef(newSignal);
  useEffect(() => { if (newSignal > initialSignal.current) setModal({ worker:null, roleid:1 }); }, [newSignal]);

  const load = useCallback(() => {
    setLoading(true);
    fetch(`${HMM_CONFIG.url}&getstaff=1`).then((r)=>r.json()).then((d)=>{ setData(d); setLoading(false); }).catch(()=>setLoading(false));
  }, []);
  useEffect(() => { load(); }, [load]);

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
        efo:rec.efo?"1":"", beouserid:rec.beouserid||"0", munkaora:rec.munkaora||"", munkaora_tipus:rec.munkaora_tipus||"havi",
        aktiv: rec.aktiv===false||rec.aktiv===0 ? "0" : "1",
      });
      if (result.status==="ok") { await load(); setModal(null); setToast("Munkatárs mentve!"); onStaffSaved && onStaffSaved(); }
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

  const q = (searchQuery || query).trim().toLowerCase();
  const allWorkers = (data.workers||[]).filter((w) => {
    if (!showInactive && w.aktiv === 0) return false;
    return !q || `${w.teljesnev} ${w.nev} ${w.email} ${w.tel}`.toLowerCase().includes(q);
  });
  const inactiveCount = (data.workers||[]).filter((w) => w.aktiv === 0).length;

  return (
    <div className="mb-scroll px-4 lg:px-6 py-4" style={{ flex:"1 1 auto", minHeight:0, overflowY:"auto" }}>
      <div className="flex items-center gap-3 mb-3 flex-wrap">
        <div className="relative max-w-xs flex-1" style={{ minWidth:180 }}>
          <span className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color:"var(--faint)" }}>{Ico.search()}</span>
          <input value={query} onChange={(e)=>setQuery(e.target.value)} placeholder="Keresés…" className="mb-in py-2 pl-9 pr-3" style={{ fontSize:13 }}/>
        </div>
        {inactiveCount > 0 && (
          <button type="button" onClick={()=>setShowInactive(p=>!p)} className="flex items-center gap-1.5 rounded-lg px-3 py-2" style={{ fontSize:12.5, fontWeight:600, color:showInactive?"var(--brand-ink)":"var(--muted)", background:showInactive?"var(--brand-soft)":"var(--surface-2)", border:"1px solid var(--border)" }}>
            {showInactive ? Ico.eye({width:13,height:13}) : Ico.eyeOff({width:13,height:13})} Inaktív munkatársak ({inactiveCount})
          </button>
        )}
      </div>
      <div className="flex flex-col gap-3" style={{ maxWidth:760 }}>
        {(data.roles||[]).map((role) => {
          const rid = Number(role.id);
          const disp = getRoleDisplay({ ...role, id: rid });
          const workers = allWorkers.filter((w) => w.roleid === rid);
          const collapsed = !!secCollapsed[rid];
          const SectionIcon = Ico[disp.icon];
          return (
            <div key={rid} className="rounded-xl overflow-hidden" style={{ background:"var(--surface)", border:"1px solid var(--border-soft)" }}>
              <button onClick={()=>setSecCollapsed(p=>({...p,[rid]:!p[rid]}))} className="flex w-full items-center justify-between px-3 py-2.5" style={{ borderBottom:collapsed?"none":"1px solid var(--border-soft)", background:`color-mix(in srgb,${disp.color} 15%,transparent)` }}>
                <span className="flex items-center gap-2">
                  <span style={{ color:disp.color, flexShrink:0 }}>{SectionIcon({width:15,height:15})}</span>
                  <span className="mb-display" style={{ fontSize:13, fontWeight:700, letterSpacing:".04em", color:disp.color }}>{disp.label}</span>
                  <span className="rounded-md px-1.5" style={{ fontSize:11, fontWeight:700, color:"var(--muted)", background:"var(--surface-2)" }}>{workers.filter((w)=>w.aktiv!==0).length}</span>
                </span>
                <span style={{ color:"var(--faint)" }}>{collapsed?Ico.chevDown({width:16,height:16}):Ico.chevUp({width:16,height:16})}</span>
              </button>
              {!collapsed && (
                <div className="flex flex-col gap-1.5 p-2">
                  {workers.map((w) => (
                    <div key={w.id} onClick={()=>setModal({ worker:w })} className="mb-tcard flex items-center justify-between gap-3 rounded-lg" style={{ background:"var(--card)", border:"1px solid var(--border)", padding:"8px 10px", opacity:w.aktiv===0?0.5:1 }}>
                      <div className="min-w-0">
                        <div className="flex items-center gap-1.5 flex-wrap">
                          <div className="truncate" style={{ fontSize:13.5, fontWeight:700 }}>{w.teljesnev||w.nev}</div>
                          {w.teljesnev && w.nev && <span style={{ fontSize:11.5, color:"var(--faint)" }}>({w.nev})</span>}
                        </div>
                        <div className="flex flex-wrap gap-x-3" style={{ fontSize:11.5, color:"var(--muted)" }}>
                          {w.email && <span>{w.email}</span>}
                          {w.tel && <span>{w.tel}</span>}
                        </div>
                      </div>
                      <div className="flex items-center gap-1.5 flex-shrink-0">
                        {w.aktiv===0 && <Badge text="Inaktív" color="var(--faint)"/>}
                        {!!w.efo && <Badge text="EFO" color="var(--purple)"/>}
                        {!!w.onVacation && <Badge text="Szabadságon" color="var(--danger)"/>}
                        {!!w.smsert && <Badge text="SMS" color="var(--blue)"/>}
                        {!!w.emailert && <Badge text="Email" color="var(--green)"/>}
                      </div>
                    </div>
                  ))}
                  {workers.length===0 && <div className="px-1 py-2" style={{ fontSize:12, color:"var(--faint)" }}>Nincs munkatárs.</div>}
                </div>
              )}
            </div>
          );
        })}
      </div>
      {modal && <StaffModal ctx={modal} roles={data.roles} users={data.users} onClose={()=>setModal(null)} onSave={save} onDelete={remove} saving={saving}/>}
    </div>
  );
}

/** Munkatárs felviteli/szerkesztő modal (elérhetőségek, kvóta, EFO, aktív státusz). */
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
  const [munkaora,       setMunkaora]       = useState(w && w.munkaora != null ? String(w.munkaora) : "");
  const [munkaora_tipus, setMunkaoraTipus] = useState(w ? (w.munkaora_tipus || "havi") : "havi");
  const [aktiv,          setAktiv]         = useState(w ? (w.aktiv !== 0) : true);

  useEffect(() => { const h=(e)=>e.key==="Escape"&&onClose(); document.addEventListener("keydown",h); return ()=>document.removeEventListener("keydown",h); }, [onClose]);

  const invalid = nev.trim()==="";
  const save = () => onSave({ id:w?w.id:0, nev, teljesnev, roleid, email, tel, smsert, emailert, efo, beouserid, munkaora, munkaora_tipus, aktiv });

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
          <Field label="Munkaidő kvóta">
            <div className="flex items-center gap-2">
              <input type="number" min="0" step="0.5" value={munkaora} onChange={(e)=>setMunkaora(e.target.value)} placeholder="pl. 28" className="mb-in px-3 py-2.5 flex-1" style={{ fontSize:13.5 }}/>
              <MiniSelect value={munkaora_tipus} onChange={setMunkaoraTipus} options={[{v:"havi",l:"óra/hó"},{v:"heti",l:"óra/hét"}]}/>
            </div>
          </Field>
        </div>
        <div className="flex items-center justify-between gap-3 px-6 py-4" style={{ borderTop:"1px solid var(--border)", background:"var(--bg)" }}>
          <div className="flex items-center gap-1">
            {w && <button onClick={()=>onDelete(w.id)} className="flex items-center gap-1.5 rounded-lg px-3 py-2" style={{ fontSize:13, fontWeight:600, color:"var(--danger-ink)" }}>{Ico.trash()} Törlés</button>}
            {w && <button type="button" onClick={()=>setAktiv(!aktiv)} className="flex items-center gap-1.5 rounded-lg px-3 py-2" style={{ fontSize:13, fontWeight:600, color:aktiv?"var(--muted)":"var(--green)" }}>{aktiv ? <>{Ico.eyeOff({width:14,height:14})} Inaktiválás</> : <>{Ico.eye({width:14,height:14})} Aktiválás</>}</button>}
          </div>
          <div className="flex items-center gap-2">
            <button onClick={onClose} className="mb-btn rounded-lg px-4 py-2.5" style={{ fontSize:13.5, fontWeight:600, color:"var(--muted)", border:"1px solid var(--border)" }}>Mégse</button>
            <button disabled={invalid||saving} onClick={save} className="mb-prim flex items-center gap-1.5 rounded-lg px-5 py-2.5" style={{ fontSize:13.5, fontWeight:700, color:"#fff", background:(invalid||saving)?"var(--faint)":"var(--brand)", cursor:(invalid||saving)?"not-allowed":"pointer" }}>{saving?"Mentés…":<>{Ico.save()} Mentés</>}</button>
          </div>
        </div>
      </div>
    </div>
  );
}

// #endregion

// #region Helyszínek / kiszállás (NapokSelector, SegBtn, PlacesView, LocationModal)
/* ---- NapokSelector -------------------------------------------------- */
/** Hét napjainak bit-flag kódolása (H=1, K=2, Sze=4, ... V=64) a napszűréshez. */
const NAPOK_DAYS = [{bit:1,l:"H"},{bit:2,l:"K"},{bit:4,l:"Sze"},{bit:8,l:"Cs"},{bit:16,l:"P"},{bit:32,l:"Szo"},{bit:64,l:"V"}];
/** Hét napjait bit-flag alapon ki/bekapcsoló gombsor (rendelés aktív napjainak beállítására). */
function NapokSelector({ value, onChange }) {
  return (
    <div className="flex gap-1">
      {NAPOK_DAYS.map(d => {
        const on = !!(value & d.bit);
        return <button key={d.bit} type="button" onClick={()=>onChange((value^d.bit)&127)}
          className="rounded-md" style={{ fontSize:12, fontWeight:700, minWidth:30, padding:"4px 2px", textAlign:"center",
            background:on?"var(--brand)":"var(--surface-2)", color:on?"#fff":"var(--muted)",
            border:`1px solid ${on?"var(--brand)":"var(--border)"}` }}>
          {d.l}
        </button>;
      })}
    </div>
  );
}

/** Szegmentált (kapcsológomb-csoport) választó, pl. Belső/Külső/Kiszállás típus váltásra. */
function SegBtn({ options, value, onChange }) {
  return (
    <div className="flex rounded-lg overflow-hidden" style={{ border:"1px solid var(--border)", display:"inline-flex" }}>
      {options.map((o, i) => {
        const sel = o.v === value;
        return <button key={o.v} type="button" onClick={()=>onChange(o.v)}
          style={{ fontSize:13, fontWeight:700, padding:"7px 16px",
            background: sel ? "color-mix(in srgb,var(--brand) 12%,transparent)" : "transparent",
            color: sel ? "var(--brand)" : "var(--muted)",
            borderRight: i < options.length-1 ? "1px solid var(--border)" : "none" }}>
          {o.l}
        </button>;
      })}
    </div>
  );
}

/* ---- PlacesView / LocationModal (Rendelések) ------------------------- */
/** Rendelések/helyszínek listája szekciónként (belső/külső/kiszállás), drag&amp;drop sorrendezéssel. */
function PlacesView({ setToast, newSignal, query: searchQuery }) {
  const [data, setData]               = useState(null);
  const [loading, setLoading]         = useState(true);
  const [modal, setModal]             = useState(null);
  const [saving, setSaving]           = useState(false);
  const [orderedPlaces, setOrderedPlaces] = useState([]);
  const [dragId, setDragId]           = useState(null);
  const [dragGroup, setDragGroup]     = useState(null);
  const [dragOverId, setDragOverId]   = useState(null);
  const [secCollapsed, setSecCollapsed] = useState({});

  const load = useCallback(() => {
    setLoading(true);
    fetch(`${HMM_CONFIG.url}&getplaces=1`).then((r)=>r.json()).then((d)=>{ setData(d); setLoading(false); }).catch(()=>setLoading(false));
  }, []);
  useEffect(() => { load(); }, [load]);
  useEffect(() => { if (data?.places) setOrderedPlaces([...data.places].sort((a,b)=>a.sorrend-b.sorrend)); }, [data]);
  const initialSignal = useRef(newSignal);
  useEffect(() => { if (newSignal > initialSignal.current) openNew(); }, [newSignal]);

  const post = async (params) => {
    const body = new URLSearchParams(params);
    const resp = await fetch(HMM_CONFIG.url, { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:body.toString() });
    return resp.json();
  };

  const save = async (rec) => {
    setSaving(true);
    try {
      let result;
      if (!rec.id) {
        const kulso     = rec.cat === "kulso" ? 1 : 0;
        const kiszallas = rec.cat === "kiszallas" ? 1 : 0;
        result = await post({ addplace:"1", roleid:1, kulso, kiszallas, org:rec.org, megnev:rec.megnev, cim:rec.cim, rendelo:rec.rendelo||"", napok:rec.napok, orvos_kell:rec.orvos_kell??1, ktarto_nev:rec.ktarto_nev||"", ktarto_tel:rec.ktarto_tel||"", ktarto_email:rec.ktarto_email||"", color:rec.color||"", validfrom:rec.validfrom||"", validto:rec.validto||"", forday:rec.forday||"" });
      } else {
        result = await post({ saveplace:"1", id:rec.id, megnev:rec.megnev, cim:rec.cim, rendelo:rec.rendelo||"", sorrend:rec.sorrend, org:rec.org, napok:rec.napok, cat:rec.cat, orvos_kell:rec.orvos_kell??1, ktarto_nev:rec.ktarto_nev||"", ktarto_tel:rec.ktarto_tel||"", ktarto_email:rec.ktarto_email||"", color:rec.color||"", validfrom:rec.validfrom||"", validto:rec.validto||"", forday:rec.forday||"" });
      }
      if (result.status==="ok") { await load(); setModal(null); setToast(rec.id ? "Rendelés mentve!" : "Rendelés létrehozva!"); }
      else setToast("Hiba: "+(result.message||"Ismeretlen hiba"));
    } catch(e) { setToast("Hálózati hiba!"); }
    finally { setSaving(false); }
  };

  const remove = async (id) => {
    setSaving(true);
    try {
      const result = await post({ deleteplace:"1", id });
      if (result.status==="ok") { await load(); setModal(null); setToast("Rendelés törölve."); }
      else setToast("Hiba: "+(result.message||"Ismeretlen hiba"));
    } catch(e) { setToast("Hálózati hiba!"); }
    finally { setSaving(false); }
  };

  const openNew = () => {
    setModal({ place:{ id:0, megnev:"", cim:"", rendelo:"", kulso:0, kiszallas:0, roleid:1, org:"HMM", sorrend:0, aktiv:1, napok:31, validfrom:"", validto:"", forday:"" } });
  };

  const getSectionKey = (p) => (p.forday && p.forday!=="0000-00-00") ? "egyszeri" : (p.kiszallas === 1 ? "kiszallas" : (p.kulso === 0 ? "belso" : "kulso"));

  const handleDrop = async (group, targetId) => {
    if (!dragId || dragId === targetId || dragGroup !== group) {
      setDragId(null); setDragGroup(null); setDragOverId(null);
      return;
    }
    const sectionPlaces = orderedPlaces.filter(p => getSectionKey(p) === group);
    const from = sectionPlaces.findIndex(p => p.id === dragId);
    const to   = sectionPlaces.findIndex(p => p.id === targetId);
    if (from === -1 || to === -1) { setDragId(null); setDragGroup(null); setDragOverId(null); return; }
    const newOrder = [...sectionPlaces];
    const [moved]  = newOrder.splice(from, 1);
    newOrder.splice(to, 0, moved);
    setOrderedPlaces([...orderedPlaces.filter(p => getSectionKey(p) !== group), ...newOrder]);
    setDragId(null); setDragGroup(null); setDragOverId(null);
    try { await post({ saveplaceorder:"1", ids: newOrder.map(p => p.id).join(",") }); }
    catch(e) { setToast("Hálózati hiba!"); }
  };

  if (loading || !data) return <LoadingBlock label="Rendelések betöltése…"/>;

  const q = (searchQuery || "").trim().toLowerCase();

  const SECTIONS = [
    { key:"belso",     label:"Belső rendelések", accent:CATS.belso.color     },
    { key:"kulso",     label:"Külső rendelések", accent:CATS.kulso.color     },
    { key:"kiszallas", label:"Kiszállások",      accent:CATS.kiszallas.color },
    { key:"egyszeri",  label:"Egyszeri rendelések", accent:"#2563eb"         },
  ];

  return (
    <div className="mb-scroll px-4 lg:px-6 py-4" style={{ flex:"1 1 auto", minHeight:0, overflowY:"auto" }}>
      <div className="flex flex-col gap-3" style={{ maxWidth:760 }}>
        {SECTIONS.map((sec) => {
          const places = orderedPlaces.filter(p => getSectionKey(p) === sec.key && (!q || `${p.megnev} ${p.rendelo||""} ${p.cim||""}`.toLowerCase().includes(q)));
          const SectionIcon = sec.key === "kiszallas" ? Ico.truck : sec.key === "egyszeri" ? Ico.calendar : Ico.building;
          return (
            <div key={sec.key} className="rounded-xl overflow-hidden" style={{ background:"var(--surface)", border:"1px solid var(--border-soft)" }}>
              <button onClick={()=>setSecCollapsed(p=>({...p,[sec.key]:!p[sec.key]}))} className="flex w-full items-center justify-between px-3 py-2.5" style={{ borderBottom:secCollapsed[sec.key]?"none":"1px solid var(--border-soft)", background:`color-mix(in srgb,${sec.accent} 15%,transparent)` }}>
                <span className="flex items-center gap-2">
                  <span style={{ color:sec.accent }}>{SectionIcon({width:15,height:15})}</span>
                  <span className="mb-display" style={{ fontSize:13, fontWeight:700, letterSpacing:".04em", color:sec.accent }}>{sec.label}</span>
                  <span className="rounded-md px-1.5" style={{ fontSize:11, fontWeight:700, color:"var(--muted)", background:"var(--surface-2)" }}>{places.length}</span>
                </span>
                <span style={{ color:"var(--faint)" }}>{secCollapsed[sec.key]?Ico.chevDown({width:16,height:16}):Ico.chevUp({width:16,height:16})}</span>
              </button>
              {!secCollapsed[sec.key] && <div className="flex flex-col gap-1.5 p-2" onDragOver={(e)=>e.preventDefault()}>
                {places.map((p) => (
                  <div key={p.id}
                    draggable
                    onDragStart={()=>{ setDragId(p.id); setDragGroup(sec.key); }}
                    onDragOver={(e)=>{ e.preventDefault(); if (dragOverId !== p.id) setDragOverId(p.id); }}
                    onDragEnd={()=>{ setDragId(null); setDragGroup(null); setDragOverId(null); }}
                    onDrop={(e)=>{ e.preventDefault(); e.stopPropagation(); handleDrop(sec.key, p.id); }}
                    className="mb-tcard flex items-center gap-2 rounded-lg"
                    style={{ background:"var(--card)", border:"1px solid var(--border)", padding:"8px 10px", opacity:dragId===p.id?.4:1, outline:dragOverId===p.id&&dragGroup===sec.key?`2px solid ${sec.accent}`:"none", transition:"opacity .15s", cursor:"default" }}>
                    <div style={{ color:"var(--faint)", flexShrink:0, cursor:"grab" }}>
                      <svg width="12" height="16" viewBox="0 0 12 16" fill="currentColor">
                        <circle cx="3.5" cy="3" r="1.3"/><circle cx="8.5" cy="3" r="1.3"/>
                        <circle cx="3.5" cy="8" r="1.3"/><circle cx="8.5" cy="8" r="1.3"/>
                        <circle cx="3.5" cy="13" r="1.3"/><circle cx="8.5" cy="13" r="1.3"/>
                      </svg>
                    </div>
                    {p.color && <div style={{ width:14, height:14, borderRadius:3, background:p.color, flexShrink:0, border:"1px solid rgba(0,0,0,.15)" }}/>}
                    <div className="min-w-0 flex-1" onClick={()=>setModal({ place:p })} style={{ cursor:"pointer" }}>
                      <div className="flex items-center gap-1.5 flex-wrap">
                        <div className="truncate" style={{ fontSize:13.5, fontWeight:700 }}>{p.megnev}</div>
                        {(p.kiszallas===1||p.kulso===1) && <Badge text={p.org||"HMM"} color={orgColor(p.org)}/>}
                        {p.forday && p.forday!=="0000-00-00" ? (
                          <span style={{ fontSize:11, fontWeight:700, color:"#2563eb", background:"rgba(37,99,235,.12)", borderRadius:4, padding:"1px 5px" }}>{p.forday}</span>
                        ) : (p.napok??127)!==127 && <span style={{ fontSize:11, fontWeight:700, color:"var(--brand-ink)", background:"var(--brand-soft)", borderRadius:4, padding:"1px 5px" }}>{(p.napok??127)===31?"H–P":NAPOK_DAYS.filter(d=>((p.napok??127)&d.bit)).map(d=>d.l).join(" ")}</span>}
                        {p.kiszallas===1 && (() => {
                          const today = new Date().toISOString().slice(0,10);
                          if (!p.validfrom && !p.validto) return <span style={{ fontSize:11, fontWeight:700, color:"var(--faint)", background:"var(--surface-2)", borderRadius:4, padding:"1px 5px" }}>Nincs időszak</span>;
                          const active = !p.validto || today <= p.validto;
                          return <span style={{ fontSize:11, fontWeight:700, color:active?"var(--green)":"var(--danger-ink)", background:active?"var(--green-soft)":"var(--danger-soft)", borderRadius:4, padding:"1px 5px" }}>{active?"Aktív":"Lejárt"}</span>;
                        })()}
                      </div>
                      {!!p.rendelo && <div className="truncate" style={{ fontSize:11.5, color:"var(--muted)", fontWeight:600 }}>{p.rendelo}</div>}
                      {!!p.cim && <div className="truncate" style={{ fontSize:11.5, color:"var(--muted)" }}>{p.cim}</div>}
                      {p.kiszallas===1 && (p.validfrom||p.validto) && (
                        <div style={{ fontSize:11, color:"var(--muted)", marginTop:1 }}>{p.validfrom||"?"} – {p.validto||"?"}</div>
                      )}
                    </div>
                  </div>
                ))}
                {places.length===0 && <div className="px-1 py-2" style={{ fontSize:12, color:"var(--faint)" }}>Nincs rendelés.</div>}
              </div>}
            </div>
          );
        })}
      </div>
      {modal && modal.place && <LocationModal ctx={modal} onClose={()=>setModal(null)} onSave={save} onDelete={remove} saving={saving}/>}
    </div>
  );
}

/** Rendelés/helyszín felviteli/szerkesztő modal (cím, napok, orvos szükséges, szín, kapcsolattartó, kiszállás időszak). */
function LocationModal({ ctx, onClose, onSave, onDelete, saving }) {
  const p = ctx.place;
  const isNew = !p.id;
  const catFromP = () => p.kiszallas===1 ? "kiszallas" : (p.kulso===0 ? "belso" : "kulso");

  const [megnev,       setMegnev]      = useState(p.megnev||"");
  const [cim,          setCim]         = useState(p.cim||"");
  const [rendelo,      setRendelo]     = useState(p.rendelo||"");
  const [org,          setOrg]         = useState(p.org||"HMM");
  const [napok,        setNapok]       = useState(p.napok ?? 31);
  const [cat,          setCat]         = useState(catFromP());
  const [ktartoNev,    setKtartoNev]   = useState(p.ktarto_nev||"");
  const [ktartoTel,    setKtartoTel]   = useState(p.ktarto_tel||"");
  const [ktartoEmail,  setKtartoEmail] = useState(p.ktarto_email||"");
  const [orvosKell,    setOrvosKell]   = useState((p.orvos_kell ?? 1) !== 0);
  const [color,        setColor]       = useState(p.color||"");
  const [validfrom,    setValidfrom]   = useState(p.validfrom||"");
  const [validto,      setValidto]     = useState(p.validto||"");
  const [egyszeri,     setEgyszeri]    = useState(!!(p.forday && p.forday!=="0000-00-00"));
  const [forday,       setForday]      = useState(p.forday && p.forday!=="0000-00-00" ? p.forday : "");

  useEffect(() => { const h=(e)=>e.key==="Escape"&&onClose(); document.addEventListener("keydown",h); return ()=>document.removeEventListener("keydown",h); }, [onClose]);

  const invalid = megnev.trim()==="" || (egyszeri && cat!=="kiszallas" && !forday);
  const save = () => onSave({ id:p.id||0, megnev:megnev.trim(), cim, rendelo, sorrend:p.sorrend||0, org, napok, cat, orvos_kell:orvosKell?1:0, ktarto_nev:ktartoNev, ktarto_tel:ktartoTel, ktarto_email:ktartoEmail, color:color||null, validfrom:cat==="kiszallas"?validfrom:"", validto:cat==="kiszallas"?validto:"", forday:(egyszeri&&cat!=="kiszallas")?forday:"" });

  const CAT_OPTS = [{v:"belso",l:"Belső"},{v:"kulso",l:"Külső"},{v:"kiszallas",l:"Kiszállás"}];

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center p-4 sm:p-6 mb-scroll" style={{ overflowY:"auto" }}>
      <div className="mb-back fixed inset-0" style={{ background:"rgba(4,6,10,.55)", backdropFilter:"blur(4px)" }} onClick={onClose}/>
      <div className="mb-pop relative w-full rounded-2xl overflow-hidden" style={{ maxWidth:700, background:"var(--surface)", border:"1px solid var(--border)", boxShadow:"0 50px 100px -28px rgba(0,0,0,.6)", marginTop:16, marginBottom:16 }}>
        <div className="flex items-center justify-between gap-3 px-6 py-4" style={{ borderBottom:"1px solid var(--border)" }}>
          <div>
            <h2 className="mb-display" style={{ fontSize:19, fontWeight:700 }}>{isNew ? "Új rendelés" : (p.megnev||"Rendelés szerkesztése")}</h2>
            {!isNew && (
              <div className="flex items-center gap-2 mt-1">
                <Badge text={CATS[cat]?.type||cat} color={CATS[cat]?.color||"var(--muted)"}/>
              </div>
            )}
          </div>
          <button onClick={onClose} className="mb-btn flex h-8 w-8 items-center justify-center rounded-lg" style={{ color:"var(--muted)", background:"var(--surface-2)" }}>{Ico.x()}</button>
        </div>
        <div className="grid gap-6 px-6 py-5 mb-scroll" style={{ gridTemplateColumns:"minmax(0,1.4fr) minmax(0,1fr)", maxHeight:"72vh", overflowY:"auto" }}>
          <div className="flex flex-col gap-4">
            <Field label="Megnevezés">
              <input autoFocus={isNew} value={megnev} onChange={(e)=>setMegnev(e.target.value)} className="mb-in px-3 py-2.5" style={{ fontSize:13.5, fontWeight:700 }}/>
            </Field>
            <div className="flex gap-4 flex-wrap">
              <Field label="Típus">
                <SegBtn value={cat} onChange={setCat} options={CAT_OPTS}/>
              </Field>
              {cat !== "belso" && (
                <Field label="Szervező">
                  <SegBtn value={org} onChange={setOrg} options={[{v:"HMM",l:"HMM"},{v:"Keltexmed",l:"Keltexmed"}]}/>
                </Field>
              )}
            </div>
            <Field label="Rendelő / szoba">
              <input value={rendelo} onChange={(e)=>setRendelo(e.target.value)} placeholder="pl. Rendelő 1, Ultrahang szoba" className="mb-in px-3 py-2.5" style={{ fontSize:13.5 }}/>
            </Field>
            <Field label="Cím (Google Maps)">
              <div className="flex items-center gap-2">
                <input value={cim} onChange={(e)=>setCim(e.target.value)} placeholder="pl. Budapest, Váci út 45" className="mb-in px-3 py-2.5" style={{ fontSize:13.5 }}/>
                {!!cim && <a href={`https://www.google.com/maps/search/${encodeURIComponent(cim)}`} target="_blank" rel="noreferrer" title="Megnyitás Google Maps-ben" style={{ color:"var(--brand-ink)", flexShrink:0 }}>{Ico.place({width:17,height:17})}</a>}
              </div>
            </Field>
          </div>
          <div className="flex flex-col gap-4">
            <div style={{ fontSize:12, fontWeight:700 }}>Részletek</div>
            {cat !== "kiszallas" && (
              <>
                <Field label="Ismétlődés">
                  <SegBtn value={egyszeri?"egyszeri":"ismetlodo"} onChange={(v)=>setEgyszeri(v==="egyszeri")} options={[{v:"ismetlodo",l:"Ismétlődő"},{v:"egyszeri",l:"Egyszeri"}]}/>
                </Field>
                {egyszeri ? (
                  <Field label="Dátum">
                    <input type="date" value={forday} onChange={(e)=>setForday(e.target.value)} className="mb-in px-3 py-2.5 mb-mono" style={{ fontSize:13.5 }}/>
                  </Field>
                ) : (
                  <Field label="Aktív napok">
                    <NapokSelector value={napok} onChange={setNapok}/>
                  </Field>
                )}
              </>
            )}
            {cat === "kiszallas" && (
              <div className="rounded-xl p-3 flex flex-col gap-2.5" style={{ background:"var(--surface-2)", border:"1px solid var(--border)" }}>
                <div style={{ fontSize:12, fontWeight:700, color:"var(--muted)" }}>Érvényességi időszak</div>
                <Field label="Kezdő dátum">
                  <input type="date" value={validfrom} onChange={(e)=>setValidfrom(e.target.value)} className="mb-in px-3 py-2" style={{ fontSize:13, width:"100%" }}/>
                </Field>
                <Field label="Záró dátum">
                  <input type="date" value={validto} onChange={(e)=>setValidto(e.target.value)} className="mb-in px-3 py-2" style={{ fontSize:13, width:"100%" }}/>
                </Field>
              </div>
            )}
            {cat !== "kiszallas" && (
              <label className="flex items-center gap-2 cursor-pointer">
                <Toggle on={orvosKell} onChange={setOrvosKell}/>
                <span style={{ fontSize:12.5, fontWeight:600 }}>Orvos szükséges</span>
              </label>
            )}
            <Field label="Kártya szín">
              <div className="flex items-center gap-2">
                <input type="color" value={color||"#ffffff"} onChange={(e)=>setColor(e.target.value)} style={{ width:36, height:32, padding:2, borderRadius:6, border:"1px solid var(--border)", cursor:"pointer", background:"var(--surface)" }}/>
                <input value={color} onChange={(e)=>{ const v=e.target.value; if(v===""||/^#[0-9a-fA-F]{0,6}$/.test(v)) setColor(v); }} placeholder="#rrggbb (üres = nincs)" className="mb-in px-3 py-2" style={{ fontSize:13, fontFamily:"monospace", flex:1 }}/>
                {color && <button onClick={()=>setColor("")} title="Szín törlése" style={{ color:"var(--faint)", flexShrink:0 }}>{Ico.x({width:14,height:14})}</button>}
              </div>
            </Field>
            {cat !== "belso" && (
              <div className="rounded-xl p-3 flex flex-col gap-2.5" style={{ background:"var(--surface-2)", border:"1px solid var(--border)" }}>
                <div style={{ fontSize:12, fontWeight:700, color:"var(--muted)" }}>Kapcsolattartó</div>
                <Field label="Név">
                  <input value={ktartoNev} onChange={(e)=>setKtartoNev(e.target.value)} placeholder="pl. Kovács Péter" className="mb-in px-3 py-2" style={{ fontSize:13 }}/>
                </Field>
                <Field label="Telefon">
                  <input value={ktartoTel} onChange={(e)=>setKtartoTel(e.target.value)} placeholder="+36 30 123 4567" className="mb-in px-3 py-2" style={{ fontSize:13 }}/>
                </Field>
                <Field label="E-mail">
                  <input value={ktartoEmail} onChange={(e)=>setKtartoEmail(e.target.value)} placeholder="pelda@email.hu" className="mb-in px-3 py-2" style={{ fontSize:13 }}/>
                </Field>
              </div>
            )}
          </div>
        </div>
        <div className="flex items-center justify-between gap-3 px-6 py-4" style={{ borderTop:"1px solid var(--border)", background:"var(--bg)" }}>
          {!isNew ? (
            <button onClick={()=>{ if(confirm("Biztosan törlöd ezt a rendelést?")) onDelete(p.id); }} className="flex items-center gap-1.5 rounded-lg px-3 py-2" style={{ fontSize:13, fontWeight:600, color:"var(--danger-ink)" }}>{Ico.trash()} Törlés</button>
          ) : <span/>}
          <div className="flex items-center gap-2">
            <button onClick={onClose} className="mb-btn rounded-lg px-4 py-2.5" style={{ fontSize:13.5, fontWeight:600, color:"var(--muted)", border:"1px solid var(--border)" }}>Mégse</button>
            <button disabled={invalid||saving} onClick={save} className="mb-prim flex items-center gap-1.5 rounded-lg px-5 py-2.5" style={{ fontSize:13.5, fontWeight:700, color:"#fff", background:(invalid||saving)?"var(--faint)":"var(--brand)", cursor:(invalid||saving)?"not-allowed":"pointer" }}>
              {saving?"Mentés…":<>{Ico.save()} {isNew?"Létrehozás":"Mentés"}</>}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

// #endregion

// #region Szabadságok — lista nézet (VacationsView)
/* ---- VacationsView / VacationModal (Szabadságok) --------------------- */
/** Szabadságok/elérhető napok listája szekciónként (függő/elfogadott/elérhető/archivált), elfogadás/elutasítás/törlés műveletekkel. */
function VacationsView({ setToast, newSignal, query: searchQuery }) {
  const [data, setData]                   = useState(null);
  const [loading, setLoading]             = useState(true);
  const [modalOpen, setModalOpen]         = useState(false);
  const [elerhetoOpen, setElerhetoOpen]   = useState(false);
  const [busyId, setBusyId]               = useState(null);
  const [secCollapsed, setSecCollapsed]   = useState({});

  const initialSignal = useRef(newSignal);
  useEffect(() => { if (newSignal > initialSignal.current) setModalOpen(true); }, [newSignal]);

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
    const result = await post({ addvacation:"1", workerid:rec.workerid, tol:rec.tol, ig:rec.ig, tipus:rec.tipus, megj:rec.megj||"" });
    if (result.status==="ok") { await load(); setModalOpen(false); setToast("Szabadság rögzítve!"); }
    else setToast("Hiba: "+(result.message||"Ismeretlen hiba"));
  };

  const addElerheto = async (rec) => {
    const result = await post({ addvacation:"1", workerid:rec.workerid, tol:rec.tol, ig:rec.ig, tipus:"Elérhető", megj:rec.megj||"" });
    if (result.status==="ok") { await load(); setElerhetoOpen(false); setToast("Elérhető nap rögzítve!"); }
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

  const today = new Date().toISOString().slice(0,10);
  const vacations = data.vacations || [];
  const isFuture  = (v) => v.to >= today;
  const isPast    = (v) => v.to < today;
  const q = (searchQuery || "").trim().toLowerCase();
  const filterVac = (v) => !q || `${v.workerName||""} ${v.tipus||""}`.toLowerCase().includes(q);

  const isVac     = (v) => v.tipus !== "Elérhető";
  const isElerheto = (v) => v.tipus === "Elérhető";

  const sections = [
    {
      key:"pending",  label:"Függő szabadságok",   color:"var(--brand)",  icon:"clock",
      items: vacations.filter((v) => isVac(v) && (v.status===0||v.status===-1) && isFuture(v) && filterVac(v)).sort((a,b)=>a.from.localeCompare(b.from)),
    },
    {
      key:"approved", label:"Elfogadott szabadság", color:"var(--green)",  icon:"sun",
      items: vacations.filter((v) => isVac(v) && v.status===1 && isFuture(v) && filterVac(v)),
    },
    {
      key:"elerheto", label:"Elérhető napok", color:"#2563eb", icon:"calendar",
      emptyText: "Nincs rögzített elérhető nap.",
      action: { label:"Hozzáadás", onClick: ()=>setElerhetoOpen(true) },
      items: vacations.filter((v) => isElerheto(v) && filterVac(v)),
    },
    {
      key:"archived", label:"Archivált",            color:"var(--muted)",  icon:"calendar",
      items: vacations.filter((v) => isVac(v) && (isPast(v) || v.status===2) && filterVac(v)),
    },
  ];

  const VacCard = ({ v, secKey }) => (
    <div className="flex items-center justify-between gap-3 rounded-lg flex-wrap" style={{ background:"var(--card)", border:"1px solid var(--border)", padding:"8px 10px" }}>
      <div className="min-w-0">
        <div className="flex items-center gap-1.5">
          <div className="truncate" style={{ fontSize:13.5, fontWeight:700 }}>{v.workerName}</div>
          <Badge text={v.tipus||"Szabadság"} color={VACATION_TYPE_COLORS[v.tipus]||VACATION_TYPE_COLORS["Szabadság"]}/>
          {v.status===2 && <Badge text="Elutasítva" color="var(--danger)"/>}
        </div>
        <div className="mb-mono" style={{ fontSize:11.5, color:"var(--muted)" }}>
          {fmtShortISO(v.from)}{v.to!==v.from?` – ${fmtShortISO(v.to)}`:""} · {v.days} nap{v.status===-1?" · vegyes állapot":""}
        </div>
        {!!v.megj && <div style={{ fontSize:12, color:"var(--muted)", fontStyle:"italic", marginTop:1 }}>{v.megj}</div>}
      </div>
      <div className="flex items-center gap-1.5 flex-shrink-0">
        {secKey==="pending" && (<>
          <button disabled={busyId===v.groupid} onClick={()=>setStatus(v.groupid,1)} className="rounded-lg px-2.5 py-1.5" style={{ fontSize:12, fontWeight:600, color:"#fff", background:"var(--green)" }}>Elfogadás</button>
          <button disabled={busyId===v.groupid} onClick={()=>setStatus(v.groupid,2)} className="rounded-lg px-2.5 py-1.5" style={{ fontSize:12, fontWeight:600, color:"#fff", background:"var(--danger)" }}>Elutasítás</button>
        </>)}
        {secKey==="approved" && (
          <button disabled={busyId===v.groupid} onClick={()=>setStatus(v.groupid,0)} className="mb-btn rounded-lg px-2.5 py-1.5" style={{ fontSize:12, fontWeight:600, color:"var(--muted)", border:"1px solid var(--border)" }}>Visszavonás</button>
        )}
        <button disabled={busyId===v.groupid} onClick={()=>remove(v.groupid)} className="mb-btn flex h-8 w-8 items-center justify-center rounded-lg" style={{ color:"var(--danger-ink)" }}>{Ico.trash()}</button>
      </div>
    </div>
  );

  return (
    <div className="mb-scroll px-4 lg:px-6 py-4" style={{ flex:"1 1 auto", minHeight:0, overflowY:"auto" }}>
      <div className="flex flex-col gap-3" style={{ maxWidth:760 }}>
        {sections.map((sec) => {
          const SectionIcon = Ico[sec.icon];
          const collapsed = !!secCollapsed[sec.key];
          const toggle = () => setSecCollapsed((p)=>({...p,[sec.key]:!p[sec.key]}));
          return (
            <div key={sec.key} className="rounded-xl overflow-hidden" style={{ background:"var(--surface)", border:"1px solid var(--border-soft)" }}>
              <button onClick={toggle} className="flex w-full items-center justify-between px-3 py-2.5" style={{ borderBottom:collapsed?"none":"1px solid var(--border-soft)", background:`color-mix(in srgb,${sec.color} 8%,transparent)` }}>
                <span className="flex items-center gap-2">
                  <span style={{ color:sec.color }}>{SectionIcon({width:15,height:15})}</span>
                  <span style={{ fontSize:13, fontWeight:700 }}>{sec.label}</span>
                  <span className="rounded-md px-1.5" style={{ fontSize:11, fontWeight:700, color:"var(--muted)", background:"var(--surface-2)" }}>{sec.items.length}</span>
                </span>
                <span className="flex items-center gap-2">
                  {sec.action && (
                    <span onClick={(e)=>{ e.stopPropagation(); sec.action.onClick(); }} className="flex items-center gap-1 rounded-lg px-2.5 py-1" style={{ fontSize:12, fontWeight:700, color:"#fff", background:sec.color }}>
                      {Ico.plus()} {sec.action.label}
                    </span>
                  )}
                  <span style={{ color:"var(--faint)" }}>{collapsed?Ico.chevDown({width:16,height:16}):Ico.chevUp({width:16,height:16})}</span>
                </span>
              </button>
              {!collapsed && <div className="flex flex-col gap-1.5 p-2">
                {sec.items.map((v) => <VacCard key={v.groupid} v={v} secKey={sec.key}/>)}
                {sec.items.length===0 && <div className="px-1 py-2" style={{ fontSize:12, color:"var(--faint)" }}>{sec.emptyText||"Nincs ilyen szabadság."}</div>}
              </div>}
            </div>
          );
        })}
      </div>

      {modalOpen && <VacationModal workers={data.workers} onClose={()=>setModalOpen(false)} onSave={addVacation}/>}
      {elerhetoOpen && <VacationModal workers={data.workers} onClose={()=>setElerhetoOpen(false)} onSave={addElerheto} forceTipus="Elérhető"/>}
    </div>
  );
}

// #endregion

// #region Statisztika
/* ---- StatisticsView -------------------------------------------------- */
/** Havi munkaidő-statisztika munkatársanként: kvóta, beosztott órák, eltérés, túlóra állapot, export. */
function StatisticsView({ setToast }) {
  const [data,    setData]    = useState(null);
  const [loading, setLoading] = useState(true);
  const [month,   setMonth]   = useState(() => new Date().toISOString().slice(0,7));

  const load = useCallback(() => {
    setLoading(true);
    fetch(`${HMM_CONFIG.url}&getmonthhours=1&month=${month}`)
      .then((r)=>r.json())
      .then((d)=>{ setData(d); setLoading(false); })
      .catch(()=>setLoading(false));
  }, [month]);
  useEffect(() => { load(); }, [load]);

  const shiftMonth = (delta) => {
    const d = new Date(month+"-01"); d.setMonth(d.getMonth()+delta);
    setMonth(d.toISOString().slice(0,7));
  };

  if (loading || !data) return <LoadingBlock label="Statisztika betöltése…"/>;

  const workers = data.workers || [];
  const grouped = { 1:[], 2:[], 3:[] };
  workers.forEach((w) => { if (grouped[w.roleid]) grouped[w.roleid].push(w); });

  const ROLE_ICONS  = { 1:"doctor", 2:"person", 3:"building" };
  const ROLE_COLORS = { 1:"var(--blue)", 2:"var(--purple)", 3:"var(--green)" };
  const ROLE_LABELS = { 1:"Orvosok", 2:"Asszisztensek", 3:"Irodai munkatársak" };

  const weeksInMonth = (m) => {
    const d = new Date(m+"-01");
    return Math.ceil(new Date(d.getFullYear(), d.getMonth()+1, 0).getDate() / 7);
  };
  const effectiveQuota = (w) => {
    if (w.quota == null) return null;
    return w.munkaora_tipus === "heti" ? w.quota * weeksInMonth(month) : w.quota;
  };
  const statusInfo = (w) => {
    const eq = effectiveQuota(w);
    if (eq == null) return null;
    const pct = w.booked / eq;
    if (pct > 1.0)  return { color:"var(--danger)",  label:"Túlóra" };
    if (pct >= 0.9) return { color:"var(--orange,#f59e0b)", label:"Közel" };
    return { color:"var(--green)", label:"OK" };
  };

  const exportUrl = `${HMM_CONFIG.url}&exportstatistics=1&month=${month}`;

  return (
    <div className="mb-scroll px-4 lg:px-6 py-4" style={{ flex:"1 1 auto", minHeight:0, overflowY:"auto" }}>
      <div className="flex items-center gap-3 mb-4 flex-wrap">
        <div className="flex items-center gap-2">
          <button onClick={()=>shiftMonth(-1)} className="mb-btn flex h-8 w-8 items-center justify-center rounded-lg" style={{ color:"var(--muted)", border:"1px solid var(--border)" }}>{Ico.left()}</button>
          <span style={{ fontSize:15, fontWeight:700 }}>{month}</span>
          <button onClick={()=>shiftMonth(1)} className="mb-btn flex h-8 w-8 items-center justify-center rounded-lg" style={{ color:"var(--muted)", border:"1px solid var(--border)" }}>{Ico.right()}</button>
        </div>
        <a href={exportUrl} className="flex items-center gap-1.5 rounded-lg px-4 py-2" style={{ fontSize:13, fontWeight:700, color:"#fff", background:"var(--green)", textDecoration:"none" }}>{Ico.ext({width:14,height:14})} Jelenléti export (.xlsx)</a>
      </div>
      <div className="flex flex-col gap-3" style={{ maxWidth:820 }}>
        {[1,2,3].map((rid) => {
          const ws = (grouped[rid]||[]).filter((w) => w.booked > 0 || w.quota != null);
          if (!ws.length) return null;
          const RoleIcon = Ico[ROLE_ICONS[rid]];
          return (
            <div key={rid} className="rounded-xl overflow-hidden" style={{ background:"var(--surface)", border:"1px solid var(--border-soft)" }}>
              <div className="flex items-center gap-2 px-3 py-2.5" style={{ background:`color-mix(in srgb,${ROLE_COLORS[rid]} 10%,transparent)`, borderBottom:"1px solid var(--border-soft)" }}>
                <span style={{ color:ROLE_COLORS[rid] }}>{RoleIcon({width:15,height:15})}</span>
                <span style={{ fontSize:13, fontWeight:700, color:ROLE_COLORS[rid] }}>{ROLE_LABELS[rid]}</span>
              </div>
              <div className="overflow-x-auto">
                <table style={{ width:"100%", borderCollapse:"collapse", fontSize:13 }}>
                  <thead>
                    <tr style={{ borderBottom:"1px solid var(--border-soft)" }}>
                      {["Munkatárs","Kvóta","Beosztott","Különbség","Állapot"].map((h,i)=>(
                        <th key={i} style={{ padding:"7px 10px", textAlign:i===0?"left":"right", fontWeight:600, color:"var(--muted)", fontSize:11.5, whiteSpace:"nowrap" }}>{h}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {ws.map((w) => {
                      const st   = statusInfo(w);
                      const eq   = effectiveQuota(w);
                      const diff = eq != null ? w.booked - eq : null;
                      const quotaLabel = w.quota != null ? `${w.quota} h/${w.munkaora_tipus==="heti"?"hét":"hó"}` : "—";
                      return (
                        <tr key={w.id} style={{ borderBottom:"1px solid var(--border-soft)" }}>
                          <td style={{ padding:"8px 10px", fontWeight:600 }}>{w.teljesnev||w.nev}</td>
                          <td style={{ padding:"8px 10px", textAlign:"right", color:"var(--muted)" }}>{quotaLabel}</td>
                          <td style={{ padding:"8px 10px", textAlign:"right", fontWeight:700 }}>{w.booked.toFixed(1)} h</td>
                          <td style={{ padding:"8px 10px", textAlign:"right", color:diff>0?"var(--danger)":diff<0?"var(--green)":"var(--muted)" }}>
                            {diff!=null?`${diff>0?"+":""}${diff.toFixed(1)} h`:"—"}
                          </td>
                          <td style={{ padding:"8px 10px", textAlign:"right" }}>
                            {st ? <span className="rounded-md px-2 py-0.5" style={{ fontSize:11, fontWeight:700, color:st.color, background:`color-mix(in srgb,${st.color} 15%,transparent)` }}>{st.label}</span> : <span style={{ color:"var(--muted)" }}>—</span>}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          );
        })}
        {workers.every((w)=>w.booked===0) && <div className="text-center py-10" style={{ fontSize:13.5, color:"var(--muted)" }}>Ebben a hónapban még nincs beosztás.</div>}
      </div>
    </div>
  );
}

// #endregion

// #region Szabadságok — dátum input és felviteli modal (TriDateInput, VacationModal)
/** Három részes (év/hó/nap) dátum beviteli mező auto-ugrással a mezők között, natív date picker gombbal. */
function TriDateInput({ value, onChange, className, style }) {
  const [y, setY] = useState('');
  const [m, setM] = useState('');
  const [d, setD] = useState('');
  const mRef      = useRef(null);
  const dRef      = useRef(null);
  const pickerRef = useRef(null);

  useEffect(() => {
    if (value && value.match(/^\d{4}-\d{2}-\d{2}$/)) {
      const parts = value.split('-');
      setY(parts[0]); setM(parts[1]); setD(parts[2]);
    } else if (!value) {
      setY(''); setM(''); setD('');
    }
  }, [value]);

  const emit = (year, month, day) => {
    if (year.length === 4 && month.length === 2 && day.length === 2) onChange(year + '-' + month + '-' + day);
    else onChange('');
  };

  const handleY = (e) => {
    const v = e.target.value.replace(/\D/g, '').slice(0, 4);
    setY(v); emit(v, m, d);
    if (v.length === 4) mRef.current?.focus();
  };
  const handleM = (e) => {
    const v = e.target.value.replace(/\D/g, '').slice(0, 2);
    setM(v); emit(y, v, d);
    if (v.length === 2) dRef.current?.focus();
  };
  const handleD = (e) => {
    const v = e.target.value.replace(/\D/g, '').slice(0, 2);
    setD(v); emit(y, m, v);
  };

  const handlePicker = (e) => {
    const val = e.target.value;
    if (val) {
      const parts = val.split('-');
      setY(parts[0]); setM(parts[1]); setD(parts[2]);
      onChange(val);
    }
  };

  const sharedStyle = { ...style, textAlign:'center', padding:'10px 6px' };
  return (
    <div style={{ display:'flex', alignItems:'center', gap:4 }}>
      <input type="text" inputMode="numeric" value={y} onChange={handleY} maxLength={4} placeholder="ÉÉÉÉ" className={className} style={{ ...sharedStyle, width:56 }}/>
      <span style={{ color:'var(--muted)', fontWeight:700 }}>-</span>
      <input type="text" inputMode="numeric" value={m} onChange={handleM} ref={mRef} maxLength={2} placeholder="HH" className={className} style={{ ...sharedStyle, width:36 }}/>
      <span style={{ color:'var(--muted)', fontWeight:700 }}>-</span>
      <input type="text" inputMode="numeric" value={d} onChange={handleD} ref={dRef} maxLength={2} placeholder="NN" className={className} style={{ ...sharedStyle, width:36 }}/>
      <input type="date" ref={pickerRef} value={value || ''} onChange={handlePicker} tabIndex={-1} style={{ position:'absolute', opacity:0, width:0, height:0, pointerEvents:'none' }}/>
      <button type="button" onClick={() => pickerRef.current?.showPicker()} className="mb-btn" style={{ padding:'6px 8px', borderRadius:6, border:'1px solid var(--border)', background:'var(--surface-2)', cursor:'pointer', color:'var(--muted)', flexShrink:0 }}>
        {Ico.calendar({ width:16, height:16 })}
      </button>
    </div>
  );
}

/** Új szabadság / elérhető nap felviteli modal, munkatárs kereséssel és típusválasztóval. */
function VacationModal({ workers, onClose, onSave, forceTipus }) {
  const [workerSearch, setWorkerSearch] = useState("");

  const filtered = useMemo(() => {
    const q = workerSearch.trim().toLowerCase();
    if (!q) return workers || [];
    return (workers||[]).filter(w =>
      w.nev.toLowerCase().includes(q) || (w.teljesnev||"").toLowerCase().includes(q)
    );
  }, [workers, workerSearch]);

  const grouped = useMemo(() => {
    const m = new Map();
    filtered.forEach((w) => { if (!m.has(w.rolenev)) m.set(w.rolenev, []); m.get(w.rolenev).push(w); });
    return Array.from(m.entries());
  }, [filtered]);

  const [workerid, setWorkerid] = useState(workers?.[0]?.id ? String(workers[0].id) : "");
  const [tol, setTol]           = useState("");
  const [ig, setIg]             = useState("");
  const [tipus, setTipus]       = useState(forceTipus || "Szabadság");
  const [megj, setMegj]         = useState("");
  const [saving, setSaving]     = useState(false);

  useEffect(() => {
    if (workerid && !filtered.find(w => String(w.id) === workerid)) {
      setWorkerid(filtered[0]?.id ? String(filtered[0].id) : "");
    }
  }, [filtered]);

  useEffect(() => { const h=(e)=>e.key==="Escape"&&onClose(); document.addEventListener("keydown",h); return ()=>document.removeEventListener("keydown",h); }, [onClose]);

  const invalid = !workerid || !tol || !ig || tol > ig;
  const showMegj = tipus === "Egyéb" || forceTipus === "Elérhető";
  const save = async () => { setSaving(true); await onSave({ workerid, tol, ig, tipus, megj }); setSaving(false); };

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center p-4 sm:p-6 mb-scroll" style={{ overflowY:"auto" }}>
      <div className="mb-back fixed inset-0" style={{ background:"rgba(4,6,10,.55)", backdropFilter:"blur(4px)" }} onClick={onClose}/>
      <div className="mb-pop relative w-full rounded-2xl overflow-hidden" style={{ maxWidth:460, background:"var(--surface)", border:"1px solid var(--border)", boxShadow:"0 50px 100px -28px rgba(0,0,0,.6)", marginTop:40 }}>
        <div className="flex items-center justify-between gap-3 px-6 py-4" style={{ borderBottom:"1px solid var(--border)" }}>
          <h2 className="mb-display" style={{ fontSize:18, fontWeight:700 }}>{forceTipus === "Elérhető" ? "Új elérhető nap" : "Új szabadság"}</h2>
          <button onClick={onClose} className="mb-btn flex h-8 w-8 items-center justify-center rounded-lg" style={{ color:"var(--muted)", background:"var(--surface-2)" }}>{Ico.x()}</button>
        </div>
        <div className="flex flex-col gap-3.5 px-6 py-5">
          <Field label="Munkatárs">
            <input
              type="text"
              value={workerSearch}
              onChange={(e)=>setWorkerSearch(e.target.value)}
              placeholder="Keresés..."
              className="mb-in px-3 py-2"
              style={{ fontSize:13, marginBottom:4, display:"block", width:"100%", boxSizing:"border-box" }}
              autoComplete="off"
            />
            <select value={workerid} onChange={(e)=>setWorkerid(e.target.value)} className="mb-in px-3 py-2.5" style={{ fontSize:13.5, fontWeight:600 }}>
              {grouped.map(([rolenev, ws]) => (
                <optgroup key={rolenev} label={rolenev}>
                  {ws.map((w) => <option key={w.id} value={w.id}>{w.nev}</option>)}
                </optgroup>
              ))}
            </select>
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Kezdő nap"><TriDateInput value={tol} onChange={setTol} className="mb-in" style={{ fontSize:13.5 }}/></Field>
            <Field label="Utolsó nap"><TriDateInput value={ig} onChange={setIg} className="mb-in" style={{ fontSize:13.5 }}/></Field>
          </div>
          {!forceTipus && (
            <Field label="Típus">
              <MiniSelect value={tipus} onChange={(v)=>{ setTipus(v); if (v!=="Egyéb") setMegj(""); }} options={VACATION_TYPES.map((t)=>({ v:t, l:t }))}/>
            </Field>
          )}
          {showMegj && (
            <Field label="Megjegyzés">
              <div className="relative">
                <textarea value={megj} maxLength={200} onChange={(e)=>setMegj(e.target.value)} rows={2} placeholder={forceTipus==="Elérhető" ? "pl. Miskolc, délelőtt" : "pl. Egyéb indok"} className="mb-in px-3 py-2.5" style={{ fontSize:13, resize:"none" }}/>
                <span className="absolute bottom-2 right-3 mb-mono" style={{ fontSize:11, color:"var(--faint)" }}>{megj.length} / 200</span>
              </div>
            </Field>
          )}
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

// #endregion

// #region Értesítések (NotifyView)
/* ---- NotifyView (Értesítések) ---------------------------------------- */
/** Értesítések nézet: heti beosztás szerinti és beosztás-változás miatti SMS/email küldés, egyéni üzenet, küldési napló. */
function NotifyView({ setToast }) {
  const [data, setData]       = useState(null);
  const [loading, setLoading] = useState(true);
  const [checks, setChecks]   = useState({});
  const [sending, setSending] = useState(false);
  const [message, setMessage] = useState("");
  const [naplo, setNaplo]     = useState(null);

  const getMondayOfWeek = (offset) => {
    const d = new Date();
    const day = d.getDay() || 7;
    d.setDate(d.getDate() - day + 1 + offset * 7);
    return d.toISOString().slice(0, 10);
  };
  const [weekOffset,    setWeekOffset]    = useState(0);
  const [weekWorkers,   setWeekWorkers]   = useState(null);
  const [weekLoading,   setWeekLoading]   = useState(false);
  const [weekChecks,    setWeekChecks]    = useState({});
  const [weekSending,   setWeekSending]   = useState(false);
  const [weekPickerOpen, setWeekPickerOpen] = useState(false);
  const weekPickerRef = useRef(null);

  useEffect(() => {
    if (!weekPickerOpen) return;
    const h = (e) => { if (weekPickerRef.current && !weekPickerRef.current.contains(e.target)) setWeekPickerOpen(false); };
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, [weekPickerOpen]);

  const monday = getMondayOfWeek(weekOffset);
  const sunday = (() => { const d = new Date(monday); d.setDate(d.getDate()+6); return d.toISOString().slice(0,10); })();
  const fmtDate = (s) => { const [y,m,d]=s.split("-"); return `${d}.${m}.${y.slice(2)}`; };
  const { year: selYear, week: selWeek } = isoWeekFromDate(monday);

  const loadWeekWorkers = useCallback((mon) => {
    setWeekLoading(true);
    fetch(`${HMM_CONFIG.url}&getweekworkers=1&monday=${mon}`).then((r)=>r.json()).then((d)=>{
      setWeekWorkers(d.items||[]);
      const init = {};
      (d.items||[]).forEach((it) => { init[it.id] = { sms: it.smsDefault, email: it.emailDefault }; });
      setWeekChecks(init);
      setWeekLoading(false);
    }).catch(()=>setWeekLoading(false));
  }, []);

  useEffect(() => { loadWeekWorkers(monday); }, [monday]);

  const weekToggle = (id, field) => setWeekChecks((c) => ({ ...c, [id]: { ...c[id], [field]: !c[id]?.[field] } }));
  const weekSetAll = (field, value) => setWeekChecks((c) => {
    const next = { ...c };
    (weekWorkers||[]).forEach((it) => {
      if (value && field==="sms"   && !it.phone) return;
      if (value && field==="email" && !it.email) return;
      next[it.id] = { ...next[it.id], [field]: value };
    });
    return next;
  });

  const sendWeek = async () => {
    setWeekSending(true);
    let sent = 0;
    for (const it of (weekWorkers||[])) {
      const c = weekChecks[it.id] || {};
      if (!c.sms && !c.email) continue;
      try {
        const body = new URLSearchParams({ sendnotify:"1", workerid:it.id, sms:c.sms?"1":"", email:c.email?"1":"" });
        const resp = await fetch(HMM_CONFIG.url, { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:body.toString() });
        const result = await resp.json();
        if (result.status==="ok") sent++;
      } catch(e) { /* continue */ }
    }
    setWeekSending(false);
    setToast(sent>0 ? `${sent} munkatárs értesítve.` : "Nem történt küldés.");
    loadNaplo();
    load();
  };

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

  const [sec1Collapsed, setSec1Collapsed] = useState(false);
  const [sec2Collapsed, setSec2Collapsed] = useState(false);
  const [sec3Collapsed, setSec3Collapsed] = useState(false);

  if (loading || !data) return <LoadingBlock label="Értesítések betöltése…"/>;

  const items = data.items || [];
  const hasSelection = items.some((it) => { const c=checks[it.id]||{}; return c.sms||c.email; });
  const weekHasSelection = (weekWorkers||[]).some((it) => { const c=weekChecks[it.id]||{}; return c.sms||c.email; });

  const NAPLO_ICON = { send:"send", copy:"copy", vacation:"sun" };

  return (
    <div className="mb-scroll px-4 lg:px-6 py-4" style={{ flex:"1 1 auto", minHeight:0, overflowY:"auto" }}>
      <div className="flex flex-col gap-3" style={{ maxWidth:760 }}>

        {/* --- Heti beosztás szerint értesítendők --- */}
        <div className="rounded-xl overflow-hidden" style={{ background:"var(--surface)", border:"1px solid var(--border-soft)" }}>
          <div className="flex items-center justify-between gap-2 px-3 py-2" style={{ borderBottom:sec1Collapsed?"none":"1px solid var(--border-soft)", background:"color-mix(in srgb,var(--brand) 8%,transparent)" }}>
            <button onClick={()=>setSec1Collapsed((v)=>!v)} className="flex items-center gap-2 min-w-0">
              <span style={{ color:"var(--brand)", flexShrink:0 }}>{Ico.calendar({width:15,height:15})}</span>
              <span className="mb-display" style={{ fontSize:13, fontWeight:700 }}>Heti beosztás szerint értesítendők</span>
              {!weekLoading && <span className="rounded-md px-1.5" style={{ fontSize:11, fontWeight:700, color:"var(--muted)", background:"var(--surface-2)", flexShrink:0 }}>{(weekWorkers||[]).length}</span>}
            </button>
            <div className="flex items-center gap-1 relative" ref={weekPickerRef} style={{ flexShrink:0 }}>
              <button onClick={()=>setWeekOffset((o)=>o-1)} className="mb-btn flex h-7 w-7 items-center justify-center rounded-md" style={{ color:"var(--muted)", border:"1px solid var(--border)" }}>{Ico.left()}</button>
              <button onClick={()=>setWeekPickerOpen((v)=>!v)} className="flex items-center gap-1.5 rounded-md px-2 py-1" style={{ border:"1px solid var(--border)", background:"var(--card)", color:"var(--ink)" }}>
                <span style={{ color:"var(--brand)", flexShrink:0 }}>{Ico.calendar({width:13,height:13})}</span>
                <span className="mb-display" style={{ fontSize:12, fontWeight:700 }}>{selYear}. {selWeek}. hét</span>
                <span className="mb-mono" style={{ fontSize:11, color:"var(--faint)" }}>{weekRange(selYear, selWeek)}</span>
              </button>
              <button onClick={()=>setWeekOffset((o)=>o+1)} className="mb-btn flex h-7 w-7 items-center justify-center rounded-md" style={{ color:"var(--muted)", border:"1px solid var(--border)" }}>{Ico.right()}</button>
              {weekPickerOpen && (
                <div className="absolute right-0 top-full mt-1 rounded-xl overflow-hidden" style={{ zIndex:200, background:"var(--surface)", border:"1px solid var(--border)", boxShadow:"0 8px 32px rgba(0,0,0,.22)", minWidth:230 }}>
                  <div className="mb-scroll" style={{ maxHeight:240, overflowY:"auto" }}>
                    {Array.from({length:24}, (_,i) => {
                      const off = weekOffset - 6 + i;
                      const mon = getMondayOfWeek(off);
                      const { year: y, week: w } = isoWeekFromDate(mon);
                      const active = off === weekOffset;
                      return (
                        <button key={off} onClick={()=>{ setWeekOffset(off); setWeekPickerOpen(false); }} className="flex w-full items-center justify-between gap-3 px-3 py-2" style={{ background:active?"var(--brand-soft)":"transparent", color:active?"var(--brand-ink)":"var(--ink)" }}>
                          <span style={{ fontSize:13, fontWeight:700 }}>{y}. {w}. hét</span>
                          <span className="mb-mono" style={{ fontSize:11, color:active?"var(--brand-ink)":"var(--faint)" }}>{weekRange(y, w)}</span>
                        </button>
                      );
                    })}
                  </div>
                </div>
              )}
              <button onClick={()=>setSec1Collapsed((v)=>!v)} style={{ color:"var(--faint)", display:"flex", alignItems:"center", marginLeft:2 }}>{sec1Collapsed?Ico.chevDown({width:16,height:16}):Ico.chevUp({width:16,height:16})}</button>
            </div>
          </div>
          {!sec1Collapsed && (weekLoading ? (
            <div className="px-4 py-4" style={{ fontSize:12.5, color:"var(--faint)" }}>Betöltés…</div>
          ) : (weekWorkers||[]).length === 0 ? (
            <div className="px-4 py-4" style={{ fontSize:12.5, color:"var(--faint)" }}>Erre a hétre nincs beosztva senki.</div>
          ) : (<>
            <div className="flex items-center gap-3 px-3 py-1.5 flex-wrap" style={{ borderBottom:"1px solid var(--border-soft)", fontSize:11.5, fontWeight:600, color:"var(--muted)" }}>
              <span className="flex items-center gap-1.5">SMS: <button onClick={()=>weekSetAll("sms",true)} className="rounded-md px-1.5 py-0.5" style={{ color:"var(--brand-ink)", background:"var(--surface-2)" }}>Mind</button><button onClick={()=>weekSetAll("sms",false)} className="rounded-md px-1.5 py-0.5" style={{ color:"var(--muted)", background:"var(--surface-2)" }}>Egyik se</button></span>
              <span className="flex items-center gap-1.5">Email: <button onClick={()=>weekSetAll("email",true)} className="rounded-md px-1.5 py-0.5" style={{ color:"var(--brand-ink)", background:"var(--surface-2)" }}>Mind</button><button onClick={()=>weekSetAll("email",false)} className="rounded-md px-1.5 py-0.5" style={{ color:"var(--muted)", background:"var(--surface-2)" }}>Egyik se</button></span>
            </div>
            <div className="flex flex-col gap-1.5 p-2">
              {(weekWorkers||[]).map((it) => { const c = weekChecks[it.id]||{}; return (
                <div key={it.id} className="flex items-center justify-between gap-4 rounded-lg flex-wrap" style={{ background:"var(--card)", border:"1px solid var(--border)", padding:"8px 10px" }}>
                  <div style={{ fontSize:13.5, fontWeight:700 }}>{it.name}</div>
                  <div className="flex items-center gap-4 flex-wrap">
                    <Check checked={!!c.sms} onChange={()=>weekToggle(it.id,"sms")} label={it.phone ? `SMS (${it.phone})` : "SMS (nincs megadva)"}/>
                    <Check checked={!!c.email} onChange={()=>weekToggle(it.id,"email")} label={it.email ? `Email (${it.email})` : "Email (nincs megadva)"}/>
                  </div>
                </div>
              ); })}
            </div>
            <div className="px-3 pb-3">
              <button onClick={sendWeek} disabled={!weekHasSelection||weekSending} className="mb-prim flex items-center gap-1.5 rounded-lg px-5 py-2.5" style={{ fontSize:13.5, fontWeight:700, color:"#fff", background:(!weekHasSelection||weekSending)?"var(--faint)":"var(--brand)", cursor:(!weekHasSelection||weekSending)?"not-allowed":"pointer" }}>{weekSending?"Küldés…":<>{Ico.send()} Heti beosztás értesítés kiküldése</>}</button>
            </div>
          </>))}
        </div>

        {/* --- Beosztás-változás miatt értesítendők --- */}
        <div className="rounded-xl overflow-hidden" style={{ background:"var(--surface)", border:"1px solid var(--border-soft)" }}>
          <button onClick={()=>setSec2Collapsed((v)=>!v)} className="flex w-full items-center justify-between gap-2 px-3 py-2.5 flex-wrap" style={{ borderBottom:sec2Collapsed?"none":"1px solid var(--border-soft)", background:"color-mix(in srgb,var(--brand) 8%,transparent)" }}>
            <span className="flex items-center gap-2">
              <span style={{ color:"var(--brand)" }}>{Ico.bell({width:15,height:15})}</span>
              <span className="mb-display" style={{ fontSize:13, fontWeight:700 }}>Beosztás-változás miatt értesítendők</span>
              <span className="rounded-md px-1.5" style={{ fontSize:11, fontWeight:700, color:"var(--muted)", background:"var(--surface-2)" }}>{items.length}</span>
            </span>
            <span style={{ color:"var(--faint)" }}>{sec2Collapsed?Ico.chevDown({width:16,height:16}):Ico.chevUp({width:16,height:16})}</span>
          </button>
          {!sec2Collapsed && (items.length === 0 ? (
            <div className="px-4 py-4" style={{ fontSize:12.5, color:"var(--faint)" }}>Nem történt változás a beosztásban.</div>
          ) : (<>
            <div className="flex items-center gap-3 px-3 py-1.5 flex-wrap" style={{ borderBottom:"1px solid var(--border-soft)", fontSize:11.5, fontWeight:600, color:"var(--muted)" }}>
              <span className="flex items-center gap-1.5">SMS: <button onClick={()=>setAll("sms",true)} className="rounded-md px-1.5 py-0.5" style={{ color:"var(--brand-ink)", background:"var(--surface-2)" }}>Mind</button><button onClick={()=>setAll("sms",false)} className="rounded-md px-1.5 py-0.5" style={{ color:"var(--muted)", background:"var(--surface-2)" }}>Egyik se</button></span>
              <span className="flex items-center gap-1.5">Email: <button onClick={()=>setAll("email",true)} className="rounded-md px-1.5 py-0.5" style={{ color:"var(--brand-ink)", background:"var(--surface-2)" }}>Mind</button><button onClick={()=>setAll("email",false)} className="rounded-md px-1.5 py-0.5" style={{ color:"var(--muted)", background:"var(--surface-2)" }}>Egyik se</button></span>
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
            <div className="px-3 pb-3 flex flex-col gap-2.5">
              <Field label="Egyéni üzenet (opcionális)">
                <textarea value={message} onChange={(e)=>setMessage(e.target.value)} rows={2} placeholder="Írd ide az egyéni üzenetet…" className="mb-in px-3 py-2.5" style={{ fontSize:13.5, resize:"none", fontWeight:500 }}/>
              </Field>
              <div className="flex items-center gap-2 flex-wrap">
                <button onClick={send} disabled={!hasSelection||sending} className="mb-prim flex items-center gap-1.5 rounded-lg px-5 py-2.5" style={{ fontSize:13.5, fontWeight:700, color:"#fff", background:(!hasSelection||sending)?"var(--faint)":"var(--brand)", cursor:(!hasSelection||sending)?"not-allowed":"pointer" }}>{sending?"Küldés…":<>{Ico.send()} Beosztás-értesítés kiküldése</>}</button>
                <button onClick={sendBulk} disabled={!hasSelection||!message.trim()||sending} className="mb-prim flex items-center gap-1.5 rounded-lg px-5 py-2.5" style={{ fontSize:13.5, fontWeight:700, color:"#fff", background:(!hasSelection||!message.trim()||sending)?"var(--faint)":"var(--purple)", cursor:(!hasSelection||!message.trim()||sending)?"not-allowed":"pointer" }}>{sending?"Küldés…":<>{Ico.send()} Egyéni üzenet kiküldése</>}</button>
              </div>
            </div>
          </>))}
        </div>

        {/* --- Napló --- */}
        <div className="rounded-xl overflow-hidden" style={{ background:"var(--surface)", border:"1px solid var(--border-soft)" }}>
          <button onClick={()=>setSec3Collapsed((v)=>!v)} className="flex w-full items-center justify-between gap-2 px-3 py-2.5" style={{ borderBottom:sec3Collapsed?"none":"1px solid var(--border-soft)", background:"color-mix(in srgb,var(--muted) 8%,transparent)" }}>
            <span className="flex items-center gap-2">
              <span style={{ color:"var(--muted)" }}>{Ico.clock({width:15,height:15})}</span>
              <span className="mb-display" style={{ fontSize:13, fontWeight:700 }}>Napló</span>
            </span>
            <span style={{ color:"var(--faint)" }}>{sec3Collapsed?Ico.chevDown({width:16,height:16}):Ico.chevUp({width:16,height:16})}</span>
          </button>
          {!sec3Collapsed && <div className="flex flex-col gap-1.5 p-2">
            {(naplo||[]).map((n,i) => (
              <div key={i} className="flex items-center gap-2.5 rounded-lg" style={{ background:"var(--card)", border:"1px solid var(--border)", padding:"8px 10px" }}>
                <span style={{ color:"var(--faint)", flexShrink:0 }}>{Ico[NAPLO_ICON[n.tipus]||"bell"]({width:14,height:14})}</span>
                <div className="min-w-0 flex-1 truncate" style={{ fontSize:12.5, fontWeight:600 }}>{n.cim}</div>
                <div className="mb-mono flex-shrink-0" style={{ fontSize:11, color:"var(--faint)" }}>{n.letrehozva}</div>
              </div>
            ))}
            {(naplo||[]).length===0 && <div className="px-1 py-2" style={{ fontSize:12, color:"var(--faint)" }}>Még nincs napló-bejegyzés.</div>}
          </div>}
        </div>

      </div>
    </div>
  );
}

// #endregion

// #region Fő komponens (root)
/* ---- Kis komponensek ----------------------------------------------- */
/** Ikon gomb (fejléc/sidebar), opcionális piros számláló jelvénnyel. */
function IconBtn({ children, onClick, badge, title }) {
  return (<button onClick={onClick} title={title} className="mb-btn relative flex h-9 w-9 items-center justify-center rounded-lg" style={{ color:"var(--muted)" }}>{children}{badge>0&&<span className="absolute -top-0.5 -right-0.5 flex items-center justify-center rounded-full" style={{ minWidth:16, height:16, padding:"0 4px", fontSize:10, fontWeight:700, color:"#fff", background:"var(--danger)", border:"2px solid var(--surface)" }}>{badge}</span>}</button>);
}
/* ================================================================
 *  FŐ KOMPONENS
 * ================================================================ */
/**
 * A munkabeosztás React alkalmazás gyökérkomponense: sidebar navigáció, hét-váltás,
 * adatlekérés (getweekdata), mentés/törlés/aktiválás/flag műveletek, hét másolása/törlése,
 * és az aktuális nézet (tábla/lista/ütközések/munkatársak/rendelések/szabadságok/statisztika/értesítések) route-olása.
 */
function MunkaidoBeosztas() {
  const [theme,        setTheme]        = useState("light");
  const [sidebarOpen,  setSidebarOpen]  = useState(true);
  const [weekOffset,   setWeekOffset]   = useState(HMM_CONFIG.offset || 0);
  const [weekData,     setWeekData]     = useState(null);
  const [loading,      setLoading]      = useState(true);
  const [saving,       setSaving]       = useState(false);
  const [doctors,      setDoctors]      = useState([]);   // [{id, nev}]
  const [assistants,   setAssistants]   = useState([]);   // [{id, nev}]
  const [egyebs,       setEgyebs]       = useState([]);   // [{id, nev}]
  const [vehicles,     setVehicles]     = useState([]);   // [{id, nev}]
  const [query,        setQuery]        = useState("");
  const roleFilter = "all";
  const [catFilter,    setCatFilter]    = useState("all");
  const onlyConflicts = false;
  const [showCatMenu,  setShowCatMenu]  = useState(false);
  const [nav,          setNav]          = useState("board");
  const [collapsed,    setCollapsed]    = useState({});
  const [modal,        setModal]        = useState(null);
  const [mapBk,        setMapBk]        = useState(null);
  const [conflictPair, setConflictPair] = useState(null);
  const [copyOpen,     setCopyOpen]     = useState(false);
  const [toast,        setToast]        = useState(null);
  const [staffNewSignal, setStaffNewSignal] = useState(0);
  const [placeNewSignal, setPlaceNewSignal] = useState(0);
  const [vacNewSignal,   setVacNewSignal]   = useState(0);
  const [monthHours,     setMonthHours]     = useState({});
  const [staffSavedSignal, setStaffSavedSignal] = useState(0);

  /* ---- adatlekérés ---- */
  const fetchWeek = useCallback((offset) => {
    setLoading(true);
    fetch(`${HMM_CONFIG.url}&getweekdata=1&offset=${offset}`)
      .then((r) => r.json())
      .then((data) => {
        setWeekData(data);
        setDoctors(data.doctorsWithId    || []);
        setAssistants(data.assistantsWithId || []);
        setEgyebs(data.egyebWithId || []);
        setVehicles(data.vehiclesWithId || []);
        setLoading(false);
      })
      .catch((err) => { console.error("fetchWeek:", err); setLoading(false); });
  }, []);

  useEffect(() => { fetchWeek(weekOffset); }, [weekOffset, fetchWeek]);
  useEffect(() => { if (nav==="board"||nav==="list"||nav==="conflicts") fetchWeek(weekOffset); }, [nav]);

  useEffect(() => {
    const month = new Date().toISOString().slice(0,7);
    fetch(`${HMM_CONFIG.url}&getmonthhours=1&month=${month}`)
      .then((r)=>r.json())
      .then((d)=>{ const map={}; (d.workers||[]).forEach((w)=>{ map[w.id]=w; }); setMonthHours(map); })
      .catch(()=>{});
  }, [weekData, staffSavedSignal]);

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

  const vacPerDay = useMemo(() =>
    (weekData?.days||[]).map((d) => new Set((d.szabadsag||[]).map((v) => v.workerId))),
  [weekData]);

  const elerhetoByDay = useMemo(() =>
    (weekData?.days||[]).map((d) => d.elerheto || []),
  [weekData]);

  const szabadsagByDay = useMemo(() =>
    (weekData?.days||[]).map((d) => d.szabadsag || []),
  [weekData]);

  const lezartByDay = useMemo(() =>
    (weekData?.days||[]).map((d) => ({ lezart: !!d.lezart, megj: d.lezartMegj||"" })),
  [weekData]);

  const toggleLezart = useCallback(async (date) => {
    const body = new URLSearchParams({ toggledaylezart:"1", datum:date });
    await fetch(HMM_CONFIG.url, { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:body.toString() });
    await fetchWeek(weekOffset);
  }, [weekOffset, fetchWeek]);

  const weekWorkerHours = useMemo(() => {
    const map = {};
    (weekData?.days||[]).forEach((day) => {
      (day.bookings||[]).forEach((b) => {
        (b.staff||[]).forEach((s) => {
          if (!s.workerId) return;
          const h = (toMin(s.to) - toMin(s.from)) / 60.0;
          map[s.workerId] = (map[s.workerId] || 0) + (h > 0 ? h : 0);
        });
      });
    });
    return map;
  }, [weekData]);

  const vacationsByDay = useMemo(() => {
    if (!weekData) return EMPTY_BOARD;
    return weekData.days.map((d) => d.szabadsag || []);
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
        const body = new URLSearchParams({ addplace:"1", roleid:"1", kulso: rec.cat==="kulso"?"1":"0", kiszallas: rec.cat==="kiszallas"?"1":"0", org: rec.org||"HMM", megnev: rec.title||"", cim: rec.address||"", megj: rec.note||"", napok: rec.napok??31, validfrom: rec.validfrom||"", validto: rec.validto||"" });
        const resp = await fetch(HMM_CONFIG.url, { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:body.toString() });
        const result = await resp.json();
        if (result.status!=="ok") { setToast("Hiba: "+(result.message||"Ismeretlen hiba")); setSaving(false); return; }
        tipusId = result.id;
      } else if (rec.id) {
        const body = new URLSearchParams({ updateplaceaddress:"1", id:tipusId, cim: rec.address||"", rendelo: rec.rendelo||"", napok: rec.napok ?? 127, org: rec.org||"HMM", ktarto_nev: rec.ktarto_nev||"", ktarto_tel: rec.ktarto_tel||"", ktarto_email: rec.ktarto_email||"" });
        const addrResp = await fetch(HMM_CONFIG.url, { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:body.toString() });
        const addrResult = await addrResp.json();
        if (addrResult.status !== "ok") { setToast("Hiba: " + (addrResult.message||"Ismeretlen hiba")); setSaving(false); return; }
      }
      let allOk = true;
      for (const datum of dates) {
        const body = new URLSearchParams({ savebooking:"1", tipusid:tipusId||"", datum:datum||"", staff:JSON.stringify(staff) });
        const resp = await fetch(HMM_CONFIG.url, { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:body.toString() });
        const result = await resp.json();
        if (result.status!=="ok") { allOk = false; setToast("Hiba: "+(result.message||"Ismeretlen hiba")); break; }
      }
      if (allOk) {
        if (rec.note !== undefined) {
          const noteBody = new URLSearchParams({ savedaynote:"1", tipusid:tipusId||"", datum:dates.join(","), megj:rec.note||"" });
          await fetch(HMM_CONFIG.url, { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:noteBody.toString() });
        }
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

  const toggleAktiv = useCallback(async (b) => {
    const newAktiv = b.aktiv === 0 ? 1 : 0;
    const body = new URLSearchParams({ togglebookingaktiv:"1", tipusid:b.tipusId, datum:b.date, aktiv:newAktiv });
    await fetch(HMM_CONFIG.url, { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:body.toString() });
    await fetchWeek(weekOffset);
  }, [weekOffset, fetchWeek]);

  const toggleFlag = useCallback(async (b) => {
    const body = new URLSearchParams({ togglebookingflag:"1", tipusid:b.tipusId, datum:b.date });
    await fetch(HMM_CONFIG.url, { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:body.toString() });
    await fetchWeek(weekOffset);
  }, [weekOffset, fetchWeek]);

  const dismissConflict = useCallback(async (b, workerId) => {
    const body = new URLSearchParams({ dismissconflict:"1", tipusid:b.tipusId, datum:b.date, workerid:workerId });
    await fetch(HMM_CONFIG.url, { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:body.toString() });
    await fetchWeek(weekOffset);
  }, [weekOffset, fetchWeek]);

  /* ---- hét törlése ---- */
  const clearWeek = useCallback(async () => {
    if (!monday) return;
    if (!window.confirm(`Biztosan törlöd a ${week}. hét teljes beosztását?`)) return;
    if (!window.confirm("Egészen biztos? Ez nem vonható vissza.")) return;
    const body = new URLSearchParams({ clearweek:"1", monday });
    const resp = await fetch(HMM_CONFIG.url, { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:body.toString() });
    const result = await resp.json();
    if (result.status==="ok") { await fetchWeek(weekOffset); setToast("Hét törölve."); }
    else setToast("Hiba: "+(result.message||"Ismeretlen hiba"));
  }, [monday, week, weekOffset, fetchWeek]);

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
    { id:"workplaces",icon:Ico.building, label:"Rendelések" },
    { id:"workers",   icon:Ico.doctor,   label:"Munkatársak" },
    { id:"vacations",  icon:Ico.sun,      label:"Szabadságok" },
    { id:"statistics", icon:Ico.chart,   label:"Statisztika" },
    { id:"copy",       icon:Ico.copy,    label:"Hét másolása" },
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
    <div className={`mb-root mb-${theme}${IS_KELTEX?" mb-keltex":""} min-h-screen w-full`} style={{ height:"100vh", overflow:"hidden" }}>
      <Styles/>
      <div className="flex" style={{ height:"100%" }}>
        {/* SIDEBAR */}
        <aside className="mb-sidebar mb-no-print flex" style={{ width:sidebarOpen?232:0, transition:"width .28s ease", flexShrink:0, overflow:"hidden", borderRight:sidebarOpen?"1px solid var(--border)":"none" }}>
          <div className="flex flex-col" style={{ width:232, height:"100%", background:"var(--sidebar)" }}>
            <div className="px-4 py-3.5 flex flex-col gap-2" style={{ borderBottom:"1px solid var(--border)" }}>
              <div className="flex items-center gap-2.5"><img src={HMM_CONFIG.logo} alt="logo" style={{height:34,width:"auto",flexShrink:0}}/></div>
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
            <div className="md:hidden"><img src={HMM_CONFIG.logo} alt="logo" style={{height:28,width:"auto",flexShrink:0}}/></div>
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
                <a href="index.php?logoutadmin" title="Kijelentkezés" className="mb-btn flex h-9 w-9 items-center justify-center rounded-lg" style={{ color:"var(--muted)" }}>{Ico.logout({width:18,height:18})}</a>
              </div>
            </div>
          </header>

          {/* ESZKÖZTÁR */}
          <div className="flex flex-wrap items-center gap-3 px-4 lg:px-6 py-3" style={{ borderBottom:"1px solid var(--border)", flexShrink:0 }}>
            <h1 className="mb-display" style={{ fontSize:20, fontWeight:700 }}>{nav==="workers"?"Munkatársak":nav==="workplaces"?"Rendelések":nav==="vacations"?"Szabadságok":nav==="notify"?"Értesítések":nav==="statistics"?"Statisztika":"Munkaidő beosztás"}</h1>
            {!["workers","workplaces","vacations","notify","statistics"].includes(nav) && (<>
            <div className="flex items-center gap-2 ml-1">
              <button onClick={()=>setWeekOffset((w)=>w-1)} className="mb-btn flex h-8 w-8 items-center justify-center rounded-lg" style={{ color:"var(--muted)", border:"1px solid var(--border)" }}>{Ico.left()}</button>
              <button onClick={()=>setWeekOffset((w)=>w+1)} className="mb-btn flex h-8 w-8 items-center justify-center rounded-lg" style={{ color:"var(--muted)", border:"1px solid var(--border)" }}>{Ico.right()}</button>
              <span style={{ color:"var(--faint)" }}>{Ico.calendar({width:16,height:16})}</span>
              <span className="mb-display" style={{ fontSize:15, fontWeight:700 }}>{year}. {week}. hét</span>
              <span className="rounded-md px-2 py-0.5" style={{ fontSize:11.5, fontWeight:700, color:"var(--green)", background:"var(--green-soft)" }}>{parity}</span>
            </div>
            <div className="flex items-center gap-2 ml-auto">
              {weekOffset!==0 && <button onClick={()=>setWeekOffset(0)} className="mb-btn rounded-lg px-3 py-2" style={{ fontSize:13, fontWeight:600, color:"var(--brand-ink)", background:"var(--brand-soft)", border:"1px solid var(--border)" }}>Aktuális hét</button>}
              {/* Kategória */}
              <div className="relative">
                <button onClick={()=>setShowCatMenu((v)=>!v)} className="mb-btn flex items-center gap-1.5 rounded-lg px-3 py-2" style={{ fontSize:13, fontWeight:600, color:"var(--muted)", border:"1px solid var(--border)" }}>Kategória: <span style={{ color:"var(--ink)" }}>{catFilter==="all"?"Összes":CATS[catFilter]?.label}</span> {Ico.chevDown({width:14,height:14})}</button>
                {showCatMenu && (<><div className="fixed inset-0 z-40" onClick={()=>setShowCatMenu(false)}/><div className="mb-pop absolute right-0 z-50 mt-1.5 rounded-xl p-1.5" style={{ width:210, background:"var(--surface)", border:"1px solid var(--border)", boxShadow:"0 20px 44px -16px rgba(0,0,0,.5)" }}>{[{v:"all",l:"Összes"},...CAT_ORDER.map((c)=>({v:c,l:CATS[c].label}))].map((o)=>(<button key={o.v} onClick={()=>{ setCatFilter(o.v); setShowCatMenu(false); }} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left" style={{ fontSize:13, fontWeight:catFilter===o.v?700:500, color:catFilter===o.v?"var(--brand-ink)":"var(--ink)", background:catFilter===o.v?"var(--brand-soft)":"transparent" }}>{o.v!=="all"&&<span style={{ width:8, height:8, borderRadius:2, background:CATS[o.v].color }}/>}{o.l}</button>))}</div></>)}
              </div>
              <button onClick={()=>setCopyOpen(true)} className="mb-btn flex h-9 w-9 items-center justify-center rounded-lg" style={{ color:"var(--muted)", border:"1px solid var(--border)" }} title="Hét másolása">{Ico.copy({width:16,height:16})}</button>
              <button onClick={clearWeek} className="mb-btn flex h-9 w-9 items-center justify-center rounded-lg" style={{ color:"var(--danger-ink)", border:"1px solid var(--border)" }} title="Hét törlése">{Ico.trash({width:16,height:16})}</button>
              {nav==="board" && <button onClick={()=>setModal({day:0, cat:"belso", booking:null})} className="mb-prim flex items-center gap-1.5 rounded-lg px-3.5 py-2" style={{ fontSize:13, fontWeight:700, color:"#fff", background:"var(--brand)" }}>{Ico.plus()} Új rendelés</button>}
            </div>
            </>)}
            {nav==="workers"   && <button onClick={()=>setStaffNewSignal((s)=>s+1)} className="mb-prim ml-auto flex items-center gap-1.5 rounded-lg px-3.5 py-2" style={{ fontSize:13, fontWeight:700, color:"#fff", background:"var(--brand)" }}>{Ico.plus()} Új munkatárs</button>}
            {nav==="workplaces"&& <button onClick={()=>setPlaceNewSignal((s)=>s+1)} className="mb-prim ml-auto flex items-center gap-1.5 rounded-lg px-3.5 py-2" style={{ fontSize:13, fontWeight:700, color:"#fff", background:"var(--brand)" }}>{Ico.plus()} Új rendelés</button>}
            {nav==="vacations" && <button onClick={()=>setVacNewSignal((s)=>s+1)}   className="mb-prim ml-auto flex items-center gap-1.5 rounded-lg px-3.5 py-2" style={{ fontSize:13, fontWeight:700, color:"#fff", background:"var(--brand)" }}>{Ico.plus()} Új szabadság</button>}
          </div>

          {/* TÁBLA */}
          <div className="mb-board flex-1 mb-scroll" style={(nav==="board"||nav==="list"||nav==="conflicts") ? { overflow:"auto" } : { display:"flex", flexDirection:"column", overflowX:"hidden", overflowY:"hidden", minHeight:0 }}>
            {loading ? (
              <div className="flex items-center justify-center" style={{ color:"var(--muted)", flex:"1 1 auto", minHeight:0 }}>
                <div className="flex flex-col items-center gap-3">
                  <div className="mb-pulse">{Ico.refresh({width:32,height:32})}</div>
                  <div style={{ fontSize:14, fontWeight:600 }}>Beosztás betöltése…</div>
                </div>
              </div>
            ) : nav==="list" ? (
              <ListView weekDays={weekDays} dayDates={dayDates} conf={conf} matches={matches} collapsed={collapsed} onToggle={(key)=>setCollapsed((p)=>({...p,[key]:!p[key]}))} onOpenCard={(b,di)=>setModal({ day:di, cat:b.cat, booking:b, date:b.date })} onMap={(b)=>setMapBk(b)} onToggleAktiv={toggleAktiv} onDismissConflict={dismissConflict}/>
            ) : nav==="conflicts" ? (
              <ConflictView weekDays={weekDays} conf={conf} catFilter={catFilter} query={query} collapsed={collapsed} onToggle={(key)=>setCollapsed((p)=>({...p,[key]:!p[key]}))} onOpenCard={(b,di)=>setModal({ day:di, cat:b.cat, booking:b, date:b.date })} onOpenPair={(cluster)=>setConflictPair(cluster)} onMap={(b)=>setMapBk(b)}/>
            ) : nav==="workers" ? (
              <StaffView setToast={setToast} newSignal={staffNewSignal} query={query} onStaffSaved={()=>setStaffSavedSignal(s=>s+1)}/>
            ) : nav==="workplaces" ? (
              <PlacesView setToast={setToast} newSignal={placeNewSignal} query={query}/>
            ) : nav==="vacations" ? (
              <VacationsView setToast={setToast} newSignal={vacNewSignal} query={query}/>
            ) : nav==="statistics" ? (
              <StatisticsView setToast={setToast}/>
            ) : nav==="notify" ? (
              <NotifyView setToast={setToast}/>
            ) : (
              <div className="flex items-start gap-3 px-4 lg:px-6 py-4" style={{ minWidth:"min-content", minHeight:"min-content" }}>
                {HU_DAYS.map((day, di) => {
                  const dayConflict = weekDays[di].some((b)=>conf.set.has(`${di}:${b.id}`));
                  const hol  = holidayOf(iso(dayDates[di]));
                  const rest = di===6 || !!hol;
                  const { lezart, megj: lezartMegj } = lezartByDay[di] || {};
                  const dateStr = iso(dayDates[di]);
                  return (
                    <div key={day} className="mb-col flex flex-col rounded-xl" style={{ width:278, flexShrink:0, background:lezart?"color-mix(in srgb,var(--danger) 6%,var(--bg))":(di>=5||hol)?"var(--weekend)":"var(--bg)", border:`1px solid ${lezart?"color-mix(in srgb,var(--danger) 35%,var(--border-soft))":"var(--border-soft)"}` }}>
                      <div className="px-3 py-2.5" style={{ borderBottom:"1px solid var(--border)" }}>
                        <div className="flex items-center justify-between">
                          <div className="flex items-baseline gap-2">
                            <span className="mb-display" style={{ fontSize:13.5, fontWeight:700, letterSpacing:".03em", color:lezart?"var(--danger)":rest?"var(--danger)":"var(--ink)" }}>{HU_DAYS_UP[di]}</span>
                            <span className="flex items-center justify-center rounded-md" style={{ minWidth:18, height:18, padding:"0 4px", fontSize:11, fontWeight:700, color:"var(--muted)", background:"var(--surface-2)" }}>{weekDays[di].length}</span>
                          </div>
                          <div className="flex items-center gap-1.5">
                            {dayConflict && !lezart && <span className="mb-pulse" style={{ color:"var(--danger)" }} title="Ütközés">{Ico.alert({width:14,height:14})}</span>}
                            <span className="mb-mono" style={{ fontSize:11, color:rest||lezart?"var(--danger-ink)":"var(--faint)", textTransform:"uppercase" }}>{HU_MON_SHORT[dayDates[di].getUTCMonth()]} {dayDates[di].getUTCDate()}.</span>
                            <button onClick={()=>toggleLezart(dateStr)} title={lezart?"Leállás feloldása":"Nap lezárása (leállás)"} className="mb-btn flex items-center justify-center rounded-md" style={{ width:22, height:22, color:lezart?"var(--danger-ink)":"var(--faint)", background:lezart?"var(--danger-soft)":"var(--surface-2)", border:lezart?"1px solid color-mix(in srgb,var(--danger) 30%,transparent)":"1px solid var(--border)" }}>{Ico.lock({width:11,height:11})}</button>
                          </div>
                        </div>
                        {lezart && <div className="flex items-center gap-1 mt-1" style={{ fontSize:10.5, fontWeight:700, color:"var(--danger-ink)" }}><span style={{ width:6, height:6, borderRadius:99, background:"var(--danger)", display:"inline-block" }}/> Leállás{lezartMegj ? ` · ${lezartMegj}` : ""}</div>}
                        {!lezart && hol && <div className="flex items-center gap-1 mt-1" style={{ fontSize:10.5, fontWeight:700, color:"var(--danger-ink)" }}><span style={{ width:6, height:6, borderRadius:99, background:"var(--danger)", display:"inline-block" }}/> {hol} · munkaszüneti nap</div>}
                      </div>
                      <div className="mb-colbody flex flex-col gap-2.5 p-2.5" style={{ opacity:lezart?0.45:1, pointerEvents:lezart?"none":"auto" }}>
                        {!conflictView && elerhetoByDay[di] && elerhetoByDay[di].length > 0 && (
                          <div className="rounded-xl overflow-hidden" style={{ background:"var(--surface)", border:"1px solid var(--border-soft)" }}>
                            <div className="flex items-center gap-2 px-3 py-2" style={{ background:"color-mix(in srgb,#2563eb 15%,transparent)", borderBottom:"1px solid var(--border-soft)" }}>
                              <span style={{ color:"#2563eb" }}>{Ico.calendar({width:14,height:14})}</span>
                              <span className="mb-display" style={{ fontSize:12.5, fontWeight:700, letterSpacing:".04em", color:"#2563eb" }}>Elérhető orvosok</span>
                              <span className="rounded-md px-1.5" style={{ fontSize:11, fontWeight:700, color:"var(--muted)", background:"var(--surface-2)" }}>{elerhetoByDay[di].length}</span>
                            </div>
                            <div className="flex flex-col p-2 gap-0.5">
                              {elerhetoByDay[di].map((e, idx) => (
                                <div key={idx} style={{ padding:"2px 4px" }}>
                                  <div style={{ fontSize:12.5, fontWeight:600, color:"var(--ink)" }}>{e.name}</div>
                                  {!!e.megj && <div style={{ fontSize:11.5, color:"var(--muted)", fontStyle:"italic" }}>{e.megj}</div>}
                                </div>
                              ))}
                            </div>
                          </div>
                        )}
                        {CAT_ORDER.filter((c)=>catFilter==="all"||c===catFilter).map((cat) => {
                          const items = weekDays[di].filter((b)=>b.cat===cat && matches(b,di));
                          const key   = `${di}:${cat}`;
                          if (filtering && items.length===0) return null;
                          return (<Group key={cat} cat={cat} di={di} items={items} collapsed={!!collapsed[key]} onToggle={()=>setCollapsed((p)=>({...p,[key]:!p[key]}))} conf={conf} query={query} roleFilter={roleFilter} onOpenCard={(b)=>setModal({ day:di, cat:b.cat, booking:b, date:b.date })} onMap={(b)=>setMapBk(b)} onToggleAktiv={toggleAktiv} onToggleFlag={toggleFlag} onDismissConflict={dismissConflict} weekWorkerHours={weekWorkerHours} monthHours={monthHours}/>);
                        })}
                        {filtering && weekDays[di].filter((b)=>matches(b,di)).length===0 && <div className="text-center py-6" style={{ fontSize:12, color:"var(--faint)" }}>Nincs találat.</div>}
                        {!conflictView && szabadsagByDay[di] && szabadsagByDay[di].length > 0 && (
                          <div className="rounded-xl overflow-hidden" style={{ background:"var(--surface)", border:"1px solid var(--border-soft)" }}>
                            <div className="flex items-center gap-2 px-3 py-2" style={{ background:"color-mix(in srgb,var(--danger) 15%,transparent)", borderBottom:"1px solid var(--border-soft)" }}>
                              <span style={{ color:"var(--danger)" }}>{Ico.sun({width:14,height:14})}</span>
                              <span className="mb-display" style={{ fontSize:12.5, fontWeight:700, letterSpacing:".04em", color:"var(--danger)" }}>Szabadságok</span>
                              <span className="rounded-md px-1.5" style={{ fontSize:11, fontWeight:700, color:"var(--muted)", background:"var(--surface-2)" }}>{szabadsagByDay[di].length}</span>
                            </div>
                            <div className="flex flex-col p-2 gap-0.5">
                              {szabadsagByDay[di].map((v, idx) => (
                                <div key={idx} style={{ padding:"2px 4px" }}>
                                  <div style={{ fontSize:12.5, fontWeight:600, color:"var(--ink)" }}>
                                    {v.name}{v.status === 0 && <span style={{ fontSize:11, fontWeight:500, opacity:.7 }}> (elbírálás alatt)</span>}
                                  </div>
                                  {!!v.megj && <div style={{ fontSize:11.5, color:"var(--muted)", fontStyle:"italic" }}>{v.megj}</div>}
                                </div>
                              ))}
                            </div>
                          </div>
                        )}
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
      {modal && <EditModal ctx={modal} dayDates={dayDates} onClose={()=>setModal(null)} onSave={saveBooking} onDelete={deleteBooking} onMap={(b)=>setMapBk(b)} doctorList={doctors} assistantList={assistants} egyebList={egyebs} vehicleList={vehicles} places={weekData?.places||[]} saving={saving} onToggleAktiv={toggleAktiv} onToggleFlag={toggleFlag} vacPerDay={vacPerDay} monthHours={monthHours} weekWorkerHours={weekWorkerHours}/>}
      {conflictPair && <ConflictPairModal cluster={conflictPair} onClose={()=>setConflictPair(null)} onSave={saveBooking} onDelete={deleteBooking} onMap={(b)=>setMapBk(b)} dayDates={dayDates} doctorList={doctors} assistantList={assistants} egyebList={egyebs} vehicleList={vehicles} places={weekData?.places||[]} saving={saving} onToggleAktiv={toggleAktiv} onToggleFlag={toggleFlag} vacPerDay={vacPerDay} monthHours={monthHours} weekWorkerHours={weekWorkerHours}/>}
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

// #endregion

/* ---- Mountolás ------------------------------------------------------ */
const _root = ReactDOM.createRoot(document.getElementById("hmm-schedule-root"));
_root.render(<MunkaidoBeosztas/>);
