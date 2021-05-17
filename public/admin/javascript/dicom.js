var element = null;
var panzoom = null;
var parent = null;

//enable mouse wheel


$(window).resize(function() {
    initImage();
});

$(document).ready(function() {
    element = document.getElementById('panzoom');
    panzoom = Panzoom(element, {
        maxScale: 100
    });
    parent = element.parentElement;
    parent.addEventListener('wheel', panzoom.zoomWithWheel);

    requestNewImage();
    initImage();
});


function initImage() {
    //alert($(window).height())
    $('#panzoom').height($(window).height());
    $('#panzoom').width($(window).width()-300);

    $('#dicomimage').height($(window).height());
    //$('#dicomimage').width($(window).width()-300);
}


function toggleNormalize() {
    if ($("#normalizebutton").data("status") == 0) {
        $("#normalizebutton").data("status", 1);
        $("#normalizebutton").css("background", "#8f8");
    } else {
        $("#normalizebutton").data("status", 0);
        $("#normalizebutton").css("background", "#fff");
    }

    requestNewImage();
}

function toggleInvert() {
    if ($("#invertbutton").data("status") == 0) {
        $("#invertbutton").data("status", 1);
        $("#invertbutton").css("background", "#8f8");
    } else {
        $("#invertbutton").data("status", 0);
        $("#invertbutton").css("background", "#fff");
    }

    //alert($("#normalizebutton").data("status"));
    requestNewImage();
}

function requestNewImage() {
    let url = $("#dicomimage").data("rooturl");

    if ($("#invertbutton").data("status") == 1) {
        url = url + "&invert=1";
    }
    if ($("#normalizebutton").data("status") == 1) {
        url = url + "&normalize=1";
    }

    $("#dicomloading").show();
    $("#dicomimage").on("load", function() {
        $("#dicomloading").hide();
    }).attr("src", url);

}