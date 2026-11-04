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

// Renk seviyesi: en yoğun güne göre dört basamak
$max = max(1, ...array_map(fn($d) => $d['contributionCount'], $days));
$level = function ($n) use ($max) {
  if ($n === 0) return 0;
  return min(4, 1 + (int) floor(($n / $max) * 3.999));
};

function esc($s) { return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

$W = 1200; $H = 336; $gx = 92; $gy = 138; $p = 19.6; $c = 15;
$o = "<defs><linearGradient id=\"split\" gradientUnits=\"userSpaceOnUse\" x1=\"0\" y1=\"0\" x2=\"$W\" y2=\"0\"><stop offset=\"0\" stop-color=\"" . BLUE . "\"/><stop offset=\".5\" stop-color=\"#8a5cff\" stop-opacity=\".55\"/><stop offset=\"1\" stop-color=\"" . RED . "\"/></linearGradient>" .
  "<clipPath id=\"pc\"><rect width=\"$W\" height=\"$H\" rx=\"20\"/></clipPath></defs>" .
  "<rect x=\".5\" y=\".5\" width=\"" . ($W - 1) . "\" height=\"" . ($H - 1) . "\" rx=\"20\" fill=\"" . PANEL . "\" stroke=\"" . LINE . "\"/>" .
  "<rect width=\"$W\" height=\"2\" fill=\"url(#split)\" clip-path=\"url(#pc)\"/>" .
  "<text x=\"48\" y=\"62\" class=\"m6\" font-size=\"13\" letter-spacing=\"1\"><tspan fill=\"" . BLUE . "\">[</tspan><tspan fill=\"" . TEXT . "\">05</tspan><tspan fill=\"" . RED . "\">]</tspan></text>" .
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

$css = file_get_contents(__DIR__ . '/fonts.css');
@mkdir(dirname($outFile), 0777, true);
file_put_contents($outFile, "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"$W\" height=\"$H\" viewBox=\"0 0 $W $H\" role=\"img\"><style>$css</style>$o</svg>\n");
echo "OK: {$cal['totalContributions']} katkı\n";
