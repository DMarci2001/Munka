var enableChatSessionListRefresh = true;

$(document).ready(function () {
    self.setInterval("chatWindowRefresh()",3000);
    self.setInterval("chatSessionListRefresh()",10000);
    loadChatWindow(0, true);

    $("#chatmessagetext").on('keyup', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            sendChatMessage();
        }
    });
});


function loadChatWindow(sessionId, scroll) {
    $.ajax({
        url: "index.php?page=chat",
        method: "POST",
        data: { loadChatWindow:1, sessionId:sessionId },
        success: function (response) {
            $("#chatsessionitems").html(response.html);
            if (scroll) {
                scrollToChatBottom();
                chatSessionListRefresh();
            }
        }
    });
}

function loadChatWindowMain(sessionId, scroll) {
    $.ajax({
        url: "index.php?page=chat",
        method: "POST",
        data: { loadChatWindowMain:1, sessionId:sessionId },
        success: function (response) {
            $("#chatwindow").html(response.html);
            $("#chatwindow").show();
            if (scroll) {
                scrollToChatBottom();
                //chatSessionListRefresh();
            }
        }
    });
}

function hideChatPopup() {
    $("#chatwindow").html("");
}

function chatWindowRefresh() {
    loadChatWindow(0, false);
}

function chatSessionListRefresh() {
    if (!enableChatSessionListRefresh) {
        return;
    }

    $.ajax({
        url: "index.php?page=chat",
        method: "POST",
        data: { chatSessionList:1 },
        success: function (response) {
            $("#chatsessionlist").html(response.html);
        }
    });
}


function scrollToChatBottom() {
    //$('#chatsessionitems').scrollTop($('#chatsessionitems')[0].scrollHeight);
}

function sendChatMessage() {
    let message = $("#chatmessagetext").val();
    $("#chatmessagetext").val("");

    if (message.trim() == "") {
        return;
    }

    $.ajax({
        url: "index.php?page=chat",
        method: "POST",
        data: { sendmessage:1, message:message },
        success: function (response) {
            loadChatWindow(0, true);
        }
    });
}

function chatFastText(text) {
    $("#chatmessagetext").val(text);
}

function openChatSessionEditor(div, id, pub) {
    closeChatSessionEditors();
    enableChatSessionListRefresh = false;
    $.ajax({
        url: "index.php?page=chat",
        method: "POST",
        data: { opensessioneditor:1, id:id, pub:pub },
        success: function (response) {
            $("#"+div).html(response);
        }
    });
}

function closeChatSessionEditors() {
    enableChatSessionListRefresh = true;
    $(".sessioneditordiv").html("");
}

function addNewChatSession() {
    let id = $("#editedsessionid").val();
    let pub = $("#editedsessionpublic").val();

    $.ajax({
        url: "index.php?page=chat",
        method: "POST",
        data: { savechatsession:1, id:id, pub:pub, title:$("#editedsessiontitle").val() },
        success: function (response) {
            closeChatSessionEditors();
            chatSessionListRefresh();
        }
    });
}


function toggleChatSessionUser(el) {
    let sessionId = $(el).data("chatsessionid");
    let userId = $(el).data("userid");
    let aktiv = $(el).data("aktiv");

    $.ajax({
        method: "POST",
        url: "index.php?page=chat",
        data: { toggleChatUserSession: sessionId, userId: userId, aktiv:aktiv },
        success: function (response) {
            $("#sessionusers"+sessionId).html(response);
        }
    });
}

function closeChatSession(id) {
    if (confirm("Biztos lezárod a chat ablakot?")) {
        window.location.href = "index.php?page=chat&closechat&id=" + id;
    }
}

