$(document).ready(function() {
    $("#loginbox").css("margin-top",$(window).height()/2 - $("#loginbox").height()/2);
    //$("#loginbox").css("margin-left",-$("#loginbox").width()/2);
    //$("#loginbox").css("opacity",1);
});



function setHelyszin(h) {
    window.location.href='index.php?page=calendar&sethelyszin='+h;
}

function setHelyszin2(h) {
    window.location.href='index.php?page=booking&sethelyszin2='+h;
}

function setNaptarSzuresTipus(t) {
    window.location.href='index.php?page=calendar&setnaptarszurestipus='+t;
}




function setCegFilter(c,p) {
    window.location.href='index.php?setcegfilter='+c+"&p="+p;
}

function sF(i) {
    window.location.href='index.php?page=bnaptar&idopont='+encodeURIComponent(i);
}



function toggleEljott(id) {
    $("#eljottcheck"+id).load("index.php?toggleeljott="+encodeURIComponent(id));
}

function statIdoszakChange(idoszak) {
    window.location.href="index.php?page=stat&idoszak="+encodeURIComponent(idoszak);
}


var respo="";

function startKepImport(id) {
    $("#importstatus").show();
    $("#importstatus").html("Importálás kezdődik ...");

    if (respo!="") $("#importstatus").html("Importálás... még "+respo+" kép van hátra.");

    request = $.ajax({
        url: "index.php",
        type: "get",
        data: "importoneimage=1&id="+encodeURIComponent(id)
    });

    request.done(function (response, textStatus, jqXHR){
        respo=response;
        if (response=="0") {
            window.location.href="index.php?page=cikkek&szerk="+id;
        } else {
            startKepImport(id);
        }
    });

}

function changeInterval(beosztasid, interval) {
    let request = $.ajax({
        url: "index.php",
        type: "get",
        data: "page=doctors&changeinterval="+beosztasid+"&interval="+interval
    });
}

function showTipusValaszto(beosztasid) {
    if ($.trim($("#tipusvalaszto"+beosztasid).html())) {
        $("#tipusvalaszto"+beosztasid).html("");
        return;
    }
    $("#tipusvalaszto"+beosztasid).load("index.php?page=doctors&showtipusvalaszto="+beosztasid);
}


function saveTipusList(beosztasid) {
    var tk="";
    var num=0;
    var t="nincs tipus hozzárendelve";
    var tlist="";

    $("#tipusvalaszto"+beosztasid+" input:checked").each(function() {
        tk=tk+"|"+$(this).attr("name").replace("tipusvalaszto"+beosztasid+"_","")+"|";
        num++;
        tlist=tlist+", "+$(this).attr("value");
    });

    if (num>0) t=tlist.substring(2);

    $("#tipusstatus"+beosztasid).html("<a href='#' class='tlink' title='"+t+"' onclick='showTipusValaszto("+beosztasid+");return false;'>"+num+" tipus</a>");


    request = $.ajax({
        url: "index.php",
        type: "get",
        data: "page=doctors&savebeosztastipusok="+beosztasid+"&value="+encodeURIComponent(tk)
    });

    request.done(function (response, textStatus, jqXHR){
        respo=response;
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


function validateBeutalo() {
    if ($("#beutalotarget").val()=="0") {
        alert("Nem adta meg hova kéred a beutalót!");
        return false;
    }

    if ($("#beutalomegj").val()=="") {
        if (!confirm("Nem adott meg megjegyzést a beutalóhoz, folytatja?")) return false;
    }

    if ($("#beutalonaploszam").val()=="") {
        if (!confirm("Biztos naplószám nélkül adja meg a beutalót?")) return false;
    }

    return true;
}



var refreshTime = 60000*5;
window.setInterval( function() {
    $.ajax({
        cache: false,
        type: "GET",
        url: "/includes/refreshsession.php",
        success: function(data) {
        }
    });
}, refreshTime );



function cssClick(tip,sor) {
    if (tip==1) {
        if ($("#csaksorban"+sor).is(":checked")) $("#csakvsorban"+sor).prop("checked", false);
    } else {
        if ($("#csakvsorban"+sor).is(":checked")) $("#csaksorban"+sor).prop("checked", false);
    }
}


function orvosDataVerify() {
    var formid=$("#iform");
    $("#errorlistdiv").hide();

    $.ajax({
        type: "POST",
        url: "index.php",
        data: "orvosdataverify=1&"+$(formid).serialize(),
        success: function(data)
        {
            if (data=="ok") {
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
    var formid=$("#iform");
    $("#errorlistdiv").hide();

    $.ajax({
        type: "POST",
        url: "index.php",
        data: "userdataverify=1&"+$(formid).serialize(),
        success: function(data)	{
            if (data=="ok") {
                formid.submit();
            } else {
                $("#errorlistdiv").html(data);
                $("#errorlistdiv").slideDown();
            }
        }
    });

    return false;
}

function add2SztCegek(cegid,sor) {
    var arid=$("#arid"+sor).val();

    $.ajax({
        type: "POST",
        url: "index.php",
        data: "add2sztceg=1&arid="+arid+"&cegid="+cegid+"&sor="+sor,
        success: function(data)	{
            $("#ceglist"+sor).html(data);
            $("#cegadd"+sor).slideToggle();
        }
    });


}

function removeSztCegek(cegid,sor) {
    var arid=$("#arid"+sor).val();

    $.ajax({
        type: "POST",
        url: "index.php",
        data: "removesztceg=1&arid="+arid+"&cegid="+cegid+"&sor="+sor,
        success: function(data)	{
            $("#ceglist"+sor).html(data);
        }
    });


}




function showFizSzolg(id) {
    $("#fizszolglist"+id).load("index.php?page=arrivals&showfizszolglist&fid="+id);
}


function addFizSzolg(fid,aid) {
    $.ajax({
        type: "POST",
        url: "index.php",
        data: "page=arrivals&addfizszolg=1&fid="+fid+"&aid="+aid,
        success: function(data)	{
            $("#fizszolglist"+fid).html(data);
        }
    });
}


function removeFizSzolg(fid,id) {
    if (!confirm("Biztos törli ezt a szolgáltatást?")) return;

    $.ajax({
        type: "POST",
        url: "index.php",
        data: "page=arrivals&removefizszolg=1&fid="+fid+"&id="+id,
        success: function(data)	{
            $("#fizszolglist"+fid).html(data);
        }
    });
}



function setListDay(day) {
    //$("#querystatus").html("lekérdezés folyamatban...");

    $("#elojegyzestable").load("index.php?page=booking&showelojegyzestable&day="+encodeURIComponent(day),null,
        function(responseText){
        }
    );
}


var foglalasSelected=0;
var foglalasSelectedPass="";
var foglalasDisplayed=0;
var cpy=0;
var selectedInterval = 0;

function setSelectedInterval(i) {
    selectedInterval = i;
}

function addIdopont(idopont,szt) {
    if (foglalasSelected!=0) {
        var msg="Biztos áthelyezed ide a kijelölt foglalást?";
        if (cpy==1) {
            msg="Biztos átmásolod ide a kijelölt foglalást?";
        }

        if (confirm(msg)) {
            let params = "?page=booking&cpy="+cpy+"&szt="+encodeURIComponent(szt)+"&moveidopont="+encodeURIComponent(idopont)+"&fid="+encodeURIComponent(foglalasSelected)+"&rinterval="+selectedInterval;
            $("#elojegyzestable").load("index.php"+params,null,
                function(responseText){
                    if (cpy==0) showIdopontEditor('booking',foglalasSelectedPass,foglalasSelected);
                    if (cpy==0) cancelFoglalasMove();
                });
        }
        return;
    }

    $.ajax({
        url:'index.php',
        type:'GET',
        data:{page:'booking', szt:szt, addidopont:idopont, rinterval: selectedInterval},
        success:function(data){
            if (data.substring(0, 5)=='error') {
                alert(data.substring(5));
            } else {
                $("#elojegyzestable").html(data);
            }
        }
    });
}


function refreshNaptar(idopont) {
    $.ajax({
        url:'index.php',
        type:'GET',
        data:{page:'calendar', loadnaptar:'1'},
        success:function(data){
            $("#foglalasnaptar").html(data);
        }
    });
}



function addIdopontNaptar(idopont,szt) {
    $("#naptarloading").show();

    if (foglalasSelected!=0) {
        if (confirm("Biztos áthelyezed ide a kijelölt foglalást: "+idopont+"?")) {

            $("#foglalasnaptaridopont").load("index.php?page=calendar&szt="+encodeURIComponent(szt)+"&moveidopont="+encodeURIComponent(idopont)+"&fid="+encodeURIComponent(foglalasSelected),null,
                function(responseText){
                    showIdopontEditor('bnaptar',foglalasSelectedPass,foglalasSelected);
                    cancelFoglalasMove();
                    refreshNaptar(idopont);
                    $("#naptarloading").hide();
                }
            );
        }
        return;
    }

    $.ajax({
        url:'index.php',
        type:'GET',
        data:{page:'calendar', szt:szt, addidopont:idopont, rinterval: selectedInterval},
        success:function(data){
            if (data.substring(0, 5)=='error') {
                alert(data.substring(5));
            } else {
                $("#foglalasnaptar").html(data);
                //refreshNaptar(idopont);
            }
            $("#naptarloading").hide();
        }
    });
}



function removeIdopont(id, p, page) {
    if (!confirm("Biztos törlöd ezt az időpontot?")) {
        return;
    }

    $.ajax({
        url:'index.php',
        type:'GET',
        data:{page:page, removeidopont:id, p:p},
        success:function(data){
            cancelFoglalasMove();
            $("#idoponteditor").slideUp();
            if (page == "booking") {
                $("#elojegyzestable").html(data);
            }
            if (page == "calendar") {
                $("#foglalasnaptar").html(data);
            }
        }
    });

}


function showIdopontEditor(page,p,id) {
    cancelFoglalasMove();
    $("#naptarloading").show();

    $("#idoponteditor").load("index.php?page="+page+"&showidoponteditor="+encodeURIComponent(id)+"&p="+encodeURIComponent(p),null,
        function(responseText){
            foglalasDisplayed=id;
            $("#idoponteditor").slideDown();
            $("#naptarloading").hide();
        }
    );
}

function startFoglalasMove(id,p) {
    cpy=0;
    foglalasSelected=id;
    foglalasSelectedPass=p;
    $("#autofill").slideUp();
    $("#copyinfo").slideUp();
    $("#moveinfo").slideDown();
}

function startFoglalasCopy(id,p) {
    cpy=1;
    foglalasSelected=id;
    foglalasSelectedPass=p;
    $("#autofill").slideUp();
    $("#moveinfo").slideUp();
    $("#copyinfo").slideDown();
}

function startAutoFill(id,p) {
    //cpy=1;
    foglalasSelected=id;
    foglalasSelectedPass=p;
    $("#copyinfo").slideUp();
    $("#moveinfo").slideUp();
    $("#autofill").slideDown();
}

function autoFill(taj,birth){
    if(taj == "" && birth == ""){
        if(taj == "")$('#user-taj').css('border','1px solid red');
        if(birth == "")$('#user-szuldatum').css('border','1px solid red');
    }

    else{
        if(taj.length < 9 || taj.length > 9){
            $('#user-taj').css('border','1px solid red');
        }
        else{
            $('#user-taj').css('border','1px solid gray');
            $.ajax({
                url:'index.php',
                type:'POST',
                data:{AFForm:taj,
                    birth:birth},
                success:function(data){
                    str = data.split('||');
                    if(str[0] == 'success'){
                        $('input[name="paciensid"]').val(str[1]);
                        $('input[name="taj"]').val(str[3]);
                        $('input[name="email"]').val(str[8]);
                        $('input[name="nev"]').val(str[2]);
                        $('input[name="telefon"]').val(str[9]);
                        $('input[name="munkakor"]').val(str[4]);
                        $('input[name="irsz"]').val(str[10]);
                        $('input[name="varos"]').val(str[11]);
                        $('input[name="utca"]').val(str[12]);
                        $('select[name="cegid"]').val(str[13]);
                        $('input[name="szulhely"]').val(str[6]);
                        $('input[name="anyjaneve"]').val(str[7]);
                        $('input[name="torzsszam"]').val(str[14]);
                        $('#editorDate').load('index.php?DateSelector=' + str[5]);
                        $('td[name="error-td"]').text(' ');
                    }
                    else{
                        $('td[name="error-td"]').text('Nincs találat az adatbázisban!');
                        return false;
                    }
                }
            });
        }
    }
}

function cancelFoglalasMove() {
    foglalasSelected=0;
    $("#moveinfo").slideUp();
    $("#copyinfo").slideUp();
    $("#autofill").slideUp();
}


function foglalasMentes(page) {
    var data = $("#iform").serialize()+"&page="+page+"&foglalasmentesnaptar2=1";
    $("#naptarloading").show();

    $.ajax({
        type: "POST",
        url: "index.php",
        data: data,
        success: function(response)	{
            $("#idoponteditor").html(response);
            if (page == "calendar") {
                refreshNaptar($("#idopontmarker").val());
                //sF2($("#idopontmarker").val());
                $("#naptarloading").hide();
                return;
            }
            $("#elojegyzestable").load("index.php?page=booking&showelojegyzestable");
        }
    });

}

function foglalasOrvosErtesites() {
    var data=$("#iform").serialize()+"&foglalasmentesnaptaresertesites2=1";

    $.ajax({
        type: "POST",
        url: "index.php?page=booking",
        data: data,
        success: function(response)	{
            $("#idoponteditor").html(response);
            alert("Értesítés elküldve!");
        }
    });
}

function foglalasOrvosErtesitesOnly(fid) {
    $.ajax({
        type: "POST",
        url: "index.php",
        data: "fid="+encodeURIComponent(fid)+"&foglalasorvosertesitesonly=1",
        success: function(response)	{
            alert(response);
        }
    });

}


var lastCell="";
var zindex=10000;

function setIdoPontCell(i) {
    var id=i.replace(" ","");
    id=id.replace("-","");
    id=id.replace("-","");
    id=id.replace(":","");

    //$("#ipbox"+id).css("background","#81d6e6");


    $("#ipbox"+id).css("transform","scale(1.1)");
    $("#ipbox"+id).css("z-index",zindex);
    $("#ipbox"+id).css("box-shadow","0px 0px 5px #444");

    if (id==lastCell) return;

    zindex++;
    if (lastCell!="") {
        $("#ipbox"+lastCell).css("transform","scale(1)");
        $("#ipbox"+lastCell).css("box-shadow","");
    }
    lastCell=id;
}


function sF2(i) {
    setIdoPontCell(i);

    $("#foglalasnaptaridopont").load("index.php?shownaptaridopont="+encodeURIComponent(i),null,
        function(responseText){
        }
    );
}

function naptarMove(d) {
    $("#naptarloading").show();
    $("#foglalasnaptar").load("index.php?page=calendar&loadnaptar&shift="+encodeURIComponent(d),null,
        function(responseText){
            $("#foglalasnaptaridopont").html("");
            $("#naptarloading").hide();
        }
    );
}

function addSMSPhone(oid) {
    $("#smsalertsettings").load("index.php?page=doctors&addsmsphone&oid="+oid);
}

function deleteSMSPhone(oid,id) {
    $("#smsalertsettings").load("index.php?page=doctors&deletesmsphone&oid="+oid+"&id="+id);
}

function showCegValaszto(phoneid) {
    if ($.trim($("#cegvalaszto"+phoneid).html())) {
        $("#cegvalaszto"+phoneid).html("");
        return;
    }
    $("#cegvalaszto"+phoneid).load("index.php?page=doctors&showcegvalaszto="+phoneid);
}

function saveCegList(phoneid) {
    var tk="";
    var bszoveg="Összes cég";
    var num=0;
    var t="nincs tipus hozzárendelve";
    var tlist="";

    $("#cegvalaszto"+phoneid+" input:checked").each(function() {
        tk=tk+"|"+$(this).attr("name").replace("cegvalaszto"+phoneid+"_","")+"|";
        num++;
        tlist=tlist+", "+$(this).attr("value");
    });

    if (num>0) {
        t=tlist.substring(2);
        bszoveg=num+" cég";
    }

    $("#cegstatus"+phoneid).html("<a href='#' class='tlink' title='"+t+"' onclick='showCegValaszto("+phoneid+");return false;'>"+bszoveg+"</a>");

    request = $.ajax({
        url: "index.php",
        type: "get",
        data: "page=doctors&savesmsphonetipusok="+phoneid+"&value="+encodeURIComponent(tk)
    });

    request.done(function (response, textStatus, jqXHR){
        respo=response;
    });
}


function lEditorOpen(id) {
    $("#lszoveg"+id).hide();
    $("#leditor"+id).show();
}
function lEditorClose(id) {
    $("#lszoveg"+id).show();
    $("#leditor"+id).hide();
}
function lEditorSave(id) {
    lEditorClose(id);
    var e=$("#langtext"+id).val();

    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: "page=langsettings&savelangvalue="+encodeURIComponent(e)+"&id="+id,
        success: function(data){
            $("#llink"+id).html(data);
        }
    });
}



//marci

var op_val = 0;
$(document).on('click','.protocol-list-frame',function (){
    if(op_val == 0){
        $('.protocol-list-main-center').css('display','inline-block');
        $('.protocol-list-wrapper-01').animate({left:'-420px'});
        $('.protocol-list-main-center').animate({width:'400px'});
        op_val++;
        return;
    }
    if(op_val == 1){
        $('.protocol-list-wrapper-01').animate({left:'-20px'});
        $('.protocol-list-main-center').animate({width:'0px'},400, function (){
            $('.protocol-list-main-center').css('display','none');
        });
        op_val--;
        return;
    }

});

$(document).on('click','.protocol-obj', function (e){
    var obj 	  = $(e.target).closest('.protocol-obj').find('.checkDiv');
    var subject   = $(e.target).closest('.protocol-obj').find('.checkDiv > svg');
    var string 	  = $(e.target).closest('.protocol-obj').attr('title');
    var curStr = $('#protocol-textarea');
    var curVal = $('#protocol-textarea').val();
    var pipe 	  = '<i class="fa fa-check"></i>';
    if( subject.length ) {
        obj.empty();
        var position = curVal.search( string );
        if( curVal != '' ){
            if( position == 0 ) modStr = curVal.replace( string , '');
            else modStr = curVal.replace( ', ' + string, '' );
        }
        else modStr = curVal.replace(string, '');
        curStr.val( modStr );
    }
    else {
        obj.html( pipe );
        if( curVal != '' ) curStr.val( curVal + ', ' + string );
        else curStr.val( string );
    }
});

function listCheck(){
    var protocolArr = new Array();
    $('.checkDiv').each( function( i, obj ) {
        var strID = $(obj).closest('.protocol-obj').attr('id').split('-');
        var protocol = strID[1];
        if( $(obj).find('svg').length ) protocolArr.push(protocol);
    });
    return protocolArr;
}

function setProtocol(val){
    var protocolArr = new Array();
    protocolArr = listCheck();

    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: {newProtocol: val},
        success: function(data){
            if( data == 'Successful added!'){
                $('.protocol-list-wrapper').load('index.php',{refreshProtocolList:protocolArr});
                $('.successful-message').css('display','block');
                $('.successful-message').find('span').text('Protocoll hozzáadva!');
                setTimeout( function() {
                    $('.successful-message').css({ opacity: 1.0, visibility: 'visible' }).animate({ opacity: 0 }, 1500, function() {
                        $('.successful-message').css('display','none');
                    });
                }, 1500);
            }
        }
    });
}

function saveProtocol(cid){
    var protocolArr = new Array();
    protocolArr = listCheck();
    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: {saveProtocol: protocolArr,
            cid: cid},
        success: function(data){
            if( data == ''){
                $('.successful-message').css('display','block');
                $('.successful-message').find('span').text('Lista elmentve!');
                setTimeout( function() {
                    $('.successful-message').css({ opacity: 1.0, visibility: 'visible' }).animate({ opacity: 0 }, 1500, function() {
                        $('.successful-message').css('display','none');
                    });
                }, 1500);
            }
        }
    });
}

$(document).ready(function(){
    $('.option-box').on('submit',(function(e) {
        $(this).slideToggle();
    }));
});

$(document).on('click','input[name="uj_lelet"]',function(){
    $('#leletform').load('index.php?uj_lelet');
    $('#leletform').slideToggle();
    $(this).css('display','none');
});

function printLelet() {
    var objToPrint=document.getElementById('lelet-content');

    var newWin=window.open('','Print-Window');

    newWin.document.open();

    newWin.document.write('<html><body style = "page-break-after: always;" onload="window.print()">'+objToPrint.innerHTML+'</body></html>');

    newWin.document.close();

    setTimeout(function(){newWin.close();},10);
}

function open_lelet(id){
    $('#uj-lelet').remove();
    if($('#leletform').css('display') == 'none')
    {
        tinymce.EditorManager.execCommand('mceAddEditor',true, 'lelet-page-'+id);
        $('#leletform').load('index.php?open_lelet=' + id);

        setTimeout(function() {
            $('#leletform').slideToggle();
            $('#add-lelet').css('display','none');
        }, 1200);
    }
}
function open_zaro(id){
    $('#uj-lelet').remove();
    if($('#zaroform').css('display') == 'none')
    {
        tinymce.EditorManager.execCommand('mceAddEditor',true, 'zaro-page-'+id);
        $('#zaroform').load('index.php?zaro_lelet=' + id);

        setTimeout(function() {
            $('#zaroform').slideToggle();
            $('#add-lelet').css('display','none');
        }, 1200);
    }
}

$(document).on('click','input[name="close_lelet"]',function(){
    //console.log('haliii');
    $('#leletform').slideToggle(function(){
        $('#leletform').empty();
        $('#leletbutton').find('input[type="button"]').css('display','block');
    });

});
$(document).on('click','input[name="close_zaro"]',function(){
    //console.log('haliii');
    $('#zaroform').slideToggle(function(){
        $('#zaroform').empty();
        $('#leletbutton').find('input[type="button"]').css('display','block');
    });

});
function add_lelet(id,textarea){

    if( id == 'empty' ) return;
    var data = 'request_lelet=' + id;
    var footage = $('.medic-footage').text().replace(/\"/g, '');
    var seal_number = $('#pecsetszam').val();
    if(footage.includes('('))
    {
        var fracted_footage = footage.split('(');
        footage = fracted_footage[0] + '(' + seal_number + fracted_footage[1];
    }

    request = $.ajax({
        url: 'index.php',
        type: 'POST',
        data: data
    });
    request.done(function (res, textStatus, jqXHR){
        $('#minta-lista').prop('disabled',true);
        $('input[name="lelet_hozzadas"]').prop('disabled',true);
        $('table[name="positive-options"]').load('index.php', {setCheckboxes:id});
        $('table[name="negative-option"]').load('index.php', {loadnegativeCheck:true});

        $('.currently-text-container').html(res);
        var iframe = textarea+'_ifr';
        $('#' + iframe).contents().find('#tinymce').append(res);
        $('#' + iframe).contents().find('#tinymce').append(footage);

    });
}

function send_iFrame( patient, medic, textarea ){

    var params = new window.URLSearchParams(window.location.search);
    if ( $('form[name="iForm"] input:checkbox:checked').length > 0 )
    {
        var mceContent = $('#' + textarea + '_ifr').contents().find('#tinymce').prop('outerHTML');
        if(textarea != 'uj-lelet-page'){
            idDumb = textarea.split('-');
            var data  = 'update_lelet=' + encodeURIComponent(mceContent);
            data += '&lid=' + idDumb[2];
            data += '&'+$('form[name="iForm"]').serialize();
        }
        else{
            var data  = 'save_lelet=' + encodeURIComponent(mceContent);
            data += '&seal_numb=' + $('#pecsetszam').val();
            data += '&tipus=' + $('#minta-lista').val();
            data += '&'+$('form[name="iForm"]').serialize();
        }

        request = $.ajax({
            url: 'index.php',
            type: 'post',
            data: data
        });
        request.done(function ( res, textStatus,jqXHR ){
            $('#lelet-lista').load('index.php?reload_leletlista&p='+params.get('page')+'&user='+params.get('szerk'));
            $('#leletform').slideToggle(function(){
                $('#leletform').empty();
                $('#leletbutton').find('input[type="button"]').css('display','block');
            });
            $('.successful-message').css('color','#67ec00');
            $('.successful-message').css('display','block');
            $('.successful-message').find('span').text('Lelet elmentve!');
            setTimeout(function() {
                $('.successful-message').css({opacity: 1.0, visibility: 'visible'}).animate({opacity: 0}, 1000, function(){
                    $('.successful-message').css('display','none');
                });
            }, 1000);
        });

        $('#' + textarea + '_ifr').get(0).contentWindow.focus();
        $('#' + textarea + '_ifr').get(0).contentWindow.print();
    }
    else
    {
        $('.successful-message').css('display','block');
        $('.successful-message').find('span').css('color','red');
        $('.successful-message').find('span').text('Jelöld ha van eltérés vagy nincs!');
        setTimeout(function() {
            $('.successful-message').css({opacity: 1.0, visibility: 'visible'}).animate({opacity: 0}, 1000, function(){
                $('.successful-message').css('display','none');
            });
        }, 1000);
    }
}
function save_iFrame( patient, medic, textarea){

    var params = new window.URLSearchParams(window.location.search);
    //console.log($('form[name="iForm"]').serializeArray());
    if ( $('form[name="iForm"] input:checkbox:checked').length > 0 )
    {
        var mceContent = $('#' + textarea + '_ifr').contents().find('#tinymce').prop('outerHTML');

        if(textarea != 'uj-lelet-page'){
            idDumb = textarea.split('-');
            var data  = 'update_lelet=' + encodeURIComponent(mceContent);
            data += '&lid=' + idDumb[2];
            data += '&'+$('form[name="iForm"]').serialize();
        }
        else{
            var data  = 'save_lelet=' + encodeURIComponent(mceContent);
            data += '&seal_numb=' + $('#pecsetszam').val();
            data += '&tipus=' + $('#minta-lista').val();
            data += '&'+$('form[name="iForm"]').serialize();
        }
        request = $.ajax({
            url: 'index.php',
            type: 'post',
            data: data
        });
        request.done(function (res, textStatus,jqXHR){
            $('#lelet-lista').load('index.php?reload_leletlista&p='+params.get('page')+'&user='+params.get('szerk'));
            $('#leletform').slideToggle(function(){
                $('#leletform').empty();
                $('#leletbutton').find('input[type="button"]').css('display','block');
            });
            $('.successful-message').css('color','#67ec00');
            $('.successful-message').css('display','block');
            $('.successful-message').find('span').text('Lelet elmentve!');
            setTimeout(function() {
                $('.successful-message').css({opacity: 1.0, visibility: 'visible'}).animate({opacity: 0}, 1000, function(){
                    $('.successful-message').css('display','none');
                });
            }, 1000);
        });
    }
    else
    {
        $('.successful-message').css('display','block');
        $('.successful-message').find('span').css('color','red');
        $('.successful-message').find('span').text('Jelöld ha van eltérés vagy nincs!');
        setTimeout(function() {
            $('.successful-message').css({opacity: 1.0, visibility: 'visible'}).animate({opacity: 0}, 1000, function(){
                $('.successful-message').css('display','none');
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

function syncFoglalasDataToUser(fogl){
    var data = $("#iform").serialize()+"&syncFoglalasDataToUser=1";
    $.ajax({
        url:'index.php',
        type:'POST',
        data:data,
        success: function(data) {
            if (data.error != "") {
                alert(data.error);
            } else {
                $('input[name="paciensid"]').val(data.userId);
            }
        }
    });
}

$(function(){
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
            if(version == 1) {
                var str = data.split('|');
                $('#coupontitle').text(str[0]);
                $('#coupondesc').css('color','#12c915').text(str[1]);
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

function accountini( id )
{
    var data = { accountIni: true, docid: id };
    $.ajax({
        method: 'POST',
        url: 'index.php',
        data: data
    }).done(function(data) {
        console.log(data);
        location.reload();
    });
}
function checkSzabiData() {

    $.ajax({
        type: 'POST',
        url: 'index.php',
        data: 'page=doctors&checkSzabiData=1&start='+$('input[name="szabadsagtol"]').val()+'&end='+$('input[name="szabadsagig"]').val()+'&orvosid='+$('input[name="orvosid"]').val(),
        success: function(data)
        {
            if(data != '')
            {
                var result = '';
                var analysis = data.split('|');
                for(var i = 0;i < analysis.length; i++)
                {
                    var match = analysis[i].split(',');
                    result+=match[0]+' '+match[1]+'\n';
                }
                alert('Az alábbi foglalások a szabadságra esnek: \n'+result);
                return false;
            }
            $('<input />').attr('type', 'hidden').attr('name', 'addszabadsag').attr('value', '1').appendTo('#iform');
            $('#iform').submit();
        }
    });

    return false;
}

function LWOpener(count)
{
    var numb = (count-10);
    if( $('.warrnings-content').css('max-height') == '250px')
    {
        $('.warrnings-content').css('max-height', 'none');
        $('.warningOpenFolder').html('Kevesebb <i class="fas fa-angle-double-up"></i>');
    }
    else
    {
        $('.warrnings-content').css('max-height', '250px');
        $('.warningOpenFolder').html(' Még '+numb+' db <i class="fas fa-angle-double-down"></i>');
    }
}

function SmoothScrollTo(string, timelength){
    var timelength = timelength || 1000;
    $('html, body').animate({
        scrollTop: $('*:contains("'+string+'"):last').offset().top-70
    }, timelength, function(){
        window.location.hash = '*:contains("'+string+'"):last';
    });
}

function scrollToTarget(string,target)
{
    $('body').highlight(string, target);
    SmoothScrollTo(string, 1000);
    $(window).scrollTop($('*:contains("'+string+'"):first').offset().top);
}

function scrollToTop()
{
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

//Highlight
jQuery.fn.highlight = function( c,target )
{
    function e( b, c )
    {
        var d = 0;
        if( 3 == b.nodeType )
        {
            var a = b.data.toUpperCase().indexOf(c);
            if( 0 <= a )
            {
                d = document.createElement('span');
                d.className = 'highlight';
                a = b.splitText( a );
                a.splitText( c.length );
                var f = a.cloneNode( !0 );
                d.appendChild( f );
                a.parentNode.replaceChild( d, a );
                d = 1
            }
        }
        else if ( 1 == b.nodeType && b.childNodes && !/(script|style)/i.test( b.tagName ))
            for( a = 0; a < b.childNodes.length; ++a )
            {
                a+= e( b.childNodes[a], c );
            }

        return d
    }
    return this.length && c && c.length ? this.each( function()
    {
        e( this, c.toUpperCase() )
    }): this
};
jQuery.fn.removeHighlight = function()
{
    return this.find('span.highlight').each( function()
    {
        this.parentNode.firstChild.nodeName;
        with( this.parentNode )replaceChild( this.firstChild, this ), normalize()
    }).end()
};

function openSidePanel(select)
{
    $('.WL-sidePanel').animate({width: 'toggle'});
    $('.WL-sidePanel').html('<img style="position:absolute;width:25px;height:25px;margin:0 auto;top:50%;left:50;" src="images/loading.svg" />');
    if( $('.WL-sidePanel').css('display') == 'block' )
    {
        if($('.WL-sidePanel').data('examIndex')) var index = $('.WL-sidePanel').data('examIndex');
        else var index = 'empty';
        $.ajax({
            type:'POST',
            url:'index.php',
            data:{ loadSelectedMenu:true,option:select,index:index},
            success:function( data ){
                $('.WL-sidePanel').html( data );
            }
        })
    }
}

function showMissingExams(index)
{
    $('.WL-sidePanel').data('examIndex', index);
    if( $('.WL-sidePanel').css('display') != 'block' )
    {
        $('.WL-sidePanel').animate({width: 'toggle'});
    }
    $('.WL-sidePanel-selected-menu-conainer').html('<img style="position:absolute;width:25px;height:25px;margin:0 auto;top:calc(50% - 28px);left:calc(50% - 10px);" src="images/loading.svg" />');

    $.ajax({
        type:'POST',
        url:'index.php',
        data:{ loadSelectedMenu:true,option:'option-3',index:index},
        success:function( data ){
            $('.WL-sidePanel').html( data );
        }
    })
}

function copyBooking(id, pass)
{
    if($('#copyButton').css('color') == 'rgb(0, 0, 0)')
    {
        $('#copyButton').css('color','red');
        cpy=1;
        foglalasSelected=id;
        foglalasSelectedPass=pass;
        return;
    }
    if($('#copyButton').css('color') == 'rgb(255, 0, 0)')
    {
        $('#copyButton').css('color','black');
        cpy=0;
        foglalasSelected=0;
        foglalasSelectedPass=0;
        return;
    }
}

function selectSPOption(option)
{
    $('.WL-sidePanel-selected-menu-conainer').html('<img style="position:absolute;width:25px;height:25px;margin:0 auto;top:calc(50% - 28px);left:calc(50% - 10px);" src="images/loading.svg" />');
    if($('.WL-sidePanel').data('examIndex')) var index = $('.WL-sidePanel').data('examIndex');
    else var index = 'empty';
    $.ajax({
        type:'POST',
        url:'index.php',
        data:{ loadWLSelectedMenu:true,option:option,index:index },
        success:function( data ){
            $('.WL-sidePanel-selected-menu-conainer').html( data );

        }
    })
    $.ajax({
        type:'POST',
        url:'index.php',
        data:{ loadWLSPTitle:true,option:option },
        success:function( data ){
            $('.WL-sidePanel-title > span:first').text( data );

        }
    })
}

function changeWLPosition()
{
    var position = $('.manager-warnings').offset();
    var relativePositionLeft  = 373;
    var relativeTop  = 50.48333740234375;
    var monitorWidth = $(window).width();
    var relativeLeft = ( monitorWidth - relativePositionLeft );


    if($('.manager-warnings').css('position') == 'fixed')
    {
        var positionTop = (position.top - relativeTop)+'px';
        var positionLeft = (position.left - relativeLeft)+'px';
        $('.manager-warnings').css({'position':'absolute','left':positionLeft,'top':positionTop});
    }
    else
    {
        var positionTop = (position.top - $(window).scrollTop() - 10)+'px';
        var positionLeft = position.left+'px';
        $('.manager-warnings').css({'position':'fixed','left':positionLeft,'top':positionTop});
    }
}

function removeManager(id)
{
    $.ajax({
        type:'POST',
        url:'index.php',
        data:{removeManager:id},
        success:function(data){
            if(data != '')
            {
                $('#manager-'+id).remove();
                if($('.WL-sidePanel').css('display') == 'block')
                {
                    $('.WL-sidePanel-selected-menu-conainer').html('<img style="position:absolute;width:25px;height:25px;margin:0 auto;top:calc(50% - 28px);left:calc(50% - 10px);" src="images/loading.svg" />');
                    $.ajax({
                        type:'POST',
                        url:'index.php',
                        data:{ loadWLSelectedMenu:true,option:'option-1' },
                        success:function( data ){
                            $('.WL-sidePanel-selected-menu-conainer').html( data );
                        }
                    });
                    $.ajax({
                        type:'POST',
                        url:'index.php',
                        data:{refreshLWOpener:true},
                        success:function(data){
                            if(data != '')
                            {
                                $('#LWOpener-container').html(data);
                            }
                        }
                    })
                }
            }
        }
    })
}

function refreshWList()
{
    $.ajax({
        type:'POST',
        url:'index.php',
        data:{refreshWL:true},
        success:function(data){
            if(data != '')
            {
                $('.warrnings-content').html(data);
            }
        }
    });
    $.ajax({
        type:'POST',
        url:'index.php',
        data:{refreshLWOpener:true},
        success:function(data){
            if(data != '')
            {
                $('#LWOpener-container').html(data);
            }
        }
    })
}
function withdrawRemove(id)
{
    $.ajax({
        type:'POST',
        url:'index.php',
        data:{withdrawManager:id},
        success:function(data){
            if(data != '')
            {
                $('#removedManager-'+id).remove();
                refreshWList();
                selectSPOption('option-1');
            }
        }
    })
}
$(document).ready(function(){
    $(function(){
        $('#vizsg_szures_start,#vizsg_szures_end').datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true,
            yearRange: '-100y:c+20',
            maxDate: '+2y'
        });
        $.datepicker.regional['hu'] = {
            monthNames: ['Január', 'Február', 'Március', 'Április', 'Május', 'Június', 'Július', 'Augusztus', 'Szeptember',
                'Október', 'November', 'December'
            ],
            monthNamesShort: ['Jan', 'Feb', 'Már', 'Ápr', 'Máj', 'Jún', 'Júl', 'Aug', 'Szep', 'Okt', 'Nov', 'Dec'],
            dayNames: ['Hétfő', 'Kedd', 'Szerda', 'Csütörtök', 'Pénter', 'Szombat', 'Vasárnap'],
            dayNamesShort: ['Hé', 'Ke', 'Sze', 'Csü', 'Pé', 'Szo', 'Vas'],
            dayNamesMin: ['Hé', 'Ke', 'Sze', 'Csü', 'Pé', 'Szo', 'Vas'],
            weekHeader: 'hét'
        };

        $.datepicker.setDefaults($.datepicker.regional['hu']);
    });
});

function downloadExamStat(){
    //var data='downloadExamStat=true&start='+$('#vizsg_szures_start').val()+'&end='+$('#vizsg_szures_end').val()+'&cegid='+$('select[name="cegselect"]').val();
    $('<form></form>').appendTo('body').submit();

    var form = $(document.createElement('form'));
    //$(form).attr('action', 'reserves.php');
    $(form).attr('method', 'POST');

    var key   = $('<input>').attr('type','hidden').attr('name','downloadExamStat').val(true);
    var start = $('<input>').attr('type','hidden').attr('name','start').val($('#vizsg_szures_start').val());
    var end   = $('<input>').attr('type','hidden').attr('name','end').val($('#vizsg_szures_end').val());
    var cegid = $('<input>').attr('type','hidden').attr('name','cegid').val($('select[name="cegselect"]').val());

    $(form).append($(key));
    $(form).append($(start));
    $(form).append($(end));
    $(form).append($(cegid));

    form.appendTo( document.body )

    $(form).submit();
}

//marci end

function showLogDetail(id) {
    $("#logdetail"+id).toggle();
    $("#logdetailcontent"+id).load("index.php?page=log&loadlogdetail="+id);
    return false;
}
