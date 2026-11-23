<?php /* Live domain search: availability via RDAP, add to cart. Needs $tlds (tld => cents). */ ?>
  <div class="dom-box" data-domain-tool data-tlds="<?= e(jenc(array_map('intval', $tlds))) ?>">
    <h3 style="margin:0 0 12px">Search your domain</h3>
    <form class="dom-search" data-dom-form><label class="sr" for="dom-q">Domain name</label><input id="dom-q" type="text" placeholder="mybusiness.ca" autocomplete="off" autocapitalize="none" spellcheck="false"><button class="btn btn-primary" type="submit">Search</button></form>
    <div class="tld-row"><span>Popular extensions:</span><?php foreach ($tlds as $t => $c): ?><button type="button" data-tld="<?= e($t) ?>">.<?= e($t) ?> <small><?= e(money((int)$c)) ?>/yr</small></button><?php endforeach; ?></div>
    <div class="dom-results" data-dom-results aria-live="polite"></div>
    <label class="ck agree" data-dom-agree hidden><input type="checkbox" data-agree><span>I agree to the <a href="/legal/terms" target="_blank" rel="noopener">Service Agreement</a> and <a href="/legal/refunds" target="_blank" rel="noopener">Refund Policy</a>.</span></label>
    <p class="fe" data-dom-err></p>
    <div class="dom-note">After payment your domain is registered and DNS records are set up for the email and website you order with us. WHOIS privacy is included where the registry allows it.</div>
  </div>
