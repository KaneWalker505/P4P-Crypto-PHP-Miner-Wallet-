<?php

$nodeUrl = "https://php-crypto.wasmer.app/?path=";
$chainUrl = "https://php-crypto.wasmer.app/chain.json";
$action = $argv[1] ?? 'help';
//echo $nodeUrl;
function sha256d($data) {
    return hash('sha256', hash('sha256', $data));
}

switch ($action) {
    case 'generate':
        $key = bin2hex(random_bytes(16));
        $address = '0x' . substr(sha256d($key), 0, 20);
        echo "New p4p Wallet Address: $address\n";
        echo "Private Key: $key\n(WRITE KEY SAFE AND STORE IT)\n";
        break;

    case 'send':
        if (!isset($argv[2], $argv[3], $argv[4], $argv[5])) {
            die("Usage: p4pwallet send <from> <to> <amount> <privkey>\n");
        }
        $tx = [
            'from' => $argv[2],
            'to' => $argv[3],
            'amount' => (float)$argv[4],
            'timestamp' => time(),
            'key' => $argv[5]
        ];

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($tx)
            ]
        ];
        $context = stream_context_create($opts);
        $res = file_get_contents("$nodeUrl/tx", false, $context);
        echo "Transaction Sent: $res\n";
        break;

    case 'balance':
        if (!isset($argv[2])) die("Usage: p4pwallet balance <address>\n");
        $target = $argv[2];
        $chain = json_decode(@file_get_contents("$chainUrl"), true);
        $balance = 0.0;
        if ($chain) {
            foreach ($chain as $block) {
                foreach ($block['transactions'] as $tx) {
                    if ($tx['to'] === $target) $balance += $tx['amount'];
                    if ($tx['from'] === $target) $balance -= $tx['amount'];
                }
            }
        }
        echo "$target: $balance p4p coins\n";
        break;

    default:
        echo "Commands: generate | send | balance\n";
        break;
}

?>

