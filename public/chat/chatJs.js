function initChat(el) {
    let welcomeText   = el.data("welcometext");
    let supportName   = el.data("supportname");
    let supportTitle  = el.data("supporttitle");
    let supportAvatar = el.data("supportavatar");

    $.ajax({
        url: "/chat/chatTemplate.php",
        method: "POST",
        data: { initChat:1, welcomeText:welcomeText, supportName:supportName, supportTitle:supportTitle, supportAvatar:supportAvatar },
        success: function (response) {
            el.html(response);

            hideChat(0);
            hideChat(1);

            $('#prime').click(function() {
                toggleFab(3);
            });

            $('#chat_first_screen').click(function(e) {
                hideChat(1);
            });

            $('#chat_second_screen').click(function(e) {
                hideChat(2);
            });

            $('#chat_third_screen').click(function(e) {
                hideChat(3);
            });

            $('#chat_fourth_screen').click(function(e) {
                hideChat(4);
            });

            $('#chat_fullscreen_loader').click(function(e) {
                $('.fullscreen').toggleClass('zmdi-window-maximize');
                $('.fullscreen').toggleClass('zmdi-window-restore');
                $('.chat').toggleClass('chat_fullscreen');
                $('.fab').toggleClass('is-hide');
                $('.header_img').toggleClass('change_img');
                $('.img_container').toggleClass('change_img');
                $('.chat_header').toggleClass('chat_header2');
                $('.fab_field').toggleClass('fab_field2');
                $('.chat_converse').toggleClass('chat_converse2');
                //$('#chat_converse').css('display', 'none');
                // $('#chat_body').css('display', 'none');
                // $('#chat_form').css('display', 'none');
                // $('.chat_login').css('display', 'none');
                // $('#chat_fullscreen').css('display', 'block');
            });

            $("#fab_send").click(function(e) {
                sendChatMessage();
            });

            $("#chatSend").on('keyup', function (e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    sendChatMessage();
                }
            });

            self.setInterval("reloadChat()",5000);
        }
    });
}



//Toggle chat and links
function toggleFab() {
    $('.prime').toggleClass('zmdi-comment-outline');
    $('.prime').toggleClass('zmdi-close');
    $('.prime').toggleClass('is-active');
    $('.prime').toggleClass('is-visible');
    $('#prime').toggleClass('is-float');
    $('.chat').toggleClass('is-visible');
    $('.fab').toggleClass('is-visible');

}


function hideChat(hide) {
    switch (hide) {
        case 0:
            $('#chat_converse').css('display', 'none');
            $('#chat_body').css('display', 'none');
            $('#chat_form').css('display', 'none');
            $('.chat_login').css('display', 'block');
            $('.chat_fullscreen_loader').css('display', 'none');
            $('#chat_fullscreen').css('display', 'none');
            break;
        case 1:
            $('#chat_converse').css('display', 'block');
            $('#chat_body').css('display', 'none');
            $('#chat_form').css('display', 'none');
            $('.chat_login').css('display', 'none');
            $('.chat_fullscreen_loader').css('display', 'block');

            scrollToChatBottom();
            break;
        case 2:
            $('#chat_converse').css('display', 'none');
            $('#chat_body').css('display', 'block');
            $('#chat_form').css('display', 'none');
            $('.chat_login').css('display', 'none');
            $('.chat_fullscreen_loader').css('display', 'block');
            break;
        case 3:
            $('#chat_converse').css('display', 'none');
            $('#chat_body').css('display', 'none');
            $('#chat_form').css('display', 'block');
            $('.chat_login').css('display', 'none');
            $('.chat_fullscreen_loader').css('display', 'block');
            break;
        case 4:
            $('#chat_converse').css('display', 'none');
            $('#chat_body').css('display', 'none');
            $('#chat_form').css('display', 'none');
            $('.chat_login').css('display', 'none');
            $('.chat_fullscreen_loader').css('display', 'block');
            $('#chat_fullscreen').css('display', 'block');
            break;
    }
}

function scrollToChatBottom() {
    $('#chat_converse').scrollTop($('#chat_converse')[0].scrollHeight);
}


function sendChatMessage() {
    let message = $("#chatSend").val();
    $("#chatSend").val("");

    if (message.trim() == "") {
        return;
    }

    $.ajax({
        url: "/chat/chatTemplate.php",
        method: "POST",
        data: { sendmessage:1, message:message },
        success: function (response) {
            $("#chat_converse").html(response);
            scrollToChatBottom();
        }
    });
}


function reloadChat() {
    $.ajax({
        url: "/chat/chatTemplate.php",
        method: "POST",
        data: { reloadChat:1 },
        success: function (response) {
            let previousContent = $("#chat_converse").html();
            if (previousContent != response) {
                $("#chat_converse").html(response);
                scrollToChatBottom();
            }
        }
    });
}


