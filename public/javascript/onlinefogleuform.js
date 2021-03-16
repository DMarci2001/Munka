$(document).ready(function () {
    $(".online-fogleu-element").change(function () {
        checkFogleuForm();
    });


});

function checkFogleuForm() {
    let onlinefogleuFormOk = false;

    let sickLeaveLengthCheck = false;
    let seriousHealthConditionCheck = false;
    let medicineUseCheck = false;
    let treatedDiseaseCheck = false;
    let healthComplaintCheck = false;
    let tendencyOfBloodPressureMeasurementCheck = false;
    let currentBloodPressureMeasurementCheck = false;
    let eyeglassesUseCheck = false;

    let sickLeave = $('input[name=sick-leave]:checked', '#online-fogleu-form').val();
    let seriousHealthCondition = $('input[name=serious-health-condition]:checked', '#online-fogleu-form').val();
    let medicineUse = $('input[name=medicine-use]:checked', '#online-fogleu-form').val();
    let treatedDisease = $('input[name=treated-disease]:checked', '#online-fogleu-form').val();
    let healthComplaint = $('input[name=health-complaint]:checked', '#online-fogleu-form').val();
    let eyeglassesUse = $('input[name=eyeglasses-use]:checked', '#online-fogleu-form').val();
    let tendencyOfBloodPressureMeasurement = $('input[name=tendency-of-blood-pressure-measurement]:checked', '#online-fogleu-form').val();
    let currentBloodPressureMeasurement = $('input[name=current-blood-pressure-measurement]:checked', '#online-fogleu-form').val();
    let weight = $('input[name=weight]', '#online-fogleu-form').val();
    let height = $('input[name=height]', '#online-fogleu-form').val();
    let gdpr = $('input[name=aszf]:checked', '#online-fogleu-form').val();
    let responsibility = $('input[name=responsiblity-confirmed]:checked', '#online-fogleu-form').val();
    let phonecall = $('input[name=telepone-consultation-required]:checked', '#online-fogleu-form').val();

    let name = $('input[name=nev]', '#online-fogleu-form').val();
    let taj = $('input[name=taj]', '#online-fogleu-form').val();
    let birthplace = $('input[name=szulhely]', '#online-fogleu-form').val();
    let mothername = $('input[name=anyjaneve]', '#online-fogleu-form').val();
    let workposition = $('input[name=munkakor]', '#online-fogleu-form').val();

    let dateofbirth = "";
    let birthyear = $('select[name=szuldatumev]', '#online-fogleu-form').val();
    let birthmonth = $('select[name=szuldatumho]', '#online-fogleu-form').val();
    let birthday = $('select[name=szuldatumnap]', '#online-fogleu-form').val();

    if (birthyear !== "0" && birthmonth !== "0" && birthday !== "0") {
        dateofbirth = birthyear + "-" + birthmonth + "-" + birthday;
    }


    /*Le kell kezelnem a text mezőket is ha be pipálják, hogy igen és többet le kell írniuk*/

    if (sickLeave === "1") {
        $("#sick-leave-textdiv").slideDown();

        //Igen esetén írnia kell:
        if ($("#sick-leave-text").val().length > 5) {
            sickLeaveLengthCheck = true;
        }

    } else {
        $("#sick-leave-textdiv").slideUp();
    }

    if (seriousHealthCondition === "1") {
        $("#serious-health-condition-textdiv").slideDown();

        //Igen esetén írnia kell:
        if ($("#serious-health-condition-text").val().length > 5) {
            seriousHealthConditionCheck = true;
        }

    } else {
        $("#serious-health-condition-textdiv").slideUp();
    }

    if (medicineUse === "1") {
        $("#medicine-use-textdiv").slideDown();

        //Igen esetén írnia kell:
        if ($("#medicine-use-text").val().length > 5) {
            medicineUseCheck = true;
        }

    } else {
        $("#medicine-use-textdiv").slideUp();
    }

    if (treatedDisease === "1") {
        $("#treated-disease-textdiv").slideDown();

        //Igen esetén írnia kell:
        if ($("#treated-disease-text").val().length > 5) {
            treatedDiseaseCheck = true;
        }

    } else {
        $("#treated-disease-textdiv").slideUp();
    }

    if (healthComplaint === "1") {
        $("#health-complaint-textdiv").slideDown();

        //Igen esetén írnia kell:
        if ($("#health-complaint-text").val().length > 5) {
            healthComplaintCheck = true;
        }


    } else {
        $("#health-complaint-textdiv").slideUp();
    }

    if (eyeglassesUse === "1") {
        $("#eyeglasses-use-textdiv").slideDown();
        if ($("input[name=for-monitor]:checked", "#online-fogleu-form").val() !== undefined || $("input[name=for-distance]:checked", "#online-fogleu-form").val() !== undefined ||
            $("input[name=for-close]:checked", "#online-fogleu-form").val() !== undefined || $("input[name=eyeglasses]:checked", "#online-fogleu-form").val() !== undefined ||
            $("input[name=contact-lens]:checked", "#online-fogleu-form").val() !== undefined) {
            eyeglassesUseCheck = true;
        }
    } else {
        $("#eyeglasses-use-textdiv").slideUp();
    }

    if (tendencyOfBloodPressureMeasurement === "1") {
        $("#tendency-of-blood-pressure-measurement-textdiv").slideDown();

        if ($("input[name=previous-blood-pressure-01]", "#online-fogleu-form").val().length !== 0 && $("input[name=previous-blood-pressure-02]", "#online-fogleu-form").val().length !== 0 && $("input[name=previous-pulse]", "#online-fogleu-form").val().length !== 0) {
            tendencyOfBloodPressureMeasurementCheck = true;
        }

    } else {
        $("#tendency-of-blood-pressure-measurement-textdiv").slideUp();
    }

    if (currentBloodPressureMeasurement === "1") {
        $("#current-blood-pressure-measurement-textdiv").slideDown();

        if ($("input[name=present-blood-pressure-01]", "#online-fogleu-form").val().length !== 0 && $("input[name=present-blood-pressure-02]", "#online-fogleu-form").val().length !== 0 && $("input[name=present-pulse]", "#online-fogleu-form").val().length !== 0) {
            currentBloodPressureMeasurementCheck = true;
        }

    } else {
        $("#current-blood-pressure-measurement-textdiv").slideUp();
    }


    if (sickLeave !== undefined && seriousHealthCondition !== undefined &&
        medicineUse !== undefined && treatedDisease !== undefined &&
        healthComplaint !== undefined && eyeglassesUse !== undefined &&
        tendencyOfBloodPressureMeasurement !== undefined &&
        currentBloodPressureMeasurement !== undefined &&
        weight.length !== 0 && height.length !== 0 && gdpr !== undefined && responsibility !== undefined &&
        name.length !== 0 && taj.length !== 0 && birthplace.length !== 0 &&
        mothername.length !== 0 && workposition.length !== 0 && dateofbirth.length !== 0) {

        if (sickLeave === "1" && sickLeaveLengthCheck != true) {
            $("#online-fogoleu-submit-button").css("opacity", 0.3);
            $("#online-fogoleu-submit-button").attr("onclick", "return false");
            return;
        }

        if (seriousHealthCondition === "1" && seriousHealthConditionCheck != true) {
            $("#online-fogoleu-submit-button").css("opacity", 0.3);
            $("#online-fogoleu-submit-button").attr("onclick", "return false");
            return;
        }

        if (medicineUse === "1" && medicineUseCheck != true) {
            $("#online-fogoleu-submit-button").css("opacity", 0.3);
            $("#online-fogoleu-submit-button").attr("onclick", "return false");
            return;
        }

        if (treatedDisease === "1" && treatedDiseaseCheck != true) {
            $("#online-fogoleu-submit-button").css("opacity", 0.3);
            $("#online-fogoleu-submit-button").attr("onclick", "return false");
            return;
        }

        if (healthComplaint === "1" && healthComplaintCheck != true) {
            $("#online-fogoleu-submit-button").css("opacity", 0.3);
            $("#online-fogoleu-submit-button").attr("onclick", "return false");
            return;
        }

        if (eyeglassesUse === "1" && eyeglassesUseCheck != true) {
            $("#online-fogoleu-submit-button").css("opacity", 0.3);
            $("#online-fogoleu-submit-button").attr("onclick", "return false");
            return;
        }

        if (tendencyOfBloodPressureMeasurement === "1" && tendencyOfBloodPressureMeasurementCheck != true) {
            $("#online-fogoleu-submit-button").css("opacity", 0.3);
            $("#online-fogoleu-submit-button").attr("onclick", "return false");
            return;
        }

        if (currentBloodPressureMeasurement === "1" && currentBloodPressureMeasurementCheck != true) {
            $("#online-fogoleu-submit-button").css("opacity", 0.3);
            $("#online-fogoleu-submit-button").attr("onclick", "return false");
            return;
        }

        if (phonecall === "1" && $('input[name=telefon]', '#online-fogleu-form').val().length < 7) {
            $("#online-fogoleu-submit-button").css("opacity", 0.3);
            $("#online-fogoleu-submit-button").attr("onclick", "return false");
            return;
        }

        onlinefogleuFormOk = true;
    }

    if (onlinefogleuFormOk) {
        $("#online-fogoleu-submit-button").css("opacity", 1);
        $("#online-fogoleu-submit-button").attr("onclick", "");
    } else {
        $("#online-fogoleu-submit-button").css("opacity", 0.3);
        $("#online-fogoleu-submit-button").attr("onclick", "return false");
    }

    /*if (travel !== undefined && kapcs !== undefined && caugh !== undefined && runnynose !== undefined && fever !== undefined && smell !== undefined && accept === true) {
        onlinefogleuFormOk = true;
    }

    if (onlinefogleuFormOk) {
        $("#covidsubmitbutton").css("opacity", 1);
    } else {
        $("#covidsubmitbutton").css("opacity", 0.3);
    }

    return onlinefogleuFormOk;*/

}

function editOnlineFogelEuRow(fid) {
    $.ajax({
        method:"POST",
        url:"index.php",
        data:{ page:"onlinefogleu", fogleueditor:fid }
    }).done(function(data) {
        $("#fogleueditor"+fid).html(data);
    });
}

function fogleuMentes(fid) {
    let params = $("#alkalmassagiform"+fid).serialize();

    $.ajax({
        type: 'POST',
        url: 'index.php?page=onlinefogleu&onlinefogleusave=1&fid='+fid,
        data: params,
        success: function(data) {
            $("#alkalmassaglista").html(data);
        }
    });


}