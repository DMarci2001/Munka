<?php

class PdfContent {

    private $pdfList = [
        [
            "title" => "Az alkati ekcéma kezelése, gondozása",
            "short" => "Az atópiás bőrgyulladás kórisméjének (diagnózisának) felállítása bőrgyógyászati/gyermekorvosi feladat. A gyermekekcéma tünetei hullámzó súlyossággal éveken át fennállhatnak, krónikus betegségnek tekintendő. Az ilyen idült, elhúzódó kórképek-állapotok nem oldhatók meg egyszeri kezeléssel, hanem folyamatos orvosi ellenőrzést, gondozást igényelnek...",
            "filename" => "alkati-ekcema-kezelese.pdf"
        ],
        [
            "title" => "Megelőzhető-e az alkati ekcéma?",
            "short" => "Az atópiás alkat ugyan öröklődő tulajdonság, de bizonyos környezeti hatások (úgynevezett provokáló faktorok) lényeges szerepet játszanak az atópiás tünetek kialakulásában, illetve súlyosabbá válásában. Ezek elkerülésével illetve mérséklésével lényegesen csökkenthető a tünetek súlyossága, és elősegíthető az immunrendszeri labilitás helyrebillenése...",
            "filename" => "megelozheto-e.pdf"
        ],
        [
            "title" => "Anyajegyek és bőrdaganatok",
            "short" => "A naevus (anyajegy) szón orvosi értelemben a bőr egyes körülírt
                jóindulatú elváltozásait értjük. A szó eredetileg hibát, foltot jelent, ennek
                megfelelően a bőr szövetétől eltérő szerkezetű körülírt elváltozásokat neveztek
                ilyen összefoglaló néven. Később a szövettani vizsgálatok birtokában a „nem oda
                való” sejtek típusa alapján különböző naevus fajtákat különböztettek meg: naevus
                pigmentosus – festékes anyajegy, naevus teleangiectaticus – éranyajegy, naevus
                epidermalis – hámanyajegy, naevus sebaceus – faggyúmirigy eredetű anyajegy.",
            "filename" => "anyajegyek-bordaganatok.pdf"
        ],
        [
            "title" => "Alkati ekcéma (atópiás dermatitisz) felnőttkorban",
            "short" => "Az utóbbi évtizedekben a fejlett országokban általános tendencia az „ekcémás”
                bőrpanaszokkal jelentkező páciensek számának gyarapodása. Ekcémán az orvosi
                gyakorlatban alkati, irritatív vagy allergiás alapon kialakuló bőrgyulladást értünk, mely
                bőrpírral, viszketéssel, hámlással, a bőr beszűrtségével, heveny esetben akár
                nedvezéssel, idült esetben a bőr megvastagodásával, rugalmatlanná válásával jár.",
            "filename" => "atopias-dermatitisz.pdf"
        ],
        [
            "title" => "Bőrápolás csecsemő- és kisgyermekkorban",
            "short" => "\"Selymes, mint a baba bőre\" - szól a közkedvelt hasonlat. De vajon minek köszönhető ez a különös finomság? A csecsemők, gyermekek bőrének szerkezete eltér a felnőttekétől. A hámréteg vékonyabb, a faggyúmirigyek működése (az első élethetektől eltekintve) minimális. A bőr turgora (vízmegkötő képességen alapuló feszessége) csecsemő és gyermekkorban a legnagyobb. Azonban ugyanazon tényezők, melyek a bababőr különleges finomságáért felelősek, annak fokozott sérülékenységét, érzékenységét is okozzák.",
            "filename" => "csecsemo-borapolas-gyermek-borapolas.pdf"
        ],
        [
            "title" => "Allergiás-e az ekcémás gyermek?",
            "short" => "Mi is az allergia?Az allergiás (túlérzékenységi) reakció védekező-rendszerünk (immunrendszer) kóros, túlzott reakciója a szervezetbe kerülő bizonyos anyagokkal szemben. Az allergiás reakciót kiváltó anyagokat allergénnek hívjuk. Aszerint, hogy az allergiát kiváltó anyag milyen módon jut be szervezetünkbe, beszélünk táplálékkal bejutó (nutritív), bőrön keresztül felszívódó (kontakt) illetve légúti allergénekről. Az egyes bejutási módok egymással kombinálódhatnak is. Az allergiás reakciót kiváltó gyógyszereket, függetlenül a bejutás módjától, külön csoportba szokták sorolni...",
            "filename" => "ekcemas-gyermek.pdf"
        ],
        [
            "title" => "Ekcémás a gyermekem!?",
            "short" => "Az atópiás bőrgyulladás (alkati ekcéma, atópiás dermatitisz, \"gyerekekcéma\") egy gyakori, öröklött hajlamon (ún. atópiás alkat) alapuló bőrbetegség, ami leggyakrabban csecsemő- vagy kisgyermekkorban kezdődik. Száraz bőr, viszketés, hámló, vöröses bőrgyulladás, elhúzódó esetben a bőr megvastagodása és rugalmatlanná válása jellemzi. A tünetek hullámzó lefolyása, tünetes és tünetmentes időszakok váltakozása jellemző...",
            "filename" => "ekcemas-a-gyermekem.pdf"
        ],
        [
            "title" => "Korpázik, hámlik, viszket - a faggyús bőrgyulladásról",
            "short" => "A szeborroás bőrgyulladás a bőrben lévő faggyúmirigyek
                működésével áll összefüggésben. Szó szerint a szeborrea faggyúömlést
                jelent, és arra utal, hogy e kórképben a tünetekért részben a fokozott
                faggyútermelés a felelős. A faggyúmirigyek a bőr hámrétege alatt
                helyezkednek el, kivezető járatuk a szőrtüszőkbe nyílik, az általuk termelt
                faggyú a szőrtüszők nyílásán keresztül jut ki a bőrfelszínre.",
            "filename" => "faggyus-borgyulladas.pdf"
        ],
        [
            "title" => "Mákszemnyi vámpír, avagy a fejtetvességről",
            "short" => "Az emberi megbetegedéseket okozó tetvek apró, élősködő, vérszívó
                rovarok. Emberben háromféle tetű tud megtelepedni: a fej-, a ruha- és a
                lapostetű. Ezek közül az első kettő egy faj két változata, amely külsőleg és
                testfelépítésében majdnem egyforma, és csak előfordulási helyében,
                életmódjában tér el egymástól. Külön fajt csak a lapostetű alkot.",
            "filename" => "fejtetu.pdf"
        ],
        [
            "title" => "Süss fel nap...",
            "short" => "A hosszú, hideg és borongós tél után ki ne várná a langyos tavaszi napsugarakat, és sokan horgásszuk elő tudatalattinkból a régi gyermekdalt. Az alábbiakban áttekinthetjük a napsugárzás szervezetünkre gyakorolt hatásait, hogy áldásos hatásait előnyünkre fordíthassuk, míg káros hatásaitól megvédhessük magunkat.",
            "filename" => "napozas-karos-hatasai.pdf"
        ],
        [
            "title" => "Pattanásosság - esztétikai probléma vagy betegség?",
            "short" => "A pattanásosság az arc, hát, mellkas bőrében lévő faggyúmirigyek gyulladása, mely enyhébb-súlyosabb formában csaknem minden embert érint főként serdülőkorban. A tünetek súlyossága az enyhe mitesszeres panaszoktól a súlyos, mély gennyes gyulladásig terjedhet. Ennek megfelelően a tünetek kezelése a drogériákban kapható \"pattanástalanító\" szerektől, a kozmetikai tisztításon, a gyógyszertári készítményeken át a belső gyógyszeres kezelésig terjed.",
            "filename" => "pattanasossag.pdf"
        ],
        [
            "title" => "Pikkelysömör gyermekkorban",
            "short" => "A pikkelysömör, orvosi nevén psoriasis (ejtsd: pszoriázis) öröklött
                hajlamon alapuló, idült, nem fertőző bőrbetegség.
                A betegség gyakori, 100 emberből átlag 2 szenved pikkelysömörben.
                Bármely életkorban előfordulhat, az esetek fele serdülő illetve fiatal
                felnőttkorban kezdődik.",
            "filename" => "pikkelysomor-gyermekkorban.pdf"
        ],
        [
            "title" => "A pikkelysömörről",
            "short" => "A pikkelysömör, orvosi nevén psoriasis (ejtsd: pszoriázis) öröklött
                hajlamon alapuló, idült, nem fertőző, elsősorban bőrtünetekben
                megnyilvánuló betegség. Nem ritka, száz emberből átlag egy-kettő szenved
                pikkelysömörben. Bármely életkorban előfordulhat, de a páciensek felénél a
                tünetek serdülő illetve fiatal felnőttkorban kezdődnek.",
            "filename" => "pikkelysomor-tajekoztato.pdf"
        ],
        [
            "title" => "Pikkelysömör - az egész szervezet gyulladásos betegsége?!",
            "short" => "A pikkelysömör, orvosi nevén psoriasis (ejtsd: pszoriázis) öröklött
                hajlamon alapuló, idült, nem fertőző, elsősorban bőrtünetekben
                megnyilvánuló betegség. Nem ritka, száz emberből átlag egy-kettő szenved
                pikkelysömörben. Bármely életkorban előfordulhat, de a páciensek felénél a
                tünetek serdülő illetve fiatal felnőttkorban kezdődnek.",
            "filename" => "pikkelysomor.pdf"
        ],
        [
            "title" => "Rozacea",
            "short" => "A rozácea (rosacea) szó szerinti fordításban rózsa virágocskát jelent, a
                magyar népnyelvben borvirágnak is nevezik. Valójában régóta ismert,
                gyakori, néha komoly esztétikai problémát okozó bőrbetegség, melynek
                pontos kóroka máig tisztázatlan. Bár a kórkép végleges gyógyítása még nem
                megoldott, számos módszerrel csökkenthetők a tünetek, illetve lassítható a
                rosszabbodás.",
            "filename" => "rozacea.pdf"
        ],
        [
            "title" => "Egy apró atka, mely nagy viszketést okoz - avagy a rühességről",
            "short" => "A rühességet egy apró parazita atka (néven nevezve Acarus siro
                varius hominis, régi nevén Sarcoptes scabiei) okozza. A kórokozó szabad
                szemmel nem, nagyító illetve mikroszkóp alatt látható. A
                megtermékenyített nőstény atka befúrja magát a bőr legfelső rétegébe, a
                hámrétegbe. Ott azután 1-2 cm-es alagutat fúr magának, és ebbe az
                úgynevezett járatba rakja a petéit.",
            "filename" => "ruhesseg.pdf"
        ],
        [
            "title" => "Korpázik, hámlik, viszket – a faggyús bőrgyulladásról",
            "short" => "A szeborroás bőrgyulladás a bőrben lévő faggyúmirigyek működésével áll
                összefüggésben. Szó szerint a szeborrea faggyúömlést jelent, és arra utal, hogy e
                kórképben a tünetekért részben a fokozott faggyútermelés a felelős. A faggyúmirigyek a
                bőr hámrétege alatt helyezkednek el, kivezető járatuk a szőrtüszőkbe nyílik, az általuk
                termelt faggyú a szőrtüszők nyílásán keresztül jut ki a bőrfelszínre.",
            "filename" => "szeborrea.pdf"
        ],
        [
            "title" => "Vírusos szemölcsök",
            "short" => "A köznyelv szemölcs elnevezéssel illeti a bőrből előemelkedő növedékeket. Orvosi értelemben szemölcsnek a vírus által okozott bőrnövedékeket hívjuk. A vírusos szemölcsök egyik csoportját a HPV (humán papilloma vírus) okozta elváltozások alkotják, a másik csoport a molluscum contagiosum vírusa által okozott fertőző uszodaszemölcs (molluscum contagiosum).",
            "filename" => "virusos-szemolcsok.pdf"
        ],


    ];


}