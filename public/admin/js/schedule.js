$().ready(function() {
    Schedule.Init();
});

let ctrlDown = false;
$(window).on("keydown", function(event) {
    if (event.which === 17) {
        ctrlDown = true;
    }
}).on("keyup", function(event) {
    ctrlDown = false;
});


ScheduleNotification = {
    NofifyURL: "index.php?page=workschedule&subpage=notify",

    Start: function(){
        let sor = 0;

        $("#sendstartbutton").hide();

        setTimeout( function() {
            ScheduleNotification.NotifyWorker(sor);
        }, 100);
    },
    NotifyWorker: function (sor) {
        let workerId = 0;
        let smsnotif = 0;
        let emailnotif = 0;

        if ($("#notifrow"+sor).length) {
            workerId = $("#notifrow"+sor).data("workerid");
            smsnotif = 0;
            emailnotif = 0;
            if ($("#smscheck"+sor).prop('checked')) {
                smsnotif = 1;
            }
            if ($("#emailcheck"+sor).prop('checked')) {
                emailnotif = 1;
            }

            $.ajax({
                type: "POST",
                url: ScheduleNotification.NofifyURL,
                data: "notifybyworkerid=1&workerid="+workerId+"&smsnotif="+smsnotif+"&emailnotif="+emailnotif,
                success: function(data)	{
                    $("#notifresult"+sor).html(data);
                    sor++;

                    setTimeout( function() {
                        ScheduleNotification.NotifyWorker(sor);
                    }, 100);
                }
            });

            //alert("wid: "+workerId);
        }
    }
};

Schedule = {
    URL: "index.php?page=workschedule",
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
        $(".workerlink").draggable({"revert":true, helper: 'clone'});
        $(".sch_oszlopdatacell" ).droppable({
            classes: {
                "ui-droppable-hover": "sch_oszlopdatacell_hover"
            },
            accept: function(d) {
                let sourceDate = $(d).find("a").data("datum");
                let targetDate = $(this).data("datum");
                let sourceRole = $(d).find("a").data("roleid");
                let targetRole = $(this).data("roleid");
                let sourceType = $(d).find("a").data("tipusid");
                let targetType = $(this).data("tipusid");
                Schedule.CopySourceDate = sourceDate;
                if(sourceRole == targetRole && (sourceType != targetType || sourceDate != targetDate)) {
                    return true;
                }
            },
            drop: function(event, ui) {
                let sourceId = $(ui.draggable).find("a").data("mapid");
                Schedule.CopyWorker(sourceId, this);
            }
        });

        $( "#addszabadsagbutton" ).click(function() {
            let tol = $("#szabadsagtol").val();
            let ig = $("#szabadsagig").val();
            let workerId = $("#workerid").val();
            $.ajax({
                type: "POST",
                url: Schedule.URL,
                data: {addszabadsag:1, workerid:workerId, tol:tol, ig:ig},
                success: function(data)	{
                    if (data.status != "ok") {
                        alert(data.status);
                        return;
                    }
                    $("#workerdetail").html(data.message);
                    Schedule.Init();
                }
            });
        });

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
    ShowAddWorkerDialog: function(el) {
        Schedule.DialogId = el;
        let mapid   = $(el).data("mapid");
        let roleid  = $(el).data("roleid");
        let tipusid = $(el).data("tipusid");
        let napszak = $(el).data("napszak");
        let datum   = $(el).data("datum");
        $.ajax({
            type: "POST",
            url: Schedule.URL,
            data: "addworkerdialog=1&mapid="+mapid+"&roleid="+roleid+"&tipusid="+tipusid+"&napszak="+napszak+"&datum="+datum,
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
    AddWorker: function () {
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
    }
};



function fallbackCopyTextToClipboard(text) {
    var textArea = document.createElement("textarea");
    textArea.value = text;

    // Avoid scrolling to bottom
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";

    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
        var successful = document.execCommand('copy');
        var msg = successful ? 'successful' : 'unsuccessful';
        console.log('Fallback: Copying text command was ' + msg);
    } catch (err) {
        console.error('unable to copy', err);
    }

    document.body.removeChild(textArea);
}

function copyTextToClipboard(text) {
    if (!navigator.clipboard) {
        fallbackCopyTextToClipboard(text);
        return;
    }
    navigator.clipboard.writeText(text).then(function() {
        console.log('Async: Copying to clipboard was successful!');
    }, function(err) {
        console.error('Async: Could not copy text: ', err);
    });
}

function scrollToTopPos() {
    let pos = 0;
    $([document.documentElement, document.body]).animate({
        scrollTop: pos
    }, 500);
}

function confirmClearWeek() {
    if (confirm("Biztos törlöd ennek a hétnek az összes beosztását?")) {
        if (confirm("Egészen biztos?")) {
            if (confirm("Biztos? (ez az utolsó megerősítés)")) {
                return true;
            }
        }
    }
    return false;
}