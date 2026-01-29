<?php
$inputText = $_POST['text'] ?? '';

$frequencies = [];
$totalChars = 0;

if (isset($_POST['submit'])) {
    $chars = preg_split('//u', $inputText, -1, PREG_SPLIT_NO_EMPTY);
    if (is_array($chars)) {
        $totalChars = count($chars);
        foreach ($chars as $ch) {
            $key = normalizeCharacterForCounting($ch);
            $frequencies[$key] = ($frequencies[$key] ?? 0) + 1;
        }
    }

    if ($totalChars > 0) {
        uasort($frequencies, function (int $a, int $b) use ($totalChars) {
            $ra = $a / $totalChars;
            $rb = $b / $totalChars;
            if ($ra === $rb) {
                if ($a === $b) {
                    return 0;
                }
                return $b <=> $a;
            }
            return $rb <=> $ra;
        });
    }
}

function normalizeCharacterForCounting(string $ch): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($ch, 'UTF-8');
    }

    return strtolower($ch);
}

function formatCharacterLabel(string $ch): string
{
    return match ($ch) {
        " " => "SPACE",
        "\t" => "TAB",
        "\n" => "LF",
        "\r" => "CR",
        default => $ch,
    };
}

function escapeForDisplay(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Zeichenhäufigkeit</title>
    <meta charset="UTF-8" name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <style>
        .freq-wrap{max-width:900px;margin:40px auto;padding:18px 18px 22px;background:#fff;border-radius:10px;box-shadow:0 2px 16px rgba(0,0,0,.08)}
        .freq-title{margin:0 0 12px;font-size:22px;font-weight:700}
        .freq-form textarea{width:100%;min-height:120px;resize:vertical;padding:10px 12px;border:1px solid rgba(0,0,0,.2);border-radius:8px;font-size:14px;line-height:1.4}
        .freq-actions{margin-top:10px;display:flex;gap:10px;align-items:center}
        .freq-actions button{padding:10px 14px;border-radius:8px;border:0;background:#1f6feb;color:#fff;font-weight:600;cursor:pointer}
        .freq-actions button:hover{filter:brightness(.95)}
        .freq-meta{margin:14px 0 10px;color:rgba(0,0,0,.75);font-size:14px}
        .freq-table{width:100%;border-collapse:collapse;overflow:hidden;border-radius:10px}
        .freq-table th,.freq-table td{padding:10px 12px;border-bottom:1px solid rgba(0,0,0,.08);text-align:left}
        .freq-table thead th{background:rgba(0,0,0,.04);font-weight:700}
        .freq-table tbody tr:hover{background:rgba(31,111,235,.06)}
        .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace}
        .num{text-align:right}
    </style>
</head>
<body>

<div class="freq-wrap">
    <div class="freq-title">Zeichenhäufigkeit</div>

    <form class="freq-form" method="post" action="durchschnitt.php">
        <textarea name="text" placeholder="Text hier eingeben..."><?php echo escapeForDisplay($inputText); ?></textarea>
        <div class="freq-actions">
            <button type="submit" name="submit" value="1">Berechnen</button>
        </div>
    </form>

    <?php if (isset($_POST['submit'])): ?>
        <div class="freq-meta">
            Gesamtanzahl an Zeichen: <span class="mono"><?php echo (int)$totalChars; ?></span>
        </div>

        <?php if ($totalChars === 0): ?>
            <div class="freq-meta">Keine Zeichen eingegeben.</div>
        <?php else: ?>
            <table class="freq-table">
                <thead>
                <tr>
                    <th>Zeichen</th>
                    <th class="num">Absolut</th>
                    <th class="num">Relativ</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($frequencies as $ch => $count):
                    $relative = $count / $totalChars;
                    $label = formatCharacterLabel($ch);
                    ?>
                    <tr>
                        <td class="mono"><?php echo escapeForDisplay($label); ?></td>
                        <td class="num mono"><?php echo (int)$count; ?></td>
                        <td class="num mono"><?php echo number_format($relative * 100, 2); ?>%</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>
