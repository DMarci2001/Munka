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


function chatWindowRefresh() {
    loadChatWindow(0, false);
}

function chatSessionListRefresh() {
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