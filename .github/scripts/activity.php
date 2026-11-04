<?php
// Son bir yılın katkılarını GitHub'dan alıp profil tasarımına uygun bir ısı haritası SVG'si üretir.
// Kullanım: GITHUB_TOKEN=... php activity.php <kullanıcı> <çıkış.svg>
[, $user, $outFile] = $argv;
$token = getenv('GITHUB_TOKEN');

const PANEL = '#0D0F15', PANEL2 = '#12151D', LINE = '#1C2030', TEXT = '#ECEEF4', TEXT2 = '#A3A9BB', TEXT3 = '#666C80';
const BLUE = '#3E84FF', RED = '#FF3B2F';
// Boştan yoğuna: Börü'nün mavi ışığından kırmızı ışığına
const LEVELS = ['#151924', '#1F3566', '#3E6FE0', '#B4405E', '#FF3B2F'];

$query = '{"query":"query($u:String!){user(login:$u){contributionsCollection{contributionCalendar{totalContributions weeks{contributionDays{date contributionCount}}}}}}","variables":{"u":' . json_encode($user) . '}}';
$ctx = stream_context_create(['http' => [
  'method' => 'POST',
  'header' => "Authorization: bearer $token\r\nUser-Agent: profile-activity\r\nContent-Type: application/json\r\n",
  'content' => $query,
]]);
$res = json_decode(file_get_contents('https://api.github.com/graphql', false, $ctx), true);
$cal = $res['data']['user']['contributionsCollection']['contributionCalendar'] ?? null;
if (!$cal) { fwrite(STDERR, "Katkı verisi alınamadı\n"); exit(1); }

$weeks = $cal['weeks'];
$days = [];
foreach ($weeks as $w) foreach ($w['contributionDays'] as $d) $days[] = $d;

// Seriler ve en yoğun gün
$longest = $run = 0; $best = ['contributionCount' => 0, 'date' => ''];
foreach ($days as $d) {
  $run = $d['contributionCount'] > 0 ? $run + 1 : 0;
  $longest = max($longest, $run);
  if ($d['contributionCount'] > $best['contributionCount']) $best = $d;
}
$current = 0;
for ($i = count($days) - 1; $i >= 0; $i--) {
  if ($days[$i]['contributionCount'] > 0) $current++;
  elseif ($i < count($days) - 1 || $current > 0) break; // bugün henüz katkı yoksa dünden say
}
$activeDays = count(array_filter($days, fn($d) => $d['contributionCount'] > 0));

// Renk seviyesi: en yoğun güne göre dört basamak
$max = max(1, $best['contributionCount']);
$level = function ($n) use ($max) {
  if ($n === 0) return 0;
  return min(4, 1 + (int) floor(($n / $max) * 3.999));
};

function esc($s) { return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

$W = 1200; $H = 420; $gx = 92; $gy = 138; $p = 19.6; $c = 15;
$o = "<defs><linearGradient id=\"split\" gradientUnits=\"userSpaceOnUse\" x1=\"0\" y1=\"0\" x2=\"$W\" y2=\"0\"><stop offset=\"0\" stop-color=\"" . BLUE . "\"/><stop offset=\".5\" stop-color=\"#8a5cff\" stop-opacity=\".55\"/><stop offset=\"1\" stop-color=\"" . RED . "\"/></linearGradient>" .
  "<clipPath id=\"pc\"><rect width=\"$W\" height=\"$H\" rx=\"20\"/></clipPath></defs>" .
  "<rect x=\".5\" y=\".5\" width=\"" . ($W - 1) . "\" height=\"" . ($H - 1) . "\" rx=\"20\" fill=\"" . PANEL . "\" stroke=\"" . LINE . "\"/>" .
  "<rect width=\"$W\" height=\"2\" fill=\"url(#split)\" clip-path=\"url(#pc)\"/>" .
  "<text x=\"48\" y=\"62\" class=\"m6\" font-size=\"13\" letter-spacing=\"1\"><tspan fill=\"" . BLUE . "\">[</tspan><tspan fill=\"" . TEXT . "\">06</tspan><tspan fill=\"" . RED . "\">]</tspan></text>" .
  "<text x=\"96\" y=\"63\" class=\"g7\" font-size=\"30\" letter-spacing=\"-.6\" fill=\"" . TEXT . "\">Activity<tspan class=\"ii\" font-size=\"16\" letter-spacing=\"0\" dx=\"14\" fill=\"" . TEXT3 . "\">Katkılar</tspan></text>" .
  "<text x=\"1152\" y=\"62\" text-anchor=\"end\" class=\"m\" font-size=\"12\" letter-spacing=\"1\" fill=\"" . TEXT3 . "\">LAST 12 MONTHS · UPDATED " . strtoupper(gmdate('j M Y')) . "</text>" .
  "<line x1=\"48\" y1=\"88\" x2=\"1152\" y2=\"88\" stroke=\"" . LINE . "\"/>";

// Gün adları
foreach ([1 => 'Mon', 3 => 'Wed', 5 => 'Fri'] as $r => $n)
  $o .= "<text x=\"48\" y=\"" . ($gy + $r * $p + 11.5) . "\" class=\"m\" font-size=\"10.5\" fill=\"" . TEXT3 . "\">$n</text>";

$lastMonth = -1;
foreach ($weeks as $wi => $w) {
  $x = round($gx + $wi * $p, 1);
  $first = $w['contributionDays'][0];
  $mo = (int) substr($first['date'], 5, 2) - 1;
  if ($mo !== $lastMonth && $wi < count($weeks) - 2) {
    $o .= "<text x=\"$x\" y=\"" . ($gy - 12) . "\" class=\"m\" font-size=\"10.5\" fill=\"" . TEXT3 . "\">{$months[$mo]}</text>";
    $lastMonth = $mo;
  }
  foreach ($w['contributionDays'] as $d) {
    $wd = (int) gmdate('w', strtotime($d['date']));
    $y = round($gy + $wd * $p, 1);
    $col = LEVELS[$level($d['contributionCount'])];
    $o .= "<rect x=\"$x\" y=\"$y\" width=\"$c\" height=\"$c\" rx=\"3.5\" fill=\"$col\"><title>" . esc($d['date']) . ": " . $d['contributionCount'] . "</title></rect>";
  }
}

// Gösterge
$lx = 1152 - 5 * 19 - 34;
$o .= "<text x=\"" . ($lx - 8) . "\" y=\"301\" text-anchor=\"end\" class=\"m\" font-size=\"10.5\" fill=\"" . TEXT3 . "\">Less</text>";
foreach (LEVELS as $i => $col) $o .= "<rect x=\"" . ($lx + $i * 19) . "\" y=\"290\" width=\"14\" height=\"14\" rx=\"3\" fill=\"$col\"/>";
$o .= "<text x=\"" . ($lx + 5 * 19 + 4) . "\" y=\"301\" class=\"m\" font-size=\"10.5\" fill=\"" . TEXT3 . "\">More</text>";

// Rakamlar
$bestDate = $best['date'] ? strtoupper(gmdate('j M Y', strtotime($best['date']))) : '—';
$stats = [
  [number_format($cal['totalContributions']), 'CONTRIBUTIONS', 'katkı'],
  [(string) $activeDays, 'ACTIVE DAYS', 'aktif gün'],
  [$longest . 'd', 'LONGEST STREAK', 'en uzun seri'],
  [$current . 'd', 'CURRENT STREAK', 'güncel seri'],
];
$o .= "<line x1=\"48\" y1=\"324\" x2=\"1152\" y2=\"324\" stroke=\"" . LINE . "\"/>";
foreach ($stats as $i => [$n, $en, $tr]) {
  $x = 48 + $i * 280;
  if ($i) $o .= "<line x1=\"" . ($x - 24) . "\" y1=\"344\" x2=\"" . ($x - 24) . "\" y2=\"388\" stroke=\"" . LINE . "\"/>";
  $nx = $x + 22 + mb_strlen($n) * 22;
  $o .= "<text x=\"$x\" y=\"380\" class=\"g7\" font-size=\"40\" letter-spacing=\"-1\" fill=\"" . TEXT . "\">" . esc($n) . "</text>" .
    "<text x=\"$nx\" y=\"360\" class=\"m\" font-size=\"11\" letter-spacing=\"1.2\" fill=\"" . TEXT2 . "\">$en</text>" .
    "<text x=\"$nx\" y=\"380\" class=\"ii\" font-size=\"12.5\" fill=\"" . TEXT3 . "\">$tr</text>";
}
$o .= "<text x=\"48\" y=\"301\" class=\"m\" font-size=\"10.5\" fill=\"" . TEXT3 . "\">BUSIEST DAY · " . esc($bestDate) . " · " . $best['contributionCount'] . "</text>";

$css = file_get_contents(__DIR__ . '/fonts.css');
@mkdir(dirname($outFile), 0777, true);
file_put_contents($outFile, "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"$W\" height=\"$H\" viewBox=\"0 0 $W $H\" role=\"img\"><style>$css</style>$o</svg>\n");
echo "OK: {$cal['totalContributions']} katkı, en uzun seri $longest, güncel seri $current\n";
