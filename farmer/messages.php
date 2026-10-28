<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/services.php';
require __DIR__ . '/../app/layout.php';
$u = require_login('farmer');
render_head('Messages', 'messages', ['bodyClass' => 'page-chat']);
?>
<div class="chat chat-single" id="chat" data-role="farmer">
  <section class="chat-main is-open">
    <header class="chat-head">
      <div class="avatar avatar-lg">🧑‍🌾</div>
      <div><strong>Farm Office</strong><small>Administrator · replies as soon as possible</small></div>
    </header>
    <div class="chat-scroll" id="chat-scroll" aria-live="polite"></div>
    <form class="chat-form" id="chat-form" autocomplete="off">
      <textarea id="chat-input" rows="1" maxlength="2000" placeholder="Type your message…" aria-label="Message"></textarea>
      <button class="btn send-btn" type="submit" aria-label="Send">➤</button>
    </form>
  </section>
</div>
<?php render_foot(['messages.js']); ?>
