$(document).ready(function () {
    reloadEvents();
});


function reloadEvents() {
    $(".dailystatfile").unbind("change");
    $(".dailystatfile").on("change", prepareDailyStatUpload);
}

function downloadDailyStat(dayFrom, dayTo) {
    $("#dailystatloader"+dayFrom).show();
    $("#datablock"+dayFrom).hide();

    $.ajax({
        type: "POST",
        url: "index.php?page=dailystat&downloaddailystat=1",
        data: "dayFrom=" + encodeURIComponent(dayFrom)+"&dayTo=" + encodeURIComponent(dayTo),
        success: function (response) {
            $("#dailystatloader"+dayFrom).hide();
            $("#datablock"+dayFrom).show();

            if (response.debughtml != "") {
                $("#debugarea").html(response.debughtml);
            }
            if (response.error != "") {
                $.toast({
                    heading: "Hiba",
                    text: response.error,
                    icon: 'error'
                });
                return;
            }

            window.location.href='index.php?page=dailystat&downloaddailystatfile='+encodeURIComponent(dayFrom)+"&dayTo="+encodeURIComponent(dayTo);
            return;
        }
    });
}

function downloadElojegyzesTable(dayFrom, dayTo) {
    window.location.href='index.php?page=dailystat&downloadelojegyzestable='+encodeURIComponent(dayFrom)+"&dayTo="+encodeURIComponent(dayTo);
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
            $.toast({
                text: 'Mentés sikerült',
                icon: 'success'
            })
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
            $.toast({
                text: 'Napi statisztika törölve',
                icon: 'success'
            });
        }
    });
}


function prepareDailyStatUpload(event) {
    let files = event.target.files;

    $("#dailystatloader").show();

    event.stopPropagation();
    event.preventDefault();

    var data = new FormData();
    $.each(files, function (key, value) {
        data.append(key, value);
    });

    $.ajax({
        url: 'index.php?page=dailystat&adddailystatfiles',
        type: 'POST',
        data: data,
        cache: false,
        processData: false,
        contentType: false,
        success: function (response, textStatus, jqXHR) {
            $("#dailystatloader").hide();

            if (response.error != "") {
                $.toast({
                    heading: "Hiba",
                    text: response.error,
                    icon: 'error',
                    hideAfter: 5000
                });
            } else {
                $.toast({
                    text: "A feltöltés sikerült",
                    icon: 'success'
                });
            }

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

function DailyStatMoveYear(offset) {
    $.ajax({
        url: 'index.php?page=monthlystat&moveyear='+offset,
        type: 'GET',
        cache: false,
        processData: false,
        contentType: false,
        success: function (response, textStatus, jqXHR) {
            $("#monthlystattable").html(response);
            reloadEvents();
        }
    });
}


function downloadMonthlyStat(day) {
    let dayBox = $("#daybox"+day);

    $.ajax({
        type: "POST",
        url: "index.php?page=monthlystat&downloadmonthlystat=1",
        data: "day=" + encodeURIComponent(day),
        success: function (response) {
            if (response.error != "") {
                $.toast({
                    heading: "Hiba",
                    text: response.error,
                    icon: 'info'
                });
                return;
            }
        }
    });
}

function downloadCompanyAndDoctorStat(year, month, debug) {
    let monthBox = $("#monthbox"+month);

    $.ajax({
        type: "POST",
        url: "index.php?page=monthlystat&downloadCompanyAndDoctorStat=1",
        data: "year=" + encodeURIComponent(year)+"&month=" + encodeURIComponent(month),
        success: function (response) {
            /*
            if (response.error != "") {
                $.toast({
                    heading: "Hiba",
                    text: response.error,
                    icon: 'info'
                });
                return;
            }

            $("#monthlystateditor").html(response.debug);

             */
        }
    });
}
