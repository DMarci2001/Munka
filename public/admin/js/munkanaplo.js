$(document).ready(function () {





});


function munkaNaploAutoFill() {
    let munkaltato = $("input[name=munkaltato]").val();

    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: { page: "munkanaplo", munkanaploautofill: munkaltato },
        success: function (data) {
            if (data.error !== "") {
                alert(data.error);
                return;
            } else {
                Object.keys(data).forEach(function(key) {
                    $("input[name="+key+"]").val(data[key]);
                    $("#"+key).val(data[key]);
                    console.log('Key : ' + key + ', Value : ' + data[key])
                })
            }
        }
    });

}