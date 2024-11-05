var enableChatSessionListRefresh = false;

$(document).ready(function () {
    self.setInterval("chatWindowRefresh()",3000);
    self.setInterval("chatSessionListRefresh()",10000);
    loadChatWindow(0, true);
});


function initChatEnterKey() {
    $("#chatmessagetext").on('keyup', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            sendChatMessage();
        }
    });
}

function newChatSession() {
    $.ajax({
        url: "index.php?page=chat",
        method: "POST",
        data: { newchatsession:1 },
        success: function (response) {
            $("#chatwindow").html(response.chatmain);
            $("#chatwindow").show();
            initChatEnterKey();
        }
    });
}

function loadChatWindow(sessionId, scroll) {
    if ($("#chatsessionitems").length == 0) {
        return;
    }

    $.ajax({
        url: "index.php?page=chat",
        method: "POST",
        data: { loadChatWindow:1, sessionId:sessionId },
        success: function (response) {
            $("#chatsessionitems").html(response.html);
            if (response.new == 1) {
                scrollToChatBottom();
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
            $("#chatsessionlist").html(response.sessionlist);
            $("#chatwindow").show();
            if (scroll) {
                scrollToChatBottom();
                //chatSessionListRefresh();
            }
            initChatEnterKey();
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
    $('#chatsessionitems').scrollTop($('#chatsessionitems')[0].scrollHeight);
}

function sendChatMessage() {
    let message = $("#chatmessagetext").val();
    $("#chatmessagetext").val("");

    if (message.trim() == "") {
        return;
    }

    $.ajax({
        url: "index.php?page=chat&newmessage",
        method: "POST",
        data: { sendmessage:1, message:message },
        success: function (response) {
            $("#chatsessionitems").html(response.messages);
            $("#chatsessionlist").html(response.sessionlist);
            scrollToChatBottom();
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
            //$("#sessionusers"+sessionId).html(response);
            $("#chatwindow").html(response.chatmain);
            $("#chatsessionlist").html(response.sessionlist);
            scrollToChatBottom();
            initChatEnterKey();
        }
    });
}

function closeChatSession(id) {
    if (confirm("Biztos lezárod a chat ablakot?")) {
        window.location.href = "index.php?page=chat&closechat&id=" + id;
    }
}

