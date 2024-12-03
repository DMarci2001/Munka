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

    $("#chatmessagetext").bind("paste", function(e) {
        for (var i = 0 ; i < e.originalEvent.clipboardData.items.length ; i++) {
            var item = e.originalEvent.clipboardData.items[i];
            if (item.type.indexOf("image") != -1) {
                uploadChatImage(item.getAsFile());
            }
        }
    });
}

function finalizeImageUpload() {
    $.ajax({
        url: "index.php?page=chat&finalizeupload",
        method: "POST",
        data: { finalizeImageUpload:1 },
        success: function (response) {
            $("#chatwindow").html(response.messages);
            $("#chatsessionlist").html(response.sessionlist);
            scrollToChatBottom();
        }
    });
}

function deleteUploadedTempFile() {
    $.ajax({
        url: "index.php?page=chat",
        method: "POST",
        data: { deleteUploadedTempFile:1 },
        success: function (response) {
            $("#chatsessionuploads").html(response);
        }
    });
}


function uploadChatImage(file) {
    var xhr = new XMLHttpRequest();

    xhr.onload = function() {
        if (xhr.status == 200) {
            $("#chatsessionuploads").html(xhr.responseText);
        } else {
            alert("Error! Upload failed");
        }
    };

    xhr.onerror = function() {
        alert("Error! Upload failed. Can not connect to server.");
    };

    xhr.open("POST", "index.php?page=chat&chatimageupload", true);
    xhr.setRequestHeader("Content-Type", file.type);
    xhr.send(file);
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

function editChatSession(id) {
    $.ajax({
        url: "index.php?page=chat",
        method: "POST",
        data: { editchatsession:id },
        success: function (response) {
            $("#chatwindow").html(response.chatmain);
            $("#chatwindow").show();
        }
    });
}

function loadChatWindow(sessionId, scroll) {
    if ($("#chatsessionitems").length == 0) {
        return;
    }

    $.ajax({
        url: "index.php?page=chat&windowrefresh",
        method: "POST",
        data: { loadChatWindow:1, sessionId:sessionId },
        success: function (response) {
            $("#chatsessionitems").html(response.html);
            if (response.new == 1) {
                scrollToChatBottom();
                $("#chatsessionlist").html(response.sessionlist);
            }
        }
    });
}

function archiveChatWindow(sessionId) {
    $.ajax({
        url: "index.php?page=chat&windowarchive",
        method: "POST",
        data: { archiveChatWindow:1, sessionId:sessionId },
        success: function (response) {
            $("#chatsessionlist").html(response.sessionlist);
        }
    });
}

function loadChatWindowMain(sessionId, scroll) {
    let chatGroupTitle = $("#chatgrouptitle").val() ? $("#chatgrouptitle").val() : "null";

    $.ajax({
        url: "index.php?page=chat",
        method: "POST",
        data: { loadChatWindowMain:1, sessionId:sessionId, chatGroupTitle:chatGroupTitle },
        success: function (response) {
            $("#chatwindow").html(response.html);
            $("#chatsessionlist").html(response.sessionlist);
            $("#chatwindow").show();
            if (scroll) {
                scrollToChatBottom();
                $("#chatsessionlist").html(response.sessionlist);
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
    let message = $("#chatmessagetext").val().trim();
    $("#chatmessagetext").val("");

    if (message == "") {
        return;
    }

    $.ajax({
        url: "index.php?page=chat&newmessage",
        method: "POST",
        data: { sendmessage:1, message:message },
        success: function (response) {
            $("#chatwindow").html(response.messages);
            $("#chatsessionlist").html(response.sessionlist);
            scrollToChatBottom();
        }
    });
}

function chatFastText(text) {
    $("#chatmessagetext").val(text);
}


function addChatUserToSession(el) {
    let sessionId = $(el).data("chatsessionid");
    let userId = $(el).data("userid");
    let aktiv = $(el).data("aktiv");
    let group = $("#chatgroupdata").length != 0;

    $.ajax({
        method: "POST",
        url: "index.php?page=chat",
        data: { addChatUserToSession: sessionId, userId: userId, aktiv:aktiv },
        success: function (response) {
            $("#chatsessionlist").html(response.sessionlist);
            $("#sessionusers"+sessionId).html(response.userbuttons);
            if (!group) {
                $("#chatwindow").html(response.chatmain);
                scrollToChatBottom();
                initChatEnterKey();
            }
        }
    });
}

function closeChatSession(id) {
    if (confirm("Biztos lezárod a chat ablakot?")) {
        window.location.href = "index.php?page=chat&closechat&id=" + id;
    }
}

