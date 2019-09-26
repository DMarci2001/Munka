var rendelestimer=10;


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

    $.ajax({
        method:"GET",
        url:"index.php",
        data:{ showidopontvalasztov2:"1", honnan:honnan, helyszin:$("#helyszin").val(), szurestipus:$("#szurestipus").val(), selectoid:orvosid, neme:neme }
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

function rendelesreszlet(oid) {
    if ($("#rendelesreszlet"+oid).is(":visible")) {
        $("#rendelesreszlet"+oid).hide();
    } else {
        $("#rendelesreszlet"+oid).show();
        $("#rendelesreszlet"+oid).load("index.php?rendelesreszlet="+oid);
        $("#rendelesfej"+oid).attr('class', 'rendeles_megnezett');
    }
}

//self.setInterval("rtimer()",1000);


function rtimer() {
    rendelestimer--;
    if (rendelestimer<0) {
        rendelestimer=60;
        ujrendeles_lekerdezes();
    }
    $("#szamlalo").html(rendelestimer);
}


function ujrendeles_lekerdezes() {
    $("#querystatus").html("lekérdezés folyamatban...");

    $("#querystatus").load("index.php?checkneworder",null,

        function(responseText){

            if (responseText!="0") {
                $("#statussor").html(responseText+" rendelés érkezett!");
                $("#querystatus").html("");
                $("#statussor").show();
                //playSound();
                //myAlert("Response:\n" + responseText);
            }
        });

}


function playSound() {
    var flashvars = {};
    var params = {};
    params.wmode="transparent";
    params.align="right";
    var attributes = {};
    swfobject.embedSWF("hangflash.swf", "sound", "1", "1", "8.0.0","swf/expressInstall.swf",flashvars, params, attributes);
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

//Új időpontfoglaló jquery-je:
function showIdoPontValasztoV4(type,honnan,stat,orvosid) {
    if (orvosid === undefined) orvosid = 0;
    if($('.obj-container-framebox').css('display') == 'block' && stat == 0) return false;
    if (type == '0') {
        myAlert('Az időpont kiválasztásához válassza ki a helyszínt!');
        return;
    }

    $.ajax({
        method:'GET',
        url:'index.php',
        data:{ showidopontvalasztov5:'1',
            honnan:honnan,
            helyszin:'1',
            szurestipus:type,
            selectoid:orvosid
        }
    }).done(function(data){
        var content  = '<div class = "ocf-control-panel">';
        content += '<span style = "float:left;font-size:16px;padding:5px 5px 5px 15px;">Időpont választás</span>';
        content += '<i style = "float:right;" id = "close-window" class="fas fa-times"></i></div>';
        content += '<div class = "ocf-scroll-box" >' + data + '</div>';
        $('.obj-container-framebox').html(content);
        if(stat == 0) $('.obj-container-framebox').slideToggle();
    });
}
$(document).on('click','#close-window', function (){
    $('.obj-container-framebox').slideToggle(function (){
        $('.obj-container-framebox').empty();
    });
});

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

function chooseIdoPontV1( idopont,szurestipus,helyszin,orvos ) {
    if (orvos === undefined) orvos = 0;
    $.ajax({
        method: 'POST',
        URL: 'index.php',
        data: { checkrendeles: '1',
            idopont: idopont,
            szurestipusid: szurestipus,
            helyszin: helyszin,
            orvos: orvos,
            version: '2'
        }
    }).done( function( msg ) {
        console.log(msg);
        var redirect = 'index.php?page=idopontfoglalas';

        if ( msg == 'ok' ) {
            $('#datum').val(idopont);
            $("#orvosselected").val(orvos);
            $('#idopontvalasztodiv').slideUp();
            $('#warnidopontpress').show();
            return;
        }

        if( msg == 'ok2' ) {
            $('.obj-container-framebox').slideUp();
            $('input[name="datum"]').val(idopont);
            $("#orvosselected").val(orvos);
            $('#current-datetime').val(idopont);
            $('#current-type').val(szurestipus);
            return;
        }
        if( msg== 'ok3' ) {
            $("#orvosselected").val(orvos);
            $('.obj-container-framebox').slideUp();
            $.redirectPost(redirect, { tipus: $('#szurestipus').val(), idopont: idopont });
            return;
        }
        myAlert(msg);
    });
}

function fieldCheck( name, val, errorType, charSet, lngth ){
    if(charSet === undefined) charSet = null;
    if(lngth === undefined) lngth = null;
    var selectList = ['szuldatumev', 'szuldatumho', 'szuldatumnap'];
    if( $.inArray( name, selectList ) !== -1){
        var $target = $('select[name="' + name + '"]');
        var name    = 'szuldatum';
    }
    else var $target = $('input[name="' + name + '"]');

    //var grantClr = '#76f200';
    var grantClr = '#B8B6B6';
    var blockClr = '#ff0000';
    var errorCnt = 0;

    if( charSet == '0' ) var available = /^[0-9]+$/;						//Csak szám lehet a beviteli adat.
    if( charSet == '1' ) var available = /^[A-Za-z\sÁÉÓŐÖÚŰÜÍáéóőöúűüí]+$/; //Csak szöveg lehet a beviteli adat.
    if( charSet == '2' ) var available = /^[A-Za-z0-9\sÁÉÓŐÖÚŰÜÍáéóőöúűüí]+$/; //szöveg és szám egyaránt lehet a beviteli adat.
    if( charSet == '3' ) var available = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/; //Email ellenőzrés.
    if( charSet == '4' ) var available = /^06([0-9]){8,9}$/; 				   //Telefon szám ellenőrzés.

    if( errorType == '0' ) var errorTxt = '*Adja meg a teljes nevét!';
    if( errorType == '1' ) var errorTxt = '*Adja meg a születési dátumát!';
    if( errorType == '2' ) var errorTxt = '*Adja meg az e-mail címét!';
    if( errorType == '3' ) var errorTxt = '*Adja meg a TAJ számát!';
    if( errorType == '4' ) var errorTxt = '*A telefonszám mindenképp 06-al kezdődjön!';
    if( errorType == '5' ) var errorTxt = '*Adja meg a születési dátumát!';
    if( errorType == '6' ) var errorTxt = '*Adja meg a születési dátumát!';
    if( errorType == '7' ) var errorTxt = '*Adja meg a születési dátumát!';
    if( errorType == '8' ) var errorTxt = '*Válassz időpontot!';
    if( errorType == '9' ) var errorTxt = '*Adja meg a nevét!';
    if( errorType == '10' ) var errorTxt = '*Adja meg a telefonszámát!';

    if( charSet != null ){

        if((available.test( val ) && ( lngth != null && val.length >= lngth )) || available.test( val ) && lngth == null ){
            $target.css( 'border', '1px solid ' + grantClr );
            $target.data( 'status', 'confirmed' );
            $( '#' + name + '-error').html('');

        }
        else{
            $target.css( 'border', '1px solid ' + blockClr );
            $target.data( 'status', 'error' );
            $( '#' + name + '-error').html(errorTxt);
            errorCnt++;
            if(name == 'nev') console.log(val);
        }
    }
    else{
        if( ( !$.isNumeric( val ) && val != '') || ( $.isNumeric( val ) && val != 0 )){
            $target.css( 'border', '1px solid ' + grantClr );
            $target.data( 'status', 'confirmed' );
            $( '#' + name +'-checkbox' ).html( '<span style="color: ' + grantClr + '"><i class="fa fa-check"></span>' );
            $( '#' + name + '-error').html('');
        }
        else{
            $target.css( 'border', '1px solid ' + blockClr );
            $target.data( 'status', 'error' );
            $( '#' + name + '-checkbox' ).html( '<i style = "color: ' + blockClr + '" class="fa fa-times"></i>' );
            $( '#' + name + '-error').html(errorTxt);
            errorCnt++;
        }
    }

    return errorCnt;
}

function targetPage( id ) {
    var error = 0;
    if( id == 'page-01' ){
        error = fieldCheck(  'nev', $('input[name="nev"]').val(),   0, 1);
        error+= fieldCheck('email', $('input[name="email"]').val(), 2, 3);
        error+= fieldCheck(  'tel', $('input[name="tel"]').val(),   4, 4);
        //error+= fieldCheck(  'taj', $('input[name="taj"]').val(),   3, 0);
        error+= fieldCheck( 'szuldatumev', $('select[name="szuldatumev"]').val(),  5 );
        error+= fieldCheck( 'szuldatumho', $('select[name="szuldatumho"]').val(),  6 );
        error+= fieldCheck('szuldatumnap', $('select[name="szuldatumnap"]').val(), 7 );
        error+= fieldCheck( 'datum',  $('input[name="datum"]').val(),    8 );
    }
    if(id == 'Booking'){

    }

    return error;
}

function counter(numb,numbEnd,type,animationLength)
{
    processing();
    function processing(){
        if(type == 'ASC') $('.inner-percent-box > span').text(numb++);
        if(type == 'DESC') $('.inner-percent-box > span').text(numb--);
        if ( (type == 'ASC' && numb <= numbEnd) || ( type == 'DESC' && numb >= numbEnd )) { // Stopping condition
            setTimeout(processing, animationLength);
        }
    };
}

var disabled_next     = false;
var disabled_previous = false;
$(document).on('click','button[name="forward"]',function ()
{
    if($('#forms-wrapper').css('margin-left') == '-3300px') return false;
    if(disabled_next) return;

    disabled_next = true;
    setTimeout(function(){disabled_next = false}, 1000);
    if($('.percent-box-container').css('margin-left') == '140px') var aniValue = '518';
    if($('.percent-box-container').css('margin-left') == '431px') var aniValue = '213';
    if($('.percent-box-container').css('margin-left') == '644px') var aniValue = '20';

    if($('.percent-box-container').css('margin-left') == '140px'){
        error = targetPage('page-01');
        if(error == 0) counter( 25, 99, 'ASC', 3 );
        else return false;
    }
    if($('.percent-box-container').css('margin-left') == '431px'){
        error = targetPage('page-02');
        if(error == 0) counter( 66, 99, 'ASC', 20 );
        else return false;
    }
    if($('.percent-box-container').css('margin-left') == '644px'){
        error = targetPage('page-03');
        if(error == 0) counter( 99, 100, 'ASC', 500 );
        else return false;
    }

    $('.percent-box-container').animate({
        'margin-left': '+=' + aniValue
    },1000);
    $('.green-bar').animate({
        'width': '+=' + aniValue
    },1000);
    if($('.percent-box-container').css('margin-left') == '140px')
    {
        var name    = $('input[name="nev"]').val();
        var mail    = $('input[name="email"]').val();
        var phone   = $('input[name="tel"]').val();
        var ssi     = $('input[name="taj"]').val();
        var date    = $('select[name="szuldatumev"]').val()+' '+$('select[name="szuldatumho"] option[value="'+$('select[name="szuldatumho"]').val()+'"]').text()+' '+$('select[name="szuldatumnap"]').val()+'.';
        //var gender  = ($('input:radio[name="neme"]:checked').val() == 1 ? 'Férfi' : 'Nő');
        var time    = $('input[name="datum"]').val();
        //var address = $('input[name="irsz"]').val()+' '+$('input[name="varos"]').val()+', '+$('input[name="utca"]').val();
        $('td[name="name"]').text(name);
        $('td[name="mail"]').text(mail);
        $('td[name="phone"]').text(phone);
        $('td[name="ssi"]').text(ssi);
        $('td[name="birth-date"]').text(date);
        $('td[name="idopont"]').text(time);
        //$('td[name="address"]').text(address);
    }

    $('#forms-wrapper').animate({
        'margin-left': '-=1100'
    }, 1000,function (){
        if($('#forms-wrapper').css('margin-left') != '0px') $('button[name="back"]').css('visibility','visible');
        if($('.percent-box-container').css('margin-left') == '658px')
        {
            $('button[name="forward"]').text('Befejezés');
            $('button[name="forward"]').attr('class','finishButton');
            $('button[name="forward"]').attr('onClick','event.preventDefault();finishBooking()');
            $('button[name="forward"]').attr('name','finish');
            if( $('button[name="finish"]').data('status') != true ) $('button[name="finish"]').data('status', false);

        }
        else {
            $('button[name="finish"]').attr('name','forward');
            $('button[name="forward"]').text('Tovább');
            $('button[name="forward"]').attr('onClick','return false');
            $('button[name="forward"]').attr('class','forwardButton');
        }
    });
});

$(document).on('click','button[name="back"]',function ()
{
    if($('#forms-wrapper').css('margin-left') == '0px') return false;
    if(disabled_previous) return;
    disabled_previous = true;
    setTimeout(function(){disabled_previous = false}, 1000);
    if($('.percent-box-container').css('margin-left') == '658px') var aniValue = '518';
    if($('.percent-box-container').css('margin-left') == '644px') var aniValue = '213';
    if($('.percent-box-container').css('margin-left') == '664px') var aniValue = '20';

    $('.percent-box-container').animate({
        'margin-left': '-=' + aniValue
    },1000);
    $('.green-bar').animate({
        'width': '-=' + aniValue
    },1000);

    if($('.percent-box-container').css('margin-left') == '658px') counter( 99, 25, 'DESC', 3 );
    if($('.percent-box-container').css('margin-left') == '644px') counter( 99, 66, 'DESC', 20 );
    if($('.percent-box-container').css('margin-left') == '664px') counter( 100, 99, 'DESC', 500 );

    $('#forms-wrapper').animate({
        'margin-left': '+=1100'
    }, 1000,function (){
        if($('#forms-wrapper').css('margin-left') == '0px') $('button[name="back"]').css('visibility','hidden');
        if($('.percent-box-container').css('margin-left') == '658px')
        {
            $('button[name="forward"]').text('Foglalás');
            $('button[name="forward"]').attr('class','finishButton');
            $('button[name="forward"]').attr('onClick','return false');
            $('button[name="forward"]').attr('name','finish');
            if( $('button[name="finish"]').data('status') != true ) $('button[name="finish"]').data('status', false);
        }
        else {
            $('button[name="finish"]').attr('name','forward');
            $('button[name="forward"]').text('Tovább');
            $('button[name="forward"]').attr('onClick','return false');
            $('button[name="forward"]').attr('class','forwardButton');
        }
    });
});
function finishBooking(){
    var error = 0;
    if( $('button[name="finish"]').data('status') != true )
    {
        $('#captcha-error').css('border','1px solid red');
        error++;
    }
    else $('#captcha-error').css('border','1px solid rgba(0,0,0,0)');
    if( !$( 'input[name="aszf"] ').is( ':checked' ))
    {
        $('td[name="aszf-error"]').text('Olvasd el az adatvédelmi nyilatkozatot és jelöld meg!');
        error++;
    }
    else $('td[name="aszf-error"]').text('');
    if( error > 0 ) return false;

    $('form[name="booking"]').submit();
}

$(document).on('submit','form[name="callback-request"]',function (event){
    var error = fieldCheck(  'nev', $('input[name="nev"]').val(), 9, 1);
    error+= fieldCheck(  'tel', $('input[name="tel"]').val(), 10 );
    if( error > 0 ){
        return false;
    }
    $('form[name="callback-request"]').submit();
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