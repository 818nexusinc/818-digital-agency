<section class="news-band" id="news" aria-labelledby="news-h"><div class="wrap">
  <div class="nb-copy"><h2 id="news-h"><?= e(t('Be first to know.')) ?></h2><p><?= e(t('New guides, services and launch offers for Canadian entrepreneurs. One short email a month at most.')) ?></p></div>
  <div class="nb-form"><?php if (!empty($_GET['subscribed'])): ?><p class="nb-ok" role="status"><?= e(t("Thanks! You're on the list. You can unsubscribe anytime from any email.")) ?></p>
  <?php else: ?><form method="post" action="/subscribe"><?= App\Csrf::field() ?><label class="sr" for="news-email"><?= e(t('Email address')) ?></label><input id="news-email" type="email" name="email" required placeholder="you@yourbusiness.ca" autocomplete="email"><input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true"><button class="btn"><?= e(t('Subscribe')) ?></button></form>
  <small><?= e(t('By subscribing you agree to receive occasional emails from 818 Nexus Inc. Unsubscribe anytime.')) ?></small><?php endif; ?></div>
</div></section>
