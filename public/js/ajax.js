$(document).ready(function () {
    $("#paciensfile").on("change", prepareUpload);
    initUploadRoutine();

    $(".addmegjlink").click(function () {
        adds = "(" + $(this).html() + ")";
        $("#foglmegj").val(($("#foglmegj").val() + " " + adds).trim());
    });


    initDateFilterPicker();

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

function manualBookingConfirm(){
    swal({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
      }).then((result) => {
        if (result.isConfirmed) {
          swal(
            'Deleted!',
            'Your file has been deleted.',
            'success'
          )
        }
      })
}


function manualBookingConfirm(orvos){
    swal({
        title: "Időpont egyeztetés szükséges!",
        text: "Az időpontfoglalást kollégánk végzi el, további egyeztetés céljából felfogja venni Önnel a kapcsolatot az itt megadott e-mail cimen keresztül. Kérem, a megjegyzés rovatban adjon meg egy intervallumot, amikor Önnek megfelelő lenne az időpontfoglalás az itt látható naptárt alapul véve.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#b90000',
        cancelButtonColor: '#808080',
        confirmButtonText: 'Rendben',
		cancelButtonText: 'Bezárás'
      }).then((result) => {
        if (result.isConfirmed) {
			$("#datum").css("background-image", "");
            $("#datum").val("Manuális időpont foglalás");
            $("#rinterval").val(15);
            $("#orvosselected").val(orvos);
            animateIdoPontValaszto();
            //$("#warnidopontpress").show();
            return;
        }
      })
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

function showIdoPontValasztoV2(honnan, orvosid) {
    if (orvosid === undefined) {
        orvosid = 0;
    }

    let neme = $('input[name="neme"]:checked', '#iform').val();
    if (neme == undefined) {
        neme = 0;
    }

    $("#loadingspinner").show();

    $.ajax({
        method: "GET",
        url: "index.php",
        data: { showidopontvalasztov2: "1", honnan: honnan, helyszin: $("#helyszin").val(), szurestipus: $("#szurestipus").val(), selectoid: orvosid, neme: neme, taj: $("input[name='taj']").val(), betegallomany: $("#betegallomanynyilatkozat").prop("checked") }
    }).done(function (data) {
        if (data.error != "") {
            myAlert(data.error);
        } else {
            $("#idopontvalasztodiv").html(data.html);
            $("#idopontvalasztodiv").slideDown();
        }
        $("#loadingspinner").hide();
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
    clearSelectedDoctor();
    $("#datum").val("");
    $("#idopontvalasztodiv").html("");
    $("#infopagetext").html("");
    $("#datum").css("background-image", "");
    tappenzCheckRefresh();

    $("#helyszinvalasztowarn").hide();
    $(".datarow").show();
    let placeId = $("#helyszin").val();
    if (placeId == 10) {
        $("#helyszinvalasztowarn").show();
        $(".datarow").hide();
    }
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
    $("#datum").css("background-image", "");
    //$("#tappenzcheck").load("index.php?tappenzcheckrefresh="+tid);
    //showTipusMegj($("#szurestipus").val());
}

function toggleCheckBox(id) {
    var checkbox = $(id);
    checkbox.prop("checked", !checkbox.prop("checked"));
    return;
}

function chooseIdoPont(idopont, rinterval, orvos, helyszin, szurestipusid) {
    if (orvos === undefined) orvos = 0;
    $.ajax({
        method: "POST",
        url: "index.php",
        data: { checkrendeles: "1", idopont: idopont, helyszin: helyszin, taj: $("#tajszam").val(), szurestipusid: szurestipusid, orvos: orvos }
    }).done(function (msg) {
        if (msg == "ok") {
            $("#datum").css("background-image", "");
            $("#datum").val(idopont);
            $("#rinterval").val(rinterval);
            $("#orvosselected").val(orvos);
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
        $("#datum").animate({
            backgroundColor: '#41b6c6',
            color: '#fff'
        }, 100, function () {
            $("#datum").animate({
                backgroundColor: '#fff',
                color: '#555'
            }, 100, function () {
                $("#datum").animate({
                    backgroundColor: '#41b6c6',
                    color: '#fff'
                }, 100, function () {
                    $("#datum").animate({
                        backgroundColor: '#fff',
                        color: '#555'
                    }, 100, function () {
                        $("#datum").css("background-image", "url(images/check.png)");
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

function selectedTipus(tipusId, helyszin) {
    window.location.href='index.php?page=booking&szurestipus='+tipusId+"&helyszin="+helyszin;
}