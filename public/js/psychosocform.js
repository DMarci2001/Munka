$(document).ready(function () {
    checkPsychosocElementinputs();
});

$(document).on("change",".psychosocelement",function(){
    checkPsychosocElementinputs();
});


function checkPsychosocElementinputs(){
    var arr = [];
    var checks = 0;

    //Ez itt tartalmazza az összes elemet
    $.each( $('input:radio.psychosocelement'), function(){
        var myname= this.name;
        if( $.inArray( myname, arr ) < 0 ){
            arr.push(myname);
        }
        if($(this).prop("checked")){
            checks++;
        }
    });

    if(arr.length==checks){
        $("#psychosocsubmitbutton").css("opacity", 1);
        $("#psychosocsubmitbutton").attr("type","submit");
        $("#psychosocsubmitbutton").attr("value","1");
    }else{
        $("#psychosocsubmitbutton").css("opacity", 0.3);
        $("#covidsubmitbutton").attr("type","button");
        $("#psychosocsubmitbutton").attr("value","0");
    }
}