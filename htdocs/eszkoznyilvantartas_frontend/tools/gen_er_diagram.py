# -*- coding: utf-8 -*-
"""
ER-diagram generátor a Hordozható Eszköznyilvántartás sémájához.
Pillow-val rasztert rajzol és .webp-ként menti (v3).
Stílus: drawSQL-szerű, derékszögű (orthogonális) kapcsolatok.
A séma forrása: device-inventory-schema.sql / DATABASE_DOCUMENTATION.md
"""
import os
from PIL import Image, ImageDraw, ImageFont

S = 2  # szuper-mintavételezés a sima szövegért
FONTS = r"C:\Windows\Fonts"

def font(name, size):
    return ImageFont.truetype(os.path.join(FONTS, name), size * S)

f_title = font("segoeuib.ttf", 22)
f_sub   = font("segoeui.ttf", 11)
f_tbl   = font("segoeuib.ttf", 12)
f_col   = font("segoeui.ttf", 10)
f_colb  = font("segoeuib.ttf", 10)
f_small = font("segoeui.ttf", 8)
f_leg   = font("segoeui.ttf", 10)

GROUPS = {
    "external": ((0x8a, 0x8f, 0x98), (0xec, 0xee, 0xf1)),
    "catalog":  ((0x2f, 0x6f, 0xb5), (0xdc, 0xe9, 0xf7)),
    "geo":      ((0x1f, 0x8a, 0x8a), (0xd8, 0xf0, 0xef)),
    "device":   ((0x2e, 0x9a, 0x52), (0xdd, 0xf2, 0xe3)),
    "events":   ((0xc9, 0x7a, 0x1e), (0xfb, 0xec, 0xd6)),
    "resv":     ((0x7a, 0x4f, 0xb5), (0xeb, 0xe2, 0xf7)),
}
INK   = (0x23, 0x29, 0x31)
MUTE  = (0x6b, 0x72, 0x7b)
PKCOL = (0xb1, 0x7d, 0x00)
FKCOL = (0x2f, 0x6f, 0xb5)
LINE  = (0x9a, 0xa3, 0xad)

T = {
 "users": ("external", [
    ("id","PK"),("username","U"),("full_name",""),("auth",""),("title","")], "KÜLSŐ — a webalkalmazásé"),
 "locations": ("external", [("id","PK"),("address","")], "KÜLSŐ — a klinika weboldala"),
 "departments": ("geo", [
    ("id","PK"),("locations_id","FK:locations"),("name",""),("type","raktár|osztály|recepció|műhely")], "ehhez a DB-hez"),
 "device_types": ("catalog", [("id","PK"),("type","U"),("description","")], None),
 "attribute_definitions": ("catalog", [
    ("id","PK"),("device_type_id","FK:device_types"),("attribute_key",""),("label",""),
    ("data_type",""),("is_required",""),("options",""),("sort_order","")], None),
 "devices": ("device", [
    ("device_id","PK"),("asset_tag","U"),("device_type_id","FK:device_types"),
    ("manufacturer",""),("model",""),("serial_number",""),("status",""),("condition",""),
    ("notes",""),("created_at",""),("updated_at",""),("created_by","FK:users"),
    ("updated_by","FK:users"),("retired_date","")], None),
 "device_attribute_values": ("device", [
    ("id","PK"),("device_id","FK:devices"),
    ("attribute_definition_id","FK:attribute_definitions"),("value","")], None),
 "device_custody_events": ("events", [
    ("event_id","PK"),("device_id","FK:devices"),("event_type",""),
    ("actor_user_id","FK:users"),("from_user_id","FK:users"),
    ("from_locations_id","FK:locations"),("from_departments_id","FK:departments"),
    ("to_user_id","FK:users"),("to_locations_id","FK:locations"),
    ("to_departments_id","FK:departments"),("event_timestamp",""),
    ("expected_return_date",""),("condition_at_event",""),("notes",""),
    ("confirmation_status",""),("confirmed_by","FK:users"),("confirmed_at","")], None),
 "device_reservations": ("resv", [
    ("reservation_id","PK"),("device_id","FK:devices U"),("reserved_by","FK:users"),
    ("reserved_at",""),("expires_at",""),("notes","")], None),
}

# ---- elrendezés (drawSQL-szerű): devices középen, custody alatta;
#      users / departments / locations / reservations jobbra; katalógus balra ----
POS = {
 "device_attribute_values":(40,  70),
 "attribute_definitions":  (40,  222),
 "device_types":           (40,  458),
 "devices":                (360, 70),
 "device_custody_events":  (360, 432),
 "users":           (700, 70),
 "departments":            (700, 243),
 "locations":              (700, 395),
 "device_reservations":    (700, 505),
}
CARD_W = 250
HEAD_H = 30
ROW_H  = 21
PAD    = 8

rects = {}
for name,(gx,gy) in POS.items():
    h = HEAD_H + len(T[name][1])*ROW_H + PAD
    rects[name] = (gx, gy, CARD_W, h)

def row_y(name, idx):
    x,y,w,h = rects[name]
    return y + HEAD_H + idx*ROW_H + ROW_H//2

W = max(x+w for (x,y,w,h) in rects.values()) + 40
H = max(y+h for (x,y,w,h) in rects.values()) + 50
W = max(W, 1020)

img = Image.new("RGB", (W*S, H*S), (0xfb,0xfc,0xfd))
d = ImageDraw.Draw(img)
def sc(v): return int(round(v*S))
def R(x,y,w,h): return [sc(x),sc(y),sc(x+w),sc(y+h)]

# ---- kapcsolatok: derékszögű elbow-k, a kártyák ALÁ rajzolva ----
edges = []
for name,(grp,cols,note) in T.items():
    for i,(cn,mark) in enumerate(cols):
        if mark.startswith("FK:"):
            edges.append((name, i, mark[3:].split()[0]))

fan = {}  # célonkénti elcsúsztatás, hogy a vonalak ne csússzanak egymásra
def lh(p1, p2):  # vízszintes/függőleges vonalszakasz
    d.line([sc(p1[0]),sc(p1[1]),sc(p2[0]),sc(p2[1])], fill=LINE, width=max(1,S))

for (src, idx, tgt) in edges:
    sx,sy,sw,sh = rects[src]; tx,ty,tw,th = rects[tgt]
    scx, tcx = sx+sw/2, tx+tw/2
    sY = row_y(src, idx); tY = row_y(tgt, 0)  # cél: PK-sor
    k = fan.get(tgt, 0); fan[tgt] = k+1
    same_col = abs(scx - tcx) < CARD_W*0.6
    if same_col:
        side = "L" if scx < W/2 else "R"
    else:
        side = "R" if tcx > scx else "L"
    if side == "R" and not same_col:
        sX = sx+sw; tX = tx; chX = tx - 18 - k*7
    elif side == "L" and not same_col:
        sX = sx; tX = tx+tw; chX = tx+tw + 18 + k*7
    elif side == "L":  # azonos oszlop, bal oldali csatorna
        sX = sx; tX = tx; chX = min(sx,tx) - 16 - k*7
    else:              # azonos oszlop, jobb oldali csatorna
        sX = sx+sw; tX = tx+tw; chX = max(sx+sw,tx+tw) + 16 + k*7
    # elbow: src él --> csatorna(vízsz) --> függőleges --> cél él(vízsz)
    lh((sX, sY), (chX, sY))
    lh((chX, sY), (chX, tY))
    lh((chX, tY), (tX, tY))
    # apró jelölőpont a cél PK-nál
    d.ellipse([sc(tX)-3, sc(tY)-3, sc(tX)+3, sc(tY)+3], fill=LINE)

# ---- kártyák ----
def rounded(rect, rad, fill=None, outline=None, width=1):
    d.rounded_rectangle(rect, radius=rad, fill=fill, outline=outline, width=width)

for name,(grp,cols,note) in T.items():
    x,y,w,h = rects[name]
    border, head = GROUPS[grp]
    d.rounded_rectangle([sc(x)+3, sc(y)+3, sc(x+w)+3, sc(y+h)+3], radius=sc(8), fill=(0xe9,0xeb,0xee))
    rounded(R(x,y,w,h), sc(8), fill=(255,255,255), outline=border, width=max(1,S))
    d.rounded_rectangle(R(x,y,w,HEAD_H), radius=sc(8), fill=head, outline=border, width=max(1,S))
    d.rectangle([sc(x), sc(y+HEAD_H-8), sc(x+w), sc(y+HEAD_H)], fill=head)
    d.line([sc(x), sc(y+HEAD_H), sc(x+w), sc(y+HEAD_H)], fill=border, width=max(1,S))
    d.text((sc(x+10), sc(y+7)), name, font=f_tbl, fill=INK)
    if note:
        tw_ = d.textlength(note, font=f_small)
        d.text((sc(x+w-10)-tw_, sc(y+10)), note, font=f_small, fill=MUTE)
    for i,(cn,mark) in enumerate(cols):
        ry = y+HEAD_H+i*ROW_H
        if i>0:
            d.line([sc(x+1), sc(ry), sc(x+w-1), sc(ry)], fill=(0xf0,0xf1,0xf3), width=1)
        is_pk = mark=="PK"
        d.text((sc(x+12), sc(ry+5)), cn, font=(f_colb if is_pk else f_col), fill=INK)
        tag=None; tagcol=MUTE
        if is_pk: tag="PK"; tagcol=PKCOL
        elif mark.startswith("FK:"):
            tag = "FK U" if mark.endswith(" U") else "FK"; tagcol=FKCOL
        elif mark=="U": tag="UQ"; tagcol=MUTE
        elif mark: tag=mark; tagcol=MUTE
        if tag:
            tww=d.textlength(tag, font=f_small)
            d.text((sc(x+w-10)-tww, sc(ry+6)), tag, font=f_small, fill=tagcol)

# ---- cím ----
d.text((sc(40), sc(18)), "Hordozható Eszköznyilvántartás — adatbázisséma (v3)", font=f_title, fill=INK)
d.text((sc(42), sc(50)), "Append-only custody-napló az igazságforrás · kétszintű hely (locations + departments) · users + locations külső (klinika)",
       font=f_sub, fill=MUTE)

# ---- jelmagyarázat (bal alsó, üres sávban) ----
lx, ly = 40, rects["device_types"][1] + rects["device_types"][3] + 40
d.text((sc(lx), sc(ly-20)), "Jelmagyarázat", font=f_colb, fill=MUTE)
legend = [("external","users / locations (külső — klinika)"),("geo","departments (ehhez a DB-hez)"),
          ("catalog","device_types / attribute_definitions"),("device","devices / device_attribute_values"),
          ("events","device_custody_events"),("resv","device_reservations")]
for i,(g,lab) in enumerate(legend):
    yy = ly + i*17
    b,hf = GROUPS[g]
    d.rectangle([sc(lx), sc(yy), sc(lx+13), sc(yy+12)], fill=hf, outline=b, width=max(1,S))
    d.text((sc(lx+19), sc(yy)), lab, font=f_leg, fill=INK)
my = ly + len(legend)*17 + 10
for i,(t,c,desc) in enumerate([("PK",PKCOL,"elsődleges kulcs"),("FK",FKCOL,"idegen kulcs"),("UQ",MUTE,"egyedi")]):
    d.text((sc(lx), sc(my+i*14)), t, font=f_small, fill=c)
    d.text((sc(lx+26), sc(my+i*14)), desc, font=f_small, fill=MUTE)

out = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                   "eszkoznyilvantartas_adatbazis_tervezet_v3.webp")
img.save(out, "WEBP", quality=92, method=6)
print("WROTE", out, img.size)
