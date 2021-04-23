$(document).ready(function() {
    $(".oltaselement").change(function() {
        checkOltasForm();
    });
    $("#oltas-submit-button").click(function() {
        let message = checkOltasForm();
        if (message != "") {
            alert(message);
            return;
        }

        let params = $("#oltasform").serialize();

        $.ajax({
            type: 'POST',
            url: 'index.php?page=oltasigenyfelmeres&oltasformsavedata',
            data: params,
            success: function (result) {
                if (result.error !== "") {
                    myAlert(result.error);
                    return;
                }
                $("#covidformdiv").html(result.html);
            }
        });

    });
});

function checkOltasForm() {
    let formMessage = "";

    let telconsultation = $('input[name=telconsultation]:checked', '#oltasform').val();
    let allergia = $('input[name=allergia]:checked', '#oltasform').val();
    let anafilaxia = $('input[name=anafilaxia]:checked', '#oltasform').val();
    let betegseg = $('input[name=betegseg]:checked', '#oltasform').val();

    let lazas = $('input[name=lazas]:checked', '#oltasform').val();
    let terhes = $('input[name=terhes]:checked', '#oltasform').val();
    let fogamzasgatlas = $('input[name=fogamzasgatlas]:checked', '#oltasform').val();
    let vedooltas = $('input[name=vedooltas]:checked', '#oltasform').val();

    if (telconsultation === "1") {
        $("#telconsultationtextdiv").slideDown();
    } else {
        $("#telconsultationtextdiv").slideUp();
    }

    if (allergia === "1") {
        $("#allergiatextdiv").slideDown();
    } else {
        $("#allergiatextdiv").slideUp();
    }

    if (anafilaxia === "1") {
        $("#anafilaxiatextdiv").slideDown();
    } else {
        $("#anafilaxiatextdiv").slideUp();
    }

    if (betegseg === "1") {
        $("#betegsegtextdiv").slideDown();
    } else {
        $("#betegsegtextdiv").slideUp();
    }

    if ($("#nev").val().trim() == "" || $("#taj").val().trim() == "" || $("#email").val().trim() == "" || $("#telefon").val().trim() == "") {
        formMessage = "Kérjük adja meg az adatait!";
    }

    if (formMessage == "") {
        if (telconsultation === undefined || allergia === undefined || anafilaxia === undefined || betegseg === undefined || lazas === undefined || terhes === undefined || fogamzasgatlas === undefined || vedooltas === undefined) {
            formMessage = "Kérjük válaszoljon az összes kérdésre!";
        }
    }

    if (formMessage == "" && $("#gdpr").prop("checked") !== true) {
        formMessage = "Kérjük fogadja el az adatvédelmi nyilatkozatot!";
    }

    if (formMessage == "" && $("#responsiblity-confirmed").prop("checked") !== true) {
        formMessage = "Kérjük fogadja el a büntetőjogi feltételt!";
    }

    return formMessage;
}

