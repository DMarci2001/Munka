$(document).ready(function() {
    $("#paciensfile").on("change", prepareUpload);


    $(".addmegjlink").click(function() {
        adds="("+$(this).html()+")";
        $("#foglmegj").val(($("#foglmegj").val()+" "+adds).trim());
    });

});


var respo="";


function myAlert(szoveg,tipus) {
    tipus = tipus || "info";
    swal({
        title: "",
        text: szoveg,
        confirmButtonColor: "#e34f45",
        confirmButtonText: "OK"
    });
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
	
	console.log('showidopontvalasztov2=1&honnan='+honnan+'&helyszin='+$("#helyszin").val()+'&szurestipus='+$("#szurestipus").val()+'&selectoid='+orvosid+'&neme='+neme+'&taj='+$("input[name='taj']").val())
	
    $.ajax({
        method:"GET",
        url:"index.php",
        data:{ showidopontvalasztov2:"1", honnan:honnan, helyszin:$("#helyszin").val(), szurestipus:$("#szurestipus").val(), selectoid:orvosid, neme:neme, taj:$("input[name='taj']").val() }
    }).done(function(data) {
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
    myAlert(tol+"-tól csak sorban foglalhatóak az időpontok!");
}
function nemfogs2() {
    myAlert("Ezen a napon csak fordított sorrenben foglalhatók az időpontok!");
}

function clearIdopontValaszto() {
    $("#datum").val("");
    $("#idopontvalasztodiv").html("");
    $("#datum").css("background-image","");
}

function showTipusMegj(tipusid) {
    $("#szurestipusmegj").html("");
    $.ajax({
        method:"POST",
        url:"index.php",
        data:{ gettipusmegj:"1", tid:tipusid, hid:$("#helyszin").val() }
    }).done(function(msg) {
        if (msg!="") {
            //myAlert(msg);
            $("#szurestipusmegj").html(msg);
            $("#szurestipusmegj").slideDown();
        } else {
            $("#szurestipusmegj").slideUp("fast",function() {
                $("#szurestipusmegj").html("");
            });
        }
    });
}


function clearSzuresTipus(hid) {
    $("#szurestipusvalaszto").load("index.php?szurestipusrefresh="+hid);
    $("#szurestipusmegj").html("");
    $("#datum").css("background-image","");
    //showTipusMegj($("#szurestipus").val());
}

function chooseIdoPont(idopont,orvos) {
    let rinterval = $("#rinterval-"+idopont.substring(0,10)).val();
    if (orvos === undefined) orvos = 0;
    $.ajax({
        method:"POST",
        url:"index.php",
        data:{ checkrendeles:"1", idopont:idopont, helyszin:$("#helyszin").val(), taj:$("#tajszam").val(), szurestipusid:$("#szurestipus").val(),orvos:orvos }
    }).done(function(msg) {
        if (msg=="ok") {
            $("#datum").css("background-image","");
            $("#datum").val(idopont);
            $("#rinterval").val(rinterval);
            $("#orvosselected").val(orvos);
            animateIdoPontValaszto();
            $("#warnidopontpress").show();
            return;
        }
        myAlert(msg);
    });
}

function animateIdoPontValaszto() {
    $("#idopontvalasztodiv").slideUp(400, function() {
        $("#datum").animate({
            backgroundColor: '#41b6c6',
            color: '#fff'
        }, 100, function() {
            $("#datum").animate({
                backgroundColor: '#fff',
                color: '#555'
            }, 100, function() {
                $("#datum").animate({
                    backgroundColor: '#41b6c6',
                    color: '#fff'
                }, 100, function() {
                    $("#datum").animate({
                        backgroundColor: '#fff',
                        color: '#555'
                    }, 100, function() {
                        $("#datum").css("background-image","url(images/check.png)");
                    });
                });
            });
        });
    });
}

var actualprefix=0

function showftable(prefix) {
    actualprefix=prefix;
    $("#foglalotable").load('index.php?showfoglalotable='+prefix);
}


var actualtol="";
var actualig="";
var lastobj;

function showfoglalas(tol,ig,obj) {
    if (lastobj) lastobj.style.borderColor="#888";
    if (obj) {
        lastobj=obj;
        obj.style.borderColor="#000";
    }

    actualtol=tol;
    actualig=ig
    $("#foglalaslista").load('index.php?showfoglalas='+encodeURIComponent(tol)+'_'+encodeURIComponent(ig));
}

function lefoglal(time) {
    $("#foglalaslista").load('index.php?lefoglal='+encodeURIComponent(time),null,
        function(responseText){
            $("#foglalotable").load('index.php?showfoglalotable='+actualprefix);
        });
}
function lefoglalnap(datum) {
    $("#foglalaslista").load('index.php?lefoglalnap='+encodeURIComponent(datum),null,
        function(responseText){
            $("#foglalotable").load('index.php?showfoglalotable='+actualprefix);
        });
}
function deletefoglalas(id) {
    $("#foglalaslista").load('index.php?deletefoglalas='+encodeURIComponent(id),null,
        function(responseText){
            $("#foglalaslista").load('index.php?showfoglalas='+encodeURIComponent(actualtol)+'_'+encodeURIComponent(actualig));
            $("#foglalotable").load('index.php?showfoglalotable='+actualprefix);
        });
}

function szerkfoglalas(id) {
    $("#fszerk"+id).load('index.php?szerkfoglalas='+id);
}

function closefoglalasszerk(id) {
    $("#fszerk"+id).html('');
}

function savefoglalas(id,nap,ora,fo) {
    $("#foglalaslista").load("index.php?savefoglalas="+encodeURIComponent(id)+"_"+encodeURIComponent(nap)+"_"+encodeURIComponent(ora)+"_"+encodeURIComponent(fo),null,
        function(responseText){
            $("#foglalaslista").load('index.php?showfoglalas='+encodeURIComponent(actualtol)+'_'+encodeURIComponent(actualig));
            $("#foglalotable").load('index.php?showfoglalotable='+actualprefix);
        });
}

function setlastminute(id,val) {
    $("#foglalaslista").load("index.php?setlastminute="+encodeURIComponent(id)+"_"+encodeURIComponent(val),null,
        function(responseText){
            $("#foglalaslista").load('index.php?showfoglalas='+encodeURIComponent(actualtol)+'_'+encodeURIComponent(actualig));
            $("#foglalotable").load('index.php?showfoglalotable='+actualprefix);
        });
}
function settiltva(id,val) {
    $("#foglalaslista").load("index.php?settiltva="+encodeURIComponent(id)+"_"+encodeURIComponent(val),null,
        function(responseText){
            $("#foglalaslista").load('index.php?showfoglalas='+encodeURIComponent(actualtol)+'_'+encodeURIComponent(actualig));
            $("#foglalotable").load('index.php?showfoglalotable='+actualprefix);
        });
}
function fizetve(id) {
    $("#foglalaslista").load("index.php?fizetve="+encodeURIComponent(id),null,
        function(responseText){
            $("#foglalaslista").load('index.php?showfoglalas='+encodeURIComponent(actualtol)+'_'+encodeURIComponent(actualig));
            $("#foglalotable").load('index.php?showfoglalotable='+actualprefix);
        });
}


function rendelesdetail(id) {
    if ($("#rendelesdetail"+id).is(':empty')) {
        $("#rendelesdetail"+id).load("index.php?rendelesdetail="+encodeURIComponent(id));
    } else {
        $("#rendelesdetail"+id).empty();
    }
}



function requestSMSkod(taj,captcha) {
    if (taj=="") {
        myAlert("Kérjük adja meg a TAJ számát!");
        return;
    }
    if (captcha=="") {
        myAlert("Kérjük adja meg a számot!");
        return;
    }

    $("#kodbutton").prop("disabled",true);

    $.ajax({
        method:"POST",
        url:"index.php",
        data:{ requestsmskod:"1", taj:taj, captcha:captcha, page:"loginwithtajnumber" }
    }).done(function(msg) {
        $("#kodbutton").prop("disabled",false);

        if (msg=="sentnow" || msg=="sentback") {
            if (msg=="sentnow") myAlert("A bejelentkezéshez szükséges kódot elküldtük a telefonszámára.");
            if (msg=="sentback") myAlert("A bejelentkezéshez szükséges kódot nemrég elküldtük a telefonszámára, kérjük használja azt.");
            $("#kodmezo").show();
            $("#kodkerogomb").hide();
            $("#logingomb").show();
        } else {
            myAlert(msg);
        }

    });

}


function loginTryWithTAJ(taj,kod) {
    if (taj=="") {
        myAlert("Kérjük adja meg a TAJ számát!");
        return;
    }
    if (kod=="") {
        myAlert("Kérjük adja meg a kódot!");
        return;
    }


    $.ajax({
        method:"POST",
        url:"index.php",
        data:{ logintrywithtaj:"1", taj:taj, kod:kod, page:"loginwithtajnumber" }
    }).done(function(msg) {
        if (msg=="lejartkod") {
            myAlert("A kapott kód időközben lejárt, kérjen egy újat!");
            window.location.href="index.php?page=tajlogin";
            return;
        }
        if (msg=="ok") {
            window.location.href="index.php";
        } else {
            myAlert(msg);
        }

    });

}


function addUserBeutalo() {
    var beutalotarget=$("#beutalotarget").val();
    var naploszam=$("#naploszam").val();
    var beutalomegj=$("#beutalomegj").val();

    if (beutalotarget=="0") {
        myAlert("Nem adta meg, hogy hova szól a beutalója!");
        return;
    }

    if (naploszam=="" && !confirm("Biztos benne, hogy naplószám nélkül adja meg a beutalót?")) {
        return;
    }
    if (beutalomegj=="" && !confirm("Biztos benne, hogy megjegyzés nélkül adja meg a beutalót?")) {
        return;
    }
    document.iform.submit();
}



var files;


// Grab the files and set them to our variable
function prepareUpload(event) {
    files=event.target.files;

    $("#paciensloader").show();

    event.stopPropagation();
    event.preventDefault();


    var data = new FormData();
    $.each(files, function(key,value) {
        data.append(key,value);
    });

    $.ajax({
        url: 'index.php?page=booking&addpaciensfiles',
        type: 'POST',
        data: data,
        cache: false,
        processData: false, // Don't process the files
        contentType: false, // Set content type to false as jQuery will tell the server its a query string request
        success: function(response,textStatus,jqXHR) {
            $("#paciensfilediv").load("index.php?page=booking&showpaciensfiles");
            $("#paciensloader").hide();

            if (response!="") {
                myAlert(response);
                return;
            }
        }, error: function(jqXHR, textStatus, errorThrown) {
            $("#paciensloader").hide();
            console.log('ERRORS: '+textStatus);
        }
    });

}

function deletePaciensFile(id,k) {
    $.ajax({
        method:"POST",
        url:"index.php",
        data:{ deletepaciensfile:"1", id:id, k:k, page:"booking" }
    }).done(function(msg) {
        $("#paciensfilediv").html(msg);
    });
}

function open_lelet(id){
    $('.target-lelet').load('index.php?load_lelet=' + id);
    setTimeout(function() {
        $('.target-lelet').slideToggle();
    }, 800);
}
function open_zaro(id){
    $('.target-lelet').load('index.php?load_zaro=' + id);
    setTimeout(function() {
        $('.target-lelet').slideToggle();
    }, 800);
}

function printLelet() {
    var objToPrint=document.getElementById('lelet-content');

    var newWin=window.open('','Print-Window');

    newWin.document.open();

    newWin.document.write('<html><body onload="window.print()">'+objToPrint.innerHTML+'</body></html>');

    newWin.document.close();

    setTimeout(function(){newWin.close();},10);
}


function recaptchaCallback() {
    $('button[name="finish"]').data('status',true);
};
// jquery extend function
$.extend(
    {
        redirectPost: function(loc, args)
        {
            var form = '';
            $.each( args, function( key, value ) {
                form += '<input type = "hidden" name = "'+key+'" value = "'+value+'">';
            });
            $('<form action = "'+loc+'" method = "POST">'+form+'</form>').appendTo('body').submit();
        }
    });


function kuponCheck(coupon,version,foglalas,szurestipus)
{
    console.log(foglalas);
    $.ajax({
        method:'POST',
        url:'index.php',
        data:{ kuponCheck:'1',
            coupon:coupon,
            version:version,
            foglalas:foglalas,
            szurestipus:szurestipus
        }
    }).done(function(data){
        if(data == 'error01')
        {
            $('#coupondesc').css('color','red').text('Érvénytelen kupon!');
        }
        if(data == 'error02')
        {
            $('#coupondesc').css('color','red').text('A kupont már felhasználták!');
        }
        if(data == 'error03'){
            $('#coupondesc').css('color','red').text('Erre a vizsgálatra nem lehet felhasználni!');
        }
        if(data != 'error01' && data != 'error02' && data != 'error03')
        {
            console.log(data);
            if(version == 1) {
                var str = data.split('|');
                var	$text01 = 'Kedv.:'+str[2];

                $('#coupontitle').text(str[0]);
                $('#coupondesc').css('color','#12c915').text(str[1]);
                $('#coupondiscount').css('color','#444;').text($text01);
            }
            if(version == 2) {
                var str = data.split('|');
                var $text01 = str[0];
                $text02 = 'Kedv.:'+str[2];
                $('#coupondesc').css('color','#444;').text($text01);
                $('#coupondiscount').css('color','#444;').text($text02);
            }

        }
    });
}


function extendedReservationSelect(t, h) {
    $.redirectPost("index.php?page=booking", { szurestipus: t, helyszin: h });
}