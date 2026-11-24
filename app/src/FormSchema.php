<?php
declare(strict_types=1);
namespace App;

/**
 * Reads the website's order forms (views/public/svc/*.php, built with wz_step / wz_field / wz_select / wz_cards /
 * wz_provinces / wz_repeat) to learn each form's steps, field labels and option names. Order details and unfinished
 * forms are then shown — to people and to the AI agents — in the same order and wording the customer saw.
 * Nothing is duplicated: change a form and the back office follows.
 */
final class FormSchema
{
    private static ?array $forms = null;

    /** All forms: file => ['steps' => [i => title], 'fields' => [path => [label, step, options]], 'groups' => [key => label], 'text' => source] */
    private static function forms(): array
    {
        if (self::$forms !== null) return self::$forms;
        self::$forms = [];
        foreach (glob(APP_ROOT . '/views/public/svc/*.php') ?: [] as $f) {
            $src = (string)file_get_contents($f);
            if (!str_contains($src, 'wz_open(')) continue;
            self::$forms[basename($f, '.php')] = self::parse($src);
        }
        return self::$forms;
    }

    private static function str(string $s): string { return stripcslashes(substr($s, 1, -1)); }

    private static function parse(string $src): array
    {
        $q = "'(?:[^'\\\\]|\\\\.)*'";
        $steps = []; $stepPos = [];
        if (preg_match_all("/wz_step\\((\\d+),\\s*($q)/", $src, $m, PREG_OFFSET_CAPTURE)) foreach ($m[1] as $i => $n) {
            $steps[(int)$n[0]] = trim(preg_replace('/^Step\s*\d+\s*:\s*/i', '', self::str($m[2][$i][0])));
            $stepPos[(int)$n[0]] = $n[1];
        }
        ksort($stepPos);
        $stepAt = function (int $pos) use ($stepPos): ?int { $s = null; foreach ($stepPos as $i => $p) if ($p <= $pos) $s = $i; return $s; };
        $groups = []; $groupStep = [];
        if (preg_match_all("/wz_repeat\\(($q),\\s*($q)/", $src, $m, PREG_OFFSET_CAPTURE)) foreach ($m[1] as $i => $k) {
            $key = self::str($k[0]); $groups[$key] = self::str($m[2][$i][0]); $groupStep[$key] = $stepAt($k[1]);
        }
        $fields = [];
        $add = function (string $name, string $label, int $pos, array $opts = []) use (&$fields, $stepAt, $groupStep) {
            $path = preg_replace('/\[\]$/', '', $name);
            $top = strtok($path, '[');
            $step = $stepAt($pos) ?? ($groupStep[$top] ?? null);
            if ($step === null && isset($groupStep[$top])) $step = $groupStep[$top];
            $fields[$path] = [trim(preg_replace('/\s*\*\s*$/', '', $label)), $step, $opts];
        };
        if (preg_match_all("/wz_field\\(($q),\\s*($q)/", $src, $m, PREG_OFFSET_CAPTURE)) foreach ($m[1] as $i => $n) $add(self::str($n[0]), self::str($m[2][$i][0]), $n[1]);
        if (preg_match_all("/wz_select\\(($q),\\s*($q),\\s*(\\[.*?\\]|\\\$\\w+)/s", $src, $m, PREG_OFFSET_CAPTURE)) foreach ($m[1] as $i => $n) {
            $opts = [];
            $arr = $m[3][$i][0];
            if ($arr[0] === '$') $opts = Catalog::PROVINCES;
            elseif (preg_match_all("/($q)\\s*=>\\s*($q)/", $arr, $om)) foreach ($om[1] as $j => $k) $opts[self::str($k)] = self::str($om[2][$j]);
            $add(self::str($n[0]), self::str($m[2][$i][0]), $n[1], $opts);
        }
        if (preg_match_all("/wz_cards\\(($q),\\s*($q),\\s*\\[(.*?)\\]\\s*(?:,\\s*$q)?\\s*(?:,\\s*(?:true|false))?\\)/s", $src, $m, PREG_OFFSET_CAPTURE)) foreach ($m[1] as $i => $n) {
            $opts = [];
            if (preg_match_all("/\\[\\s*($q)\\s*,\\s*($q)/", $m[3][$i][0], $om)) foreach ($om[1] as $j => $k) $opts[self::str($k)] = self::str($om[2][$j]);
            $add(self::str($n[0]), self::str($m[2][$i][0]), $n[1], $opts);
        }
        if (preg_match_all("/wz_addons\\(\\[[^\\]]*\\](?:,\\s*($q))?(?:,\\s*($q))?/", $src, $m, PREG_OFFSET_CAPTURE)) foreach ($m[0] as $i => $all) {
            $name = isset($m[2][$i]) && $m[2][$i][1] >= 0 ? self::str($m[2][$i][0]) : 'addons[]';
            $add($name, isset($m[1][$i]) && $m[1][$i][1] >= 0 ? self::str($m[1][$i][0]) : 'Optional add-ons', $all[1]);
        }
        // Cards whose options live in a variable: wz_cards('app_type', 'App type', $types) with $types = [['portal', 'Client portal', …], …];
        if (preg_match_all("/wz_cards\\(($q),\\s*($q),\\s*\\\$(\\w+)/", $src, $m, PREG_OFFSET_CAPTURE)) foreach ($m[1] as $i => $n) {
            $opts = [];
            if (preg_match('/\\$' . $m[3][$i][0] . '\\s*=\\s*\\[(.*?)\\];/s', $src, $vm) && preg_match_all("/\\[\\s*($q)\\s*,\\s*($q)/", $vm[1], $om)) foreach ($om[1] as $j => $k) $opts[self::str($k)] = self::str($om[2][$j]);
            $add(self::str($n[0]), self::str($m[2][$i][0]), $n[1], $opts);
        }
        if (preg_match_all("/wz_provinces\\(($q)(?:,\\s*[^,()]+(?:\\([^()]*\\))?)?(?:,\\s*($q))?/", $src, $m, PREG_OFFSET_CAPTURE)) foreach ($m[1] as $i => $n)
            $add(self::str($n[0]), isset($m[2][$i]) && $m[2][$i][1] >= 0 && $m[2][$i][0] !== '' ? self::str($m[2][$i][0]) : 'Province or territory', $n[1], ['FED' => 'Federal'] + Catalog::PROVINCES);
        return ['steps' => $steps, 'fields' => $fields, 'groups' => $groups, 'text' => $src];
    }

    /** The form a service is ordered from (by slug or draft form key). */
    public static function formFor(string $slug, ?string $path = null): ?array
    {
        $forms = self::forms();
        if (isset($forms[$slug])) return $forms[$slug];
        // The page the form was on, e.g. /web-application → web_app.php, /website/maintenance → maintenance.php
        foreach (array_reverse(array_filter(explode('/', (string)$path))) as $seg) { $k = str_replace('-', '_', $seg); if (isset($forms[$k])) return $forms[$k]; if (isset($forms[preg_replace('/_application$/', '_app', $k)])) return $forms[preg_replace('/_application$/', '_app', $k)]; }
        foreach ($forms as $f) if (preg_match("/['\"]" . preg_quote($slug, '/') . "['\"]/", $f['text'])) return $f;
        return null;
    }

    private static function value(mixed $v, array $opts, string $key): string
    {
        if (is_bool($v)) return $v ? 'Yes' : 'No';
        $s = (string)$v;
        if ($s === '1' && preg_match('/(agree|incorporator|resident|consent|confirm|is_|has_)/', $key)) return 'Yes';
        if ($s === '0' && preg_match('/(agree|incorporator|resident|consent|confirm|is_|has_)/', $key)) return 'No';
        if (isset($opts[$s])) return $opts[$s];
        if ($key === 'addons' || str_starts_with($s, 'addon_')) { $svc = Catalog::find($s); if ($svc) return $svc['name']; }
        return $s;
    }

    private static function humanize(string $k): string
    {
        $k = str_replace(['_', '-'], ' ', $k);
        $map = ['dob' => 'Date of birth', 'postal' => 'Postal code', 'url' => 'Website address', 'tld' => 'Domain ending', 'cra' => 'CRA', 'bn' => 'Business number', 'nuans' => 'NUANS'];
        return $map[$k] ?? ucfirst($k);
    }

    /**
     * Sections for display: [ ['title' => step title, 'rows' => [[label, value], …], 'groups' => [['title' => 'Director 1', 'rows' => …], …]], … ]
     * in the form's own step order. Fields the form no longer has are kept under "Other details".
     */
    public static function sections(string $slug, array $details, ?string $path = null): array
    {
        $form = self::formFor($slug, $path) ?? ['steps' => [], 'fields' => [], 'groups' => []];
        $auth = $details['_authorization'] ?? null; unset($details['_authorization']);
        $buckets = [];
        $label = function (string $path, string $leaf) use ($form): array {
            $f = $form['fields'][$path] ?? null;
            return $f ? [$f[0], $f[1], $f[2]] : [self::humanize($leaf), null, []];
        };
        $walk = function (array $d, string $path, ?array $group) use (&$walk, &$buckets, $label, $form) {
            foreach ($d as $k => $v) {
                $isIdx = is_int($k) || ctype_digit((string)$k);
                $p = $path === '' ? (string)$k : $path . '[' . ($isIdx ? '{i}' : $k) . ']';
                $top = strtok($p, '[');
                if (is_array($v) && $v && array_is_list($v) && !is_array($v[0] ?? null) && !isset($form['groups'][$top])) {
                    // a simple list (e.g. add-ons, backup names): one row
                    [$lab, $step, $opts] = $label($p, (string)$k);
                    if (!isset($form['fields'][$p]) && isset($form['fields'][$p . '[{i}]'])) [$lab, $step, $opts] = $label($p . '[{i}]', (string)$k);
                    if (!isset($form['fields'][$p])) foreach ($form['fields'] as $fp => $fv) if (str_starts_with($fp, $p . '[')) { $step = $fv[1]; break; }
                    $vals = array_map(fn($x) => self::value($x, $opts, (string)$k), array_filter($v, fn($x) => $x !== '' && $x !== null));
                    if ($vals) $buckets[$step ?? 'other'][$group ? 'g:' . $group[0] : '_'][] = [$lab, implode(', ', $vals), $group];
                    continue;
                }
                if (is_array($v)) {
                    if ($isIdx && isset($form['groups'][$top]) && substr_count($p, '[') === 1) { $walk($v, $p, [($form['groups'][$top] ?? self::humanize($top)) . ' ' . ((int)$k + 1), $top]); continue; }
                    $walk($v, $p, $group); continue;
                }
                if ($v === '' || $v === null) continue;
                [$lab, $step, $opts] = $label($p, (string)$k);
                if ($step === null && $group) $step = $label(preg_replace('/\[[^\]]+\]$/', '', $p), '')[1];
                if ($step === null) foreach ($form['fields'] as $fp => $fv) if (strtok($fp, '[') === $top && $fv[1] !== null) { $step = $fv[1]; break; }
                $buckets[$step ?? 'other'][$group ? 'g:' . $group[0] : '_'][] = [$lab, self::value($v, $opts, (string)$k), $group];
            }
        };
        $walk($details, '', null);
        $order = array_keys($form['steps']); sort($order);
        $out = [];
        foreach (array_merge($order, ['other']) as $s) {
            if (empty($buckets[$s])) continue;
            $sec = ['title' => $s === 'other' ? 'Other details' : $form['steps'][$s], 'rows' => [], 'groups' => []];
            foreach ($buckets[$s] as $gk => $rows) {
                $clean = array_map(fn($r) => [$r[0], $r[1]], $rows);
                if ($gk === '_') $sec['rows'] = array_merge($sec['rows'], $clean); else $sec['groups'][] = ['title' => substr($gk, 2), 'rows' => $clean];
            }
            $out[] = $sec;
        }
        if (is_array($auth)) $out[] = ['title' => 'Customer authorization', 'rows' => array_values(array_filter([['Authorized at', (string)($auth['at'] ?? '') . ' UTC'], ['From IP address', (string)($auth['ip'] ?? '')], ['Statement accepted', (string)($auth['text'] ?? '')]], fn($r) => trim($r[1]) !== '' && trim($r[1]) !== 'UTC')), 'groups' => []];
        return $out;
    }

    /** Same, as plain text for agents, project briefs and emails. */
    public static function text(string $slug, array $details): string
    {
        $o = [];
        foreach (self::sections($slug, $details) as $s) {
            $o[] = '## ' . $s['title'];
            foreach ($s['rows'] as [$l, $v]) $o[] = "- {$l}: {$v}";
            foreach ($s['groups'] as $g) { $o[] = '### ' . $g['title']; foreach ($g['rows'] as [$l, $v]) $o[] = "- {$l}: {$v}"; }
            $o[] = '';
        }
        return trim(implode("\n", $o));
    }
}
