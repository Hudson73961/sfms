<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/services.php';
require __DIR__ . '/../app/layout.php';
$u = require_login('admin');
render_head('Messages', 'messages', ['bodyClass' => 'page-chat']);
?>
<div class="chat chat-split" id="chat" data-role="admin">
  <aside class="chat-side">
    <div class="chat-side-head"><h2>💬 Messages</h2><input id="conv-search" type="search" placeholder="🔍 Find a farmer…" aria-label="Find a farmer"></div>
    <div class="conv-list" id="conv-list"><p class="muted pad">Loading…</p></div>
  </aside>
  <section class="chat-main" id="chat-main">
    <header class="chat-head">
      <button class="btn btn-ghost btn-sm back-btn" id="back-btn" type="button" aria-label="Back to list">←</button>
      <div class="avatar avatar-lg" id="chat-peer-av">?</div>
      <div><strong id="chat-peer-name">Choose a farmer</strong><small id="chat-peer-sub"></small></div>
    </header>
    <div class="chat-scroll" id="chat-scroll" aria-live="polite"></div>
    <form class="chat-form" id="chat-form" autocomplete="off">
      <textarea id="chat-input" rows="1" maxlength="2000" placeholder="Type your reply…" aria-label="Message"></textarea>
      <button class="btn send-btn" type="submit" aria-label="Send">➤</button>
    </form>
  </section>
</div>
<?php render_foot(['messages.js']); ?>
