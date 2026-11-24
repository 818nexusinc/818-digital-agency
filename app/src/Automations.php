<?php
declare(strict_types=1);
namespace App;

/**
 * Automation center: every recurring job the business runs on its own, with an on/off switch, a schedule,
 * the last result and a "run now" button. Cron calls runDue() every few minutes; each job decides if it is due.
 */
final class Automations
{
    /** key => [name, what it does, every N seconds, group, can be switched off] */
    public const JOBS = [
        'workflows'         => ['Run workflows and AI agent tasks', 'Moves every order, project and ticket to its next step and lets the AI agents work through their queue.', 0, 'Core', false],
        'filings'           => ['Advance government filings', 'Submits authorized filings and checks registries for results.', 0, 'Core', false],
        'housekeeping'      => ['Tidy up', 'Expires old proposals, clears stale locks and old sessions.', 0, 'Core', false],
        'lead_followups'    => ['Follow up with leads', 'Sends a short, personal follow-up to leads who have not replied (Canada\'s anti-spam rules respected; stops on reply, opt-out or after the set number of touches).', 3600, 'Sales', true],
        'draft_followups'   => ['Rescue unfinished forms', 'Two hours after someone stops part-way through an order form and left an email, sends one friendly "need a hand?" email and adds them to Leads.', 1800, 'Sales', true],
        'approval_reminders'=> ['Remind me about approvals', 'Emails you once a day if anything has waited in the Action center for more than 24 hours.', 86400, 'Owner', true],
        'owner_reports'     => ['Daily and weekly summaries', 'Emails you a daily summary after noon UTC and a weekly report on Mondays, and asks the Growth agent for three actions each week.', 3600, 'Owner', true],
        'blog_weekly'       => ['Weekly blog article', 'Writes and publishes one SEO article every 7 days and tells search engines about it.', 21600, 'Marketing', true],
        'pricing_weekly'    => ['Weekly pricing check', 'Asks the Pricing Agent to check competitor prices and government fees across Atlantic Canada every Monday and propose changes for you to approve. Needs the Anthropic connection for web research.', 604800, 'Sales', true],
        'indexnow_weekly'   => ['Notify search engines', 'Sends every page address to Bing and other IndexNow engines once a week so changes are picked up quickly.', 604800, 'Marketing', true],
        'agents_selftest'   => ['AI agent self-test', 'Runs all agents through the simulator every night and emails you if any of them fails.', 86400, 'Health', true],
        'security_daily'    => ['Daily security check', 'Runs the security check every morning and emails you if a new problem appears.', 86400, 'Health', true],
        'backup_nightly'    => ['Nightly backup', 'Saves a compressed copy of the database on the server. Keep your host\'s backups switched on too.', 86400, 'Health', true],
    ];

    public static function enabled(string $key): bool
    {
        return !self::JOBS[$key][4] || Settings::get("auto.{$key}.on", '1') === '1';
    }

    public static function last(string $key): ?array
    {
        $j = jdec((string)Settings::get("auto.{$key}.last") ?: 'null');
        return is_array($j) ? $j : null;
    }

    public static function due(string $key): bool
    {
        $every = self::JOBS[$key][2];
        if ($every === 0) return true;
        $l = self::last($key);
        return !$l || strtotime($l['at'] . ' UTC') <= time() - $every + 60;
    }

    /** Called from Cron::run. Runs every enabled, due job and records what happened. */
    public static function runDue(float $deadline): array
    {
        $out = [];
        foreach (array_keys(self::JOBS) as $k) {
            if (!self::enabled($k) || !self::due($k)) continue;
            if (microtime(true) > $deadline && self::JOBS[$k][2] > 0) break; // leave periodic jobs for the next cycle
            $out[$k] = self::runOne($k);
        }
        return $out;
    }

    public static function runOne(string $k, string $by = 'schedule'): array
    {
        $t0 = microtime(true);
        try { $note = self::handle($k); $ok = true; }
        catch (\Throwable $e) { $note = $e->getMessage(); $ok = false; Log::error('automation.failed', ['job' => $k, 'error' => $note]); }
        $rec = ['at' => now(), 'ok' => $ok, 'note' => mb_substr((string)$note, 0, 300), 'ms' => (int)((microtime(true) - $t0) * 1000), 'by' => $by];
        // Core jobs run every cycle; only record them when something happened, to keep "last run" meaningful.
        Settings::set("auto.{$k}.last", jenc($rec));
        return $rec;
    }

    private static function handle(string $k): string
    {
        switch ($k) {
            case 'workflows':
                $e = Workflows::dispatchEvents(); $w = Workflows::runDue(30, 15); $t = Orchestrator::work(8, 25); $e2 = Workflows::dispatchEvents();
                return ($e + $e2) . " event(s), {$w} workflow step(s), {$t} agent task(s).";
            case 'filings': return Filings::autoAdvance() . ' filing(s) moved forward.';
            case 'housekeeping': return jenc(Cron::housekeeping());
            case 'lead_followups': return Cron::followUps() . ' follow-up(s) sent.';
            case 'draft_followups': return self::draftFollowups();
            case 'approval_reminders': return self::approvalReminder();
            case 'owner_reports': $r = Cron::reports(); return $r ? implode(', ', $r) . ' sent.' : 'Nothing due.';
            case 'blog_weekly': $b = BlogEngine::weekly(); return $b ? 'Wrote "' . $b['title'] . '".' : 'Not due yet (one article every 7 days).';
            case 'pricing_weekly':
                if (!Integrations::configured('anthropic')) return 'Skipped: connect Anthropic so the Pricing Agent can search the web.';
                return 'Queued pricing research (task #' . Pricing::research(null, '', 'schedule') . ').';
            case 'indexnow_weekly': $r = Seo::indexNow(Seo::allUrls()); return $r['ok'] ? "Sent {$r['count']} pages." : ($r['note'] ?? 'Search engines did not accept the list (code ' . ($r['code'] ?? 0) . ').');
            case 'agents_selftest':
                $res = Simulator::run(); $bad = array_filter($res, fn($x) => !$x['ok']);
                if ($bad) Mailer::notifyOwner(count($bad) . ' AI agent(s) failed the nightly self-test', implode("\n", array_map(fn($x) => "- {$x['name']}: {$x['error']}", $bad)), '/admin/simulator');
                return (count($res) - count($bad)) . ' of ' . count($res) . ' agents passed.';
            case 'security_daily':
                $r = SecurityCheck::run(false); $fails = array_values(array_filter($r, fn($x) => $x['s'] === 'fail'));
                $sig = md5(implode('|', array_column($fails, 't'))); $prev = (string)Settings::get('auto.security_daily.sig');
                if ($fails && $sig !== $prev) Mailer::notifyOwner('Security check: ' . count($fails) . ' item(s) need attention', implode("\n", array_map(fn($x) => "- {$x['t']}: {$x['found']}", $fails)), '/admin/security');
                Settings::set('auto.security_daily.sig', $sig);
                return 'Score ' . SecurityCheck::score($r) . '/100, ' . count($fails) . ' failing.';
            case 'backup_nightly':
                if (ON_VERCEL) return 'Skipped on Vercel: use the database provider\'s backups.';
                $f = Backup::run(); return 'Saved ' . basename($f) . '.';
        }
        return 'Unknown job.';
    }

    /** One friendly email per unfinished form with an email, then hand it to Leads. */
    private static function draftFollowups(): string
    {
        $cut = gmdate('Y-m-d H:i:s', time() - 7200);
        $rows = Db::all("SELECT * FROM form_drafts WHERE status = 'open' AND email IS NOT NULL AND updated_at <= ? AND (notes IS NULL OR notes NOT LIKE '%[reminded]%') ORDER BY id LIMIT 20", [$cut]);
        $n = 0;
        foreach ($rows as $d) {
            if (Db::val('SELECT 1 FROM email_suppressions WHERE email = ?', [$d['email']])) { Db::update('form_drafts', ['notes' => trim(($d['notes'] ?? '') . ' [reminded]')], 'id = ?', [$d['id']]); continue; }
            $what = mb_strtolower(label($d['form']));
            $first = $d['name'] ? trim(explode(' ', (string)$d['name'])[0]) : 'there';
            Mailer::send('follow_up', $d['email'], ['subject' => "Need a hand finishing your {$what}?", 'body' => "Hi {$first},\n\nYou started our {$what} form but didn't get to the end. If something was unclear, just reply to this email and a person on our team will help. If you're ready, you can pick up here:\n\n" . url($d['path'] ?: '/') . "\n\nThanks,\n818 Digital Agency"],
                'draft-remind:' . $d['id'], 'form_draft', (int)$d['id']);
            Db::update('form_drafts', ['notes' => trim(($d['notes'] ?? '') . ' [reminded]')], 'id = ?', [$d['id']]);
            try { FormDrafts::toLead((int)$d['id'], 'automation'); } catch (\Throwable) {}
            $n++;
        }
        return "{$n} reminder(s) sent.";
    }

    private static function approvalReminder(): string
    {
        $old = Db::all("SELECT title FROM approvals WHERE status = 'pending' AND created_at <= ? ORDER BY id LIMIT 10", [gmdate('Y-m-d H:i:s', time() - 86400)]);
        if (!$old) return 'Nothing waiting more than a day.';
        Mailer::notifyOwner(count($old) . ' approval(s) waiting for you', "These have waited more than a day, and the agents are paused on them:\n" . implode("\n", array_map(fn($a) => '- ' . $a['title'], $old)), '/admin/approvals');
        return count($old) . ' reminder(s) in one email.';
    }
}
