$(document).ready(function() {
    $(".covidelement").change(function() {
        checkCovidForm();
    });


});

function checkCovidForm() {
    let covidFormOk = false;

    let travel = $('input[name=travel]:checked', '#covidform').val();
    let kapcs = $('input[name=kapcs]:checked', '#covidform').val();
    let accept = $("#acceptcheck").prop("checked");

    let caugh = $('input[name=caugh]:checked', '#covidform').val();
    let runnynose = $('input[name=runnynose]:checked', '#covidform').val();
    let fever = $('input[name=fever]:checked', '#covidform').val();
    let smell = $('input[name=smell]:checked', '#covidform').val();

    if (travel === "1") {
        $("#traveltextdiv").slideDown();
    } else {
        $("#traveltextdiv").slideUp();
    }

    if (kapcs === "1") {
        $("#kapcstextdiv").slideDown();
    } else {
        $("#kapcstextdiv").slideUp();
    }

    if (travel !== undefined && kapcs !== undefined && caugh !== undefined && runnynose !== undefined && fever !== undefined && smell !== undefined && accept === true) {
        covidFormOk = true;
    }

    if (covidFormOk) {
        $("#covidsubmitbutton").css("opacity", 1);
    } else {
        $("#covidsubmitbutton").css("opacity", 0.3);
    }

    return covidFormOk;
}

function covidFormSubmit() {
    if (checkCovidForm()) {
        let params = $("#covidform").serialize();

        $.ajax({
            type: 'POST',
            url: 'index.php?page=covidform&covidformsavedata',
            data: params,
            success: function (result) {
                if (result.error !== "") {
                    myAlert(result.error);
                    return;
                }
                $("#covidformdiv").html(result.html);
            }
        });

    } else {
        //myAlert("nem ok");
    }

}
