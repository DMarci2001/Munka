SELECT fogl.datum,fogl.nev,fogl.szuldatum,fogl.taj,h.cim,fogl.eljott FROM foglalasok fogl 
LEFT JOIN helyszinek h ON h.id=fogl.helyszinid
WHERE fogl.cegid=1214 AND fogl.datum BETWEEN "2026-03-31" AND "2026-07-01";


SELECT fogl.datum,fogl.nev,fogl.cegid,fogl.szuldatum,fogl.taj,h.cim,fogl.eljott FROM foglalasok fogl
LEFT JOIN helyszinek h ON h.id=fogl.helyszinid
WHERE fogl.helyszinid IN (1061,1062,1063,1064,1065,1066,1068,1069,1070,1071,1072,1073) AND fogl.cegid=0 AND fogl.datum BETWEEN "2026-03-31" AND "2026-07-01" AND fogl.nev!="nincs név";


