$(document).ready(function () {
    reloadEvents();
});


function reloadEvents() {
    $(".vizsglapfile").unbind("change");
    $(".vizsglapfile").on("change", prepareVizsglapUpload);
}

function prepareVizsglapUpload(event) {
    let files = event.target.files;

    $("#loader").show();

    event.stopPropagation();
    event.preventDefault();

    var data = new FormData();
    $.each(files, function (key, value) {
        data.append(key, value);
    });

    $.ajax({
        url: 'index.php?page=vizsgalatilapok&addvizsglapfiles',
        type: 'POST',
        data: data,
        cache: false,
        processData: false,
        contentType: false,
        success: function (response, textStatus, jqXHR) {
            $("#loader").hide();

            if (response.error != "") {
                $.toast({
                    heading: "Hiba",
                    text: response.error,
                    icon: 'error',
                    hideAfter: 5000
                });
            } else {
                if (response.success != "") {
                    $.toast({
                        text: response.success,
                        icon: 'success',
                        hideAfter: 5000
                    });
                }
            }

            $("#debugcontainer").html(response.debug);

            reloadEvents();
        }
    });
}
