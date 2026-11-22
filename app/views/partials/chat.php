<?php
$area = App\ChatService::area($path ?? '/');
$sugs = App\ChatService::SUGGESTIONS[$area] ?? App\ChatService::SUGGESTIONS['default'];
$aiLive = App\Ai::available('anthropic') || App\Ai::available('openai');
?>
<button class="chat-fab" id="chat-fab" type="button" aria-controls="chat-panel"><span class="pulse"></span> <?= !empty($user) ? 'Ask the assistant' : 'Tell us what you need' ?></button>
<section class="chat-panel" id="chat-panel" role="dialog" aria-label="818 AI assistant" data-service="<?= e($chat_service ?? '') ?>" data-suggestions="<?= e(jenc($sugs)) ?>"
  data-greeting="<?= e(($user ?? null) ? 'Hi ' . explode(' ', (string)$user['name'])[0] . '! I can check your orders and projects, answer questions or set up something new.' : "Hi! I'm the 818 assistant. Tell me about your business and what you need — I'll recommend services, show exact pricing and can set everything up.") ?>">
  <div class="chat-top"><div><b>818 Assistant</b><small><?= $aiLive ? 'AI · a human reviews anything important' : 'Basic mode · our team will follow up' ?></small></div>
    <button type="button" id="chat-new" title="New conversation" aria-label="Start a new conversation">↺</button><button type="button" id="chat-close" aria-label="Close chat">×</button></div>
  <div class="chat-log" id="chat-log" aria-live="polite"></div>
  <div class="chat-actions" id="chat-actions"></div>
  <form class="chat-form" id="chat-form"><label class="sr" for="chat-input">Message</label><textarea id="chat-input" rows="1" maxlength="2000" placeholder="Ask anything…"></textarea><button class="btn btn-primary" type="submit">Send</button></form>
  <div class="chat-legal">AI can make mistakes; prices come from our live catalog. <a href="/legal/ai">How we use AI</a> · <a href="/contact">Talk to a person</a></div>
</section>
