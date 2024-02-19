$(document).ready(function () {
    $("#paciensfile").on("change", prepareUpload);
    initUploadRoutine();

    $(".addmegjlink").click(function () {
        adds = "(" + $(this).html() + ")";
        $("#foglmegj").val(($("#foglmegj").val() + " " + adds).trim());
    });


    initDateFilterPicker();
    initHMMChat();
    initIrszAutoFill();
    initSubReservationButtons();
});


var respo = "";


function myAlert(szoveg, tipus) {
    tipus = tipus || "info";
    swal({
        title: "",
        text: szoveg,
        confirmButtonColor: "#e34f45",
        confirmButtonText: "OK"
    });
}

function manualBookingConfirm(orvos){
    swal({
        title: "Időpont egyeztetés szükséges!",
        text: "Kedves Ügyfelünk! Az online időpont foglalás technikai okok miatt ebben a rendelőnkben sajnos jelenleg nem működik, ezért elnézését kérjük. Legyen szíves töltse ki a jelentkezési felületet, és a válasz e-mailünkben küldött telefonszámon keresse fel a megadott rendelőt időpont egyeztetés céljából. Megértését köszönjük!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#b90000',
        cancelButtonColor: '#808080',
        confirmButtonText: 'Rendben',
		cancelButtonText: 'Bezárás'
      }).then(function(result) {
        if (result) {
            $("#datum"+datumIndex).val("Időpont egyeztetés");
            $("#datumText"+datumIndex).css("background-image", "");
            $("#datumText"+datumIndex).val("Időpont egyeztetés");
            $("#rinterval"+datumIndex).val(15);
            $("#orvosselected"+datumIndex).val(orvos);
            animateIdoPontValaszto();
            //$("#warnidopontpress").show();
            return;
        }
      });
}

$(document).ready(function () {
    $(".vaccination-question-elements").change(function () {
        checkVaccinationElements();
    });
});

function checkVaccinationElements(){
    let isVaccinated = $('input[name=is-vaccinated]:checked', '#iform').val();

    if(isVaccinated==="1"){
        $("#vaccination-info-vaccine-type").show();
        $("#vaccination-info-first-vaccine").show();
        $("#vaccination-info-second-vaccine").show();
        $("#vaccination-info-third-vaccine").show();
    }else{
        $("#vaccination-info-vaccine-type").hide();
        $("#vaccination-info-first-vaccine").hide();
        $("#vaccination-info-second-vaccine").hide();
        $("#vaccination-info-third-vaccine").hide();
    }
    
}

var datumIndex = "";

function setDatumIndex(i) {
    datumIndex = i;
}

function reservedTimeInvalidate() {
    $("#datum").val("");
    $("#datumText").val("");
    $("#datumText").css("background-image", "");
    silentBookingPost();
}

function getKiegVizsgalatIds() {
    let checkedKieg = "";
    for (let i = 0; i < 10; i++) {
        let id = "#kiegoption"+i;
        if ($(id).length) {
            if ($(id).is(":checked")) {
                if (checkedKieg != "") {
                    checkedKieg += "_";
                }
                checkedKieg += $(id).val();
            }
        }
    }
    return checkedKieg;
}

function getCheckedServices() {
    let checkedServices = "";
    $('.altipuscheck').each(function (index, obj) {
        if (this.checked === true) {
            if (checkedServices != "") {
                checkedServices += "_";
            }
            checkedServices += $(this).attr("name").replace("altipus", "");
        }
    });
    return checkedServices;
}

function getSpecialManagerRequiedData(){
    var data = {};
    //Végig megyek minden input mezőn ami az infopagetext-ben van
    $("#infopagetext").find(":input").each(function(){
        var name = $(this).attr("name");
        var value = $(this).val();
        //Ha checkboxról van szó:
        if($(this).attr("type")=="checkbox"){
            if($(this).is(":checked")){
                value = 1;
            }else{
                return true;
            }
        }
        data[name] = value;
    })
    return data;
}

function showIdoPontValasztoV2(honnan, orvosid) {

    var extraData = getSpecialManagerRequiedData();
    var data = [];

    if (orvosid === undefined) {
        orvosid = 0;
    }

    let neme = $('input[name="neme"]:checked', '#iform').val();
    if (neme == undefined) {
        neme = 0;
    }

    let laborOption = 0;
    if ($("#laboranswerneeded").length) {
        laborOption = $('input[name="labor"]:checked').val();
        if (laborOption == undefined) {
            myAlert("Kérjük válasszon, hogy szüksége van-e labor vizsgálatra!");
            return;
        }
    }
    
    var data = {
        showidopontvalasztov2: "1",
        honnan: honnan,
        helyszin: $("#helyszin").val(),
        szurestipus: $("#szurestipus").val(),
        selectoid: orvosid,
        neme: neme,
        taj: $("input[name='taj']").val(),
        betegallomany: $("#betegallomanynyilatkozat").prop("checked"),
        laborOption:laborOption,
        checkedServices:getCheckedServices(),
        kiegChecked:getKiegVizsgalatIds()
    };

    data = $.extend(data,extraData);

    $("#loadingspinner"+datumIndex).show();

    $.ajax({
        type:"GET",
        url:"index.php",
        dataType:"JSON",
        data: data,
        success: function(data){
            //console.table(data);
            if (data.error != "") {
                myAlert(data.error);
            } else {
                $("#idopontvalasztodiv").html(data.html);
                $("#idopontvalasztodiv").slideDown();
            }
            $("#loadingspinner"+datumIndex).hide();
        }

    });
}

function showIdoPontValasztoV3(honnan, orvosid, szurestipus, helyszin) {
    if (orvosid === undefined) {
        orvosid = 0;
    }

    let neme = $('input[name="neme"]:checked', '#remoteForm').val();
    if (neme == undefined) {
        neme = 0;
    }

    $("#loadingspinner").show();

    //console.log('showidopontvalasztov2=1&honnan='+honnan+'&helyszin='+$("#helyszin").val()+'&szurestipus='+$("#szurestipus").val()+'&selectoid='+orvosid+'&neme='+neme+'&taj='+$("input[name='taj']").val()+'&betegallomany='+$("#betegallomanynyilatkozat").prop("checked"));

    $.ajax({
        method: "GET",
        url: "index.php",
        data: { showidopontvalasztov2: "1", honnan: honnan, helyszin: helyszin, szurestipus: szurestipus, selectoid: orvosid, neme: neme, javascript: "showIdoPontValasztoV3" }
    }).done(function (data) {
        console.log(data);
        if (data.error != "") {
            myAlert(data.error);
        } else {

            $("#idopontvalasztodiv").html(data.html);
            $("#idopontvalasztodiv").slideDown();
        }
        $("#loadingspinner").hide();
    });

}



function nemfog() {
    myAlert("nem foglalható, vagy foglalt időpont!");
}
function nemfogs(tol) {
    myAlert(tol + "-tól csak sorban foglalhatóak az időpontok!");
}
function nemfogs2() {
    myAlert("Ezen a napon csak fordított sorrenben foglalhatók az időpontok!");
}

function clearSelectedDoctor() {
    $.ajax({
        method: "GET",
        url: "index.php",
        data: { page: "booking", clearselecteddoctor: 1 }
    });
}

function tappenzCheckRefresh() {
    $.ajax({
        method: "GET",
        url: "index.php",
        data: { page: "booking", tappenzcheckrefresh: $("#helyszin").val() }
    }).done(function (msg) {
        $("#tappenzcheck").html(msg);
    });
}

function setSzurestipusValaszto(){
    var score = neme = 0;
    var szuldatum = "";
    if($("select[name=\"szuldatumev\"]").val()>0)  score++;
    if($("select[name=\"szuldatumho\"]").val()>0)  score++;
    if($("select[name=\"szuldatumnap\"]").val()>0) score++;
    if($("input[name=\"neme\"]:checked").length>0) score++;
    
    if(score==4){
        szuldatum = $("select[name=\"szuldatumev\"]").val()+"-"+$("select[name=\"szuldatumho\"]").val()+"-"+$("select[name=\"szuldatumnap\"]").val();
        neme = $("input[name=\"neme\"]:checked").val();
       
        $.ajax({
            url: 'index.php',
            type: 'POST',
            dataType:"JSON",
            data: {setSzurestipusValaszto:true,szuldatum:szuldatum,neme:neme},
            success: function (response) {
                if(response.notification!=""){
                    myAlert(response.notification);
                }
                $("#szurestipusvalaszto").html(response.szurestipusValaszto);
                $("#helyszinvalaszto").html(response.helyszinValaszto);
                showInfoPageText(response.id);
            }
        });
    }
    
}

function showInfoPageText(szurestipusid){

    console.log(szurestipusid);
    $.ajax({
        method: "GET",
        url: "index.php",
        data: { page: "booking", getInfoPageText: szurestipusid }
    }).done(function (msg) {
        $("#infopagetext").html(msg);
    });
}

function clearIdopontValaszto() {
    clearIdopontValasztoOnly();
    $("#infopagetext").html("");
    tappenzCheckRefresh();
}


function clearIdopontValasztoOnly() {
    clearSelectedDoctor();
    $("#datum").val("");
    $("#datum1").val("");
    $("#datum2").val("");
    $("#datum3").val("");
    $("#datumText").val("");
    $("#datumText1").val("");
    $("#datumText2").val("");
    $("#datumText3").val("");
    $("#idopontvalasztodiv").html("");
    $("#datumText").css("background-image", "");
    $("#datumText1").css("background-image", "");
    $("#datumText2").css("background-image", "");
    $("#datumText3").css("background-image", "");

    $("#helyszinvalasztowarn").hide();
    $(".datarow").show();
    let placeId = $("#helyszin").val();
    if (placeId == 10) {
        $("#helyszinvalasztowarn").show();
        $(".datarow").hide();
    }
    return true;
}

function onlyUnique(value, index, array) {
    return array.indexOf(value) === index;
}

function preventMultipleServiceSelect(el) {
    let checkedKieg = new Array();
    for (let i = 0; i < 10; i++) {
        let id = "#kiegoption"+i;
        if ($(id).length) {
            if ($(id).is(":checked")) {
                checkedKieg.push($(id).val());
            }
        }
    }

    checkedKieg = checkedKieg.filter(onlyUnique);
    if (checkedKieg.length > 1) {
        myAlert("Egyszerre csak 1 vizsgálathoz lehet időpontot foglalni. Ez alól kivételt képez a laborvizsgálat, amiből egyszerre többet is kijelölhet.");
        $(el).prop('checked', false);
        return;
    }
    clearIdopontValasztoOnly();
}

function showTipusMegj(tipusid) {
    $("#szurestipusmegj").html("");
    $.ajax({
        method: "POST",
        url: "index.php",
        data: { gettipusmegj: "1", tid: tipusid, hid: $("#helyszin").val() }
    }).done(function (msg) {
        if (msg != "") {
            //myAlert(msg);
            $("#szurestipusmegj").html(msg);
            $("#szurestipusmegj").slideDown();
        } else {
            $("#szurestipusmegj").slideUp("fast", function () {
                $("#szurestipusmegj").html("");
            });
        }
    });
}


function clearHelyszinSelector(tid) {
    $("#helyszinvalaszto").load("index.php?page=booking&helyszinrefresh=" + tid);
    $("#szurestipusmegj").html("");
    $("#datumText").css("background-image", "");
    $("#datumText1").css("background-image", "");
    $("#datumText2").css("background-image", "");
    $("#datumText3").css("background-image", "");
    //$("#tappenzcheck").load("index.php?tappenzcheckrefresh="+tid);
    //showTipusMegj($("#szurestipus").val());
}

function toggleCheckBox(id) {
    var checkbox = $(id);
    checkbox.prop("checked", !checkbox.prop("checked"));
    return;
}

var varolista = 0;

function chooseIdoPont(idopont, rinterval, orvos, helyszin, szurestipusid) {
    if (orvos === undefined) orvos = 0;

    let neme = $('input[name="neme"]:checked', '#iform').val();
    if (neme == undefined) {
        neme = 0;
    }

    $.ajax({
        method: "POST",
        url: "index.php",
        data: { checkrendeles: "1", idopont: idopont, helyszin: helyszin, taj: $("#tajszam").val(), neme:neme, szurestipusid: szurestipusid, orvos: orvos }
    }).done(function (msg) {
        if (msg == "ok") {
            $("#datumText"+datumIndex).css("background-image", "");

            if (varolista == 1) {
                $("#datumText"+datumIndex).val("Várólista");
            } else {
                $("#datumText"+datumIndex).val(idopont);
            }

            $("#datum"+datumIndex).val(idopont);
            $("#rinterval"+datumIndex).val(rinterval);
            $("#orvosselected"+datumIndex).val(orvos);
            animateIdoPontValaszto();
            $("#warnidopontpress").show();
            return;
        }
        if(msg == "manual_booking"){
            manualBookingConfirm(orvos);
            return;
        }
        myAlert(msg);
    });
}

function animateIdoPontValaszto() {
    $("#idopontvalasztodiv").slideUp(400, function () {
        $("#datumText"+datumIndex).animate({
            backgroundColor: '#41b6c6',
            color: '#fff'
        }, 100, function () {
            $("#datumText"+datumIndex).animate({
                backgroundColor: '#fff',
                color: '#555'
            }, 100, function () {
                $("#datumText"+datumIndex).animate({
                    backgroundColor: '#41b6c6',
                    color: '#fff'
                }, 100, function () {
                    $("#datumText"+datumIndex).animate({
                        backgroundColor: '#fff',
                        color: '#555'
                    }, 100, function () {
                        $("#datumText"+datumIndex).css("background-image", "url(images/check.png)");
                    });
                });
            });
        });
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



function requestSMSkod(taj, captcha) {
    if (taj == "") {
        myAlert("Kérjük adja meg a TAJ számát!");
        return;
    }
    if (captcha == "") {
        myAlert("Kérjük adja meg a számot!");
        return;
    }

    $("#kodbutton").prop("disabled", true);

    $.ajax({
        method: "POST",
        url: "index.php",
        data: { requestsmskod: "1", taj: taj, captcha: captcha, page: "loginwithtajnumber" }
    }).done(function (msg) {
        $("#kodbutton").prop("disabled", false);

        if (msg == "sentnow" || msg == "sentback") {
            if (msg == "sentnow") myAlert("A bejelentkezéshez szükséges kódot elküldtük a telefonszámára.");
            if (msg == "sentback") myAlert("A bejelentkezéshez szükséges kódot nemrég elküldtük a telefonszámára, kérjük használja azt.");
            $("#kodmezo").show();
            $("#kodkerogomb").hide();
            $("#logingomb").show();
        } else {
            myAlert(msg);
        }

    });

}


function loginTryWithTAJ(taj, kod) {
    if (taj == "") {
        myAlert("Kérjük adja meg a TAJ számát!");
        return;
    }
    if (kod == "") {
        myAlert("Kérjük adja meg a kódot!");
        return;
    }


    $.ajax({
        method: "POST",
        url: "index.php",
        data: { logintrywithtaj: "1", taj: taj, kod: kod, page: "loginwithtajnumber" }
    }).done(function (msg) {
        if (msg == "lejartkod") {
            myAlert("A kapott kód időközben lejárt, kérjen egy újat!");
            window.location.href = "index.php?page=tajlogin";
            return;
        }
        if (msg == "ok") {
            window.location.href = "index.php";
        } else {
            myAlert(msg);
        }

    });

}


function addUserBeutalo() {
    var beutalotarget = $("#beutalotarget").val();
    var naploszam = $("#naploszam").val();
    var beutalomegj = $("#beutalomegj").val();

    if (beutalotarget == "0") {
        myAlert("Nem adta meg, hogy hova szól a beutalója!");
        return;
    }

    if (naploszam == "" && !confirm("Biztos benne, hogy naplószám nélkül adja meg a beutalót?")) {
        return;
    }
    if (beutalomegj == "" && !confirm("Biztos benne, hogy megjegyzés nélkül adja meg a beutalót?")) {
        return;
    }
    document.iform.submit();
}



var files;


// Grab the files and set them to our variable
function prepareUpload(event) {
    files = event.target.files;

    $("#paciensloader").show();

    event.stopPropagation();
    event.preventDefault();


    var data = new FormData();
    $.each(files, function (key, value) {
        data.append(key, value);
    });

    $.ajax({
        url: 'index.php?page=booking&addpaciensfiles',
        type: 'POST',
        data: data,
        cache: false,
        processData: false, // Don't process the files
        contentType: false, // Set content type to false as jQuery will tell the server its a query string request
        success: function (response, textStatus, jqXHR) {
            $("#paciensfilediv").load("index.php?page=booking&showpaciensfiles");
            $("#paciensloader").hide();

            if (response != "") {
                myAlert(response);
                return;
            }
        }, error: function (jqXHR, textStatus, errorThrown) {
            $("#paciensloader").hide();
            console.log('ERRORS: ' + textStatus);
        }
    });

}

function initUploadRoutine() {
    $(".assetphotofile").on("change", preparePhotoUpload);
}

function preparePhotoUpload(event) {
    let tipus = $(this).data("tipus");
    let id = $(this).data("id");

    files = event.target.files;

    $("#ajaxloader_"+tipus+"_"+id).show();

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
            $("#ajaxloader_"+tipus+"_"+id).hide();

            $("#asseteditor").html(response.html);
            $("#asseteditor_"+tipus).html(response.html);
            $("#asseteditor"+id).html(response.html);
            initUploadRoutine();

            if (response.error != "") {
                alert(response.error);
                return;
            }
        }, error: function (jqXHR, textStatus, errorThrown) {
            $("#ajaxloader_"+tipus+"_"+id).hide();
            console.log('ERRORS: ' + textStatus);
        }
    });
}

function deleteAsset(tipus, id, assetId) {
    if (!confirm("Biztos törli ezt a képet?")) {
        return;
    }

    $.ajax({
        type: 'POST',
        url: 'index.php',
        data: { deleteasset: id, tipus: tipus },
        success: function (result) {
            $("#asseteditor").html(result.html);
            $("#asseteditor_"+tipus).html(result.html);
            $("#asseteditor"+assetId).html(result.html);
            initUploadRoutine();
        }
    });
}


function deletePaciensFile(id, k) {
    $.ajax({
        method: "POST",
        url: "index.php",
        data: { deletepaciensfile: "1", id: id, k: k, page: "booking" }
    }).done(function (msg) {
        $("#paciensfilediv").html(msg);
    });
}

function open_lelet(id) {
    $('.target-lelet').load('index.php?load_lelet=' + id);
    setTimeout(function () {
        $('.target-lelet').slideToggle();
    }, 800);
}
function open_zaro(id) {
    $('.target-lelet').load('index.php?load_zaro=' + id);
    setTimeout(function () {
        $('.target-lelet').slideToggle();
    }, 800);
}

function printLelet() {
    var objToPrint = document.getElementById('lelet-content');

    var newWin = window.open('', 'Print-Window');

    newWin.document.open();

    newWin.document.write('<html><body onload="window.print()">' + objToPrint.innerHTML + '</body></html>');

    newWin.document.close();

    setTimeout(function () { newWin.close(); }, 10);
}


function recaptchaCallback() {
    $('button[name="finish"]').data('status', true);
};
// jquery extend function
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
            console.log(data);
            if (version == 1) {
                var str = data.split('|');
                var $text01 = 'Kedv.:' + str[2];

                $('#coupontitle').text(str[0]);
                $('#coupondesc').css('color', '#12c915').text(str[1]);
                $('#coupondiscount').css('color', '#444;').text($text01);
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

function setQuestions(orvosid, szurestipus) {
    $.ajax({
        type: 'post',
        url: 'index.php?page=remotebooking',
        data: { setQuestions: true, orvosid: orvosid, szurestipus: szurestipus },
        success: function (data) {
            $('#questions').html(data.questions);
            $('#idopontvalasztodiv').html('');
            if (data.reservationstatus == 0) {
                $('#idopontvalasztotr').html('<td>Időpont:* </td><td id="idopontvalasztotd"></td>');
                $('#idopontvalasztotd').html(data.bookingselector);
                $('#datum').val('');
            }
            else $('#idopontvalasztotr').html('');
        }
    });
}


function extendedReservationSelect(t, h, r) {
    if (r == 1) var page = "remoteBooking";
    else var page = "booking";
    $.redirectPost("index.php?page=" + page, { szurestipus: t, helyszin: h });
}

function changeServicePaymentMethod(sid, method) {
    $.ajax({
        type: "GET",
        url: "?page=services",
        data: { changeServiceMethod: true, sid: sid, method: method },
        success: function (response) {
            if (response == null) {
                return;
            }
            else {
                $("#sid-" + sid + " td:nth-child(4)").text(response);
            }
        }
    })
}

function openDescription(id) {
    var box = $("#sid-" + id + "-description");
    if (typeof box.data("height") === "undefined") {
        var height = (box.outerHeight() + 19);
        box.data("height", height);
    } else {
        var height = box.outerHeight();
    }

    console.log(height);


    box.css({ "height": height });
    box.slideToggle("slow");

}

function startServiceOrderPay(fizId) {
    $.ajax({
        type: "POST",
        url: "?page=services",
        data: { startServiceOrderPay: true, fizId: fizId },
        success: function (response) {
            if (response == "") {
                window.location.href = "index.php?page=services&startpay="+fizId
                return;
            } else {
                alert(response);
            }
        }
    })
}

function initDateFilterPicker() {
    $('#napfilter').datepicker({
        language: 'hu',
        onSelect: function (formattedDate, date, inst) {
            inst.hide();
            //setListDay(formattedDate);
            //window.location.href="index.php?page="+$("#napfilter").data("page")+"&setday="+formattedDate;
        }
    })
}

function initModifyFilterPicker(extraId) {
    $("input[name='vaccine-date"+extraId+"'").datepicker({
        language: 'hu',
        onSelect: function (formattedDate, date, inst) {
            inst.hide();
            //setListDay(formattedDate);
            //window.location.href="index.php?page="+$("#napfilter").data("page")+"&setday="+formattedDate;
        }
    })
}


function modify_covid_data(cid){

    $.ajax({
        type: "POST",
        url: "?page=covidoltasnaplo",
        data: { modify_covid_data: true, covId: cid },
        success: function (response) {
            if(response!=""){
                $("#covid-data-id-"+cid).html(response);
                extraId = "-"+cid;
                initModifyFilterPicker(extraId);
            }
        }
    })
}


function cancel_covid_data(cid){

    $.ajax({
        type: "POST",
        url: "?page=covidoltasnaplo",
        data: { cancel_covid_data: true, covId: cid },
        success: function (response) {
            if(response!=""){
                $("#covid-data-id-"+cid).html(response);
            }
        }
    })
}

function save_covid_data(cid){
    $.ajax({
        type: "POST",
        url: "?page=covidoltasnaplo",
        data: { save_covid_data: true, covId: cid,oltas_tipus:$("select[name='vaccination-type-"+cid+"']").children("option:selected").val(),oltas_datum:$("input[name='vaccine-date-"+cid+"']").val(),sorszam:$("input[name='serial-number-"+cid+"']").val()},
        success: function (response) {
            if(response!=""){
                $("#covid-data-id-"+cid).html(response);
            }
        }
    })
}
function delete_covid_data(cid){
    if (!confirm("Biztos törli ezt az oltási eseményt?")) {
        return;
    }

    $.ajax({
        type: "POST",
        url: "?page=covidoltasnaplo",
        data: { delete_covid_data: true, covId: cid},
        success: function (response) {
            $("#covid-data-id-"+cid).remove();
        }
    })
}


function covidFormCheckboxCheck(el) {
    let id = $(el).attr("id");

    let nocovid1 = $("#nocovid1").prop("checked");
    let nocovid2 = $("#nocovid1").prop("checked");


    if (id == "nocovid1" && nocovid1) {
        $( "#nocovid2" ).prop("checked", false);
    }

    if (id == "nocovid2" && nocovid2) {
        $( "#nocovid1" ).prop("checked", false);
    }

    nocovid1 = $("#nocovid1").prop("checked");
    nocovid2 = $("#nocovid2").prop("checked");

    if (nocovid1) {
        $("#igazolasuploaddiv").slideDown();
    } else {
        $("#igazolasuploaddiv").slideUp();
    }

    if (nocovid2) {
        $("#covidnyilatkozatdiv").slideDown();
    } else {
        $("#covidnyilatkozatdiv").slideUp();
    }


}

function silentBookingPost() {
    var formid = $("#iform");
    $("#silentmode").val(1);
    $(formid).submit();
}


function selectedTipus(tipusId, helyszin) {
    window.location.href='index.php?page=booking&szurestipus='+tipusId+"&helyszin="+helyszin;
}

function uniqaServiceCheck(){
    if($("#szurestipus").val()==0){
        swal({
            title: "Kedves Kolléga!",
            text: "A továbblépéshez kérlek, válassz egy szűréstípust!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#b90000',
            cancelButtonColor: '#808080',
            confirmButtonText: 'Értem',
            cancelButtonText: 'Bezárás'
          }).then(function(result) {
            if (result) {
                $("input[name='email']").blur();
            }
          });
    }
}

function uniqaEmailCheck(email){
    if($("#szurestipus").val()!=0){
        $.ajax({
            type: "POST",
            url: "?page=booking",
            data: { uniqaEmailCheck: true, email: email, szurestipus: $("#szurestipus").val()},
            success: function (response) {

              if(response.companyEmail==false){
                swal({
                    title: "Kedves Kolléga!",
                    text: "Kérlek, a céges e-mail címedet add meg az időpontfoglaláshoz!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#b90000',
                    cancelButtonColor: '#808080',
                    confirmButtonText: 'Értem',
                    cancelButtonText: 'Bezárás'
                  }).then(function(result) {
                    if (result) {
                        return;
                    }
                  });
              }
              
              if(response.blacklistScenario==true && response.isFree==true){
                swal({
                    title: "Kedves Kolléga!",
                    text: "Mivel az áprilisi Egészségnap alkalmával már részt vettél ingyenes vizsgálaton, ezért most erre nincs lehetőséged. Térítés ellenében vérvételre vagy hasi Ultranhang vizsgálatra tudsz regisztrálni. Megértésedet és együttműködésedet köszönjük!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#b90000',
                    cancelButtonColor: '#808080',
                    confirmButtonText: 'Értem',
                    cancelButtonText: 'Bezárás'
                  }).then(function(result) {
                    if (result) {
                        return;
                    }
                  });
              }
              if(response.alreadyBookedForFreeScenario==true && response.isFree==true){
                swal({
                    title: "Kedves Kolléga!",
                    text: "Egy ingyenes szűrővizsgálati lehetőséget tudunk számodra biztosítani, melyre már regisztráltál. Térítés ellenében vérvételre vagy hasi Ultranhang vizsgálatra tudsz még jelentkezni. Megértésedet és együttműködésedet köszönjük!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#b90000',
                    cancelButtonColor: '#808080',
                    confirmButtonText: 'Értem',
                    cancelButtonText: 'Bezárás'
                  }).then(function(result) {
                    if (result) {
                        return;
                    }
                  });
              }
            }
        });    
    }
}


function initHMMChat() {
    if ($('#hmmchat').length) {
        initChat($("#hmmchat"));
    }
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
}

function initSubReservationButtons() {
    $(".subreservationopenbutton").click(function () {
        let reservationTypeId = $(this).data("reservationtypeid");

        $("#reservationContainer" + reservationTypeId).slideToggle();

        $.ajax({
            method: 'POST',
            url: '/index.php',
            data: "displaySlots=1&reservationTypeId=" + reservationTypeId
        }).done(function (data) {
            $("#reservationContainer" + reservationTypeId).html(data);
            bindIdopontButtons();
        });

        return false;
    });
}


function bindIdopontButtons() {
    $(".freesubidopontbutton").click(function() {
        let cartRow = $(this).data("cartrow");
        let num = $(this).data("num");
        let reservationTypeId = $(this).data("reservationtypeid");
        let doctorId = $(this).data("doctorid");
        let length = $(this).data("length");
        let time = $(this).data("time");
        let timeExists = $(this).data("timeexists");
        let mainServiceId = $("#szurestipus").val();

        if (timeExists === 1) {
            myAlert("Ezt az időpontot már kiválasztottad!");
            return false;
        }

        $("#reservationContainer"+reservationTypeId).slideToggle();

        $.ajax({
            method:'POST',
            url:'/index.php',
            data: "selectSubTime=1&reservationTypeId="+reservationTypeId+"&cartRow="+cartRow+"&num="+num+"&doctorId="+doctorId+"&length="+length+"&time="+time+"&mainServiceId="+mainServiceId
        }).done(function(data){
            $("#infopagetext").html(data);
            initSubReservationButtons();
        });

        return false;
    });
}

