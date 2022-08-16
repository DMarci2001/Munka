<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);


session_start();

require __DIR__."/ChatEngine.php";


$chatEngine = new ChatEngine();
$chatEngine->processAjaxRequests();


?><head>
    <meta charset="UTF-8">
    <title>Chat</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel='stylesheet' type='text/css' href='chatStyle.css?v=11' />
</head>

<body>
<p>Kattints a sarokban a chatre...</p>

<div class="fabs">
    <div class="chat">
        <div class="chat_header">
            <div class="chat_option">
                <div class="header_img">
                    <img src="https://st4.depositphotos.com/7877830/25337/v/380/depositphotos_253374286-stock-illustration-vector-illustration-male-doctor-avatar.jpg?forcejpeg=true"/>
                </div>
                <span id="chat_head"><?php echo $chatEngine->supportName; ?></span> <br> <span class="agent"><?php echo $chatEngine->supportTitle; ?></span> <span class="online">(Online)</span>
                <span id="chat_fullscreen_loader" class="chat_fullscreen_loader"><i class="fullscreen zmdi zmdi-window-maximize"></i></span>

            </div>

        </div>
        <div id="chat_converse" class="chat_conversion chat_converse"><?php echo $chatEngine->generateChatContentHTML(); ?></div>
        <div id="chat_form" class="chat_converse chat_form">
            <a id="chat_fourth_screen" class="fab"><i class="zmdi zmdi-arrow-right"></i></a>
            <span class="chat_msg_item chat_msg_item_admin">
            <div class="chat_avatar">
               <img src="http://res.cloudinary.com/dqvwa7vpe/image/upload/v1496415051/avatar_ma6vug.jpg"/>
            </div>Hey there! Any question?</span>
            <span class="chat_msg_item chat_msg_item_user">
            Hello!</span>
            <span class="status">20m ago</span>
            <span class="chat_msg_item chat_msg_item_admin">
            <div class="chat_avatar">
               <img src="http://res.cloudinary.com/dqvwa7vpe/image/upload/v1496415051/avatar_ma6vug.jpg"/>
            </div>Agent typically replies in a few hours. Don't miss their reply.
            <div>
              <br>
              <form class="get-notified">
                  <label for="chat_log_email">Get notified by email</label>
                  <input id="chat_log_email" placeholder="Enter your email"/>
                  <i class="zmdi zmdi-chevron-right"></i>
              </form>
            </div></span>

            <span class="chat_msg_item chat_msg_item_admin">
            <div class="chat_avatar">
               <img src="http://res.cloudinary.com/dqvwa7vpe/image/upload/v1496415051/avatar_ma6vug.jpg"/>
            </div>Send message to agent.
            <div>
              <form class="message_form">
                  <input placeholder="Your email"/>
                  <input placeholder="Technical issue"/>
                  <textarea rows="4" placeholder="Your message"></textarea>
                  <button>Send</button>
              </form>

        </div></span>
        </div>
        <div id="chat_fullscreen" class="chat_conversion chat_converse">
      <span class="chat_msg_item chat_msg_item_admin">
            <div class="chat_avatar">
               <img src="http://res.cloudinary.com/dqvwa7vpe/image/upload/v1496415051/avatar_ma6vug.jpg"/>
            </div>Hey there! Any question?</span>
            <span class="chat_msg_item chat_msg_item_user">
            Hello!</span>
            <div class="status">20m ago</div>
            <span class="chat_msg_item chat_msg_item_admin">
            <div class="chat_avatar">
               <img src="http://res.cloudinary.com/dqvwa7vpe/image/upload/v1496415051/avatar_ma6vug.jpg"/>
            </div>Hey! Would you like to talk sales, support, or anyone?</span>
            <span class="chat_msg_item chat_msg_item_user">
            Lorem Ipsum is simply dummy text of the printing and typesetting industry.</span>
            <span class="chat_msg_item chat_msg_item_admin">
            <div class="chat_avatar">
               <img src="http://res.cloudinary.com/dqvwa7vpe/image/upload/v1496415051/avatar_ma6vug.jpg"/>
             </div>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book.</span>
            <span class="chat_msg_item chat_msg_item_user">
            Where can I get some?</span>
            <span class="chat_msg_item chat_msg_item_admin">
            <div class="chat_avatar">
               <img src="http://res.cloudinary.com/dqvwa7vpe/image/upload/v1496415051/avatar_ma6vug.jpg"/>
             </div>The standard chuck...</span>
            <span class="chat_msg_item chat_msg_item_user">
            There are many variations of passages of Lorem Ipsum available</span>
            <div class="status2">Épp most, még nem látták</div>
            <span class="chat_msg_item ">
          <ul class="tags">
            <li>Hats</li>
            <li>T-Shirts</li>
            <li>Pants</li>
          </ul>
      </span>
        </div>
        <div class="fab_field">
            <a id="fab_send" class="fab"><i title='Üzenet küldése' class="zmdi zmdi-mail-send"></i></a>
            <textarea id="chatSend" name="chat_message" placeholder="Írja be az üzenetét..." class="chat_field chat_message"></textarea>
        </div>
    </div>
    <a id="prime" class="fab"><i class="prime zmdi zmdi-comment-outline"></i></a>
</div>
<script src='/js/jquery/jquery.js'></script>

<script src="chatJs.js"></script>

</body>
