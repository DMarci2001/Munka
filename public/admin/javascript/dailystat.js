$(document).ready(function () {
    reloadEvents();
});


function reloadEvents() {
    $(".dailystatfile").unbind("change");
    $(".dailystatfile").on("change", prepareDailyStatUpload);
}

function downloadDailyStat(day) {
    let dayBox = $("#daybox"+day);

    $.ajax({
        type: "POST",
        url: "index.php?page=dailystat&downloaddailystat=1",
        data: "day=" + encodeURIComponent(day),
        success: function (response) {
            if (response.error != "") {
                alert(response.error);
                return;
            }
        }
    });
}

function generateDailyStat(day) {
    let dayBox = $("#daybox"+day);

    $.ajax({
        type: "POST",
        url: "index.php?page=dailystat&generatedailystat=1",
        data: "day=" + encodeURIComponent(day),
        success: function (response) {
            if (response.error != "") {
                alert(response.error);
                return;
            }

            $(dayBox).html(response.html);
        }
    });
}

function editDailyStat(day) {
    let dayBox = $("#daybox"+day);

    $.ajax({
        type: "POST",
        url: "index.php?page=dailystat&getdailystateditor=1",
        data: "day=" + encodeURIComponent(day),
        success: function (response) {
            if (response.error != "") {
                alert(response.error);
                return;
            }

            $("#dailystattable").hide();
            $("#dailystateditor").show();
            $("#dailystateditor").html(response.html);
        }
    });
}

function saveDailyCalendar(day) {
    $.ajax({
        type: "POST",
        url: "index.php?page=dailystat&savedailystat=1",
        data: $("#dayform").serialize(),
        success: function (response) {
            $("#daybox"+day).html(response.html);
            reloadEvents();
        }
    });
}

function backToDailyCalendar() {
    $("#dailystattable").show();
    $("#dailystateditor").hide();
}


function deleteDailyStat(day) {
    if (!confirm("Biztos törlöd a " + day + " napi statisztikát?")) {
        return;
    }

    $.ajax({
        type: "POST",
        url: "index.php?page=dailystat",
        data: "day=" + encodeURIComponent(day) + "&deletedailystat=1",
        success: function (response) {
            $("#daybox"+day).html(response.html);
            reloadEvents();
        }
    });
}


function prepareDailyStatUpload(event) {
    let files = event.target.files;
    let day = $(this).data("day");

    $("#dailystatloader"+day).show();
    $("#datablock"+day).hide();

    event.stopPropagation();
    event.preventDefault();

    var data = new FormData();
    $.each(files, function (key, value) {
        data.append(key, value);
    });

    $.ajax({
        url: 'index.php?page=dailystat&adddailystatfiles&day='+encodeURIComponent(day),
        type: 'POST',
        data: data,
        cache: false,
        processData: false,
        contentType: false,
        success: function (response, textStatus, jqXHR) {
            $("#dailystatloader"+day).hide();
            $("#datablock"+day).show();

            if (response.error != "") {
                alert(response.error);
                return;
            }

            $("#daybox"+day).html(response.html);
            reloadEvents();
        }
    });
}

function DailyStatMoveMonth(offset) {
    $.ajax({
        url: 'index.php?page=dailystat&movemonth='+offset,
        type: 'GET',
        cache: false,
        processData: false,
        contentType: false,
        success: function (response, textStatus, jqXHR) {
            $("#dailystattable").html(response);
            reloadEvents();
        }
    });
}
