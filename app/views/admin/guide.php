<?php
/* Plain-language guide to the AI workforce. Facts about tools and autonomy come live from the registry; the "what to expect" text is written here. */
$G = [
 'Customer-facing' => ['#f472b6', [
   'sales' => ['Website chat on service pages, and anyone asking about prices.', 'Short answers with real catalog prices, a suggested bundle, a ready cart link or proposal, and a lead in Sell → Leads when the visitor leaves an email.', 'Nothing in normal use. Discounts and custom pricing come to you.'],
   'concierge' => ['The chat bubble on every page and the customer portal assistant.', 'Answers about services, order and project status for signed-in customers, missing details collected, support tickets opened. Hands off to a person when asked.', 'Nothing in normal use; it can only read the signed-in customer\'s own data.'],
   'support' => ['Every new support ticket.', 'A first reply within minutes using the knowledge base. Refunds, billing disputes, legal questions and upset customers are escalated to you instead of answered.', 'Any refund or exception — it escalates rather than decides.'],
 ]],
 'Registrations & filings' => ['#818cf8', [
   'registration' => ['A paid registration order (sole proprietorship, partnership, corporation, co-op).', 'A checked filing package and checklist on the project, and questions sent to the customer when something is missing.', 'Anything legally sensitive. It never submits; it prepares and asks.'],
   'filing' => ['A filing that is paid, validated and authorized at checkout.', 'A clerk-style review of name, addresses, directors and shares, then submission (automatic for BC, assisted portal filing elsewhere) and tracking until documents arrive.', 'Provinces you set to owner approval, anything it flags as unusual, and every filing above the daily cap.'],
 ]],
 'Delivery' => ['#2dd4bf', [
   'project_manager' => ['Every paid order that becomes a project.', 'Milestones, specialist tasks assigned, deadline tracking and short factual updates emailed to the customer.', 'Deadline changes that affect a customer promise.'],
   'website_dev' => ['Website projects once requirements are in.', 'Sitemap, page outlines and build specifications as project deliverables. Previews only.', 'Any deployment or change to a live site.'],
   'web_app' => ['Custom web application projects.', 'Technical specification, data model, API design, test plan and release notes, with security and privacy called out.', 'Scope or price changes.'],
   'brand' => ['Brand or logo work on a project.', 'Concept directions, naming ideas, visual briefs and brand guidelines as drafts.', 'Nothing directly; drafts go through QA and you.'],
   'qa' => ['Every deliverable another agent produces.', 'A pass or a specific list of defects. Failed work is sent back to the agent that made it.', 'Nothing; it cannot approve its own work or anyone\'s release.'],
 ]],
 'Marketing & growth' => ['#fb923c', [
   'seo' => ['Content requests and the weekly blog plan.', 'Keyword and intent notes, titles, meta descriptions, schema and AI-search suggestions as drafts.', 'Publishing — drafts only.'],
   'content' => ['The weekly blog engine and content requests.', 'Articles, service-page copy and newsletters built only from your catalog and knowledge base. Weekly articles publish automatically unless you switch on review.', 'Every newsletter send. Articles when review mode is on.'],
   'social' => ['New articles and campaigns.', 'Ready-to-post LinkedIn, X and Facebook copy. It does not post anywhere by itself.', 'Posting — copy goes to you or your scheduler via the RSS feed.'],
   'growth' => ['When asked, or from the owner assistant.', 'Ranked opportunities and pricing experiment ideas with the numbers behind them.', 'Any price change.'],
   'retention' => ['Daily, looking at inactive customers and renewals.', 'A list of at-risk customers and draft follow-ups that respect consent and opt-outs.', 'Sending anything to a customer who has not opted in.'],
   'research' => ['When asked.', 'Opportunity reports where every claim is labelled as fact (with source) or assumption.', 'Nothing; it only writes reports.'],
 ]],
 'Running the business' => ['#94a3b8', [
   'chief_ops' => ['Incoming work that is not obviously for one agent.', 'Work split into tasks for the right specialists, duplicates prevented, failures escalated. It does not do specialist work itself.', 'Anything above the specialists\' autonomy.'],
   'owner_assistant' => ['You, in the "Ask about the business" box on the Control Tower.', 'Answers with live numbers (revenue, leads, tasks, approvals). If the data is not there, it says so.', 'Any action it offers to start — it asks first.'],
   'finance' => ['Payments, failed charges and subscriptions.', 'Revenue summaries and payment reminders. It cannot move money.', 'Every refund, credit or invoice exception.'],
   'security' => ['Daily and when unusual activity appears.', 'A read-only review of failed logins, denied access and agent activity, with recommended fixes.', 'Any remediation. It has no access to secrets.'],
 ]],
];
$levels = [1 => ['Assist', 'Recommends only. Nothing runs.'], 2 => ['Draft', 'Reads and writes drafts. Anything riskier asks you.'], 3 => ['Controlled', 'Routine actions run; risky ones come to the Action center.'], 4 => ['Trusted', 'Runs high-risk actions without asking. Not recommended.']];
$live = []; foreach ($agents as $a) $live[$a['slug']] = $a;
$tdefs = App\Tools::defs();
?>
<div class="topbar"><div><h1>How your AI agents work</h1><p class="muted" style="margin:4px 0 0">A plain-language guide to the 20 agents: what wakes each one up, what you will see from it, and what always comes to you first.</p></div>
  <div class="row"><a class="btn btn-ghost btn-sm" href="/admin/simulator">Test all agents</a><a class="btn btn-primary btn-sm" href="/admin/approvals">Action center</a></div></div>

<div class="g-intro">
  <div><b>Where work comes from</b><span>Orders, the website chat, tickets, the weekly schedule and your questions. Each becomes a task in AI workforce → Tasks.</span></div>
  <div><b>Where you step in</b><span>Anything risky lands in the Action center. The agent waits until you approve, reject or ask for changes.</span></div>
  <div><b>Autonomy levels</b><span><?php foreach ($levels as $n => [$l, $d]): ?><span class="lvl lvl-<?= $n ?>" title="<?= e($d) ?>"><?= $n ?> · <?= e($l) ?></span> <?php endforeach; ?></span></div>
  <div><b>Without an AI provider</b><span><?= $ai ? 'An AI provider is connected, so agents write full answers.' : 'Agents run in basic mode: chat answers from your catalog and knowledge base, and writing tasks wait. Connect Anthropic in Integrations.' ?></span></div>
</div>

<?php foreach ($G as $team => [$col, $list]): ?>
<h2 class="g-team" style="--g:<?= $col ?>"><?= e($team) ?></h2>
<div class="g-grid">
<?php foreach ($list as $slug => [$when, $expect, $asks]): $a = $live[$slug] ?? null; if (!$a) continue; $lv = (int)$a['autonomy_level']; $tools = jdec((string)$a['tools']); ?>
  <article class="g-card" id="<?= e($slug) ?>">
    <div class="g-head"><div><h3><?= e($a['name']) ?></h3><p><?= e(App\Agents::DEFS[$slug][1] ?? '') ?></p></div>
      <span class="lvl lvl-<?= min(4, max(1, $lv)) ?>" title="<?= e($levels[$lv][1] ?? '') ?>"><?= $lv ?> · <?= e($levels[$lv][0] ?? 'Custom') ?></span></div>
    <dl>
      <dt>Starts when</dt><dd><?= e($when) ?></dd>
      <dt>You'll see</dt><dd><?= e($expect) ?></dd>
      <dt>Asks you first</dt><dd><?= e($asks) ?></dd>
      <dt>Status</dt><dd><?= $a['status'] === 'enabled' ? '<span class="badge b-green">On</span>' : '<span class="badge">Off</span>' ?> <span class="small muted"><?= e($a['model']) ?></span></dd>
      <dt>Tools</dt><dd class="g-tools"><?php foreach ($tools as $tn): $r = $tdefs[$tn]['risk'] ?? ''; ?><code title="<?= e(($tdefs[$tn]['description'] ?? '') . ' · risk: ' . $r) ?>"><?= e($tn) ?></code><?php endforeach; ?></dd>
    </dl>
    <div class="g-foot"><a class="btn btn-ghost btn-sm" href="/admin/agents/<?= e($slug) ?>">Settings & test</a><a class="btn btn-ghost btn-sm" href="/admin/tasks?agent=<?= e($slug) ?>">Its tasks</a></div>
  </article>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
