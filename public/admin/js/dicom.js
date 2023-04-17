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

function toggleDicomImageRow(id) {
    $("#imagerow"+id).toggle();

    $.ajax({
        method: "POST",
        url: "index.php",
        data: {page:"dicom", showimagelist:id}
    }).done(function (msg) {
        $("#imagerow"+id).html(msg);
    });
}

function setLeletStatus(pid, id, num) {
    $.ajax({
        type: "POST",
        url: "index.php",
        data: {page:"dicom", setleletstatus:1, pid:pid, id:id, num:num},
        success: function (response) {
            $("#imagerow"+pid).html(response.imagerow);
            $("#lstatus"+pid).html(response.leletstatus);
        }
    });
}

function toggleLeletKiallitva(id, pid, date) {
    $.ajax({
        type: "POST",
        url: "index.php",
        data: {page:"dicom", toggleLeletKiallitva:1, id:id, pid:pid, date:date},
        success: function (response) {
            $("#lstatus"+pid).html(response.leletstatus);
        }
    });
}