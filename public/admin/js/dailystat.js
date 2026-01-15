let runningDailyStatButtonText = "";

$(document).ready(function () {
    reloadEvents();
});


function reloadEvents() {
    $(".dailystatfile").unbind("change");
    $(".dailystatfile").on("change", prepareDailyStatUpload);
}


function downloadDailyStat(el, dayFrom, dayTo) {
    runningDailyStatButtonText = $(el).html();
    $(el).html("<img style='height:15px;' src='/images/loading_transparent_white.svg' />");

    $.ajax({
        type: "POST",
        url: "index.php?page=dailystat&downloaddailystat=1",
        data: "dayFrom=" + encodeURIComponent(dayFrom)+"&dayTo=" + encodeURIComponent(dayTo),
        success: function (response) {
            $(el).html(runningDailyStatButtonText);
            runningDailyStatButtonText = "";

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
        },
        error: function (response) {
            $(el).html(runningDailyStatButtonText);
            runningDailyStatButtonText = "";
            $.toast({
                heading: "Hiba",
                text: "A file létrehozása közben hiba történt!",
                icon: 'error'
            });
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

function vehicleMoveMonth(offset) {
    $.ajax({
        url: 'index.php?page=vehicles&movemonth='+offset,
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



Vehicle = {
    URL: "index.php?page=vehicles",
    WorkerURL: "index.php?page=workschedule&subpage=workers",
    WorkplaceURL: "index.php?page=workschedule&subpage=workplaces",
    DialogCloseHTML: "<div id='dialogclose' style='width:20px;height:20px;float:right;'></div>",
    DialogId: "",
    CopySourceDate: "",

    Init: function(){
        $( "#dialogclose" ).click(function() {
            $(".sch_dialog").hide();
        });

        $(".sch_dialog").draggable();
    },
    DeleteSzabadsag: function(groupId) {
        let workerId = $("#workerid").val();
        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: {deleteszabadsag:1, groupid:groupId, workerid:workerId},
            success: function(data)	{
                if (data.status != "ok") {
                    alert(data.message);
                    return;
                }
                $("#workerdetail").html(data.message);
                Schedule.Init();
            }
        });
    },
    ShowVehicleDialog: function(el) {
        Vehicle.DialogId = $(el).data("div");
        let mapid   = $(el).data("mapid");
        let datum   = $(el).data("datum");
        $.ajax({
            type: "POST",
            url: Vehicle.URL,
            data: "showvehicledialog=1&mapid="+mapid+"&datum="+datum,
            success: function(data)	{
                let position = $(el).offset();
                let left = position.left + 15;

                $(".sch_dialogcontent").html(data);
                $(".sch_dialogtop").html(datum+Vehicle.DialogCloseHTML);
                Vehicle.Init();
                $(".sch_dialog").show();

                let width = $(".sch_dialog").width();
                let winWidth = $(window).width();
                if (left + width > winWidth) {
                    left = winWidth - width;
                }

                $(".sch_dialog").css("top", position.top + 15);
                $(".sch_dialog").css("left", left);
            }
        });
    },
    ShowAddPlaceDialog: function(el) {
        Schedule.DialogId = el;
        let tipusid = $(el).data("tipusid");
        let datum   = $(el).data("datum");
        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: "addplacedialog=1&tipusid="+tipusid+"&datum="+datum,
            success: function(data)	{
                let position = $(el).offset();
                let left = position.left + 15;

                $(".sch_dialogcontent").html(data);
                $(".sch_dialogtop").html($(el).data("tipusnev")+Schedule.DialogCloseHTML);
                Schedule.Init();
                $(".sch_dialog").show();

                let width = $(".sch_dialog").width();
                let winWidth = $(window).width();
                if (left + width > winWidth) {
                    left = winWidth - width;
                }

                $(".sch_dialog").css("top", position.top + 15);
                $(".sch_dialog").css("left", left);
            }
        });
    },
    ShowCollisions: function() {
        $("#collisionsdiv").toggle();

        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: {showcollisions:1},
            success: function(data)	{
                $("#collisionsdiv").html(data.message);
            }
        });
    },
    AddCompanyForDay: function(day) {
        let companyName = $("#companyname"+day).val();
        let companyAddress = $("#companyaddress"+day).val();
        let companyComment = $("#companycomment"+day).val();

        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: {addcompanyforday:1, companyname:companyName, companyaddress:companyAddress, companycomment:companyComment, day:day},
            success: function(data)	{
                if (data.status != "ok") {
                    alert(data.message);
                    return;
                }
                $("#daycontainer"+day).html(data.message);
                $(".sch_dialog").hide();
                Schedule.Init();
            }
        });
    },
    SavePlaceForDay: function(placeId) {
        let companyName = $("#companynameeditor").val();
        let companyAddress = $("#companyaddresseditor").val();
        let companyComment = $("#companycommenteditor").val();
        let day = $("#companydayeditor").val();

        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: {savecompanyforday:1, companyname:companyName, companyaddress:companyAddress, companycomment:companyComment, id:placeId, day:day},
            success: function(data)	{
                if (data.status != "ok") {
                    alert(data.message);
                    return;
                }
                $("#daycontainer"+day).html(data.message);
                $(".sch_dialog").hide();
                Schedule.Init();
            }
        });
    },
    DeleteWorkplaceForDay: function(id, day) {
        if (!confirm("Biztos törlöd ezt a céget erről a napról?")) {
            return;
        }

        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: {deleteworkplaceforday:1, id:id, day:day},
            success: function(data)	{
                if (data.status != "ok") {
                    alert(data.message);
                    return;
                }
                $("#daycontainer"+day).html(data.message);
                $(".sch_dialog").hide();
                Schedule.Init();
            }
        });
    },
    AddVehicle: function () {
        let params = $("#dialogform").serialize();
        $.ajax({
            type: "POST",
            url: Vehicle.URL,
            data: "addvehicle=1&"+params,
            success: function(data)	{
                if (data.status != "ok") {
                    alert(data.message);
                    return;
                }
                $("#"+$(Vehicle.DialogId)).html(data.message);
                $(".sch_dialog").hide();
                Vehicle.Init();
            }
        });
    },
    AddPlace: function () {
        let params = $("#dialogform").serialize();
        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: "addworker=1&"+params,
            success: function(data)	{
                if (data.status != "ok") {
                    alert(data.message);
                    return;
                }
                $("#daycontainer"+$(Schedule.DialogId).data("datum")).html(data.message);
                $(".sch_dialog").hide();
                Schedule.Init();
            }
        });
    },
    DeleteWorkerMap: function () {
        let params = $("#dialogform").serialize();
        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: "deleteworkermap=1&"+params,
            success: function(data)	{
                if (data.status != "ok") {
                    alert(data.message);
                    return;
                }
                $("#daycontainer"+$(Schedule.DialogId).data("datum")).html(data.message);
                $(".sch_dialog").hide();
                Schedule.Init();
            }
        });
    },
    DeleteWorker: function () {
        if (!confirm("Biztos törli ezt a munkatársat?")) {
            return;
        }
        let params = $("#workerform").serialize();
        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: "deleteworker=1&"+params,
            success: function(data)	{
                $("#workerlist").html(data);
                $("#workerdetail").html("");
            }
        });
    },
    DeleteWorkplace: function () {
        if (!confirm("Biztos törli ezt a munkahelyet?")) {
            return;
        }
        let params = $("#workplaceform").serialize();
        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: "deleteworkplace=1&"+params,
            success: function(data)	{
                $("#workplacelist").html(data);
                $("#workplacedetail").html("");
            }
        });
    },
    OrderWorkplace: function (direction, id) {
        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: {orderworkplace:1, direction:direction, id:id},
            success: function(data)	{
                $("#workplacelist").html(data);
            }
        });
    },
    SaveWorker: function () {
        let params = $("#workerform").serialize();
        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: "saveworker=1&"+params,
            success: function(data)	{
                $("#workerlist").html(data.list);
                $("#workerdetail").html(data.detail);
            }
        });
    },
    SaveWorkplace: function () {
        let params = $("#workplaceform").serialize();
        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: "saveworkplace=1&"+params,
            success: function(data)	{
                $("#workplacelist").html(data.list);
                $("#workplacedetail").html(data.detail);
            }
        });
    },
    CopyWorker: function (sourceId, targetId) {
        let roleid    = $(targetId).data("roleid");
        let tipusid   = $(targetId).data("tipusid");
        let napszak   = $(targetId).data("napszak");
        let datum     = $(targetId).data("datum");
        let operation = ctrlDown ? "copy":"move";

        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: "copyworker=1&sourceid="+sourceId+"&roleid="+roleid+"&tipusid="+tipusid+"&napszak="+napszak+"&datum="+datum+"&operation="+operation,
            success: function(data)	{
                if (data.status != "ok") {
                    alert(data.message);
                    return;
                }
                $("#daycontainer"+datum).html(data.message);
                if (data.messageSource != "") {
                    $("#daycontainer" + Schedule.CopySourceDate).html(data.messageSource);
                }
                $(".sch_dialog").hide();
                Schedule.Init();
            }
        });
    },
    OpenWorkerDetail: function (id) {
        $.ajax({
            type: "POST",
            url: Schedule.WorkerURL,
            data: "openworkerdetail=1&id="+id,
            success: function(data)	{
                $("#workerdetail").html(data);
                scrollToTopPos();
                Schedule.Init();
            }
        });
    },
    AddNewWorker: function (roleId) {
        $.ajax({
            type: "POST",
            url: Schedule.WorkerURL,
            data: "addnewworker=1&roleid="+roleId,
            success: function(data)	{
                $("#workerlist").html(data);
            }
        });
    },
    OpenWorkplaceDetail: function (id) {
        $.ajax({
            type: "POST",
            url: Schedule.WorkplaceURL,
            data: "openworkplacedetail=1&id="+id,
            success: function(data)	{
                $("#workplacedetail").html(data);
                scrollToTopPos();
            }
        });
    },
    AddNewWorkplace: function (roleId, kulso) {
        $.ajax({
            type: "POST",
            url: Schedule.WorkplaceURL,
            data: "addnewworkplace=1&roleid="+roleId+"&kulso="+kulso,
            success: function(data)	{
                $("#workplacelist").html(data);
            }
        });
    },
    CopyURL: function () {
        let copyText = $("#copylink").data("url");
        copyTextToClipboard(copyText);
        alert("URL vágólapra másolva");
    },


    ShowAddWorkerVacationDialog: function(el) {
        Schedule.DialogId = el;
        let datum = $(el).data("datum");

        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: "addworkerdialogszabi=1&datum="+datum,
            success: function(data)	{
                let position = $(el).offset();
                let left = position.left + 15;

                $(".sch_dialogcontent").html(data);
                $(".sch_dialogtop").html(datum+Schedule.DialogCloseHTML);
                Schedule.Init();
                $(".sch_dialog").show();

                let width = $(".sch_dialog").width();
                let winWidth = $(window).width();
                if (left + width > winWidth) {
                    left = winWidth - width;
                }

                $(".sch_dialog").css("top", position.top + 15);
                $(".sch_dialog").css("left", left);
            }
        });
    },

    AddWorkerVacation: function () {
        let params = $("#dialogform").serialize();
        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: "addworkervacation=1&"+params,
            success: function(data)	{
                if (data.status != "ok") {
                    alert(data.message);
                    return;
                }
                $("#szabirow"+$(Schedule.DialogId).data("datum")).html(data.message);
                $(".sch_dialog").hide();
            }
        });
    },
    DeleteWorkerVacation: function (day, id) {
        if (!confirm("Biztos törlöd ezt a szabadságot?")) {
            return;
        }
        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: "deleteworkervacation=1&datum="+day+"&id="+id,
            success: function(data)	{
                $("#szabirow"+day).html(data.message);
            }
        });
    },
    SetVacationStatus: function (day, id, status) {
        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: "setvacationstatus=1&datum="+day+"&id="+id+"&status="+status,
            success: function(data)	{
                if (data.error != "") {
                    $.toast({
                        text: data.error,
                        icon: "error"
                    });

                }
                $("#szabirow"+day).html(data.message);
            }
        });
    },

};


