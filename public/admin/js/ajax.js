$(document).ready(function () {
    $("#loginbox").css("margin-top", $(window).height() / 2 - $("#loginbox").height() / 2);
    //$("#loginbox").css("margin-left",-$("#loginbox").width()/2);
    //$("#loginbox").css("opacity",1);

    setTimeout(function () {
        checkAdminWarnings();
    }, 1000);

    $('.option-box').on('submit',(function(e) {
        $(this).slideToggle();
    }));

    tinymce.init({
        selector: '.mce',
        plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount code',
        toolbar: 'bold italic underline strikethrough | blocks | link image | align | numlist bullist indent outdent',
    });

    self.setInterval("searchTimer()",1000);
    self.setInterval("checkChat()",10000);

    initUploadRoutine();
    initIrszAutoFill();
    initGeneralSearch();
    initDateFilterPicker();
    initQueryDatePicker();
    checkChat();

    if (Notification.permission !== "granted") {
        Notification.requestPermission();
    }
});


function createNotification(title, icon, body, url) {
    var notification = new Notification(title, {
        icon: icon,
        body: body,
    });
    notification.onclick = function() {
        window.open(url);
    };
    return notification;
}

function setHelyszin(h) {
    window.location.href = 'index.php?page=calendar&sethelyszin=' + h;
}

function setHelyszin2(h) {
    window.location.href = 'index.php?page=booking&sethelyszin2=' + h;
}

function setNaptarSzuresTipus(t) {
    window.location.href = 'index.php?page=calendar&setnaptarszurestipus=' + t;
}




function setCegFilter(c, p) {
    window.location.href = 'index.php?setcegfilter=' + c + "&p=" + p;
}

function sF(i) {
    window.location.href = 'index.php?page=bnaptar&idopont=' + encodeURIComponent(i);
}



function toggleEljott(id) {
    $("#eljottcheck" + id).load("index.php?toggleeljott=" + encodeURIComponent(id));
}

function statIdoszakChange(idoszak) {
    window.location.href = "index.php?page=stat&idoszak=" + encodeURIComponent(idoszak);
}


var respo = "";

function startKepImport(id) {
    $("#importstatus").show();
    $("#importstatus").html("Importálás kezdődik ...");

    if (respo != "") $("#importstatus").html("Importálás... még " + respo + " kép van hátra.");

    let request = $.ajax({
        url: "index.php",
        type: "get",
        data: "importoneimage=1&id=" + encodeURIComponent(id)
    });

    request.done(function (response, textStatus, jqXHR) {
        respo = response;
        if (response == "0") {
            window.location.href = "index.php?page=cikkek&szerk=" + id;
        } else {
            a
            startKepImport(id);
        }
    });

}

function changeInterval(beosztasid, interval) {
    let request = $.ajax({
        url: "index.php",
        type: "get",
        data: "page=doctors&changeinterval=" + beosztasid + "&interval=" + interval
    });
}

function showTipusValaszto(beosztasid) {
    if ($.trim($("#tipusvalaszto" + beosztasid).html())) {
        $("#tipusvalaszto" + beosztasid).html("");
        return;
    }

    let request = $.ajax({
        url: "index.php",
        type: "get",
        data: { page: "doctors", showtipusvalaszto: beosztasid }
    });

    request.done(function (response, textStatus, jqXHR) {
        $("#tipusvalaszto"+beosztasid).html(response);
    });
}


var actualprefix = 0

function showftable(prefix) {
    actualprefix = prefix;
    $("#foglalotable").load('index.php?showfoglalotable=' + prefix);
}


var actualtol = "";
var actualig = "";
var lastobj;

function showfoglalas(tol, ig, obj) {
    if (lastobj) lastobj.style.borderColor = "#888";
    if (obj) {
        lastobj = obj;
        obj.style.borderColor = "#000";
    }

    actualtol = tol;
    actualig = ig
    $("#foglalaslista").load('index.php?showfoglalas=' + encodeURIComponent(tol) + '_' + encodeURIComponent(ig));
}

function lefoglal(time) {
    $("#foglalaslista").load('index.php?lefoglal=' + encodeURIComponent(time), null,
        function (responseText) {
            $("#foglalotable").load('index.php?showfoglalotable=' + actualprefix);
        });
}
function lefoglalnap(datum) {
    $("#foglalaslista").load('index.php?lefoglalnap=' + encodeURIComponent(datum), null,
        function (responseText) {
            $("#foglalotable").load('index.php?showfoglalotable=' + actualprefix);
        });
}
function deletefoglalas(id) {
    $("#foglalaslista").load('index.php?deletefoglalas=' + encodeURIComponent(id), null,
        function (responseText) {
            $("#foglalaslista").load('index.php?showfoglalas=' + encodeURIComponent(actualtol) + '_' + encodeURIComponent(actualig));
            $("#foglalotable").load('index.php?showfoglalotable=' + actualprefix);
        });
}

function szerkfoglalas(id) {
    $("#fszerk" + id).load('index.php?szerkfoglalas=' + id);
}

function closefoglalasszerk(id) {
    $("#fszerk" + id).html('');
}

function savefoglalas(id, nap, ora, fo) {
    $("#foglalaslista").load("index.php?savefoglalas=" + encodeURIComponent(id) + "_" + encodeURIComponent(nap) + "_" + encodeURIComponent(ora) + "_" + encodeURIComponent(fo), null,
        function (responseText) {
            $("#foglalaslista").load('index.php?showfoglalas=' + encodeURIComponent(actualtol) + '_' + encodeURIComponent(actualig));
            $("#foglalotable").load('index.php?showfoglalotable=' + actualprefix);
        });
}

function setlastminute(id, val) {
    $("#foglalaslista").load("index.php?setlastminute=" + encodeURIComponent(id) + "_" + encodeURIComponent(val), null,
        function (responseText) {
            $("#foglalaslista").load('index.php?showfoglalas=' + encodeURIComponent(actualtol) + '_' + encodeURIComponent(actualig));
            $("#foglalotable").load('index.php?showfoglalotable=' + actualprefix);
        });
}
function settiltva(id, val) {
    $("#foglalaslista").load("index.php?settiltva=" + encodeURIComponent(id) + "_" + encodeURIComponent(val), null,
        function (responseText) {
            $("#foglalaslista").load('index.php?showfoglalas=' + encodeURIComponent(actualtol) + '_' + encodeURIComponent(actualig));
            $("#foglalotable").load('index.php?showfoglalotable=' + actualprefix);
        });
}
function fizetve(id) {
    $("#foglalaslista").load("index.php?fizetve=" + encodeURIComponent(id), null,
        function (responseText) {
            $("#foglalaslista").load('index.php?showfoglalas=' + encodeURIComponent(actualtol) + '_' + encodeURIComponent(actualig));
            $("#foglalotable").load('index.php?showfoglalotable=' + actualprefix);
        });
}


function rendelesdetail(id) {
    if ($("#rendelesdetail" + id).is(':empty')) {
        $("#rendelesdetail" + id).load("index.php?rendelesdetail=" + encodeURIComponent(id));
    } else {
        $("#rendelesdetail" + id).empty();
    }
}


function validateBeutalo() {
    if ($("#beutalotarget").val() == "0") {
        alert("Nem adta meg hova kéred a beutalót!");
        return false;
    }

    if ($("#beutalomegj").val() == "") {
        if (!confirm("Nem adott meg megjegyzést a beutalóhoz, folytatja?")) return false;
    }

    if ($("#beutalonaploszam").val() == "") {
        if (!confirm("Biztos naplószám nélkül adja meg a beutalót?")) return false;
    }

    return true;
}



var refreshTime = 60000 * 5;
window.setInterval(function () {
    $.ajax({
        cache: false,
        type: "GET",
        url: "/admin/refreshsession.php",
        success: function (data) {
        }
    });
}, refreshTime);



function cssClick(tip, sor) {
    if (tip == 1) {
        if ($("#csaksorban" + sor).is(":checked")) $("#csakvsorban" + sor).prop("checked", false);
    } else {
        if ($("#csakvsorban" + sor).is(":checked")) $("#csaksorban" + sor).prop("checked", false);
    }
}


function orvosDataVerify() {
    var formid = $("#iform");
    $("#errorlistdiv").hide();

    $.ajax({
        type: "POST",
        url: "index.php",
        data: "orvosdataverify=1&" + $(formid).serialize(),
        success: function (data) {
            if (data == "ok") {
                formid.submit();
            } else {
                $("#errorlistdiv").html(data);
                $("#errorlistdiv").slideDown();
            }
        }
    });

    return false;
}




function userDataVerify() {
    var formid = $("#iform");
    $("#errorlistdiv").hide();

    $.ajax({
        type: "POST",
        url: "index.php",
        data: "userdataverify=1&" + $(formid).serialize(),
        success: function (data) {
            if (data == "ok") {
                formid.submit();
            } else {
                $("#errorlistdiv").html(data);
                $("#errorlistdiv").slideDown();
            }
        }
    });

    return false;
}

function add2SztCegek(cegid, sor) {
    var arid = $("#arid" + sor).val();

    $.ajax({
        type: "POST",
        url: "index.php",
        data: "add2sztceg=1&arid=" + arid + "&cegid=" + cegid + "&sor=" + sor,
        success: function (data) {
            $("#ceglist" + sor).html(data);
            $("#cegadd" + sor).slideToggle();
        }
    });


}

function removeSztCegek(cegid, sor) {
    var arid = $("#arid" + sor).val();

    $.ajax({
        type: "POST",
        url: "index.php",
        data: "removesztceg=1&arid=" + arid + "&cegid=" + cegid + "&sor=" + sor,
        success: function (data) {
            $("#ceglist" + sor).html(data);
        }
    });


}




function showFizSzolg(id) {
    $("#fizszolglist" + id).load("index.php?page=arrivals&showfizszolglist&fid=" + id);
}


function addFizSzolg(fid, aid) {
    $.ajax({
        type: "POST",
        url: "index.php",
        data: "page=arrivals&addfizszolg=1&fid=" + fid + "&aid=" + aid,
        success: function (data) {
            $("#fizszolglist" + fid).html(data);
        }
    });
}


function removeFizSzolg(fid, id) {
    if (!confirm("Biztos törli ezt a szolgáltatást?")) return;

    $.ajax({
        type: "POST",
        url: "index.php",
        data: "page=arrivals&removefizszolg=1&fid=" + fid + "&id=" + id,
        success: function (data) {
            $("#fizszolglist" + fid).html(data);
        }
    });
}



function setListDay(day) {
    //$("#querystatus").html("lekérdezés folyamatban...");

    $("#napfilter").css("background-image","url('/images/loading_transparent.svg')");
    $("#elojegyzestable").load("index.php?page=booking&showelojegyzestable&day="+encodeURIComponent(day),null,
        function(responseText){
            afterElojegyzesTableInit();
            $("#napfilter").css("background-image","url('/images/empty-128.png')");
            reloadWaitList();
        }
    );
}

function setQueryDay(day) {
    //$("#querystatus").html("lekérdezés folyamatban...");

    $("#start-query-date").css("background-image", "url('/images/loading_transparent.svg')");
    $("#end-query-date").css("background-image", "url('/images/loading_transparent.svg')");
}

var foglalasSelected = 0;
var foglalasSelectedPass = "";
var foglalasDisplayed = 0;
var cpy = 0;
var selectedInterval = 0;
var selectedOrvos = 0;

function setSelectedInterval(i) {
    selectedInterval = i;
}

function setSelectedOrvos(oId) {
    selectedOrvos = oId;
}

function addIdopont(idopont, szt, el) {
    $(".eloj_dialog").hide();

    if (szt.indexOf(',') > -1) {
        $.ajax({
            url: 'index.php',
            type: 'GET',
            data: { page: 'booking', addidoponttipusdialog: 1, tipusok: szt, idopont: idopont },
            success: function (data) {
                let position = $(el).offset();
                let left = position.left + 15;

                $(".eloj_dialogcontent").html(data);
                $(".eloj_dialogtop").html(idopont.substring(11) + " - válassz szolgáltatást");
                $(".eloj_dialog").show();

                let width = $(".eloj_dialog").width();
                let winWidth = $(window).width();
                if (left + width > winWidth) {
                    left = winWidth - width;
                }

                $(".eloj_dialog").css("top", position.top);
                $(".eloj_dialog").css("left", left+5);
            }
        });

        return;
    }

    if (foglalasSelected != 0) {
        let msg = "Biztos áthelyezed ide a kijelölt foglalást?";
        if (cpy == 1) {
            msg = "Biztos átmásolod ide a kijelölt foglalást?";
        }

        if (confirm(msg)) {
            $.ajax({
                url: 'index.php',
                type: 'GET',
                data: { page: 'booking', cpy: cpy, szt: szt, moveidopont: idopont, fid: foglalasSelected, rinterval: selectedInterval, orvosid: selectedOrvos },
                success: function (data) {
                    if (data.substring(0, 5) == "error") {
                        alert(data.substring(5));
                    } else {
                        $("#elojegyzestable").html(data);
                        if (cpy == 0) {
                            showIdopontEditor('booking', foglalasSelectedPass, foglalasSelected);
                            cancelFoglalasMove();
                        }
                    }
                }
            });
        }
        return;
    }

    let pos = $(el).offset();
    $("#elojloader").show();
    $("#elojloader").css("top", pos.top-2);
    $("#elojloader").css("left", 182);

    $.ajax({
        url: 'index.php',
        type: 'GET',
        data: { page: 'booking', szt: szt, addidopont: idopont, rinterval: selectedInterval, orvosid: selectedOrvos },
        success: function (data) {
            $("#elojloader").hide();
            if (data.substring(0, 5) == 'error') {
                alert(data.substring(5));
            } else {
                $("#elojegyzestable").html(data);
                afterElojegyzesTableInit();
            }
        }
    });
}

function afterElojegyzesTableInit() {
    initDateFilterPicker();
    initIrszAutoFill();
    initTabOrder();
    initDateInput();
}

function refreshNaptar(idopont) {
    $.ajax({
        url: 'index.php',
        type: 'GET',
        data: { page: 'calendar', loadnaptar: '1' },
        success: function (data) {
            $("#foglalasnaptar").html(data);
        }
    });
}



function addIdopontNaptar(idopont, szt) {
    $("#naptarloading").show();

    if (foglalasSelected != 0) {
        if (confirm("Biztos áthelyezed ide a kijelölt foglalást: " + idopont + "?")) {

            $("#foglalasnaptaridopont").load("index.php?page=calendar&szt=" + encodeURIComponent(szt) + "&moveidopont=" + encodeURIComponent(idopont) + "&fid=" + encodeURIComponent(foglalasSelected), null,
                function (responseText) {
                    showIdopontEditor('bnaptar', foglalasSelectedPass, foglalasSelected);
                    cancelFoglalasMove();
                    refreshNaptar(idopont);
                    $("#naptarloading").hide();
                }
            );
        }
        return;
    }

    $.ajax({
        url: 'index.php',
        type: 'GET',
        data: { page: 'calendar', szt: szt, addidopont: idopont, rinterval: selectedInterval },
        success: function (data) {
            if (data.substring(0, 5) == 'error') {
                alert(data.substring(5));
            } else {
                $("#foglalasnaptar").html(data);
                //refreshNaptar(idopont);
            }
            $("#naptarloading").hide();
        }
    });
}



function removeIdopont(id, p, page, el) {
    if (!confirm("Biztos törlöd ezt az időpontot?")) {
        return;
    }

    if (el != 0) {
        let pos = $(el).offset();
        $("#elojloader").show();
        $("#elojloader").css("top", pos.top - 2);
        $("#elojloader").css("left", 182);
    }

    $.ajax({
        url: 'index.php',
        type: 'GET',
        data: { page: page, removeidopont: id, p: p },
        success: function (data) {
            $("#elojloader").hide();
            cancelFoglalasMove();
            $("#idoponteditor").slideUp();
            if (page == "booking") {
                $("#elojegyzestable").html(data);
                afterElojegyzesTableInit();
            }
            if (page == "calendar") {
                $("#foglalasnaptar").html(data);
            }
        }
    });

}

function addReplaceDoctor(nap, helyszin, beoid, sourceoid) {
    let helyettesitoorvosid = $("#helyettesitoorvosid"+sourceoid).val();
    let orvosMegj = $("#orvosmegj"+sourceoid).val();

    $.ajax({
        url:'index.php',
        type:'POST',
        data:{page:"booking", addreplacedoctor:1, helyszin:helyszin, nap:nap, beoid:beoid, sourceoid:sourceoid, helyettesitoorvosid:helyettesitoorvosid, orvosMegj:orvosMegj},
        success:function(data) {
            if (data.error != "") {
                alert(data.error);
                return;
            }
            successToast("Helyettesítő hozzáadva");
            cancelFoglalasMove();
            $("#idoponteditor").slideUp();
            $("#elojegyzestable").html(data.html);
            afterElojegyzesTableInit();
        }
    });
}

function removeReplaceDoctor(nap, oid) {
    $.ajax({
        url:'index.php',
        type:'GET',
        data:{page:"booking", removereplacedoctor:1, nap:nap, oid:oid},
        success:function(data) {
            if (data.error != "") {
                alert(data.error);
                return;
            }
            successToast("Helyettesítő eltávolítva");
            cancelFoglalasMove();
            $("#idoponteditor").slideUp();
            $("#elojegyzestable").html(data.html);
            afterElojegyzesTableInit();
        }
    });
}

function addTempDoctor(nap, helyszin, szt, sourceoid) {
    let orvosNev = $("#orvosnev" + sourceoid).val();
    let orvosMegj = $("#orvosmegj" + sourceoid).val();
    let orvosTol = $("#orvostol" + sourceoid).val();
    let orvosIg = $("#orvosig" + sourceoid).val();
    let orvosInterval = $("#orvosinterval" + sourceoid).val();

    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: { page: "booking", addtempdoctor: 1, helyszin: helyszin, nap: nap, szt: szt, sourceoid: sourceoid, orvosNev: orvosNev, orvosMegj: orvosMegj, orvosTol: orvosTol, orvosIg: orvosIg, orvosInterval: orvosInterval },
        success: function (data) {
            if (data.error != "") {
                alert(data.error);
                return;
            }
            cancelFoglalasMove();
            $("#idoponteditor").slideUp();
            $("#elojegyzestable").html(data.html);
            afterElojegyzesTableInit();
            scrollTo("orvosdiv"+data.newOrvosId);
        }
    });
}

function saveTempDoctor(oid) {
    let orvosNev = $("#editorvosnev" + oid).val();
    let orvosMegj = $("#editorvosmegj" + oid).val();
    let orvosTol = $("#editorvostol" + oid).val();
    let orvosIg = $("#editorvosig" + oid).val();

    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: { page: "booking", savetempdoctor: 1, oid: oid, orvosNev: orvosNev, orvosMegj: orvosMegj, orvosTol: orvosTol, orvosIg: orvosIg },
        success: function (data) {
            if (data.error != "") {
                alert(data.error);
                return;
            }
            cancelFoglalasMove();
            $("#idoponteditor").slideUp();
            $("#elojegyzestable").html(data.html);
            afterElojegyzesTableInit();
        }
    });
}

function removeTempDoctor(nap, oid) {
    $.ajax({
        url: 'index.php',
        type: 'GET',
        data: { page: "booking", removetempdoctor: 1, nap: nap, oid: oid },
        success: function (data) {
            if (data.error != "") {
                alert(data.error);
                return;
            }
            cancelFoglalasMove();
            $("#idoponteditor").slideUp();
            $("#elojegyzestable").html(data.html);
            afterElojegyzesTableInit();
        }
    });
}

function showIdopontEditor(page, p, id) { 
    cancelFoglalasMove();
    $("#naptarloading").show();

    $.ajax({
        url: 'index.php',
        type: 'GET',
        data: { page: page, showidoponteditor: id, p: p },
        success: function (data) {
            $("#idoponteditor").html(data);
            foglalasDisplayed = id;
            $("#idoponteditor").slideDown();
            $("#naptarloading").hide();
            initIrszAutoFill();
            initTabOrder();
            initTajEditor();
            initDateInput();
        }
    });
}

var lastTaj = "";
function initTajEditor() {
    /*
    $(".editortaj2").keydown(function () {
       lastTaj = $(this).val();
    });
    $(".editortaj2").keyup(function () {
        let taj = $(this).val();
        if (taj.length >= 9 && taj != lastTaj) {
            autoFill(true);
        }
    });
    */
}

function startFoglalasMove(id, p) {
    cpy = 0;
    foglalasSelected = id;
    foglalasSelectedPass = p;
    $("#timeedit").slideUp();
    $("#copyinfo").slideUp();
    $("#moveinfo").slideDown();
}

function startFoglalasCopy(id, p) {
    cpy = 1;
    foglalasSelected = id;
    foglalasSelectedPass = p;
    $("#timeedit").slideUp();
    $("#moveinfo").slideUp();
    $("#copyinfo").slideDown();
}

function startTimeEditor(id, p) {
    //cpy=1;
    foglalasSelected = id;
    foglalasSelectedPass = p;
    $("#copyinfo").slideUp();
    $("#moveinfo").slideUp();
    $("#timeedit").slideDown();
}

function duplicateReservation(id, p) {
    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: { duplicatereservation:1, id:id, p:p },
        success: function (data) {
            alert(data);
        }
    });
}

function autoFill(silent) {
    let taj = $("#editortaj").val().trim();
    let fid = $("#reservationId").val();
    let pid = $("#paciensId").val();

    if (taj == "" && !silent) {
        alert("Add meg a TAJ számot!");
        return;
    }

    if ((taj.length < 9 || taj.length > 9) && !silent) {
        alert("A megadott TAJ szám formátuma nem megfelelő!");
        return;
    }

    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: { AFForm: taj, fid:fid, pid:pid },
        success: function (data) {
            if (data.error != "") {
                if (!silent) {
                    alert(data.error);
                }
            } else {
                $('input[name="paciensid"]').val(data.id);
                //$('input[name="taj"]').val(data.taj);
                $('input[name="email"]').val(data.email);
                $('input[name="nev"]').val(data.nev);
                $('input[name="telefon"]').val(data.telefon);
                $('input[name="munkakor"]').val(data.munkakor);
                $('input[name="irsz"]').val(data.irsz);
                $('input[name="varos"]').val(data.varos);
                $('input[name="utca"]').val(data.utca);
                $('#cegid').val(data.cegid);
                $('#cegid').trigger('change');
                $('input[name="szulhely"]').val(data.szulhely);
                $('input[name="anyjaneve"]').val(data.anyjaneve);
                $('input[name="torzsszam"]').val(data.torzsszam);
                $('input[name="szuldatum"]').val(data.szuldatum);
            }
        }
    });
}

function cancelFoglalasMove() {
    foglalasSelected = 0;
    $("#moveinfo").slideUp();
    $("#copyinfo").slideUp();
    $("#timeedit").slideUp();
}

function foReservationInfo(id, p) {
    $.ajax({
        type: 'post',
        url: 'index.php',
        data: { foReservationInfo: 1, page: "booking", fid: id, p: p },
        success: function (data) {
            alert(data.result);
        }
    });
}

function saveTimeEdit() {
    let page = $("#currentPage").val();
    let id = $("#reservationId").val();
    let p = $("#reservationToken").val();
    let modTime = $("#modtime").val();
    let modInterval = $("#modinterval").val();

    $.ajax({
        type: "POST",
        url: "index.php",
        data: { savetimemod: 1, page: page, fid: id, p: p, modTime:modTime, modInterval:modInterval },
        success: function (response) {
            if (response.status != "") {
                alert(response.status);
            }
            //$("#idoponteditor").html(response.html);
            //$("#elojegyzestable").load("index.php?page=booking&showelojegyzestable", null,
            //    function(responseText){
            //        afterElojegyzesTableInit();
            //    }
            //);
        }
    });
}


function foglalasMentes(page, allowNewCompany) {
    var data = $("#iform").serialize() + "&page=" + page + "&foglalasmentesnaptar2=1";

    $("#naptarloading").show();

    let cegId = $("#cegid").val();
    let allowNewCompany2 = $("#allowNewCompany").val();
    let mustChooseCompany = $("#mustChooseCompany").val();
    if (isNaN(cegId)) {
        if (allowNewCompany == 0) {
            alert("Új cég bevitele nem engedélyezett, válassz a listából!");
            return;
        }

        if (!confirm("Új céget készülsz létrehozni ("+cegId+"), biztos vagy benne?")) {
            return;
        }
    }

    if (mustChooseCompany == 1 && cegId == 0) {
        alert("Céget választani kötelező!");
        return;
    }
    
    $.ajax({
        type: "POST",
        url: "index.php",
        data: data,
        success: function (response) {
            if (response.status != "") {
                alert(response.status);
            }

            if(response.updatedokirexjson){
                if(confirm("Szeretnéd menteni a Bejelentkező cég-dokirex cég kapcsolatot?")){
                    setCegBubble(cegId,$("select[name='dokirexcegid']").val(),false);
                }
            }
            
            $("#idoponteditor").html(response.html);
            $("#elojegyzestable").load("index.php?page=booking&showelojegyzestable", null,
                function(responseText){
                    afterElojegyzesTableInit();

                    if (response.sync != 0) {
                        $.ajax({
                            type: "POST",
                            url: "index.php?page=booking",
                            data: "syncreservation="+response.sync
                        });
                    }

                }
            );
        }
    });

}

function foglalasOrvosErtesites() {
    var data = $("#iform").serialize() + "&foglalasmentesnaptaresertesites2=1";

    $.ajax({
        type: "POST",
        url: "index.php?page=booking",
        data: data,
        success: function (response) {
            $("#idoponteditor").html(response.html);
            alert("Értesítés elküldve!");
        }
    });
}


var lastCell = "";
var zindex = 10000;

function setIdoPontCell(i) {
    var id = i.replace(" ", "");
    id = id.replace("-", "");
    id = id.replace("-", "");
    id = id.replace(":", "");

    //$("#ipbox"+id).css("background","#81d6e6");


    $("#ipbox" + id).css("transform", "scale(1.1)");
    $("#ipbox" + id).css("z-index", zindex);
    $("#ipbox" + id).css("box-shadow", "0px 0px 5px #444");

    if (id == lastCell) return;

    zindex++;
    if (lastCell != "") {
        $("#ipbox" + lastCell).css("transform", "scale(1)");
        $("#ipbox" + lastCell).css("box-shadow", "");
    }
    lastCell = id;
}


function sF2(i) {
    setIdoPontCell(i);

    $("#foglalasnaptaridopont").load("index.php?shownaptaridopont=" + encodeURIComponent(i), null,
        function (responseText) {
        }
    );
}

function naptarMove(d) {
    $("#naptarloading").show();
    $("#foglalasnaptar").load("index.php?page=calendar&loadnaptar&shift=" + encodeURIComponent(d), null,
        function (responseText) {
            $("#foglalasnaptaridopont").html("");
            $("#naptarloading").hide();
        }
    );
}

function addSMSPhone(oid) {
    $("#smsalertsettings").load("index.php?page=doctors&addsmsphone&oid=" + oid);
}

function deleteSMSPhone(oid, id) {
    $("#smsalertsettings").load("index.php?page=doctors&deletesmsphone&oid=" + oid + "&id=" + id);
}

function showCegValaszto(phoneid) {
    if ($.trim($("#cegvalaszto" + phoneid).html())) {
        $("#cegvalaszto" + phoneid).html("");
        return;
    }
    $("#cegvalaszto" + phoneid).load("index.php?page=doctors&showcegvalaszto=" + phoneid);
}

function saveCegList(phoneid) {
    var tk = "";
    var bszoveg = "Összes cég";
    var num = 0;
    var t = "nincs tipus hozzárendelve";
    var tlist = "";

    $("#cegvalaszto" + phoneid + " input:checked").each(function () {
        tk = tk + "|" + $(this).attr("name").replace("cegvalaszto" + phoneid + "_", "") + "|";
        num++;
        tlist = tlist + ", " + $(this).attr("value");
    });

    if (num > 0) {
        t = tlist.substring(2);
        bszoveg = num + " cég";
    }

    $("#cegstatus" + phoneid).html("<a href='#' class='tlink' title='" + t + "' onclick='showCegValaszto(" + phoneid + ");return false;'>" + bszoveg + "</a>");

    request = $.ajax({
        url: "index.php",
        type: "get",
        data: "page=doctors&savesmsphonetipusok=" + phoneid + "&value=" + encodeURIComponent(tk)
    });

    request.done(function (response, textStatus, jqXHR) {
        respo = response;
    });
}


function lEditorOpen(id) {
    $("#lszoveg" + id).hide();
    $("#leditor" + id).show();
}
function lEditorClose(id) {
    $("#lszoveg" + id).show();
    $("#leditor" + id).hide();
}
function lEditorSave(id) {
    lEditorClose(id);
    var e = $("#langtext" + id).val();

    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: "page=langsettings&savelangvalue=" + encodeURIComponent(e) + "&id=" + id,
        success: function (data) {
            $("#llink" + id).html(data);
        }
    });
}



//marci

var op_val = 0;
$(document).on('click', '.protocol-list-frame', function () {
    if (op_val == 0) {
        $('.protocol-list-main-center').css('display', 'inline-block');
        $('.protocol-list-wrapper-01').animate({ left: '-420px' });
        $('.protocol-list-main-center').animate({ width: '400px' });
        op_val++;
        return;
    }
    if (op_val == 1) {
        $('.protocol-list-wrapper-01').animate({ left: '-20px' });
        $('.protocol-list-main-center').animate({ width: '0px' }, 400, function () {
            $('.protocol-list-main-center').css('display', 'none');
        });
        op_val--;
        return;
    }

});

$(document).on('click', '.protocol-obj', function (e) {
    var obj = $(e.target).closest('.protocol-obj').find('.checkDiv');
    var subject = $(e.target).closest('.protocol-obj').find('.checkDiv > svg');
    var string = $(e.target).closest('.protocol-obj').attr('title');
    var curStr = $('#protocol-textarea');
    var curVal = $('#protocol-textarea').val();
    var pipe = '<i class="fa fa-check"></i>';
    if (subject.length) {
        obj.empty();
        var position = curVal.search(string);
        if (curVal != '') {
            if (position == 0) modStr = curVal.replace(string, '');
            else modStr = curVal.replace(', ' + string, '');
        }
        else modStr = curVal.replace(string, '');
        curStr.val(modStr);
    }
    else {
        obj.html(pipe);
        if (curVal != '') curStr.val(curVal + ', ' + string);
        else curStr.val(string);
    }
});

function listCheck() {
    var protocolArr = new Array();
    $('.checkDiv').each(function (i, obj) {
        var strID = $(obj).closest('.protocol-obj').attr('id').split('-');
        var protocol = strID[1];
        if ($(obj).find('svg').length) protocolArr.push(protocol);
    });
    return protocolArr;
}

function setProtocol(val) {
    var protocolArr = new Array();
    protocolArr = listCheck();

    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: { newProtocol: val },
        success: function (data) {
            if (data == 'Successful added!') {
                $('.protocol-list-wrapper').load('index.php', { refreshProtocolList: protocolArr });
                $('.successful-message').css('display', 'block');
                $('.successful-message').find('span').text('Protocoll hozzáadva!');
                setTimeout(function () {
                    $('.successful-message').css({ opacity: 1.0, visibility: 'visible' }).animate({ opacity: 0 }, 1500, function () {
                        $('.successful-message').css('display', 'none');
                    });
                }, 1500);
            }
        }
    });
}

function saveProtocol(cid) {
    var protocolArr = new Array();
    protocolArr = listCheck();
    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: {
            saveProtocol: protocolArr,
            cid: cid
        },
        success: function (data) {
            if (data == '') {
                $('.successful-message').css('display', 'block');
                $('.successful-message').find('span').text('Lista elmentve!');
                setTimeout(function () {
                    $('.successful-message').css({ opacity: 1.0, visibility: 'visible' }).animate({ opacity: 0 }, 1500, function () {
                        $('.successful-message').css('display', 'none');
                    });
                }, 1500);
            }
        }
    });
}

$(document).on('click','input[name="uj_lelet"]',function(){
    $('#leletform').load('index.php?uj_lelet');
    $('#leletform').slideToggle();
    $(this).css('display', 'none');
});

function printLelet() {
    var objToPrint = document.getElementById('lelet-content');

    var newWin = window.open('', 'Print-Window');

    newWin.document.open();

    newWin.document.write('<html><body style = "page-break-after: always;" onload="window.print()">' + objToPrint.innerHTML + '</body></html>');

    newWin.document.close();

    setTimeout(function () { newWin.close(); }, 10);
}

function open_lelet(id) {
    $('#uj-lelet').remove();
    if ($('#leletform').css('display') == 'none') {
        tinymce.EditorManager.execCommand('mceAddEditor', true, 'lelet-page-' + id);
        $('#leletform').load('index.php?open_lelet=' + id);

        setTimeout(function () {
            $('#leletform').slideToggle();
            $('#add-lelet').css('display', 'none');
        }, 1200);
    }
}
function open_zaro(id) {
    $('#uj-lelet').remove();
    if ($('#zaroform').css('display') == 'none') {
        tinymce.EditorManager.execCommand('mceAddEditor', true, 'zaro-page-' + id);
        $('#zaroform').load('index.php?zaro_lelet=' + id);

        setTimeout(function () {
            $('#zaroform').slideToggle();
            $('#add-lelet').css('display', 'none');
        }, 1200);
    }
}

$(document).on('click', 'input[name="close_lelet"]', function () {
    //console.log('haliii');
    $('#leletform').slideToggle(function () {
        $('#leletform').empty();
        $('#leletbutton').find('input[type="button"]').css('display', 'block');
    });

});
$(document).on('click', 'input[name="close_zaro"]', function () {
    //console.log('haliii');
    $('#zaroform').slideToggle(function () {
        $('#zaroform').empty();
        $('#leletbutton').find('input[type="button"]').css('display', 'block');
    });

});
function add_lelet(id, textarea) {

    if (id == 'empty') return;
    var data = 'request_lelet=' + id;
    var footage = $('.medic-footage').text().replace(/\"/g, '');
    var seal_number = $('#pecsetszam').val();
    if (footage.includes('(')) {
        var fracted_footage = footage.split('(');
        footage = fracted_footage[0] + '(' + seal_number + fracted_footage[1];
    }

    request = $.ajax({
        url: 'index.php',
        type: 'POST',
        data: data
    });
    request.done(function (res, textStatus, jqXHR) {
        $('#minta-lista').prop('disabled', true);
        $('input[name="lelet_hozzadas"]').prop('disabled', true);
        $('table[name="positive-options"]').load('index.php', { setCheckboxes: id });
        $('table[name="negative-option"]').load('index.php', { loadnegativeCheck: true });

        $('.currently-text-container').html(res);
        var iframe = textarea + '_ifr';
        $('#' + iframe).contents().find('#tinymce').append(res);
        $('#' + iframe).contents().find('#tinymce').append(footage);

    });
}

function send_iFrame(patient, medic, textarea) {

    var params = new window.URLSearchParams(window.location.search);
    if ($('form[name="iForm"] input:checkbox:checked').length > 0) {
        var mceContent = $('#' + textarea + '_ifr').contents().find('#tinymce').prop('outerHTML');
        if (textarea != 'uj-lelet-page') {
            idDumb = textarea.split('-');
            var data = 'update_lelet=' + encodeURIComponent(mceContent);
            data += '&lid=' + idDumb[2];
            data += '&' + $('form[name="iForm"]').serialize();
        }
        else {
            var data = 'save_lelet=' + encodeURIComponent(mceContent);
            data += '&seal_numb=' + $('#pecsetszam').val();
            data += '&tipus=' + $('#minta-lista').val();
            data += '&' + $('form[name="iForm"]').serialize();
        }

        request = $.ajax({
            url: 'index.php',
            type: 'post',
            data: data
        });
        request.done(function (res, textStatus, jqXHR) {
            $('#lelet-lista').load('index.php?reload_leletlista&p=' + params.get('page') + '&user=' + params.get('szerk'));
            $('#leletform').slideToggle(function () {
                $('#leletform').empty();
                $('#leletbutton').find('input[type="button"]').css('display', 'block');
            });
            $('.successful-message').css('color', '#67ec00');
            $('.successful-message').css('display', 'block');
            $('.successful-message').find('span').text('Lelet elmentve!');
            setTimeout(function () {
                $('.successful-message').css({ opacity: 1.0, visibility: 'visible' }).animate({ opacity: 0 }, 1000, function () {
                    $('.successful-message').css('display', 'none');
                });
            }, 1000);
        });

        $('#' + textarea + '_ifr').get(0).contentWindow.focus();
        $('#' + textarea + '_ifr').get(0).contentWindow.print();
    }
    else {
        $('.successful-message').css('display', 'block');
        $('.successful-message').find('span').css('color', 'red');
        $('.successful-message').find('span').text('Jelöld ha van eltérés vagy nincs!');
        setTimeout(function () {
            $('.successful-message').css({ opacity: 1.0, visibility: 'visible' }).animate({ opacity: 0 }, 1000, function () {
                $('.successful-message').css('display', 'none');
            });
        }, 1000);
    }
}
function save_iFrame(patient, medic, textarea) {

    var params = new window.URLSearchParams(window.location.search);
    //console.log($('form[name="iForm"]').serializeArray());
    if ($('form[name="iForm"] input:checkbox:checked').length > 0) {
        var mceContent = $('#' + textarea + '_ifr').contents().find('#tinymce').prop('outerHTML');

        if (textarea != 'uj-lelet-page') {
            idDumb = textarea.split('-');
            var data = 'update_lelet=' + encodeURIComponent(mceContent);
            data += '&lid=' + idDumb[2];
            data += '&' + $('form[name="iForm"]').serialize();
        }
        else {
            var data = 'save_lelet=' + encodeURIComponent(mceContent);
            data += '&seal_numb=' + $('#pecsetszam').val();
            data += '&tipus=' + $('#minta-lista').val();
            data += '&' + $('form[name="iForm"]').serialize();
        }
        request = $.ajax({
            url: 'index.php',
            type: 'post',
            data: data
        });
        request.done(function (res, textStatus, jqXHR) {
            $('#lelet-lista').load('index.php?reload_leletlista&p=' + params.get('page') + '&user=' + params.get('szerk'));
            $('#leletform').slideToggle(function () {
                $('#leletform').empty();
                $('#leletbutton').find('input[type="button"]').css('display', 'block');
            });
            $('.successful-message').css('color', '#67ec00');
            $('.successful-message').css('display', 'block');
            $('.successful-message').find('span').text('Lelet elmentve!');
            setTimeout(function () {
                $('.successful-message').css({ opacity: 1.0, visibility: 'visible' }).animate({ opacity: 0 }, 1000, function () {
                    $('.successful-message').css('display', 'none');
                });
            }, 1000);
        });
    }
    else {
        $('.successful-message').css('display', 'block');
        $('.successful-message').find('span').css('color', 'red');
        $('.successful-message').find('span').text('Jelöld ha van eltérés vagy nincs!');
        setTimeout(function () {
            $('.successful-message').css({ opacity: 1.0, visibility: 'visible' }).animate({ opacity: 0 }, 1000, function () {
                $('.successful-message').css('display', 'none');
            });
        }, 1000);
    }

}
/*$(function(){
    $('input[name="intval-start"], input[name="intval-end"]').datepicker({
    dateFormat: 'yy-mm-dd',
    changeMonth: true,
    changeYear: true,
    yearRange: '-100y:c+nn',
    maxDate: '+2y'
    });
});*/

function selectFolder(e) {
    var theFiles = e.target.files;
    var relativePath = theFiles[0].webkitRelativePath;
    var folder = relativePath.split("/");
    //alert(folder[0]);
}

/*function load_uj_lelet(){
    $('#leletform').load('index.php?uj_lelet');
    //tinymce.get('uj-lelet-page').setContent('');
    //var iframe = document.getElementById(FrameId);
    //iframe.src = iframe.src;
}*/

function syncFoglalasDataToUser(fogl, pass) {
    var data = $("#iform").serialize() + "&syncFoglalasDataToUser=1";
    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: data,
        success: function (data) {
            if (data.error != "") {
                alert(data.error);
            } else {
                showIdopontEditor("booking", pass, fogl);
                //$('input[name="paciensid"]').val(data.userId);
            }
        }
    });
}

$(function () {
    $('input[name="intval-start"], input[name="intval-end"]').datepicker({
        dateFormat: 'yy-mm-dd',
        changeMonth: true,
        changeYear: true,
        yearRange: '-100y:c+nn',
        maxDate: '+2y'
    });
});

$.extend(
    {
        redirectPost: function (loc, args) {
            var form = '';
            $.each(args, function (key, value) {
                form += '<input type = "hidden" name = "' + key + '" value = "' + value + '">';
            });
            $('<form action = "' + loc + '" method = "POST">' + form + '</form>').appendTo('body').submit();
        }
    });

function kuponCheck(coupon, version, foglalas, szurestipus) {
    console.log(foglalas);
    $.ajax({
        method: 'POST',
        url: 'index.php',
        data: {
            kuponCheck: '1',
            coupon: coupon,
            version: version,
            foglalas: foglalas,
            szurestipus: szurestipus
        }
    }).done(function (data) {
        if (data == 'error01') {
            $('#coupondesc').css('color', 'red').text('Érvénytelen kupon!');
        }
        if (data == 'error02') {
            $('#coupondesc').css('color', 'red').text('A kupont már felhasználták!');
        }
        if (data == 'error03') {
            $('#coupondesc').css('color', 'red').text('Erre a vizsgálatra nem lehet felhasználni!');
        }
        if (data != 'error01' && data != 'error02' && data != 'error03') {
            if (version == 1) {
                var str = data.split('|');
                $('#coupontitle').text(str[0]);
                $('#coupondesc').css('color', '#12c915').text(str[1]);
            }
            if (version == 2) {
                var str = data.split('|');
                var $text01 = str[0];
                $text02 = 'Kedv.:' + str[2];
                $('#coupondesc').css('color', '#444;').text($text01);
                $('#coupondiscount').css('color', '#444;').text($text02);
            }

        }
    });
}

function accountini(id) {
    var data = { accountIni: true, docid: id };
    $.ajax({
        method: 'POST',
        url: 'index.php',
        data: data
    }).done(function (data) {
        console.log(data);
        location.reload();
    });
}
function checkSzabiData() {

    $.ajax({
        type: 'POST',
        url: 'index.php',
        data: 'page=doctors&checkSzabiData=1&start=' + $('input[name="szabadsagtol"]').val() + '&end=' + $('input[name="szabadsagig"]').val() + '&orvosid=' + $('input[name="orvosid"]').val(),
        success: function (data) {
            if (data != '') {
                var result = '';
                var analysis = data.split('|');
                for (var i = 0; i < analysis.length; i++) {
                    var match = analysis[i].split(',');
                    result += match[0] + ' ' + match[1] + '\n';
                }
                alert('Az alábbi foglalások a szabadságra esnek: \n' + result);
                return false;
            }
            $('<input />').attr('type', 'hidden').attr('name', 'addszabadsag').attr('value', '1').appendTo('#iform');
            $('#iform').submit();
        }
    });

    return false;
}

function LWOpener(count) {
    var numb = (count - 10);
    if ($('.warrnings-content').css('max-height') == '250px') {
        $('.warrnings-content').css('max-height', 'none');
        $('.warningOpenFolder').html('Kevesebb <i class="fas fa-angle-double-up"></i>');
    }
    else {
        $('.warrnings-content').css('max-height', '250px');
        $('.warningOpenFolder').html(' Még ' + numb + ' db <i class="fas fa-angle-double-down"></i>');
    }
}

function SmoothScrollTo(string, timelength) {
    var timelength = timelength || 1000;
    $('html, body').animate({
        scrollTop: $('*:contains("' + string + '"):last').offset().top - 70
    }, timelength, function () {
        window.location.hash = '*:contains("' + string + '"):last';
    });
}

function scrollToTarget(string, target) {
    $('body').highlight(string, target);
    SmoothScrollTo(string, 1000);
    $(window).scrollTop($('*:contains("' + string + '"):first').offset().top);
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function scrollTo(id) {
    let pos = $("#" + id).offset().top - ($("#stickytablefilter").height() + 20);
    if (id == "filterbox") {
        pos = 0;
    }
    $([document.documentElement, document.body]).animate({
        scrollTop: pos
    }, 500);
}

//Highlight
jQuery.fn.highlight = function (c, target) {
    function e(b, c) {
        var d = 0;
        if (3 == b.nodeType) {
            var a = b.data.toUpperCase().indexOf(c);
            if (0 <= a) {
                d = document.createElement('span');
                d.className = 'highlight';
                a = b.splitText(a);
                a.splitText(c.length);
                var f = a.cloneNode(!0);
                d.appendChild(f);
                a.parentNode.replaceChild(d, a);
                d = 1
            }
        }
        else if (1 == b.nodeType && b.childNodes && !/(script|style)/i.test(b.tagName))
            for (a = 0; a < b.childNodes.length; ++a) {
                a += e(b.childNodes[a], c);
            }

        return d
    }
    return this.length && c && c.length ? this.each(function () {
        e(this, c.toUpperCase())
    }) : this
};
jQuery.fn.removeHighlight = function () {
    return this.find('span.highlight').each(function () {
        this.parentNode.firstChild.nodeName;
        with (this.parentNode) replaceChild(this.firstChild, this), normalize()
    }).end()
};

function openSidePanel(select) {
    $('.WL-sidePanel').animate({ width: 'toggle' });
    $('.WL-sidePanel').html('<img style="position:absolute;width:25px;height:25px;margin:0 auto;top:50%;left:50%;" src="images/loading.svg" />');
    if ($('.WL-sidePanel').css('display') == 'block') {
        if ($('.WL-sidePanel').data('examIndex')) var index = $('.WL-sidePanel').data('examIndex');
        else var index = 'empty';
        $.ajax({
            type: 'POST',
            url: 'index.php',
            data: { loadSelectedMenu: true, option: select, index: index },
            success: function (data) {
                $('.WL-sidePanel').html(data);
            }
        })
    }
}

function showMissingExams(index) {
    $('.WL-sidePanel').data('examIndex', index);
    if ($('.WL-sidePanel').css('display') != 'block') {
        $('.WL-sidePanel').animate({ width: 'toggle' });
    }
    $('.WL-sidePanel-selected-menu-conainer').html('<img style="position:absolute;width:25px;height:25px;margin:0 auto;top:calc(50% - 28px);left:calc(50% - 10px);" src="images/loading.svg" />');

    $.ajax({
        type: 'POST',
        url: 'index.php',
        data: { loadSelectedMenu: true, option: 'option-3', index: index },
        success: function (data) {
            $('.WL-sidePanel').html(data);
        }
    })
}

function copyBooking(id, pass) {
    if ($('#copyButton').css('color') == 'rgb(0, 0, 0)') {
        $('#copyButton').css('color', 'red');
        cpy = 1;
        foglalasSelected = id;
        foglalasSelectedPass = pass;
        return;
    }
    if ($('#copyButton').css('color') == 'rgb(255, 0, 0)') {
        $('#copyButton').css('color', 'black');
        cpy = 0;
        foglalasSelected = 0;
        foglalasSelectedPass = 0;
        return;
    }
}

function selectSPOption(option) {
    $('.WL-sidePanel-selected-menu-conainer').html('<img style="position:absolute;width:25px;height:25px;margin:0 auto;top:calc(50% - 28px);left:calc(50% - 10px);" src="images/loading.svg" />');
    if ($('.WL-sidePanel').data('examIndex')) var index = $('.WL-sidePanel').data('examIndex');
    else var index = 'empty';
    $.ajax({
        type: 'POST',
        url: 'index.php',
        data: { loadWLSelectedMenu: true, option: option, index: index },
        success: function (data) {
            $('.WL-sidePanel-selected-menu-conainer').html(data);

        }
    })
    $.ajax({
        type: 'POST',
        url: 'index.php',
        data: { loadWLSPTitle: true, option: option },
        success: function (data) {
            $('.WL-sidePanel-title > span:first').text(data);

        }
    })
}

function changeWLPosition() {
    var position = $('.manager-warnings').offset();
    var relativePositionLeft = 373;
    var relativeTop = 50.48333740234375;
    var monitorWidth = $(window).width();
    var relativeLeft = (monitorWidth - relativePositionLeft);


    if ($('.manager-warnings').css('position') == 'fixed') {
        var positionTop = (position.top - relativeTop) + 'px';
        var positionLeft = (position.left - relativeLeft) + 'px';
        $('.manager-warnings').css({ 'position': 'absolute', 'left': positionLeft, 'top': positionTop });
    }
    else {
        var positionTop = (position.top - $(window).scrollTop() - 10) + 'px';
        var positionLeft = position.left + 'px';
        $('.manager-warnings').css({ 'position': 'fixed', 'left': positionLeft, 'top': positionTop });
    }
}

function removeManager(id) {
    $.ajax({
        type: 'POST',
        url: 'index.php',
        data: { removeManager: id },
        success: function (data) {
            if (data != '') {
                $('#manager-' + id).remove();
                if ($('.WL-sidePanel').css('display') == 'block') {
                    $('.WL-sidePanel-selected-menu-conainer').html('<img style="position:absolute;width:25px;height:25px;margin:0 auto;top:calc(50% - 28px);left:calc(50% - 10px);" src="images/loading.svg" />');
                    $.ajax({
                        type: 'POST',
                        url: 'index.php',
                        data: { loadWLSelectedMenu: true, option: 'option-1' },
                        success: function (data) {
                            $('.WL-sidePanel-selected-menu-conainer').html(data);
                        }
                    });
                    $.ajax({
                        type: 'POST',
                        url: 'index.php',
                        data: { refreshLWOpener: true },
                        success: function (data) {
                            if (data != '') {
                                $('#LWOpener-container').html(data);
                            }
                        }
                    })
                }
            }
        }
    })
}

function refreshWList() {
    $.ajax({
        type: 'POST',
        url: 'index.php',
        data: { refreshWL: true },
        success: function (data) {
            if (data != '') {
                $('.warrnings-content').html(data);
            }
        }
    });
    $.ajax({
        type: 'POST',
        url: 'index.php',
        data: { refreshLWOpener: true },
        success: function (data) {
            if (data != '') {
                $('#LWOpener-container').html(data);
            }
        }
    })
}
function withdrawRemove(id) {
    $.ajax({
        type: 'POST',
        url: 'index.php',
        data: { withdrawManager: id },
        success: function (data) {
            if (data != '') {
                $('#removedManager-' + id).remove();
                refreshWList();
                selectSPOption('option-1');
            }
        }
    })
}

function initDateFilterPicker() {
    $('#napfilter').datepicker({
        language: 'hu',
        onSelect: function (formattedDate, date, inst) {
            inst.hide();
            setListDay(formattedDate);
            //window.location.href="index.php?page="+$("#napfilter").data("page")+"&setday="+formattedDate;
        }
    })

    $('#naptarfilter').datepicker({
        language: 'hu',
        onSelect: function (formattedDate, date, inst) {
            inst.hide();
            setNaptarDay(formattedDate);
            //window.location.href="index.php?page="+$("#napfilter").data("page")+"&setday="+formattedDate;
        }
    })

    $('#datefrom').datepicker({
        language: 'hu',
        onSelect: function (formattedDate, date, inst) {
            inst.hide();
            //setListDay(formattedDate);
            //window.location.href="index.php?page="+$("#napfilter").data("page")+"&setday="+formattedDate;
        }
    })
    $('#dateto').datepicker({
        language: 'hu',
        onSelect: function (formattedDate, date, inst) {
            inst.hide();
            //setListDay(formattedDate);
            //window.location.href="index.php?page="+$("#napfilter").data("page")+"&setday="+formattedDate;
        }
    })

    $('#vizsg_szures_start,#vizsg_szures_end').datepicker({
        language: 'hu'
    });

    $('.companyselector2').select2({
        placeholder: "Szűrés cégre",
        allowClear: true
    });
    $('.addressselector2').select2({
        placeholder: "Válassz helyszínt!"
    });

    $('.munkakorlist').select2({
        placeholder: "Válassz munkakört!",
        minimumInputLength: 3,
        allowClear: true,
        ajax: {
            url: 'index.php?getmunkakorlist',
            dataType: 'json'
            // Additional AJAX parameters go here; see the end of this chapter for the full code of this example
          }
    });
}

function refreshMunkakorlista(event){
    $(event).children("i").addClass("fa-spin");
    $.ajax({
        type: 'POST',
        url: 'index.php?page=booking',
        data: { refreshmunkakorlist:true },
        success: function (data) {
            $(event).children("i").removeClass("fa-spin");
        }
    })
}

function refreshCeglista(event){
    $(event).children("i").addClass("fa-spin");
    $.ajax({
        type: 'POST',
        url: 'index.php?page=booking',
        data: { refreshceglist:true },
        success: function (data) {
            $(event).children("i").removeClass("fa-spin");
            console.log("itt vagyok!");
        }
    })
}



function initQueryDatePicker() {
    $('#start-query-date, #end-query-date').datepicker({
        language: 'hu',
        onSelect: function (formattedDate, date, inst) {
            inst.hide();
            //setListDay(formattedDate);
            //window.location.href="index.php?page="+$("#napfilter").data("page")+"&setday="+formattedDate;
        }
    })
}




function downloadExamStat(){
    //var data='downloadExamStat=true&start='+$('#vizsg_szures_start').val()+'&end='+$('#vizsg_szures_end').val()+'&cegid='+$('select[name="cegselect"]').val();
    $('<form></form>').appendTo('body').submit();

    var form = $(document.createElement('form'));
    //$(form).attr('action', 'reserves.php');
    $(form).attr('method', 'POST');

    var key = $('<input>').attr('type', 'hidden').attr('name', 'downloadExamStat').val(true);
    var start = $('<input>').attr('type', 'hidden').attr('name', 'start').val($('#vizsg_szures_start').val());
    var end = $('<input>').attr('type', 'hidden').attr('name', 'end').val($('#vizsg_szures_end').val());
    var cegid = $('<input>').attr('type', 'hidden').attr('name', 'cegid').val($('select[name="cegselect"]').val());

    $(form).append($(key));
    $(form).append($(start));
    $(form).append($(end));
    $(form).append($(cegid));

    form.appendTo(document.body)

    $(form).submit();
}

//marci end

function showLogDetail(id) {
    $("#logdetail" + id).toggle();
    $("#logdetailcontent" + id).load("index.php?page=log&loadlogdetail=" + id);
    return false;
}

function startFODoctorSync(oid) {
    $.ajax({
        type: 'POST',
        url: 'index.php',
        data: { page: "doctors", fosync: 1, oid: oid },
        success: function (data) {
            if (data != '') {
                $("#fosyncbutton").html(data);
            }
        }
    })
}

function getFOData(oid) {
    $.ajax({
        type: 'POST',
        url: 'index.php',
        data: { page: "doctors", getfodata: 1, oid: oid },
        success: function (data) {
            if (data != '') {
                $("#fodatadiv").html(data);
            }
        }
    })
}

function manualNotificationSend(id, pass) {
    $.ajax({
        type: 'post',
        url: 'index.php',
        data: { manualNotificationSend: true, id: id },
        success: function (data) {
            if (data.status == true) alert(data.text);
            if (data.status == "error") alert(data.text);
            if (data.status == false) {
                var choice = confirm(data.text);
                //Ha mégis elakarja küldeni az értesítést:
                if (choice == true) {
                    $.ajax({
                        type: 'post',
                        url: 'index.php',
                        data: { manualNotificationSend: true, id: id, status: true },
                        success: function (data) {
                            if (data.status == true) alert(data.text);
                        }
                    });
                }
            }
        }
    });
}

function formSerializer(selector, formula) {
    if (formula == 'get') {
        data = "";
        // look for every form field
        selector.find('input, select, textarea').each(function () {
            // serialize data
            //Radio gomb kezelés:
            if ($(this).attr('type') == 'radio' && $(this).prop('checked') != true) return true;
            //Checkbox kezelés:
            if ($(this).attr('type') == 'checkbox' && $(this).prop('checked') != true) return true;

            //Normál kezelés:
            data += $(this).attr('name') + '=' + $(this).val() + '&';
        });
        // remove the last & char
        return data.replace(/&$/g, "");
    }

    if (formula == 'arr') {
        data = [];
        // look for every form field
        selector.find('input, select, textarea').each(function (i) {
            // serialize data
            //Radio gomb kezelés:
            if ($(this).attr('type') == 'radio' && $(this).prop('checked') != true) return true;
            //Checkbox kezelés:
            if ($(this).attr('type') == 'checkbox' && $(this).prop('checked') != true) return true;

            //Normál kezelés:
            data[i] = { name: $(this).attr('name'), value: $(this).val() };
        });
        // remove the last & char
        return data;
    }

}

function setQndA(orvosid, szurestipus) {
    $.ajax({
        type: 'post',
        url: 'index.php?page=doctors',
        data: { showQndA: true, orvosid: orvosid, szurestipus: szurestipus },
        success: function (data) {
            showGeneralPopup(data);
            /*if (data.status == "ok") {
                showGeneralPopup(data.html);
            } else {
                alert(data.status);
            }*/
        }
    });
}

function addkerdes(szurestipus, orvosid) {
    $.ajax({
        type: 'post',
        url: 'index.php?page=doctors',
        data: { addkerdes: true, orvosid: orvosid, szurestipus: szurestipus },
        success: function (data) {
            if (data == "ok") {
                setQndA(orvosid, szurestipus);
            }
        }
    });
}

function delkerdes(szurestipus, orvosid, q) {
    if (confirm('Biztos törlöd ezt az egységet?')) {
        $.ajax({
            type: 'post',
            url: 'index.php?page=doctors',
            data: { delkerdes: true, orvosid: orvosid, szurestipus: szurestipus, q },
            success: function (data) {
                console.log(data);
                if (data == "ok") {
                    setQndA(orvosid, szurestipus);
                }
            }
        });
    }
}

function saveQndA(szurestipus, orvosid) {
    var inputs = formSerializer($('#questions'), 'arr');

    $.ajax({
        type: 'post',
        url: 'index.php?page=doctors',
        data: { saveQndA: true, orvosid, szurestipus, inputs },
        success: function (data) {
            if (data == "ok") {
                setQndA(orvosid, szurestipus);
            }
        }
    });
}


function showRefundWindow(source, id) {
    $.ajax({
        type: 'post',
        url: 'index.php?page=banktransactions',
        data: { source: source, showrefund: id },
        success: function (data) {
            if (data.status == "ok") {
                showGeneralPopup(data.html);
            } else {
                alert(data.status);
            }
        }
    });
}

function startSimpleRefund(id, osszeg, source) {
    $("#refunbuttonsor").hide();
    $.ajax({
        type: 'post',
        url: 'index.php?page=banktransactions',
        data: { startsimplerefund: id, osszeg: osszeg, source:source },
        success: function (data) {
            $("#refunbuttonsor").show();
            $("#simplerefundbutton").hide();
            $("#transferresult").show();
            $("#transferresult").html(data.html);
            popUpPosition();
        }
    });
}


$(window).resize(function () {
    popUpPosition();
});

function showGeneralPopup(html) {
    $("#generalpopup").html(html);
    $("#generalpopup").show();
    popUpPosition();
}


function hideGeneralPopup() {
    $("#generalpopup").hide();
}

function popUpPosition() {
    let ww = $(window).width();
    let wh = $(window).height();
    let bw = $("#generalpopup").width();
    let bh = $("#generalpopup").height();

    $("#generalpopup").css("left", ww / 2 - bw / 2);
    $("#generalpopup").css("top", wh / 2 - bh / 2);
}

function toggleCheckBox(id) {
    var checkbox = $(id);
    console.log(checkbox.prop("checked"));
    if (checkbox.prop("checked") == true) checkbox.prop("checked", false);
    else checkbox.prop("checked", true);
    //checkbox.prop("checked", !checkbox.prop("checked"));
    return;
}

function switchCheckBoxes(classname, func) {
    if (func == 'disable') {
        $('input.' + classname + '[type=checkbox]').each(function () {
            if (this.checked) $(this).prop('checked', false);
        });

        $('#checkBoxSwitcher').attr('onClick', 'switchCheckBoxes("' + classname + '","enable")');
        $('#checkBoxSwitcher').text('Mindegyik');
        return true;
    }

    if (func == 'enable') {
        $('input.' + classname + '[type=checkbox]').each(function () {
            if (!this.checked) $(this).prop('checked', true);
        });

        $('#checkBoxSwitcher').attr('onClick', 'switchCheckBoxes("' + classname + '","disable")');
        $('#checkBoxSwitcher').text('Egyikse');
        return true;
    }
}

function selectAllCopyCompany() {
    $(".copycegch").prop('checked', true);
}
function deselectAllCopyCompany() {
    $(".copycegch").prop('checked', false);
}

function insertPaciensIntoDokirex(pid) {
    $.ajax({
        type: 'POST',
        url: 'index.php',
        data: { insertPaciensIntoDokirex: true, pid:pid },
        success: function (result) {
            showGeneralPopup(result);
        }
    });
}

function insertPaciensIntoDokirexHMM(pid) {
    if (!confirm("Biztos a HMM-es Dokirexbe akarsz exportálni?")) {
        return;
    }
    $.ajax({
        type: 'POST',
        url: 'index.php',
        data: { insertPaciensIntoDokirex: true, pid:pid, config: 'hmm' },
        success: function (result) {
            showGeneralPopup(result);
        }
    });
}


function checkAdminWarnings() {
    $.ajax({
        type: 'POST',
        url: 'index.php',
        data: { checkAdminWarnings: true },
        success: function (result) {
            $("#warnbuttoncontainer").html(result.button);
            $("#adminwarnwindow").html(result.window);
            if (result.window == "" && $("#adminwarnwindow").css("right") == "20px") {
                toggleWarnWindow();
            }
        }
    });

}

function warningAck(wid) {
    $.ajax({
        type: 'POST',
        url: 'index.php',
        data: { warningAck: true, wid: wid },
        success: function (result) {
            checkAdminWarnings();
        }
    });
}

function toggleWarnWindow() {
    if ($("#adminwarnwindow").css("right") == "20px") {
        $("#adminwarnwindow").css("right", -600);
    } else {
        $("#adminwarnwindow").css("right", 20);
    }
}

function toggleUploadFiles() {
    if ($("#uploadfilesfolder").css("margin-left") == "-260px") {
        $("#uploadfilesfolder").css("margin-left", -45);
    } else {
        $("#uploadfilesfolder").css("margin-left", -260);
    }
}

function toggleAlkalmassagBox() {
    if ($("#alkalmassagfolder").css("margin-left") == "-332px") {
        $("#alkalmassagfolder").css("margin-left", -45);
    } else {
        $("#alkalmassagfolder").css("margin-left", -332);
    }
}


var triggerSearch = "";
var searchIsGoing = false;

function prepareUserDataSearch() {
    $("#pdatasearchrow").toggle();
    $(".pdatarow").toggle();

    $("#pdatasearchinput").unbind();
    $("#pdatasearchinput").keyup(function () {
        triggerSearch = $(this).val();
    });
}

function searchTimer() {
    if (triggerSearch != "" && !searchIsGoing) {
        searchIsGoing = true;
        $.ajax({
            url: 'index.php',
            type: 'POST',
            data: { page: "booking", searchpaciens: 1, term: triggerSearch },
            success: function (data) {
                $("#searchpaciensresult").html(data);
                searchIsGoing = false;
            }
        });
        triggerSearch = "";
    }
}

function bindUserToReservation(uid) {
    let fid = $("#reservationId").val();
    let ppp = $("#reservationToken").val();
    if (confirm("Csatoljuk a kiválasztott felhasználót a foglaláshoz?")) {
        $.ajax({
            url: 'index.php',
            type: 'POST',
            data: { page: "booking", bindusertoreservation: 1, uid: uid, fid: fid, pp: ppp },
            success: function (data) {
                showIdopontEditor("booking", ppp, fid);

                if ($("#arrivalstable").length) {
                    $.ajax({
                        type: "GET",
                        url: "index.php?page=blista&showarrivalstable",
                        success: function (response) {
                            $("#arrivalstable").html(response);
                        }
                    });
                }

                if ($("#elojegyzestable").length) {
                    $.ajax({
                        type: "GET",
                        url: "index.php?page=booking&showelojegyzestable",
                        success: function (response) {
                            $("#elojegyzestable").html(response);
                            afterElojegyzesTableInit();
                        }
                    });
                }

            }
        });
    }
}

function newUserDataFromReservation() {
    let fid = $("#reservationId").val();
    let ppp = $("#reservationToken").val();

    let data = $("#iform").serialize() + "&page=booking&newUserDataFromReservation=1";
    $.ajax({
        url: 'index.php',
        type: 'GET',
        data: data,
        success: function (data) {
            if (data.error != "") {
                alert(data.error);
            } else {
                foglalasMentes("booking");
            }
        }
    });
}



function refreshMonthSalary(month) {
    $.ajax({
        type: 'POST',
        url: 'index.php',
        data: { refreshmonthsalary: 1, month: month, page: "salary" },
        success: function (result) {
            $("#salarycontainer").html(result);
            initDateFilterPicker();
        }
    });
}

function refreshSalary(month) {
    $.ajax({
        type: 'POST',
        url: 'index.php',
        data: { refreshsalary: 1, datefrom: $("#datefrom").val(), dateto: $("#dateto").val(), page: "salary" },
        success: function (result) {
            if (result.error != "") {
                alert(result.error);
                return;
            }
            $("#salarycontainer").html(result.html);
            initDateFilterPicker();
        }
    });
}

function salaryDataNew(oid) {
    $.ajax({
        type: 'POST',
        url: 'index.php',
        data: { salarydatanew: 1, oid: oid, page: "salary" },
        success: function (result) {
            if (result.error != "") {
                alert(result.error);
                return;
            }
            $("#salarydataeditor" + oid).html(result.html);
        }
    });
}

function salaryDataDelete(oid, sid) {
    if (confirm("Biztos törlöd?")) {
        $.ajax({
            type: 'POST',
            url: 'index.php',
            data: { salarydatadelete: 1, oid: oid, sid: sid, page: "salary" },
            success: function (result) {
                if (result.error != "") {
                    alert(result.error);
                    return;
                }
                $("#salarydataeditor" + oid).html(result.html);
            }
        });
    }
}

function salaryDataSave(oid) {
    let params = $("#salarydataeditorform" + oid).serialize();

    $.ajax({
        type: 'POST',
        url: 'index.php?page=salary&salarydatasave=1&oid=' + oid,
        data: params,
        success: function (result) {
            if (result.error != "") {
                alert(result.error);
                return;
            }
            $("#salarydataeditor" + oid).html(result.html);
        }
    });
}


function initUploadRoutine() {
    $("#assetphotofile").on("change", preparePhotoUpload);
    $(".assetphotofile").on("change", preparePhotoUpload);
}

function preparePhotoUpload(event) {
    let tipus = $(this).data("tipus");
    let id = $(this).data("id");

    files = event.target.files;

    $("#ajaxloader").show();

    event.stopPropagation();
    event.preventDefault();

    var data = new FormData();
    data.append("uploadasset", id)
    data.append("tipus", tipus)
    $.each(files, function (key, value) {
        data.append(key, value);
    });

    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: data,
        cache: false,
        processData: false,
        contentType: false,
        success: function (response, textStatus, jqXHR) {
            $("#ajaxloader").hide();

            $("#asseteditor").html(response.html);
            $("#asseteditor_"+tipus).html(response.html);
            initUploadRoutine();

            if (response.error != "") {
                alert(response.error);
                return;
            }
        }, error: function (jqXHR, textStatus, errorThrown) {
            $("#ajaxloader").hide();
            console.log('ERRORS: ' + textStatus);
        }
    });
}

function deleteAsset(tipus, id, assetId) {
    if (!confirm("Biztos törlöd ezt a képet?")) {
        return;
    }

    $.ajax({
        type: 'POST',
        url: 'index.php',
        data: { deleteasset: id, tipus: tipus },
        success: function (result) {
            $("#asseteditor").html(result.html);
            $("#asseteditor_"+tipus).html(result.html);
            initUploadRoutine();
        }
    });
}

function toggleInvoiceFizetve(invId, partnerId) {
    $.ajax({
        type: 'POST',
        url: 'index.php?page=invoices',
        data: { toggleinvoicefizetve: invId, partnerid: partnerId },
        success: function (result) {
            $("#invoicelist").html(result.html);
        }
    });

}

function oltasEljottCheck(id) {
    $.ajax({
        type: "POST",
        url: "?page=oltasigenyek&subpage=showall",
        data: { oltaseljottcheck: id },
        success: function (response) {
            $("#personrow" + id).html(response);
        }
    })
}

function sendOltasMessage(id) {
    $.ajax({
        type: "POST",
        url: "?page=oltasigenyek&subpage=showall",
        data: { sendoltasmessage: id },
        success: function (response) {
            $("#personrow" + id).html(response);
        }
    })
}

function initIrszAutoFill() {
    $("#irsz").keyup(function () {
        let irsz = $(this).val();
        if (irsz.length == 4) {
            $.ajax({
                method: "POST",
                url: "/index.php",
                data: { irszquery: irsz }
            }).done(function (msg) {
                if (msg != "") {
                    $("#varos").val(msg);
                }
            });
        }
    });

    $(".bookingeditorcegselector2").select2({
        placeholder: "Nincs céghez kötve!",
        tags: true
    });

    $(".bookingeditormunkakorselector2").select2({
        placeholder: "Nincs munkakör megadva!",
        minimumInputLength: 2,
        tags: true
    });

    $(".bookingeditorselector2").select2({
        placeholder: "Nincs orvoshoz kötve!"
    });

    $('.munkakorlist').select2({
        placeholder: "Válassz munkakört!",
        minimumInputLength: 3,
        allowClear: true,
        ajax: {
            url: 'index.php?getmunkakorlist',
            dataType: 'json'
            // Additional AJAX parameters go here; see the end of this chapter for the full code of this example
          }
    });

    initCeglistSelect2();
}

function initTabOrder() {
    $(".ui-taborder").keypress(function (e) {
        if (e.which == 13) {
            var index = $(this).data("taborder") + 1;
            $(document).find("[data-taborder='" + index + "']").focus();
            //$('.ui-dform-text').eq(index).focus();

        }
    });
}

function initDateInput() {
    var el = document.getElementById("editorszuldatum");

    el.onkeyup = function(evt) {
        if((evt.keyCode >= 48 && evt.keyCode <= 57) || (evt.keyCode >= 96 &&
            evt.keyCode <= 105)) {
            evt = evt || window.event;

            var size = document.getElementById('editorszuldatum').value.length;

            if ((size == 4 && document.getElementById('editorszuldatum').value > 2500)|| (size == 7 && Number(document.getElementById('editorszuldatum').value.split('-')[1]) > 12) || (size == 10 && Number(document.getElementById('editorszuldatum').value.split('-')[2]) > 31)) {
                alert('Invalid Date');
                document.getElementById('editorszuldatum').value = '';
                return;
            }

            if ((size == 4 && document.getElementById('editorszuldatum').value < 2500)|| (size == 7 && Number(document.getElementById('editorszuldatum').value.split('-')[1]) < 13)) {
                document.getElementById('editorszuldatum').value += '-';
            }

        }
    }
}


function initGeneralSearch() {
    $("#generalsearch").keypress(function (e) {
        if (e.which == 13) {
            let term = $(this).val();
            let page = $(this).data("page");
            let resultDiv = $(this).data("resultdiv");

            if (term.length < 3 && term.length != 0) {
                alert("A keresési érték minimum 3 karakter!");
                return;
            }

            $.ajax({
                method: "POST",
                url: "index.php",
                data: { page: page, generalsearch: 1, term: term }
            }).done(function (msg) {
                $("#" + resultDiv).html(msg);
            });
        }
    });

}

function toggleSubMenu(id) {
    $("#submenu" + id).slideToggle("fast", function () {
        let open = 1;
        if ($("#submenu" + id).is(":hidden")) {
            open = 0;
        }
        $.ajax({
            method: "POST",
            url: "index.php",
            data: { opensubmenu: id, open: open }
        });
    });

}

function toggleElojegyzesTableNaptar(oid, tid) {
    $("#tablenyito"+oid+"_"+tid).css({'transform' : 'rotate(180deg)'});
    if ($(".beotable"+oid+"_"+tid).is(":hidden")) {
        $("#tablenyito"+oid+"_"+tid).css({'transform' : 'rotate(0deg)'});
    }

    $(".beotable"+oid+"_"+tid).slideToggle(function() {
        let closed = 0;
        if($(this).is(":hidden")) {
            closed = 1;
        }

        $.ajax({
            method: "POST",
            url: "index.php",
            data: {closebeotable:closed, oid:oid, tid:tid}
        });
    });


}

function setSynlabStatus() {
    var form = $("#synlabParamsForApplication");
    var arr = [];
    var i = 0;

    form.find("input").each(function () {
        if (typeof $(this).attr("name") != "undefined" && ~$(this).attr("name").indexOf("sltc") && $(this).prop("checked") == true) {
            i++;
            var id = $(this).attr("value");
            //Ez lesz a formula amire szükségem lesz.
            arr[id] = $("input[name='sltp-" + id + "']").val();
        }
    });


    $.ajax({
        url: "?page=laborkero",
        method: "POST",
        data: { getsynlabstatus: true, items: arr, packId: $("select[name='PackId']").val() },
        success: function (response) {
            response = $.parseJSON(response);

            $("#item_numb").html(response.unit + " db");
            $("#grand_total").html(response.price + ".-");
            $("#grand_total_int").val(response.total_price);
            $("#required_tubes").html(response.tubes);
        }
    });

}

$(document).mouseup(function (e) {

    if (!$("#searchbar").is(e.target) && !$("#data-source").is(e.target) && !$("#patientlist").is(e.target)) {
        $("#patientlist").hide();
    }

    if ($("#searchbar").is(e.target) && $("#searchbar").val().length > 4) {
        $("#patientlist").show();
    }
    /*var object = $("YOUR CONTAINER SELECTOR");

    // if the target of the click isn't the container nor a descendant of the container
    if (!container.is(e.target) && container.has(e.target).length === 0) 
    {
        container.hide();
    }*/
});

function setAId(aid) {
    $("#aid").val(aid);
    $("#searchbar").val($("#patientlist").find('option:selected').attr("name"));
    $("#patientlist").hide();
}

function setPatientDroplist(word) {

    if (word.length > 4) {
        $("#patientlist").css({ "display": "block" });
        $.ajax({
            url: "?page=laborkero",
            method: "POST",
            data: { searchPatient: true, word: word, source: $("#data-source").val() },
            success: function (response) {
                $("#patientlist").html(response);
                $("#patientlist").attr("size", $("#patientlist option").length);
            }
        })
    } else {
        $("#patientlist").css({ "display": "none" });
    }
}

function setLaborPatientData(id, source) {
    $.ajax({
        url: "?page=laborkero",
        method: "POST",
        data: { setPatientData: true, aid: id, source: source },
        success: function (response) {
            //console.log(response);
            $("#patientData").html(response);
        }
    })
}

function searchbyitem(keyword) {
    $.ajax({
        url: "?page=labortetelek",
        method: "POST",
        data: { searchbyitem: true, keyword: keyword, szerk: true },
        success: function (response) {
            $("#item-content").html(response);
        }
    })
}

function selectItemForPackage(item) {
    $.ajax({
        url: "?page=labortetelek",
        method: "POST",
        data: { selectItemForPackage: true, id: item, szerk: true },
        success: function (response) {
            console.log(response);
        }
    })
}

function readExcel() {

    var data = new FormData();
    $.each($('#staff-list-file')[0].files, function (i, file) {
        data.append('file-' + i, file);
    });

    data.append('readExcel', true);
    //var company  = ;
    //var referral = ;
    data.append('cegid', $('#companyid').val());
    data.append('nev', $('#nev-column').val());
    data.append('taj', $('#taj-column').val());
    data.append('szuldatum', $('#szuldatum-column').val());
    data.append('szulhely', $('#szulhely-column').val());
    data.append('anyjaneve', $('#anyjaneve-column').val());
    data.append('lakcim', $('#lakcim-column').val());
    data.append('email', $('#email-column').val());
    data.append('tel1', $('#tel1-column').val());
    data.append('tel2', $('#tel2-column').val());
    data.append('szerv', $('#szerv-column').val());
    //data.append('referral_template',$('select[name="referral_template"]').val());
    //data.append('sheet',$('#sheet').val());
    /*if($("#onlyexcel").prop("checked")==true){
        data.append('onlyexcel',1);
    }else{
        data.append('onlyexcel',0);
    }*/

    /*if($("#nojob").prop("checked")==true){
        data.append('nojob',1);
    }else{
        data.append('nojob',0);
    }*/

    /*var columns = {};
    $("#column_names").find("input").each(function(){
        data.append($(this).attr("name"),$(this).val());
        //columns[$(this).attr("name")] = $(this).val();
    });*/


    $('#excel_loading').html('<img src="images/loading.svg" width="20" />');
    $.ajax({
        url: 'index.php?page=companies',
        data: data,
        cache: false,
        contentType: false,
        processData: false,
        method: 'POST',
        type: 'POST', // For jQuery < 1.9
        success: function (data) {
            $('#excel_loading').html('');
            $('#excel-processing-result').html(data);
        }
    });
}

function Insert_New_Organizational_Units(cid) {
    if (!confirm("Biztosan importálni akarod az új szervezeti egységeket?")) {
        return;
    }
    var values = $("input[name='new_units[]']")
        .map(function () { return $(this).val(); }).get();

    $.ajax({
        url: "?page=companies",
        method: "POST",
        data: { Insert_New_Organizational_Units: true, units: values, cegid: cid },
        success: function (response) {
            confirm(response);
            $("#Insert-New-O-Units-Button").remove();
        }
    })

    //console.table(values);
}

function Insert_New_Staff_List(cid) {
    if (!confirm("Biztosan importálni akarod az új állományi listát?")) {
        return;
    }

    $.ajax({
        url: "?page=companies",
        method: "POST",
        data: { Insert_New_Staff_List: true, cegid: cid },
        success: function (response) {
            console.log(response);
            //confirm(response);
            //$("#Insert-New-Staff-List-Button").remove();
        }
    })

    //console.table(values);
}

function New_Notification_Message(cid) {
    $.ajax({
        url: "?page=companies",
        method: "POST",
        data: { New_Notification_Message: true, cegid: cid },
        success: function (response) {
            $("#edit-notification-message").attr("disabled", true);
            $("#set-new-notification-message").attr("disabled", true);
            $("#notification-editor").html(response).css("border", "none");
        }
    })
}

function Insert_New_Notification() {
    if (!confirm("Biztosan mented az üzentet?")) {
        return;
    }

    $.ajax({
        url: "?page=companies",
        method: "POST",
        data: { Insert_New_Notification: true, cegid: $("#companyid").val(), megnev: $("#megnev").val(), targy: $("#targy").val(), szoveg: $("#szoveg").val() },
        dataType: "json",
        success: function (response) {

            //Minden ok
            if (response.result) {
                window.location.reload();
            }

            //Tárgy nem megfelelő
            if (response.targyError == true) {
                $("#targy").css("border", "1px solid red");
            } else {
                $("#targy").css("border", "1px solid #a3a3a3");
            }

            //Szöveg nem megfelelő
            if (response.szovegError == true) {
                $("#szoveg").css("border", "1px solid red");
            } else {
                $("#szoveg").css("border", "1px solid #a3a3a3");
            }

            //Megnevezés nem megfelelő
            if (response.megnevError == true) {
                $("#megnev").css("border", "1px solid red");
            } else {
                $("#megnev").css("border", "1px solid red");
            }
        }
    })
}
function Save_Notification(nid) {
    if (!confirm("Biztosan mented a módosításokat?")) {
        return;
    }

    $.ajax({
        url: "?page=companies",
        method: "POST",
        data: { Save_Notification: true, notificationId: nid, megnev: $("#megnev").val(), targy: $("#targy").val(), szoveg: $("#szoveg").val() },
        dataType: "json",
        success: function (response) {
            if (response.errorCode == 0) {
                window.location.reload();
            }
            if (response.errorCode == 1) {
                //A tárgy jó
                $("#targy").css("border", "1px solid #a3a3a3");
                //A szöveg rossz
                $("#szoveg").css("border", "1px solid red");
            }
            if (response.errorCode == 2) {
                //A tárgy rossz
                $("#targy").css("border", "1px solid red");
                //A szöveg jó
                $("#szoveg").css("border", "1px solid #a3a3a3");
            }
            if (response.errorCode == 3) {
                //A tárgy rossz
                $("#targy").css("border", "1px solid red");
                //A szöveg rossz
                $("#szoveg").css("border", "1px solid red");
            }
        }
    })

}

function Load_Notification_Message(nid, cegid) {
    console.log("itt vagyok!");
    $.ajax({
        url: "?page=companies",
        method: "POST",
        data: { Load_Notification_Message: true, notificationId: nid, cegid: cegid },
        dataType: "json",
        success: function (response) {
            if (response) {
                $("#notification-editor").html(response.editor).css("border", "1px solid #a3a3a3");;
                $("#notification-selector").html(response.selector);
            }

        }
    })
}

function Edit_Notification_Message(nid) {
    $.ajax({
        url: "?page=companies",
        method: "POST",
        data: { Edit_Notification_Message: true, notificationId: nid },
        success: function (response) {
            $("#edit-notification-message").attr("disabled", true);
            $("#set-new-notification-message").attr("disabled", true);
            $("#notification-editor").html(response);
        }
    })
}

function Cancel_Notification_Processing() {
    if (!confirm("Biztosan bezárod az üzenet szerkesztőjét?")) {
        return;
    }

    $.ajax({
        url: "?page=companies",
        method: "POST",
        data: { Cancel_Notification_Processing: true, notificationId: $("#notification-selector").val() },
        success: function (response) {
            console.log(response);
            $("#edit-notification-message").attr("disabled", false);
            $("#set-new-notification-message").attr("disabled", false);
            $("#notification-editor").html(response).css("border", "1px solid #a3a3a3");
            //confirm(response);
            //$("#Insert-New-Staff-List-Button").remove();
        }
    })
}

function Delete_Notification_Message(nid) {
    if (!confirm("Biztosan törölni akarod az értesítő üzenetet?")) {
        return;
    }
    $.ajax({
        url: "?page=companies",
        method: "POST",
        data: { Delete_Notification_Message: true, notificationId: nid },
        success: function (response) {
            window.location.reload();
            //console.log(response);
            //$("#edit-notification-message").attr("disabled", false);
            //$("#set-new-notification-message").attr("disabled", false);
            //$("#notification-editor").html(response);
            //confirm(response);
            //$("#Insert-New-Staff-List-Button").remove();
        }
    })
}

function Show_Organizational_List(cegid, nlid) {
    $.ajax({
        url: "?page=companies",
        method: "POST",
        data: { Show_Organizational_List: true, cegid: cegid, list: nlid },
        success: function (response) {
            if ($("#" + nlid + "-szervek").contents().length == 0) {
                $("#" + nlid + "-szervek").html(response);
            } else {
                $("#" + nlid + "-szervek").empty();
            }

        }
    })
}

function Set_Organizational_To_List(szid, cegid, nlid) {
    $.ajax({
        url: "?page=companies",
        method: "POST",
        data: { Set_Organizational_To_List: true, list: nlid, szid: szid, cegid: cegid },
        success: function (response) {
            $("#" + nlid + "-organizational-list").html(response);
        }
    })
}

function Save_Custom_Notification_List(nlid) {
    $.ajax({
        url: "?page=companies",
        method: "POST",
        data: { Save_Custom_Notification_List: true, list: nlid, megnev: $("#" + nlid + "-megnev").val(), leiras: $("#" + nlid + "-leiras").val(), uid: $("#" + nlid + "-uzenet").val() },
        success: function (response) {
            if (response == "success") {
                window.location.reload();
            }

        }
    })
}

function Show_Affected_Staff(nlid) {
    if ($("#" + nlid + "-staff-list").contents().length == 0) {
        $.ajax({
            url: "?page=companies",
            method: "POST",
            data: { Show_Affected_Staff: true, list: nlid },
            success: function (response) {
                $("#" + nlid + "-staff-list").html(response);
            }
        })
    } else {
        $("#" + nlid + "-staff-list").empty();
    }
}

function Inicialize_Custom_Notification_List(nlid) {
    $.ajax({
        url: "?page=companies",
        method: "POST",
        data: { Inicialize_Custom_Notification_List: true, list: nlid },
        success: function (response) {
            window.location.reload();
        }
    })
}

function Set_Scroll_To_Staff_List(scrollNumber, cegid) {
    
    $.ajax({
        url: "?page=companies",
        method: "POST",
        data: { Set_Scroll_To_Staff_List: true, scroll: scrollNumber, cegid: cegid },
        success: function (response) {
            $("#staff-list-box").html(response);
        }
    })
}

function Staff_List_Searching(cegid,keyword,szid){
    $('#staff-list-search-bar-loading').html('<img src="images/loading.svg" width="20" />');
    $.ajax({
        url: "?page=companies",
        method: "POST",
        data: { Staff_List_Searching: true,cegid: cegid, keyword: keyword,szid:szid },
        success: function (response) {
            console.log(response);
            $('#staff-list-search-bar-loading').html('');
            $("#staff-list-box").html(response);
        }
    })
}

function Staff_List_Filtering(cegid,keyword,szid){
    $('#staff-list-filter-loading').html('<img src="images/loading.svg" width="20" />');
    $.ajax({
        url: "?page=companies",
        method: "POST",
        data: { Staff_List_Filtering: true,cegid: cegid, szid: szid,keyword:keyword },
        success: function (response) {
            console.log(response);
            $('#staff-list-filter-loading').html('');
            $("#staff-list-box").html(response);
        }
    })
}

function toggleBeoService(button) {
    if ($(button).hasClass("serviceselected")) {
        $(button).removeClass("serviceselected");
        $(button).addClass("servicenotselected");
    } else {
        $(button).removeClass("servicenotselected");
        $(button).addClass("serviceselected");
    }

    var tk = "";
    var num = 0;
    var t = "nincs tipus hozzárendelve";
    var tlist = "";
    var beosztasid = $(button).data("beoid");

    $("#tipusvalaszto" + beosztasid + " a").each(function () {
        if ($(this).hasClass("serviceselected")) {
            tk = tk + "|" + $(this).data("tipusid") + "|";
            num++;
            tlist = tlist + ", " + $(this).html();
        }
    });

    if (num > 0) {
        t = tlist.substring(2);
    }

    $("#tipusstatus" + beosztasid).html("<a href='#' class='tlink' title='" + t + "' onclick='showTipusValaszto(" + beosztasid + ");return false;'>" + num + " tipus</a>");

    $.ajax({
        url: "index.php",
        type: "get",
        data: "page=doctors&savebeosztastipusok=" + beosztasid + "&value=" + encodeURIComponent(tk)
    });
}

function toggleBeoCegSelector(groupid) {
    $("#selectcompanydiv" + groupid).toggle(function () {
        let open = 1;
        if ($("#selectcompanydiv" + groupid).is(":hidden")) {
            open = 0;
        }
        $.ajax({
            method: "POST",
            url: "index.php",
            data: { toggleBeoCegSelector: groupid, open: open }
        });
    });

}

function toggleBeoCompany(button) {
    if ($(button).hasClass("serviceselected")) {
        $(button).removeClass("serviceselected");
        $(button).addClass("servicenotselected");
    } else {
        $(button).removeClass("servicenotselected");
        $(button).addClass("serviceselected");
    }

    let num = 0;
    let cegids = $(button).parent().data("cegids");
    let doctorId = $(button).parent().data("doctorid");
    let groupId = $(button).parent().data("beogroupid");

    let ts = [];
    $("#selectedcompanies" + groupId + " a").each(function () {
        if ($(this).hasClass("serviceselected")) {
            ts.push(parseInt($(this).data("cegid")));
            num++;
        }
    });

    ts = ts.sort(function(a, b) {
        return a - b;
    });
    let tk = "|"+ts.join("||")+"|";

    let request = $.ajax({
        url: "index.php?page=doctors",
        type: "post",
        data: "savebeosztascompanies=" + encodeURIComponent(cegids) + "&doctorid=" + doctorId + "&groupid=" + groupId + "&value=" + encodeURIComponent(tk)
    });

    request.done(function (response, textStatus, jqXHR) {
        $("#beoeditor").html(response);
    });
}

function addBeoRow(doctorId, groupId) {
    let request = $.ajax({
        url: "index.php?page=doctors",
        type: "post",
        data: "addbeorow=1&doctorid=" + doctorId + "&groupid=" + groupId
    });

    request.done(function (response, textStatus, jqXHR) {
        $("#beoeditor").html(response);
    });
}

function delBeoRow(doctorId, id) {
    if (!confirm("Biztos törlöd ezt a beosztás sort?")) {
        return;
    }

    let request = $.ajax({
        url: "index.php?page=doctors",
        type: "post",
        data: "delbeorow=1&doctorid=" + doctorId + "&id=" + id
    });

    request.done(function (response, textStatus, jqXHR) {
        $("#beoeditor").html(response);
    });
}

function addBeoCopy(doctorId, groupId) {
    if (!confirm("Duplikálod ezt a beosztást?")) {
        return;
    }

    let request = $.ajax({
        url: "index.php?page=doctors",
        type: "post",
        data: "addbeocopy=1&doctorid=" + doctorId + "&groupid=" + groupId
    });

    request.done(function (response, textStatus, jqXHR) {
        $("#beoeditor").html(response);
    });
}

function addBeoBlock(doctorId) {
    let request = $.ajax({
        url: "index.php?page=doctors",
        type: "post",
        data: "addbeoblock=1&doctorid=" + doctorId
    });

    request.done(function (response, textStatus, jqXHR) {
        $("#beoeditor").html(response);
    });
}

function eljottButtonProtocol(el, force) {
    let id = $(el).data("id");

    $.ajax({
        url: "index.php",
        method: "POST",
        data: { eljottcheckboxprotocol: 1, id: id, force:force },
        success: function (response) {
            if (response.confirm != "" && force == 0) {
                $.confirm({
                    title: 'Figyelem!',
                    content: response.confirm,
                    useBootstrap: false,
                    boxWidth: '300px',
                    buttons: {
                        igenButton: {
                            text: 'Igen',
                            btnClass: 'btn-blue',
                            keys: ['enter', 'shift'],
                            action: function(){
                                eljottButtonProtocol(el, 1);
                            }
                        },
                        nemButton: {
                            text: 'Nem',
                            btnClass: 'btn-blue',
                            action: function(){

                            }
                        }
                    }
                });
                return;
            }

            $("#eljottchk").html(response.html);
        }
    })
}

function setCovidListStatus(status, id) {
    if (status == "DENIED") {
        $("#coviddeniedrow"+id).slideToggle();
        return;
    }

    let deniedText = "";
    if (status == "DENIEDCONFIRM") {
        status = "DENIED";
        deniedText = $("#coviddeniedtext"+id).val();
    }

    $.ajax({
        url: "index.php?page=covidlist",
        method: "POST",
        data: { setstatus: status, id: id, deniedText:deniedText },
        success: function (response) {
            $("#covidsor"+id).html(response);
        }
    })
}

function setCovidListFilter(status) {
    window.location.href="index.php?page=covidlist&statusfilter="+encodeURIComponent(status);
}

function setCovidListFilter(status) {
    window.location.href="index.php?page=covidlist&statusfilter="+encodeURIComponent(status);
}

function setPreBookingStatus(id,value){
    $.ajax({
        url: "index.php?page=prebookingmanagement",
        method: "POST",
        data: { setPreBookingStatus:id,indicator:value },
        success: function (response) {
            $("#pbindicatorcontainer"+id).html(response);
        }
    })
}

var t;
function autoSaveTextArea(id,text){
    clearTimeout(t);
    $("#pbtext"+id).css({"border":"1px solid orange","outline":"none"});
    t = setTimeout(function() {
        $.ajax({
            url: "index.php?page=prebookingmanagement",
            method: "POST",
            data: { autoSaveTextArea:id,text:text },
            success: function (response) {
                $("#pbtext"+id).css({"border":"1px solid #ccc","outline":"none"});
            }
        })
    }, 2000);
}

function downloadTargetFile(fileType,id){
    $.ajax({
        url: "index.php?page=prebookingmanagement",
        method: "POST",
        data: { downloadTargetFile:id,type:fileType },
        success: function (response) {
            console.log(response);
          //window.location=response;
        }
    });
}

function copyToClipboard(element) {
    var $temp = $("<input>");
    $("body").append($temp);
    $temp.val($(element).html()).select();
    document.execCommand("copy");
    $temp.remove();

    var tooltip = $("#"+$(element).attr("id")+"tooltip");
    tooltip.html("Copied");
  }
  
function outFunc(element) {
    var tooltip = $("#"+$(element).attr("id")+"tooltip");
    tooltip.html("Copy to clipboard");
}


function oroklesSet(id) {
    $("#"+id).prop( "checked", false);
}

function oroklesImageToggle(el, key, tipus, id, parent) {
    if ($(el).is(":checked")) {
        id = parent;
    }

    $.ajax({
        url: "index.php?page=webpagedata",
        method: "POST",
        data: { getImageUploadDiv:1, key:key, tipus:tipus, id:id },
        success: function (response) {
            $("#asseteditor_"+tipus).html(response);
            initUploadRoutine();
        }
    });
}

function orvosVelemenyEnter() {
    $("#orvosszoveg").height(216);
    $(".mainalkform").hide();
    $(".ovsubmit").show();
}

function orvosVelemenyExit() {
    $("#orvosszoveg").height(40);
    $(".mainalkform").show();
    $(".ovsubmit").hide();
}


function setNaptarDay(day) {
    $("#napfilter").css("background-image","url('/images/loading_transparent.svg')");
    $("#naptartable").load("index.php?page=varoterem&showtable&setday="+encodeURIComponent(day),null,
        function(responseText){
            initDateFilterPicker();
            $("#napfilter").css("background-image","url('/images/empty-128.png')");
        }
    );
}

function beutaloHozzadasa(fid){
	$.ajax({
        url: "index.php",
        method: "POST",
        data: { beutaloHozzadasBox:fid},
        success: function (result) {
            showGeneralPopup(result);
        }
    });
}

function beutalohozzadasafinish(bid,fid,tname){
	$.ajax({
        url: "index.php",
        method: "POST",
        data: { beutalohozzadasafinish:true,bid:bid,fid:fid,tname:tname},
        success: function (result) {
            console.log(result);
            location.reload();
        }
    });
}

function deleteUploadedFile(id, k, fid) {
    if (!confirm("Biztos törlöd ezt a fájlt?")) {
        return false;
    }

    $.ajax({
        url: "index.php",
        method: "POST",
        data: { deleteuploadedfile:true,id:id, k:k, fid:fid},
        success: function (response) {
            if (response.status != "") {
                alert(response.status);
            }
            $("#idoponteditor").html(response.html);
        }
    });

}

function beutaloFileUpload(el, fid) {
    files = el.files;

    $("#ajaxloaderbeutalo").show();

    //el.event.stopPropagation();
    //el.event.preventDefault();

    var data = new FormData();
    data.append("page", "booking")
    data.append("uploadbeutalofile", fid)
    $.each(files, function (key, value) {
        data.append(key, value);
    });

    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: data,
        cache: false,
        processData: false,
        contentType: false,
        success: function (response, textStatus, jqXHR) {
            $("#ajaxloaderbeutalo").hide();
            $("#idoponteditor").html(response.html);
            if (response.status != "") {
                alert(response.status);
            }
        }, error: function (jqXHR, textStatus, errorThrown) {
            $("#ajaxloaderbeutalo").hide();
            console.log('ERRORS: ' + textStatus);
        }
    });
}

function elojegyzesSearchStart() {
    let key = $("#eljegyzessearchkey").val();
    $("#elojegyzessearchloading").show();
    $("#elojegyzessearchresult").html("");

    $.ajax({
        url: "index.php?page=booking",
        method: "POST",
        data: { searchkey:key },
        success: function (response) {
            $("#elojegyzessearchloading").hide();
            $("#elojegyzessearchresult").html(response);
        }
    });

}

function elojegyzesCegSearchStart() {
    let key = $(".companyselector2").val();
    $("#elojegyzessearchloading").show();
    $("#elojegyzessearchresult").html("");

    $.ajax({
        url: "index.php?page=booking",
        method: "POST",
        data: { searchkey:key, searchkeytype:"company" },
        success: function (response) {
            $("#elojegyzessearchloading").hide();
            $("#elojegyzessearchresult").html(response);
        }
    });

}

function checkChat() {
    $.ajax({
        url: "index.php",
        method: "POST",
        data: { checkChat:1 },
        success: function (response) {
            $("#loggedusers").html(response.users);
            $("#chatbuttoncontainer").html(response.button);
            if (response.number != 0) {
                $.toast({
                    text: response.number + " olvasatlan chat üzenet!<br/><a href='index.php?page=chat'>Elolvas</a>",
                    icon: "success"
                });
            }
        }
    });

}


function openPermissionEditor(key) {
    let div = $("#permissioneditor_" + key);
    if (div.is(":hidden")) {
        $.ajax({
            url: "index.php?page=permissions",
            method: "POST",
            data: {userlist: 1, key: key},
            success: function (response) {
                div.html(response.html);
                div.show();
            }
        });
    } else {
        div.html("");
        div.hide();
    }
}

function savePermissionEditor(key) {
    $.ajax({
        type: "POST",
        url: "index.php?page=permissions",
        data: "savepermissions=1&key=" + key + "&" + $("#userlist_"+key).serialize(),
        success: function (data) {
            let div = $("#permissioneditor_" + key);
            div.html("");
            div.hide();
        }
    });

}


function checkAllPermissionEditor(key, checked) {
    $("#permissioneditor_"+key).find("input:checkbox").prop("checked", checked);
}


function beoSave(oid, beoId) {
    $.ajax({
        type: "POST",
        url: "index.php?page=doctors&szerk="+oid,
        data: "savebeorow=1&"+$("#beorow"+beoId).serialize(),
        success: function (data) {
            $.toast({
                text: "Beosztás mentve",
                icon: 'success'
            });
        }
    });
}

function showEljottLog(fid) {
    $.ajax({
        type: "POST",
        url: "index.php",
        data: "showeljottlog=1&fid="+fid,
        success: function (data) {
            $.confirm({
                title: 'Eljött checkbox napló',
                content: data,
                useBootstrap: false,
                boxWidth: '300px',
                buttons: {
                    nemButton: {
                        text: 'Ok',
                        btnClass: 'btn-blue',
                        action: function(){

                        }
                    }
                }
            });
        }
    });
}

function showManagerStat(num) {
    $("#loadingspinner").show();
    $.ajax({
        type: "POST",
        url: "index.php?page=managerstatus",
        data: "showmanagerstat=1&num="+num,
        success: function (data) {
            $("#managerlista").html(data);
            $("#loadingspinner").hide();
        }
    });
}

function setCegBubble(cid,dokirexcegid,res){
    $.ajax({
        type: "POST",
        url: "index.php?page=companies",
        data: {dxidtocid:true,cid:cid,dokirexcegid:dokirexcegid,res:res},
        success: function (response) {
            if(res==true){
                $(".cegbubble-container").html(response);
            }
           
        }
    });
}

function setDefaultDokirexCegId(cegid){
    $.ajax({
        type: "POST",
        url: "index.php?page=companies",
        data: {setDefaultDokirexCegId:true,cegid:cegid},
        success: function (response) {
            initCeglistSelect2(cegid);
        }
    });
}

function initCeglistSelect2(cegid){
    
    if(cegid){
        $.ajax({
            type: "POST",
            url: "index.php",
            data: {initCeglistSelect2:true,cegid:cegid},
            success: function (response) {
               $(".ceglist").html(response);
               $('.ceglist').select2({
                placeholder: "Válassz céget!",
                minimumInputLength: 3,
                allowClear: true,
                debug:true,
                ajax: {
                    url: 'index.php?getceglist',
                    dataType: 'json'
                    // Additional AJAX parameters go here; see the end of this chapter for the full code of this example
                  }
            });
            }
        }); 
    }else{
        $('.ceglist').select2({
            placeholder: "Válassz céget!",
            minimumInputLength: 3,
            allowClear: true,
            ajax: {
                url: 'index.php?getceglist',
                dataType: 'json'
                // Additional AJAX parameters go here; see the end of this chapter for the full code of this example
              }
        });
    }
   
}

function setMunkakorText(munkakorid){
    $.ajax({
        type:"POST",
        url:"index.php",
        data: {setMunkakorText:munkakorid},
        success: function(response){
            $("#bookingeditormunkakor").val(response);
        }

    })
}

function addNewTopic(el) {
    let text = $("#topictext").val();
    let categoryId = $("#topiccategoryid").val();

    if (categoryId == '0') {
        alert("Válassz kategóriát!");
        return;
    }

    $(el).hide();

    $.ajax({
        url: "index.php?page=hirek",
        method: "POST",
        data: { addtopic:true, categoryid:categoryId, text:text },
        success: function (response) {
            $("#newstable").html(response.html);
            initTopicSearch();
        }
    });
}

function addNewComment(el) {
    let id = $(el).data("id");
    let text = $("#commenttext"+id).val();

    $(el).hide();

    $.ajax({
        url: "index.php?page=hirek",
        method: "POST",
        data: { addcomment:true, id:id, text:text },
        success: function (response) {
            $("#newsitem"+id).html(response.html);
        }
    });
}


function iReadTheNews(el) {
    let id = $(el).data("id");
    $(el).hide();
    $.ajax({
        url: "index.php?page=hirek",
        method: "POST",
        data: { ireadthenews:true, id:id },
        success: function (response) {
            $("#newsitem"+id).html(response.html);
        }
    });
}

function deleteComment(el) {
    if (!confirm("Biztos törlöd a hozzászólást?")) {
        return;
    }

    let id = $(el).data("id");
    let commentId = $(el).data("commentid");

    $.ajax({
        url: "index.php?page=hirek",
        method: "POST",
        data: { deletecomment:true, id:id, commentid:commentId },
        success: function (response) {
            $("#newsitem"+id).html(response.html);
        }
    });
}

function deleteTopic(el) {
    if (!confirm("Biztos törlöd a témát?")) {
        return;
    }

    let id = $(el).data("id");

    $.ajax({
        url: "index.php?page=hirek",
        method: "POST",
        data: { deletetopic:true, id:id },
        success: function (response) {
            $("#newstable").html(response.html);
            initTopicSearch();
        }
    });
}

function setNewsFilter(key) {
    $.ajax({
        url: "index.php?page=hirek",
        method: "POST",
        data: { setnewsfilter:key },
        success: function (response) {
            $("#newstable").html(response.html);
            initTopicSearch();
        }
    });
}

function initTopicSearch() {
    $("#topicsearch").on('keyup', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            $("#newstable").html("keresés...");
            $.ajax({
                url: "index.php?page=hirek",
                method: "POST",
                data: { settopicfilter:$(this).val() },
                success: function (response) {
                    $("#newstable").html(response.html);
                    initTopicSearch();
                }
            });
        }
    });
}

function clearTopicFilter() {
    $("#newstable").html("keresés törlése...");
    $.ajax({
        url: "index.php?page=hirek",
        method: "POST",
        data: { settopicfilter:'' },
        success: function (response) {
            $("#newstable").html(response.html);
            initTopicSearch();
        }
    });
}
function reloadWaitList(){

    $.ajax({
        type:"POST",
        url:"index.php?page=booking",
        data: {reloadWaitList:true},
        success: function(response){
            console.log(response);
            $("#waiting-room").html(response);
        }

    })
}

function reloadWaitListTable(){
    $.ajax({
        type:"POST",
        url:"index.php?page=booking",
        data: {reloadWaitListTable:true},
        success: function(response){
            $("#waitlist-table").html(response);
        }

    })
}

$(document).ready(function () {
    setInterval(function () {
        if (document.querySelector('.dropdown-toggle.show') !== null) {
            //console.log("létezik");
        }else{
            reloadWaitListTable();
        }
        //reloadWaitListTable();
    }, 5000);
});



function callInToVisit(wid){
    $.ajax({
        type:"POST",
        url:"index.php?page=booking",
        dataType:"JSON",
        data: {callInToVisit:wid},
        success: function(response){
            console.table(response);
            $("#waitlist-table").html(response.html);
            if(response.status=="ok"){
                $.toast({
                    text: "Behívás vizsgálatra",
                    icon: 'success'
                });
            }else{
                $.toast({
                    text: response.status,
                    icon: 'error'
                });
            }
        }
    })
}

function addToWaitList(fid,oid){
    $.ajax({
        type:"POST",
        url:"index.php?page=booking",
        dataType:"JSON",
        data: {addToWaitList:fid,oid:oid},
        success: function(response){
            console.table(response);
            $("#waitlist-table").html(response.html);
            if(response.status=="ok"){
                $.toast({
                    text: "Várólistához adva",
                    icon: 'success'
                });
            }
            if(response.status=="already exists"){
                $.toast({
                    text: "Már hozzá lett adva a váróteremhez!",
                    icon: 'error'
                });
            }
        }
    })
}

function returnToWaitingRoom(fid){
    $.ajax({
        type:"POST",
        url:"index.php?page=booking",
        dataType:"JSON",
        data: {returnToWaitingRoom:fid},
        success: function(response){
            $("#waitlist-table").html(response.html);
            if(response.status=="ok"){
                $.toast({
                    text: "Pácines visszakerült a várólistára.",
                    icon: 'success'
                });
            }
        }
    })
}

function finishExamination(fid){
    $.ajax({
        type:"POST",
        url:"index.php?page=booking",
        dataType:"JSON",
        data: {finishExamination:fid},
        success: function(response){
            $("#waitlist-table").html(response.html1);
            $("#finished-exams-table").html(response.html2);
            if(response.status=="ok"){
                $.toast({
                    text: "A vizsgálat lezárásra került.",
                    icon: 'success'
                });
            }
        }
    })
}

function removeFromWaitList(fid){
    $.ajax({
        type:"POST",
        url:"index.php?page=booking",
        dataType:"JSON",
        data: {removeFromWaitList:fid},
        success: function(response){
            console.table(response);
            $("#waitlist-table").html(response.html);
            if(response.status=="ok"){
                $.toast({
                    text: "Várólistáról törölve",
                    icon: 'success'
                });
            }
            /*if(response.status=="already exists"){
                $.toast({
                    text: "Már hozzá lett adva a váróteremhez!",
                    icon: 'error'
                });
            }*/
        }
    })
}

$(document).on("change",".waitlist-relevant-types",function(){
    $.ajax({
        type:"POST",
        url:"index.php?page=booking",
        data: {changeWaitlistRelevantTypes:$(this).val()},
        success: function(response){
            reloadWaitListTable();
            $.toast({
                text: "Beállítás mentve",
                icon: 'success'
            });
        }

    })
});

$(document).on("click",".doctor-card",function(){
    $.ajax({
        type:"POST",
        url:"index.php?page=booking",
        data: {update_wl_data:true,type:$(this).attr("data-object-type"),id:$(this).attr("data-menu-id")},
        success: function(response){
        }

    })
});

$(document).on("change",".waitlist-bound-to-doctor-list",function(){
    $.ajax({
        type:"POST",
        url:"index.php?page=booking",
        data: {changeBoundToDoctor:$(this).val()},
        success: function(response){
            reloadWaitListTable();
            $.toast({
                text: "Beállítás mentve",
                icon: 'success'
            });
        }

    })
});

function webShopOrderAck(id) {
    $.ajax({
        type:"POST",
        url:"index.php?page=booking",
        data: {webshoporderack:id},
        success: function(response){
            $("#webshoplist").html(response);
            $.toast({
                text: "Rendben",
                icon: 'success'
            });
        }

    })
}


function successToast(text) {
    $.toast({
        text: text,
        icon: 'success'
    });
}

function toggleAlkAnswer(el) {
    let id = $(el).data("id");
    let row = $(el).data("row");
    let answer = $(el).data("answer");

    $.ajax({
        type:"POST",
        url:"index.php?page=booking",
        data: {setalkanswer:id, row:row, answer:answer},
        success: function(response){
            $("#alkquestions").html(response);
            $.toast({
                text: "Válasz átállítva",
                icon: 'success'
            });
        }

    })
}

